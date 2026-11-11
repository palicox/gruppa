<?php
defined( 'ABSPATH' ) || exit;

/**
 * Elementor module: "Gruppa CMS" widget category, widgets and dynamic tags.
 *
 * Widgets: every PHP file in modules/elementor/widgets/ (or one level deeper,
 * widgets/{name}/*.php) is loaded and each class extending \Elementor\Widget_Base
 * it declares is registered. Widgets put themselves into the category with
 * get_categories() { return [ 'gruppa-cms' ]; }.
 *
 * Order in the Elementor panel = registration order (self::ORDER): Text, Search,
 * Listing, Table, Form, Login; other widgets after them. Gruppa_TB_Widget comes in
 * through the 'gruppa_cms/elementor/widget_instances' filter.
 *
 * Icons: Phosphor Icons (MIT), Regular, Duotone on hover — assets/editor-icons.css,
 * get_icon() returns 'gruppa-icon gruppa-icon-{name}'.
 *
 * Add-ons can register more widgets on the 'gruppa_cms/elementor/widgets' action.
 */
class Gruppa_Elementor {

	const CATEGORY = 'gruppa-cms';

	/** Panel order (widget name => position). */
	const ORDER = array(
		'gruppa-text'           => 1,
		'gruppa-search-filters' => 2,
		'gruppa-listing'        => 3,
		'gruppa-table'          => 4,
		'gruppa-form'           => 5,
		'gruppa-login'          => 6,
	);

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'tags' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_styles' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'editor_styles' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'editor_panel_styles' ) );
	}

	/** Widget icons in the panel (and in the Navigator of the preview). */
	public static function editor_styles() {
		wp_enqueue_style( 'gruppa-editor-icons', plugins_url( 'assets/editor-icons.css', __FILE__ ), array(), GRUPPA_CMS_VERSION );
	}

	/** Dynamic tag settings: aligned labels and inputs (editor panel only). */
	public static function editor_panel_styles() {
		wp_enqueue_style( 'gruppa-editor', plugins_url( 'assets/editor.css', __FILE__ ), array(), GRUPPA_CMS_VERSION );
	}

	/** Icon class for get_icon(): text, search, listing, table, form, login. */
	public static function icon( $name ) {
		return 'gruppa-icon gruppa-icon-' . sanitize_html_class( $name );
	}

	public static function category( $elements ) {
		$elements->add_category( self::CATEGORY, array(
			'title' => 'Gruppa CMS',
			'icon'  => 'eicon-wordpress',
		) );
	}

	public static function widgets( $manager ) {
		$files = array_merge(
			(array) glob( __DIR__ . '/widgets/*.php' ),
			(array) glob( __DIR__ . '/widgets/*/*.php' )
		);
		sort( $files );

		$before = get_declared_classes();
		foreach ( $files as $file ) {
			if ( 'index.php' !== basename( $file ) ) {
				require_once $file;
			}
		}
		$list = array();
		foreach ( array_diff( get_declared_classes(), $before ) as $class ) {
			$ref = new ReflectionClass( $class );
			if ( ! $ref->isAbstract() && $ref->isSubclassOf( '\Elementor\Widget_Base' ) ) {
				$list[] = new $class();
			}
		}
		$list = apply_filters( 'gruppa_cms/elementor/widget_instances', $list );

		$pos = array();
		foreach ( $list as $i => $w ) {
			$n         = $w->get_name();
			$pos[ $i ] = isset( self::ORDER[ $n ] ) ? self::ORDER[ $n ] : 100 + $i;
		}
		asort( $pos );
		foreach ( array_keys( $pos ) as $i ) {
			$manager->register( $list[ $i ] );
		}

		do_action( 'gruppa_cms/elementor/widgets', $manager );
	}

	public static function tags( $manager ) {
		$dir = __DIR__ . '/tags/';
		require_once $dir . 'trait-gruppa-tag.php';
		require_once $dir . 'class-tag-field.php';
		require_once $dir . 'class-tag-url.php';
		require_once $dir . 'class-tag-image.php';
		require_once $dir . 'class-tag-gallery.php';

		$manager->register_group( 'gruppa', array( 'title' => 'Gruppa CMS' ) );
		$manager->register( new Gruppa_Tag_Field() );
		$manager->register( new Gruppa_Tag_URL() );
		$manager->register( new Gruppa_Tag_Image() );
		$manager->register( new Gruppa_Tag_Gallery() );
	}
}
