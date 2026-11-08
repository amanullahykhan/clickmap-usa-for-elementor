<?php
/**
 * ClickMap USA Elementor widget.
 *
 * Renders a clickable, hoverable US states map from an SVG asset file.
 * Every state path becomes a focusable, clickable button with its own
 * optional link and tooltip, plus full styling controls.
 *
 * @package ClickMap_USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;
use Elementor\Utils;

/**
 * Class ClickMap_USA_Widget
 */
class ClickMap_USA_Widget extends Widget_Base {

	/**
	 * Cached parsed SVG state data for this request.
	 *
	 * @var array|null
	 */
	protected $svg_states = null;

	/**
	 * Widget slug.
	 */
	public function get_name() {
		return 'clickmap_usa_map';
	}

	/**
	 * Widget title.
	 */
	public function get_title() {
		return esc_html__( 'ClickMap USA', 'clickmap-usa-for-elementor' );
	}

	/**
	 * Widget icon.
	 */
	public function get_icon() {
		return 'eicon-map-pin';
	}

	/**
	 * Widget category.
	 */
	public function get_categories() {
		return array( 'clickmap', 'general' );
	}

	/**
	 * Search keywords.
	 */
	public function get_keywords() {
		return array( 'usa', 'map', 'states', 'clickable', 'america', 'interactive', 'clickmap' );
	}

	/**
	 * Front-end styles this widget needs.
	 */
	public function get_style_depends() {
		return array( 'clickmap-usa-for-elementor' );
	}

	/**
	 * Front-end scripts this widget needs.
	 */
	public function get_script_depends() {
		return array( 'clickmap-usa-for-elementor' );
	}

	/* =====================================================================
	 * SVG LOADING / PARSING
	 * ================================================================== */

	/**
	 * Path to the SVG asset used for the map.
	 * Filterable so a child theme or another plugin can supply its own map.
	 *
	 * @return string
	 */
	protected function get_svg_path() {
		$path = CLICKMAP_USA_PATH . 'assets/us-states.svg';
		return apply_filters( 'clickmap_usa_svg_path', $path );
	}

	/**
	 * Load and lightly parse the SVG, returning a normalised structure:
	 *   array(
	 *     'viewBox' => '0 0 959 593',
	 *     'states'  => array( 'CA' => array('name'=>'California','d'=>'...','extra'=>'' ), ... ),
	 *   )
	 *
	 * States are detected from <path>/<polygon> elements. The element's
	 * id OR data-id OR data-abbr (two-letter) is used as the state key; its
	 * data-name / title / aria-label (if present) is used as the label,
	 * otherwise the canonical name list is consulted.
	 *
	 * @return array
	 */
	protected function get_svg_states() {
		if ( null !== $this->svg_states ) {
			return $this->svg_states;
		}

		$this->svg_states = array(
			'viewBox' => '0 0 959 593',
			'states'  => array(),
			'error'   => '',
		);

		$file = $this->get_svg_path();

		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			$this->svg_states['error'] = esc_html__( 'Map SVG file not found in the plugin assets folder.', 'clickmap-usa-for-elementor' );
			return $this->svg_states;
		}

