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

if ( ! defined( 'ABSPATH' ) ) {
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — register hooks.
	 */
	private function __construct() {
		// Whether Pro is active is asked when the widget is about to show
		// (should_show()), not here: the Pro add-on answers through a filter
		// that may not be registered yet while plugins are still loading.
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_uc_dismiss_promo_widget', array( $this, 'ajax_dismiss_widget' ) );
	}

	/**
	 * Whether the current user may see the widget: administrators, and never
	 * while Pro features are available.
	 *
	 * @return bool
	 */
	private function should_show() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return ! ( class_exists( 'UltimateCursor' ) && UltimateCursor::is_premium_active() );
	}

	/*
	------------------------------------------------------------------
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
	 *     @type string $headline           Widget headline.
	 *     @type string $description        Short CTA text.
	 *     @type string $button_text        CTA button label.
	 *     @type string $accent             Primary accent hex colour.
	 *     @type string $accent_secondary   Secondary accent hex colour.
	 *     @type string $gradient           CSS background gradient.
	 *     @type string $icon               Campaign icon key (see get_icon_svg()).
	 *     @type array  $features           Feature highlight list.
	 * }
	 */
	private function get_campaign() {
		// Allow testing via URL parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview toggle gated by capability check, no data is processed.
		$test_halloween = isset( $_GET['halloween'] ) && current_user_can( 'manage_options' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview toggle gated by capability check, no data is processed.
		$test_black_friday = isset( $_GET['black_friday'] ) && current_user_can( 'manage_options' );
		$now               = current_time( 'Y-m-d' );
		$year              = current_time( 'Y' );

		$features = array(
			__( 'Interactive Hover: the cursor reacts to links and buttons', 'ultimate-cursor' ),
			__( 'Multiple cursors, shown on the pages and elements you choose', 'ultimate-cursor' ),
			__( '20 more shapes, circular text and image hotspot', 'ultimate-cursor' ),
			__( 'Settings for every animated effect and background', 'ultimate-cursor' ),
		);

		// Halloween: October 15 – October 31.
		if ( $test_halloween || ( $now >= "$year-10-15" && $now <= "$year-10-31" ) ) {
			return array(
				'key'              => 'halloween',
				'discount'         => 25,
				'end_date'         => "$year-10-31",
				'coupon'           => 'HALLOWEEN',
				'widget_title'     => __( 'Ultimate Cursor — Halloween Sale', 'ultimate-cursor' ),
				'notice_title'     => __( 'Halloween Sale — Ultimate Cursor Pro', 'ultimate-cursor' ),
				'headline'         => __( 'Spooky-good cursors for less', 'ultimate-cursor' ),
				'description'      => __( 'Everything in Ultimate Cursor Pro, at the Halloween price.', 'ultimate-cursor' ),
				'button_text'      => __( 'Grab 25% OFF', 'ultimate-cursor' ),
				'accent'           => '#ff6600',
				'accent_secondary' => '#a855f7',
				'gradient'         => '#F76707',
				'icon'             => 'pumpkin',
				'features'         => $features,
			);
		}

		// Black Friday / Cyber Monday: November 1 – December 5.
		if ( $test_black_friday || ( $now >= "$year-11-01" && $now <= "$year-12-05" ) ) {
			return array(
				'key'              => 'black_friday',
				'discount'         => 25,
				'end_date'         => "$year-12-05",
				'coupon'           => 'BFCM',
				'widget_title'     => __( 'Ultimate Cursor — Black Friday Sale', 'ultimate-cursor' ),
				'notice_title'     => __( 'Black Friday Sale — Ultimate Cursor Pro', 'ultimate-cursor' ),
				'headline'         => __( 'Our biggest sale of the year', 'ultimate-cursor' ),
				'description'      => __( 'Everything in Ultimate Cursor Pro, at the Black Friday price.', 'ultimate-cursor' ),
				'button_text'      => __( 'Grab 25% OFF', 'ultimate-cursor' ),
				'accent'           => '#f43f5e',
				'accent_secondary' => '#ec4899',
				'gradient'         => '#111111',
				'icon'             => 'flame',
				'features'         => $features,
			);
		}

		// Regular promo windows: 20th of current month to 10th of next month.
		$day   = (int) current_time( 'j' );
		$month = (int) current_time( 'n' );

		if ( $day >= 20 || $day <= 10 ) {
			if ( $day >= 20 ) {
				$next_month = $month === 12 ? 1 : $month + 1;
				$next_year  = $month === 12 ? (int) $year + 1 : (int) $year;
				$end_date   = sprintf( '%04d-%02d-10', $next_year, $next_month );
			} else {
				$end_date = sprintf( '%s-%02d-10', $year, $month );
			}

			return array(
				'key'              => 'regular',
				'discount'         => 20,
				'end_date'         => $end_date,
				'coupon'           => 'UNLOCKPRO',
				'widget_title'     => __( 'Ultimate Cursor — Limited Offer', 'ultimate-cursor' ),
				'notice_title'     => __( 'Limited Time Offer — Ultimate Cursor Pro', 'ultimate-cursor' ),
				'headline'         => __( 'A cursor that reacts to your site', 'ultimate-cursor' ),
				'description'      => __( 'Take the cursor you built further with Ultimate Cursor Pro.', 'ultimate-cursor' ),
				'button_text'      => __( 'Get Pro — 20% OFF', 'ultimate-cursor' ),
				'accent'           => '#6366f1',
				'accent_secondary' => '#818cf8',
				'gradient'         => '#5C33FF',
				'icon'             => 'rocket',
				'features'         => $features,
			);
		}

		return null;
	}

	/*
	------------------------------------------------------------------
	 * 30-day dismiss helpers (server-side via user meta)
	 * ----------------------------------------------------------------*/

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
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
		);
	}

	/**
	 * Check if a promo type was dismissed within the last 30 days.
	 *
	 * @param string $type 'widget' or 'notice'.
	 * @return bool
	 */
	private function is_dismissed( $type ) {
		$meta_key  = 'uc_dismissed_promo_' . $type;
		$dismissed = get_user_meta( get_current_user_id(), $meta_key, true );

		if ( empty( $dismissed ) ) {
			return false;
		}

		$dismissed_time = (int) $dismissed;
		$elapsed_days   = ( time() - $dismissed_time ) / DAY_IN_SECONDS;

		if ( $elapsed_days >= self::DISMISS_DAYS ) {
			// Expired — remove stale meta and allow display.
			delete_user_meta( get_current_user_id(), $meta_key );
			return false;
		}

		return true;
	}

	/**
	 * Record a dismissal timestamp for a promo type.
	 *
	 * @param string $type 'widget' or 'notice'.
	 */
	private function dismiss( $type ) {
		$meta_key = 'uc_dismissed_promo_' . $type;
		update_user_meta( get_current_user_id(), $meta_key, time() );
	}

	/*
	------------------------------------------------------------------
	 * Dashboard Widget
	 * ----------------------------------------------------------------*/

	/**
	 * Register the dashboard widget.
	 */
	public function add_dashboard_widget() {
		if ( ! $this->should_show() ) {
			return;
		}

		$campaign = $this->get_campaign();
		if ( ! $campaign ) {
			return;
		}

		if ( $this->is_dismissed( 'widget' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ultimate_cursor_promo_widget',
			$campaign['widget_title'],
			array( $this, 'render_dashboard_widget' ),
			null,
			null,
			'column4',
			'high'
		);
	}

	/**
	 * Pricing URL with the campaign's coupon attached, so the discount is
	 * applied at checkout without the visitor copying a code.
	 *
	 * @param array $campaign Campaign configuration.
	 * @return string
	 */
	private function get_offer_url( $campaign ) {
		$url = self::PRICING_URL;
		if ( ! empty( $campaign['coupon'] ) ) {
			$url = add_query_arg( 'coupon', rawurlencode( $campaign['coupon'] ), $url );
		}
		return $url;
	}

	/**
	 * Render the dashboard widget body.
	 */
	public function render_dashboard_widget() {
		$c = $this->get_campaign();
		if ( ! $c ) {
			return;
		}
		$nonce          = wp_create_nonce( 'uc_dismiss_promo_widget' );
		$campaign_class = 'uc-campaign-' . $c['key'];
		$check          = '<svg width="10" height="10" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M2.5 7.2l3 3 6-6.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>';
		$units          = array(
			'd' => __( 'days', 'ultimate-cursor' ),
			'h' => __( 'hrs', 'ultimate-cursor' ),
			'm' => __( 'min', 'ultimate-cursor' ),
			's' => __( 'sec', 'ultimate-cursor' ),
		);
		?>
		<div class="uc-promo-widget <?php echo esc_attr( $campaign_class ); ?>"
			style="--uc-accent:<?php echo esc_attr( $c['accent'] ); ?>;--uc-accent-secondary:<?php echo esc_attr( $c['accent_secondary'] ); ?>;--uc-bg:<?php echo esc_attr( $c['gradient'] ); ?>">

			<button type="button" class="uc-pw-dismiss" data-nonce="<?php echo esc_attr( $nonce ); ?>" title="<?php esc_attr_e( 'Dismiss for 30 days', 'ultimate-cursor' ); ?>" aria-label="<?php esc_attr_e( 'Dismiss for 30 days', 'ultimate-cursor' ); ?>">
				<svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">
					<path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
				</svg>
			</button>

			<div class="uc-pw-top">
				<span class="uc-pw-mark"><?php echo wp_kses( $this->get_icon_svg( $c['icon'] ), $this->get_svg_kses_allowed() ); ?></span>
				<span class="uc-pw-brand"><?php esc_html_e( 'Ultimate Cursor Pro', 'ultimate-cursor' ); ?></span>
				<span class="uc-pw-badge">
					<?php
					/* translators: %d: discount percentage. */
					echo esc_html( sprintf( __( '%d%% OFF', 'ultimate-cursor' ), (int) $c['discount'] ) );
					?>
				</span>
			</div>

			<h3 class="uc-pw-title"><?php echo esc_html( $c['headline'] ); ?></h3>
			<p class="uc-pw-desc"><?php echo esc_html( $c['description'] ); ?></p>

			<ul class="uc-pw-features">
				<?php foreach ( $c['features'] as $feature ) : ?>
					<li>
						<span class="uc-pw-check"><?php echo wp_kses( $check, $this->get_svg_kses_allowed() ); ?></span>
						<?php echo esc_html( $feature ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="uc-pw-timer" data-end="<?php echo esc_attr( $c['end_date'] ); ?>">
				<span class="uc-pw-timer-label"><?php esc_html_e( 'Offer ends in', 'ultimate-cursor' ); ?></span>
				<div class="uc-pw-tiles">
					<?php foreach ( $units as $unit => $label ) : ?>
						<span class="uc-pw-tile">
							<b data-unit="<?php echo esc_attr( $unit ); ?>">--</b>
							<i><?php echo esc_html( $label ); ?></i>
						</span>
					<?php endforeach; ?>
				</div>
			</div>

			<a href="<?php echo esc_url( $this->get_offer_url( $c ) ); ?>" class="uc-pw-cta" target="_blank" rel="noopener">
				<?php echo esc_html( $c['button_text'] ); ?>
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false">
					<path d="M3 7h8m0 0L8 4m3 3L8 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
			</a>

			<p class="uc-pw-foot">
				<?php esc_html_e( 'Discount applied at checkout.', 'ultimate-cursor' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=ultimate-cursor&sub_page=pro' ) ); ?>"><?php esc_html_e( 'See what Pro adds', 'ultimate-cursor' ); ?></a>
			</p>
		</div>
		<?php
	}

	/*
	------------------------------------------------------------------
	 * AJAX
	 * ----------------------------------------------------------------*/

	/**
	 * Persist widget dismissal for 30 days.
	 */
	public function ajax_dismiss_widget() {
		check_ajax_referer( 'uc_dismiss_promo_widget', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$this->dismiss( 'widget' );
		wp_send_json_success();
	}

	/*
	------------------------------------------------------------------
	 * Assets (CSS + JS)
	 * ----------------------------------------------------------------*/

	/**
	 * Enqueue inline styles and footer scripts on relevant admin pages.
	 */
	public function enqueue_assets( $hook ) {
		// The widget lives on the Dashboard only; its styles and countdown
		// script are not needed anywhere else.
		if ( 'index.php' !== $hook || ! $this->should_show() ) {
			return;
		}

		$campaign = $this->get_campaign();
		if ( ! $campaign ) {
			return;
		}

		// Attach inline CSS to an existing core handle.
		wp_add_inline_style( 'wp-admin', $this->get_css() );

		// Print JS in footer.
		add_action( 'admin_footer', array( $this, 'print_scripts' ) );
	}

	/**
	 * Widget styles. Calm by design: no looping animation, and the hover
	 * transitions are dropped under prefers-reduced-motion.
	 *
	 * @return string CSS.
	 */
	private function get_css() {
		return '
/* === Ultimate Cursor promo widget (WordPress Dashboard) === */
#ultimate_cursor_promo_widget { border: 0; background: transparent; box-shadow: none; }
#ultimate_cursor_promo_widget .inside { padding: 0; margin: 0; }
#ultimate_cursor_promo_widget .postbox-header { display: none; }

.uc-promo-widget {
	position: relative;
	overflow: hidden;
	padding: 22px 22px 18px;
	border-radius: 14px;
	color: #fff;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
	text-align: left;
	/* Regular campaign: brand violet with yellow. Overridden per campaign below. */
	--uc-shadow: rgba(92, 51, 255, 0.35);
	--uc-pop: #FFD43B;
	--uc-pop-ink: #111827;
	--uc-num: #5C33FF;
	background: var(--uc-bg);
	box-shadow: 0 14px 30px var(--uc-shadow);
}

/* Top row: mark, name, discount */
.uc-pw-top {
	display: flex;
	align-items: center;
	gap: 9px;
	margin-bottom: 16px;
	padding-right: 28px;
}

.uc-pw-mark {
	display: grid;
	place-items: center;
	flex: none;
	width: 30px;
	height: 30px;
	border-radius: 9px;
	background: #fff;
	font-size: 16px;
	line-height: 0;
}

.uc-pw-brand {
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.02em;
	color: rgba(255, 255, 255, 0.85);
}

.uc-pw-badge {
	margin-left: auto;
	padding: 4px 10px;
	border-radius: 999px;
	background: var(--uc-pop);
	color: var(--uc-pop-ink);
	font-size: 11.5px;
	font-weight: 800;
	letter-spacing: 0.04em;
	white-space: nowrap;
}

#ultimate_cursor_promo_widget .uc-pw-title {
	margin: 0 0 6px;
	padding: 0;
	color: #fff;
	font-size: 20px;
	font-weight: 700;
	line-height: 1.25;
	letter-spacing: -0.01em;
}

#ultimate_cursor_promo_widget .uc-pw-desc {
	margin: 0 0 16px;
	color: rgba(255, 255, 255, 0.85);
	font-size: 13px;
	line-height: 1.5;
}

/* Feature list */
#ultimate_cursor_promo_widget .uc-pw-features {
	display: grid;
	gap: 9px;
	margin: 0 0 18px;
	padding: 0;
	list-style: none;
}

#ultimate_cursor_promo_widget .uc-pw-features li {
	display: flex;
	align-items: flex-start;
	gap: 9px;
	margin: 0;
	color: #fff;
	font-size: 12.5px;
	line-height: 1.45;
}

.uc-pw-check {
	display: grid;
	place-items: center;
	flex: none;
	width: 18px;
	height: 18px;
	margin-top: 1px;
	border-radius: 50%;
	background: #fff;
	color: var(--uc-num);
	line-height: 0;
}

/* Countdown */
.uc-pw-timer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	margin-bottom: 14px;
	padding: 10px 12px;
	border-radius: 12px;
	background: rgba(255, 255, 255, 0.14);
}

