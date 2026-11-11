<?php
/**
 * Type tree (0.29): order and placement of the types — shown in Gruppa CMS → Architecture (0.32; until 0.31
 * the types were items of the WordPress admin menu).
 *
 * Every published entity gets its own item in the main admin menu, in one block between
 * Comments and Gruppa CMS. A Data entity attached to Content entities (data links) appears
 * as a submenu item of each of them (level 2). An entity without a parent is level 1.
 *
 * The tree is edited in Content Types / Data Types (drag and drop, indent / outdent). A manual change is
 * stored in the entity settings and wins over the data links:
 *   settings.menu = { manual: 1, parents: [ slug, … ] }   (no key = automatic, from data links)
 * Order = order_index of the entities (the same order as in Content Types / Data Types).
 * Dashboard → Rebuild menu removes every manual change.
 *
 * Only two levels exist (the WordPress menu has two): an entity that has children is always
 * level 1, and a child is never a parent.
 */

defined( 'ABSPATH' ) || exit;

final class Gruppa_Menu {


	/** @var array|null Resolved tree per status key. */
	private static $cache = array();

	public static function reset() {
		self::$cache = array();
	}

	/* ---------- Parents ---------- */

	/** Content entities a Data entity is attached to (data links, any group). */
	public static function auto_parents( $type ) {
		if ( ! $type || 'data' !== $type->kind ) {
			return array();
		}
		$out = array();
		if ( 'groups' === $type->structure ) {
			foreach ( $type->groups as $g ) {
				$out = array_merge( $out, (array) $g['targets'] );
			}
		} else {
			$out = (array) $type->link['targets'];
		}
		$out = array_values( array_unique( array_filter( array_map( 'strval', $out ) ) ) );
		return array_values( array_diff( $out, array( $type->slug ) ) );
	}

	public static function is_manual( $type ) {
		return ! empty( $type->settings['menu']['manual'] );
	}

	/** Parents as configured: manual list or data links. */
	public static function wanted_parents( $type ) {
		if ( self::is_manual( $type ) ) {
			$p = isset( $type->settings['menu']['parents'] ) ? (array) $type->settings['menu']['parents'] : array();
			return array_values( array_diff( array_unique( array_map( 'sanitize_key', $p ) ), array( $type->slug, '' ) ) );
		}
		return self::auto_parents( $type );
	}

	/**
	 * Resolved tree of a set of entities.
	 *
	 * @param string $status 'publish' (admin menu) or '' (all, Entity Builder).
	 * @return array {
	 *     @type object[] $types    slug => entity, in order.
	 *     @type string[] $roots    level 1 slugs, in order.
	 *     @type array    $children parent slug => child slugs, in order.
	 *     @type array    $parents  child slug => parent slugs.
	 * }
	 */
	public static function resolve( $status = 'publish' ) {
		$key = $status ? $status : 'all';
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}
		$types = array();
		foreach ( Gruppa_Types::all( $status ) as $t ) {
			$types[ $t->slug ] = $t;
		}

