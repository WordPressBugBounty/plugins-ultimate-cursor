<?php

/**
 * Dashboard Widget for Ultimate Cursor Plugin
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
	/**
	 * The single class instance.
	 *
	 * @var $instance
	 */
	private static $instance = null;

	/**
	 * Get instance
	 */
	public static function instance() {
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Ultimate_Cursor_Dashboard_Widget constructor.
	 */
	private function __construct() {

		add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_styles']);
		add_action('admin_notices', [$this, 'show_promotional_notice']);
	}

	/**
	 * Add dashboard widget
	 */
	public function add_dashboard_widget() {
		// Debug: Always add the widget for testing

		// Only show to users who can manage options
		if (!current_user_can('manage_options')) {
			return;
		}

		// Check if user has dismissed the widget for this year
		if (get_user_meta(get_current_user_id(), 'uc_dismissed_bf_widget_' . gmdate('Y'), true)) {
			return;
		}

		// Check if it's promotional season (Halloween or Black Friday)
		if (!$this->is_promotional_season()) {
			return;
		}
		$campaign = $this->get_current_campaign();
		$widget_title = $campaign === 'halloween'
			? __('Ultimate Cursor Halloween Sale!', 'ultimate-cursor')
			: __('Ultimate Cursor Black Friday Sale!', 'ultimate-cursor');

		wp_add_dashboard_widget(
			'ultimate_cursor_promo_widget',
			$widget_title,
			[$this, 'render_dashboard_widget'],
			null,
			null,
			'column4',
			'high'
		);
	}

	/**
	 * Check if it's promotional season (Halloween or Black Friday)
	 */
	private function is_promotional_season() {
		// Allow testing with URL parameter for development
		if (isset($_GET['uc_test_promo_widget']) && current_user_can('manage_options')) {
			return true;
		}

		$current_date = current_time('Y-m-d');
		$current_year = gmdate('Y');

		// Halloween season: October 15th to October 31st
		// Black Friday season: November 1st to December 5th
		$halloween_start = $current_year . '-10-15';
		$halloween_end = $current_year . '-10-31';
		$bf_start = $current_year . '-11-01';
		$bf_end = $current_year . '-12-05';

		return ($current_date >= $halloween_start && $current_date <= $halloween_end) ||
			($current_date >= $bf_start && $current_date <= $bf_end);
	}

	/**
	 * Get current promotional campaign type
	 */
	private function get_current_campaign() {
		$current_date = current_time('Y-m-d');
		$current_year = gmdate('Y');

		// Halloween season: October 15th to October 31st
		$halloween_start = $current_year . '-10-15';
		$halloween_end = $current_year . '-10-31';

		if ($current_date >= $halloween_start && $current_date <= $halloween_end) {
			return 'halloween';
		}

		// Default to Black Friday for November-December period
		return 'black_friday';
	}

	/**
	 * Render the dashboard widget content
	 */
	public function render_dashboard_widget() {
		$campaign = $this->get_current_campaign();

		// Dynamic content based on campaign
		if ($campaign === 'halloween') {
			$discount_percentage = 25;
			$sale_end_date = gmdate('Y') . '-10-30';
			$title = __('Halloween Spooky Sale!', 'ultimate-cursor');
			$description = __('👻 Upgrade to Ultimate Cursor Pro - Spooky good deals with haunting cursor effects!', 'ultimate-cursor');
			$coupon_code = 'HALLOWEEN25';
			$button_text = __('🎃 Get Pro Now - 25% OFF', 'ultimate-cursor');
			$widget_class = 'ultimate-cursor-halloween-widget';
		} else {
			$discount_percentage = 25;
			$sale_end_date = gmdate('Y') . '-12-05';
			$title = __('Black Friday Mega Sale!', 'ultimate-cursor');
			$description = __('🚀 Upgrade to Ultimate Cursor Pro - 10+ premium effects, advanced customization & priority support!', 'ultimate-cursor');
			$coupon_code = 'BFCM25';
			$button_text = __('🛒 Get Pro Now - 25% OFF', 'ultimate-cursor');
			$widget_class = 'ultimate-cursor-black-friday-widget';
		}

?>
		<div class="<?php echo esc_attr($widget_class); ?>">
			<div class="uc-bf-header">
				<div class="uc-bf-badge">
					<span class="uc-bf-discount"><?php echo esc_html($discount_percentage); ?>% OFF</span>
				</div>
				<h3 class="uc-bf-title">
					<?php echo esc_html($title); ?>
				</h3>
			</div>

			<div class="uc-bf-content">
				<p class="uc-bf-description">
					<?php echo esc_html($description); ?>
				</p>

				<div class="uc-bf-countdown">
					<p class="uc-bf-countdown-label">
						<?php esc_html_e('⏰ Sale ends in:', 'ultimate-cursor'); ?>
					</p>
					<div class="uc-bf-countdown-timer" id="uc-countdown-timer" data-end-date="<?php echo esc_attr($sale_end_date); ?>">
						<div class="uc-countdown-item">
							<span class="uc-countdown-number" id="uc-days">00</span>
							<span class="uc-countdown-label">Days</span>
						</div>
						<div class="uc-countdown-item">
							<span class="uc-countdown-number" id="uc-hours">00</span>
							<span class="uc-countdown-label">Hours</span>
						</div>
						<div class="uc-countdown-item">
							<span class="uc-countdown-number" id="uc-minutes">00</span>
							<span class="uc-countdown-label">Minutes</span>
						</div>
						<div class="uc-countdown-item">
							<span class="uc-countdown-number" id="uc-seconds">00</span>
							<span class="uc-countdown-label">Seconds</span>
						</div>
					</div>
				</div>

				<div class="uc-bf-coupon">
					<p class="uc-bf-coupon-label">
						<?php esc_html_e('🎟️ Use Coupon Code:', 'ultimate-cursor'); ?>
					</p>
					<div class="uc-bf-coupon-code" onclick="ucCopyCouponCode(this)" title="Click to copy">
						<span class="uc-coupon-text"><?php echo esc_html($coupon_code); ?></span>
						<span class="uc-copy-icon">📋</span>
					</div>
					<p class="uc-bf-coupon-copied" id="uc-coupon-copied" style="display: none;">
						<?php esc_html_e('✅ Coupon code copied!', 'ultimate-cursor'); ?>
					</p>
				</div>

				<div class="uc-bf-actions">
					<?php
					$pricing_url = function_exists('ultimate_cursor_fs') ? ultimate_cursor_fs()->get_upgrade_url() : 'https://wpxero.com/plugins/ultimate-cursor/pricing';
					?>
					<a href="<?php echo esc_url($pricing_url); ?>"
						class="uc-bf-btn uc-bf-btn-primary uc-bf-btn-full">
						<?php echo esc_html($button_text); ?>
					</a>
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * Show promotional notice in admin
	 */
	public function show_promotional_notice() {
		// Only show to users who can manage options
		if (!current_user_can('manage_options')) {
			return;
		}

		// Check if it's promotional season
		if (!$this->is_promotional_season()) {
			return;
		}

		// Don't show on dashboard page (widget is already there)
		global $pagenow;
		if ($pagenow === 'index.php') {
			return;
		}

		$campaign = $this->get_current_campaign();

		// Get campaign-specific content
		if ($campaign === 'halloween') {
			$title = __('🎃 Halloween Spooky Sale - Ultimate Cursor Pro!', 'ultimate-cursor');
			$message = __('Get 25% OFF on Ultimate Cursor Pro! Spooky good deals with haunting cursor effects. Limited time offer!', 'ultimate-cursor');
			$coupon_code = 'HALLOWEEN25';
			$end_date = gmdate('Y') . '-10-30';
			$notice_class = 'uc-halloween-notice uc-promo-notice';
		} else {
			$title = __('🛒 Black Friday Mega Sale - Ultimate Cursor Pro!', 'ultimate-cursor');
			$message = __('Get 25% OFF on Ultimate Cursor Pro! 10+ premium effects, advanced customization & priority support. Don\'t miss out!', 'ultimate-cursor');
			$coupon_code = 'BFCM25';
			$end_date = gmdate('Y') . '-12-05';
			$notice_class = 'uc-black-friday-notice uc-promo-notice';
		}

		$pricing_url = function_exists('ultimate_cursor_fs') ? ultimate_cursor_fs()->get_upgrade_url() : 'https://wpxero.com/plugins/ultimate-cursor/pricing';

		// Check if notice should be hidden based on localStorage (server-side check)
		$notice_id = 'uc-notice-' . $campaign . '-' . gmdate('Y');
	?>
		<script type="text/javascript">
			// Immediately hide notice if dismissed (before DOM ready)
			(function() {
				const campaign = '<?php echo esc_js($campaign); ?>';
				const dismissKey = 'uc_dismissed_' + campaign + '_notice_' + new Date().getFullYear();
				const dismissData = localStorage.getItem(dismissKey);

				if (dismissData) {
					const dismissInfo = JSON.parse(dismissData);
					const now = new Date().getTime();
					const dismissTime = dismissInfo.timestamp;
					const hoursPassed = (now - dismissTime) / (1000 * 60 * 60);

					// Hide if less than 24 hours have passed
					if (hoursPassed < 24) {
						document.write('<style>#<?php echo esc_js($notice_id); ?> { display: none !important; }</style>');
					} else {
						// Remove expired dismissal
						localStorage.removeItem(dismissKey);
					}
				}
			})();
		</script>
		<div id="<?php echo esc_attr($notice_id); ?>" class="notice notice-info is-dismissible <?php echo esc_attr($notice_class); ?>" data-campaign="<?php echo esc_attr($campaign); ?>">
			<div class="uc-promo-notice-content">
				<div class="uc-promo-notice-left">
					<div class="uc-promo-notice-header">
						<h3><?php echo esc_html($title); ?></h3>
						<span class="uc-promo-badge">25% OFF</span>
					</div>
					<span class="uc-promo-countdown" data-end-date="<?php echo esc_attr($end_date); ?>">
						<?php esc_html_e('Ends in: ', 'ultimate-cursor'); ?><strong id="uc-notice-countdown">Loading...</strong>
					</span>

				</div>
				<div class="uc-promo-notice-actions">
					<div class="uc-promo-coupon">
						<strong><?php esc_html_e('COUPON CODE:', 'ultimate-cursor'); ?></strong>
						<code class="uc-promo-coupon-code" onclick="ucCopyNoticeCode(this)" title="Click to copy"><?php echo esc_html($coupon_code); ?></code>
						<span class="uc-copy-feedback" style="display: none;">✅ Copied</span>
					</div>
					<a href="<?php echo esc_url($pricing_url); ?>" class="button button-primary uc-promo-btn">
						<?php esc_html_e('Get Pro Now', 'ultimate-cursor'); ?>
					</a>
				</div>
			</div>
		</div>
	<?php
	}


	/**
	 * Enqueue dashboard styles
	 */
	public function enqueue_dashboard_styles($hook) {
		// Only load if promotional season and user can manage options
		if (!$this->is_promotional_season() || !current_user_can('manage_options')) {
			return;
		}

		// Load notice styles on all admin pages
		wp_enqueue_style('admin-bar');
		wp_add_inline_style('admin-bar', $this->get_notice_css());

		// Fallback: Add notice CSS directly to head
		add_action('admin_head', [$this, 'add_notice_css_to_head']);

		// Load widget styles only on dashboard
		if ('index.php' === $hook) {
			// Check if user hasn't dismissed the widget
			if (!get_user_meta(get_current_user_id(), 'uc_dismissed_bf_widget_' . gmdate('Y'), true)) {
				// Enqueue a dummy style to attach our CSS to
				wp_enqueue_style('dashboard');
				wp_add_inline_style('dashboard', $this->get_dashboard_widget_css());

				// Alternative: Add CSS directly to head if inline style doesn't work
				add_action('admin_head', [$this, 'add_dashboard_widget_css_to_head']);
			}
		}

		// Add JavaScript for coupon copy functionality and notice handling
		add_action('admin_footer', [$this, 'add_promo_scripts']);

		// Add AJAX handler for dismissing widget
		add_action('wp_ajax_uc_dismiss_black_friday_widget', [$this, 'dismiss_widget_ajax']);
	}

	/**
	 * AJAX handler for dismissing the widget
	 */
	public function dismiss_widget_ajax() {
		check_ajax_referer('uc_dismiss_bf_widget', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_die();
		}

		update_user_meta(get_current_user_id(), 'uc_dismissed_bf_widget_' . gmdate('Y'), true);

		wp_send_json_success();
	}

	/**
	 * Add CSS directly to admin head as fallback
	 */
	public function add_dashboard_widget_css_to_head() {
		echo '<style type="text/css">' . $this->get_dashboard_widget_css() . '</style>';
	}

	/**
	 * Add notice CSS directly to admin head as fallback
	 */
	public function add_notice_css_to_head() {
		echo '<style type="text/css">' . $this->get_notice_css() . '</style>';
	}

	/**
	 * Get CSS for promotional notices
	 */
	private function get_notice_css() {
		return '
		/* Enhanced Admin Notice Styles */
		.uc-halloween-notice,
		.uc-black-friday-notice {
			border: none !important;
			border-radius: 12px !important;
			box-shadow: 0 4px 20px rgba(0,0,0,0.1) !important;
			padding: 0 !important;
			margin: 15px 20px 15px 2px !important;
			position: relative;
			overflow: hidden;
		}

		.uc-halloween-notice {
			background: linear-gradient(135deg, #1a0a1a 0%, #2d1a2d 30%, #4a0e4a 70%, #8b008b 100%) !important;
		}

		.uc-black-friday-notice {
			background: linear-gradient(135deg, #1a1a1a 0%, #2d1b69 30%, #8b0000 70%, #ff4500 100%) !important;
		}

		.uc-halloween-notice::before,
		.uc-black-friday-notice::before {
			content: "";
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			pointer-events: none;
			z-index: 1;
		}

		.uc-halloween-notice::before {
			background:
				radial-gradient(circle at 20% 20%, rgba(255,102,0,0.3) 0%, transparent 50%),
				radial-gradient(circle at 80% 80%, rgba(139,0,139,0.2) 0%, transparent 50%);
		}

		.uc-black-friday-notice::before {
			background:
				radial-gradient(circle at 20% 20%, rgba(255,69,0,0.3) 0%, transparent 50%),
				radial-gradient(circle at 80% 80%, rgba(255,215,0,0.2) 0%, transparent 50%);
		}

		.uc-promo-notice-content {
			position: relative;
			z-index: 2;
			padding: 12px 20px;
			color: #ffffff;
			height: 60px;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 20px;
		}

		.uc-promo-notice-left {
			display: flex;
			align-items: center;
			gap: 15px;
			flex: 1;
		}

		.uc-promo-notice-header {
			display: flex;
			align-items: center;
			gap: 10px;
		}

		.uc-promo-notice-header h3 {
			margin: 0;
			font-size: 14px;
			font-weight: 600;
			color: #ffffff !important;
			text-shadow: 0 1px 2px rgba(0,0,0,0.3);
		}

		.uc-promo-badge {
			background: linear-gradient(135deg, #ff6b35, #f7931e);
			color: #000;
			padding: 3px 8px;
			border-radius: 12px;
			font-size: 10px;
			font-weight: bold;
			animation: badgePulse 2s infinite;
			box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3);
		}

		@keyframes badgePulse {
			0%, 100% {
				transform: scale(1);
				box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
			}
			50% {
				transform: scale(1.05);
				box-shadow: 0 6px 20px rgba(255, 107, 53, 0.6);
			}
		}

		.uc-halloween-notice .uc-promo-badge {
			background: linear-gradient(135deg, #ff6600, #ff4500);
			color: #fff;
			box-shadow: 0 4px 15px rgba(255, 102, 0, 0.4);
		}

		.uc-promo-notice-actions {
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.uc-promo-coupon {
			display: flex;
			align-items: center;
			gap: 6px;
		}

		.uc-promo-coupon strong {
			color: #ffffff;
			font-size: 11px;
			white-space: nowrap;
		}

		.uc-promo-coupon-code {
			background: linear-gradient(135deg, #ffd700, #ff8c00);
			color: #000;
			padding: 2px 6px;
			border-radius: 3px;
			cursor: pointer;
			transition: all 0.3s ease;
			font-family: "Courier New", monospace;
			font-weight: bold;
			font-size: 16px;
			border: 1px solid #ff4500;
			box-shadow: 0 1px 4px rgba(255, 215, 0, 0.3);
		}

		.uc-promo-coupon-code:hover {
			transform: translateY(-1px);
			box-shadow: 0 2px 8px rgba(255, 215, 0, 0.5);
		}

		.uc-halloween-notice .uc-promo-coupon-code {
			background: linear-gradient(135deg, #ff6600, #ff4500);
			color: #fff;
			border-color: #8b008b;
			box-shadow: 0 2px 8px rgba(255, 102, 0, 0.4);
		}

		.uc-halloween-notice .uc-promo-coupon-code:hover {
			box-shadow: 0 4px 12px rgba(255, 102, 0, 0.6);
		}

		.uc-copy-feedback {
			color: #4ade80;
			font-size: 12px;
			font-weight: bold;
			text-shadow: 0 1px 2px rgba(0,0,0,0.3);
		}

		.uc-promo-buttons {
			display: flex;
			align-items: center;
			gap: 15px;
		}

		.uc-promo-btn {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
			color: #ffffff !important;
			border: none !important;
			padding: 6px 12px !important;
			border-radius: 4px !important;
			font-weight: 600 !important;
			text-decoration: none !important;
			transition: all 0.3s ease !important;
			box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
			font-size: 11px !important;
			white-space: nowrap !important;
		}

		.uc-promo-btn:hover {
			transform: translateY(-1px) !important;
			box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
			color: #ffffff !important;
		}

		.uc-halloween-notice .uc-promo-btn {
			background: linear-gradient(135deg, #ff6600 0%, #8b008b 100%) !important;
			box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3) !important;
		}

		.uc-halloween-notice .uc-promo-btn:hover {
			box-shadow: 0 6px 20px rgba(255, 102, 0, 0.4) !important;
		}

		.uc-promo-countdown {
			font-size: 10px;
			color: #e0e0e0;
			white-space: nowrap;
		}

		.uc-promo-countdown strong {
			color: #ffffff;
		}

		/* Custom dismiss button styling */
		.uc-promo-notice .notice-dismiss {
		z-index: 9999 !important;
		top: -10px !important;
		right: -10px !important;
		// width: 20px !important;
		// height: 20px !important;
		}
		.uc-halloween-notice .notice-dismiss::before,
		.uc-black-friday-notice .notice-dismiss::before {
			color: #ffffff !important;
		}

		.uc-halloween-notice .notice-dismiss:hover::before,
		.uc-black-friday-notice .notice-dismiss:hover::before {
			color: #ff6600 !important;
		}

		@media (max-width: 782px) {
			.uc-promo-notice-actions {
				flex-direction: column;
				align-items: stretch;
				gap: 15px;
			}

			.uc-promo-buttons {
				flex-direction: column;
				gap: 10px;
			}

			.uc-promo-coupon {
				justify-content: center;
			}

			.uc-halloween-notice,
			.uc-black-friday-notice {
				margin: 10px 10px 10px 0 !important;
			}

			.uc-promo-notice-content {
				padding: 16px;
			}

			.uc-promo-notice-header h3 {
				font-size: 16px;
			}
		}
		';
	}

	/**
	 * Add JavaScript for promotional functionality
	 */
	public function add_promo_scripts() {
	?>
		<script type="text/javascript">
			// Widget coupon copy functionality
			function ucCopyCouponCode(element) {
				const couponText = element.querySelector('.uc-coupon-text').textContent;
				const copiedMessage = document.getElementById('uc-coupon-copied');

				// Try to copy to clipboard
				if (navigator.clipboard && window.isSecureContext) {
					// Modern async clipboard API
					navigator.clipboard.writeText(couponText).then(function() {
						ucShowCopiedMessage(copiedMessage);
					}).catch(function(err) {
						ucFallbackCopyTextToClipboard(couponText, copiedMessage);
					});
				} else {
					// Fallback for older browsers
					ucFallbackCopyTextToClipboard(couponText, copiedMessage);
				}
			}

			// Notice coupon copy functionality
			function ucCopyNoticeCode(element) {
				const couponText = element.textContent;
				const feedback = element.parentNode.querySelector('.uc-copy-feedback');

				// Try to copy to clipboard
				if (navigator.clipboard && window.isSecureContext) {
					navigator.clipboard.writeText(couponText).then(function() {
						ucShowNoticeFeedback(feedback);
					}).catch(function(err) {
						ucFallbackCopyNoticeCode(couponText, feedback);
					});
				} else {
					ucFallbackCopyNoticeCode(couponText, feedback);
				}
			}

			function ucFallbackCopyNoticeCode(text, feedback) {
				const textArea = document.createElement("textarea");
				textArea.value = text;
				textArea.style.position = "fixed";
				textArea.style.left = "-999999px";
				textArea.style.top = "-999999px";
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();

				try {
					document.execCommand('copy');
					ucShowNoticeFeedback(feedback);
				} catch (err) {
					console.error('Failed to copy coupon code: ', err);
				}

				document.body.removeChild(textArea);
			}

			function ucShowNoticeFeedback(feedback) {
				feedback.style.display = 'inline';
				setTimeout(function() {
					feedback.style.display = 'none';
				}, 2000);
			}

			function ucFallbackCopyTextToClipboard(text, copiedMessage) {
				const textArea = document.createElement("textarea");
				textArea.value = text;
				textArea.style.position = "fixed";
				textArea.style.left = "-999999px";
				textArea.style.top = "-999999px";
				document.body.appendChild(textArea);
				textArea.focus();
				textArea.select();

				try {
					document.execCommand('copy');
					ucShowCopiedMessage(copiedMessage);
				} catch (err) {
					console.error('Failed to copy coupon code: ', err);
				}

				document.body.removeChild(textArea);
			}

			function ucShowCopiedMessage(copiedMessage) {
				copiedMessage.style.display = 'block';
				setTimeout(function() {
					copiedMessage.style.display = 'none';
				}, 2000);
			}

			// Countdown Timer Functionality
			function ucInitCountdown() {
				const countdownElement = document.getElementById('uc-countdown-timer');
				if (!countdownElement) return;

				const endDate = new Date(countdownElement.getAttribute('data-end-date') + ' 23:59:59').getTime();

				function updateCountdown() {
					const now = new Date().getTime();
					const distance = endDate - now;

					if (distance < 0) {
						document.getElementById('uc-days').textContent = '00';
						document.getElementById('uc-hours').textContent = '00';
						document.getElementById('uc-minutes').textContent = '00';
						document.getElementById('uc-seconds').textContent = '00';
						return;
					}

					// Calculate remaining days (excluding today)
					const days = Math.floor(distance / (1000 * 60 * 60 * 24));
					const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
					const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
					const seconds = Math.floor((distance % (1000 * 60)) / 1000);

					document.getElementById('uc-days').textContent = days.toString().padStart(2, '0');
					document.getElementById('uc-hours').textContent = hours.toString().padStart(2, '0');
					document.getElementById('uc-minutes').textContent = minutes.toString().padStart(2, '0');
					document.getElementById('uc-seconds').textContent = seconds.toString().padStart(2, '0');
				}

				// Update immediately and then every second
				updateCountdown();
				setInterval(updateCountdown, 1000);
			}

			// Notice countdown functionality
			function ucInitNoticeCountdown() {
				const noticeCountdown = document.querySelector('.uc-promo-countdown');
				if (!noticeCountdown) return;

				const endDate = new Date(noticeCountdown.getAttribute('data-end-date') + ' 23:59:59').getTime();
				const countdownElement = document.getElementById('uc-notice-countdown');

				function updateNoticeCountdown() {
					const now = new Date().getTime();
					const distance = endDate - now;

					if (distance < 0) {
						countdownElement.textContent = 'Sale ended';
						return;
					}

					const days = Math.floor(distance / (1000 * 60 * 60 * 24));
					const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

					if (days > 0) {
						countdownElement.textContent = days + ' day' + (days !== 1 ? 's' : '') + ', ' + hours + ' hour' + (hours !== 1 ? 's' : '');
					} else if (hours > 0) {
						const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
						countdownElement.textContent = hours + ' hour' + (hours !== 1 ? 's' : '') + ', ' + minutes + ' minute' + (minutes !== 1 ? 's' : '');
					} else {
						const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
						countdownElement.textContent = minutes + ' minute' + (minutes !== 1 ? 's' : '');
					}
				}

				updateNoticeCountdown();
				setInterval(updateNoticeCountdown, 60000); // Update every minute
			}

			// Check if notice should be hidden based on localStorage (fallback for any missed notices)
			function ucCheckNoticeVisibility() {
				const notices = document.querySelectorAll('.uc-promo-notice');

				notices.forEach(function(notice) {
					const campaign = notice.getAttribute('data-campaign');
					const dismissKey = 'uc_dismissed_' + campaign + '_notice_' + new Date().getFullYear();
					const dismissData = localStorage.getItem(dismissKey);

					if (dismissData) {
						const dismissInfo = JSON.parse(dismissData);
						const now = new Date().getTime();
						const dismissTime = dismissInfo.timestamp;
						const hoursPassed = (now - dismissTime) / (1000 * 60 * 60);

						// Hide if less than 24 hours have passed
						if (hoursPassed < 24) {
							notice.style.display = 'none';
						} else {
							// Remove expired dismissal
							localStorage.removeItem(dismissKey);
						}
					}
				});
			}

			// Custom notice dismiss handling
			jQuery(document).ready(function($) {
				// Check visibility on page load
				ucCheckNoticeVisibility();

				// Handle notice dismiss
				$(document).on('click', '.uc-promo-notice .notice-dismiss', function(e) {
					e.preventDefault();

					const notice = $(this).closest('.notice');
					const campaign = notice.data('campaign');

					// Hide notice immediately
					notice.fadeOut(300);

					// Store dismissal in localStorage with timestamp
					const dismissKey = 'uc_dismissed_' + campaign + '_notice_' + new Date().getFullYear();
					const dismissData = {
						timestamp: new Date().getTime(),
						campaign: campaign
					};

					localStorage.setItem(dismissKey, JSON.stringify(dismissData));
				});
			});

			// Initialize countdowns when DOM is ready
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function() {
					ucInitCountdown();
					ucInitNoticeCountdown();
				});
			} else {
				ucInitCountdown();
				ucInitNoticeCountdown();
			}
		</script>
<?php
	}

	/**
	 * Get CSS for the dashboard widget
	 */
	private function get_dashboard_widget_css() {
		return '
		/* Black Friday Widget Styles */
		.ultimate-cursor-black-friday-widget {
			background: linear-gradient(135deg, #1a1a1a 0%, #2d1b69 30%, #8b0000 70%, #ff4500 100%);
			color: #ffffff;
			border-radius: 16px;
			padding: 0;
			position: relative;
			overflow: hidden;
			box-shadow: 0 20px 40px rgba(255, 69, 0, 0.3);
			animation: widgetFloat 6s ease-in-out infinite;
			border: 2px solid #ff6b35;
		}

		/* Halloween Widget Styles */
		.ultimate-cursor-halloween-widget {
			background: linear-gradient(135deg, #1a0a1a 0%, #2d1a2d 30%, #4a0e4a 70%, #8b008b 100%);
			color: #ffffff;
			border-radius: 16px;
			padding: 0;
			position: relative;
			overflow: hidden;
			box-shadow: 0 20px 40px rgba(255, 165, 0, 0.4);
			animation: widgetFloat 6s ease-in-out infinite;
			border: 2px solid #ff6600;
		}

		@keyframes widgetFloat {
			0%, 100% { transform: translateY(0px) rotate(0deg); }
			50% { transform: translateY(-5px) rotate(0.5deg); }
		}

		/* Shared styles for both widgets */
		.ultimate-cursor-black-friday-widget::before,
		.ultimate-cursor-halloween-widget::before {
			content: "";
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			pointer-events: none;
		}

		.ultimate-cursor-black-friday-widget::before {
			background:
				radial-gradient(circle at 20% 20%, rgba(255,69,0,0.3) 0%, transparent 50%),
				radial-gradient(circle at 80% 80%, rgba(255,215,0,0.2) 0%, transparent 50%),
				radial-gradient(circle at 40% 60%, rgba(255,140,0,0.15) 0%, transparent 50%);
		}

		.ultimate-cursor-halloween-widget::before {
			background:
				radial-gradient(circle at 20% 20%, rgba(255,102,0,0.4) 0%, transparent 50%),
				radial-gradient(circle at 80% 80%, rgba(139,0,139,0.3) 0%, transparent 50%),
				radial-gradient(circle at 40% 60%, rgba(255,69,0,0.2) 0%, transparent 50%);
		}

		.ultimate-cursor-black-friday-widget::after,
		.ultimate-cursor-halloween-widget::after {
			content: "";
			position: absolute;
			top: -50%;
			left: -50%;
			width: 200%;
			height: 200%;
			animation: shimmer 3s infinite;
			pointer-events: none;
		}

		.ultimate-cursor-black-friday-widget::after {
			background: linear-gradient(45deg, transparent, rgba(255,215,0,0.2), transparent);
		}

		.ultimate-cursor-halloween-widget::after {
			background: linear-gradient(45deg, transparent, rgba(255,102,0,0.3), transparent);
		}

		@keyframes shimmer {
			0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
			100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
		}

		.uc-bf-header {
			padding: 16px 20px 12px;
			text-align: center;
			position: relative;
			z-index: 2;
		}

		.uc-bf-badge {
			display: inline-block;
			margin-bottom: 12px;
			animation: bounceIn 1s ease-out;
		}

		@keyframes bounceIn {
			0% { transform: scale(0.3) rotate(-10deg); opacity: 0; }
			50% { transform: scale(1.05) rotate(5deg); }
			70% { transform: scale(0.9) rotate(-2deg); }
			100% { transform: scale(1) rotate(0deg); opacity: 1; }
		}

		.uc-bf-discount {
			background: linear-gradient(45deg, #ff4500, #ff6b35);
			color: #000;
			padding: 6px 16px;
			border-radius: 20px;
			font-weight: bold;
			font-size: 13px;
			box-shadow: 0 8px 25px rgba(255, 69, 0, 0.5);
			animation: pulseGlow 2s infinite;
			position: relative;
			overflow: hidden;
			border: 2px solid #ffd700;
			display: inline-block;
		}

		.uc-bf-discount::before {
			content: "";
			position: absolute;
			top: 0;
			left: -100%;
			width: 100%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(255,215,0,0.6), transparent);
			animation: slideShine 2s infinite;
		}

		@keyframes pulseGlow {
			0%, 100% {
				transform: scale(1);
				box-shadow: 0 8px 25px rgba(255, 69, 0, 0.5);
			}
			50% {
				transform: scale(1.05);
				box-shadow: 0 12px 35px rgba(255, 69, 0, 0.8);
			}
		}

		@keyframes slideShine {
			0% { left: -100%; }
			100% { left: 100%; }
		}

		.uc-bf-title {
			margin: 0;
			font-size: 18px;
			font-weight: 700;
			background: linear-gradient(45deg, #ffd700, #ff8c00);
			-webkit-background-clip: text;
			-webkit-text-fill-color: transparent;
			background-clip: text;
			animation: titleSlide 1s ease-out 0.3s both;
			text-shadow: 0 2px 4px rgba(0,0,0,0.3);
		}

		@keyframes titleSlide {
			0% { transform: translateY(20px); opacity: 0; }
			100% { transform: translateY(0); opacity: 1; }
		}

		.uc-bf-content {
			padding: 0 20px 16px;
			position: relative;
			z-index: 2;
		}

		.uc-bf-description {
			font-size: 14px;
			line-height: 1.5;
			margin-bottom: 12px;
			color: #f0f0f0;
			font-weight: 500;
			animation: fadeInUp 1s ease-out 0.5s both;
		}

		@keyframes fadeInUp {
			0% { transform: translateY(15px); opacity: 0; }
			100% { transform: translateY(0); opacity: 1; }
		}

		.uc-bf-features ul {
			list-style: none;
			padding: 0;
			margin: 0 0 20px 0;
		}

		.uc-bf-features li {
			padding: 8px 0;
			font-size: 14px;
			color: #e0e0e0;
			font-weight: 500;
			animation: slideInLeft 0.6s ease-out both;
			transform: translateX(-20px);
			opacity: 0;
		}

		.uc-bf-features li:nth-child(1) { animation-delay: 0.7s; }
		.uc-bf-features li:nth-child(2) { animation-delay: 0.8s; }
		.uc-bf-features li:nth-child(3) { animation-delay: 0.9s; }
		.uc-bf-features li:nth-child(4) { animation-delay: 1.0s; }
		.uc-bf-features li:nth-child(5) { animation-delay: 1.1s; }

		@keyframes slideInLeft {
			0% { transform: translateX(-20px); opacity: 0; }
			100% { transform: translateX(0); opacity: 1; }
		}

		.uc-bf-countdown {
			background: linear-gradient(135deg, rgba(255,69,0,0.2), rgba(255,140,0,0.3));
			backdrop-filter: blur(10px);
			padding: 12px;
			border-radius: 8px;
			margin-bottom: 15px;
			text-align: center;
			border: 2px solid #ff8c00;
			animation: countdownPulse 3s infinite;
		}

		.ultimate-cursor-halloween-widget .uc-bf-countdown {
			background: linear-gradient(135deg, rgba(255,102,0,0.3), rgba(139,0,139,0.2));
			border: 2px solid #ff6600;
		}

		@keyframes countdownPulse {
			0%, 100% { transform: scale(1); }
			50% { transform: scale(1.02); }
		}

		.uc-bf-countdown-label {
			margin: 0 0 10px 0;
			font-size: 13px;
			color: #ffffff;
			font-weight: 600;
		}

		.uc-bf-countdown-timer {
			display: flex;
			justify-content: center;
			gap: 10px;
			flex-wrap: wrap;
		}

		.uc-countdown-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			min-width: 50px;
		}

		.uc-countdown-number {
			background: linear-gradient(135deg, #ffd700, #ff8c00);
			color: #000;
			font-size: 18px;
			font-weight: bold;
			padding: 6px 10px;
			border-radius: 6px;
			min-width: 35px;
			text-align: center;
			box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
			animation: numberPulse 2s infinite;
			font-family: "Arial", sans-serif;
		}

		.ultimate-cursor-halloween-widget .uc-countdown-number {
			background: linear-gradient(135deg, #ff6600, #ff4500);
			color: #fff;
			box-shadow: 0 4px 15px rgba(255, 102, 0, 0.4);
		}

		@keyframes numberPulse {
			0%, 100% {
				transform: scale(1);
				box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
			}
			50% {
				transform: scale(1.05);
				box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5);
			}
		}

		.uc-countdown-label {
			font-size: 11px;
			color: #e0e0e0;
			margin-top: 5px;
			font-weight: 500;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}

		.uc-bf-coupon {
			background: linear-gradient(135deg, rgba(255,215,0,0.2), rgba(255,140,0,0.3));
			backdrop-filter: blur(10px);
			padding: 12px;
			border-radius: 8px;
			margin-bottom: 15px;
			text-align: center;
			border: 2px solid #ffd700;
			animation: fadeInUp 1s ease-out 1.0s both;
		}

		.ultimate-cursor-halloween-widget .uc-bf-coupon {
			background: linear-gradient(135deg, rgba(255,102,0,0.3), rgba(139,0,139,0.2));
			border: 2px solid #ff6600;
		}

		.uc-bf-coupon-label {
			margin: 0 0 8px 0;
			font-size: 13px;
			color: #ffffff;
			font-weight: 600;
		}

		.uc-bf-coupon-code {
			background: linear-gradient(135deg, #ffd700, #ff8c00);
			color: #000;
			padding: 8px 16px;
			border-radius: 6px;
			font-weight: bold;
			font-size: 14px;
			cursor: pointer;
			transition: all 0.3s ease;
			display: inline-flex;
			align-items: center;
			gap: 6px;
			border: 2px solid #ff4500;
			box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
			animation: couponGlow 2s infinite;
		}

		.ultimate-cursor-halloween-widget .uc-bf-coupon-code {
			background: linear-gradient(135deg, #ff6600, #ff4500);
			color: #fff;
			border: 2px solid #8b008b;
			box-shadow: 0 4px 15px rgba(255, 102, 0, 0.4);
		}

		@keyframes couponGlow {
			0%, 100% {
				box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
			}
			50% {
				box-shadow: 0 6px 20px rgba(255, 215, 0, 0.5);
			}
		}

		.uc-bf-coupon-code:hover {
			transform: translateY(-2px) scale(1.05);
			box-shadow: 0 8px 25px rgba(255, 215, 0, 0.5);
		}

		.uc-coupon-text {
			font-family: "Courier New", monospace;
			letter-spacing: 2px;
		}

		.uc-copy-icon {
			font-size: 14px;
			opacity: 0.8;
		}

		.uc-bf-coupon-copied {
			margin: 8px 0 0 0;
			font-size: 13px;
			color: #4ade80;
			font-weight: 600;
			animation: fadeIn 0.3s ease-in;
		}

		@keyframes fadeIn {
			0% { opacity: 0; transform: translateY(-5px); }
			100% { opacity: 1; transform: translateY(0); }
		}

		.uc-bf-actions {
			display: flex;
			gap: 12px;
			flex-wrap: wrap;
			animation: fadeInUp 1s ease-out 1.2s both;
		}

		.uc-bf-btn {
			flex: 1;
			padding: 14px 24px;
			border-radius: 10px;
			text-decoration: none;
			font-weight: 600;
			text-align: center;
			transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
			font-size: 14px;
			min-width: 130px;
			position: relative;
			overflow: hidden;
		}

		.uc-bf-btn::before {
			content: "";
			position: absolute;
			top: 0;
			left: -100%;
			width: 100%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(255,215,0,0.3), transparent);
			transition: left 0.5s;
		}

		.uc-bf-btn:hover::before {
			left: 100%;
		}

		.uc-bf-btn-primary {
			background: linear-gradient(135deg, #ff4500 0%, #8b0000 100%);
			color: #fff;
			box-shadow: 0 8px 25px rgba(255, 69, 0, 0.4);
			border: 2px solid #ffd700;
		}

		.uc-bf-btn-primary:hover {
			background: linear-gradient(135deg, #ff6b35 0%, #a00000 100%);
			transform: translateY(-3px) scale(1.02);
			box-shadow: 0 12px 35px rgba(255, 69, 0, 0.6);
			color: #fff;
		}

		.uc-bf-btn-secondary {
			background: linear-gradient(135deg, rgba(255,140,0,0.3), rgba(255,69,0,0.2));
			backdrop-filter: blur(10px);
			color: #ffffff;
			border: 2px solid #ff8c00;
		}

		.uc-bf-btn-secondary:hover {
			background: linear-gradient(135deg, rgba(255,140,0,0.5), rgba(255,69,0,0.4));
			transform: translateY(-3px) scale(1.02);
			color: #ffffff;
			box-shadow: 0 8px 25px rgba(255, 140, 0, 0.3);
		}

		@media (max-width: 782px) {
			.uc-bf-actions {
				flex-direction: column;
			}

			.uc-bf-btn {
				flex: none;
			}

			.uc-bf-header {
				padding: 20px 20px 14px;
			}

			.uc-bf-content {
				padding: 0 20px 20px;
			}

			.uc-bf-title {
				font-size: 20px;
			}

			.uc-bf-countdown-timer {
				gap: 10px;
			}

			.uc-countdown-item {
				min-width: 50px;
			}

			.uc-countdown-number {
				font-size: 20px;
				padding: 6px 10px;
				min-width: 35px;
			}

			.uc-countdown-label {
				font-size: 10px;
			}
		}
		';
	}
}

Ultimate_Cursor_Dashboard_Widget::instance();