.uc-pw-timer-label {
	color: rgba(255, 255, 255, 0.85);
	font-size: 11.5px;
	font-weight: 500;
	white-space: nowrap;
}

.uc-pw-tiles {
	display: flex;
	gap: 6px;
}

.uc-pw-tile {
	display: flex;
	flex-direction: column;
	align-items: center;
	min-width: 34px;
	padding: 5px 4px 4px;
	border-radius: 8px;
	background: #fff;
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
}

.uc-pw-tile b {
	color: var(--uc-num);
	font-size: 15px;
	font-weight: 700;
	font-variant-numeric: tabular-nums;
	line-height: 1.1;
}

.uc-pw-tile i {
	margin-top: 2px;
	color: #64748b;
	font-size: 9px;
	font-style: normal;
	letter-spacing: 0.06em;
	text-transform: uppercase;
}

/* Call to action */
.uc-promo-widget .uc-pw-cta {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	padding: 13px 16px;
	border-radius: 11px;
	background: var(--uc-pop);
	color: var(--uc-pop-ink);
	font-size: 14px;
	font-weight: 800;
	text-decoration: none;
	transition: transform 0.18s ease, background 0.18s ease;
}

.uc-promo-widget .uc-pw-cta:hover,
.uc-promo-widget .uc-pw-cta:focus-visible {
	background: #fff;
	color: #111827;
	transform: translateY(-2px);
}

