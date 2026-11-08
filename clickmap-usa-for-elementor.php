<?php
/**
 * Plugin Name: ClickMap USA for Elementor
 * Description: A highly customizable, responsive, clickable USA map widget for Elementor. Every state is a hoverable button with its own link, tooltip and styling controls.
 * Version:     1.3.0
 * Author:      Amanullah Khan
 * Author URI:  https://github.com/amanullahykhan
 * Plugin URI:  https://github.com/amanullahykhan/clickmap-usa-for-elementor
 * Text Domain: clickmap-usa-for-elementor
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: elementor
 * Elementor tested up to: 3.25
 *
 * @package ClickMap_USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CLICKMAP_USA_VERSION', '1.3.0' );
define( 'CLICKMAP_USA_FILE', __FILE__ );
define( 'CLICKMAP_USA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CLICKMAP_USA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin bootstrap.
 */
final class ClickMap_USA_Plugin {

	/**
	 * Minimum Elementor version.
	 */
	const MINIMUM_ELEMENTOR_VERSION = '3.5.0';

	/**
	 * Minimum PHP version.
	 */
	const MINIMUM_PHP_VERSION = '7.2';

	/**
	 * Singleton instance.
	 *
	 * @var ClickMap_USA_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return ClickMap_USA_Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialise the plugin once all plugins are loaded.
	 */
	public function init() {

		// Check that Elementor is active.
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_missing_elementor' ) );
			return;
		}

		// Check Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_minimum_elementor' ) );
			return;
		}

		// Check PHP version.
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_minimum_php' ) );
			return;
		}

		// Register assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'register_assets' ) );

		// Register the widget category.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );

		// Register the widget itself.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register (but do not force-enqueue) front-end assets.
	 * Assets are enqueued on demand by the widget's get_script_depends()/get_style_depends().
	 */
	public function register_assets() {
		wp_register_style(
			'clickmap-usa-for-elementor',
			CLICKMAP_USA_URL . 'assets/css/usa-map.css',
			array(),
			CLICKMAP_USA_VERSION
		);

		wp_register_script(
			'clickmap-usa-for-elementor',
			CLICKMAP_USA_URL . 'assets/js/usa-map.js',
			array(),
			CLICKMAP_USA_VERSION,
			true
		);
	}

	/**
	 * Register a custom Elementor category.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			'clickmap',
			array(
				'title' => esc_html__( 'ClickMap', 'clickmap-usa-for-elementor' ),
				'icon'  => 'fa fa-plug',
			)
		);
	}

	/**
	 * Register widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		require_once CLICKMAP_USA_PATH . 'includes/usa-map-data.php';
		require_once CLICKMAP_USA_PATH . 'includes/usa-map-labels.php';
		require_once CLICKMAP_USA_PATH . 'widgets/usa-map-widget.php';
		$widgets_manager->register( new \ClickMap_USA_Widget() );

		// Keep pages built with the pre-rename widget working.
		require_once CLICKMAP_USA_PATH . 'includes/legacy-widget.php';
		$widgets_manager->register( new \ClickMap_USA_Widget_Legacy() );
	}

	/* -------------------------------------------------------------------------
	 * Admin notices
	 * ---------------------------------------------------------------------- */

	/**
	 * Notice: Elementor missing.
	 */
	public function notice_missing_elementor() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor */
			esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'clickmap-usa-for-elementor' ),
			'<strong>' . esc_html__( 'ClickMap USA for Elementor', 'clickmap-usa-for-elementor' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'clickmap-usa-for-elementor' ) . '</strong>'
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Notice: Elementor version too low.
	 */
	public function notice_minimum_elementor() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'clickmap-usa-for-elementor' ),
			'<strong>' . esc_html__( 'ClickMap USA for Elementor', 'clickmap-usa-for-elementor' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'clickmap-usa-for-elementor' ) . '</strong>',
			self::MINIMUM_ELEMENTOR_VERSION
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Notice: PHP version too low.
	 */
	public function notice_minimum_php() {
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'clickmap-usa-for-elementor' ),
			'<strong>' . esc_html__( 'ClickMap USA for Elementor', 'clickmap-usa-for-elementor' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'clickmap-usa-for-elementor' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', wp_kses_post( $message ) );
	}
}

ClickMap_USA_Plugin::instance();
