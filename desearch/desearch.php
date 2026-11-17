<?php
/**
 * Plugin Name: Desearch
 * Plugin URI: https://github.com/Desearch-ai/desearch-wordpress
 * Description: Search the public web with Desearch from your WordPress administration area.
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Desearch
 * Author URI: https://desearch.ai
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: desearch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Desearch_Plugin {
	const OPTION = 'desearch_api_key';
	const ENDPOINT = 'https://mcp.desearch.ai/mcp';

	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
	}

	public static function menu() {
		add_management_page( __( 'Desearch', 'desearch' ), __( 'Desearch', 'desearch' ), 'manage_options', 'desearch', array( __CLASS__, 'page' ) );
	}

	/** One explicit search, no retry, telemetry, or local result storage. */
	public static function search( $query ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'desearch_forbidden', __( 'Administrator access is required.', 'desearch' ) );
		}
		$key = get_option( self::OPTION, '' );
		if ( ! is_string( $key ) || '' === trim( $key ) ) {
			return new WP_Error( 'desearch_missing_key', __( 'Save a Desearch API key before searching.', 'desearch' ) );
		}
		$query = is_string( $query ) ? trim( $query ) : '';
		if ( '' === $query || strlen( $query ) > 2000 ) {
			return new WP_Error( 'desearch_query', __( 'Enter a query of 1 to 2,000 bytes.', 'desearch' ) );
		}
		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'             => 45,
				'redirection'         => 0,
				'limit_response_size' => 1048576,
				'headers'             => array(
					'Authorization'        => 'Bearer ' . $key,
					'Content-Type'         => 'application/json',
					'Accept'               => 'application/json, text/event-stream',
					'MCP-Protocol-Version' => '2025-03-26',
				),
				'body'                => wp_json_encode(
					array(
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => 'tools/call',
						'params'  => array( 'name' => 'search', 'arguments' => array( 'query' => $query ) ),
					)
				),
			)
		);
		// Upstream error text can contain credentials or query text. Never expose it.
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'desearch_network', __( 'Desearch could not be reached. Try again later.', 'desearch' ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 401 === $status || 403 === $status ) {
			return new WP_Error( 'desearch_auth', __( 'Desearch rejected the request. Check your API key and account access.', 'desearch' ) );
		}
		if ( 200 !== $status ) {
			return new WP_Error( 'desearch_http', __( 'Desearch could not complete the request. Check your account limits or try later.', 'desearch' ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ( $body['jsonrpc'] ?? '' ) !== '2.0' || ( $body['id'] ?? null ) !== 1 ) {
			return self::invalid_response();
		}
		if ( isset( $body['error'] ) || ! empty( $body['result']['isError'] ) ) {
			return new WP_Error( 'desearch_service', __( 'Desearch could not complete the search. Check your API key and account limits.', 'desearch' ) );
		}
		$data = $body['result']['structuredContent'] ?? null;
		// MCP implementations may carry the same structured payload as a text block.
		if ( ! is_array( $data ) && isset( $body['result']['content'] ) && is_array( $body['result']['content'] ) ) {
			foreach ( $body['result']['content'] as $block ) {
				if ( is_array( $block ) && ( $block['type'] ?? '' ) === 'text' && is_string( $block['text'] ?? null ) ) {
					$data = json_decode( $block['text'], true );
					if ( is_array( $data ) && isset( $data['results'] ) ) {
						break;
					}
				}
			}
		}
		if ( ! is_array( $data ) || ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			return self::invalid_response();
		}
		$results = array();
		foreach ( array_slice( $data['results'], 0, 50 ) as $item ) {
			if ( ! is_array( $item ) || ! is_string( $item['title'] ?? null ) || ! is_string( $item['url'] ?? null ) ) {
				return self::invalid_response();
			}
			$url = esc_url_raw( $item['url'], array( 'http', 'https' ) );
			if ( in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && wp_parse_url( $url, PHP_URL_HOST ) && '' !== trim( $item['title'] ) ) {
				$results[] = array( 'title' => $item['title'], 'url' => $url );
			}
		}
		return $results;
	}

	private static function invalid_response() {
		return new WP_Error( 'desearch_response', __( 'Desearch returned an unreadable response. Try again later.', 'desearch' ) );
	}

	/** Handle only nonce-checked administrator submissions. */
	public static function submit( $post ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'desearch_forbidden', __( 'Administrator access is required.', 'desearch' ) );
		}
		if ( ! isset( $post['_wpnonce'] ) || ! is_string( $post['_wpnonce'] ) || ! wp_verify_nonce( $post['_wpnonce'], 'desearch_action' ) ) {
			return new WP_Error( 'desearch_nonce', __( 'The form expired. Reload the page and try again.', 'desearch' ) );
		}
		$action = isset( $post['desearch_action'] ) && is_string( $post['desearch_action'] ) ? $post['desearch_action'] : '';
		if ( 'save' === $action ) {
			if ( ! empty( $post['remove_key'] ) ) {
				delete_option( self::OPTION );
				return null;
			}
			$key = isset( $post['api_key'] ) && is_string( $post['api_key'] ) ? trim( $post['api_key'] ) : '';
			if ( '' === $key ) {
				return null;
			}
			if ( strlen( $key ) > 512 || preg_match( '/[^\x21-\x7E]/', $key ) ) {
				return new WP_Error( 'desearch_key_format', __( 'Enter a valid API key without spaces or control characters.', 'desearch' ) );
			}
			update_option( self::OPTION, $key, false );
			return null;
		}
		if ( 'search' === $action ) {
			return self::search( $post['query'] ?? '' );
		}
		return new WP_Error( 'desearch_action', __( 'Unknown form action.', 'desearch' ) );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = null;
		$query = '';
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === $method ) {
			// Nonce and capabilities are checked in submit() before any effect.
			$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$result = self::submit( $post );
			$query = isset( $post['query'] ) && is_string( $post['query'] ) ? substr( $post['query'], 0, 2000 ) : '';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Desearch', 'desearch' ); ?></h1>
			<p><?php esc_html_e( 'Search the public web from your administration area. Only administrators with permission to manage settings can use this page.', 'desearch' ); ?></p>
			<?php if ( is_wp_error( $result ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
			<?php elseif ( 'POST' === $method && null === $result ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'desearch' ); ?></p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'API key', 'desearch' ); ?></h2>
			<p><?php esc_html_e( 'Your key is stored in this site’s WordPress database and is never displayed here. Site database administrators can access it. Saving a key does not contact Desearch.', 'desearch' ); ?></p>
			<p><a href="https://console.desearch.ai/api-keys" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get a Desearch API key', 'desearch' ); ?></a></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=desearch' ) ); ?>">
				<?php wp_nonce_field( 'desearch_action' ); ?>
				<input type="hidden" name="desearch_action" value="save">
				<label for="desearch-api-key"><?php esc_html_e( 'New API key (leave blank to keep the saved key)', 'desearch' ); ?></label><br>
				<input id="desearch-api-key" name="api_key" type="password" value="" maxlength="512" autocomplete="new-password" class="regular-text">
				<p><?php echo get_option( self::OPTION, '' ) ? esc_html__( 'A key is saved.', 'desearch' ) : esc_html__( 'No key is saved.', 'desearch' ); ?></p>
				<p><label><input type="checkbox" name="remove_key" value="1"> <?php esc_html_e( 'Remove the saved key', 'desearch' ); ?></label></p>
				<?php submit_button( __( 'Save settings', 'desearch' ) ); ?>
			</form>
			<h2><?php esc_html_e( 'Search', 'desearch' ); ?></h2>
			<p><?php esc_html_e( 'When you select Search, this site sends your query and API key to mcp.desearch.ai over HTTPS. Desearch account usage limits and charges may apply. No requests are sent automatically. This plugin does not store queries or results.', 'desearch' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=desearch' ) ); ?>">
				<?php wp_nonce_field( 'desearch_action' ); ?>
				<input type="hidden" name="desearch_action" value="search">
				<label for="desearch-query"><?php esc_html_e( 'Search query', 'desearch' ); ?></label><br>
				<input id="desearch-query" name="query" type="search" required maxlength="2000" class="large-text" value="<?php echo esc_attr( $query ); ?>">
				<?php submit_button( __( 'Search', 'desearch' ) ); ?>
			</form>
			<?php if ( is_array( $result ) ) : ?>
				<h2><?php esc_html_e( 'Search results', 'desearch' ); ?></h2>
				<?php if ( empty( $result ) ) : ?>
					<p><?php esc_html_e( 'No results were returned.', 'desearch' ); ?></p>
				<?php else : ?>
					<ol>
					<?php foreach ( $result as $item ) : ?>
						<li><a href="<?php echo esc_url( $item['url'], array( 'http', 'https' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['title'] ); ?></a></li>
					<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

Desearch_Plugin::boot();