.uc-campaign-halloween .uc-pw-cta:hover,
.uc-campaign-halloween .uc-pw-cta:focus-visible {
	background: #fff;
	color: #1a1a1a;
}

.uc-promo-widget .uc-pw-cta:focus-visible {
	outline: 2px solid #fff;
	outline-offset: 2px;
}

.uc-promo-widget .uc-pw-cta svg {
	transition: transform 0.18s ease;
}

.uc-promo-widget .uc-pw-cta:hover svg {
	transform: translateX(3px);
}

#ultimate_cursor_promo_widget .uc-pw-foot {
	margin: 11px 0 0;
	color: rgba(255, 255, 255, 0.85);
	font-size: 11.5px;
	line-height: 1.5;
	text-align: center;
}

#ultimate_cursor_promo_widget .uc-pw-foot a {
	color: #fff;
	font-weight: 700;
	text-decoration: underline;
	text-decoration-color: rgba(255, 255, 255, 0.55);
	text-underline-offset: 2px;
}

#ultimate_cursor_promo_widget .uc-pw-foot a:hover {
	text-decoration-color: currentColor;
}

/* Dismiss */
.uc-pw-dismiss {
	position: absolute;
	top: 12px;
	right: 12px;
	display: grid;
	place-items: center;
	width: 26px;
	height: 26px;
	padding: 0;
	border: 0;
	border-radius: 8px;
	background: rgba(255, 255, 255, 0.18);
	color: #fff;
	cursor: pointer;
	transition: background 0.18s ease, color 0.18s ease;
}

