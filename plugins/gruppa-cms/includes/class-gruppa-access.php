<?php
defined( 'ABSPATH' ) || exit;

/**
 * Per-Type access rules (REST API; Dynamic Forms unless the form sets its own rule).
 *
 * Rule values: 'public' | 'logged_in' | 'author' (update / delete: own items only) | 'role:{role}' | 'cap' (+ {action}_cap).
 * Administrators (manage_options) are always allowed.
 */
class Gruppa_Access {

	const ACTIONS = array( 'read', 'create', 'update', 'delete' );

	public static function defaults() {
		return array(
			'rest'       => false,
			'read'       => 'public',
			'read_cap'   => '',
			'create'     => 'role:administrator',
			'create_cap' => '',
			'update'     => 'role:administrator',
			'update_cap' => '',
			'delete'     => 'role:administrator',
			'delete_cap' => '',
		);
	}

	/** Options for an action's select. Public is not offered for update/delete. */
	public static function options( $action ) {
		$o = array();
		if ( in_array( $action, array( 'read', 'create' ), true ) ) {
			$o['public'] = 'Public';
		}
		$o['logged_in'] = 'Logged in';
		if ( in_array( $action, array( 'update', 'delete' ), true ) ) {
			$o['author'] = 'Author of the item (own items)';
		}
		foreach ( wp_roles()->get_names() as $role => $name ) {
			$o[ 'role:' . $role ] = 'Role: ' . translate_user_role( $name );
		}
		$o['cap'] = 'Capability…';
		return $o;
	}

	public static function get( $type ) {
		$s = array();
		if ( ! empty( $type->settings ) ) {
			$s = is_array( $type->settings ) ? $type->settings : json_decode( $type->settings, true );
		}
		$s = isset( $s['access'] ) && is_array( $s['access'] ) ? $s['access'] : array();
		return wp_parse_args( $s, self::defaults() );
	}

	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array( 'rest' => ! empty( $raw['rest'] ) );
		foreach ( self::ACTIONS as $a ) {
			$opts            = self::options( $a );
			$v               = isset( $raw[ $a ] ) ? (string) $raw[ $a ] : '';
			$out[ $a ]       = isset( $opts[ $v ] ) ? $v : self::defaults()[ $a ];
			$out[ $a . '_cap' ] = sanitize_key( isset( $raw[ $a . '_cap' ] ) ? $raw[ $a . '_cap' ] : '' );
			if ( 'cap' === $out[ $a ] && '' === $out[ $a . '_cap' ] ) {
				$out[ $a ] = 'role:administrator';
			}
		}
		return $out;
	}

	/** Rule of an action. */
	public static function rule( $type, $action ) {
		$s = self::get( $type );
		return isset( $s[ $action ] ) ? $s[ $action ] : 'role:administrator';
	}

	/**
	 * @param object|null $item With rule "author": null = may try (check the item later), object = must be the author.
	 */
	public static function can( $type, $action, $user_id = null, $item = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		$s    = self::get( $type );
		$rule = isset( $s[ $action ] ) ? $s[ $action ] : 'role:administrator';
		return self::allows( $rule, $action, $user_id, $item, isset( $s[ $action . '_cap' ] ) ? $s[ $action . '_cap' ] : '' );
	}

	/**
	 * Does a single rule allow the user? (Administrators are checked by the caller.)
	 * Also used by forms with their own "Who can submit" rule.
	 */
	public static function allows( $rule, $action, $user_id = null, $item = null, $cap = '' ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( 'author' === $rule ) {
			if ( ! $user_id ) {
				return false;
			}
			return null === $item || (int) $item->author === $user_id;
		}

		if ( 'public' === $rule ) {
			return in_array( $action, array( 'read', 'create' ), true );
		}
		if ( ! $user_id ) {
			return false;
		}
		if ( 'logged_in' === $rule ) {
			return true;
		}
		if ( 0 === strpos( $rule, 'role:' ) ) {
			$user = get_userdata( $user_id );
			return $user && in_array( substr( $rule, 5 ), (array) $user->roles, true );
		}
		if ( 'cap' === $rule ) {
			return $cap && user_can( $user_id, $cap );
		}
		return false;
	}

	/** Allowed for every item of the type ("author" = only own items → false). */
	public static function can_all( $type, $action, $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( $user_id && user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		return 'author' !== self::rule( $type, $action ) && self::can( $type, $action, $user_id );
	}
}
