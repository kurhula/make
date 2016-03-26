<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Compatibility_Methods
 *
 * @since x.x.x.
 */
class MAKE_Compatibility_Methods extends MAKE_Util_Modules implements MAKE_Compatibility_MethodsInterface, MAKE_Util_HookInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'error' => 'MAKE_Error_CollectorInterface',
	);

	/**
	 * The available compatibility modes.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	private $modes = array(
		'full'    => array(
			'deprecated'   => array( '1.5', '1.6', '1.7' ),
			'hookprefixer' => true,
			'keyconverter' => true,
		),
		'1.5'     => array(
			'deprecated'   => array( '1.6', '1.7' ),
			'hookprefixer' => true,
			'keyconverter' => false,
		),
		'1.6'     => array(
			'deprecated'   => array( '1.7' ),
			'hookprefixer' => true,
			'keyconverter' => false,
		),
		'1.7'     => array(
			'deprecated'   => false,
			'hookprefixer' => false,
			'keyconverter' => false,
		),
		'current' => array(
			'deprecated'   => false,
			'hookprefixer' => false,
			'keyconverter' => false,
		),
	);

	/**
	 * The current compatibility mode.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	protected $mode = array();

	/**
	 * Indicator of whether the hook routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * MAKE_Compatibility_Methods constructor.
	 *
	 * @since x.x.x.
	 *
	 * @param MAKE_APIInterface $api
	 * @param array             $modules
	 */
	public function __construct( MAKE_APIInterface $api = null, array $modules = array() ) {
		// Load dependencies.
		parent::__construct( $api, $modules );

		// Set the compatibility mode.
		$this->set_mode();
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

		// Deprecated files
		add_action( 'make_api_loaded', array( $this, 'require_deprecated_files' ), 0 );

		// Load modules
		add_action( 'make_api_loaded', array( $this, 'load_modules' ), 0 );

		// Add notice if user attempts to install Make Plus as a theme
		add_filter( 'upgrader_source_selection', array( $this, 'check_package' ), 9, 3 );

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
	 * Set the mode for compatibility.
	 *
	 * @since x.x.x.
	 *
	 * @return string    $mode    The mode that was set.
	 */
	protected function set_mode() {
		$default_mode = 'full';

		/**
		 * Filter: Set the mode for compatibility.
		 *
		 * - 'full' will load all the files to enable back compatibility with deprecated code.
		 * - 'current' will not load any deprecated code. Use with caution! Could result in a fatal PHP error.
		 * - A minor release value, such as '1.5', will load files necessary for back compatibility with version 1.5.x.
		 *   (Note that there are no separate modes for releases prior to 1.5.)
		 *
		 * @since x.x.x.
		 *
		 * @param string    $mode    The compatibility mode to run the theme in.
		 */
		$mode = apply_filters( 'make_compatibility_mode', $default_mode );

		if ( ! isset( $this->modes[ $mode ] ) ) {
			$mode = $default_mode;
		}

		$this->mode = $this->modes[ $mode ];

		return $mode;
	}

	/**
	 * Load back compat files for deprecated functionality based on specified version numbers.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function require_deprecated_files() {
		// Only run this in the proper hook context.
		if ( 'make_api_loaded' !== current_action() ) {
			return;
		}

		if ( isset( $this->mode['deprecated'] ) && is_array( $this->mode['deprecated'] ) ) {
			foreach ( $this->mode['deprecated'] as $version ) {
				$file = dirname( __FILE__ ) . '/deprecated/deprecated-' . $version . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
	}

	/**
	 * Load additional compatibility modules, depending on the mode.
	 *
	 * @since x.x.x.
	 *
	 * @param MAKE_APIInterface|null $api
	 *
	 * @return void
	 */
	public function load_modules( MAKE_APIInterface $api = null ) {
		// Load the hook prefixer
		if ( true === $this->mode['hookprefixer'] ) {
			$this->add_module( 'hookprefixer', new MAKE_Compatibility_HookPrefixer( $api ) );
		}

		// Load the key converter
		if ( true === $this->mode['keyconverter'] ) {
			$this->add_module( 'keyconverter', new MAKE_Compatibility_KeyConverter( $api ) );
		}
	}

	/**
	 * Add notice if user attempts to install Make Plus as a theme.
	 *
	 * @since  1.1.2.
	 *
	 * @param  string         $source           File source location.
	 * @param  string         $remote_source    Remove file source location.
	 * @param  WP_Upgrader    $upgrader         WP_Upgrader instance.
	 *
	 * @return WP_Error                         Error or source on success.
	 */
	public function check_package( $source, $remote_source, $upgrader ) {
		// Only run this in the proper hook context.
		if ( 'upgrader_source_selection' !== current_filter() ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! isset( $_GET['action'] ) || 'upload-theme' !== $_GET['action'] ) {
			return $source;
		}

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		// Check the folder contains a valid theme
		$working_directory = str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), $source );
		if ( ! is_dir( $working_directory ) ) { // Sanity check, if the above fails, lets not prevent installation.
			return $source;
		}

		// A proper archive should have a style.css file in the single subdirectory
		if ( ! file_exists( $working_directory . 'style.css' ) && strpos( $source, 'make-plus-' ) >= 0 ) {
			return new WP_Error( 'incompatible_archive_theme_no_style', $upgrader->strings[ 'incompatible_archive' ], __( 'The uploaded package appears to be a plugin. PLEASE INSTALL AS A PLUGIN.', 'make' ) );
		}

		return $source;
	}

	/**
	 * Mark a function as deprecated and inform when it has been used.
	 *
	 * Based on _deprecated_function() in WordPress core.
	 *
	 * @since x.x.x.
	 *
	 * @param string      $function    The function that was called.
	 * @param string      $version     The version of Make that deprecated the function.
	 * @param string|null $replacement The function that should have been called.
	 * @param string|null $message     Explanatory text if there is no direct replacement available.
	 * @param bool        $backtrace   True to include a backtrace in the error message.
	 *
	 * @return void
	 */
	public function deprecated_function( $function, $version, $replacement = null, $message = null, $backtrace = true ) {
		/**
		 * Fires when a deprecated function is called.
		 *
		 * @since x.x.x.
		 *
		 * @param string $function    The function that was called.
		 * @param string $version     The version of Make that deprecated the function.
		 * @param string $replacement The function that should have been called.
		 * @param string $message     Explanatory text if there is no direct replacement available.
		 */
		do_action( 'make_deprecated_function_run', $function, $version, $replacement, $message );

		$error_code = 'make_deprecated_function';
		$error_message = __( '<strong>%1$s</strong> is deprecated since version %2$s of Make. %3$s', 'make' );

		// Add additional messages.
		if ( ! is_null( $replacement ) ) {
			$message2 = sprintf( __( 'Use <strong>%s</strong> instead.', 'make' ), $replacement );
		} else if ( ! is_null( $message ) ) {
			$message2 = $message;
		} else {
			$message2 = __( 'No alternative is available.', 'make' );
		}

		$error_message = sprintf(
			$error_message,
			$function,
			$version,
			$message2
		);

		// Add a backtrace.
		if ( $backtrace ) {
			$error_message .= $this->error()->generate_backtrace( array( get_class( $this ) ) );
		}

		// Add the error.
		$this->error()->add_error( $error_code, $error_message );
	}

	/**
	 * Mark an action or filter hook as deprecated and inform when it has been used.
	 *
	 * Based on _deprecated_argument() in WordPress core.
	 *
	 * @since x.x.x.
	 *
	 * @param string $hook     The hook that was used.
	 * @param string $version  The version of WordPress that deprecated the hook.
	 * @param string $message  Optional. A message regarding the change. Default null.
	 *
	 * @return void
	 */
	public function deprecated_hook( $hook, $version, $message = null ) {
		/**
		 * Fires when a deprecated hook has an attached function/method.
		 *
		 * @since x.x.x.
		 *
		 * @param string $hook        The hook that was called.
		 * @param string $version     The version of Make that deprecated the hook.
		 * @param string $message     Optional. A message regarding the change. Default null.
		 */
		do_action( 'make_deprecated_hook_run', $hook, $version, $message );

		$error_code = 'make_deprecated_hook';

		if ( is_null( $message ) ) {
			$message = __( 'No alternative is available.', 'make' );
		}

		$error_message = sprintf(
			__( 'The <strong>%1$s</strong> hook is deprecated since version %2$s of Make. %3$s', 'make' ),
			$hook,
			$version,
			$message
		);

		// Add an error
		$this->error()->add_error( $error_code, $error_message );
	}

	/**
	 * Mark something as being incorrectly called.
	 *
	 * Based on _doing_it_wrong() in WordPress core.
	 *
	 * @since x.x.x.
	 *
	 * @param string $function  The function that was called.
	 * @param string $message   A message explaining what has been done incorrectly.
	 * @param string $version   The version of WordPress where the message was added.
	 * @param bool   $backtrace True to include a backtrace in the error message.
	 *
	 * @return void
	 */
	public function doing_it_wrong( $function, $message, $version = null, $backtrace = true ) {
		/**
		 * Fires when the given function is being used incorrectly.
		 *
		 * @since x.x.x.
		 *
		 * @param string $function The function that was called.
		 * @param string $message  A message explaining what has been done incorrectly.
		 * @param string $version  The version of Make where the message was added.
		 */
		do_action( 'make_doing_it_wrong_run', $function, $message, $version );

		$error_code = 'make_doing_it_wrong';

		// Add a version.
		if ( ! is_null( $version ) ) {
			$message = sprintf(
				__( '%1$s (This message was added in version %2$s.)', 'make' ),
				$message,
				$version
			);
		}

		$error_message = sprintf(
			__( '<strong>%1$s</strong> was called incorrectly. %2$s', 'make' ),
			$function,
			$message
		);

		// Add a backtrace.
		if ( $backtrace ) {
			$error_message .= $this->error()->generate_backtrace( array( get_class( $this ) ) );
		}

		// Add the error.
		$this->error()->add_error( $error_code, $error_message );
	}
}