<?php

/**
 * Dashboard Widget & Admin Promotional Notice for Ultimate Cursor Plugin.
 *
 * Displays a seasonal upgrade CTA on the WP Dashboard (widget) and on other
 * admin pages (admin notice) when the user does not have a Pro license.
 * Both widget and notice can be dismissed for 30 days, after which they
 * automatically re-appear.
 *
 * @package ultimate-cursor
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Ultimate Cursor Dashboard Widget class.
 */
class Ultimate_Cursor_Dashboard_Widget {

	/** @var self|null Singleton instance. */
	private static $instance = null;

	/** @var string Pricing page URL. */
	const PRICING_URL = 'https://wpxero.com/plugins/ultimate-cursor/pricing';

	/** @var int Number of days before a dismissed promo re-appears. */
	const DISMISS_DAYS = 30;

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register hooks.
	 */
	private function __construct() {
		// Never show promos to Pro users.
		if (class_exists('UltimateCursor') && UltimateCursor::is_premium_active()) {
			return;
		}

		add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('admin_notices', [$this, 'show_promotional_notice']);
		add_action('wp_ajax_uc_dismiss_promo_widget', [$this, 'ajax_dismiss_widget']);
		add_action('wp_ajax_uc_dismiss_promo_notice', [$this, 'ajax_dismiss_notice']);
	}

	/* ------------------------------------------------------------------
	 * Campaign configuration (single source of truth)
	 * ----------------------------------------------------------------*/

	/**
	 * Get the active campaign configuration or null when outside promo windows.
	 *
	 * @return array|null {
	 *     @type string $key               Internal key.
	 *     @type int    $discount           Discount percentage.
	 *     @type string $end_date           Y-m-d sale end date.
	 *     @type string $coupon             Coupon code.
	 *     @type string $widget_title       Dashboard widget title.
	 *     @type string $notice_title       Admin notice headline.
	 *     @type string $description        Short CTA text.
	 *     @type string $button_text        CTA button label.
	 *     @type string $accent             Primary accent hex colour.
	 *     @type string $accent_secondary   Secondary accent hex colour.
	 *     @type string $gradient           CSS background gradient.
	 *     @type string $icon               Campaign icon/emoji.
	 *     @type array  $features           Feature highlight list.
	 * }
	 */
	private function get_campaign() {
		// Allow testing via URL parameter.
		$test_halloween = isset($_GET['halloween']) && current_user_can('manage_options');
		$test_black_friday = isset($_GET['black_friday']) && current_user_can('manage_options');
		$now  = current_time('Y-m-d');
		$year = current_time('Y');

		$features = [
			__('Multiple Cursor & Background Effects Configurations', 'ultimate-cursor'),
			__('Element-Specific Cursors & Background Effects', 'ultimate-cursor'),
			__('Custom Cursor & Background Effects for Specific Pages', 'ultimate-cursor'),
			__('Advanced Animation Options', 'ultimate-cursor'),
			__('Priority Support', 'ultimate-cursor'),
		];

		// Halloween: October 15 – October 31.
		if ($test_halloween || ($now >= "$year-10-15" && $now <= "$year-10-31")) {
			return [
				'key'              => 'halloween',
				'discount'         => 25,
				'end_date'         => "$year-10-31",
				'coupon'           => 'HALLOWEEN',
				'widget_title'     => __('Ultimate Cursor — Halloween Sale 🎃', 'ultimate-cursor'),
				'notice_title'     => __('Halloween Sale — Ultimate Cursor Pro', 'ultimate-cursor'),
				'description'      => __('Unlock spooky-good premium cursor effects, advanced customisation & priority support this Halloween!', 'ultimate-cursor'),
				'button_text'      => __('Grab 25% OFF', 'ultimate-cursor'),
				'accent'           => '#ff6600',
				'accent_secondary' => '#a855f7',
				'gradient'         => 'linear-gradient(135deg, #1a0a2e 0%, #2d1150 35%, #4c1d95 70%, #7c3aed 100%)',
				'icon'             => '🎃',
				'features'         => $features,
			];
		}

		// Black Friday / Cyber Monday: November 1 – December 5.
		if ($test_black_friday || ($now >= "$year-11-01" && $now <= "$year-12-05")) {
			return [
				'key'              => 'black_friday',
				'discount'         => 25,
				'end_date'         => "$year-12-05",
				'coupon'           => 'BFCM',
				'widget_title'     => __('Ultimate Cursor — Black Friday Sale 🔥', 'ultimate-cursor'),
				'notice_title'     => __('Black Friday Sale — Ultimate Cursor Pro', 'ultimate-cursor'),
				'description'      => __('The biggest sale of the year! Unlock 10+ premium cursor effects, advanced customisation & priority support.', 'ultimate-cursor'),
				'button_text'      => __('Grab 25% OFF', 'ultimate-cursor'),
				'accent'           => '#f43f5e',
				'accent_secondary' => '#ec4899',
				'gradient'         => 'linear-gradient(135deg, #0f172a 0%, #1e1b4b 40%, #581c87 75%, #7c3aed 100%)',
				'icon'             => '🔥',
				'features'         => $features,
			];
		}

		// Regular promo windows: 20th of current month to 10th of next month.
		$day   = (int) current_time('j');
		$month = (int) current_time('n');

		if ($day >= 20 || $day <= 10) {
			if ($day >= 20) {
				$next_month = $month === 12 ? 1 : $month + 1;
				$next_year  = $month === 12 ? (int) $year + 1 : (int) $year;
				$end_date   = sprintf('%04d-%02d-10', $next_year, $next_month);
			} else {
				$end_date = sprintf('%s-%02d-10', $year, $month);
			}

			return [
				'key'              => 'regular',
				'discount'         => 20,
				'end_date'         => $end_date,
				'coupon'           => 'UNLOCKPRO',
				'widget_title'     => __('Ultimate Cursor — Limited Offer 🚀', 'ultimate-cursor'),
				'notice_title'     => __('Limited Time Offer — Ultimate Cursor Pro', 'ultimate-cursor'),
				'description'      => __('Upgrade to Ultimate Cursor Pro — premium effects, advanced customisation & priority support.', 'ultimate-cursor'),
				'button_text'      => __('Get Pro — 20% OFF', 'ultimate-cursor'),
				'accent'           => '#6366f1',
				'accent_secondary' => '#818cf8',
				'gradient'         => 'linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%)',
				'icon'             => '🚀',
				'features'         => $features,
			];
		}

		return null;
	}

