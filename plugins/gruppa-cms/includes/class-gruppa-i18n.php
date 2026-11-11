<?php
defined( 'ABSPATH' ) || exit;

/**
 * Texts visitors see on the website (forms, listings, filters, login, validation)
 * follow the site language. The admin stays in English.
 *
 * Translations: /languages/gruppa-cms-{locale}.mo (sk_SK included).
 */
class Gruppa_I18n {

	const DOMAIN = 'gruppa-cms';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'load' ), 0 );
	}

	public static function load() {
		load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( GRUPPA_CMS_FILE ) ) . '/languages' );
	}

	/**
	 * A stored front text that is still the built-in default (English, or the older
	 * built-in Slovak one) is shown in the site language; a custom text stays as typed.
	 *
	 * @param string   $stored Saved value.
	 * @param string   $msgid  English default.
	 * @param string[] $legacy Older built-in defaults of the same text.
	 */
	public static function text( $stored, $msgid, $legacy = array() ) {
		$stored = (string) $stored;
		if ( $stored === $msgid || in_array( $stored, (array) $legacy, true ) ) {
			return translate( $msgid, self::DOMAIN ); // phpcs:ignore WordPress.WP.I18n
		}
		return $stored;
	}
}
