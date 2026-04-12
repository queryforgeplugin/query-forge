/**
 * Query Forge Widget Frontend JavaScript
 *
 * AJAX pagination and frontend search (debounced).
 * Search settings are read from [data-qf-instance-id] (instance root).
 *
 * @package Query_Forge
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var DEBOUNCE_MS = 400;
	var MIN_CHARS = 3;
	var initialized = false;

	function getCfg() {
		if (typeof QueryForgeWidget !== 'undefined') {
			return QueryForgeWidget;
		}
		return {};
	}

	function parseSettings($grid) {
		var settingsData = $grid.data('qf-settings');
		if (!settingsData || typeof settingsData === 'string') {
			var attr = $grid.attr('data-qf-settings');
			if (attr) {
				try {
					settingsData = JSON.parse(attr);
				} catch (e) {
					return null;
				}
			}
		}
		return settingsData || null;
	}

	function setSearchActive($scope, active) {
		var v = active ? '1' : '0';
		$scope.attr('data-qf-search-active', v);
		$scope.find('.qf-pagination').attr('data-qf-search-active', v);
	}

	function getSearchField($scope) {
		return $scope.attr('data-qf-search-field') || 'title';
	}

	function isSearchEnabled($scope) {
		return $scope.attr('data-qf-search-enabled') === '1';
	}

	function syncSearchInputs($scope, val) {
		$scope.find('.qf-search-input').val(val);
	}

	function applyGridResponse($scope, $grid, data) {
		if (!$grid.length || !data) {
			return;
		}
		$grid.html(data.html || '');
		if (data.pagination_html) {
			var $p = $scope.find('.qf-pagination');
			if ($p.length) {
				$p.html(data.pagination_html);
			}
		}
		if (data.results_summary_html) {
			var $rs = $scope.find('.qf-results-summary');
			if ($rs.length) {
				$rs.replaceWith(data.results_summary_html);
			}
		}
		var sa = data.search_active === '1' || data.search_active === true;
		setSearchActive($scope, sa);
	}

	function runGridRequest($scope, $grid, settings, options) {
		var cfg = getCfg();
		var data = {
			action: options.action || 'query_forge_load_more_posts',
			nonce: cfg.nonce,
			logic_json: settings.logic_json,
			widget_settings: JSON.stringify(settings.widget_settings || {}),
			paged: options.paged || 1,
			search_enabled: $scope.attr('data-qf-search-enabled') === '1' ? '1' : '0'
		};
		if (options.search_term !== undefined) {
			data.search_term = options.search_term;
		}
		if (data.search_enabled === '1' && options.search_term !== undefined) {
			data.search_field = options.search_field || getSearchField($scope);
		}

		$scope.addClass('qf-is-loading');

		$.ajax({
			url: cfg.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(res) {
				if (res.success && res.data) {
					applyGridResponse($scope, $grid, res.data);
					if (options.scroll !== false) {
						var top = $scope.offset();
						if (top) {
							$('html, body').animate({ scrollTop: top.top - 100 }, 400);
						}
					}
				} else {
					window.alert((res.data && res.data.message) || 'Error');
				}
			},
			error: function() {
				window.alert('Error loading posts. Please try again.');
			},
			complete: function() {
				$scope.removeClass('qf-is-loading');
			}
		});
	}

	function doSearch($scope, $grid, settings, term, paged) {
		runGridRequest($scope, $grid, settings, {
			action: 'qf_search',
			paged: paged || 1,
			search_term: term,
			search_field: getSearchField($scope),
			scroll: false
		});
	}

	function doPaginate($scope, $grid, settings, paged, searchTerm) {
		var opts = {
			action: 'query_forge_load_more_posts',
			paged: paged,
			scroll: true
		};
		if (searchTerm !== undefined) {
			opts.search_term = searchTerm;
			opts.search_field = getSearchField($scope);
		}
		runGridRequest($scope, $grid, settings, opts);
	}

	var debounceTimers = {};

	var QFWidgetHandler = {
		init: function() {
			if (initialized) {
				return;
			}
			initialized = true;
			this.initAjaxPagination();
			this.initSearch();
		},

		initAjaxPagination: function() {
			$(document).on('click', '[data-qf-instance-id] .qf-pagination.qf-pagination-ajax a', function(e) {
				e.preventDefault();
				var $link = $(this);
				var $scope = $link.closest('[data-qf-instance-id]');
				var $grid = $scope.find('.qf-grid').first();
				if (!$grid.length) {
					return;
				}
				var settings = parseSettings($grid);
				if (!settings || !settings.logic_json) {
					return;
				}
				var href = $link.attr('href');
				var paged = QFWidgetHandler.getPageFromUrl(href);
				if (!paged) {
					return;
				}
				var term = ($scope.find('.qf-search-input').first().val() || '').trim();
				if (isSearchEnabled($scope) && term.length >= MIN_CHARS) {
					doPaginate($scope, $grid, settings, paged, term);
				} else {
					doPaginate($scope, $grid, settings, paged);
				}
			});
		},

		initSearch: function() {
			$(document).on('input', '[data-qf-instance-id] .qf-search-input', function() {
				var $input = $(this);
				var $scope = $input.closest('[data-qf-instance-id]');
				var $grid = $scope.find('.qf-grid').first();
				if (!$grid.length || !isSearchEnabled($scope)) {
					return;
				}
				syncSearchInputs($scope, $input.val());

				var settings = parseSettings($grid);
				if (!settings || !settings.logic_json) {
					return;
				}
				var term = ($input.val() || '').trim();
				var key = $scope.attr('data-qf-instance-id') || 'x';

				if (debounceTimers[key]) {
					clearTimeout(debounceTimers[key]);
				}

				if (term.length === 0) {
					setSearchActive($scope, false);
					doSearch($scope, $grid, settings, '', 1);
					return;
				}
				if (term.length < MIN_CHARS) {
					return;
				}

				debounceTimers[key] = setTimeout(function() {
					setSearchActive($scope, true);
					doSearch($scope, $grid, settings, term, 1);
				}, DEBOUNCE_MS);
			});

			$(document).on('keydown', '[data-qf-instance-id] .qf-search-input', function(e) {
				if (e.key !== 'Enter') {
					return;
				}
				e.preventDefault();
				var $input = $(this);
				var $scope = $input.closest('[data-qf-instance-id]');
				var $grid = $scope.find('.qf-grid').first();
				if (!$grid.length || !isSearchEnabled($scope)) {
					return;
				}
				var settings = parseSettings($grid);
				if (!settings || !settings.logic_json) {
					return;
				}
				var term = ($input.val() || '').trim();
				var key = $scope.attr('data-qf-instance-id') || 'x';
				if (debounceTimers[key]) {
					clearTimeout(debounceTimers[key]);
				}
				if (term.length === 0) {
					setSearchActive($scope, false);
					doSearch($scope, $grid, settings, '', 1);
					return;
				}
				if (term.length < MIN_CHARS) {
					return;
				}
				setSearchActive($scope, true);
				doSearch($scope, $grid, settings, term, 1);
			});
		},

		getPageFromUrl: function(url) {
			if (!url) {
				return null;
			}
			var match = url.match(/[?&]paged=(\d+)/);
			return match ? parseInt(match[1], 10) : null;
		}
	};

	$(document).ready(function() {
		QFWidgetHandler.init();
	});

	if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks && typeof elementorFrontend.hooks.addAction === 'function') {
		elementorFrontend.hooks.addAction('frontend/element_ready/qf_smart_loop_grid.default', function() {
			QFWidgetHandler.init();
		});
	}

})(jQuery);
