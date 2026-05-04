=== Query Forge ===

Contributors: queryforge
Tags: query builder, posts grid, custom post types, member directory, gutenberg, elementor, visual builder
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.6.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The Visual Query Engine and Display Tool for WordPress OR Elementor. Build complex post queries with a drag-and-drop interface, no code required!

== Description ==

https://www.youtube.com/watch?v=PEDp7-HbwQE

Query Forge lets you design and build queries from your posts, pages, or custom post types anywhere on your WordPress site with our unique Visual Node Canvas! And the Pro version goes deeper into the users or comments table, internal or external SQL tables, or any API data sources, all without writing code!

You can build a member directory, a WooCommerce product grid, a related posts section, an event listing, or a staff page. Really your imagination is the limit here. But the real power of Query Forge is the fact that you can not only filter by any field, or sort any way you want, but add MULTIPLE sources combined to create data sets that just are not possible with any other tool without writing code.

And the best part is that you can display those results in a fully styled card grid which works with the Gutenberg Block Editor OR Elementor (free or pro).

The process is simple. Open the canvas, drop a source node, connect your filter, sort, and save your logic. Then you stylize the card, and/or the page the way you want. Voila, results. No other plugin does this, not without having you write code, or with shortcodes. Query Forge does that all out of the box.

= Key Features =

* Visual Node-Based Interface — Drag and drop nodes to build queries visually
* Works with Gutenberg OR Elementor — one plugin, both editors
* Post Type Support — Query Posts, Pages, and all Custom Post Types
* Multiple Source Node configurations for combining multiple types of data (Pro Only)
* Advanced data sources — internal and external SQL tables and REST APIs, authenticated or unauthenticated (Pro Only)
* Taxonomy Filtering — Filter by categories, tags, and custom taxonomies
* Multi-Condition Filters — multiple conditions per filter node with internal AND/OR logic
* Meta Filtering — Filter by custom fields with operators (=, !=, LIKE, NOT LIKE, BETWEEN, IN, NOT IN, EXISTS, NOT EXISTS)
* Flexible Sorting — Sort by Date, Title, or ID
* Include/Exclude Posts — Fine-tune which posts appear in your results
* AND/OR Logic — Combine multiple filters with AND or OR logic
* Standard Pagination — Built-in pagination support
* Save, Export & Import Queries — Save and move your query configurations across multiple domains
* Card Design Controls — Typography, colors, alignment, image ratio, border radius, and shadow — no CSS required
* Inline Preview — See your query results directly in the editor canvas (Pro Only)
* Server-Side Rendering — Block output is rendered by PHP for fast, SEO-friendly results
* Frontend Search — Live AJAX search bar for any query output. Works in both Gutenberg and Elementor
* Query Result Caching — Cache rendered query output as HTML. Free supports up to 2 hours. Pro supports up to 7 days with auto-refresh

= Perfect For =

* Real estate listings. Filter by price range, bedrooms, or any property meta field.
* WooCommerce product grids. Filter by price, category, sale status, or any product meta.
* Job boards. Filter open positions by department, location, or employment type.
* Portfolio grids. Display work by type, client, or any custom taxonomy.
* Member directories and staff pages. Display users by role with avatar, bio, and custom fields.
* Related posts sections. Show posts from the same category, tag, or custom taxonomy automatically.
* Event listings. Sort by event date, filter by location or type, display with custom field details.
* Restaurant and location finders. Display locations filtered by city, cuisine, or amenity.
* Sports and leaderboards. Display rankings, stats, or standings from any data source.
* Any custom post type displayed as a fully styled, filterable card grid.

= Free vs Pro =

**Free Version Includes:**

* One source node per query (Posts, Pages, all CPTs)
* Taxonomy filters — categories, tags, and custom taxonomies (Has any of, Does not have, Has all of)
* Multi-Condition Filters — multiple conditions per filter node with internal AND/OR logic
* Meta filters — filter by custom fields with operators (=, !=, LIKE, NOT LIKE, BETWEEN, IN, NOT IN, EXISTS, NOT EXISTS)
* AND/OR logic within filter nodes
* Basic sorting (Date, Title, ID)
* Standard pagination
* 5 Canned Card Styles
* Full Card Design Controls — typography, colors, alignment, image ratio, border, shadow
* Works with Gutenberg block editor and Elementor
* Query result caching (up to 2 hours)
* Single Templates (5 styles)

