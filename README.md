# query-forge

Query Forge is a visual query builder for Wordpress in the Elementor ecosystem. 

=== Query Forge ===

Contributors: queryforge
Tags: elementor, query builder, posts, custom post types, visual builder
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual Node-Based Query Builder for Elementor. Build complex WordPress queries with an intuitive drag-and-drop interface.

== Description ==

Query Forge is a powerful visual query builder for Elementor that lets you create sophisticated WordPress queries without writing code. Using a node-based interface powered by React Flow, you can visually connect data sources, filters, sorting, and logic to build exactly the queries you need.

= Key Features =

* Visual Node-Based Interface - Drag and drop nodes to build queries visually
* Post Type Support - Query Posts, Pages, and all Custom Post Types
* Taxonomy Filtering - Filter by categories, tags, and custom taxonomies
* Basic Meta Filtering - Filter by custom fields with operators (=, !=, LIKE)
* Flexible Sorting - Sort by Date, Title, or ID
* Include/Exclude Posts - Fine-tune which posts appear in your results
* AND Logic - Combine multiple filters with AND logic
* Standard Pagination - Built-in pagination support
* Save & Import Queries - Save your query configurations for reuse
* Elementor Integration - Seamlessly integrates with Elementor widgets and templates

= Perfect For =

* Building custom post grids and loops
* Creating related posts sections
* Filtering content by custom fields
* Displaying posts from multiple post types
* Creating dynamic content sections

= Free vs Pro =

Free Version Includes:
* One source node per query (Posts, Pages, all CPTs)
* Basic meta filters (single key, =, !=, LIKE operators)
* Taxonomy filters
* AND-only logic
* Basic sorting (Date, Title, ID)
* Standard pagination
* Static literal values only

Pro Version Unlocks:
* Multiple source nodes per query
* Advanced data sources (Users, Comments, SQL Tables, REST APIs)
* Multiple meta keys and advanced operators (>, <, BETWEEN, IN, etc.)
* OR logic and nested logic groups
* Advanced sorting (Random, Menu Order, Meta Value)
* Dynamic data tags ({{ current_post_id }}, {{ current_user_id }}, etc.)
* SQL Joins
* AJAX pagination, Load More, and Infinite Scroll
* Related content logic
* Context-aware behavior

[Upgrade to Pro →](https://queryforgeplugin.com)

== Installation ==

= From WordPress.org =

1. Go to Plugins → Add New
2. Search for "Query Forge"
3. Click Install Now
4. Click Activate

= Manual Installation =

1. Download the plugin zip file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the zip file and click Install Now
4. Click Activate

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Elementor 3.0.0 or higher (must be installed and activated)

= Building the Editor JavaScript from Source =

The plugin ships with a pre-built editor bundle (assets/js/qf-editor.bundle.js and assets/js/style-qf-editor.css). The human-readable source code for the editor is available at:

**https://github.com/queryforgeplugin/Query-Forge**

The source uses React (via WordPress wp-element), React Flow, and a standard JavaScript build toolchain (webpack).

To build the editor assets from source:

1. Clone the repository: `git clone https://github.com/queryforgeplugin/Query-Forge.git`
2. Install Node.js (v16 or higher recommended) and npm.
3. In the plugin root directory, run: `npm install`
4. Run the build script: `npm run build`

The build process compiles the React components and utilities in `src/` (including `src/editor-app.js`, `src/components/`, and `src/utils/`) into the bundled files used by the plugin.

== Frequently Asked Questions ==

= Does this work with Elementor Pro? =

Yes! Query Forge works with both Elementor Free and Elementor Pro.

= Can I use this with Custom Post Types? =

Yes! The Free version supports all Custom Post Types. Simply select your CPT in the Source node.

= What's the difference between Free and Pro? =

The Free version provides essential query building capabilities for standard WordPress content. Pro unlocks advanced features like dynamic data, multiple data sources, complex logic, and advanced pagination options. See the "Free vs Pro" section above for details.

= Can I save my queries? =

Yes! You can save query configurations and import them later for reuse across different widgets.

= Does this work with page builders other than Elementor? =

No, Query Forge is specifically designed for Elementor and requires Elementor to be installed and activated.

= Is this plugin translation-ready? =

Yes, Query Forge is translation-ready and includes a .pot file for translators.

== Screenshots ==

1. Visual node-based query builder interface
2. Source node configuration
3. Filter node with taxonomy and meta options
4. Sort and pagination settings
5. Query results displayed in Elementor

== Changelog ==

= 1.0.0 =
* Initial release
* Visual node-based query builder
* Post type, taxonomy, and basic meta filtering
* Standard pagination support
* Save and import query functionality
* Elementor widget integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Query Forge. Install and activate to start building visual queries in Elementor.

== Support ==

For support, feature requests, and documentation, please visit:
* Query Forge Website: https://queryforgeplugin.com
* Support Forum: https://queryforgeplugin.com/support

== Credits ==

Built with:
* React Flow (https://reactflow.dev) - Node-based UI framework
* Elementor (https://elementor.com) - Page builder integration

== License ==

Query Forge is licensed under the GPL v2 or later.

Need more power? Upgrade to Query Forge Pro (https://queryforgeplugin.com) for advanced features like dynamic data, multiple data sources, SQL joins, and more.
