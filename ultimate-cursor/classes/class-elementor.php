<?php

namespace UltimateCursor\Extension;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Utils;

defined( 'ABSPATH' ) || die();

class Extend_Cursor {
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
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	static $should_script_enqueue = false;

	private function __construct() {
		// Check for required Elementor version first — the hooks below
		// enqueue/render Elementor-specific integration and must not
		// register at all on an incompatible version (previously this
		// check ran after registering them, so the version gate was
		// decorative: notice-only, hooks fired regardless).
		if ( ! version_compare( ELEMENTOR_VERSION, ultimate_cursor()->minimum_elementor_version, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			return;
		}

		// The Elementor per-widget cursor controls are a legacy system, superseded
		// by the main Ultimate Cursor dashboard. New installs no longer see the
		// editor controls (soft-deprecation), but widgets that already have a saved
		// cursor still render on the frontend. Sites that want the editor controls
		// back can re-enable them:
		// add_filter('ultimate_cursor_enable_elementor_controls', '__return_true');
		if ( apply_filters( 'ultimate_cursor_enable_elementor_controls', false ) ) {
			add_action( 'elementor/element/common/_section_style/after_section_end', array( $this, 'add_controls_section' ), 1 );
		}
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'should_script_enqueue' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	public function enqueue_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( 'ultimate-cursor-cotton', ultimate_cursor()->plugin_url . 'assets/js/cotton' . $suffix . '.js', array(), '5.3.5', true );
		wp_enqueue_style( 'uce-cursor-css', ultimate_cursor()->plugin_url . 'assets/css/ultimate-cursor.css', null, UCA_VERSION );
		wp_enqueue_script( 'uce-cursor-js', ultimate_cursor()->plugin_url . 'assets/js/ultimate-cursor.js', array( 'jquery' ), UCA_VERSION, true );
	}
	public function should_script_enqueue( $element ) {
		if ( self::$should_script_enqueue ) {
			return;
		}
		if ( 'yes' === $element->get_settings_for_display( 'ultimate_cursor_show' ) ) {
			self::$should_script_enqueue = true;
			$this->enqueue_scripts();
			self::enqueue_scripts();
			remove_action( 'elementor/frontend/widget/before_render', array( $this, 'should_script_enqueue' ) );
		}
	}

	public function add_controls_section( $element ) {

		$element->start_controls_section(
			'section_ultimate_cursor',
			array(
				'label' => __( 'Ultimate Cursor', 'ultimate-cursor' ),
				'tab'   => Controls_Manager::TAB_ADVANCED,
			)
		);

		$element->add_control(
			'ultimate_cursor_show',
			array(
				'label'              => __( 'Enable Cursors?', 'ultimate-cursor' ),
				'type'               => Controls_Manager::SWITCHER,
				'return_value'       => 'yes',
				'prefix_class'       => 'uce-cursor-enabled-',
				'frontend_available' => true,
				'render_type'        => 'template',
			)
		);
		$element->start_controls_tabs(
			'ultimate_cursor_tabs'
		);

		$element->start_controls_tab(
			'ultimate_cursor_tab_layout',
			array(
				'label'     => esc_html__( 'Layout', 'ultimate-cursor' ),
				'condition' => array(
					'ultimate_cursor_show' => 'yes',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_source',
			array(
				'label'              => esc_html__( 'Cursor Type', 'ultimate-cursor' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'default',
				'frontend_available' => true,
				'render_type'        => 'template',
				'options'            => array(
					'default' => esc_html__( 'Default', 'ultimate-cursor' ),
					'text'    => esc_html__( 'Text', 'ultimate-cursor' ),
					'image'   => esc_html__( 'Image', 'ultimate-cursor' ),
					'icons'   => esc_html__( 'Icons', 'ultimate-cursor' ),
				),
				'condition'          => array(
					'ultimate_cursor_show' => 'yes',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_image_src',
			array(
				'label'              => esc_html__( 'Image', 'ultimate-cursor' ),
				'type'               => Controls_Manager::MEDIA,
				'frontend_available' => true,
				'render_type'        => 'template',
				'default'            => array(
					'url' => Utils::get_placeholder_image_src(),
				),
				'condition'          => array(
					'ultimate_cursor_source' => 'image',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_icons',
			array(
				'label'              => esc_html__( 'Icons', 'ultimate-cursor' ),
				'type'               => Controls_Manager::ICONS,
				'frontend_available' => true,
				'render_type'        => 'template',
				'condition'          => array(
					'ultimate_cursor_source' => 'icons',
				),
				'default'            => array(
					'value'   => 'fas fa-laugh-wink',
					'library' => 'fa-solid',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_style',
			array(
				'label'              => __( 'Style', 'ultimate-cursor' ),
				'type'               => Controls_Manager::SELECT,
				'default'            => 'ep-cursor-style-1',
				'options'            => array(
					'ep-cursor-style-1' => __( 'Style 1', 'ultimate-cursor' ),
					'ep-cursor-style-2' => __( 'Style 2', 'ultimate-cursor' ),
					'ep-cursor-style-3' => __( 'Style 3', 'ultimate-cursor' ),
				),
				'frontend_available' => true,
				'render_type'        => 'template',
				'condition'          => array(
					'ultimate_cursor_show'   => 'yes',
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_text_label',
			array(
				'label'              => esc_html__( 'Text Label', 'ultimate-cursor' ),
				'type'               => Controls_Manager::TEXT,
				'default'            => esc_html__( 'Ultimate Cursor', 'ultimate-cursor' ),
				'selectors'          => array(
					'{{WRAPPE}}.uce-cursor-enabled-yes' => '--cursor-text-label:"{{VALUE}}"',
				),
				'frontend_available' => true,
				'render_type'        => 'template',
				'condition'          => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_speed',
			array(
				'label'              => __( 'Speed', 'ultimate-cursor' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array(
						'min'  => 0,
						'max'  => 1,
						'step' => 0.001,
					),
				),
				'default'            => array(
					'unit' => 'px',
					'size' => 0.075,
				),
				'frontend_available' => true,
				'render_type'        => 'none',
				'condition'          => array(
					'ultimate_cursor_show'   => 'yes',
					'ultimate_cursor_source' => 'default',
				),

			)
		);
		$element->add_control(
			'ultimate_cursor_disable_default_cursor',
			array(
				'label'        => __( 'Disable Default Cursor', 'ultimate-cursor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'separator'    => 'before',
				'condition'    => array(
					'ultimate_cursor_show' => 'yes',
				),
				'selectors'    => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes' => 'cursor: none',
				),
			)
		);
		$element->end_controls_tab();
		$element->start_controls_tab(
			'ultimate_cursor_tab_style',
			array(
				'label'     => esc_html__( 'Style', 'ultimate-cursor' ),
				'condition' => array(
					'ultimate_cursor_show' => 'yes',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_primary',
			array(
				'label'     => esc_html__( 'Primary', 'ultimate-cursor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_primary_color',
			array(
				'label'     => esc_html__( 'Color', 'ultimate-cursor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-ball-color: {{VALUE}}',
				),
				'condition' => array(
					'ultimate_cursor_source' => array( 'default', 'icons' ),
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_primary_size',
			array(
				'label'     => esc_html__( 'Size', 'ultimate-cursor' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-ball-size:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_secondary',
			array(
				'label'     => esc_html__( 'Secondary', 'ultimate-cursor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		$element->add_control(
			'ultimate_cursor_secondary_color',
			array(
				'label'     => esc_html__( 'Color', 'ultimate-cursor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-circle-color: {{VALUE}}',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_secondary_size',
			array(
				'label'     => esc_html__( 'Size', 'ultimate-cursor' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-circle-size:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'default',
				),
			)
		);
		// TEXT
		$element->add_control(
			'ultimate_cursor_text_color',
			array(
				'label'     => esc_html__( 'Color', 'ultimate-cursor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'color: {{VALUE}}',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'      => 'ultimate_cursor_text_background',
				'label'     => esc_html__( 'Background', 'ultimate-cursor' ),
				'types'     => array( 'classic', 'gradient' ),
				'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
				'condition' => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_text_padding',
			array(
				'label'      => esc_html__( 'Padding', 'ultimate-cursor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'ultimate_cursor_text_border',
				'label'     => esc_html__( 'Border', 'ultimate-cursor' ),
				'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
				'condition' => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_text_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ultimate-cursor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'ultimate_cursor_text_typography',
				'label'     => esc_html__( 'Typography', 'ultimate-cursor' ),
				'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
				'condition' => array(
					'ultimate_cursor_source' => 'text',
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_image_size',
			array(
				'label'     => esc_html__( 'Size', 'ultimate-cursor' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image' => 'width:{{SIZE}}{{UNIT}}; height:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'image',
				),
			)
		);
		$element->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'ultimate_cursor_image_border',
				'label'     => esc_html__( 'Border', 'ultimate-cursor' ),
				'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image',
				'condition' => array(
					'ultimate_cursor_source' => 'image',
				),
			)
		);
		$element->add_responsive_control(
			'ultimate_cursor_image_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'ultimate-cursor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
				'condition'  => array(
					'ultimate_cursor_source' => 'image',
				),
			)
		);

		$element->add_responsive_control(
			'ultimate_cursor_icons_size',
			array(
				'label'     => esc_html__( 'Size', 'ultimate-cursor' ),
				'type'      => Controls_Manager::SLIDER,
				'selectors' => array(
					'{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-icons' => 'font-size:{{SIZE}}{{UNIT}};',
				),
				'condition' => array(
					'ultimate_cursor_source' => 'icons',
				),
			)
		);
		$element->end_controls_tab();

		$element->end_controls_tabs();
		$element->end_controls_section();
	}
}
Extend_Cursor::instance();
