<?php
/** Run inside a disposable WordPress installation: wp eval-file tests/smoke.php. */
function desearch_check( $ok, $label ) {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	echo 'PASS: ' . $label . "\n";
}
function desearch_error( $result, $code ) {
	return is_wp_error( $result ) && $result->get_error_code() === $code;
}
wp_set_current_user( 1 );
delete_option( Desearch_Plugin::OPTION );
$requests = 0;
$mock = function ( $pre, $args, $url ) use ( &$requests ) {
	++$requests;
	desearch_check( $url === Desearch_Plugin::ENDPOINT && $args['redirection'] === 0, 'fixed HTTPS endpoint, redirects disabled' );
	$payload = json_decode( $args['body'], true );
	desearch_check( $payload['method'] === 'tools/call' && $payload['params']['name'] === 'search', 'released MCP search contract' );
	return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'result' => array( 'structuredContent' => array( 'results' => array( array( 'title' => '<script>unsafe</script>', 'url' => 'https://example.org/' ), array( 'title' => 'Unsafe URL', 'url' => 'javascript:alert(1)' ), array( 'title' => 'Relative URL', 'url' => '/wp-admin/' ) ) ) ) ) ) );
};
add_filter( 'pre_http_request', $mock, 10, 3 );
desearch_check( desearch_error( Desearch_Plugin::search( 'WordPress' ), 'desearch_missing_key' ) && $requests === 0, 'missing key makes no request' );
$nonce = wp_create_nonce( 'desearch_action' );
$save = array( '_wpnonce' => $nonce, 'desearch_action' => 'save', 'api_key' => 'dummy-test-key-1124' );
desearch_check( desearch_error( Desearch_Plugin::submit( array_merge( $save, array( '_wpnonce' => 'bad' ) ) ), 'desearch_nonce' ), 'bad nonce rejected' );
desearch_check( get_option( Desearch_Plugin::OPTION, '' ) === '', 'bad nonce has no storage effect' );
Desearch_Plugin::submit( $save );
desearch_check( get_option( Desearch_Plugin::OPTION ) === $save['api_key'], 'key saved' );
global $wpdb;
$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Desearch_Plugin::OPTION ) );
desearch_check( in_array( $autoload, array( 'no', 'off', 'auto-off' ), true ), 'key not autoloaded' );
Desearch_Plugin::submit( array_merge( $save, array( 'api_key' => '' ) ) );
desearch_check( get_option( Desearch_Plugin::OPTION ) === $save['api_key'], 'blank save preserves key' );
desearch_check( desearch_error( Desearch_Plugin::submit( array_merge( $save, array( 'api_key' => "bad\r\nheader" ) ) ), 'desearch_key_format' ), 'header injection rejected' );
desearch_check( desearch_error( Desearch_Plugin::search( '' ), 'desearch_query' ) && desearch_error( Desearch_Plugin::search( str_repeat( 'x', 2001 ) ), 'desearch_query' ) && $requests === 0, 'empty and excessive queries make no request' );
wp_set_current_user( 0 );
desearch_check( desearch_error( Desearch_Plugin::search( 'WordPress' ), 'desearch_forbidden' ) && desearch_error( Desearch_Plugin::submit( $save ), 'desearch_forbidden' ) && $requests === 0, 'unauthorized calls denied before request or mutation' );
ob_start(); Desearch_Plugin::page(); $html = ob_get_clean();
desearch_check( $html === '', 'unauthorized page empty' );
wp_set_current_user( 1 );
$results = Desearch_Plugin::search( 'WordPress' );
desearch_check( is_array( $results ) && count( $results ) === 1, 'structured payload accepted, unsafe and relative URLs removed' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( '_wpnonce' => $nonce, 'desearch_action' => 'search', 'query' => '<img src=x onerror=alert(1)>' );
ob_start(); Desearch_Plugin::page(); $html = ob_get_clean();
desearch_check( strpos( $html, '<script>unsafe</script>' ) === false && strpos( $html, '&lt;script&gt;unsafe&lt;/script&gt;' ) !== false, 'result title escaped' );
desearch_check( strpos( $html, '<img src=x' ) === false && strpos( $html, 'javascript:' ) === false && strpos( $html, $save['api_key'] ) === false, 'query escaped, unsafe URL and key absent from HTML' );
remove_filter( 'pre_http_request', $mock, 10 );
foreach ( array( 'network', '401', '429', 'malformed', 'rpc', 'tool', 'wrong-id', 'shape', 'text' ) as $case ) {
	$filter = function () use ( $case ) {
		if ( 'network' === $case ) { return new WP_Error( 'network', 'SECRET-UPSTREAM-DETAIL' ); }
		$body = 'SECRET-UPSTREAM-DETAIL';
		if ( 'rpc' === $case ) { $body = wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'error' => array( 'message' => 'SECRET-UPSTREAM-DETAIL' ) ) ); }
		if ( 'tool' === $case ) { $body = wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'result' => array( 'isError' => true, 'content' => array( array( 'type' => 'text', 'text' => 'SECRET-UPSTREAM-DETAIL' ) ) ) ) ); }
		if ( 'wrong-id' === $case ) { $body = '{"jsonrpc":"2.0","id":2,"result":{}}'; }
		if ( 'shape' === $case ) { $body = '{"jsonrpc":"2.0","id":1,"result":{"structuredContent":{"results":[null]}}}'; }
		if ( 'text' === $case ) { $body = wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'result' => array( 'content' => array( array( 'type' => 'text', 'text' => '{"results":[]}' ) ) ) ) ); }
		return array( 'response' => array( 'code' => is_numeric( $case ) ? (int) $case : 200 ), 'body' => $body );
	};
	add_filter( 'pre_http_request', $filter );
	$result = Desearch_Plugin::search( 'WordPress' );
	desearch_check( 'text' === $case ? $result === array() : is_wp_error( $result ) && strpos( $result->get_error_message(), 'SECRET' ) === false, 'safe handling: ' . $case );
	remove_filter( 'pre_http_request', $filter );
}
Desearch_Plugin::submit( array_merge( $save, array( 'remove_key' => '1' ) ) );
desearch_check( get_option( Desearch_Plugin::OPTION, '' ) === '', 'key removal' );
$_POST = array();
echo "SMOKE COMPLETE\n";
