<?php
/** Pass DESEARCH_API_KEY only through the process environment, never argv or files. */
wp_set_current_user( 1 );
$key = getenv( 'DESEARCH_API_KEY' );
if ( ! $key ) { throw new RuntimeException( 'DESEARCH_API_KEY is required' ); }
try {
	update_option( Desearch_Plugin::OPTION, 't1124-invalid-key', false );
	$invalid = Desearch_Plugin::search( 'WordPress' );
	if ( ! is_wp_error( $invalid ) ) { throw new RuntimeException( 'Invalid key unexpectedly succeeded' ); }
	echo 'PASS live invalid key: ' . $invalid->get_error_code() . "\n";
	update_option( Desearch_Plugin::OPTION, $key, false );
	$results = Desearch_Plugin::search( 'WordPress plugin developer handbook' );
	if ( is_wp_error( $results ) ) { throw new RuntimeException( 'Live search failed: ' . $results->get_error_code() ); }
	if ( ! count( $results ) ) { throw new RuntimeException( 'Live search returned no titled results' ); }
	echo 'PASS live titled search: ' . count( $results ) . " results\n";
	foreach ( array_slice( $results, 0, 3 ) as $result ) {
		echo wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) . "\n";
	}
} finally {
	delete_option( Desearch_Plugin::OPTION );
}
