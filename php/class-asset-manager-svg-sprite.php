<?php
/**
 * Class file for Asset_Manager_SVG_Sprite
 *
 * @package AssetManager
 */

/**
 * Asset_Manager_SVG_Sprite class.
 *
 * @todo add_action: modify_svg_asset.
 */
class Asset_Manager_SVG_Sprite {
	use Conditions;

	/**
	 * Holds references to the singleton instances.
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Directory from which relative paths will be completed.
	 *
	 * @var string
	 */
	public static $_svg_directory; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Array fo attributes to add to each symbol.
	 *
	 * @var array
	 */
	public static $_global_attributes; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The sprite document.
	 *
	 * @var DOMDocument
	 */
	public $sprite_document;

	/**
	 * The allowed HTML elements and attributes for use in `wp_kses` when printing
	 * the output of `am_use_symbol`.
	 *
	 * @var array
	 */
	public $symbol_allowed_html = [
		'svg' => [
			'height' => true,
			'width'  => true,
			'class'  => true,
		],
		'use' => [
			'href' => true,
		],
	];

	/**
	 * The allowed HTML elements and attributes for use in `wp_kses` when printing
	 * the sprite sheet.
	 *
	 * @var array
	 */
	public $sprite_allowed_html = [
		'svg'    => [
			'style' => true,
			'xmlns' => true,
		],
		'symbol' => [
			'id'      => true,
			'viewbox' => true, // `viewBox` must be lowercase here.
		],
	];

	/**
	 * Reference array of asset handles.
	 *
	 * @var array
	 */
	public $asset_handles = [];

