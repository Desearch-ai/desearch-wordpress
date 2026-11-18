<?php
/** Disposable network only; verifies per-site settings and uninstall cleanup. */
if ( ! is_multisite() ) {
	throw new RuntimeException( 'A disposable multisite network is required' );
}
wp_set_current_user( 1 );
$second = get_blog_id_from_url( '127.0.0.1', '/second/' );
if ( ! $second ) {
	$second = wpmu_create_blog( '127.0.0.1', '/second/', 'Second QA site', 1 );
}
if ( is_wp_error( $second ) ) {
	throw new RuntimeException( 'Could not create disposable second site' );
}
$sites = array( get_main_site_id(), (int) $second );
foreach ( $sites as $site ) {
	switch_to_blog( $site );
	$result = Desearch_Plugin::submit( array( '_wpnonce' => wp_create_nonce( 'desearch_action' ), 'desearch_action' => 'save', 'api_key' => 'dummy-site-' . $site ) );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Per-site key save failed' ); }
	update_option( 't1124_unrelated_setting', 'keep' );
	restore_current_blog();
}
foreach ( $sites as $site ) {
	switch_to_blog( $site );
	if ( get_option( Desearch_Plugin::OPTION ) !== 'dummy-site-' . $site ) { throw new RuntimeException( 'Site keys were not isolated' ); }
	restore_current_blog();
}
echo "PASS: two sites store isolated keys\n";
define( 'WP_UNINSTALL_PLUGIN', 'desearch/desearch.php' );
require WP_PLUGIN_DIR . '/desearch/uninstall.php';
foreach ( $sites as $site ) {
	switch_to_blog( $site );
	if ( get_option( Desearch_Plugin::OPTION, '' ) !== '' ) { throw new RuntimeException( 'A site key survived uninstall' ); }
	if ( get_option( 't1124_unrelated_setting' ) !== 'keep' ) { throw new RuntimeException( 'Unrelated option changed' ); }
	delete_option( 't1124_unrelated_setting' );
	restore_current_blog();
}
echo "PASS: network uninstall clears both keys and preserves unrelated settings\n";
