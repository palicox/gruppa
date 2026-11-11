<?php
/**
 * Natívny Web Push (VAPID + RFC 8291 aes128gcm) bez externých knižníc.
 */
defined( 'ABSPATH' ) || exit;

class Gruppa_Notify_WebPush {

	/* ---------- base64url ---------- */

	public static function b64u_enc( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	public static function b64u_dec( $data ) {
		$data = strtr( $data, '-_', '+/' );
		$pad  = strlen( $data ) % 4;
		if ( $pad ) {
			$data .= str_repeat( '=', 4 - $pad );
		}
		return base64_decode( $data );
	}

	/* ---------- kľúče ---------- */

	/**
	 * Vygeneruje VAPID pár. Vracia [ 'private_pem' => ..., 'public' => b64url(raw 65B) ].
	 */
	public static function generate_vapid() {
		$key = self::new_ec_key();
		if ( ! $key ) {
			return false;
		}
		if ( ! openssl_pkey_export( $key, $pem, null, self::openssl_config() ) || ! $pem ) {
			self::clear_errors();
			return false;
		}
		return [
			'private_pem' => $pem,
			'public'      => self::b64u_enc( self::raw_public( $key ) ),
		];
	}

	/** True once the server's own openssl.cnf turned out to be missing — then the bundled one is used. */
	private static $own_cnf = false;

	private static function openssl_config() {
		$c = [
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		];
		if ( self::$own_cnf ) {
			$c['config'] = __DIR__ . '/openssl.cnf';
		}
		return $c;
	}

	/**
	 * New P-256 key. Local servers (Local, XAMPP, MAMP, Windows) often have no openssl.cnf
	 * and openssl_pkey_new() fails — retry with the minimal config bundled with the plugin.
	 */
	private static function new_ec_key() {
		$key = function_exists( 'openssl_pkey_new' ) ? openssl_pkey_new( self::openssl_config() ) : false;
		if ( ! $key && ! self::$own_cnf && function_exists( 'openssl_pkey_new' ) ) {
			self::$own_cnf = true;
			$key           = openssl_pkey_new( self::openssl_config() );
		}
		self::clear_errors();
		return $key;
	}

	private static function clear_errors() {
		if ( function_exists( 'openssl_error_string' ) ) {
			while ( openssl_error_string() ) { // phpcs:ignore
			}
		}
	}

	/** Why keys cannot be generated (for the admin notice). */
	public static function diagnose() {
		if ( ! extension_loaded( 'openssl' ) ) {
			return 'PHP extension OpenSSL is not loaded.';
		}
		if ( function_exists( 'openssl_get_curve_names' ) && ! in_array( 'prime256v1', (array) openssl_get_curve_names(), true ) ) {
			return 'OpenSSL ' . OPENSSL_VERSION_TEXT . ' does not support the prime256v1 curve.';
		}
		self::$own_cnf = false;
		openssl_pkey_new( self::openssl_config() );
		$err = function_exists( 'openssl_error_string' ) ? openssl_error_string() : '';
		self::clear_errors();
		return trim( OPENSSL_VERSION_TEXT . ( $err ? ' — ' . $err : '' ) );
	}

	/** Raw nekomprimovaný verejný bod 0x04 || X || Y (65 B). */
	private static function raw_public( $key ) {
		$d = openssl_pkey_get_details( $key );
		$x = str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT );
		$y = str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
		return "\x04" . $x . $y;
	}

	/** Raw bod P-256 → PEM verejného kľúča (SPKI). */
	private static function raw_to_pem( $raw ) {
		$der = hex2bin( '3059301306072a8648ce3d020106082a8648ce3d030107034200' ) . $raw;
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	/* ---------- VAPID JWT (ES256) ---------- */

	private static function vapid_header( $endpoint, $private_pem, $public_b64u, $subject ) {
		$p   = wp_parse_url( $endpoint );
		$aud = $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );

		$header  = self::b64u_enc( wp_json_encode( [ 'typ' => 'JWT', 'alg' => 'ES256' ] ) );
		$payload = self::b64u_enc( wp_json_encode( [
			'aud' => $aud,
			'exp' => time() + 12 * HOUR_IN_SECONDS,
			'sub' => $subject,
		] ) );
		$input = $header . '.' . $payload;

		if ( ! openssl_sign( $input, $der, $private_pem, OPENSSL_ALGO_SHA256 ) ) {
			return false;
		}
		$jwt = $input . '.' . self::b64u_enc( self::der_to_raw( $der ) );

		return 'vapid t=' . $jwt . ', k=' . $public_b64u;
	}

	/** DER podpis ECDSA → raw r||s (64 B). */
	private static function der_to_raw( $der ) {
		$pos = 2;
		if ( ord( $der[1] ) & 0x80 ) {
			$pos += ord( $der[1] ) & 0x7f;
		}
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$pos++; // 0x02
			$len  = ord( $der[ $pos ] );
			$pos++;
			$int  = substr( $der, $pos, $len );
			$pos += $len;
			$int  = ltrim( $int, "\0" );
			$out .= str_pad( $int, 32, "\0", STR_PAD_LEFT );
		}
		return $out;
	}

	/* ---------- šifrovanie payloadu (RFC 8291) ---------- */

	public static function encrypt( $payload, $ua_public_b64u, $auth_b64u ) {
		$ua_public = self::b64u_dec( $ua_public_b64u );
		$auth      = self::b64u_dec( $auth_b64u );
		if ( strlen( $ua_public ) !== 65 || strlen( $auth ) !== 16 ) {
			return false;
		}

		$as_key    = self::new_ec_key();
		$as_public = self::raw_public( $as_key );
		$peer      = openssl_pkey_get_public( self::raw_to_pem( $ua_public ) );
		if ( ! $as_key || ! $peer ) {
			return false;
		}
		$shared = openssl_pkey_derive( $peer, $as_key, 32 );
		if ( false === $shared ) {
			return false;
		}

		$key_info = "WebPush: info\0" . $ua_public . $as_public;
		$ikm      = hash_hkdf( 'sha256', $shared, 32, $key_info, $auth );

		$salt  = random_bytes( 16 );
		$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
		$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );

		$tag = '';
		$ct  = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $ct ) {
			return false;
		}

		$header = $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_public;
		return $header . $ct . $tag;
	}

	/* ---------- odoslanie ---------- */

	/**
	 * Vracia HTTP kód (201 = OK, 404/410 = subscription neplatná) alebo 0 pri chybe.
	 */
	public static function send( $sub, $payload, $vapid, $subject ) {
		$body = self::encrypt( $payload, $sub['p256dh'], $sub['auth'] );
		if ( false === $body ) {
			return 0;
		}
		$auth = self::vapid_header( $sub['endpoint'], $vapid['private_pem'], $vapid['public'], $subject );
		if ( false === $auth ) {
			return 0;
		}

		$res = wp_remote_post( $sub['endpoint'], [
			'timeout' => 10,
			'headers' => [
				'Authorization'    => $auth,
				'Content-Type'     => 'application/octet-stream',
				'Content-Encoding' => 'aes128gcm',
				'TTL'              => (string) DAY_IN_SECONDS,
				'Urgency'          => 'high',
			],
			'body'    => $body,
		] );

		return is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	}
}
