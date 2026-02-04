<?php
/**
 * Query Result Wrapper
 *
 * Provides a WP_Query-like interface for non-post queries (Users, Comments, SQL, REST API).
 *
 * @package Query_Forge
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

/**
 * Query Result Wrapper Class
 *
 * Mimics WP_Query interface for non-post data sources.
 */
class QF_Query_Result_Wrapper {
	
	/**
	 * Array of result items
	 *
	 * @var array
	 */
	public $posts = [];
	
	/**
	 * Total number of results
	 *
	 * @var int
	 */
	public $found_posts = 0;
	
	/**
	 * Number of posts in current result set
	 *
	 * @var int
	 */
	public $post_count = 0;
	
	/**
	 * Maximum number of pages
	 *
	 * @var int
	 */
	public $max_num_pages = 1;
	
	/**
	 * Current post index
	 *
	 * @var int
	 */
	private $current_post_index = -1;
	
	/**
	 * Current post object
	 *
	 * @var object|null
	 */
	private $current_post = null;
	
	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param array $items Result items.
	 * @param int   $total Total number of items.
	 * @param int   $per_page Items per page.
	 */
	public function __construct( $items = [], $total = 0, $per_page = 10 ) {
		$this->posts = $items;
		$this->post_count = count( $items );
		$this->found_posts = $total > 0 ? $total : $this->post_count;
		$this->max_num_pages = $per_page > 0 ? ceil( $this->found_posts / $per_page ) : 1;
	}
	
	/**
	 * Check if there are posts
	 *
	 * @since 1.0.0
	 * @return bool True if there are more posts, false otherwise.
	 */
	public function have_posts() {
		return $this->current_post_index < ( $this->post_count - 1 );
	}
	
	/**
	 * Setup post data (mimics the_post())
	 *
	 * @since 1.0.0
	 * @return object|null Current post object.
	 */
	public function the_post() {
		$this->current_post_index++;
		if ( isset( $this->posts[ $this->current_post_index ] ) ) {
			$this->current_post = $this->posts[ $this->current_post_index ];
			// Set global post for WordPress functions
			global $post;
			$post = $this->current_post;
			return $this->current_post;
		}
		$this->current_post = null;
		return null;
	}
	
	/**
	 * Reset post data
	 *
	 * @since 1.0.0
	 */
	public function reset_postdata() {
		$this->current_post_index = -1;
		$this->current_post = null;
		wp_reset_postdata();
	}
}

