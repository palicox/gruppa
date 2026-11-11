<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Forms — actions run on submit.
 *
 *   email        send an email (notification, auto-reply…)
 *   webhook      POST the item as JSON / form data
 *   update_item  change a field of the related item(s) (Relation field)
 *   register     create a WordPress user from the submitted fields (guests)
 *
 * Every action can have conditions (all / any) on submitted values.
 * Add-ons: filter gruppa_cms/form_action_types, action gruppa_cms/form_action_{type}.
 */
class Gruppa_Form_Actions {

	const OPS = array(
		'eq'        => 'is',
		'neq'       => 'is not',
		'contains'  => 'contains',
		'empty'     => 'is empty',
		'not_empty' => 'is not empty',
		'gt'        => 'greater than',
		'lt'        => 'less than',
	);

	public static function types() {
		return apply_filters( 'gruppa_cms/form_action_types', array(
			'email'       => 'Email',
			'webhook'     => 'Webhook',
			'update_item' => 'Update related item',
			'register'    => 'Register user',
		) );
	}

	public static function defaults( $type ) {
		$common = array(
			'type'       => $type,
			'enabled'    => true,
			'title'      => '',
			'cond_match' => 'all',
			'conditions' => array(),
		);
		$own = array(
			'email'       => array( 'to' => '{admin_email}', 'subject' => 'New submission: {form}', 'message' => "{all_fields}", 'reply_to' => '', 'from_name' => '', 'from_email' => '', 'format' => 'html' ),
			'webhook'     => array( 'url' => '', 'format' => 'json', 'secret' => '' ),
			'update_item' => array( 'relation' => '', 'target' => '', 'op' => 'set', 'value' => '' ),
			'register'    => array( 'email_field' => '', 'username_field' => '', 'password_field' => '', 'first_name_field' => '', 'last_name_field' => '', 'role' => 'subscriber', 'login' => true, 'author' => true ),
		);
		// Add-on actions (e.g. Gruppa Payments) bring their own defaults.
		$extra = isset( $own[ $type ] ) ? $own[ $type ] : (array) apply_filters( 'gruppa_cms/form_action_defaults', array(), $type );
		return array_merge( $common, $extra );
	}

