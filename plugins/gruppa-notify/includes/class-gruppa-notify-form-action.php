<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Notify — Form Builder action "Send notification" (Gruppa CMS 0.28+).
 *
 * After a form is submitted the chosen users (and every user of the chosen roles)
 * get a push on all their devices; every notification is written to Notify → Log.
 * Recipients, text and link are set by the admin in the form — visitors cannot change them.
 */
class Gruppa_Notify_Form_Action {

	const TYPE = 'notify';

	public static function init() {
		add_filter( 'gruppa_cms/form_action_types', array( __CLASS__, 'types' ) );
		add_filter( 'gruppa_cms/form_action_defaults', array( __CLASS__, 'defaults' ), 10, 2 );
		add_filter( 'gruppa_cms/form_action_sanitize', array( __CLASS__, 'sanitize' ), 10, 3 );
		add_filter( 'gruppa_cms/form_action_summary', array( __CLASS__, 'summary' ), 10, 2 );
		add_action( 'gruppa_cms/form_action_fields', array( __CLASS__, 'fields' ), 10, 2 );
		add_action( 'gruppa_cms/form_action_' . self::TYPE, array( __CLASS__, 'run' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function types( $types ) {
		$types[ self::TYPE ] = 'Send notification';
		return $types;
	}

	public static function defaults( $own, $type ) {
		if ( self::TYPE !== $type ) {
			return $own;
		}
		return array(
			'users'      => array(),
			'roles'      => array(),
			'push_title' => '',
			'push_body'  => '{form}: {title}',
			'open'       => 'item',
			'push_url'   => '',
		);
	}

	public static function sanitize( $s, $raw, $type ) {
		if ( self::TYPE !== $type ) {
			return $s;
		}
		$roles = array_keys( wp_roles()->roles );
		$s['users'] = array_values( array_unique( array_filter( array_map( 'intval', isset( $raw['users'] ) ? (array) $raw['users'] : array() ) ) ) );
		$s['roles'] = array_values( array_intersect( array_map( 'sanitize_key', isset( $raw['roles'] ) ? (array) $raw['roles'] : array() ), $roles ) );
		$s['push_title'] = sanitize_text_field( isset( $raw['push_title'] ) ? $raw['push_title'] : '' );
		$s['push_body']  = sanitize_textarea_field( isset( $raw['push_body'] ) ? $raw['push_body'] : '' );
		$s['open']       = isset( $raw['open'] ) && in_array( $raw['open'], array( 'item', 'permalink', 'custom' ), true ) ? $raw['open'] : 'item';
		$s['push_url']   = sanitize_text_field( isset( $raw['push_url'] ) ? $raw['push_url'] : '' );
		return $s;
	}

	public static function summary( $text, $a ) {
		if ( self::TYPE !== $a['type'] ) {
			return $text;
		}
		$parts = array();
		$users = isset( $a['users'] ) ? (array) $a['users'] : array();
		if ( $users ) {
			$names = array();
			foreach ( array_slice( $users, 0, 3 ) as $id ) {
				$u       = get_userdata( $id );
				$names[] = $u ? $u->display_name : '#' . $id;
			}
			$parts[] = implode( ', ', $names ) . ( count( $users ) > 3 ? ' +' . ( count( $users ) - 3 ) : '' );
		}
		foreach ( isset( $a['roles'] ) ? (array) $a['roles'] : array() as $r ) {
			$parts[] = 'role ' . ( isset( wp_roles()->roles[ $r ] ) ? translate_user_role( wp_roles()->roles[ $r ]['name'] ) : $r );
		}
		return $parts ? '→ ' . implode( ', ', $parts ) : 'no recipients';
	}

	/* ======================================================
	 * Settings in the form editor
	 * ==================================================== */

	public static function fields( $n, $a ) {
		if ( self::TYPE !== $a['type'] || ! class_exists( 'Gruppa_Admin_Form_Actions' ) ) {
			return;
		}
		$counts = Gruppa_Notify_Module::device_counts();
		$chosen = array_map( 'intval', (array) $a['users'] );
		$ids    = array_unique( array_merge( array_keys( $counts ), $chosen ) );
		$users  = $ids ? get_users( array( 'include' => $ids, 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) ) : array();

		Gruppa_Admin_Form_Actions::row(
			'Users',
			function () use ( $n, $users, $counts, $chosen ) {
				if ( ! $users ) {
					echo '<p class="gn-fa-empty">No user has turned on notifications yet. Add the <code>[gruppa_notify_button]</code> shortcode to a page for logged-in users — after they press it, they appear here.</p>';
					return;
				}
				echo '<div class="gn-fa-list">';
				foreach ( $users as $u ) {
					$c = isset( $counts[ $u->ID ] ) ? $counts[ $u->ID ] : 0;
					printf(
						'<label><input type="checkbox" name="%s[users][]" value="%d" %s> %s <span class="gn-fa-count%s">%s</span></label>',
						esc_attr( $n ),
						(int) $u->ID,
						checked( in_array( (int) $u->ID, $chosen, true ), true, false ),
						esc_html( $u->display_name ),
						$c ? '' : ' is-none',
						esc_html( $c ? sprintf( 1 === $c ? '%d device' : '%d devices', $c ) : 'no device' )
					);
				}
				echo '</div>';
			},
			'Users who turned on notifications with the button on the website.'
		);

		Gruppa_Admin_Form_Actions::row(
			'Roles',
			function () use ( $n, $a ) {
				echo '<div class="gn-fa-list gn-fa-roles">';
				foreach ( wp_roles()->roles as $slug => $r ) {
					printf(
						'<label><input type="checkbox" name="%s[roles][]" value="%s" %s> %s</label>',
						esc_attr( $n ),
						esc_attr( $slug ),
						checked( in_array( $slug, (array) $a['roles'], true ), true, false ),
						esc_html( translate_user_role( $r['name'] ) )
					);
				}
				echo '</div>';
			},
			'Every user of these roles — also users added later.'
		);

		Gruppa_Admin_Form_Actions::row(
			'Title',
			function () use ( $n, $a ) {
				Gruppa_Admin_Form_Actions::text( $n . '[push_title]', $a['push_title'], Gruppa_Notify_Module::get( 'title' ) ? Gruppa_Notify_Module::get( 'title' ) : get_bloginfo( 'name' ) );
			},
			'Empty = Title from Notify → Settings.'
		);

		Gruppa_Admin_Form_Actions::row(
			'Message',
			function () use ( $n, $a ) {
				printf( '<textarea name="%s" rows="3">%s</textarea>', esc_attr( $n . '[push_body]' ), esc_textarea( $a['push_body'] ) );
				echo '<p class="description gfa-placeholders">Placeholders: {title}, {id}, {form}, {site_name}, {date}, {user_name} or any field key, e.g. {email}<span class="gfa-ph-fields"></span></p>';
			}
		);

		Gruppa_Admin_Form_Actions::row(
			'On click open',
			function () use ( $n, $a ) {
				echo '<div class="gruppa-inline gn-fa-open">';
				Gruppa_Admin_Form_Actions::select(
					$n . '[open]',
					array(
						'item'      => 'Item in the admin',
						'permalink' => 'Item page on the website',
						'custom'    => 'Custom URL',
					),
					$a['open']
				);
				Gruppa_Admin_Form_Actions::text( $n . '[push_url]', $a['push_url'], 'https://… or {permalink}' );
				echo '</div>';
			},
			'Item in the admin needs a user who can edit items. Item page needs an entity with a single page.'
		);
	}

	public static function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( 'gruppa-forms' !== $page ) {
			return;
		}
		$css = '.gn-fa-list{display:flex;flex-direction:column;gap:6px;max-height:208px;overflow:auto;padding:10px 12px;border:1px solid #dcdcde;border-radius:8px;background:#fff}'
			. '.gn-fa-roles{flex-direction:row;flex-wrap:wrap;gap:6px 18px;max-height:none}'
			. '.gn-fa-list label{display:flex;align-items:center;gap:8px;margin:0}'
			. '.gn-fa-list input[type=checkbox]{margin:0}'
			. '.gn-fa-count{margin-left:auto;color:#646970;font-size:12px}'
			. '.gn-fa-count.is-none{color:#b32d2e}'
			. '.gn-fa-empty{margin:0}'
			. '.gn-fa-open select{flex:0 0 auto}';
		wp_register_style( 'gruppa-notify-form-action', false, array(), GRUPPA_NOTIFY_VERSION );
		wp_enqueue_style( 'gruppa-notify-form-action' );
		wp_add_inline_style( 'gruppa-notify-form-action', $css );
		wp_add_inline_script(
			'jquery-core',
			'jQuery(function($){function t(c){var s=c.find("select[name$=\'[open]\']");c.find("input[name$=\'[push_url]\']").toggle(s.val()==="custom");}'
			. '$(document).on("change","select[name$=\'[open]\']",function(){t($(this).closest(".gfa-card"));});'
			. '$(".gfa-card[data-type=notify]").each(function(){t($(this));});'
			. '$("#gfa-add").on("click",function(){setTimeout(function(){$(".gfa-card[data-type=notify]").each(function(){t($(this));});},0);});});'
		);
	}

	/* ======================================================
	 * Run (after the item is saved)
	 * ==================================================== */

	public static function run( $a, $ctx ) {
		if ( isset( $ctx['mode'] ) && 'delete' === $ctx['mode'] ) {
			return;
		}
		$ids = array_map( 'intval', (array) $a['users'] );
		if ( ! empty( $a['roles'] ) ) {
			$ids = array_merge( $ids, array_map( 'intval', get_users( array( 'role__in' => (array) $a['roles'], 'fields' => 'ID' ) ) ) );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		$ids = array_values( array_filter( $ids, 'get_userdata' ) );
		if ( ! $ids ) {
			return;
		}

		$item_id = ! empty( $ctx['item']['id'] ) ? (int) $ctx['item']['id'] : 0;
		$slug    = isset( $ctx['type']->slug ) ? $ctx['type']->slug : '';

		$url = '';
		if ( 'custom' === $a['open'] ) {
			$url = trim( Gruppa_Form_Actions::replace( $a['push_url'], $ctx ) );
		} elseif ( 'permalink' === $a['open'] ) {
			$url = ! empty( $ctx['item']['permalink'] ) ? $ctx['item']['permalink'] : '';
		} elseif ( $item_id && $slug ) {
			$url = Gruppa_Notify_Module::item_admin_url( $slug, $item_id );
		}

		Gruppa_Notify_Module::notify(
			$ids,
			array(
				'title'    => Gruppa_Form_Actions::replace( $a['push_title'], $ctx ),
				'body'     => Gruppa_Form_Actions::replace( $a['push_body'], $ctx ),
				'url'      => $url,
				'source'   => 'form',
				'label'    => isset( $ctx['form']->name ) ? $ctx['form']->name : '',
				'ref_type' => $slug,
				'ref_id'   => $item_id,
			)
		);
	}
}