		// Wanted parents inside this set.
		$want = array();
		foreach ( $types as $slug => $t ) {
			$want[ $slug ] = array_values( array_filter( self::wanted_parents( $t ), function ( $p ) use ( $types ) {
				return isset( $types[ $p ] );
			} ) );
		}
		// An entity that somebody wants as a parent is level 1.
		$is_parent = array();
		foreach ( $want as $slug => $ps ) {
			foreach ( $ps as $p ) {
				$is_parent[ $p ] = true;
			}
		}
		$roots    = array();
		$children = array();
		$parents  = array();
		foreach ( $types as $slug => $t ) {
			$ps = isset( $is_parent[ $slug ] ) ? array() : $want[ $slug ];
			if ( ! $ps ) {
				$roots[] = $slug;
				continue;
			}
			$parents[ $slug ] = $ps;
			foreach ( $ps as $p ) {
				$children[ $p ][] = $slug;
			}
		}
		// A wanted parent may have ended as a child itself (chain) → its children go to level 1.
		foreach ( $parents as $slug => $ps ) {
			$ok = array_values( array_filter( $ps, function ( $p ) use ( $parents ) {
				return ! isset( $parents[ $p ] );
			} ) );
			if ( count( $ok ) !== count( $ps ) ) {
				foreach ( array_diff( $ps, $ok ) as $p ) {
					$children[ $p ] = array_values( array_diff( isset( $children[ $p ] ) ? $children[ $p ] : array(), array( $slug ) ) );
				}
				if ( $ok ) {
					$parents[ $slug ] = $ok;
				} else {
					unset( $parents[ $slug ] );
					$roots[] = $slug;
				}
			}
		}
		$order = array_flip( array_keys( $types ) );
		usort( $roots, function ( $a, $b ) use ( $order ) {
			return $order[ $a ] - $order[ $b ];
		} );
		$children = array_filter( $children );