.uc-pw-dismiss:hover,
.uc-pw-dismiss:focus-visible {
	background: rgba(255, 255, 255, 0.35);
	color: #fff;
}

/* Halloween: pumpkin orange with black. */
.uc-campaign-halloween {
	--uc-shadow: rgba(247, 103, 7, 0.4);
	--uc-pop: #1a1a1a;
	--uc-pop-ink: #fff;
	--uc-num: #D9480F;
}

/* Black Friday: black with yellow. */
.uc-campaign-black_friday {
	--uc-shadow: rgba(0, 0, 0, 0.4);
	--uc-pop: #FFD43B;
	--uc-pop-ink: #111111;
	--uc-num: #111111;
}

@media (prefers-reduced-motion: reduce) {
	.uc-promo-widget * { transition: none !important; }
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
				/* --- Countdown tiles --- */
				function ucPad(n) {
					return (n < 10 ? '0' : '') + n;
				}
				function ucUpdateCountdowns() {
					document.querySelectorAll('.uc-pw-timer[data-end]').forEach(function(timer) {
						var end = new Date(timer.getAttribute('data-end') + 'T23:59:59').getTime();
						var diff = end - Date.now();
						if (diff <= 0) {
							timer.style.display = 'none';
							return;
						}
						var values = {
							d: Math.floor(diff / 86400000),
							h: Math.floor((diff % 86400000) / 3600000),
							m: Math.floor((diff % 3600000) / 60000),
							s: Math.floor((diff % 60000) / 1000)
						};
						Object.keys(values).forEach(function(unit) {
							var el = timer.querySelector('[data-unit="' + unit + '"]');
							if (el) {
								el.textContent = ucPad(values[unit]);
							}
						});
					});
				}
				ucUpdateCountdowns();
				setInterval(ucUpdateCountdowns, 1000);

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
						xhr.open('POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>');
						xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
						xhr.send('action=uc_dismiss_promo_widget&nonce=' + encodeURIComponent(nonce));
					});
				});
			})();
		</script>
		<?php
	}
}

Ultimate_Cursor_Dashboard_Widget::instance();