	/* ------------------------------------------------------------------
	 * 30-day dismiss helpers (server-side via user meta)
	 * ----------------------------------------------------------------*/

	/**
	 * Check if a promo type was dismissed within the last 30 days.
	 *
	 * @param string $type 'widget' or 'notice'.
	 * @return bool
	 */
	private function is_dismissed($type) {
		$meta_key  = 'uc_dismissed_promo_' . $type;
		$dismissed = get_user_meta(get_current_user_id(), $meta_key, true);

		if (empty($dismissed)) {
			return false;
		}

		$dismissed_time = (int) $dismissed;
		$elapsed_days   = (time() - $dismissed_time) / DAY_IN_SECONDS;

		if ($elapsed_days >= self::DISMISS_DAYS) {
			// Expired — remove stale meta and allow display.
			delete_user_meta(get_current_user_id(), $meta_key);
			return false;
		}

		return true;
	}

	/**
	 * Record a dismissal timestamp for a promo type.
	 *
	 * @param string $type 'widget' or 'notice'.
	 */
	private function dismiss($type) {
		$meta_key = 'uc_dismissed_promo_' . $type;
		update_user_meta(get_current_user_id(), $meta_key, time());
	}

	/* ------------------------------------------------------------------
	 * Dashboard Widget
	 * ----------------------------------------------------------------*/

	/**
	 * Register the dashboard widget.
	 */
	public function add_dashboard_widget() {
		if (!current_user_can('manage_options')) {
			return;
		}

		$campaign = $this->get_campaign();
		if (!$campaign) {
			return;
		}

		if ($this->is_dismissed('widget')) {
			return;
		}

		wp_add_dashboard_widget(
			'ultimate_cursor_promo_widget',
			$campaign['widget_title'],
			[$this, 'render_dashboard_widget']
		);
	}

