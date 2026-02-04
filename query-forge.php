<?php
/**
 * Plugin Name:       Query Forge
 * Plugin URI:        https://github.com/queryforgeplugin/Query-Forge
 * Description:       Visual Node-Based Query Builder for Elementor. Build complex WordPress queries with an intuitive drag-and-drop interface.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Query Forge
 * Author URI:        https://queryforgeplugin.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       query-forge
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

define( 'QUERY_FORGE_VERSION', '1.0.0' );
define( 'QUERY_FORGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUERY_FORGE_URL', plugin_dir_url( __FILE__ ) );
define( 'QUERY_FORGE_FILE', __FILE__ );

/**
 * Check if a plugin is active
 *
 * @since 1.0.0
 * @param string $plugin_file Plugin file path (e.g., 'elementor/elementor.php').
 * @return bool True if plugin is active, false otherwise.
 */
function query_forge_is_plugin_active( $plugin_file ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	return is_plugin_active( $plugin_file );
}

// Check if Elementor is installed and activated.
if ( ! query_forge_is_plugin_active( 'elementor/elementor.php' ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
	add_action( 'admin_notices', 'query_forge_missing_elementor_notice' );
	return;
}

/**
 * Display notice when Elementor is missing
 *
 * @since 1.0.0
 */
function query_forge_missing_elementor_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Query Forge requires Elementor to be installed and activated.', 'query-forge' ); ?></p>
	</div>
	<?php
}

// Load the main plugin class.
require_once QUERY_FORGE_PATH . 'includes/class-qf-plugin.php';

\Query_Forge\Plugin::instance();

