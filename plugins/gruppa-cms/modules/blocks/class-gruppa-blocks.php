<?php
defined( 'ABSPATH' ) || exit;

/**
 * Block Bindings source "gruppa/field" (WordPress 6.5+).
 *
 * Bound core blocks: Paragraph, Heading (content), Button (text, url), Image (url, alt).
 *
 * Markup example:
 *   <!-- wp:heading {"metadata":{"bindings":{"content":{"source":"gruppa/field","args":{"key":"produkty:title"}}}}} -->
 *   Args: key (required), source (current|id|slug), item (ID or slug).
 */
class Gruppa_Blocks {

	const SOURCE = 'gruppa/field';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source( self::SOURCE, array(
			'label'              => 'Gruppa',
			'get_value_callback' => array( __CLASS__, 'get_value' ),
			'uses_context'       => array( 'postId', 'postType' ),
		) );
	}

	public static function get_value( array $args, $block, $attribute ) {
		list( $type_slug, $key ) = Gruppa_Render::parse_ref( isset( $args['key'] ) ? $args['key'] : '' );
		if ( ! $type_slug ) {
			return null;
		}
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
		$found   = Gruppa_Render::item(
			$type_slug,
			isset( $args['source'] ) ? $args['source'] : 'current',
			isset( $args['item'] ) ? $args['item'] : '',
			$post_id
		);
		if ( ! $found ) {
			return null;
		}
		list( $type, $item ) = $found;

		switch ( $attribute ) {
			case 'url':
				return Gruppa_Render::url( $type, $item, $key );
			case 'alt':
			case 'title':
				$ids = Gruppa_Render::media_ids( $type, $item, $key );
				if ( $ids ) {
					$alt = get_post_meta( $ids[0], '_wp_attachment_image_alt', true );
					return 'alt' === $attribute && $alt ? $alt : get_the_title( $ids[0] );
				}
				return Gruppa_Render::text( $type, $item, $key, array( 'html' => false ) );
			case 'id':
				$ids = Gruppa_Render::media_ids( $type, $item, $key );
				return $ids ? $ids[0] : null;
			default:
				$out = Gruppa_Render::text( $type, $item, $key, array(
					'separator'   => isset( $args['separator'] ) ? (string) $args['separator'] : ', ',
					'date_format' => isset( $args['date_format'] ) ? (string) $args['date_format'] : '',
					'autop'       => false,
				) );
				// Bound attributes are inline rich text: no nested paragraphs.
				$out = preg_replace( '#</p>\s*<p[^>]*>#i', '<br><br>', $out );
				return trim( preg_replace( '#</?p[^>]*>#i', '', $out ) );
		}
	}

	public static function editor_assets() {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		wp_enqueue_script(
			'gruppa-block-bindings',
			plugin_dir_url( __FILE__ ) . 'assets/block-bindings.js',
			array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-blocks' ),
			GRUPPA_CMS_VERSION,
			true
		);
		wp_localize_script( 'gruppa-block-bindings', 'gruppaBindings', array(
			'source' => self::SOURCE,
			'all'    => Gruppa_Render::field_groups(),
			'url'    => Gruppa_Render::field_groups( array( 'system', 'text', 'media' ) ),
			'media'  => Gruppa_Render::field_groups( array( 'media' ) ),
		) );
	}
}