	/**
	 * Render the dashboard widget body.
	 */
	public function render_dashboard_widget() {
		$c = $this->get_campaign();
		if (!$c) {
			return;
		}
		$nonce = wp_create_nonce('uc_dismiss_promo_widget');
		$campaign_class = 'uc-campaign-' . esc_attr($c['key']);
?>
		<div class="uc-promo-widget <?php echo $campaign_class; ?>"
			style="--uc-accent:<?php echo esc_attr($c['accent']); ?>;--uc-accent-secondary:<?php echo esc_attr($c['accent_secondary']); ?>;background:<?php echo esc_attr($c['gradient']); ?>">

			<div class="uc-pw-glow"></div>

			<button type="button" class="uc-pw-dismiss" data-nonce="<?php echo esc_attr($nonce); ?>" title="<?php esc_attr_e('Dismiss for 30 days', 'ultimate-cursor'); ?>">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none">
					<path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
				</svg>
			</button>

			<div class="uc-pw-header">
				<span class="uc-pw-icon"><?php echo esc_html($c['icon']); ?></span>
				<span class="uc-pw-badge"><?php echo esc_html($c['discount']); ?>% OFF</span>
			</div>

			<p class="uc-pw-desc"><?php echo esc_html($c['description']); ?></p>

			<ul class="uc-pw-features">
				<?php foreach ($c['features'] as $feature) : ?>
					<li>
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none">
							<path d="M2.5 7l3 3 6-6" stroke="var(--uc-accent)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
						<?php echo esc_html($feature); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="uc-pw-countdown" data-end="<?php echo esc_attr($c['end_date']); ?>">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none">
					<circle cx="7" cy="7" r="6" stroke="#94a3b8" stroke-width="1.2" />
					<path d="M7 4v3.5l2.5 1.5" stroke="#94a3b8" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
				<span class="uc-pw-cd-label"><?php esc_html_e('Ends in:', 'ultimate-cursor'); ?></span>
				<span class="uc-pw-cd-value" data-role="countdown">--</span>
			</div>

			<div class="uc-pw-coupon">
				<span class="uc-pw-coupon-label"><?php esc_html_e('Use coupon:', 'ultimate-cursor'); ?></span>
				<button type="button" class="uc-pw-coupon-code" data-code="<?php echo esc_attr($c['coupon']); ?>">
					<span class="uc-pw-code-text"><?php echo esc_html($c['coupon']); ?></span>
					<span class="uc-pw-code-copied"><?php esc_html_e('Copied!', 'ultimate-cursor'); ?></span>
					<svg class="uc-pw-copy-icon" width="12" height="12" viewBox="0 0 12 12" fill="none">
						<rect x="4" y="4" width="7" height="7" rx="1.5" stroke="currentColor" stroke-width="1.2" />
						<path d="M8 4V2.5A1.5 1.5 0 006.5 1h-4A1.5 1.5 0 001 2.5v4A1.5 1.5 0 002.5 8H4" stroke="currentColor" stroke-width="1.2" />
					</svg>
				</button>
			</div>

			<a href="<?php echo esc_url(self::PRICING_URL); ?>" class="uc-pw-cta" target="_blank" rel="noopener">
				<?php echo esc_html($c['button_text']); ?>
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none">
					<path d="M3 7h8m0 0L8 4m3 3L8 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</a>
		</div>
	<?php
	}

	/* ------------------------------------------------------------------
	 * Admin Notice (non-dashboard pages)
	 * ----------------------------------------------------------------*/

