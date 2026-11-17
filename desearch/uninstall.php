<?php
/** Remove only this plugin's saved setting. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( is_multisite() ) {
	$desearch_offset = 0;
	do {
		$desearch_sites = get_sites( array( 'fields' => 'ids', 'number' => 100, 'offset' => $desearch_offset ) );
		foreach ( $desearch_sites as $desearch_site ) {
			switch_to_blog( $desearch_site );
			delete_option( 'desearch_api_key' );
			restore_current_blog();
		}
		$desearch_offset += 100;
	} while ( count( $desearch_sites ) === 100 );
} else {
	delete_option( 'desearch_api_key' );
}
