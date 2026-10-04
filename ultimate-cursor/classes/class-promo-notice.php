<?php

/**
 * Upgrade notice for Ultimate Cursor.
 *
 * Shows a single, slim upgrade notice to administrators who do not have a
 * Pro license. Deliberately restrained:
 *   - Plugins screen only — never on unrelated admin pages or the dashboard.
 *   - Not before the plugin has been installed for DELAY_DAYS.
 *   - Dismissal is permanent for that campaign (the recurring offer is
 *     dismissed once, forever; a seasonal sale once per year).
 *   - Assets are only added on the screen where the notice is printed.
 *
 * @package ultimate-cursor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ultimate Cursor promo notice class.
 */
class Ultimate_Cursor_Promo_Notice {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Days after first activation before any promotion is shown.
	 *
	 * @var int
	 */
	const DELAY_DAYS = 7;

	/**
	 * User meta key holding the list of dismissed campaign ids.
	 *
	 * @var string
	 */
	const DISMISSED_META = 'ultimate_cursor_dismissed_promos';

	/**
	 * Nonce action / AJAX action for dismissing the notice.
	 *
	 * @var string
	 */
	const DISMISS_ACTION = 'ultimate_cursor_dismiss_promo';

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register hooks.
	 */
	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_promotional_notice' ) );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, array( $this, 'ajax_dismiss_notice' ) );
	}

	/*
	------------------------------------------------------------------
	 * Campaign configuration (single source of truth)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Get the active campaign configuration or null when outside promo windows.
	 *
	 * @return array|null {
	 *     @type string      $key          Internal key.
	 *     @type string      $id           Dismissal id (key, plus the year for seasonal sales).
	 *     @type int         $discount     Discount percentage.
	 *     @type string|null $end_date     Y-m-d sale end date, or null when there is no real deadline.
	 *     @type string      $notice_title Admin notice headline.
	 *     @type string      $description  Short CTA text.
	 *     @type string      $button_text  CTA button label.
	 *     @type string      $accent       Primary accent hex colour.
	 *     @type string      $gradient     CSS background gradient.
	 *     @type string      $icon         Campaign icon key (see get_icon_svg()).
	 * }
	 */
	private function get_campaign() {
		// Allow previewing a seasonal campaign via URL parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview toggle gated by capability check, no data is processed.
		$test_halloween = isset( $_GET['halloween'] ) && current_user_can( 'manage_options' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview toggle gated by capability check, no data is processed.
		$test_black_friday = isset( $_GET['black_friday'] ) && current_user_can( 'manage_options' );
		$now               = current_time( 'Y-m-d' );
		$year              = current_time( 'Y' );

		// Halloween: October 15 – October 31.
		if ( $test_halloween || ( $now >= "$year-10-15" && $now <= "$year-10-31" ) ) {
			return array(
				'key'          => 'halloween',
				'id'           => "halloween-$year",
				'discount'     => 25,
				'end_date'     => "$year-10-31",
				'notice_title' => __( 'Halloween Sale — Ultimate Cursor Pro', 'ultimate-cursor' ),
				'description'  => __( 'Unlock spooky-good premium cursor effects, advanced customisation & priority support this Halloween!', 'ultimate-cursor' ),
				'button_text'  => __( 'Grab 25% OFF', 'ultimate-cursor' ),
				'accent'       => '#ff6600',
				'gradient'     => 'linear-gradient(135deg, #1a0a2e 0%, #2d1150 35%, #4c1d95 70%, #7c3aed 100%)',
				'icon'         => 'pumpkin',
			);
		}

		// Black Friday / Cyber Monday: November 1 – December 5.
		if ( $test_black_friday || ( $now >= "$year-11-01" && $now <= "$year-12-05" ) ) {
			return array(
				'key'          => 'black_friday',
				'id'           => "black_friday-$year",
				'discount'     => 25,
				'end_date'     => "$year-12-05",
				'notice_title' => __( 'Black Friday Sale — Ultimate Cursor Pro', 'ultimate-cursor' ),
				'description'  => __( 'The biggest sale of the year! Unlock 10+ premium cursor effects, advanced customisation & priority support.', 'ultimate-cursor' ),
				'button_text'  => __( 'Grab 25% OFF', 'ultimate-cursor' ),
				'accent'       => '#f43f5e',
				'gradient'     => 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #581c87 75%, #7c3aed 100%)',
				'icon'         => 'flame',
			);
		}

		// Recurring offer: 20th of the month to the 10th of the next. It has
		// no real deadline, so it carries no end date (no countdown) and is
		// not described as time-limited.
		$day = (int) current_time( 'j' );

		if ( $day >= 20 || $day <= 10 ) {
			return array(
				'key'          => 'regular',
				'id'           => 'regular',
				'discount'     => 20,
				'end_date'     => null,
				'notice_title' => __( 'Ultimate Cursor Pro — 20% off', 'ultimate-cursor' ),
				'description'  => __( 'Upgrade to Ultimate Cursor Pro — premium effects, advanced customisation & priority support.', 'ultimate-cursor' ),
				'button_text'  => __( 'Get Pro — 20% OFF', 'ultimate-cursor' ),
				'accent'       => '#6366f1',
				'gradient'     => 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)',
				'icon'         => 'rocket',
			);
		}

		return null;
	}

	/**
	 * Inline SVG for a campaign icon key.
	 *
	 * Emojis render inconsistently (or as tofu boxes) on some OS/browser
	 * combinations, so campaign icons ship as SVGs drawn in the campaign
	 * accent color instead.
	 *
	 * @param string $key Icon key: 'pumpkin', 'flame', or 'rocket'.
	 * @return string SVG markup (safe, static; echo through wp_kses with
	 *                self::get_svg_kses_allowed()).
	 */
	private function get_icon_svg( $key ) {
		$icons = array(
			'pumpkin' => '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="var(--uc-accent)" aria-hidden="true" focusable="false"><path d="M13.5 4.5c.8-1.4 2-2.2 3.5-2.3l.6 1.9c-1.1.1-2 .6-2.6 1.5 3.9.4 7 3.5 7 8 0 4.7-3.4 8.4-7.6 8.4-.9 0-1.7-.2-2.4-.5-.7.3-1.5.5-2.4.5C5.4 22 2 18.3 2 13.6c0-4.4 3-7.5 6.8-8 1.3-.7 2.9-1.1 4.7-1.1zM12 7.6c-.9 0-1.7 2.7-1.7 6s.8 6 1.7 6 1.7-2.7 1.7-6-.8-6-1.7-6z"/></svg>',
			'flame'   => '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="var(--uc-accent)" aria-hidden="true" focusable="false"><path d="M12 2c.5 3.5-2.9 5.4-2.9 9a3.4 3.4 0 006.8.3c.7.9 1.1 2 1.1 3.2A5.5 5.5 0 0112 20a5.5 5.5 0 01-5.5-5.5c0-2.3 1.1-3.9 2.2-5.5C9.8 7.4 11.5 5.2 12 2zm5.8 7.2c1.4 1.6 2.2 3.5 2.2 5.3A8 8 0 0112 22a8 8 0 01-8-7.5c0-.2 0-.4 0-.6A7.6 7.6 0 0012 22a7.6 7.6 0 007.6-7.6c0-1.9-.7-3.7-1.8-5.2z"/></svg>',
			'rocket'  => '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="var(--uc-accent)" aria-hidden="true" focusable="false"><path d="M12 2c3 1.8 5 5.5 5 9.6l2.2 3.3-3.2-.5c-.9 1.4-2.3 2.5-4 3.1-1.7-.6-3.1-1.7-4-3.1l-3.2.5L7 11.6C7 7.5 9 3.8 12 2zm0 5.2a1.9 1.9 0 100 3.8 1.9 1.9 0 000-3.8zM8.5 18.7c-.3 1.2-1.2 2.3-2.7 3.3.4-1.7.8-2.9 1.4-3.8.4.2.8.4 1.3.5zm7 0c.5-.1.9-.3 1.3-.5.6.9 1 2.1 1.4 3.8-1.5-1-2.4-2.1-2.7-3.3z"/></svg>',
		);
		return isset( $icons[ $key ] ) ? $icons[ $key ] : '';
	}

	/**
	 * Allowed tags/attributes for echoing the campaign icon SVGs.
	 *
	 * @return array wp_kses allowed-HTML array.
	 */
	private function get_svg_kses_allowed() {
		return array(
			'svg'  => array(
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'fill'        => true,
				'aria-hidden' => true,
				'focusable'   => true,
			),
			'path' => array(
				'd'    => true,
				'fill' => true,
			),
		);
	}

	/*
	------------------------------------------------------------------
	 * Eligibility
	 * ----------------------------------------------------------------
	 */

	/**
	 * Whether the plugin has been installed long enough to show a promotion.
	 *
	 * @return bool
	 */
	private function is_past_install_delay() {
		/**
		 * Filter the number of days after first activation before the
		 * upgrade notice may appear.
		 *
		 * @param int $days Delay in days.
		 */
		$delay_days = (int) apply_filters( 'ultimate_cursor_promo_delay_days', self::DELAY_DAYS );

		return ( time() - UltimateCursor::get_installed_at() ) >= ( $delay_days * DAY_IN_SECONDS );
	}

	/**
	 * Whether the current user has dismissed a campaign.
	 *
	 * @param array $campaign Campaign config from get_campaign().
	 * @return bool
	 */
	private function is_dismissed( $campaign ) {
		$user_id   = get_current_user_id();
		$dismissed = get_user_meta( $user_id, self::DISMISSED_META, true );

		if ( is_array( $dismissed ) && in_array( $campaign['id'], $dismissed, true ) ) {
			return true;
		}

		// Honor a dismissal recorded by older versions (30-day timestamp):
		// someone who already said no to the recurring offer is not asked again.
		if ( 'regular' === $campaign['key'] && get_user_meta( $user_id, 'uc_dismissed_promo_notice', true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The campaign to show on the current screen for the current user, if any.
	 *
	 * Single gate used by both the notice and its assets, so nothing is
	 * enqueued on a screen where nothing is printed.
	 *
	 * @return array|null Campaign config, or null when nothing should show.
	 */
	private function get_visible_campaign() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		// Never show promos when Pro features are already available. Checked
		// here (not in the constructor) because the add-on's answer is only
		// reliable once everything has loaded.
		if ( UltimateCursor::is_premium_active() ) {
			return null;
		}

		// Plugins screen only.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return null;
		}

		if ( ! $this->is_past_install_delay() ) {
			return null;
		}

		$campaign = $this->get_campaign();
		if ( ! $campaign || $this->is_dismissed( $campaign ) ) {
			return null;
		}

		return $campaign;
	}

	/*
	------------------------------------------------------------------
	 * Admin notice
	 * ----------------------------------------------------------------
	 */

	/**
	 * Show a slim promotional admin notice.
	 */
	public function show_promotional_notice() {
		$c = $this->get_visible_campaign();
		if ( ! $c ) {
			return;
		}

		$nonce          = wp_create_nonce( self::DISMISS_ACTION );
		$campaign_class = 'uc-campaign-' . $c['key'];
		?>
		<div id="<?php echo esc_attr( 'uc-promo-notice-' . $c['id'] ); ?>"
			class="notice uc-promo-notice <?php echo esc_attr( $campaign_class ); ?>"
			style="--uc-accent:<?php echo esc_attr( $c['accent'] ); ?>;background:<?php echo esc_attr( $c['gradient'] ); ?>"
			data-campaign="<?php echo esc_attr( $c['id'] ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>">

			<div class="uc-pn-inner">
				<div class="uc-pn-badge-wrap">
					<span class="uc-pn-icon"><?php echo wp_kses( $this->get_icon_svg( $c['icon'] ), $this->get_svg_kses_allowed() ); ?></span>
					<span class="uc-pn-discount"><?php echo esc_html( $c['discount'] ); ?>% OFF</span>
				</div>

				<div class="uc-pn-content">
					<strong class="uc-pn-title"><?php echo esc_html( $c['notice_title'] ); ?></strong>
					<span class="uc-pn-desc"><?php echo esc_html( $c['description'] ); ?></span>
				</div>

				<div class="uc-pn-actions">
					<?php if ( $c['end_date'] ) : ?>
						<span class="uc-pn-timer" data-end="<?php echo esc_attr( $c['end_date'] ); ?>">
							<svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">
								<circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2" />
								<path d="M7 4v3.5l2.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
							<strong data-role="countdown">--</strong>
						</span>
					<?php endif; ?>

					<a href="<?php echo esc_url( UltimateCursor::get_pro_url( 'admin-notice-' . $c['key'], 'admin-notice' ) ); ?>" class="uc-pn-btn" target="_blank" rel="noopener">
						<?php echo esc_html( $c['button_text'] ); ?>
						<svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">
							<path d="M3 7h8m0 0L8 4m3 3L8 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</a>
				</div>

				<button type="button" class="uc-pn-dismiss" aria-label="<?php esc_attr_e( 'Dismiss this offer', 'ultimate-cursor' ); ?>" title="<?php esc_attr_e( 'Dismiss this offer', 'ultimate-cursor' ); ?>">
					<svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">
						<path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
					</svg>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist the dismissal of the campaign whose notice was closed.
	 *
	 * The id comes from the notice itself (so a campaign shown through the
	 * preview parameter, or one that ended while the page was open, is the
	 * one recorded) and is only stored if it has the shape of a campaign id.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( self::DISMISS_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$campaign_id = isset( $_POST['campaign'] ) ? sanitize_text_field( wp_unslash( $_POST['campaign'] ) ) : '';

		if ( preg_match( '/^(regular|(halloween|black_friday)-\d{4})$/', $campaign_id ) ) {
			$user_id   = get_current_user_id();
			$dismissed = get_user_meta( $user_id, self::DISMISSED_META, true );
			if ( ! is_array( $dismissed ) ) {
				$dismissed = array();
			}
			if ( ! in_array( $campaign_id, $dismissed, true ) ) {
				$dismissed[] = $campaign_id;
				update_user_meta( $user_id, self::DISMISSED_META, $dismissed );
			}
		}

		wp_send_json_success();
	}

	/*
	------------------------------------------------------------------
	 * Assets (CSS + JS)
	 * ----------------------------------------------------------------
	 */

	/**
	 * Add the notice styles and script — only where the notice is printed.
	 */
	public function enqueue_assets() {
		if ( ! $this->get_visible_campaign() ) {
			return;
		}

		// Attach inline CSS to an existing core handle.
		wp_add_inline_style( 'wp-admin', $this->get_css() );

		// Print JS in footer.
		add_action( 'admin_footer', array( $this, 'print_scripts' ) );
	}

	/**
	 * Notice styles. Static (no looping animation) and direction-neutral.
	 */
	private function get_css() {
		return '
/* === Ultimate Cursor Promo Notice === */
.uc-promo-notice {
	border: none !important;
	border-radius: 8px !important;
	padding: 0 !important;
	overflow: hidden;
	margin: 15px 0 !important;
	position: relative;
	box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.uc-pn-inner {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 14px 20px;
	color: #f1f5f9;
	flex-wrap: wrap;
	position: relative;
}

.uc-pn-badge-wrap {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-shrink: 0;
}

.uc-pn-icon {
	font-size: 22px;
	line-height: 1;
}

.uc-pn-discount {
	display: inline-flex;
	align-items: center;
	background: var(--uc-accent, #6366f1);
	color: #fff;
	font-size: 11px;
	font-weight: 800;
	padding: 4px 10px;
	border-radius: 14px;
	letter-spacing: 0.6px;
	text-transform: uppercase;
	box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.uc-pn-content {
	display: flex;
	flex-direction: column;
	gap: 2px;
	flex: 1;
	min-width: 200px;
}

.uc-pn-title {
	font-size: 13px;
	font-weight: 700;
	color: #fff;
	line-height: 1.3;
}

.uc-pn-desc {
	font-size: 12px;
	color: #cbd5e1;
	line-height: 1.4;
}

.uc-pn-actions {
	display: flex;
	align-items: center;
	gap: 14px;
	flex-shrink: 0;
	margin-inline-end: 30px;
}

.uc-pn-timer {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	font-size: 12px;
	color: #94a3b8;
	white-space: nowrap;
	background: rgba(255,255,255,0.06);
	padding: 5px 10px;
	border-radius: 6px;
}

.uc-pn-timer svg { opacity: 0.7; }

.uc-pn-timer strong {
	color: #fff;
	font-variant-numeric: tabular-nums;
}

.uc-pn-btn {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	background: var(--uc-accent, #6366f1) !important;
	color: #fff !important;
	text-decoration: none !important;
	font-size: 12px;
	font-weight: 700;
	padding: 8px 18px;
	border-radius: 8px;
	white-space: nowrap;
	box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.uc-pn-btn:hover,
.uc-pn-btn:focus {
	color: #fff !important;
	filter: brightness(1.1);
}

.uc-pn-dismiss {
	position: absolute;
	top: 10px;
	inset-inline-end: 0;
	transform: translateY(-50%);
	background: rgba(255,255,255,0.08);
	border: none;
	color: #cbd5e1;
	cursor: pointer;
	padding: 5px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.uc-pn-dismiss:hover,
.uc-pn-dismiss:focus {
	color: #fff;
	background: rgba(255,255,255,0.15);
}

.uc-promo-notice.uc-campaign-halloween .uc-pn-discount,
.uc-promo-notice.uc-campaign-halloween .uc-pn-btn {
	background: linear-gradient(135deg, #ff6600, #ff8c00) !important;
}

.uc-promo-notice.uc-campaign-black_friday .uc-pn-discount,
.uc-promo-notice.uc-campaign-black_friday .uc-pn-btn {
	background: linear-gradient(135deg, #f43f5e, #ec4899) !important;
}

.uc-promo-notice.uc-campaign-regular .uc-pn-discount,
.uc-promo-notice.uc-campaign-regular .uc-pn-btn {
	background: linear-gradient(135deg, #6366f1, #818cf8) !important;
}

@media (max-width: 782px) {
	.uc-pn-inner {
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
		padding-block: 14px;
		padding-inline: 16px 44px;
	}
	.uc-pn-actions { flex-wrap: wrap; gap: 8px; }
}
		';
	}

	/**
	 * Minimal JS — seasonal countdown and dismiss persistence.
	 */
	public function print_scripts() {
		?>
		<script>
			(function() {
				var notice = document.querySelector('.uc-promo-notice');
				if (!notice) return;

				/* --- Countdown (seasonal sales only; minute precision) --- */
				var timer = notice.querySelector('[data-end]');
				if (timer) {
					var out = timer.querySelector('[data-role="countdown"]');
					var end = new Date(timer.getAttribute('data-end') + 'T23:59:59').getTime();
					var tick = function() {
						var diff = end - Date.now();
						if (diff <= 0) {
							out.textContent = '<?php echo esc_js( __( 'Ended', 'ultimate-cursor' ) ); ?>';
							return;
						}
						var d = Math.floor(diff / 864e5);
						var h = Math.floor((diff % 864e5) / 36e5);
						var m = Math.floor((diff % 36e5) / 6e4);
						out.textContent = (d > 0 ? d + 'd ' : '') + h + 'h ' + m + 'm';
					};
					tick();
					setInterval(tick, 60000);
				}

				/* --- Dismiss (permanent for this campaign, stored server-side) --- */
				var btn = notice.querySelector('.uc-pn-dismiss');
				if (btn) {
					btn.addEventListener('click', function() {
						notice.style.display = 'none';
						var xhr = new XMLHttpRequest();
						xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>');
						xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
						xhr.send('action=<?php echo esc_js( self::DISMISS_ACTION ); ?>&nonce=' + encodeURIComponent(notice.getAttribute('data-nonce')) + '&campaign=' + encodeURIComponent(notice.getAttribute('data-campaign')));
					});
				}
			})();
		</script>
		<?php
	}
}

Ultimate_Cursor_Promo_Notice::instance();