	/**
	 * Mapping of definitions for symbols added to the sprite.
	 *
	 * @var array
	 */
	public $sprite_map = [];

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Don't do anything, needs to be initialized via instance() method.
	}

	/**
	 * Get an instance of the class.
	 *
	 * @return Asset_Manager_SVG_Sprite
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
			self::$instance->setup();
			self::$instance->create_sprite_sheet();
		}

		return self::$instance;
	}

	/**
	 * Get the SVG directory.
	 */
	public function get_svg_directory() {
		if ( ! isset( static::$_svg_directory ) ) {
			/**
			 * Filter function for updating the directory upon which a symbol's relative
			 * path will be based.
			 *
			 * @since 0.1.3
			 *
			 * @param string $path The absolute root for relative SVG paths.
			 */
			static::$_svg_directory = apply_filters( 'am_modify_svg_directory', get_stylesheet_directory() );
		}

		return static::$_svg_directory;
	}

	/**
	 * Get the SVG directory.
	 */
	public function get_global_attributes() {
		if ( ! isset( static::$_global_attributes ) ) {
			/**
			 * Filter function for configuring attributes to be added to all SVG symbols.
			 *
			 * @since 0.1.3
			 *
			 * @param array $attributes {
			 *     A list of attributes to be added to all SVG symbols.
			 *
			 *     @type array $attribute Attribute name-value pairs.
			 * }
			 */
			static::$_global_attributes = apply_filters( 'am_svg_attributes', [] );
		}

		return static::$_global_attributes;
	}

	/**
	 * Perform setup tasks.
	 */
	public function setup() {
		/**
		 * Updates allowed inline style properties.
		 *
		 * @param  string[] $styles Array of allowed CSS properties.
		 * @return string[]         Modified safe inline style properties.
		 */
		add_filter(
			'safe_style_css',
			function( $styles ) {
				$styles[] = 'display';
				return $styles;
			}
		);
	}

	/**
	 * Creates the sprite sheet.
	 */
	public function create_sprite_sheet() {
		$this->sprite_document = new DOMDocument();

		$this->svg_root = $this->sprite_document->createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
		$this->svg_root->setAttribute( 'style', 'display:none' );

		$this->sprite_document->appendChild( $this->svg_root );

		add_action( 'wp_body_open', [ $this, 'print_sprite_sheet' ], 10 );
	}

	/**
	 * Prints the sprite sheet to the page at `wp_body_open`.
	 */
	public function print_sprite_sheet() {
		/**
		 * Filter function for patching in missing attributes and alements for escaping with
		 * `wp_kses`, particularly `xmlns:*` attributes, which DOMDocument doesn't handle well.
		 *
		 * @since 0.1.3
		 *
		 * @param array $allowed_html wp_kses allowed HTML for the sprite sheet.
		 */
		$this->sprite_allowed_html = apply_filters( 'am_sprite_allowed_html', $this->sprite_allowed_html );

		echo wp_kses(
			$this->sprite_document->C14N(),
			$this->sprite_allowed_html
		);
	}

	/**
	 * Convenience function that returns the symbol id based on the asset handle.
	 *
	 * @param  string $handle The asset handle.
	 * @return string         The asset handle formatted for use as the symbol id.
	 */
	public function format_handle_as_symbol_id( $handle ) {
		return "am-symbol-{$handle}";
	}


	/**
	 * Evaluates and returns the filepath for a given file.
	 *
	 * @param  string $path The relative or absolute path to the SVG file.
	 * @return string       The absolute filepath.
	 */
	public function get_the_normalized_filepath( $path ) {
		if ( empty( $path ) ) {
			return '';
		}

		// Build the file path, validating absolute or relative path.
		return ( DIRECTORY_SEPARATOR === $path[0] )
			? $path
			: rtrim( $this->get_svg_directory(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $path;
	}

	/**
	 * Update allowed HTML.
	 *
	 * @param array $attributes Asset attributes.
	 */
	public function update_allowed_html( $attributes ) {
		foreach ( array_keys( $attributes ) as $attr ) {
			$this->symbol_allowed_html['svg'][ $attr ] = true;
		}
	}

	/**
	 * Returns a formatted integer value.
	 *
	 * @param  int|float $value     The value to format.
	 * @param  int       $precision The formatting precision.
	 * @return int|float            The value with the proper precision.
	 */
	public function format_precision( $value, $precision = 2 ) {
		$pow = pow( 10, $precision );
		return ( intval( $value * $pow ) / $pow );
	}

	/**
	 * Returns the contents of an SVG file.
	 *
	 * @param string $path The SVG file path.
	 * @return DOMDocument The SVG file contents.
	 */
	public function get_svg( $path ) {
		if ( empty( $path ) ) {
			return '';
		}

		if ( file_exists( $path ) && 0 === validate_file( $path ) ) {
			$file_contents = function_exists( 'wpcom_vip_file_get_contents' )
				? wpcom_vip_file_get_contents( $path )
				: file_get_contents( $path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

			if ( ! empty( $file_contents ) ) {
				$doc = new DOMDocument();
				$doc->loadXML( $file_contents );
				$svg = $doc->getElementsByTagName( 'svg' );

				if ( ! empty( $svg->item( 0 ) ) ) {
					return $svg->item( 0 );
				}
			}
		}

		return false;
	}

	/**
	 * Collect elements and attributes for the `wp_kses` allowed_html used to escape the sprite sheet.
	 *
	 * @param DOMElement $element The element from which attributes are to be collected.
	 * @param bool       $recurse Whether or not to recurse through childNodes.
	 */
	public function compile_allowed_html( $element, $recurse = true ) {
		if ( $element instanceof DOMElement ) {
			/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

			// Be sure the element itself is allowed.
			if ( empty( $this->sprite_allowed_html[ $element->nodeName ] ) ) {
				$this->sprite_allowed_html[ $element->nodeName ] = [];
			}

			// Collect attributes.
			if ( $element->hasAttributes() ) {
				foreach ( $element->attributes as $attr ) {
					$this->sprite_allowed_html[ $element->nodeName ][ $attr->nodeName ] = true;
				}
			}

			// Recurse through child nodes.
			if ( $recurse && $element->hasChildNodes() ) {
				foreach ( iterator_to_array( $element->childNodes ) as $child_node ) {
					$this->compile_allowed_html( $child_node );
				}
			}

			/* phpcs:enable */
		}
	}

	/**
	 * Determine an asset's default dimensions.
	 *
	 * @param  DOMDocument $svg   The SVG contents.
	 * @param  array       $asset The asset definition.
	 * @return array              The height and width to use for the asset.
	 */
	public function get_default_dimensions( $svg, $asset ) {
		$attributes = $asset['attributes'] ?? [];

		// Default to the height and width attributes from the asset definition.
		if ( ! empty( $attributes['height'] ) && ! empty( $attributes['width'] ) ) {
			return [
				'width'  => (int) $attributes['width'],
				'height' => (int) $attributes['height'],
			];
		}

		// Fall back to <svg> attribute values if we have both.
		$width_attr  = (int) $svg->getAttribute( 'width' ) ?? 0;
		$height_attr = (int) $svg->getAttribute( 'height' ) ?? 0;

		if ( ! empty( $width_attr ) && ! empty( $height_attr ) ) {
			return [
				'width'  => $width_attr,
				'height' => $height_attr,
			];
		}

		// Use the viewBox attribute values if neither of the above are present.
		$viewbox = $svg->getAttribute( 'viewBox' ) ?? '';

		if ( ! empty( $viewbox ) ) {
			// 0. min-x, 1. min-y, 2. width, 3. height.
			$viewbox_attr = explode( ' ', $viewbox );

			if ( ! empty( $viewbox_attr[2] ) && ! empty( $viewbox_attr[3] ) ) {
				return [
					'width'  => (int) $viewbox_attr[2],
					'height' => (int) $viewbox_attr[3],
				];
			}
		}

		// We tried...
		return [
			'width'  => 0,
			'height' => 0,
		];
	}

	/**
	 * Perform final mutations before adding an asset to sprite.
	 *
	 * @param array $asset Asset to mutate.
	 * @return array The modified asset definition.
	 */
	public function pre_add_asset( $asset ) {
		$src = $this->get_the_normalized_filepath( $asset['src'] );

		return ( empty( $src ) )
			? $asset
			: array_merge( $asset, [ 'src' => $src ] );
	}

	/**
	 * Adds an asset to the sprite sheet.
	 *
	 * @param array $asset An asset definition.
	 * @return void
	 */
	public function add_asset( $asset ): void {
		if ( ! $this->asset_should_add( $asset ) ) {
			return;
		}

		$asset = $this->pre_add_asset( $asset );

		// Get the SVG file contents.
		$svg = $this->get_svg( $asset['src'] ?? '' );

		if ( ! ( $svg instanceof DOMElement ) ) {
			return;
		}

		/*
		 * Try to determine a default size for the SVG.
		 * These dimensions are used to create a ratio for setting the symbol
		 * size when only one dimension is passed via `am_use_symbol()`
		 */
		$default_dimensions = $this->get_default_dimensions( $svg, $asset );

		if ( ! empty( $default_dimensions['width'] ) && ! empty( $default_dimensions['height'] ) ) {
			$asset['attributes'] = array_merge( $asset['attributes'] ?? [], $default_dimensions );
		}

		// Create the <symbol> element.
		$symbol = $this->sprite_document->createElement( 'symbol' );

		// Add the id attribute.
		$symbol->setAttribute( 'id', $this->format_handle_as_symbol_id( $asset['handle'] ) );

		// Use the viewBox attribute from the SVG asset.
		$viewbox = $svg->getAttribute( 'viewBox' ) ?? '';

		if ( ! empty( $viewbox ) ) {
			$symbol->setAttribute( 'viewBox', $viewbox );
		}

		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

		// Add the SVG's childNodes to the symbol.
		foreach ( iterator_to_array( $svg->childNodes ) as $child_node ) {
			if (
				! ( $child_node instanceof DOMText ) // Exclude text nodes.
				&& 'script' !== $child_node->nodeName // Exclude <script> tags.
			) {
				$symbol->appendChild( $this->sprite_document->importNode( $child_node, true ) );
			}
		}

		$this->compile_allowed_html( $symbol );

		/* phpcs:enable */

		// Append the symbol to the SVG sprite.
		$this->svg_root->appendChild( $symbol );

		$this->asset_handles[]                = $asset['handle'];
		$this->sprite_map[ $asset['handle'] ] = $asset;
	}

	/**
	 * Returns the SVG markup for displaying a symbol.
	 *
	 * @param  string $handle The symbol handle.
	 * @param  array  $attrs  Additional attributes to add to the <svg> element.
	 * @return string         The <svg> and <use> elements for displaying a symbol.
	 */
	public function get_symbol( $handle, $attrs = [] ) {
		if ( empty( $handle ) || ! in_array( $handle, array_keys( $this->sprite_map ), true ) ) {
			return '';
		}

		$asset = $this->sprite_map[ $handle ];

		if ( empty( $asset ) ) {
			return '';
		}

		/*
		 * Use the dimensions from `get_default_dimensions()` to calculate the
		 * expected size when only one dimension is provided in $attrs.
		 */
		if ( ! empty( $asset['attributes']['width'] ) && ! empty( $asset['attributes']['height'] ) ) {
			$use_ratio_for_width  = ( empty( $attrs['width'] ) && ! empty( $attrs['height'] ) );
			$use_ratio_for_height = ( empty( $attrs['height'] ) && ! empty( $attrs['width'] ) );

			$ratio = ( $asset['attributes']['width'] / $asset['attributes']['height'] );

			if ( $use_ratio_for_width ) {
				// width from height: ratio * height.
				$attrs['width'] = $this->format_precision( $ratio * $attrs['height'] );
			} elseif ( $use_ratio_for_height ) {
				// height from width: width / ratio.
				$attrs['height'] = $this->format_precision( $attrs['width'] / $ratio );
			}
		}

		// Merge attributes.
		$local_attrs = array_merge(
			$this->get_global_attributes(),
			$asset['attributes'] ?? [],
			$attrs
		);
		$local_attrs = array_map( 'esc_attr', $local_attrs );

		// Ensure attributes are in allowed_html.
		$this->update_allowed_html( $local_attrs );

		// Build a string of all attributes.
		$attrs = '';
		foreach ( $local_attrs as $name => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$attrs .= " $name=" . '"' . $value . '"';
		}

		return sprintf(
			'<svg %1$s><use href="#%2$s"></use></svg>',
			trim( $attrs ),
			esc_attr( $this->format_handle_as_symbol_id( $handle ) )
		);
	}

	/**
	 * Print a symbol's SVG markup.
	 *
	 * @param  string $handle The asset handle.
	 * @param  array  $attrs  Additional HTML attributes to add to the SVG markup.
	 */
	public function use_symbol( $handle, $attrs = [] ) {
		$symbol_markup = $this->get_symbol( $handle, $attrs );

		if ( ! empty( $symbol_markup ) ) {
			echo wp_kses( $symbol_markup, $this->symbol_allowed_html );
		}
	}
}
