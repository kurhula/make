<?php
/**
 * @package Make
 */


final class MAKE_Font_Source_Google extends MAKE_Font_Source_Base implements MAKE_Util_LoadInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'compatibility' => 'MAKE_Compatibility_MethodsInterface',
	);

	/**
	 * Array to collect the available subsets in the Google data.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	private $subsets = array();

	/**
	 * CSS font stacks for Google's font categories.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	private $stacks = array(
		'serif' => 'Georgia,Times,"Times New Roman",serif',
		'sans-serif' => '"Helvetica Neue",Helvetica,Arial,sans-serif',
		'display' => 'Copperplate,Copperplate Gothic Light,fantasy',
		'handwriting' => 'Brush Script MT,cursive',
		'monospace' => 'Monaco,"Lucida Sans Typewriter","Lucida Typewriter","Courier New",Courier,monospace',
	);

	/**
	 * Indicator of whether the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * MAKE_Font_Source_Google constructor.
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
		// Load dependencies.
		parent::__construct( $api, $modules );

		// Set the ID.
		$this->id = 'google';

		// Set the label.
		$this->label = __( 'Google Fonts', 'make' );

		// Set the priority.
		$this->priority = 20;
	}

	/**
	 * Load data files.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function load() {
		// Load the font data file.
		$file = dirname( __FILE__ ) . '/google-data.php';
		if ( is_readable( $file ) ) {
			include_once $file;
		}

		// Loading has occurred.
		$this->loaded = true;
	}

	/**
	 * Check if the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return $this->loaded;
	}

	/**
	 * Setter for the source's data property.
	 *
	 * @since x.x.x.
	 *
	 * @param array $data
	 */
	private function load_font_data( array $data ) {
		$this->data = $data;
	}

	/**
	 * Wrapper to ensure the font data is loaded before retrieving it.
	 *
	 * @since x.x.x.
	 *
	 * @param string $font
	 *
	 * @return array
	 */
	public function get_font_data( $font = null ) {
		// Load the font data if necessary.
		if ( ! $this->is_loaded() ) {
			$this->load();
		}

		return parent::get_font_data( $font );
	}

	/**
	 * Append a CSS font stack to a font, based on its category. Return a default stack if the font doesn't exist.
	 *
	 * @since x.x.x.
	 *
	 * @param string $font
	 * @param string $default_stack
	 *
	 * @return string
	 */
	public function get_font_stack( $font, $default_stack = 'sans-serif' ) {
		$data = $this->get_font_data( $font );

		if ( isset( $data['category'] ) && $category_stack = $this->get_category_stack( $data['category'] ) ) {
			$stack = "\"$font\"," . $category_stack;
		} else {
			$stack = $default_stack;
		}

		return $stack;
	}

	/**
	 * Retrieve the CSS font stack for a particular font category.
	 *
	 * @since x.x.x.
	 *
	 * @param $category
	 *
	 * @return mixed|void
	 */
	private function get_category_stack( $category ) {
		$stack = '';

		if ( isset( $this->stacks[ $category ] ) ) {
			$stack = $this->stacks[ $category ];
		}

		/**
		 * Filter: Modify the CSS font stack for a particular category of Google font.
		 *
		 * @since x.x.x.
		 *
		 * @param string $stack    The CSS font stack.
		 * @param string $category The font category.
		 */
		return apply_filters( 'make_font_google_stack', $stack, $category );
	}

	/**
	 * Build the URL for loading Google fonts, given an array of fonts used in the theme and an array of subsets.
	 *
	 * @since x.x.x.
	 *
	 * @param array $fonts
	 * @param array $subsets
	 *
	 * @return mixed|string|void
	 */
	public function build_url( array $fonts, array $subsets = array() ) {
		$url = '';
		$fonts = array_unique( $fonts );
		$family = array();

		foreach ( $fonts as $font ) {
			// Verify that the font exists
			if ( $this->has_font( $font ) ) {
				$font_data = $this->get_font_data( $font );
				$font_variants = ( isset( $font_data['variants'] ) ) ? $font_data['variants'] : array();

				// Build the family name and variant string (e.g., "Open+Sans:regular,italic,700")
				$family[] = urlencode( $font . ':' . join( ',', $this->choose_font_variants( $font, $font_variants ) ) );
			}
		}

		if ( ! empty( $family ) ) {
			// Start building the URL.
			$base_url = '//fonts.googleapis.com/css';

			// Add families
			$url = add_query_arg( 'family', implode( '|', $family ), $base_url );

			// Add subsets, if specified.
			if ( ! empty( $subsets ) ) {
				$subsets = array_map( 'sanitize_key', $subsets );
				$url = add_query_arg( 'subset', join( ',', $subsets ), $url );
			}
		}

		/**
		 * Filter the Google Fonts URL.
		 *
		 * @since 1.2.3.
		 *
		 * @param string    $url    The URL to retrieve the Google Fonts.
		 */
		return apply_filters( 'make_get_google_font_uri', $url );
	}

	/**
	 * Build an array of data that can be converted to JSON and fed into the Web Font Loader, given an array of fonts used in the theme and an array of subsets.
	 *
	 * @param array $fonts
	 * @param array $subsets
	 *
	 * @return array
	 */
	public function build_loader_array( array $fonts, array $subsets = array() ) {
		$data = array();
		$families = array();
		$fonts = array_unique( $fonts );
		$subsets = array_map( 'sanitize_key', $subsets );

		foreach ( $fonts as $font ) {
			// Verify that the font exists
			if ( $this->has_font( $font ) ) {
				$font_data = $this->get_font_data( $font );
				$font_variants = ( isset( $font_data['variants'] ) ) ? $font_data['variants'] : array();

				// Build the family name, variant, and subset string (e.g., "Open+Sans:regular,italic,700:latin")
				$families[] = urlencode( $font ) . ':' . join( ',', $this->choose_font_variants( $font, $font_variants ) ) . ':' . join( ',', $subsets );
			}
		}

		if ( ! empty( $families ) ) {
			$data['google'] = array(
				'families' => $families
			);
		}

		return $data;
	}

	/**
	 * Choose font variants to load for a given font, based on what's available.
	 *
	 * @since x.x.x.
	 *
	 * @param string $font
	 * @param array  $available_variants
	 *
	 * @return array
	 */
	private function choose_font_variants( $font, array $available_variants ) {
		$chosen_variants = array();

		// If a "regular" variant is not found, get the first variant.
		if ( ! in_array( 'regular', $available_variants ) && count( $available_variants ) >= 1 ) {
			$chosen_variants[] = $available_variants[0];
		} else {
			$chosen_variants[] = 'regular';
		}

		// Only add "italic" if it exists.
		if ( in_array( 'italic', $available_variants ) ) {
			$chosen_variants[] = 'italic';
		}

		// Only add "700" if it exists.
		if ( in_array( '700', $available_variants ) ) {
			$chosen_variants[] = '700';
		}

		// De-dupe.
		$chosen_variants = array_unique( $chosen_variants );

		// Check for deprecated filter
		if ( has_filter( 'make_font_variants' ) ) {
			$this->compatibility()->deprecated_hook(
				'make_font_variants',
				'1.7.0',
				__( 'Use the make_font_google_variants hook instead.', 'make' )
			);

			/**
			 * Allow developers to alter the font variant choice.
			 *
			 * @since 1.2.3.
			 * @deprecated x.x.x.
			 *
			 * @param array     $variants    The list of variants for a font.
			 * @param string    $font        The font to load variants for.
			 * @param array     $variants    The variants for the font.
			 */
			$chosen_variants = apply_filters( 'make_font_variants', $chosen_variants, $font, $available_variants );
		}

		/**
		 * Allow developers to alter the Google font variant choice.
		 *
		 * @since x.x.x.
		 *
		 * @param array     $variants    The list of variants for a font.
		 * @param string    $font        The font to load variants for.
		 * @param array     $variants    The variants for the font.
		 */
		return apply_filters( 'make_font_google_variants', $chosen_variants, $font, $available_variants );
	}

	/**
	 * Iterate through all the Google font data and build a list of unique subset options.
	 *
	 * @since x.x.x.
	 *
	 * @param array $font_data
	 *
	 * @return array
	 */
	private function collect_subsets( array $font_data ) {
		$subsets = array();

		foreach ( $font_data as $font => $data ) {
			if ( isset( $data['subsets'] ) ) {
				$subsets = array_merge( $subsets, (array) $data['subsets'] );
			}
		}

		$subsets = array_unique( $subsets );
		sort( $subsets );

		return $subsets;
	}

	/**
	 * Getter for the $subsets property.
	 *
	 * @since x.x.x.
	 *
	 * @return array
	 */
	public function get_subsets() {
		if ( empty( $this->subsets ) ) {
			$this->subsets = $this->collect_subsets( $this->get_font_data() );
		}

		// Check for deprecated filter
		if ( has_filter( 'make_get_google_font_subsets' ) ) {
			$this->compatibility()->deprecated_hook(
				'make_get_google_font_subsets',
				'1.7.0'
			);
		}

		return $this->subsets;
	}

	/**
	 * Verify that a subset choice is valid. Return a default value if it's not.
	 *
	 * @since x.x.x.
	 *
	 * @param        $value
	 * @param string $default
	 *
	 * @return string
	 */
	public function sanitize_subset( $value, $default = '' ) {
		if ( in_array( $value, $this->get_subsets() ) ) {
			return $value;
		}

		return $default;
	}
}