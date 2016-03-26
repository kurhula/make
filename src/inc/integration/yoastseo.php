<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Integration_YoastSEO
 *
 * @since x.x.x.
 */
class MAKE_Integration_YoastSEO extends MAKE_Util_Modules implements MAKE_Util_HookInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'view'                => 'MAKE_Layout_ViewInterface',
		'thememod'            => 'MAKE_Settings_ThemeModInterface',
		'customizer_controls' => 'MAKE_Customizer_ControlsInterface',
	);

	/**
	 * Indicator of whether the hook routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Inject dependencies.
	 *
	 * @since x.x.x.
	 *
	 * @param MAKE_APIInterface $api
	 * @param array             $modules
	 */
	public function __construct(
		MAKE_APIInterface $api,
		array $modules = array()
	) {
		// The Customizer Controls module only exists in a Customizer context.
		if ( ! $api->has_module( 'customizer_controls' ) ) {
			unset( $this->dependencies['customizer_controls'] );
		}

		parent::__construct( $api, $modules );
	}

	/**
	 * Hook into WordPress.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function hook() {
		if ( $this->is_hooked() ) {
			return;
		}

		// Theme support
		add_action( 'after_setup_theme', array( $this, 'theme_support' ) );

		// Breadcrumb replacement
		add_action( 'after_setup_theme', array( $this, 'replace_breadcrumb' ) );

		// Theme Mod settings
		add_action( 'make_settings_thememod_loaded', array( $this, 'load_thememod_definitions' ) );

		// Customizer controls
		add_action( 'customize_register', array( $this, 'add_controls' ), 11 );

		// Hooking has occurred.
		self::$hooked = true;
	}

	/**
	 * Check if the hook routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	public function is_hooked() {
		return self::$hooked;
	}

	/**
	 * Declare theme support for specific features.
	 *
	 * @since x.x.x.
	 *
	 * return void
	 */
	public function theme_support() {
		// Only run this in the proper hook context.
		if ( 'after_setup_theme' !== current_action() ) {
			return;
		}

		// Yoast SEO breadcrumbs
		add_theme_support( 'yoast-seo-breadcrumbs' );
	}

	/**
	 * Add Theme Mod settings for the integration.
	 *
	 * @since x.x.x.
	 *
	 * @param MAKE_Settings_ThemeMod $thememod
	 *
	 * @return bool
	 */
	public function load_thememod_definitions( MAKE_Settings_ThemeMod $thememod ) {
		// Only run this in the proper hook context.
		if ( 'make_settings_thememod_loaded' !== current_action() ) {
			return false;
		}

		// Integration settings
		return $thememod->add_settings(
			array_fill_keys( array(
				'layout-blog-yoast-breadcrumb',
				'layout-archive-yoast-breadcrumb',
				'layout-search-yoast-breadcrumb',
				'layout-post-yoast-breadcrumb',
				'layout-page-yoast-breadcrumb',
			), array() ),
			array(
				'default'  => true,
				'sanitize' => 'wp_validate_boolean',
			)
		);
	}

	/**
	 * Add Customizer controls.
	 *
	 * @since x.x.x.
	 *
	 * @param WP_Customize_Manager $wp_customize
	 */
	public function add_controls( WP_Customize_Manager $wp_customize ) {
		// Only run this in the proper hook context.
		if ( 'customize_register' !== current_action() ) {
			return;
		}

		// Views that can have breadcrumbs
		$views = array(
			'blog',
			'archive',
			'search',
			'post',
			'page',
		);

		foreach ( $views as $view ) {
			$section_id = 'make_layout-' . $view;
			$setting_id = 'layout-' . $view . '-yoast-breadcrumb';
			$section_controls = $this->customizer_controls()->get_section_controls( $wp_customize, $section_id );
			$last_priority = $this->customizer_controls()->get_last_priority( $section_controls );

			// Breadcrumb heading
			$wp_customize->add_control( new MAKE_Customizer_Control_Html( $wp_customize, 'breadcrumb-group-' . $view, array(
				'section'  => $section_id,
				'priority' => $last_priority + 1,
				'html'     => '<h4 class="make-group-title">' . esc_html__( 'Breadcrumbs', 'make' ) . '</h4><span class="description customize-control-description">' . esc_html__( 'The Yoast SEO plugin enables this option.', 'make' ) . '</span>',
			) ) );

			// Breadcrumb setting
			$wp_customize->add_setting( $setting_id, array(
				'default'              => $this->thememod()->get_default( $setting_id ),
				'sanitize_callback'    => array( $this->customizer_controls(), 'sanitize' ),
				'sanitize_js_callback' => array( $this->customizer_controls(), 'sanitize_js' ),
			) );

			// Breadcrumb control
			$wp_customize->add_control( 'make_' . $setting_id, array(
				'settings' => $setting_id,
				'section'  => $section_id,
				'priority' => $last_priority + 2,
				'label'    => __( 'Show breadcrumbs', 'make' ),
				'type'     => 'checkbox',
			) );
		}
	}

	/**
	 * Use Yoast SEO's function to generate breadcrumb markup, if the current view calls for it.
	 *
	 * @since x.x.x.
	 *
	 * @param string $before
	 * @param string $after
	 *
	 * @return string
	 */
	public function maybe_render_breadcrumb( $before = '<p class="yoast-seo-breadcrumb">', $after = '</p>' ) {
		if ( function_exists( 'yoast_breadcrumb' ) ) {
			$show_breadcrumbs = $this->thememod()->get_value( 'layout-' . $this->view()->get_current_view() . '-yoast-breadcrumb' );

			if ( ( $show_breadcrumbs && ! is_front_page() ) || is_404() ) {
				return yoast_breadcrumb( $before, $after, false );
			}
		}

		return '';
	}

	/**
	 * Replace other breadcrumbs with the Yoast SEO version, for unified breadcrumbs.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function replace_breadcrumb() {
		// WooCommerce
		if ( false !== $priority = has_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb' ) ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', $priority );
			add_action( 'woocommerce_before_main_content', 'make_breadcrumb', $priority, 0 );
		}
	}
}