<?php
/**
 * Plugin Name: Query Forge
 * Plugin URI: https://github.com/queryforgeplugin/Query-Forge
 * Description: Visual node-based query builder for WordPress: Elementor widget and Gutenberg block with a shared React Flow editor.
 * Version: 1.3.4
 * Author: Query Forge Development
 * Author URI: https://queryforgeplugin.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: query-forge
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Tested up to: 6.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

define( 'QUERY_FORGE_VERSION', '1.3.4' );
define( 'QUERY_FORGE_PATH', plugin_dir_path( __FILE__ ) );
define( 'QUERY_FORGE_URL', plugin_dir_url( __FILE__ ) );
define( 'QUERY_FORGE_FILE', __FILE__ );

// Load the main plugin class.
require_once QUERY_FORGE_PATH . 'includes/class-qf-plugin.php';

register_activation_hook(
	QUERY_FORGE_FILE,
	static function () {
		require_once QUERY_FORGE_PATH . 'includes/class-qf-starter-queries.php';
		\Query_Forge\QF_Starter_Queries::seed_on_activation();
	}
);

\Query_Forge\Plugin::instance();
