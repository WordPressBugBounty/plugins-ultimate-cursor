<?php

namespace UltimateCursor\Extension;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use \Elementor\Group_Control_Background;
use \Elementor\Group_Control_Border;
use Elementor\Utils;

defined('ABSPATH') || die();

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
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    static $should_script_enqueue = false;

    private function __construct() {
        add_action('elementor/element/common/_section_style/after_section_end', [$this, 'add_controls_section'], 1);
        add_action('elementor/frontend/widget/before_render', [$this, 'should_script_enqueue']);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_scripts']);

		// Check for required Elementor version
		if (!version_compare(ELEMENTOR_VERSION, ultimate_cursor()->minimum_elementor_version, '>=')) {
			add_action('admin_notices', array($this, 'admin_notice_minimum_elementor_version'));
			return;
		}
	}
    public function enqueue_scripts() {
        $suffix       = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_script('cotton-js', ultimate_cursor()->plugin_url . 'assets/js/cotton' . $suffix . '.js', [], '5.3.5', true);
        wp_enqueue_style('uce-cursor-css', ultimate_cursor()->plugin_url . 'assets/css/ultimate-cursor.css', null, UCA_VERSION);
        wp_enqueue_script('uce-cursor-js', ultimate_cursor()->plugin_url . 'assets/js/ultimate-cursor.js', ['jquery'], UCA_VERSION, true);
    }
    public function should_script_enqueue($element) {
        if (self::$should_script_enqueue) {
            return;
        }
        if ('yes' === $element->get_settings_for_display('ultimate_cursor_show')) {
            self::$should_script_enqueue = true;
            $this->enqueue_scripts();
            self::enqueue_scripts();
            remove_action('elementor/frontend/widget/before_render', [$this, 'should_script_enqueue']);
        }
    }

    public function add_controls_section($element) {

        $element->start_controls_section(
            'section_ultimate_cursor',
            [
                'label' => __('Ultimate Cursor', 'ultimate-cursor'),
                'tab'   => Controls_Manager::TAB_ADVANCED,
            ]
        );

        $element->add_control(
            'ultimate_cursor_show',
            [
                'label'              => __('Enable Cursors?', 'ultimate-cursor'),
                'type'               => Controls_Manager::SWITCHER,
                'return_value'       => 'yes',
                'prefix_class'       => 'uce-cursor-enabled-',
                'frontend_available' => true,
                'render_type'        => 'template',
            ]
        );
        $element->start_controls_tabs(
            'ultimate_cursor_tabs'
        );

        $element->start_controls_tab(
            'ultimate_cursor_tab_layout',
            [
                'label'     => esc_html__('Layout', 'ultimate-cursor'),
                'condition' => [
                    'ultimate_cursor_show' => 'yes'
                ],
            ]
        );
        $element->add_control(
            'ultimate_cursor_source',
            [
                'label'              => esc_html__('Cursor Type', 'ultimate-cursor'),
                'type'               => Controls_Manager::SELECT,
                'default'            => 'default',
                'frontend_available' => true,
                'render_type'        => 'template',
                'options'            => [
                    'default' => esc_html__('Default', 'ultimate-cursor'),
                    'text'    => esc_html__('Text', 'ultimate-cursor'),
                    'image'   => esc_html__('Image', 'ultimate-cursor'),
                    'icons'   => esc_html__('Icons', 'ultimate-cursor'),
                ],
                'condition'          => [
                    'ultimate_cursor_show' => 'yes'
                ],
            ]
        );
        $element->add_control(
            'ultimate_cursor_image_src',
            [
                'label'              => esc_html__('Image', 'ultimate-cursor'),
                'type'               => Controls_Manager::MEDIA,
                'frontend_available' => true,
                'render_type'        => 'template',
                'default'            => [
                    'url' => Utils::get_placeholder_image_src(),
                ],
                'condition'          => [
                    'ultimate_cursor_source' => 'image'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_icons',
            [
                'label'              => esc_html__('Icons', 'ultimate-cursor'),
                'type'               => Controls_Manager::ICONS,
                'frontend_available' => true,
                'render_type'        => 'template',
                'condition'          => [
                    'ultimate_cursor_source' => 'icons'
                ],
                'default'            => [
                    'value'   => 'fas fa-laugh-wink',
                    'library' => 'fa-solid',
                ],
            ]
        );
        $element->add_control(
            'ultimate_cursor_style',
            [
                'label'              => __('Style', 'ultimate-cursor'),
                'type'               => Controls_Manager::SELECT,
                'default'            => 'ep-cursor-style-1',
                'options'            => [
                    'ep-cursor-style-1' => __('Style 1', 'ultimate-cursor'),
                    'ep-cursor-style-2' => __('Style 2', 'ultimate-cursor'),
                    'ep-cursor-style-3' => __('Style 3', 'ultimate-cursor'),
                ],
                'frontend_available' => true,
                'render_type'        => 'template',
                'condition'          => [
                    'ultimate_cursor_show'   => 'yes',
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_text_label',
            [
                'label'              => esc_html__('Text Label', 'ultimate-cursor'),
                'type'               => Controls_Manager::TEXT,
                'default'            => esc_html__('Ultimate Cursor', 'ultimate-cursor'),
                'selectors'          => [
                    '{{WRAPPE}}.uce-cursor-enabled-yes' => '--cursor-text-label:"{{VALUE}}"'
                ],
                'frontend_available' => true,
                'render_type'        => 'template',
                'condition'          => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_speed',
            [
                'label'              => __('Speed', 'ultimate-cursor'),
                'type'               => Controls_Manager::SLIDER,
                'size_units'         => ['px'],
                'range'              => [
                    'px' => [
                        'min'  => 0,
                        'max'  => 1,
                        'step' => 0.001,
                    ]
                ],
                'default'            => [
                    'unit' => 'px',
                    'size' => 0.075,
                ],
                'frontend_available' => true,
                'render_type'        => 'none',
                'condition'          => [
                    'ultimate_cursor_show'   => 'yes',
                    'ultimate_cursor_source' => 'default'
                ]

            ]
        );
        $element->add_control(
            'ultimate_cursor_disable_default_cursor',
            [
                'label'        => __('Disable Default Cursor', 'ultimate-cursor'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'separator'    => 'before',
                'condition'    => [
                    'ultimate_cursor_show' => 'yes'
                ],
                'selectors'    => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes' => 'cursor: none'
                ]
            ]
        );
        $element->end_controls_tab();
        $element->start_controls_tab(
            'ultimate_cursor_tab_style',
            [
                'label'     => esc_html__('Style', 'ultimate-cursor'),
                'condition' => [
                    'ultimate_cursor_show' => 'yes'
                ],
            ]
        );
        $element->add_control(
            'ultimate_cursor_primary',
            [
                'label'     => esc_html__('Primary', 'ultimate-cursor'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_primary_color',
            [
                'label'     => esc_html__('Color', 'ultimate-cursor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-ball-color: {{VALUE}}',
                ],
                'condition' => [
                    'ultimate_cursor_source' => ['default', 'icons']
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_primary_size',
            [
                'label'     => esc_html__('Size', 'ultimate-cursor'),
                'type'      => Controls_Manager::SLIDER,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-ball-size:{{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_secondary',
            [
                'label'     => esc_html__('Secondary', 'ultimate-cursor'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => [
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        $element->add_control(
            'ultimate_cursor_secondary_color',
            [
                'label'     => esc_html__('Color', 'ultimate-cursor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-circle-color: {{VALUE}}',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_secondary_size',
            [
                'label'     => esc_html__('Size', 'ultimate-cursor'),
                'type'      => Controls_Manager::SLIDER,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes' => '--cursor-circle-size:{{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'default'
                ]
            ]
        );
        //TEXT
        $element->add_control(
            'ultimate_cursor_text_color',
            [
                'label'     => esc_html__('Color', 'ultimate-cursor'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'color: {{VALUE}}',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'      => 'ultimate_cursor_text_background',
                'label'     => esc_html__('Background', 'ultimate-cursor'),
                'types'     => ['classic', 'gradient'],
                'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
                'condition' => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_text_padding',
            [
                'label'      => esc_html__('Padding', 'ultimate-cursor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors'  => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition'  => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'      => 'ultimate_cursor_text_border',
                'label'     => esc_html__('Border', 'ultimate-cursor'),
                'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
                'condition' => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_text_radius',
            [
                'label'      => esc_html__('Border Radius', 'ultimate-cursor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors'  => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition'  => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'ultimate_cursor_text_typography',
                'label'     => esc_html__('Typography', 'ultimate-cursor'),
                'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-text',
                'condition' => [
                    'ultimate_cursor_source' => 'text'
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_image_size',
            [
                'label'     => esc_html__('Size', 'ultimate-cursor'),
                'type'      => Controls_Manager::SLIDER,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image' => 'width:{{SIZE}}{{UNIT}}; height:{{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'image'
                ]
            ]
        );
        $element->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'      => 'ultimate_cursor_image_border',
                'label'     => esc_html__('Border', 'ultimate-cursor'),
                'selector'  => '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image',
                'condition' => [
                    'ultimate_cursor_source' => 'image'
                ]
            ]
        );
        $element->add_responsive_control(
            'ultimate_cursor_image_radius',
            [
                'label'      => esc_html__('Border Radius', 'ultimate-cursor'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors'  => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
                'condition'  => [
                    'ultimate_cursor_source' => 'image'
                ]
            ]
        );

        $element->add_responsive_control(
            'ultimate_cursor_icons_size',
            [
                'label'     => esc_html__('Size', 'ultimate-cursor'),
                'type'      => Controls_Manager::SLIDER,
                'selectors' => [
                    '{{WRAPPER}}.uce-cursor-enabled-yes .bdt-cursor-icons' => 'font-size:{{SIZE}}{{UNIT}};',
                ],
                'condition' => [
                    'ultimate_cursor_source' => 'icons'
                ]
            ]
        );
        $element->end_controls_tab();

        $element->end_controls_tabs();
        $element->end_controls_section();
    }
}
Extend_Cursor::instance();
