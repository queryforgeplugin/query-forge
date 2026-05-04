<?php
/**
 * Admin settings hub: Contact, Import/Export Queries, Go Pro.
 *
 * @package Query_Forge
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query Forge admin page (top-level menu).
 */
class QF_Admin_Settings {

	private const MENU_SLUG = 'query-forge';

	public const TAB_CONTACT = 'contact';
	public const TAB_QUERIES  = 'queries';
	public const TAB_GO_PRO   = 'go-pro';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_menu', [ $this, 'remove_duplicate_submenu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_hub_assets' ] );
		add_action( 'admin_post_query_forge_support_form', [ $this, 'handle_support_form' ] );
	}

	/**
	 * Admin URL for a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function admin_page_url( string $tab = self::TAB_CONTACT ): string {
		$allowed = [ self::TAB_CONTACT, self::TAB_QUERIES, self::TAB_GO_PRO ];
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = self::TAB_CONTACT;
		}
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		return add_query_arg( 'tab', $tab, $url );
	}

	/**
	 * Current tab from request.
	 *
	 * @return string
	 */
	public static function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab navigation.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		$allowed = [ self::TAB_CONTACT, self::TAB_QUERIES, self::TAB_GO_PRO ];
		if ( in_array( $tab, $allowed, true ) ) {
			return $tab;
		}
		return self::TAB_CONTACT;
	}

	/**
	 * Register top-level menu page.
	 */
	public function add_settings_page(): void {
		add_menu_page(
			__( 'Query Forge', 'query-forge' ),
			__( 'Query Forge', 'query-forge' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_settings_page' ],
			'dashicons-filter',
			59
		);
	}

	/**
	 * Remove duplicate first submenu item.
	 */
	public function remove_duplicate_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Enqueue admin assets on our page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_hub_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'qf-admin-tabs',
			QUERY_FORGE_URL . 'assets/css/qf-admin-pro-tabs.css',
			[],
			'1.0.0'
		);

		wp_register_script(
			'qf-admin-hub',
			false,
			[ 'jquery' ],
			QUERY_FORGE_VERSION,
			true
		);
		wp_enqueue_script( 'qf-admin-hub' );
		wp_localize_script(
			'qf-admin-hub',
			'QueryForgeAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'query_forge_nonce' ),
			]
		);
	}

	/**
	 * Render full settings page with tabs.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::get_current_tab();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice keys.
		$msg = isset( $_GET['qf_message'] ) ? sanitize_key( wp_unslash( (string) $_GET['qf_message'] ) ) : '';

		?>
		<div class="wrap qf-admin-hub">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			if ( 'sent' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Thank you. Your message has been sent.', 'query-forge' ) . '</p></div>';
			} elseif ( 'empty' === $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please fill in all fields.', 'query-forge' ) . '</p></div>';
			} elseif ( 'invalid_email' === $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please enter a valid email address.', 'query-forge' ) . '</p></div>';
			} elseif ( 'error' === $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Something went wrong. Please try again later.', 'query-forge' ) . '</p></div>';
			}
			?>

			<h2 class="nav-tab-wrapper wp-clearfix">
				<a href="<?php echo esc_url( self::admin_page_url( self::TAB_CONTACT ) ); ?>" class="nav-tab <?php echo self::TAB_CONTACT === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Contact Us', 'query-forge' ); ?></a>
				<a href="<?php echo esc_url( self::admin_page_url( self::TAB_QUERIES ) ); ?>" class="nav-tab <?php echo self::TAB_QUERIES === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Import/Export Queries', 'query-forge' ); ?></a>
				<a href="<?php echo esc_url( self::admin_page_url( self::TAB_GO_PRO ) ); ?>" class="nav-tab <?php echo self::TAB_GO_PRO === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Go Pro', 'query-forge' ); ?></a>
			</h2>

			<div class="qf-admin-card">
				<?php
				if ( self::TAB_QUERIES === $tab ) {
					$this->render_queries_tab();
				} elseif ( self::TAB_GO_PRO === $tab ) {
					$this->render_go_pro_tab();
				} else {
					$this->render_contact_tab();
				}
				?>
			</div>
		</div>
		<script>
		(function() {
			var h = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
			var map = { 'contact': 'contact', 'queries': 'queries', 'go-pro': 'go-pro' };
			if (map[h]) {
				var base = window.location.href.split('#')[0];
				if (base.indexOf('page=<?php echo esc_js( self::MENU_SLUG ); ?>') !== -1 && base.indexOf('tab=') === -1) {
					window.location.replace(base + (base.indexOf('?') > -1 ? '&' : '?') + 'tab=' + encodeURIComponent(map[h]));
				}
			}
		})();
		</script>
		<?php
	}

	/**
	 * Contact / support form tab.
	 */
	public function render_contact_tab(): void {
		?>
		<h2><?php esc_html_e( 'Contact us', 'query-forge' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Send a message to the Query Forge team. We typically respond within a few business days.', 'query-forge' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'query_forge_support_form', 'query_forge_support_nonce' ); ?>
			<input type="hidden" name="action" value="query_forge_support_form" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="qf_support_name"><?php esc_html_e( 'Name', 'query-forge' ); ?></label></th>
					<td><input name="qf_support_name" type="text" id="qf_support_name" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="qf_support_email"><?php esc_html_e( 'Email', 'query-forge' ); ?></label></th>
					<td><input name="qf_support_email" type="email" id="qf_support_email" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="qf_support_message"><?php esc_html_e( 'Message', 'query-forge' ); ?></label></th>
					<td><textarea name="qf_support_message" id="qf_support_message" rows="6" class="large-text" required></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Send message', 'query-forge' ) ); ?>
		</form>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: plugin version */
					__( 'Query Forge version %s', 'query-forge' ),
					QUERY_FORGE_VERSION
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Handle support form POST.
	 */
	public function handle_support_form(): void {
		if ( ! isset( $_POST['query_forge_support_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['query_forge_support_nonce'] ) ), 'query_forge_support_form' ) ) {
			wp_safe_redirect( add_query_arg( 'qf_message', 'error', self::admin_page_url( self::TAB_CONTACT ) ) );
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( add_query_arg( 'qf_message', 'error', self::admin_page_url( self::TAB_CONTACT ) ) );
			exit;
		}

		$name    = isset( $_POST['qf_support_name'] ) ? sanitize_text_field( wp_unslash( $_POST['qf_support_name'] ) ) : '';
		$email   = isset( $_POST['qf_support_email'] ) ? sanitize_email( wp_unslash( $_POST['qf_support_email'] ) ) : '';
		$message = isset( $_POST['qf_support_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['qf_support_message'] ) ) : '';

		if ( '' === $name || '' === $message ) {
			wp_safe_redirect( add_query_arg( 'qf_message', 'empty', self::admin_page_url( self::TAB_CONTACT ) ) );
			exit;
		}

		if ( '' === $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'qf_message', 'invalid_email', self::admin_page_url( self::TAB_CONTACT ) ) );
			exit;
		}

		$to      = get_option( 'admin_email' );
		$subject = sprintf( '[%s] %s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), __( 'Query Forge support request', 'query-forge' ) );
		$body    = sprintf(
			"%s\n\n%s: %s\n%s: %s\n%s: %s\n\n%s\n",
			__( 'The following message was sent from the Query Forge support form.', 'query-forge' ),
			__( 'Name', 'query-forge' ),
			$name,
			__( 'Email', 'query-forge' ),
			$email,
			__( 'Plugin version', 'query-forge' ),
			QUERY_FORGE_VERSION,
			$message
		);
		$headers = [ 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>' ];

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( $sent ) {
			wp_safe_redirect( add_query_arg( 'qf_message', 'sent', self::admin_page_url( self::TAB_CONTACT ) ) );
		} else {
			wp_safe_redirect( add_query_arg( 'qf_message', 'error', self::admin_page_url( self::TAB_CONTACT ) ) );
		}
		exit;
	}

	/**
	 * Import / export queries tab.
	 */
	public function render_queries_tab(): void {
		$export_count     = (int) get_option( 'query_forge_export_count', 0 );
		$export_limit     = 5;
		$export_remaining = max( 0, $export_limit - $export_count );
		$limit_reached    = $export_remaining <= 0;

		$query_list = get_option( 'query_forge_saved_queries', [] );
		if ( ! is_array( $query_list ) ) {
			$query_list = [];
		}

		?>
		<h2><?php esc_html_e( 'Export queries', 'query-forge' ); ?></h2>
		<?php if ( ! $limit_reached ) : ?>
			<p class="description qf-export-intro-line"><?php esc_html_e( 'Select saved queries and download them as a JSON file for backup or migration.', 'query-forge' ); ?></p>
			<p class="description qf-export-remaining-line">
				<strong>
					<?php echo esc_html__( 'You have ', 'query-forge' ); ?>
					<span class="qf-export-count-nums"><?php echo esc_html( (string) (int) $export_remaining ); ?></span>
					<?php echo esc_html__( ' of ', 'query-forge' ); ?>
					<span class="qf-export-count-nums">5</span>
					<?php echo esc_html__( ' exports remaining.', 'query-forge' ); ?>
				</strong>
			</p>
			<p class="description qf-export-upgrade-line">
				<a href="<?php echo esc_url( self::admin_page_url( self::TAB_GO_PRO ) ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'query-forge' ); ?></a><?php esc_html_e( ' for unlimited exports.', 'query-forge' ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $limit_reached ) : ?>
			<div class="notice notice-warning inline qf-export-locked-notice" style="margin: 12px 0;">
				<p><?php esc_html_e( 'You have used all 5 of your free exports.', 'query-forge' ); ?> <a href="<?php echo esc_url( self::admin_page_url( self::TAB_GO_PRO ) ); ?>"><?php esc_html_e( 'Upgrade to Pro', 'query-forge' ); ?></a> <?php esc_html_e( 'for unlimited query exports.', 'query-forge' ); ?></p>
			</div>
		<?php else : ?>
			<div class="qf-queries-toolbar">
				<button type="button" class="button" id="qf-select-all-queries"><?php esc_html_e( 'Select all', 'query-forge' ); ?></button>
				<button type="button" class="button" id="qf-deselect-all-queries"><?php esc_html_e( 'Deselect all', 'query-forge' ); ?></button>
				<button type="button" class="button button-primary" id="qf-export-selected"><?php esc_html_e( 'Export selected', 'query-forge' ); ?></button>
			</div>
			<div class="qf-queries-table-wrap">
				<table class="widefat striped qf-queries-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="qf-cb-all" /></td>
							<th class="qf-sortable qf-sort" scope="col" data-qf-sort="name" aria-sort="none">
								<button type="button" class="qf-sort-btn"><?php esc_html_e( 'Name', 'query-forge' ); ?> <span class="qf-sort-ind" aria-hidden="true"></span></button>
							</th>
							<th class="qf-sortable qf-sort" scope="col" data-qf-sort="date" aria-sort="none">
								<button type="button" class="qf-sort-btn"><?php esc_html_e( 'Saved', 'query-forge' ); ?> <span class="qf-sort-ind" aria-hidden="true"></span></button>
							</th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( empty( $query_list ) ) {
							?>
							<tr class="qf-queries-empty"><td colspan="3"><?php esc_html_e( 'No saved queries yet.', 'query-forge' ); ?></td></tr>
							<?php
						} else {
							foreach ( $query_list as $qid => $meta ) {
								$name = isset( $meta['name'] ) ? (string) $meta['name'] : '';
								$date = isset( $meta['date'] ) ? (string) $meta['date'] : '';
								?>
								<tr data-qf-name="<?php echo esc_attr( $name ); ?>" data-qf-date="<?php echo esc_attr( $date ); ?>">
									<th scope="row" class="check-column">
										<input type="checkbox" class="qf-query-export-cb" name="query_ids[]" value="<?php echo esc_attr( $qid ); ?>" />
									</th>
									<td><?php echo esc_html( $name ); ?></td>
									<td><?php echo esc_html( $date ); ?></td>
								</tr>
								<?php
							}
						}
						?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div class="qf-import-zone">
			<h2><?php esc_html_e( 'Import queries', 'query-forge' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Upload a JSON file previously exported from Query Forge.', 'query-forge' ); ?></p>
			<div class="qf-import-controls">
				<input type="file" id="qf-import-file" accept=".json,application/json" />
				<button type="button" class="button button-primary" id="qf-import-submit"><?php esc_html_e( 'Import', 'query-forge' ); ?></button>
			</div>
			<div class="qf-import-feedback" aria-live="polite" role="status">
				<div id="qf-import-result" class="notice inline qf-import-result-notice" style="display:none;"></div>
			</div>
		</div>

		<?php
		$this->render_queries_tab_scripts( $limit_reached );
	}

	/**
	 * Inline scripts for queries tab.
	 *
	 * @param bool $limit_reached Whether export limit is reached.
	 */
	private function render_queries_tab_scripts( bool $limit_reached ): void {
		?>
		<script>
		(function($) {
			var ajaxUrl = (typeof QueryForgeAdmin !== 'undefined' && QueryForgeAdmin.ajaxUrl) ? QueryForgeAdmin.ajaxUrl : '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = (typeof QueryForgeAdmin !== 'undefined' && QueryForgeAdmin.nonce) ? QueryForgeAdmin.nonce : '<?php echo esc_js( wp_create_nonce( 'query_forge_nonce' ) ); ?>';

			function setRemainingText(n) {
				var $p = $('.qf-export-remaining-line');
				if (!$p.length) return;
				var num = parseInt(n, 10);
				var inner = '<strong><?php echo esc_js( __( 'You have ', 'query-forge' ) ); ?>' +
					'<span class="qf-export-count-nums">' + num + '</span><?php echo esc_js( __( ' of ', 'query-forge' ) ); ?>' +
					'<span class="qf-export-count-nums">5</span> ' +
					'<?php echo esc_js( __( 'exports remaining.', 'query-forge' ) ); ?></strong>';
				$p.html(inner);
			}

			function lockExportUi() {
				$('.qf-queries-toolbar, .qf-queries-table-wrap').remove();
				if ($('.qf-export-locked-notice').length) return;
				var go = '<?php echo esc_url( self::admin_page_url( self::TAB_GO_PRO ) ); ?>';
				var html = '<div class="notice notice-warning inline qf-export-locked-notice" style="margin:12px 0;"><p><?php echo esc_js( __( 'You have used all 5 of your free exports.', 'query-forge' ) ); ?> <a href="' + go + '"><?php echo esc_js( __( 'Upgrade to Pro', 'query-forge' ) ); ?></a> <?php echo esc_js( __( 'for unlimited query exports.', 'query-forge' ) ); ?></p></div>';
				$('.qf-export-upgrade-line').after(html);
				$('.qf-export-intro-line, .qf-export-remaining-line, .qf-export-upgrade-line').remove();
			}

			<?php if ( ! $limit_reached ) : ?>
			$('#qf-cb-all').on('change', function() {
				$('.qf-query-export-cb').prop('checked', $(this).prop('checked'));
			});
			$('#qf-select-all-queries').on('click', function() {
				$('.qf-query-export-cb, #qf-cb-all').prop('checked', true);
			});
			$('#qf-deselect-all-queries').on('click', function() {
				$('.qf-query-export-cb, #qf-cb-all').prop('checked', false);
			});
			$('#qf-export-selected').on('click', function() {
				var ids = [];
				$('.qf-query-export-cb:checked').each(function() { ids.push($(this).val()); });
				if (!ids.length) {
					alert('<?php echo esc_js( __( 'Select at least one query to export.', 'query-forge' ) ); ?>');
					return;
				}
				$.post(ajaxUrl, {
					action: 'query_forge_export_queries',
					nonce: nonce,
					query_ids: JSON.stringify(ids)
				}).done(function(resp) {
					if (!resp || !resp.success) {
						alert(resp && resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Export failed.', 'query-forge' ) ); ?>');
						return;
					}
					var payload = resp.data && resp.data.export ? resp.data.export : null;
					if (!payload) return;
					var blob = new Blob([JSON.stringify(payload, null, 2)], {type: 'application/json'});
					var d = new Date();
					var p2 = function(n) { return String(n).padStart(2, '0'); };
					var exportFileName = 'query-forge-export-' + d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()) + '-' + p2(d.getHours()) + '-' + p2(d.getMinutes()) + '-' + p2(d.getSeconds()) + '.json';
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = exportFileName;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(a.href);
					var rem = typeof resp.data.remaining === 'number' ? resp.data.remaining : 0;
					setRemainingText(rem);
					if (rem <= 0) lockExportUi();
				}).fail(function() {
					alert('<?php echo esc_js( __( 'Export failed.', 'query-forge' ) ); ?>');
				});
			});
			<?php endif; ?>

			function qfParseSavedDate(s) {
				if (!s) return 0;
				var t = Date.parse(s.replace(' ', 'T'));
				return isNaN(t) ? 0 : t;
			}

			var sortState = { key: null, dir: 'asc' };
			function qfSortRows(key) {
				var $tbody = $('.qf-queries-table tbody');
				if (!$tbody.length) return;
				var $rows = $tbody.find('tr').filter(function() {
					return $(this).attr('data-qf-name') !== undefined;
				});
				if ($rows.length < 2) return;
				if (sortState.key === key) {
					sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
				} else {
					sortState.key = key;
					sortState.dir = 'asc';
				}
				$('.qf-sort').attr('aria-sort', 'none');
				var $th = $('.qf-sort[data-qf-sort="' + key + '"]');
				$th.attr('aria-sort', sortState.dir === 'asc' ? 'ascending' : 'descending');
				var arr = $rows.toArray();
				arr.sort(function(a, b) {
					var av, bv;
					if (key === 'date') {
						av = qfParseSavedDate($(a).attr('data-qf-date'));
						bv = qfParseSavedDate($(b).attr('data-qf-date'));
					} else {
						av = ($(a).attr('data-qf-name') || '').toLowerCase();
						bv = ($(b).attr('data-qf-name') || '').toLowerCase();
					}
					var cmp = 0;
					if (key === 'date') {
						cmp = av - bv;
					} else {
						cmp = av < bv ? -1 : av > bv ? 1 : 0;
					}
					return sortState.dir === 'asc' ? cmp : -cmp;
				});
				$rows.detach();
				$tbody.append(arr);
			}

			$(document).on('click', '.qf-sort-btn', function(e) {
				e.preventDefault();
				var $th = $(this).closest('.qf-sort');
				var key = $th.attr('data-qf-sort');
				if (key) qfSortRows(key);
			});

			$('#qf-import-submit').on('click', function() {
				var fileInput = document.getElementById('qf-import-file');
				if (!fileInput || !fileInput.files || !fileInput.files[0]) {
					alert('<?php echo esc_js( __( 'Choose a JSON file first.', 'query-forge' ) ); ?>');
					return;
				}
				var importedFileName = fileInput.files[0].name || '';
				var fd = new FormData();
				fd.append('action', 'query_forge_import_queries');
				fd.append('nonce', nonce);
				fd.append('qf_import_file', fileInput.files[0]);
				$('#qf-import-result').hide().empty();
				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: fd,
					processData: false,
					contentType: false
				}).done(function(resp) {
					if (!resp || !resp.success) {
						var msg = resp && resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js( __( 'Import failed.', 'query-forge' ) ); ?>';
						$('#qf-import-result').addClass('notice-error').removeClass('notice-success').html('<p>' + $('<div/>').text(msg).html() + '</p>').show();
						return;
					}
					var d = resp.data || {};
					var safeName = $('<div/>').text(importedFileName).html();
					var html = '<p class="qf-import-success-line"><strong><?php echo esc_js( __( 'Success:', 'query-forge' ) ); ?></strong> <span class="qf-import-success-filename">' + safeName + '</span> <?php echo esc_js( __( 'imported.', 'query-forge' ) ); ?></p>';
					html += '<p class="qf-import-detail-line"><strong><?php echo esc_js( __( 'Imported:', 'query-forge' ) ); ?></strong> ' + (d.imported || 0) + '</p>';
					if (d.renames && d.renames.length) {
						html += '<ul class="qf-import-renames" style="margin-left:1.25em;">';
						d.renames.forEach(function(r) {
							html += '<li>' + $('<div/>').text(r.from).html() + ' → ' + $('<div/>').text(r.to).html() + '</li>';
						});
						html += '</ul>';
					}
					if (d.errors && d.errors.length) {
						html += '<p><strong><?php echo esc_js( __( 'Skipped rows:', 'query-forge' ) ); ?></strong></p><ul style="margin-left:1.25em;">';
						d.errors.forEach(function(e) {
							var row = (typeof e.index === 'number') ? (e.index + 1) : '';
							html += '<li><?php echo esc_js( __( 'Row', 'query-forge' ) ); ?> ' + row + ': ' + $('<div/>').text(e.reason || '').html() + '</li>';
						});
						html += '</ul>';
					}
					$('#qf-import-result').addClass('notice-success').removeClass('notice-error').html(html).show();
					window.setTimeout(function() { window.location.reload(); }, 2500);
				}).fail(function() {
					$('#qf-import-result').addClass('notice-error').removeClass('notice-success').html('<p><?php echo esc_js( __( 'Import failed.', 'query-forge' ) ); ?></p>').show();
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Go Pro tab with feature list and changelog.
	 */
	public function render_go_pro_tab(): void {
		?>
		<h2><?php esc_html_e( 'Unlock Query Forge Pro', 'query-forge' ); ?></h2>
		<p><?php esc_html_e( 'Everything in the free version, plus the tools to build dynamic, data-driven content without writing code.', 'query-forge' ); ?></p>
		<ul class="qf-go-pro-features">
			<li><?php esc_html_e( 'Unlimited query exports', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Multiple Source Nodes and mixed queries', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Users, Comments, SQL Tables, and REST API sources', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Dynamic context tags (current user, current post, URL parameters)', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Advanced operators (greater than, less than, EXISTS, NOT EXISTS)', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'AJAX pagination, Load More, and Infinite Scroll', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Sort by meta value, random order, and menu order', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Visual SQL joins', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Custom Card Templates (Gutenberg)', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Query result caching up to 7 days with auto-refresh', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Single Templates (15 styles)', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Dynamic Custom Fields with per-field typography, alignment, and color controls', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Preview Node: see live query results on the canvas as you build', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Source Preview: browse and search raw source content inline', 'query-forge' ); ?></li>
			<li><?php esc_html_e( 'Priority email support', 'query-forge' ); ?></li>
		</ul>
		<p>
			<a href="https://queryforgeplugin.com" class="button button-primary button-large" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Query Forge Pro', 'query-forge' ); ?></a>
		</p>
		<p class="description"><?php esc_html_e( '$49.95 / year. 1 site license. Cancel anytime.', 'query-forge' ); ?></p>

		<h2 style="margin-top:2em;"><?php esc_html_e( 'Changelog', 'query-forge' ); ?></h2>
		<?php
		$readme = QUERY_FORGE_PATH . 'readme.txt';
		if ( ! is_readable( $readme ) ) {
			echo '<p class="description">' . esc_html__( 'Changelog not available.', 'query-forge' ) . '</p>';
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local readme only.
		$raw = file_get_contents( $readme );
		if ( false === $raw || '' === $raw ) {
			echo '<p class="description">' . esc_html__( 'Changelog not available.', 'query-forge' ) . '</p>';
			return;
		}
		$pos = stripos( $raw, '== Changelog ==' );
		if ( false === $pos ) {
			echo '<p class="description">' . esc_html__( 'Changelog not available.', 'query-forge' ) . '</p>';
			return;
		}
		$section = substr( $raw, $pos + strlen( '== Changelog ==' ) );
		$next    = preg_split( '/\n== [^=]+ ==\s*\n/', $section, 2 );
		$section = $next[0];

		$lines        = preg_split( '/\r\n|\r|\n/', $section );
		$version      = null;
		$bullets      = [];
		$flush_blocks = static function ( $ver, $items ) {
			if ( '' === $ver || empty( $items ) ) {
				return;
			}
			echo '<h4 class="qf-changelog-version">' . esc_html( $ver ) . '</h4>';
			echo '<ul class="qf-changelog-list">';
			foreach ( $items as $item ) {
				$item = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $item );
				echo '<li>' . wp_kses_post( $item ) . '</li>';
			}
			echo '</ul>';
		};
		foreach ( $lines as $line ) {
			$t = trim( $line );
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $t, $vm ) ) {
				$flush_blocks( $version, $bullets );
				$version = trim( $vm[1] );
				$bullets = [];
				continue;
			}
			if ( '' === $t || null === $version ) {
				continue;
			}
			if ( strpos( $t, '*' ) === 0 ) {
				$bullets[] = trim( $t, "* \t" );
			}
		}
		$flush_blocks( $version, $bullets );
	}
}