	/**
	 * Show a slim promotional admin notice.
	 */
	public function show_promotional_notice() {
		if (!current_user_can('manage_options')) {
			return;
		}

		// Don't show on dashboard — the widget is already there.
		global $pagenow;
		if ($pagenow === 'index.php') {
			return;
		}

		$c = $this->get_campaign();
		if (!$c) {
			return;
		}

		if ($this->is_dismissed('notice')) {
			return;
		}

		$notice_id     = 'uc-promo-notice-' . $c['key'] . '-' . current_time('Y');
		$nonce          = wp_create_nonce('uc_dismiss_promo_notice');
		$campaign_class = 'uc-campaign-' . esc_attr($c['key']);
	?>
		<div id="<?php echo esc_attr($notice_id); ?>"
			class="notice uc-promo-notice <?php echo $campaign_class; ?>"
			style="--uc-accent:<?php echo esc_attr($c['accent']); ?>;--uc-accent-secondary:<?php echo esc_attr($c['accent_secondary']); ?>;background:<?php echo esc_attr($c['gradient']); ?>"
			data-campaign="<?php echo esc_attr($c['key']); ?>"
			data-nonce="<?php echo esc_attr($nonce); ?>">

			<div class="uc-pn-glow"></div>

			<div class="uc-pn-inner">
				<div class="uc-pn-badge-wrap">
					<span class="uc-pn-icon"><?php echo esc_html($c['icon']); ?></span>
					<span class="uc-pn-discount"><?php echo esc_html($c['discount']); ?>% OFF</span>
				</div>

				<div class="uc-pn-content">
					<strong class="uc-pn-title"><?php echo esc_html($c['notice_title']); ?></strong>
					<span class="uc-pn-desc"><?php echo esc_html($c['description']); ?></span>
				</div>

				<div class="uc-pn-actions">
					<span class="uc-pn-timer" data-end="<?php echo esc_attr($c['end_date']); ?>">
						<svg width="12" height="12" viewBox="0 0 14 14" fill="none">
							<circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2" />
							<path d="M7 4v3.5l2.5 1.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
						<strong data-role="countdown">--</strong>
					</span>

					<a href="<?php echo esc_url(self::PRICING_URL); ?>" class="uc-pn-btn" target="_blank" rel="noopener">
						<?php echo esc_html($c['button_text']); ?>
						<svg width="12" height="12" viewBox="0 0 14 14" fill="none">
							<path d="M3 7h8m0 0L8 4m3 3L8 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
					</a>
				</div>

				<button type="button" class="uc-pn-dismiss" title="<?php esc_attr_e('Dismiss for 30 days', 'ultimate-cursor'); ?>">
					<svg width="12" height="12" viewBox="0 0 14 14" fill="none">
						<path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
					</svg>
				</button>
			</div>
		</div>
	<?php
	}

	/* ------------------------------------------------------------------
	 * AJAX dismiss handlers (30-day server-side persistence)
	 * ----------------------------------------------------------------*/

	/**
	 * Persist widget dismissal for 30 days.
	 */
	public function ajax_dismiss_widget() {
		check_ajax_referer('uc_dismiss_promo_widget', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Forbidden', 403);
		}

		$this->dismiss('widget');
		wp_send_json_success();
	}

	/**
	 * Persist notice dismissal for 30 days.
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer('uc_dismiss_promo_notice', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error('Forbidden', 403);
		}

		$this->dismiss('notice');
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------
	 * Assets (CSS + JS)
	 * ----------------------------------------------------------------*/

	/**
	 * Enqueue inline styles and footer scripts on relevant admin pages.
	 */
	public function enqueue_assets($hook) {
		if (!current_user_can('manage_options')) {
			return;
		}

		$campaign = $this->get_campaign();
		if (!$campaign) {
			return;
		}

		// Attach inline CSS to an existing core handle.
		wp_add_inline_style('wp-admin', $this->get_css());

		// Print JS in footer.
		add_action('admin_footer', [$this, 'print_scripts']);
	}