**Pro Version Unlocks:**

* Multiple source nodes per query
* Advanced data sources (Users, Comments, SQL Tables, REST APIs)
* OR logic and nested logic groups across multiple source nodes
* Advanced sorting (Random, Menu Order, Meta Value)
* Dynamic data tags ({{ current_post_id }}, {{ current_user_id }}, etc.)
* SQL Joins
* AJAX pagination, Load More, and Infinite Scroll
* Related content logic
* Context-aware behavior
* Preview Node — see live query results on the canvas as you build, before saving
* Source Preview — browse and search raw source content inline on any Source node
* Custom Card Templates for Elementor (via Elementor Loop Item)
* Custom Card Templates for Gutenberg (shortcode-based system)
* Query result caching up to 7 days with auto-refresh via WP-Cron
* Dynamic Custom Fields with per-field typography, alignment, and color controls
* Single Templates (15 styles)

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

* WordPress 6.0 or higher
* PHP 7.4 or higher
* Elementor is optional — Query Forge works with the WordPress block editor without it

= Build Your Own =

The plugin ships with a pre-built editor bundle. The human-readable source code is available at:

**https://github.com/queryforgeplugin/Query-Forge**

The source uses React (via WordPress wp-element), React Flow, and a standard webpack build toolchain.

To build from source:

1. Clone the repository: `git clone https://github.com/queryforgeplugin/Query-Forge.git`
2. Install Node.js (v16 or higher) and npm
3. In the plugin root directory, run: `npm install`
4. Run the build script: `npm run build`

The build produces two bundles: `qf-editor.bundle.js` (Elementor) and `qf-block.bundle.js` (Gutenberg block editor).

== External services ==

This plugin does not connect to any third-party or external web services. All data is stored and processed within your own WordPress site.

Some fields in the visual builder may show example URLs such as `https://api.example.com/endpoint`. These are placeholder examples only. Query Forge itself does not send any data to those URLs or to any external API.

If you integrate Query Forge with your own APIs or services, please review the terms of service and privacy policies for those services.

== Frequently Asked Questions ==

= Does this work without Elementor? =

Yes! Query Forge works with the WordPress block editor (Gutenberg) without Elementor. Install and activate the plugin, add a Query Forge block to any page or post, and build your query. Elementor is completely optional.

= Does this work with Elementor? =

Yes! Query Forge includes the Smart Loop Grid widget for Elementor. It works with both Elementor Free and Elementor Pro. You do not need Elementor Pro to use Query Forge.

= Can I use this with Custom Post Types? =

Yes! The Free version supports all Custom Post Types. Simply select your CPT in the Source node.

= What are Multi-Condition Filters? =

Previously, each filter node handled one condition -- one field, one operator, one value. If you wanted to filter posts that had any of three tags, you needed three separate filter nodes wired through a Logic node.

Multi-Condition Filters let you do that inside a single filter node. Add as many conditions as you need, set the internal relation to AND or OR, and keep your canvas clean. One node, multiple conditions, same result.

= I got an upgrade notice. What does that mean? =

Your existing filter nodes use the previous single-condition format. Query Forge has detected those and is offering to upgrade them to the new Multi-Condition format.

This is opt-in for now. If you upgrade, Query Forge saves a timestamped backup of your canvas first, then converts automatically. Your existing queries keep working either way.

Important: the legacy format will be supported for 2 more point releases. After that, remaining legacy filter nodes will be converted automatically. Upgrade now to review the results on your own schedule.

= What's the difference between Free and Pro? =

The Free version provides essential query building for standard WordPress content using our visual builder. Pro unlocks advanced features like multiple sources, dynamic data, complex logic, and advanced pagination. See the "Free vs Pro" section above for details.

