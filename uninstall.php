<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes saved query options created by the plugin.
 *
 * @package Query_Forge
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$saved_queries = get_option( 'query_forge_saved_queries', [] );
if ( is_array( $saved_queries ) ) {
	foreach ( $saved_queries as $query_id => $meta ) {
		delete_option( $query_id );
	}
}
delete_option( 'query_forge_saved_queries' );
