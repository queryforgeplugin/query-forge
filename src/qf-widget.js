/**
 * Query Forge Widget Frontend JavaScript
 *
 * Handles AJAX pagination for numbered page links (when pagination type is AJAX).
 *
 * @package Query_Forge
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Query Forge Widget Handler
	 */
	var QFWidgetHandler = {

		/**
		 * Initialize widget handlers
		 */
		init: function() {
			this.initAjaxPagination();
		},

		/**
		 * Initialize AJAX Pagination
		 */
		initAjaxPagination: function() {
			// Only intercept clicks inside the Elementor widget. Gutenberg blocks also use .qf-pagination
			// for standard (full page) links — those must not get preventDefault when there is no Elementor wrapper.
			$(document).on('click', '.elementor-widget-qf_smart_loop_grid .qf-pagination a', function(e) {
				e.preventDefault();

				var $link = $(this);
				var $widget = $link.closest('.elementor-widget-qf_smart_loop_grid');
				if (!$widget.length) {
					$widget = $link.closest('.elementor-element').find('.elementor-widget-qf_smart_loop_grid');
				}

				if (!$widget.length) {
					return;
				}

				var $grid = $widget.find('.qf-grid');
				if (!$grid.length) {
					return;
				}

				// Get settings from data attribute
				var settingsData = $grid.data('qf-settings');
				// If data() doesn't parse JSON automatically, try parsing the attribute directly
				if (!settingsData || typeof settingsData === 'string') {
					var settingsAttr = $grid.attr('data-qf-settings');
					if (settingsAttr) {
						try {
							settingsData = JSON.parse(settingsAttr);
						} catch(e) {
							console.error('Query Forge: Failed to parse settings JSON', e);
							return;
						}
					}
				}
				if (!settingsData || !settingsData.logic_json) {
					console.error('Query Forge: Invalid query data');
					return;
				}

				var url = $link.attr('href');
				var paged = QFWidgetHandler.getPageFromUrl(url);

				if (!paged) {
					return;
				}

				// Show loading state
				$grid.css('opacity', '0.5');
				$grid.css('pointer-events', 'none');

				var cfg = typeof QueryForgeWidget !== 'undefined' ? QueryForgeWidget : (typeof QFWidget !== 'undefined' ? QFWidget : {});

				// Make AJAX request
				$.ajax({
					url: cfg.ajaxUrl,
					type: 'POST',
					data: {
						action: 'query_forge_load_more_posts',
						nonce: cfg.nonce,
						logic_json: settingsData.logic_json,
						widget_settings: JSON.stringify(settingsData.widget_settings || {}),
						paged: paged,
						widget_id: settingsData.widget_settings?.widget_id || ''
					},
					success: function(response) {
						if (response.success && response.data) {
							// Update grid content
							$grid.html(response.data.html || '');

							// Update pagination if provided (keeps active page in sync)
							if (response.data.pagination_html) {
								var $pagination = $widget.find('.qf-pagination');
								if ($pagination.length) {
									$pagination.html(response.data.pagination_html);
								}
							}

							// Update results summary if provided (e.g. "Showing 11–20 of 116 results")
							if (response.data.results_summary_html) {
								var $summary = $widget.find('.qf-results-summary');
								if ($summary.length) {
									$summary.replaceWith(response.data.results_summary_html);
								}
							}

							// Scroll to top of widget
							$('html, body').animate({
								scrollTop: $widget.offset().top - 100
							}, 500);
						} else {
							alert('Error loading posts: ' + (response.data?.message || 'Unknown error'));
						}
					},
					error: function(xhr, status, error) {
						console.error('Query Forge AJAX Error:', error);
						alert('Error loading posts. Please try again.');
					},
					complete: function() {
						// Restore grid state
						$grid.css('opacity', '1');
						$grid.css('pointer-events', 'auto');
					}
				});
			});
		},

		/**
		 * Extract page number from URL
		 */
		getPageFromUrl: function(url) {
			if (!url) {
				return null;
			}
			var match = url.match(/[?&]paged=(\d+)/);
			return match ? parseInt(match[1], 10) : null;
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		QFWidgetHandler.init();
	});

	// Also initialize if Elementor Frontend is available
	if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks && typeof elementorFrontend.hooks.addAction === 'function') {
		elementorFrontend.hooks.addAction('frontend/element_ready/qf_smart_loop_grid.default', function() {
			QFWidgetHandler.init();
		});
	}

})(jQuery);