	/** Roles a form may assign: no role that can manage the site or users. */
	public static function safe_roles() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $r ) {
			$caps = isset( $r['capabilities'] ) ? $r['capabilities'] : array();
			if ( empty( $caps['manage_options'] ) && empty( $caps['edit_users'] ) && empty( $caps['promote_users'] ) && empty( $caps['create_users'] ) ) {
				$out[ $slug ] = translate_user_role( $r['name'] );
			}
		}
		return $out;
	}

	/* ======================================================
	 * Sanitize (form save)
	 * ==================================================== */

	public static function sanitize( $raw ) {
		$out = array();
		foreach ( (array) $raw as $a ) {
			if ( ! is_array( $a ) || empty( $a['type'] ) || ! isset( self::types()[ $a['type'] ] ) ) {
				continue;
			}
			$t = sanitize_key( $a['type'] );
			$d = self::defaults( $t );
			$s = array(
				'type'       => $t,
				'enabled'    => ! empty( $a['enabled'] ),
				'title'      => sanitize_text_field( isset( $a['title'] ) ? $a['title'] : '' ),
				'cond_match' => isset( $a['cond_match'] ) && 'any' === $a['cond_match'] ? 'any' : 'all',
				'conditions' => array(),
			);
			foreach ( (array) ( isset( $a['conditions'] ) ? $a['conditions'] : array() ) as $c ) {
				$field = isset( $c['field'] ) ? sanitize_key( $c['field'] ) : '';
				$op    = isset( $c['op'] ) && isset( self::OPS[ $c['op'] ] ) ? $c['op'] : 'eq';
				if ( '' !== $field ) {
					$s['conditions'][] = array( 'field' => $field, 'op' => $op, 'value' => sanitize_text_field( isset( $c['value'] ) ? $c['value'] : '' ) );
				}
			}
			$v = function ( $k ) use ( $a, $d ) {
				return isset( $a[ $k ] ) ? $a[ $k ] : $d[ $k ];
			};
			switch ( $t ) {
				case 'email':
					$s += array(
						'to'         => sanitize_text_field( $v( 'to' ) ),
						'subject'    => sanitize_text_field( $v( 'subject' ) ),
						'message'    => wp_kses_post( $v( 'message' ) ),
						'reply_to'   => sanitize_text_field( $v( 'reply_to' ) ),
						'from_name'  => sanitize_text_field( $v( 'from_name' ) ),
						'from_email' => sanitize_email( $v( 'from_email' ) ),
						'format'     => 'plain' === $v( 'format' ) ? 'plain' : 'html',
					);
					break;
				case 'webhook':
					$s += array(
						'url'    => esc_url_raw( trim( $v( 'url' ) ) ),
						'format' => 'form' === $v( 'format' ) ? 'form' : 'json',
						'secret' => sanitize_text_field( $v( 'secret' ) ),
					);
					break;
				case 'update_item':
					$s += array(
						'relation' => sanitize_key( $v( 'relation' ) ),
						'target'   => sanitize_key( $v( 'target' ) ),
						'op'       => in_array( $v( 'op' ), array( 'set', 'inc', 'dec' ), true ) ? $v( 'op' ) : 'set',
						'value'    => sanitize_text_field( $v( 'value' ) ),
					);
					break;
				case 'register':
					$roles = self::safe_roles();
					$s    += array(
						'email_field'      => sanitize_key( $v( 'email_field' ) ),
						'username_field'   => sanitize_key( $v( 'username_field' ) ),
						'password_field'   => sanitize_key( $v( 'password_field' ) ),
						'first_name_field' => sanitize_key( $v( 'first_name_field' ) ),
						'last_name_field'  => sanitize_key( $v( 'last_name_field' ) ),
						'role'             => isset( $roles[ $v( 'role' ) ] ) ? $v( 'role' ) : ( isset( $roles['subscriber'] ) ? 'subscriber' : (string) key( $roles ) ),
						'login'            => ! empty( $a['login'] ),
						'author'           => ! empty( $a['author'] ),
					);
					break;
				default:
					// Add-on action: keep scalar settings.
					foreach ( $a as $k => $val ) {
						if ( ! isset( $s[ $k ] ) && is_scalar( $val ) ) {
							$s[ sanitize_key( $k ) ] = sanitize_text_field( (string) $val );
						}
					}
					$s = (array) apply_filters( 'gruppa_cms/form_action_sanitize', $s, $a, $t );
			}
			$out[] = $s;
		}
		return $out;
	}

	/* ======================================================
	 * Context & placeholders
	 * ==================================================== */

	/**
	 * $ctx keys: form, type, mode, data (submitted), item (formatted array|null), item_obj (row|null).
	 */
	private static function raw( $key, $ctx ) {
		if ( ! empty( $ctx['item']['relations'][ $key ] ) && is_array( $ctx['item']['relations'][ $key ] ) ) {
			return wp_list_pluck( $ctx['item']['relations'][ $key ], 'title' ); // Data link: value titles.
		}
		if ( ! empty( $ctx['item'] ) && array_key_exists( $key, $ctx['item'] ) ) {
			return $ctx['item'][ $key ];
		}
		return isset( $ctx['data'][ $key ] ) ? $ctx['data'][ $key ] : null;
	}

	/** Readable value of a key (labels for options / relations, URLs for media). */
	public static function display( $key, $ctx ) {
		$user = wp_get_current_user();
		switch ( $key ) {
			case 'form':
				return $ctx['form']->name;
			case 'type':
				return $ctx['type']->name;
			case 'site_name':
				return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			case 'site_url':
				return home_url( '/' );
			case 'admin_email':
				return get_option( 'admin_email' );
			case 'date':
				return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
			case 'user_id':
				return $user->ID ? (string) $user->ID : '';
			case 'user_email':
				return $user->ID ? $user->user_email : '';
			case 'user_name':
				return $user->ID ? $user->display_name : '';
			case 'permalink':
				return ! empty( $ctx['item']['permalink'] ) ? $ctx['item']['permalink'] : '';
		}
		if ( ! empty( $ctx['item_obj'] ) && class_exists( 'Gruppa_Render' ) ) {
			if ( isset( Gruppa_Render::SYSTEM[ $key ] ) || Gruppa_Render::field_def( $ctx['type'], $key ) ) {
				return (string) Gruppa_Render::text( $ctx['type'], $ctx['item_obj'], $key, array( 'html' => false, 'autop' => false ) );
			}
		}
		$v = self::raw( $key, $ctx );
		if ( is_array( $v ) ) {
			return implode( ', ', array_filter( array_map( 'strval', array_filter( $v, 'is_scalar' ) ), 'strlen' ) );
		}
		return is_bool( $v ) ? ( $v ? 'Yes' : 'No' ) : (string) $v;
	}

	/** Visible form fields as [ label => value ]. */
	private static function all_fields( $ctx ) {
		$fields = array();
		foreach ( Gruppa_Types::fields_all( $ctx['type'] ) as $f ) {
			$fields[ $f['key'] ] = $f;
		}
		$out = array();
		foreach ( $ctx['form']->settings['layout'] as $e ) {
			$k = $e['key'];
			if ( $e['hidden'] || ( isset( $fields[ $k ] ) && Gruppa_Fields::is_password( $fields[ $k ] ) ) ) {
				continue;
			}
			$label = '' !== $e['label'] ? $e['label'] : ( isset( $fields[ $k ] ) ? $fields[ $k ]['label'] : ( isset( Gruppa_Forms::SYSTEM[ $k ] ) ? Gruppa_Forms::SYSTEM[ $k ] : $k ) );
			$out[ $label ] = self::display( $k, $ctx );
		}
		return $out;
	}

	/**
	 * Replace {key} placeholders.
	 *
	 * @param string $mode 'text' | 'html' (values escaped, {all_fields} as a table) | 'url'
	 */
	public static function replace( $text, $ctx, $mode = 'text' ) {
		return preg_replace_callback( '/\{([a-z0-9_]+)\}/', function ( $m ) use ( $ctx, $mode ) {
			if ( 'all_fields' === $m[1] ) {
				$rows = self::all_fields( $ctx );
				if ( 'html' === $mode ) {
					$h = '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px">';
					foreach ( $rows as $l => $v ) {
						$h .= '<tr><td style="border-bottom:1px solid #eee;vertical-align:top;width:35%"><strong>' . esc_html( $l ) . '</strong></td><td style="border-bottom:1px solid #eee">' . nl2br( esc_html( $v ) ) . '</td></tr>';
					}
					return $h . '</table>';
				}
				$t = '';
				foreach ( $rows as $l => $v ) {
					$t .= $l . ': ' . $v . "\n";
				}
				return trim( $t );
			}
			$v = self::display( $m[1], $ctx );
			if ( 'html' === $mode ) {
				return nl2br( esc_html( $v ) );
			}
			if ( 'url' === $mode ) {
				return rawurlencode( $v );
			}
			return $v;
		}, (string) $text );
	}

	/* ======================================================
	 * Conditions
	 * ==================================================== */

	public static function passes( $a, $ctx ) {
		if ( empty( $a['conditions'] ) ) {
			return true;
		}
		$any = 'any' === $a['cond_match'];
		foreach ( $a['conditions'] as $c ) {
			$ok = self::check( self::raw( $c['field'], $ctx ), $c['op'], self::replace( $c['value'], $ctx ) );
			if ( $any && $ok ) {
				return true;
			}
			if ( ! $any && ! $ok ) {
				return false;
			}
		}
		return ! $any;
	}

	private static function check( $v, $op, $want ) {
		$list = is_array( $v ) ? array_map( 'strval', array_filter( $v, 'is_scalar' ) ) : ( null === $v || '' === $v ? array() : array( is_bool( $v ) ? ( $v ? '1' : '0' ) : (string) $v ) );
		$lw   = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
		switch ( $op ) {
			case 'empty':
				return ! $list;
			case 'not_empty':
				return (bool) $list;
			case 'eq':
				return in_array( $lw( $want ), array_map( $lw, $list ), true );
			case 'neq':
				return ! in_array( $lw( $want ), array_map( $lw, $list ), true );
			case 'contains':
				foreach ( $list as $x ) {
					if ( '' !== $want && false !== strpos( $lw( $x ), $lw( $want ) ) ) {
						return true;
					}
				}
				return false;
			case 'gt':
			case 'lt':
				if ( ! $list || ! is_numeric( $list[0] ) || ! is_numeric( $want ) ) {
					return false;
				}
				return 'gt' === $op ? $list[0] + 0 > $want + 0 : $list[0] + 0 < $want + 0;
		}
		return false;
	}

	/* ======================================================
	 * Run
	 * ==================================================== */

	/**
	 * Before the item is saved (create / update). Only "register" runs here.
	 *
	 * @return array|WP_Error [ ctx ] or errors keyed by field
	 */
	public static function run_before( $ctx ) {
		foreach ( self::active( $ctx ) as $a ) {
			if ( 'register' === $a['type'] ) {
				$res = self::register( $a, $ctx );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$ctx = $res;
			}
		}
		return $ctx;
	}

	/** After save / delete. Failures never fail the submission. */
	public static function run_after( $ctx ) {
		foreach ( self::active( $ctx ) as $a ) {
			switch ( $a['type'] ) {
				case 'email':
					self::email( $a, $ctx );
					break;
				case 'webhook':
					self::webhook( $a, $ctx );
					break;
				case 'update_item':
					self::update_item( $a, $ctx );
					break;
				case 'register':
					// Ran before saving; assign author if the user was created but not logged in.
					if ( ! empty( $ctx['new_user'] ) && $a['author'] && ! $a['login'] && ! empty( $ctx['item']['id'] ) && 'delete' !== $ctx['mode'] ) {
						global $wpdb;
						$wpdb->update( Gruppa_DB::data_table( $ctx['type']->slug ), array( 'author' => (int) $ctx['new_user'] ), array( 'id' => (int) $ctx['item']['id'] ) );
						if ( $ctx['type']->has_single && $ctx['type']->post_type ) {
							Gruppa_Items::sync_post( $ctx['type'], (int) $ctx['item']['id'] );
						}
					}
					break;
				default:
					do_action( 'gruppa_cms/form_action_' . $a['type'], $a, $ctx );
			}
		}
	}

	private static function active( $ctx ) {
		$list = isset( $ctx['form']->settings['actions'] ) ? (array) $ctx['form']->settings['actions'] : array();
		return array_filter( $list, function ( $a ) use ( $ctx ) {
			return ! empty( $a['enabled'] ) && self::passes( $a, $ctx );
		} );
	}

	/* ---------- Email ---------- */

	private static function email( $a, $ctx ) {
		$to = array();
		foreach ( preg_split( '/[,;]+/', self::replace( $a['to'], $ctx ) ) as $addr ) {
			$addr = sanitize_email( trim( $addr ) );
			if ( is_email( $addr ) ) {
				$to[] = $addr;
			}
		}
		if ( ! $to ) {
			return false;
		}
		$html    = 'html' === $a['format'];
		$subject = str_replace( array( "\r", "\n" ), ' ', self::replace( wp_strip_all_tags( $a['subject'] ), $ctx ) );

		if ( $html ) {
			// Values escaped; the admin's own text may contain simple HTML. Table inserted after autop.
			$marker  = '%%GRUPPA_ALL_FIELDS%%';
			$body    = wpautop( self::replace( str_replace( '{all_fields}', $marker, $a['message'] ), $ctx, 'html' ) );
			$table   = self::replace( '{all_fields}', $ctx, 'html' );
			$body    = str_replace( array( '<p>' . $marker . '</p>', $marker ), $table, $body );
			$body    = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1d2327">' . $body . '</div>';
		} else {
			$body = self::replace( wp_strip_all_tags( $a['message'] ), $ctx );
		}

		$headers = array( 'Content-Type: ' . ( $html ? 'text/html' : 'text/plain' ) . '; charset=UTF-8' );
		if ( $a['from_email'] && is_email( $a['from_email'] ) ) {
			$name      = str_replace( array( "\r", "\n", '"' ), '', self::replace( $a['from_name'], $ctx ) );
			$headers[] = 'From: ' . ( '' !== $name ? '"' . $name . '" ' : '' ) . '<' . $a['from_email'] . '>';
		}
		$reply = sanitize_email( trim( self::replace( $a['reply_to'], $ctx ) ) );
		if ( $reply && is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		return wp_mail( $to, $subject, $body, $headers );
	}

	/* ---------- Webhook ---------- */

	private static function webhook( $a, $ctx ) {
		if ( ! $a['url'] || ! wp_http_validate_url( $a['url'] ) ) {
			return;
		}
		$user    = wp_get_current_user();
		$payload = array(
			'event'     => 'gruppa_form.' . $ctx['mode'],
			'form'      => array( 'id' => (int) $ctx['form']->id, 'slug' => $ctx['form']->slug, 'name' => $ctx['form']->name ),
			'type'      => $ctx['type']->slug,
			'item'      => $ctx['item'],
			'user'      => $user->ID ? array( 'id' => $user->ID, 'email' => $user->user_email, 'name' => $user->display_name ) : null,
			'submitted' => current_time( 'c' ),
			'site'      => home_url( '/' ),
		);
		$payload = apply_filters( 'gruppa_cms/form_webhook_payload', $payload, $a, $ctx );

		if ( 'form' === $a['format'] ) {
			$body    = http_build_query( $payload );
			$headers = array( 'Content-Type' => 'application/x-www-form-urlencoded' );
		} else {
			$body    = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$headers = array( 'Content-Type' => 'application/json' );
		}
		if ( '' !== $a['secret'] ) {
			$headers['X-Gruppa-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $a['secret'] );
		}
		wp_safe_remote_post( $a['url'], array(
			'body'     => $body,
			'headers'  => $headers,
			'timeout'  => 5,
			'blocking' => false,
		) );
	}

	/* ---------- Update related item ---------- */

	private static function update_item( $a, $ctx ) {
		$rel = null;
		foreach ( $ctx['type']->fields as $f ) {
			if ( $f['key'] === $a['relation'] && 'relation' === $f['type'] ) {
				$rel = $f;
			}
		}
		$rtype = $rel ? Gruppa_Fields::related_type( $rel ) : null;
		if ( ! $rtype || '' === $a['target'] ) {
			return;
		}
		$target = null;
		if ( 'status' !== $a['target'] ) {
			foreach ( $rtype->fields as $f ) {
				if ( $f['key'] === $a['target'] ) {
					$target = $f;
				}
			}
			if ( ! $target || Gruppa_Fields::is_password( $target ) ) {
				return;
			}
		}
		$ids   = (array) self::raw( $a['relation'], $ctx );
		$value = self::replace( $a['value'], $ctx );

		foreach ( array_filter( array_map( 'absint', $ids ) ) as $rid ) {
			$ritem = Gruppa_Items::get( $rtype, $rid );
			if ( ! $ritem ) {
				continue;
			}
			if ( 'status' === $a['target'] ) {
				Gruppa_Items::set_status( $rtype, $rid, 'draft' === strtolower( trim( $value ) ) ? 'draft' : 'publish' );
				continue;
			}
			$new = $value;
			if ( 'set' !== $a['op'] ) {
				$cur  = isset( $ritem->{$target['key']} ) && is_numeric( $ritem->{$target['key']} ) ? $ritem->{$target['key']} + 0 : 0;
				$step = is_numeric( str_replace( ',', '.', $value ) ) ? (float) str_replace( ',', '.', $value ) : 1;
				$new  = 'inc' === $a['op'] ? $cur + $step : $cur - $step;
				if ( 'number' === $target['type'] && '' !== $target['min'] && $new < $target['min'] ) {
					$new = $target['min'] + 0;
				}
				if ( 'number' === $target['type'] && '' !== $target['max'] && $new > $target['max'] ) {
					$new = $target['max'] + 0;
				}
			}
			list( $val, $err ) = Gruppa_Fields::sanitize_value( $target, Gruppa_Transfer::coerce( $target, $new ) );
			if ( ! $err ) {
				Gruppa_Items::save( $rtype, array( $target['key'] => $val ), $rid );
			}
		}
	}

	/* ---------- Register user ---------- */

	/** @return array|WP_Error ctx */
	private static function register( $a, $ctx ) {
		if ( is_user_logged_in() || 'delete' === $ctx['mode'] ) {
			return $ctx;
		}
		$get = function ( $k ) use ( $ctx ) {
			if ( '' === $k || ! isset( $ctx['data'][ $k ] ) || is_array( $ctx['data'][ $k ] ) ) {
				return '';
			}
			return trim( (string) $ctx['data'][ $k ] );
		};
		$fail = function ( $field, $msg ) {
			return new WP_Error( 'gruppa_invalid', __( 'Please check the highlighted fields.', 'gruppa-cms' ), array( 'status' => 400, 'fields' => array( $field ? $field : '_form' => $msg ) ) );
		};

		$email = sanitize_email( $get( $a['email_field'] ) );
		if ( ! $email || ! is_email( $email ) ) {
			return $fail( $a['email_field'], __( 'Enter a valid email.', 'gruppa-cms' ) );
		}
		if ( email_exists( $email ) ) {
			return $fail( $a['email_field'], __( 'This email is already registered. Please log in.', 'gruppa-cms' ) );
		}

		$login = sanitize_user( $get( $a['username_field'] ), true );
		if ( '' !== $a['username_field'] && '' !== $get( $a['username_field'] ) ) {
			if ( '' === $login || username_exists( $login ) ) {
				return $fail( $a['username_field'], '' === $login ? __( 'Invalid username.', 'gruppa-cms' ) : __( 'This username is taken.', 'gruppa-cms' ) );
			}
		} else {
			$base  = sanitize_user( strstr( $email, '@', true ), true );
			$base  = '' !== $base ? $base : 'user';
			$login = $base;
			for ( $n = 2; username_exists( $login ); $n++ ) {
				$login = $base . $n;
			}
		}

		$pass  = '' !== $a['password_field'] ? $get( $a['password_field'] ) : '';
		$given = '' !== $pass;
		if ( $given && strlen( $pass ) < 8 ) {
			return $fail( $a['password_field'], __( 'At least 8 characters.', 'gruppa-cms' ) );
		}
		$first = sanitize_text_field( $get( $a['first_name_field'] ) );
		$last  = sanitize_text_field( $get( $a['last_name_field'] ) );

		$uid = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $given ? $pass : wp_generate_password( 20 ),
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : $login,
			'role'         => $a['role'],
		) );
		if ( is_wp_error( $uid ) ) {
			return $fail( $a['email_field'], $uid->get_error_message() );
		}
		// Admin is told; the user gets a "set password" link when no password was entered.
		wp_new_user_notification( $uid, null, $given ? 'admin' : 'both' );

		if ( $a['login'] ) {
			wp_set_current_user( $uid );
			wp_set_auth_cookie( $uid, true );
		}
		$ctx['new_user'] = $uid;
		return $ctx;
	}
}