		$raw = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw || '' === trim( $raw ) ) {
			$this->svg_states['error'] = esc_html__( 'Map SVG file is empty.', 'clickmap-usa-for-elementor' );
			return $this->svg_states;
		}

		// Parse with DOMDocument so we never echo the file blindly.
		$names = function_exists( 'clickmap_usa_states' ) ? clickmap_usa_states() : array();

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadXML( $raw, LIBXML_NONET | LIBXML_NOENT );
		libxml_clear_errors();

		if ( ! $loaded ) {
			$this->svg_states['error'] = esc_html__( 'Map SVG could not be parsed. Make sure it is a valid SVG file.', 'clickmap-usa-for-elementor' );
			return $this->svg_states;
		}

		// viewBox.
		$svg_el = $dom->getElementsByTagName( 'svg' )->item( 0 );
		if ( $svg_el && $svg_el->hasAttribute( 'viewBox' ) ) {
			$this->svg_states['viewBox'] = $svg_el->getAttribute( 'viewBox' );
		}

		$elements = array();
		foreach ( array( 'path', 'polygon', 'g' ) as $tag ) {
			foreach ( $dom->getElementsByTagName( $tag ) as $node ) {
				$elements[] = $node;
			}
		}

		foreach ( $elements as $node ) {
			/** @var \DOMElement $node */
			// Determine the state key.
			$key = '';
			foreach ( array( 'data-abbr', 'data-id', 'data-state', 'id' ) as $attr ) {
				if ( $node->hasAttribute( $attr ) ) {
					$maybe = strtoupper( trim( $node->getAttribute( $attr ) ) );
					// Accept 2-letter abbreviations only as a state key.
					if ( preg_match( '/^[A-Z]{2}$/', $maybe ) ) {
						$key = $maybe;
						break;
					}
				}
			}

			if ( '' === $key ) {
				continue; // Not a recognisable state element; skip.
			}
			if ( isset( $this->svg_states['states'][ $key ] ) ) {
				continue; // First occurrence wins.
			}

			// Geometry.
			$d        = $node->hasAttribute( 'd' ) ? $node->getAttribute( 'd' ) : '';
			$points   = $node->hasAttribute( 'points' ) ? $node->getAttribute( 'points' ) : '';
			$tag_name = $node->tagName;

			// If the state is a <g> wrapper (e.g. <g id="CA"><path .../></g>),
			// flatten the geometry of its descendant <path> elements so grouped
			// SVGs work when swapped in. (Child transforms are not applied, so a
			// flat path-per-state SVG remains the most reliable format.)
			if ( 'g' === $tag_name && '' === $d ) {
				$d_parts = array();
				foreach ( $node->getElementsByTagName( 'path' ) as $child ) {
					if ( $child->hasAttribute( 'd' ) && '' !== trim( $child->getAttribute( 'd' ) ) ) {
						$d_parts[] = trim( $child->getAttribute( 'd' ) );
					}
				}
				if ( ! empty( $d_parts ) ) {
					$d        = implode( ' ', $d_parts );
					$tag_name = 'path'; // Render the flattened geometry as a path.
				}
			}

			// A state element with no usable geometry is skipped (avoids empty shapes).
			if ( '' === $d && '' === $points ) {
				continue;
			}

			// Human label: explicit attr, then <title> child, then canonical list.
			$label = '';
			foreach ( array( 'data-name', 'aria-label', 'title' ) as $attr ) {
				if ( $node->hasAttribute( $attr ) && '' !== trim( $node->getAttribute( $attr ) ) ) {
					$label = trim( $node->getAttribute( $attr ) );
					break;
				}
			}
			if ( '' === $label ) {
				$title_node = $node->getElementsByTagName( 'title' )->item( 0 );
				if ( $title_node ) {
					$label = trim( $title_node->textContent );
				}
			}
			if ( '' === $label && isset( $names[ $key ] ) ) {
				$label = $names[ $key ];
			}
			if ( '' === $label ) {
				$label = $key;
			}

			$this->svg_states['states'][ $key ] = array(
				'tag'    => $tag_name,
				'name'   => $label,
				'd'      => $d,
				'points' => $points,
			);
		}

		if ( empty( $this->svg_states['states'] ) ) {
			$this->svg_states['error'] = esc_html__( 'No state shapes were detected in the SVG. Each state path needs an id or data-abbr with its 2-letter code (e.g. id="CA").', 'clickmap-usa-for-elementor' );
		}

		return $this->svg_states;
	}

	/* =====================================================================
	 * CONTROLS
	 * ================================================================== */

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {

		$this->register_content_controls();
		$this->register_links_controls();
		$this->register_map_style_controls();
		$this->register_tooltip_style_controls();
		$this->register_label_style_controls();
	}

	/**
	 * CONTENT: general map behaviour.
	 */
	protected function register_content_controls() {

		$this->start_controls_section(
			'section_general',
			array(
				'label' => esc_html__( 'Map Settings', 'clickmap-usa-for-elementor' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'max_width',
			array(
				'label'      => esc_html__( 'Map Max Width', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'vw' ),
				'range'      => array(
					'px' => array(
						'min' => 200,
						'max' => 1600,
					),
					'%'  => array(
						'min' => 10,
						'max' => 100,
					),
				),
				'default'    => array(
					'unit' => '%',
					'size' => 100,
				),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-usa-map' => 'max-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => esc_html__( 'Alignment', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'clickmap-usa-for-elementor' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'clickmap-usa-for-elementor' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Right', 'clickmap-usa-for-elementor' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'center',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-usa-map-wrap' => 'display:flex; justify-content: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'show_labels',
			array(
				'label'        => esc_html__( 'Show State Labels (abbr.)', 'clickmap-usa-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'clickmap-usa-for-elementor' ),
				'label_off'    => esc_html__( 'No', 'clickmap-usa-for-elementor' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Overlays the 2-letter code on each state. Best on a detailed map SVG.', 'clickmap-usa-for-elementor' ),
			)
		);

		$this->add_control(
			'tooltip_heading',
			array(
				'label'     => esc_html__( 'Tooltip', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'enable_tooltip',
			array(
				'label'        => esc_html__( 'Show Name on Hover', 'clickmap-usa-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'open_new_tab',
			array(
				'label'        => esc_html__( 'Open Links in New Tab (default)', 'clickmap-usa-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'separator'    => 'before',
				'description'  => esc_html__( 'Can be overridden per state in the Links tab.', 'clickmap-usa-for-elementor' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * CONTENT: per-state links repeater.
	 */
	protected function register_links_controls() {

		$this->start_controls_section(
			'section_links',
			array(
				'label' => esc_html__( 'State Links', 'clickmap-usa-for-elementor' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'links_info',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Add a row per state you want to link. Pick the state, set its URL. States without a row are still hoverable buttons (they fire a JS event but have no link).', 'clickmap-usa-for-elementor' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'state',
			array(
				'label'   => esc_html__( 'State', 'clickmap-usa-for-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $this->get_state_select_options(),
				'default' => '',
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'         => esc_html__( 'Link', 'clickmap-usa-for-elementor' ),
				'type'          => Controls_Manager::URL,
				'placeholder'   => esc_html__( 'https://your-site.com/state/', 'clickmap-usa-for-elementor' ),
				'show_external' => true,
				'default'       => array(
					'url'         => '',
					'is_external' => false,
					'nofollow'    => false,
				),
			)
		);

		$repeater->add_control(
			'custom_label',
			array(
				'label'       => esc_html__( 'Tooltip Label Override', 'clickmap-usa-for-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'e.g. California — Apply Now', 'clickmap-usa-for-elementor' ),
				'description' => esc_html__( 'Leave empty to use the state name.', 'clickmap-usa-for-elementor' ),
			)
		);

		// Per-state colors use Elementor's native repeater CSS via
		// {{CURRENT_ITEM}} (which resolves to .elementor-repeater-item-{id}).
		// render() adds that class to each state's shape. This is what makes
		// Global Colors, custom hex values and dynamic tags all work.
		// Selectors include .clickmap-state so they outrank the global fill.
		$repeater->add_control(
			'custom_color',
			array(
				'label'     => esc_html__( 'Custom Fill (this state)', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state{{CURRENT_ITEM}}' => 'fill: {{VALUE}};',
				),
			)
		);

		$repeater->add_control(
			'custom_hover_color',
			array(
				'label'     => esc_html__( 'Custom Hover Fill (this state)', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state{{CURRENT_ITEM}}:hover' => 'fill: {{VALUE}};',
					'{{WRAPPER}} .clickmap-state{{CURRENT_ITEM}}:focus' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'state_links',
			array(
				'label'       => esc_html__( 'States', 'clickmap-usa-for-elementor' ),
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ state }}}',
				'prevent_empty' => false,
			)
		);

		$this->end_controls_section();
	}

	/**
	 * STYLE: map base + hover + active states.
	 */
	protected function register_map_style_controls() {

		$this->start_controls_section(
			'section_map_style',
			array(
				'label' => esc_html__( 'States', 'clickmap-usa-for-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'state_style_tabs' );

		/* --- Normal --- */
		$this->start_controls_tab(
			'state_tab_normal',
			array( 'label' => esc_html__( 'Normal', 'clickmap-usa-for-elementor' ) )
		);

		$this->add_control(
			'state_fill',
			array(
				'label'     => esc_html__( 'Fill Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#2f8f4e',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'state_stroke',
			array(
				'label'     => esc_html__( 'Border Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state' => 'stroke: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'state_stroke_width',
			array(
				'label'      => esc_html__( 'Border Width', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => 0.25,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 1,
				),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-state' => 'stroke-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		/* --- Hover --- */
		$this->start_controls_tab(
			'state_tab_hover',
			array( 'label' => esc_html__( 'Hover', 'clickmap-usa-for-elementor' ) )
		);

		$this->add_control(
			'state_fill_hover',
			array(
				'label'     => esc_html__( 'Fill Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#1f6b39',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state:hover, {{WRAPPER}} .clickmap-state:focus' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'state_stroke_hover',
			array(
				'label'     => esc_html__( 'Border Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state:hover, {{WRAPPER}} .clickmap-state:focus' => 'stroke: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'state_stroke_width_hover',
			array(
				'label'      => esc_html__( 'Border Width', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 10,
						'step' => 0.25,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 1.5,
				),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-state:hover, {{WRAPPER}} .clickmap-state:focus' => 'stroke-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'state_hover_lift',
			array(
				'label'        => esc_html__( 'Lift Effect on Hover', 'clickmap-usa-for-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => 'yes',
				'selectors'    => array(
					'{{WRAPPER}} .clickmap-state' => 'transform-box: fill-box; transform-origin: center;',
					'{{WRAPPER}} .clickmap-state:hover, {{WRAPPER}} .clickmap-state:focus' => 'transform: scale(1.03);',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'transition_speed',
			array(
				'label'      => esc_html__( 'Transition Speed (ms)', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'ms' ),
				'range'      => array(
					'ms' => array(
						'min' => 0,
						'max' => 1000,
					),
				),
				'default'    => array(
					'unit' => 'ms',
					'size' => 200,
				),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-state' => 'transition: all {{SIZE}}{{UNIT}} ease;',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'map_box_shadow',
				'selector' => '{{WRAPPER}} .clickmap-usa-map svg',
			)
		);

		$this->add_responsive_control(
			'map_padding',
			array(
				'label'      => esc_html__( 'Map Padding', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-usa-map' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'map_bg',
			array(
				'label'     => esc_html__( 'Map Background', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .clickmap-usa-map' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * STYLE: tooltip.
	 */
	protected function register_tooltip_style_controls() {

		$this->start_controls_section(
			'section_tooltip_style',
			array(
				'label'     => esc_html__( 'Tooltip', 'clickmap-usa-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'enable_tooltip' => 'yes' ),
			)
		);

		$this->add_control(
			'tooltip_bg',
			array(
				'label'     => esc_html__( 'Background', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#111827',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-tooltip' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .clickmap-tooltip::after' => 'border-top-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'tooltip_color',
			array(
				'label'     => esc_html__( 'Text Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-tooltip' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'tooltip_typography',
				'selector' => '{{WRAPPER}} .clickmap-tooltip',
			)
		);

		$this->add_responsive_control(
			'tooltip_padding',
			array(
				'label'      => esc_html__( 'Padding', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'default'    => array(
					'top'      => 6,
					'right'    => 10,
					'bottom'   => 6,
					'left'     => 10,
					'unit'     => 'px',
					'isLinked' => false,
				),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-tooltip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'tooltip_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 6 ),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-tooltip' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * STYLE: state abbreviation labels.
	 */
	protected function register_label_style_controls() {

		$this->start_controls_section(
			'section_label_style',
			array(
				'label'     => esc_html__( 'State Labels', 'clickmap-usa-for-elementor' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_labels' => 'yes' ),
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => esc_html__( 'Label Color (on the map)', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state-label' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'label_outside_color',
			array(
				'label'       => esc_html__( 'Label Color (small states)', 'clickmap-usa-for-elementor' ),
				'type'        => Controls_Manager::COLOR,
				'default'     => '#333333',
				'description' => esc_html__( 'Small states (RI, DE, NJ, CT, MA, MD, VT, NH) are labelled beside the map, so they sit on the page background.', 'clickmap-usa-for-elementor' ),
				'selectors'   => array(
					'{{WRAPPER}} .clickmap-state-label--outside' => 'fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'leader_color',
			array(
				'label'     => esc_html__( 'Leader Line Color', 'clickmap-usa-for-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#555555',
				'selectors' => array(
					'{{WRAPPER}} .clickmap-state-leader' => 'stroke: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'leader_width',
			array(
				'label'      => esc_html__( 'Leader Line Width', 'clickmap-usa-for-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 0,
						'max'  => 4,
						'step' => 0.1,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 0.8,
				),
				'selectors'  => array(
					'{{WRAPPER}} .clickmap-state-leader' => 'stroke-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'selector' => '{{WRAPPER}} .clickmap-state-label',
			)
		);

		$this->end_controls_section();
	}

	/* =====================================================================
	 * HELPERS
	 * ================================================================== */

	/**
	 * Build the <select> options for the links repeater from the canonical
	 * list (so the dropdown is complete even before a real SVG is dropped in).
	 *
	 * @return array
	 */
	protected function get_state_select_options() {
		$options = array( '' => esc_html__( '— Select —', 'clickmap-usa-for-elementor' ) );
		$names   = function_exists( 'clickmap_usa_states' ) ? clickmap_usa_states() : array();
		foreach ( $names as $abbr => $name ) {
			$options[ $abbr ] = $name . ' (' . $abbr . ')';
		}
		return $options;
	}

	/**
	 * Normalize a viewBox string for comparison (collapse separators).
	 *
	 * @param string $vb Raw viewBox attribute.
	 * @return string
	 */
	protected function normalize_viewbox( $vb ) {
		return trim( preg_replace( '/[\s,]+/', ' ', (string) $vb ) );
	}

	/* =====================================================================
	 * RENDER
	 * ================================================================== */

	/**
	 * Render the widget output on the frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$data     = $this->get_svg_states();

		// Build a lookup of per-state link settings.
		$link_map = array();
		if ( ! empty( $settings['state_links'] ) && is_array( $settings['state_links'] ) ) {
			foreach ( $settings['state_links'] as $row ) {
				if ( empty( $row['state'] ) ) {
					continue;
				}
				$link_map[ strtoupper( $row['state'] ) ] = $row;
			}
		}

		$enable_tooltip = ( 'yes' === ( $settings['enable_tooltip'] ?? '' ) );
		$show_labels    = ( 'yes' === ( $settings['show_labels'] ?? '' ) );
		$default_newtab = ( 'yes' === ( $settings['open_new_tab'] ?? '' ) );

		// Precise label placement for the bundled map. Each position is the
		// state's pole of inaccessibility, so labels never fall outside a
		// concave/multi-part shape (Michigan, Louisiana, West Virginia...).
		// Small states that cannot hold text get an outside label + leader.
		// A swapped-in SVG won't match, and falls back to JS auto-placement.
		$labels     = function_exists( 'clickmap_usa_labels' ) ? clickmap_usa_labels() : array();
		$use_labels = (
			$show_labels
			&& ! empty( $labels['source_viewbox'] )
			&& $this->normalize_viewbox( $data['viewBox'] ) === $this->normalize_viewbox( $labels['source_viewbox'] )
		);

		// Widen the canvas so outside labels have room to sit beside the map.
		$view_box = $data['viewBox'];
		if ( $use_labels && ! empty( $labels['viewbox'] ) ) {
			$view_box = $labels['viewbox'];
		}

		// Build marker: lets you confirm which plugin build is actually running
		// (View Source and search for "clickmap-usa-map v").
		echo "\n<!-- clickmap-usa-map v" . esc_html( CLICKMAP_USA_VERSION ) . " -->\n";

		echo '<div class="clickmap-usa-map-wrap">';

		// Error / setup state: render a friendly notice (admins see detail).
		if ( ! empty( $data['error'] ) || empty( $data['states'] ) ) {
			$this->render_setup_notice( $data );
			echo '</div>';
			return;
		}

		printf(
			'<div class="clickmap-usa-map" data-tooltip="%s">',
			esc_attr( $enable_tooltip ? '1' : '0' )
		);

		printf(
			'<svg class="clickmap-usa-map-svg" viewBox="%s" xmlns="http://www.w3.org/2000/svg" role="group" aria-label="%s" preserveAspectRatio="xMidYMid meet">',
			esc_attr( $view_box ),
			esc_attr__( 'Clickable map of the United States', 'clickmap-usa-for-elementor' )
		);

		foreach ( $data['states'] as $abbr => $state ) {
			$row      = isset( $link_map[ $abbr ] ) ? $link_map[ $abbr ] : array();
			$label    = ! empty( $row['custom_label'] ) ? $row['custom_label'] : $state['name'];
			$url      = '';
			$target   = '';
			$rel      = '';
			$has_link = false;

			if ( ! empty( $row['link']['url'] ) ) {
				$url      = $row['link']['url'];
				$has_link = true;
				$is_ext   = ! empty( $row['link']['is_external'] ) || $default_newtab;
				$is_nf    = ! empty( $row['link']['nofollow'] );
				if ( $is_ext ) {
					$target = ' target="_blank"';
					$rel    = ' rel="noopener' . ( $is_nf ? ' nofollow' : '' ) . '"';
				} elseif ( $is_nf ) {
					$rel = ' rel="nofollow"';
				}
			}

			// Tag the shape with its Elementor repeater-item class so the
			// per-state color controls ({{CURRENT_ITEM}}) target it. This is
			// what lets Global Colors and custom values both apply.
			$item_class = '';
			if ( ! empty( $row['_id'] ) ) {
				$item_class = ' elementor-repeater-item-' . $row['_id'];
			}

			// Common attributes for the shape.
			$shape_attrs = sprintf(
				'class="clickmap-state%1$s" data-state="%2$s" data-name="%3$s"',
				esc_attr( $item_class ),
				esc_attr( $abbr ),
				esc_attr( $label )
			);

			// Geometry element (path or polygon).
			if ( 'polygon' === $state['tag'] && '' !== $state['points'] ) {
				$shape = sprintf( '<polygon %1$s points="%2$s"></polygon>', $shape_attrs, esc_attr( $state['points'] ) );
			} else {
				$shape = sprintf( '<path %1$s d="%2$s"></path>', $shape_attrs, esc_attr( $state['d'] ) );
			}

			// Wrap each state as a native link (button) when it has a URL;
			// otherwise as a focusable group that still fires a JS click event.
			if ( $has_link ) {
				printf(
					'<a class="clickmap-state-link" href="%1$s"%2$s%3$s aria-label="%4$s">%5$s</a>',
					esc_url( $url ),
					$target, // already escaped literals.
					$rel,    // already escaped literals.
					esc_attr( $label ),
					$shape // built from esc_attr above.
				);
			} else {
				printf(
					'<g class="clickmap-state-link clickmap-state-nolink" role="button" tabindex="0" aria-label="%1$s">%2$s</g>',
					esc_attr( $label ),
					$shape
				);
			}

			// Optional abbreviation label.
			if ( $show_labels ) {
				if ( $use_labels && isset( $labels['inside'][ $abbr ] ) ) {
					// Exact interior position.
					$pos = $labels['inside'][ $abbr ];
					printf(
						'<text class="clickmap-state-label" data-label-for="%1$s" x="%2$s" y="%3$s" text-anchor="middle" dominant-baseline="central" pointer-events="none">%1$s</text>',
						esc_html( $abbr ),
						esc_attr( $pos['x'] ),
						esc_attr( $pos['y'] )
					);
				} elseif ( $use_labels && isset( $labels['outside'][ $abbr ] ) ) {
					// Small state: label beside the map + leader line back to it.
					$pos = $labels['outside'][ $abbr ];
					$sx  = $pos['lx'] - 11;
					printf(
						'<line class="clickmap-state-leader" x1="%1$s" y1="%2$s" x2="%3$s" y2="%4$s" pointer-events="none"></line>',
						esc_attr( $sx ),
						esc_attr( $pos['ly'] ),
						esc_attr( $pos['ax'] ),
						esc_attr( $pos['ay'] )
					);
					printf(
						'<text class="clickmap-state-label clickmap-state-label--outside" data-label-for="%1$s" x="%2$s" y="%3$s" text-anchor="middle" dominant-baseline="central" pointer-events="none">%1$s</text>',
						esc_html( $abbr ),
						esc_attr( $pos['lx'] ),
						esc_attr( $pos['ly'] )
					);
				} else {
					// Unknown map: let the JS place it from the shape's bbox.
					printf(
						'<text class="clickmap-state-label" data-label-for="%1$s" text-anchor="middle" dominant-baseline="central" pointer-events="none">%1$s</text>',
						esc_html( $abbr )
					);
				}
			}
		}

		echo '</svg>';

		// Tooltip element (single, repositioned by JS).
		if ( $enable_tooltip ) {
			echo '<span class="clickmap-tooltip" role="status" aria-hidden="true"></span>';
		}

		echo '</div>'; // .clickmap-usa-map
		echo '</div>'; // .clickmap-usa-map-wrap
	}

	/**
	 * Render a setup notice when the SVG is missing/empty/unparsed.
	 * Visitors see a neutral message; logged-in editors see the reason.
	 *
	 * @param array $data Parsed data (may contain 'error').
	 */
	protected function render_setup_notice( $data ) {
		$can_edit = current_user_can( 'edit_posts' );
		echo '<div class="clickmap-usa-map-notice">';
		echo '<strong>' . esc_html__( 'USA Map', 'clickmap-usa-for-elementor' ) . '</strong><br>';
		if ( $can_edit ) {
			echo esc_html__( 'Setup needed: ', 'clickmap-usa-for-elementor' );
			echo esc_html( ! empty( $data['error'] ) ? $data['error'] : __( 'No states found.', 'clickmap-usa-for-elementor' ) );
			echo '<br><span class="clickmap-usa-map-notice-hint">';
			echo esc_html__( 'Drop a valid US-states SVG into the plugin\'s /assets/us-states.svg (see the plugin README). Each state path needs id="XX" or data-abbr="XX".', 'clickmap-usa-for-elementor' );
			echo '</span>';
		} else {
			echo esc_html__( 'The map is being set up.', 'clickmap-usa-for-elementor' );
		}
		echo '</div>';
	}
}