= Can I build queries with any CPT? =

Yes. Query Forge works with any Custom Post Type created by ACF, JetEngine, or any other plugin. The Free version allows one source node per query — filter, sort, and display content from any single CPT. The Pro version unlocks multiple source nodes to combine data from different sources in a single query.

= Can I save my queries? =

Yes! You can save query configurations and import them later for reuse across different blocks or widgets.

= Can I export queries to another WordPress install? =

Yes! Free users can export up to 5 queries and import unlimited queries across any site. Pro users get unlimited exports and imports.

= Does this work with page builders other than Elementor? =

Query Forge is designed for the WordPress block editor (Gutenberg) and Elementor. It does not have integrations for other page builders at this time.

= Is this plugin translation-ready? =

Yes, Query Forge is translation-ready and includes a .pot file for translators.

= Are there built-in card layouts? =

Yes. Both Free and Pro include five canned card styles (Vertical, Horizontal, Minimal, Grid, Magazine) with full design controls — typography, colors, alignment, image ratio, border radius, and shadow — directly in the sidebar. No CSS required.

= Can I see query results before saving? =

Yes, but only with Pro. The Preview node shows live results on the canvas as you build, updating automatically without saving. The Source Preview shows raw source content inline on any Source node.

== Screenshots ==

1. Visual node-based query builder interface
2. Query Forge block in the WordPress block editor with inline preview
3. Source node configuration
4. Filter node with multi-condition support
5. Card Design sidebar controls
6. Query results displayed in Gutenberg
7. Smart Loop Grid widget in Elementor

== Changelog ==

= 1.3.6.5 =

* **Bug fix:** Corrected a logic error where OR relations in the Logic node were not being preserved through the schema transform path, causing incorrect query results in certain filter configurations.
* **Multi-Condition Filters** — Filter nodes now support multiple conditions in a single node. Add as many conditions as you need, set the internal relation to AND or OR, and keep your canvas clean. One node, multiple conditions.
* **New operators** — BETWEEN, IN, NOT IN, EXISTS, and NOT EXISTS are now available in filter nodes for both taxonomy and meta fields.
* **Date picker** — Date fields now render a date picker input automatically, including side-by-side From/To pickers for BETWEEN conditions.
* **Filter node upgrade path** — Existing single-condition filter nodes are detected automatically. You will be prompted to upgrade to the new Multi-Condition format. Query Forge saves a timestamped backup of your canvas before converting. The legacy format will be supported for 2 more point releases.

= 1.3.6 =

* Added **Import/Export** Free — You can now move your queries across sites that you own. You have 5 exports, and unlimited imports. Once you hit your limit, you will need to upgrade to the pro version.
* Added **Import/Export** (PRO) — Unlimited Import and Export Queries across all your sites.
* Added **Admin Page** — Free users now have a contact form, import/export tab, and a pro call button.
* Cleaned up bugs.

= 1.3.5 =

* **Single Templates** — Click any result card to open the post in a full-page Query Forge template instead of the theme default. Renders inside the theme shell. Toggle sections on or off (title, image, content, excerpt, date, author, terms, navigation) with per-section typography and color controls. Free includes 5 styles (Vertical, Horizontal, Minimal List, Grid Card, Magazine). Pro includes 15 styles.
* **Field Mapping extended to Post Types and CPTs** (Pro) — Source nodes for Posts, Pages, and Custom Post Types now support field mapping. CPT sources use meta key discovery via dropdown; Posts and Pages use manual entry. Mapped fields resolve at render time for both Gutenberg and Elementor.
* **Dynamic Custom Fields** (Pro) — Add configurable display fields to any card output beyond the defaults. Define label, source path, enabled state, show/hide label, empty behavior, prefix, and suffix per field. Add, remove, and reorder fields in the Source node settings. Full per-field design controls: alignment, label color, value color, and typography (font family, weight, style, transform, line height) in both Gutenberg and Elementor.

= 1.3.4 =