	/**
	 * All CSS in one method — widget + notice.
	 */
	private function get_css() {
		return '
/* === Ultimate Cursor Promo Widget === */
#ultimate_cursor_promo_widget .inside { padding: 0; margin:0;  }
#ultimate_cursor_promo_widget .postbox-header { display: none; }

.uc-promo-widget {
	position: relative;
	color: #f1f5f9;
	// border-radius: 14px;
	padding: 28px 22px 22px;
	text-align: center;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
	overflow: hidden;
}

/* Ambient glow effect */
.uc-pw-glow {
	position: absolute;
	top: -40%;
	right: -20%;
	width: 200px;
	height: 200px;
	background: var(--uc-accent, #6366f1);
	border-radius: 50%;
	opacity: 0.12;
	filter: blur(60px);
	pointer-events: none;
	animation: uc-glow-pulse 4s ease-in-out infinite;
}

@keyframes uc-glow-pulse {
	0%, 100% { opacity: 0.10; transform: scale(1); }
	50% { opacity: 0.20; transform: scale(1.15); }
}

/* Header */
.uc-pw-header {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 10px;
	margin-bottom: 16px;
}

.uc-pw-icon {
	font-size: 28px;
	line-height: 1;
	animation: uc-icon-bounce 2s ease-in-out infinite;
}

@keyframes uc-icon-bounce {
	0%, 100% { transform: translateY(0); }
	50% { transform: translateY(-4px); }
}

.uc-pw-badge {
	display: inline-flex;
	align-items: center;
	background: var(--uc-accent, #6366f1);
	color: #fff;
	font-size: 12px;
	font-weight: 800;
	padding: 5px 14px;
	border-radius: 20px;
	letter-spacing: 0.8px;
	text-transform: uppercase;
	box-shadow: 0 2px 12px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.15);
	animation: uc-badge-glow 3s ease-in-out infinite;
}

@keyframes uc-badge-glow {
	0%, 100% { box-shadow: 0 2px 12px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.15); }
	50% { box-shadow: 0 2px 20px var(--uc-accent, rgba(99,102,241,0.5)), inset 0 1px 0 rgba(255,255,255,0.15); }
}

/* Description */
.uc-pw-desc {
	font-size: 13px;
	line-height: 1.65;
	color: #cbd5e1;
	margin: 0 0 16px;
}

/* Feature list */
.uc-pw-features {
	list-style: none;
	margin: 0 0 18px;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.uc-pw-features li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	color: #e2e8f0;
	justify-content: center;
}

.uc-pw-features li svg {
	flex-shrink: 0;
}

/* Countdown */
.uc-pw-countdown {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	color: #94a3b8;
	margin-bottom: 16px;
	background: rgba(255,255,255,0.05);
	padding: 6px 14px;
	border-radius: 8px;
	border: 1px solid rgba(255,255,255,0.08);
}

.uc-pw-cd-value {
	color: #f8fafc;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
	font-size: 13px;
}

/* Coupon */
.uc-pw-coupon {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	margin-bottom: 18px;
	font-size: 12px;
	color: #94a3b8;
}

.uc-pw-coupon-code {
	position: relative;
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: rgba(255,255,255,0.08);
	border: 1px dashed rgba(255,255,255,0.25);
	color: #f8fafc;
	font-family: "SF Mono", "Fira Code", "Courier New", monospace;
	font-size: 13px;
	font-weight: 700;
	letter-spacing: 2px;
	padding: 6px 14px;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.25s ease;
}

.uc-pw-coupon-code:hover {
	background: rgba(255,255,255,0.15);
	border-color: rgba(255,255,255,0.4);
	transform: translateY(-1px);
}

.uc-pw-copy-icon { opacity: 0.6; transition: opacity 0.2s; }
.uc-pw-coupon-code:hover .uc-pw-copy-icon { opacity: 1; }

.uc-pw-code-copied {
	display: none;
	position: absolute;
	inset: 0;
	background: #22c55e;
	color: #fff;
	border-radius: 7px;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
	font-size: 11px;
	font-weight: 600;
	letter-spacing: 0;
	align-items: center;
	justify-content: center;
}

.uc-pw-coupon-code.is-copied .uc-pw-code-copied { display: flex; }
.uc-pw-coupon-code.is-copied .uc-pw-code-text,
.uc-pw-coupon-code.is-copied .uc-pw-copy-icon { visibility: hidden; }

/* CTA Button */
.uc-pw-cta {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	background: var(--uc-accent, #6366f1);
	color: #fff !important;
	text-decoration: none !important;
	font-weight: 700;
	font-size: 13px;
	padding: 11px 28px;
	border-radius: 10px;
	transition: all 0.25s ease;
	box-shadow: 0 4px 14px rgba(0,0,0,0.3);
	width: 100%;
	box-sizing: border-box;
}

.uc-pw-cta:hover {
	transform: translateY(-2px);
	box-shadow: 0 6px 20px rgba(0,0,0,0.4);
	color: #fff !important;
	filter: brightness(1.1);
}

.uc-pw-cta:active {
	transform: translateY(0);
}

/* Dismiss button */
.uc-pw-dismiss {
	position: absolute;
	top: 10px;
	right: 10px;
	background: rgba(255,255,255,0.08);
	border: none;
	color: #64748b;
	font-size: 14px;
	cursor: pointer;
	line-height: 1;
	padding: 5px;
	border-radius: 6px;
	transition: all 0.2s;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.uc-pw-dismiss:hover {
	color: #f1f5f9;
	background: rgba(255,255,255,0.15);
}

/* === Campaign-specific widget styles === */

/* Halloween */
.uc-campaign-halloween .uc-pw-glow {
	background: #ff6600;
}

.uc-campaign-halloween .uc-pw-badge {
	background: linear-gradient(135deg, #ff6600, #ff8c00);
}

.uc-campaign-halloween .uc-pw-cta {
	background: linear-gradient(135deg, #ff6600, #ff8c00);
}

/* Black Friday */
.uc-campaign-black_friday .uc-pw-glow {
	background: #f43f5e;
}

.uc-campaign-black_friday .uc-pw-badge {
	background: linear-gradient(135deg, #f43f5e, #ec4899);
}

.uc-campaign-black_friday .uc-pw-cta {
	background: linear-gradient(135deg, #f43f5e, #ec4899);
}

/* Regular */
.uc-campaign-regular .uc-pw-glow {
	background: #6366f1;
}

.uc-campaign-regular .uc-pw-badge {
	background: linear-gradient(135deg, #6366f1, #818cf8);
}

.uc-campaign-regular .uc-pw-cta {
	background: linear-gradient(135deg, #6366f1, #818cf8);
}

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

.uc-pn-glow {
	position: absolute;
	top: -50%;
	right: -10%;
	width: 160px;
	height: 160px;
	background: var(--uc-accent, #6366f1);
	border-radius: 50%;
	opacity: 0.1;
	filter: blur(50px);
	pointer-events: none;
	animation: uc-glow-pulse 4s ease-in-out infinite;
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
	animation: uc-icon-bounce 2s ease-in-out infinite;
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
	margin-right:30px;
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
	transition: all 0.25s ease;
	box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.uc-pn-btn:hover {
	transform: translateY(-1px);
	box-shadow: 0 4px 16px rgba(0,0,0,0.3);
	color: #fff !important;
	filter: brightness(1.1);
}

.uc-pn-dismiss {
	position: absolute;
	top: 10px;
	right: 0px;
	transform: translateY(-50%);
	background: rgba(255,255,255,0.08);
	border: none;
	color: #64748b;
	cursor: pointer;
	padding: 5px;
	border-radius: 6px;
	transition: all 0.2s;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.uc-pn-dismiss:hover {
	color: #f1f5f9;
	background: rgba(255,255,255,0.15);
}

/* Campaign-specific notice styles */
.uc-promo-notice.uc-campaign-halloween .uc-pn-discount {
	background: linear-gradient(135deg, #ff6600, #ff8c00);
}
.uc-promo-notice.uc-campaign-halloween .uc-pn-btn {
	background: linear-gradient(135deg, #ff6600, #ff8c00) !important;
}

.uc-promo-notice.uc-campaign-black_friday .uc-pn-discount {
	background: linear-gradient(135deg, #f43f5e, #ec4899);
}
.uc-promo-notice.uc-campaign-black_friday .uc-pn-btn {
	background: linear-gradient(135deg, #f43f5e, #ec4899) !important;
}

.uc-promo-notice.uc-campaign-regular .uc-pn-discount {
	background: linear-gradient(135deg, #6366f1, #818cf8);
}
.uc-promo-notice.uc-campaign-regular .uc-pn-btn {
	background: linear-gradient(135deg, #6366f1, #818cf8) !important;
}

@media (max-width: 782px) {
	.uc-pn-inner {
		flex-direction: column;
		align-items: flex-start;
		gap: 10px;
		padding: 14px 44px 14px 16px;
	}
	.uc-pn-actions { flex-wrap: wrap; gap: 8px; }
	.uc-pn-btn { margin-left: 0; }
}
		';
	}

	/**
	 * Minimal JS — handles countdowns, clipboard copy, and dismiss persistence.
	 */
	public function print_scripts() {
	?>
		<script>
			(function() {
				/* --- Countdown helper --- */
				function ucUpdateCountdowns() {
					document.querySelectorAll('[data-role="countdown"]').forEach(function(el) {
						var container = el.closest('[data-end]');
						if (!container) return;
						var end = new Date(container.getAttribute('data-end') + 'T23:59:59').getTime();
						var diff = end - Date.now();
						if (diff <= 0) {
							el.textContent = '<?php echo esc_js(__('Ended', 'ultimate-cursor')); ?>';
							return;
						}
						var d = Math.floor(diff / 864e5);
						var h = Math.floor((diff % 864e5) / 36e5);
						var m = Math.floor((diff % 36e5) / 6e4);
						var s = Math.floor((diff % 6e4) / 1e3);
						if (d > 0) {
							el.textContent = d + 'd ' + h + 'h ' + m + 'm';
						} else {
							el.textContent = h + 'h ' + m + 'm ' + s + 's';
						}
					});
				}
				ucUpdateCountdowns();
				setInterval(ucUpdateCountdowns, 1000);

				/* --- Coupon copy --- */
				document.querySelectorAll('.uc-pw-coupon-code').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var code = btn.getAttribute('data-code');
						if (!code) return;
						var done = function() {
							btn.classList.add('is-copied');
							setTimeout(function() {
								btn.classList.remove('is-copied');
							}, 1500);
						};
						if (navigator.clipboard) {
							navigator.clipboard.writeText(code).then(done).catch(done);
						} else {
							var ta = document.createElement('textarea');
							ta.value = code;
							ta.style.cssText = 'position:fixed;left:-9999px';
							document.body.appendChild(ta);
							ta.select();
							try {
								document.execCommand('copy');
							} catch (e) {}
							document.body.removeChild(ta);
							done();
						}
					});
				});

				/* --- Widget dismiss (AJAX — 30-day server-side) --- */
				document.querySelectorAll('.uc-pw-dismiss').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var widget = btn.closest('.uc-promo-widget');
						if (widget) {
							widget.style.opacity = '0';
							widget.style.transform = 'scale(0.95)';
							widget.style.transition = 'all 0.3s ease';
						}
						var wrap = btn.closest('.postbox');
						setTimeout(function() {
							if (wrap) wrap.style.display = 'none';
						}, 300);
						var nonce = btn.getAttribute('data-nonce');
						var xhr = new XMLHttpRequest();
						xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
						xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
						xhr.send('action=uc_dismiss_promo_widget&nonce=' + encodeURIComponent(nonce));
					});
				});

				/* --- Notice dismiss (AJAX — 30-day server-side) --- */
				document.querySelectorAll('.uc-pn-dismiss').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var notice = btn.closest('.uc-promo-notice');
						if (notice) {
							notice.style.opacity = '0';
							notice.style.transform = 'translateY(-10px)';
							notice.style.transition = 'all 0.3s ease';
							setTimeout(function() {
								notice.style.display = 'none';
							}, 300);
						}
						var nonce = notice ? notice.getAttribute('data-nonce') : '';
						if (nonce) {
							var xhr = new XMLHttpRequest();
							xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
							xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
							xhr.send('action=uc_dismiss_promo_notice&nonce=' + encodeURIComponent(nonce));
						}
					});
				});
			})();
		</script>
<?php
	}
}

Ultimate_Cursor_Dashboard_Widget::instance();