		self::$cache[ $key ] = array(
			'types'    => $types,
			'roots'    => $roots,
			'children' => $children,
			'parents'  => $parents,
		);
		return self::$cache[ $key ];
	}

	/**
	 * Tree for output: list of [ entity, child entities[] ].
	 */
	public static function tree( $status = 'publish' ) {
		$r   = self::resolve( $status );
		$out = array();
		foreach ( $r['roots'] as $slug ) {
			$kids = array();
			foreach ( isset( $r['children'][ $slug ] ) ? $r['children'][ $slug ] : array() as $c ) {
				$kids[] = $r['types'][ $c ];
			}
			$out[] = array( $r['types'][ $slug ], $kids );
		}
		return $out;
	}

	/** Parents of a published entity in the admin menu (empty = level 1). */
	public static function parents_of( $slug ) {
		$r = self::resolve( 'publish' );
		return isset( $r['parents'][ $slug ] ) ? $r['parents'][ $slug ] : array();
	}

	/** Valid ?parent= of the current request for an entity, '' when none. */
	public static function request_parent( $slug ) {
		$p = isset( $_GET['parent'] ) ? sanitize_key( wp_unslash( $_GET['parent'] ) ) : ''; // phpcs:ignore
		return $p && in_array( $p, self::parents_of( $slug ), true ) ? $p : '';
	}

	/**
	 * Groups of a Data entity shown under a parent: the groups attached to the parent.
	 *
	 * @return int[]|null Group ids, null = all groups (no groups, or placed there manually).
	 */
	public static function groups_for( $type, $parent ) {
		if ( ! $type || 'data' !== $type->kind || 'groups' !== $type->structure || '' === $parent ) {
			return null;
		}
		$ids = array();
		foreach ( $type->groups as $g ) {
			if ( in_array( $parent, (array) $g['targets'], true ) ) {
				$ids[] = (int) $g['id'];
			}
		}
		return $ids ? $ids : null;
	}

	/**
	 * Item counts of an entity as shown under a parent (only its groups there).
	 *
	 * @return array all, publish, draft
	 */
	public static function counts_under( $type, $parent ) {
		global $wpdb;
		$ids = self::groups_for( $type, $parent );
		$out = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		if ( ! Gruppa_DB::data_table_exists( $type->slug ) ) {
			return $out;
		}
		$where = null === $ids ? '' : ' WHERE group_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
		$rows  = $wpdb->get_results( 'SELECT status, COUNT(*) c FROM `' . Gruppa_DB::data_table( $type->slug ) . '`' . $where . ' GROUP BY status' ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r->status ] ) ) {
				$out[ $r->status ] = (int) $r->c;
			}
			$out['all'] += (int) $r->c;
		}
		return $out;
	}

	/* ---------- Saving ---------- */

	private static function write_menu( $type, $menu ) {
		global $wpdb;
		$settings = is_array( $type->settings ) ? $type->settings : array();
		if ( null === $menu ) {
			if ( ! isset( $settings['menu'] ) ) {
				return;
			}
			unset( $settings['menu'] );
		} else {
			$settings['menu'] = $menu;
		}
		$wpdb->update( Gruppa_DB::types_table(), array( 'settings' => wp_json_encode( $settings ) ), array( 'id' => (int) $type->id ) );
	}

	/**
	 * Save the tree from Content Types or Data Types (rows of that screen).
	 *
	 * @param array $rows Rows in screen order: [ [ 'slug' => …, 'parent' => '' | slug ], … ].
	 *                    A shared entity has one row under each parent.
	 */
	public static function save( $rows ) {
		global $wpdb;
		$before = self::resolve( '' );
		$types  = $before['types'];

		$order   = array();
		$wanted  = array();
		$is_kid  = array();
		foreach ( (array) $rows as $row ) {
			$slug   = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
			$parent = isset( $row['parent'] ) ? sanitize_key( $row['parent'] ) : '';
			if ( ! isset( $types[ $slug ] ) ) {
				continue;
			}
			if ( ! isset( $order[ $slug ] ) ) {
				$order[ $slug ] = count( $order );
				$wanted[ $slug ] = array();
			}
			if ( '' !== $parent && $parent !== $slug && isset( $types[ $parent ] ) ) {
				$wanted[ $slug ][] = $parent;
				$is_kid[ $slug ]   = true;
			}
		}
		// 0.31: one screen (Content Types or Data Types) sends only its own rows. They take the places they had
		// among all types, in the new order; types not on the screen keep their place and their parents.
		$queue = array_keys( $order );
		$on    = array_flip( $queue );
		$final = array();
		foreach ( array_keys( $types ) as $slug ) {
			if ( isset( $on[ $slug ] ) ) {
				$final[] = array_shift( $queue );
			} else {
				$final[]         = $slug;
				$wanted[ $slug ] = isset( $before['parents'][ $slug ] ) ? $before['parents'][ $slug ] : array();
			}
		}
		$order = array_flip( $final );
		// A child cannot be a parent: drop such parents.
		foreach ( $wanted as $slug => $ps ) {
			$wanted[ $slug ] = array_values( array_unique( array_filter( $ps, function ( $p ) use ( $is_kid ) {
				return empty( $is_kid[ $p ] );
			} ) ) );
		}

		$table = Gruppa_DB::types_table();
		foreach ( $types as $slug => $t ) {
			$wpdb->update( $table, array( 'order_index' => (int) $order[ $slug ] ), array( 'id' => (int) $t->id ) );

			$new = $wanted[ $slug ];
			$old = isset( $before['parents'][ $slug ] ) ? $before['parents'][ $slug ] : array();
			sort( $new );
			sort( $old );
			if ( $new === $old ) {
				continue; // Not moved: keep automatic / manual as it was.
			}
			$auto = self::auto_parents( $t );
			sort( $auto );
			self::write_menu( $t, $new === $auto ? null : array( 'manual' => 1, 'parents' => $wanted[ $slug ] ) );
		}
		self::reset();
	}

	/** Dashboard → Rebuild menu: back to the data links for every entity. */
	public static function rebuild() {
		foreach ( Gruppa_Types::all() as $t ) {
			self::write_menu( $t, null );
		}
		self::reset();
	}

	/* ---------- Admin menu ---------- */

	/** Default icon by kind (Content / Data). */
	public static function default_icon( $kind ) {
		return 'content' === $kind ? 'dashicons-database' : 'dashicons-list-view';
	}

	/** Menu icon of the entity: settings.icon (Dashicons) or the default by kind. */
	public static function icon( $type ) {
		$icon = isset( $type->settings['icon'] ) ? (string) $type->settings['icon'] : '';
		return in_array( $icon, self::icons(), true ) ? $icon : self::default_icon( $type->kind );
	}

	/** Dashicons offered in Content Types / Data Types → Menu icon (WordPress core set). */
	public static function icons() {
		$names = 'database list-view admin-post admin-page admin-media admin-links admin-comments admin-users admin-tools admin-settings admin-network admin-home admin-generic admin-site admin-site-alt3 '
			. 'dashboard archive portfolio book book-alt media-document media-text media-spreadsheet media-archive media-code media-interactive media-audio media-video format-image format-gallery format-video format-audio format-chat format-quote format-aside format-status '
			. 'images-alt2 camera camera-alt video-alt3 playlist-audio playlist-video microphone album '
			. 'category tag clipboard edit-page edit text-page feedback forms list-view excerpt-view grid-view layout table-col-after table-row-after '
			. 'id id-alt businessperson businessman businesswoman groups admin-users nametag universal-access welcome-learn-more welcome-write-blog welcome-add-page '
			. 'building store bank cart products money money-alt car airplane location location-alt palmtree hammer superhero superhero-alt '
			. 'calendar calendar-alt clock schedule backup chart-bar chart-pie chart-line chart-area analytics performance '
			. 'email email-alt email-alt2 phone smartphone tablet laptop desktop testimonial megaphone bell sos '
			. 'heart star-filled star-empty flag awards shield shield-alt lock unlock visibility hidden thumbs-up thumbs-down '
			. 'cloud cloud-saved cloud-upload download upload paperclip share share-alt2 rss networking '
			. 'food coffee carrot beer pets buddicons-activity buddicons-groups buddicons-community buddicons-forums '
			. 'tickets tickets-alt games art color-picker lightbulb sticky info-outline warning editor-help '
			. 'translation privacy filter search randomize update plugins-checked html code-standards editor-code database-view database-add';
		$out = array();
		foreach ( preg_split( '/\s+/', trim( $names ) ) as $n ) {
			$out[] = 'dashicons-' . $n;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * 0.32: the types are no longer items of the main WordPress menu. The main menu keeps Gruppa CMS and
	 * Gruppa Tools; Gruppa CMS gets a line and "Architecture" (Gruppa_Admin_Architecture), where every type and
	 * its items are reached. The items pages of all types are registered hidden (under options.php) and
	 * highlight Gruppa CMS → Architecture. Called from Gruppa_Admin::menu().
	 *
	 * @param callable $render Items page renderer.
	 * @param string   $cap    Capability.
	 */
	public static function register( $render, $cap ) {
		foreach ( self::resolve( 'publish' )['types'] as $t ) {
			add_submenu_page( 'options.php', $t->name, esc_html( $t->name ), $cap, Gruppa_Admin_Items::page_slug( $t->slug ), $render );
		}
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ), 10, 2 );
		add_action( 'admin_head', array( __CLASS__, 'real_parent' ) );
	}

	/**
	 * Items pages are registered under options.php (hidden). menu-header.php calls get_admin_page_parent()
	 * after the parent_file filter, which would open Settings — map it to Gruppa CMS (this request only).
	 */
	public static function real_parent() {
		if ( '' !== self::current_slug() ) {
			global $_wp_real_parent_file;
			$_wp_real_parent_file['options.php'] = Gruppa_Admin::PAGE; // phpcs:ignore
		}
	}

	public static function child_url( $slug, $parent ) {
		return 'admin.php?page=' . Gruppa_Admin_Items::page_slug( $slug ) . '&parent=' . $parent;
	}

	/** Current items page → its entity slug, '' elsewhere. */
	private static function current_slug() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		return 0 === strpos( $page, 'gruppa-item-' ) ? substr( $page, strlen( 'gruppa-item-' ) ) : '';
	}

	public static function parent_file( $parent_file ) {
		return '' === self::current_slug() ? $parent_file : Gruppa_Admin::PAGE;
	}

	public static function submenu_file( $submenu_file, $parent_file ) {
		return '' === self::current_slug() ? $submenu_file : Gruppa_Admin_Architecture::PAGE;
	}
}