* Added Onboarding Experience for new users.
* Added 4 new import presets for new users.
* Added dismissible starter notice on plugin page.
* Added Modal for 'Your First Query' in Gutenberg and Elementor.

= 1.3.3 =

* Added optional **query result caching** (Free) — cache rendered HTML and AJAX pagination payloads with transients; Target node controls TTL and manual flush; registry + FIFO eviction; cache bypass for administrators, WP_DEBUG, and the `query_forge_bypass_query_cache` filter; content saves clear cached results.
* **Parser:** `get_query()` now takes explicit page and posts-per-page arguments (no reliance on `$_GET` inside the parser).
* **Frontend Search** — Search field (title, content, or both), bar position (above, below, or both), and alignment from the **Gutenberg block sidebar** or **Elementor widget panel** (not the query graph); `get_query()` accepts optional `s` / `search_columns` (WordPress 6.2+); dedicated `qf_search` AJAX handler; instances with search enabled skip query-result cache; `data-qf-instance-id` root carries search metadata; `qf-widget.js` debounces input and coordinates AJAX pagination with search.
* **Requires WordPress 6.2+** for `search_columns` support used by frontend search.

= 1.3.2 =

* Fixed small bugs and documentation errors.

= 1.3.1 =

* Fixes Pagination (block) — Fixes missing page links (correct total page count when WordPress reports max_num_pages as 0, better base URL and current page on static/singular pages).
* Added Title link decoration (block) — New "Underline title link" control (maps to titleLinkDecoration / underline vs none on the title link).
* Added Custom Templates with Shortcodes for Gutenberg (PRO ONLY).

= 1.3.0 =

* Added **Query Forge block** for the WordPress block editor — the same visual canvas, server-rendered card output, and full sidebar design controls (typography, colors, alignment, image ratio, shadow) stored as block attributes.
* Plugin no longer requires Elementor — Elementor remains fully supported for the Smart Loop Grid widget; both integrations are active simultaneously.
* Inline block preview via ServerSideRender — see your query results directly in the block editor canvas.
* Raised minimum WordPress version to 6.0; tested up to 6.7.

= 1.2.1 =

* Added taxonomy filtering to the Filter node — filter by categories, tags, and any custom taxonomy registered to the selected post type.
* Taxonomy operators: Has any of (IN), Does not have (NOT IN), Has all of (AND).
* Live term search — type in the taxonomy value field to search and select terms by name.
* Fixed: query results now correctly include published posts only by default.
* Added Free/Pro type label to plugin header for clarity.

= 1.2.0 =

* Added post IDs to Preview node results — click any ID to copy it to the clipboard.
* Added Search to Preview node — filter results by title on demand.
* Added Source Preview (Pro) — browse and search raw source content inline on any Source node.

= 1.1.0 =

* Added Preview node (Pro) — see live post titles and result count as you build.
* Added NOT LIKE operator to filter conditions.
* Added UNION and UNION ALL relations to the Logic node.
* Added Card Design section — typography, colors, alignment, image ratio, border radius, and shadow.
* Added Read More button to canned card styles.
* Fixed: canvas with no complete path now correctly returns no results.
* Fixed: disconnected nodes are now visually dimmed on the canvas.
* Improved: query execution model rebuilt for more reliable results.

= 1.0.0 =

* Initial release.
* Visual node-based query builder.
* Post type, taxonomy, and basic meta filtering.
* Standard pagination support.
* Save and import query functionality.
* Elementor widget integration.

== Support ==

For support, feature requests, and documentation, please visit:
* Query Forge Website: https://queryforgeplugin.com
* Documentation: https://queryforgeplugin.com/documentation.html

== Credits ==

Built with:

* React Flow (https://reactflow.dev) — Node-based UI framework
* WordPress Block Editor (Gutenberg) — Block integration
* Elementor (https://elementor.com) — Widget integration

== License ==

Query Forge is licensed under the GPL v2 or later.

Need more power? Upgrade to Query Forge Pro (https://queryforgeplugin.com) for advanced features like multiple data sources, dynamic data, SQL joins, AJAX pagination, and more.