<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-gruppa-admin-items.php';
require_once __DIR__ . '/class-gruppa-admin-forms.php';
require_once __DIR__ . '/class-gruppa-admin-listings.php';
require_once __DIR__ . '/class-gruppa-admin-filters.php';
require_once __DIR__ . '/class-gruppa-admin-dashboard.php';
require_once __DIR__ . '/class-gruppa-admin-architecture.php';

class Gruppa_Admin {

	const CAP  = 'manage_options';
	const PAGE      = 'gruppa-types';      // Content Types (also the parent of the Gruppa CMS menu).
	const DATA_PAGE = 'gruppa-data-types'; // Data Types (0.31): the same editor, kind = data.

	private $items;
	private $forms;
	private $listings;
	private $filters;
	private $dashboard;
	private $architecture;

	public function __construct() {
		$this->items    = new Gruppa_Admin_Items();
		$this->forms    = new Gruppa_Admin_Forms();
		$this->listings = new Gruppa_Admin_Listings();
		$this->filters  = new Gruppa_Admin_Filters();
		$this->dashboard = new Gruppa_Admin_Dashboard();
		$this->architecture = new Gruppa_Admin_Architecture();

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_gruppa_type_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_type_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_gruppa_type_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_gruppa_duplicate', array( __CLASS__, 'handle_duplicate' ) );
		add_action( 'wp_ajax_gruppa_reorder', array( $this, 'ajax_reorder' ) );
		add_action( 'wp_ajax_gruppa_entity_tree', array( $this, 'ajax_entity_tree' ) );
	}

	public function menu() {
		add_menu_page( 'Gruppa CMS', 'Gruppa CMS', self::CAP, self::PAGE, array( $this, 'render' ), 'dashicons-wordpress', 25 );
		// First submenu = where the "Gruppa CMS" menu link leads.
		add_submenu_page( self::PAGE, 'Gruppa CMS', 'Dashboard', self::CAP, Gruppa_Admin_Dashboard::PAGE, array( $this->dashboard, 'render' ) );
		add_submenu_page( self::PAGE, 'Content Types', 'Content Types', self::CAP, self::PAGE, array( $this, 'render' ) );
		add_submenu_page( self::PAGE, 'Data Types', 'Data Types', self::CAP, self::DATA_PAGE, array( $this, 'render' ) );
		add_submenu_page( self::PAGE, 'Listings', 'Listings', self::CAP, Gruppa_Admin_Listings::PAGE, array( $this->listings, 'render' ) );
		add_submenu_page( self::PAGE, 'Filters', 'Filters', self::CAP, Gruppa_Admin_Filters::PAGE, array( $this->filters, 'render' ) );
		add_submenu_page( self::PAGE, 'Forms', 'Forms', self::CAP, Gruppa_Admin_Forms::PAGE, array( $this->forms, 'render' ) );
		// Table Builder, SEO Builder, Import/Export… are Gruppa Tools: separate plugins in the Gruppa Tools menu (0.31).

		// 0.32: a line, then Architecture — all types and their items (no type items in the main menu any more).
		add_submenu_page( self::PAGE, '', '', self::CAP, 'gruppa-sep', '__return_null' );
		add_submenu_page( self::PAGE, 'Architecture', 'Architecture', self::CAP, Gruppa_Admin_Architecture::PAGE, array( $this->architecture, 'render' ) );
		Gruppa_Menu::register( array( $this->items, 'render' ), self::CAP );

		// WordPress adds the parent itself ("Gruppa CMS" → Content Types) as the first submenu item
		// when the first submenu has another slug. Drop that copy so Dashboard is first.
		global $submenu;
		if ( ! empty( $submenu[ self::PAGE ] ) ) {
			$first = reset( $submenu[ self::PAGE ] );
			$key   = key( $submenu[ self::PAGE ] );
			if ( self::PAGE === $first[2] && 'Gruppa CMS' === $first[0] ) {
				unset( $submenu[ self::PAGE ][ $key ] );
			}
		}
	}

	public function assets( $hook ) {
		$url = plugin_dir_url( GRUPPA_CMS_FILE ) . 'admin/assets/';
		wp_enqueue_style( 'gruppa-admin', $url . 'admin.css', array(), GRUPPA_CMS_VERSION );

		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		// Help: ⓘ bubbles + "Help" switch + Tutorial link on Gruppa CMS screens (add-on pages: hub copy, no Tutorial).
		$cms_pages = array( self::PAGE, self::DATA_PAGE, Gruppa_Admin_Dashboard::PAGE, Gruppa_Admin_Architecture::PAGE, Gruppa_Admin_Forms::PAGE, Gruppa_Admin_Listings::PAGE, Gruppa_Admin_Filters::PAGE );
		if ( in_array( $page, $cms_pages, true ) || 0 === strpos( $page, 'gruppa-item-' ) ) {
			wp_enqueue_script( 'gruppa-help', $url . 'help.js', array(), GRUPPA_CMS_VERSION, true );
			wp_localize_script( 'gruppa-help', 'gruppaHelp', array( 'tutorial' => esc_url_raw( apply_filters( 'gruppa_cms/tutorial_url', 'https://gruppa.cloud/gruppa_cms_tutorial.pdf' ) ) ) );
		}

		// 0.32.3: box titles inside the boxes — every Gruppa screen, Gruppa Tools pages included.
		if ( 0 === strpos( $page, 'gruppa-' ) ) {
			wp_enqueue_script( 'gruppa-boxes', $url . 'boxes.js', array(), GRUPPA_CMS_VERSION, true );
		}

		// Every edit screen: warn before leaving with unsaved changes.
		$edit_pages = array( self::PAGE, self::DATA_PAGE, Gruppa_Admin_Forms::PAGE, Gruppa_Admin_Listings::PAGE, Gruppa_Admin_Filters::PAGE );
		if ( isset( $_GET['view'] ) && 'edit' === $_GET['view'] && ( in_array( $page, $edit_pages, true ) || 0 === strpos( $page, 'gruppa-item-' ) ) ) {
			wp_enqueue_script( 'gruppa-dirty', $url . 'dirty.js', array( 'jquery' ), GRUPPA_CMS_VERSION, true );
		}

		if ( Gruppa_Admin_Filters::PAGE === $page ) {
			wp_enqueue_script( 'gruppa-sortable', $url . 'sortable.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
			wp_localize_script( 'gruppa-sortable', 'gruppaSort', array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gruppa_reorder' ),
			) );
			wp_enqueue_script( 'gruppa-filters-builder', $url . 'filters-builder.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
			return;
		}
		if ( Gruppa_Admin_Listings::PAGE === $page ) {
			wp_enqueue_script( 'gruppa-sortable', $url . 'sortable.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
			wp_localize_script( 'gruppa-sortable', 'gruppaSort', array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gruppa_reorder' ),
			) );
			wp_enqueue_script( 'gruppa-listings', $url . 'listings.js', array( 'jquery' ), GRUPPA_CMS_VERSION, true );
			return;
		}
		$types_page = self::PAGE === $page || self::DATA_PAGE === $page;
		if ( ! $types_page && Gruppa_Admin_Forms::PAGE !== $page && 0 !== strpos( $page, 'gruppa-item-' ) ) {
			return;
		}

		wp_enqueue_script( 'gruppa-sortable', $url . 'sortable.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
		wp_localize_script( 'gruppa-sortable', 'gruppaSort', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'gruppa_reorder' ),
		) );

		if ( Gruppa_Admin_Forms::PAGE === $page && isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) {
			wp_enqueue_script( 'gruppa-forms-builder', $url . 'forms-builder.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
			wp_enqueue_script( 'gruppa-form-actions', $url . 'form-actions.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
		}

		if ( $types_page && isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) {
			wp_enqueue_script( 'gruppa-types', $url . 'types.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
		}

		// Content Types / Data Types list = the tree shown in Architecture (drag and drop, indent / outdent).
		if ( $types_page && ( ! isset( $_GET['view'] ) || 'edit' !== $_GET['view'] ) ) {
			wp_enqueue_script( 'gruppa-entity-tree', $url . 'entity-tree.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
			wp_localize_script( 'gruppa-entity-tree', 'gruppaTree', array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'gruppa_entity_tree' ),
				'i18n'  => array(
					'error'    => 'The tree could not be saved. Reload the page and try again.',
					'hasKids'  => 'This type has types below it. Move them first.',
					'noParent' => 'There is no level 1 type above to put it under.',
				),
			) );
		}

		if ( 0 === strpos( $page, 'gruppa-item-' ) && isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) {
			wp_enqueue_media();
			wp_enqueue_editor(); // 0.33: Editor columns of Repeater rows (wp.editor).
			wp_enqueue_script( 'gruppa-items', $url . 'items.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_CMS_VERSION, true );
		}
	}

	/** Admin page of a kind: Content Types or Data Types. */
	public static function page_of( $kind ) {
		return 'data' === $kind ? self::DATA_PAGE : self::PAGE;
	}

	/** Kind of the current screen / request (Data Types page, or the posted kind). */
	private function cur_kind() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : ''; // phpcs:ignore
		if ( self::DATA_PAGE === $page ) {
			return 'data';
		}
		if ( '' === $page && isset( $_POST['kind'] ) && 'data' === sanitize_key( wp_unslash( $_POST['kind'] ) ) ) { // phpcs:ignore
			return 'data';
		}
		return 'content';
	}

	private function url( $args = array(), $kind = '' ) {
		return add_query_arg( array_merge( array( 'page' => self::page_of( $kind ? $kind : $this->cur_kind() ) ), $args ), admin_url( 'admin.php' ) );
	}

	private function action_url( $action, $id ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => $action, 'id' => (int) $id ), admin_url( 'admin-post.php' ) ),
			$action . '_' . (int) $id
		);
	}

	/* ---------- Handlers ---------- */

	public function ajax_reorder() {
		check_ajax_referer( 'gruppa_reorder', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

		if ( 'types' !== $key && '_forms' !== $key && '_listings' !== $key && '_filters' !== $key && ! Gruppa_Types::get_by_slug( $key ) ) {
			wp_send_json_error( null, 400 );
		}
		Gruppa_DB::reorder( $key, $ids, isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0 );
		wp_send_json_success();
	}

	/** Content Types / Data Types: save the tree (order + parents) of the rows on that screen. */
	public function ajax_entity_tree() {
		check_ajax_referer( 'gruppa_entity_tree', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$rows = array();
		foreach ( isset( $_POST['rows'] ) ? (array) wp_unslash( $_POST['rows'] ) : array() as $r ) { // phpcs:ignore
			if ( is_array( $r ) ) {
				$rows[] = array(
					'slug'   => isset( $r['slug'] ) ? sanitize_key( $r['slug'] ) : '',
					'parent' => isset( $r['parent'] ) ? sanitize_key( $r['parent'] ) : '',
				);
			}
		}
		if ( ! $rows ) {
			wp_send_json_error( null, 400 );
		}
		Gruppa_Menu::save( $rows );
		wp_send_json_success();
	}

	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_type_save' );

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$res = Gruppa_Types::save( wp_unslash( $_POST ), $id );

		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( $this->url( array( 'view' => 'edit', 'id' => $id, 'msg' => $res->get_error_code() ) ) );
			exit;
		}
		$args = array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' );
		if ( Gruppa_Types::$skipped ) {
			$args['msg']     = 'reserved';
			$args['skipped'] = implode( ',', Gruppa_Types::$skipped );
		}

		// New published type → straight to its items (the new submenu page).
		$saved = Gruppa_Types::get( $res );
		$kind  = $saved ? $saved->kind : '';
		if ( ! $id && $saved && 'publish' === $saved->status ) {
			$to = array( 'page' => Gruppa_Admin_Items::page_slug( $saved->slug ), 'msg' => 'created' );
			if ( isset( $args['skipped'] ) ) {
				$to['msg']     = 'reserved';
				$to['skipped'] = $args['skipped'];
			}
			wp_safe_redirect( add_query_arg( $to, admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( $this->url( $args, $kind ) );
		exit;
	}

	public function handle_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_type_status_' . $id );

		$type = Gruppa_Types::get( $id );
		if ( $type ) {
			Gruppa_Types::set_status( $id, 'publish' === $type->status ? 'draft' : 'publish' );
		}
		wp_safe_redirect( $this->url( array( 'msg' => 'status' ), $type ? $type->kind : '' ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_type_delete_' . $id );

		$type = Gruppa_Types::get( $id );
		$res  = Gruppa_Types::delete( $id );
		wp_safe_redirect( $this->url( array( 'msg' => is_wp_error( $res ) ? $res->get_error_code() : 'deleted' ), $type ? $type->kind : '' ) );
		exit;
	}

	/* ---------- Views ---------- */

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';
		echo '<div class="wrap gruppa-wrap">';
		self::notice();
		if ( 'edit' === $view ) {
			$this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	public static function notice() {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : '';
		$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0;
		$map = array(
			'saved'       => array( 'success', 'Saved.' ),
			'created'     => array( 'success', 'Type created. You can add items now.' ),
			'status'      => array( 'success', 'Status changed.' ),
			'deleted'     => array( 'success', 'Deleted.' ),
			'has_items'   => array( 'error', 'Type has items. Delete all items first.' ),
			'name'        => array( 'error', 'Name is required.' ),
			'slug'        => array( 'error', 'Invalid slug.' ),
			'slug_exists' => array( 'error', 'Slug already exists.' ),
			'slug_locked' => array( 'error', 'The slug can be changed only while the type has no items.' ),
			'slug_reserved' => array( 'error', 'This slug is used by the system (' . implode( ', ', Gruppa_DB::RESERVED_SLUGS ) . '). Choose another one.' ),
			'missing'     => array( 'error', 'Not found.' ),
			'db'          => array( 'error', 'Database error.' ),
			'post_type'   => array( 'error', 'Select post type.' ),
			'kind'        => array( 'error', 'Select type: Content or Data.' ),
			'form_type'   => array( 'error', 'Select type.' ),
			'group_has_values' => array( 'error', 'A group you removed still has values (or values have a group). Move or delete those values first.' ),
			'groups_empty'     => array( 'error', 'Structure "Grouped records" needs at least one group.' ),
			'reserved'    => array( 'warning', 'Saved. Skipped keys: ' . ( isset( $_GET['skipped'] ) ? sanitize_text_field( wp_unslash( $_GET['skipped'] ) ) : '' ) ),
			'duplicated'  => array( 'success', 'Copy created as a Draft — you are editing the copy now.' ),
			'bulk_empty'  => array( 'warning', 'Select at least one item.' ),
			'bulk_none'   => array( 'warning', 'Choose a bulk action.' ),
			'bulk_publish'   => array( 'success', $n . ' published.' ),
			'bulk_draft'     => array( 'success', $n . ' moved to Draft.' ),
			'bulk_duplicate' => array( 'success', $n . ' copied as Draft.' ),
			'bulk_delete'    => array( 'success', $n . ' deleted.' ),
			'bulk_group'     => array( 'success', $n . ' moved to the group.' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
	}

	/* ---------- Duplicate (types, forms, listings, filter groups) ---------- */

	public static function duplicate_url( $kind, $id ) {
		return wp_nonce_url(
			add_query_arg( array( 'action' => 'gruppa_duplicate', 'kind' => $kind, 'id' => (int) $id ), admin_url( 'admin-post.php' ) ),
			'gruppa_duplicate_' . $kind . '_' . (int) $id
		);
	}

	private static function edit_url( $kind, $args = array() ) {
		$pages = array(
			'type'    => self::PAGE,
			'form'    => Gruppa_Admin_Forms::PAGE,
			'listing' => Gruppa_Admin_Listings::PAGE,
			'filters' => Gruppa_Admin_Filters::PAGE,
		);
		return add_query_arg( array_merge( array( 'page' => $pages[ $kind ] ), $args ), admin_url( 'admin.php' ) );
	}

	public static function handle_duplicate() {
		$kind = isset( $_GET['kind'] ) ? sanitize_key( $_GET['kind'] ) : '';
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) || ! in_array( $kind, Gruppa_Duplicate::KINDS, true ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_duplicate_' . $kind . '_' . $id );
		$new = Gruppa_Duplicate::run( $kind, $id );
		if ( is_wp_error( $new ) ) {
			wp_safe_redirect( self::edit_url( $kind, array( 'msg' => $new->get_error_code() ) ) );
			exit;
		}
		$args = array( 'view' => 'edit', 'id' => (int) $new, 'msg' => 'duplicated' );
		if ( 'type' === $kind ) {
			$copy = Gruppa_Types::get( (int) $new );
			wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::page_of( $copy ? $copy->kind : '' ) ), $args ), admin_url( 'admin.php' ) ) );
			exit;
		}
		wp_safe_redirect( self::edit_url( $kind, $args ) );
		exit;
	}

	/** Missing record: notice + way back instead of a bare "Not found." */
	public static function not_found( $back_url, $what = 'This item' ) {
		printf(
			'<h1 class="wp-heading-inline">Not found</h1><hr class="wp-header-end"><div class="notice notice-warning"><p>%s does not exist — it may have been deleted.</p></div><p><a href="%s" class="button">Back to the list</a></p>',
			esc_html( $what ), esc_url( $back_url )
		);
	}

	public static function status_filter( $base_url, $current, $counts ) {
		$links = array(
			''        => 'All',
			'publish' => 'Published',
			'draft'   => 'Draft',
		);
		echo '<nav class="gruppa-tabs">';
		foreach ( $links as $k => $label ) {
			$url = $k ? add_query_arg( 'status', $k, $base_url ) : $base_url;
			$n   = $counts[ $k ? $k : 'all' ];
			printf(
				'<a href="%s" class="%s">%s <span class="gruppa-tab-count">%d</span></a>',
				esc_url( $url ), $current === $k ? 'is-active' : '', esc_html( $label ), (int) $n
			);
		}
		echo '</nav>';
	}

	private function render_list() {
		$kind   = $this->cur_kind();
		$is_cnt = 'content' === $kind;
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : '';
		$tree   = '' === $status;
		$r      = Gruppa_Menu::resolve( '' );

		// Counts of this kind only.
		$counts = array( 'all' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( $r['types'] as $t ) {
			if ( $t->kind === $kind ) {
				++$counts['all'];
				++$counts[ 'publish' === $t->status ? 'publish' : 'draft' ];
			}
		}

		// Rows: [ type, parent slug, children count ]. Tree = level 1 types of this kind with the types under them.
		$rows     = array();
		$attached = array(); // Data Types: data types placed under a content type (menu level 2).
		if ( $tree ) {
			foreach ( Gruppa_Menu::tree( '' ) as $node ) {
				if ( $node[0]->kind !== $kind ) {
					continue;
				}
				$rows[] = array( $node[0], '', count( $node[1] ) );
				foreach ( $node[1] as $kid ) {
					$rows[] = array( $kid, $node[0]->slug, 0 );
				}
			}
			if ( ! $is_cnt ) {
				foreach ( $r['types'] as $slug => $t ) {
					if ( 'data' === $t->kind && ! empty( $r['parents'][ $slug ] ) ) {
						$attached[] = $t;
					}
				}
			}
		} else {
			foreach ( Gruppa_Types::all( $status ) as $t ) {
				if ( $t->kind === $kind ) {
					$rows[] = array( $t, '', 0 );
				}
			}
		}
		$title = $is_cnt ? 'Content Types' : 'Data Types';
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
		<a href="<?php echo esc_url( $this->url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action"><?php echo $is_cnt ? 'Add Content Type' : 'Add Data Type'; ?></a>
		<hr class="wp-header-end">

		<?php self::status_filter( $this->url(), $status, $counts ); ?>

		<?php if ( $tree && $rows ) : ?>
			<?php if ( $is_cnt ) : ?>
				<p class="gruppa-muted gruppa-tree-intro gruppa-help-text">The tree is the structure in Architecture. Drag ☰ to change the order; → puts a type under the type above it, ← moves it back to level 1. Data types attached to a content type are placed under it automatically — a manual change stays until <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Dashboard::PAGE . '#gruppa-structure' ) ); ?>">Rebuild menu</a>.</p>
			<?php else : ?>
				<p class="gruppa-muted gruppa-tree-intro gruppa-help-text">Data types without a content type are on level 1 of the structure in Architecture — drag ☰ to change their order. Data types attached to a content type sit under it (order and placement in Content Types).</p>
			<?php endif; ?>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed gruppa-table gruppa-tree-table">
			<thead>
				<tr>
					<th class="gruppa-col-handle"></th>
					<th class="gruppa-col-id">ID</th>
					<th class="gruppa-col-name">Name</th>
					<th class="gruppa-col-kind"><?php echo $is_cnt ? 'Post type' : 'Records'; ?></th>
					<th>Menu</th>
					<th>Slug</th>
					<th class="gruppa-col-status">Status</th>
					<th class="gruppa-col-actions">Actions</th>
				</tr>
			</thead>
			<tbody class="<?php echo $tree ? 'gruppa-entity-tree' : ''; ?>">
			<?php if ( ! $rows && ! $attached ) : ?>
				<tr><td colspan="8"><?php echo $is_cnt ? 'No content types found.' : 'No data types found.'; ?></td></tr>
			<?php endif; ?>
			<?php
			foreach ( $rows as $row ) {
				$this->list_row( $row[0], $row[1], $row[2], $tree, $is_cnt, $r );
			}
			?>
			</tbody>
			<?php if ( $attached ) : ?>
				<tbody class="gruppa-tree-attached">
				<?php
				foreach ( $attached as $t ) {
					$this->list_row( $t, '', 0, false, false, $r, true );
				}
				?>
				</tbody>
			<?php endif; ?>
		</table>
		<?php
	}

	/** Label of the post type / records structure of a type (list column). */
	private static function kind_label( $t ) {
		if ( 'data' === $t->kind ) {
			return 'groups' === $t->structure ? 'Grouped' : 'Single';
		}
		if ( $t->custom_pt ) {
			return 'Custom Type'; // The post type name is in Edit (side box CPT).
		}
		$labels = array( 'page' => 'Page Type', 'post' => 'Post Type' );
		return isset( $labels[ $t->post_type ] ) ? $labels[ $t->post_type ] : $t->post_type;
	}

	/**
	 * One row of the Content Types / Data Types list.
	 *
	 * @param bool $moves   Tree on Content Types: show ← → buttons.
	 * @param bool $placed  Data Types: data type placed under content types (no drag; menu = the parents).
	 */
	private function list_row( $t, $parent, $nkids, $tree, $moves, $r, $placed = false ) {
		$child  = '' !== $parent;
		$count  = $child ? Gruppa_Menu::counts_under( $t, $parent )['all'] : Gruppa_DB::count_items( $t->slug );
		$total  = $child ? Gruppa_DB::count_items( $t->slug ) : $count; // Delete needs an empty table.
		$edit   = $this->url( array( 'view' => 'edit', 'id' => $t->id ), $t->kind );
		$items  = admin_url( $child ? Gruppa_Menu::child_url( $t->slug, $parent ) : 'admin.php?page=' . Gruppa_Admin_Items::page_slug( $t->slug ) );
		$all_ps = isset( $r['parents'][ $t->slug ] ) ? $r['parents'][ $t->slug ] : array();
		$class  = ( $child ? 'is-child' : 'is-root' ) . ( $nkids ? ' has-kids' : '' ) . ( 'publish' === $t->status ? '' : ' is-draft' );
		?>
		<tr <?php echo $placed ? '' : 'data-id="' . (int) $t->id . '" data-slug="' . esc_attr( $t->slug ) . '" data-parent="' . esc_attr( $parent ) . '"'; ?> class="<?php echo esc_attr( $class ); ?>">
			<td class="gruppa-col-handle"><?php if ( $tree ) : ?><span class="dashicons dashicons-menu gruppa-handle" title="Drag"></span><?php endif; ?></td>
			<td><?php echo (int) $t->id; ?></td>
			<td class="gruppa-tree-name">
				<span class="dashicons <?php echo esc_attr( Gruppa_Menu::icon( $t ) ); ?> gruppa-tree-icon" aria-hidden="true"></span>
				<strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $t->name ); ?></a></strong>
				<?php if ( 'publish' === $t->status ) : ?>
					<a class="gruppa-count" href="<?php echo esc_url( $items ); ?>"><?php echo (int) $count; ?></a>
				<?php else : ?>
					<span class="gruppa-count"><?php echo (int) $count; ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $child && 'data' === $t->kind ? 'Data' : self::kind_label( $t ) ); ?></td>
			<td class="gruppa-tree-menu">
				<?php
				if ( $placed ) {
					$names = array();
					foreach ( $all_ps as $p ) {
						$names[] = isset( $r['types'][ $p ] ) ? $r['types'][ $p ]->name : $p;
					}
					echo '<span class="gruppa-muted">Under</span> ' . esc_html( implode( ', ', $names ) );
				} elseif ( ! $tree ) {
					echo ''; // Nothing to say: empty cell, one line per row.
				} elseif ( $child ) {
					$others = array_diff( $all_ps, array( $parent ) );
					echo '<span class="gruppa-badge">' . ( Gruppa_Menu::is_manual( $t ) ? 'Manual' : 'Data link' ) . '</span>';
					if ( $others ) {
						$names = array();
						foreach ( $others as $o ) {
							$names[] = isset( $r['types'][ $o ] ) ? $r['types'][ $o ]->name : $o;
						}
						echo ' <span class="gruppa-badge is-shared" title="' . esc_attr( 'Also under: ' . implode( ', ', $names ) ) . '">⇄ ' . esc_html( implode( ', ', $names ) ) . '</span>';
					}
				} elseif ( Gruppa_Menu::is_manual( $t ) ) {
					echo '<span class="gruppa-badge">Manual</span>';
				}
				?>
			</td>
			<td><?php echo esc_html( $t->slug ); ?></td>
			<td><?php echo 'publish' === $t->status ? 'Published' : 'Draft'; ?></td>
			<td class="gruppa-col-actions">
				<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
				<a href="<?php echo esc_url( self::duplicate_url( 'type', $t->id ) ); ?>">Duplicate</a> |
				<a href="<?php echo esc_url( $this->action_url( 'gruppa_type_status', $t->id ) ); ?>"><?php echo 'publish' === $t->status ? 'Draft' : 'Publish'; ?></a> |
				<?php if ( $total > 0 ) : ?>
					<span class="gruppa-disabled">Delete</span>
				<?php else : ?>
					<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_type_delete', $t->id ) ); ?>">Delete</a>
				<?php endif; ?>
				<?php if ( $tree && $moves ) : // After the links, so Edit starts under the column title. ?>
					<span class="gruppa-tree-moves">
						<button type="button" class="button-link gruppa-tree-out" title="Move to level 1" aria-label="Move <?php echo esc_attr( $t->name ); ?> to level 1">←</button>
						<button type="button" class="button-link gruppa-tree-in" title="Put under the type above" aria-label="Put <?php echo esc_attr( $t->name ); ?> under the type above">→</button>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_edit( $id ) {
		$type = $id ? Gruppa_Types::get( $id ) : null;
		if ( $id && ! $type ) {
			self::not_found( $this->url(), 'This type' );
			return;
		}
		$kind = $type ? $type->kind : $this->cur_kind(); // 0.31: the screen decides — no Content / Data selector.
		$is_new     = ! $type;
		$fields     = $type ? $type->fields : array();
		// Content items live in Pages, Posts or an own post type. Builder post types (Elementor templates,
		// JetEngine listings, JetForms…) would only get empty, hidden posts.
		$post_types = array();
		foreach ( array( 'page', 'post' ) as $pt_name ) {
			$pt_obj = get_post_type_object( $pt_name );
			if ( $pt_obj ) {
				$post_types[ $pt_name ] = $pt_obj;
			}
		}
		$post_types = apply_filters( 'gruppa_cms/single_post_types', $post_types );
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( ( $is_new ? 'Add ' : 'Edit ' ) . ( 'data' === $kind ? 'Data Type' : 'Content Type' ) ); ?><?php if ( ! $is_new ) : ?> <span class="gruppa-muted gruppa-h1-sub"><?php echo esc_html( $type->name ); ?></span><?php endif; ?></h1>
		<a href="<?php echo esc_url( $this->url( array(), $kind ) ); ?>" class="page-title-action">Back</a>
		<?php if ( $type && 'publish' === $type->status ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Items::page_slug( $type->slug ) ) ); ?>" class="page-title-action">Items</a>
		<?php endif; ?>
		<a href="<?php echo esc_url( $this->url( array( 'view' => 'edit' ), $kind ) ); ?>" class="page-title-action"><?php echo 'data' === $kind ? 'Add Data Type' : 'Add Content Type'; ?></a>
		<?php if ( $type ) : ?>
			<a href="<?php echo esc_url( self::duplicate_url( 'type', $type->id ) ); ?>" class="page-title-action">Duplicate</a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gruppa-type-form">
			<input type="hidden" name="action" value="gruppa_type_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<input type="hidden" name="kind" id="g-kind" value="<?php echo esc_attr( $kind ); ?>">
			<?php wp_nonce_field( 'gruppa_type_save' ); ?>

			<?php
			global $wpdb;
			$status   = $type ? $type->status : 'publish';
			$author   = $type ? get_userdata( $type->author ) : wp_get_current_user();
			$table    = Gruppa_DB::data_table( $type ? $type->slug : '' );
			$access   = $type ? Gruppa_Access::get( $type ) : Gruppa_Access::defaults();
			$date_fmt = 'Y/m/d H:i';
			?>
			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-edit-layout gruppa-type-layout">
			<div class="gruppa-edit-main">
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="g-name">Name <span class="gruppa-req">*</span></label></th>
						<td><input type="text" id="g-name" name="name" value="<?php echo esc_attr( $type ? $type->name : '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="g-slug">Slug <span class="gruppa-req">*</span></label></th>
						<td>
							<?php if ( $is_new ) : ?>
								<input type="text" id="g-slug" name="slug" pattern="[a-z0-9_]+" maxlength="40" required>
							<?php elseif ( 0 === Gruppa_DB::count_items( $type->slug ) ) : ?>
								<input type="text" id="g-slug" name="slug" value="<?php echo esc_attr( $type->slug ); ?>" pattern="[a-z0-9_]+" maxlength="40" required <?php echo preg_match( '/_copy\d*$/', $type->slug ) ? 'data-auto="1"' : 'data-touched="1"'; ?>>
								<p class="description">Can be changed while the entity has no items — its table and every builder that uses it follow the new slug.</p>
							<?php else : ?>
								<input type="text" id="g-slug" value="<?php echo esc_attr( $type->slug ); ?>" readonly>
								<p class="description">Fixed: the entity has items.</p>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( 'content' === $kind ) : ?>
					<tr id="g-post-type-row">
						<th><label for="g-post-type">Post type <span class="gruppa-req">*</span></label></th>
						<td>
							<select name="post_type" id="g-post-type">
								<option value="">— select —</option>
								<?php foreach ( $post_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $type && ! $type->custom_pt ? $type->post_type : '', $pt->name ); ?>><?php echo esc_html( 'page' === $pt->name ? 'Page Types' : ( 'post' === $pt->name ? 'Post Types' : $pt->labels->singular_name . ' (' . $pt->name . ')' ) ); ?></option>
								<?php endforeach; ?>
								<option value="<?php echo esc_attr( Gruppa_Types::CUSTOM ); ?>" <?php selected( $type && $type->custom_pt ); ?>>Custom Types</option>
							</select>
							<p class="description">Page Types = items are WordPress pages, Post Types = posts, Custom Types = an own post type for this type (its own single template in Elementor Theme Builder).</p>
						</td>
					</tr>
					<?php else : ?>
						<?php $this->data_rows( $type ); ?>
					<?php endif; ?>
					<tr>
						<th><label for="g-rest">REST API</label></th>
						<td>
							<select name="access[rest]" id="g-rest">
								<option value="0" <?php selected( ! $access['rest'] ); ?>>False</option>
								<option value="1" <?php selected( $access['rest'] ); ?>>True</option>
							</select>
						</td>
					</tr>
					<?php foreach ( array( 'read' => 'Read GET', 'create' => 'Create POST', 'update' => 'Update PUT/PATCH', 'delete' => 'Delete DELETE' ) as $act => $label ) : ?>
						<tr class="g-rest-row">
							<th><label for="g-acc-<?php echo esc_attr( $act ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<div class="gruppa-inline">
									<select name="access[<?php echo esc_attr( $act ); ?>]" id="g-acc-<?php echo esc_attr( $act ); ?>" class="g-acc">
										<?php foreach ( Gruppa_Access::options( $act ) as $k => $l ) : ?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $access[ $act ], $k ); ?>><?php echo esc_html( $l ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" class="g-acc-cap" name="access[<?php echo esc_attr( $act ); ?>_cap]" value="<?php echo esc_attr( $access[ $act . '_cap' ] ); ?>" placeholder="capability" <?php echo 'cap' === $access[ $act ] ? '' : 'style="display:none"'; ?>>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php
					$icon_cur = $type && ! empty( $type->settings['icon'] ) ? Gruppa_Menu::icon( $type ) : '';
					$icon_def = Gruppa_Menu::default_icon( $kind ? $kind : 'content' );
					?>
					<tr>
						<th><label for="g-icon-btn">Menu icon</label></th>
						<td>
							<div class="gruppa-icon-picker" data-default-content="<?php echo esc_attr( Gruppa_Menu::default_icon( 'content' ) ); ?>" data-default-data="<?php echo esc_attr( Gruppa_Menu::default_icon( 'data' ) ); ?>">
								<input type="hidden" name="icon" id="g-icon" value="<?php echo esc_attr( $icon_cur ); ?>">
								<button type="button" class="button gruppa-icon-btn" id="g-icon-btn" aria-expanded="false"><span class="dashicons <?php echo esc_attr( $icon_cur ? $icon_cur : $icon_def ); ?>" aria-hidden="true"></span><span class="gruppa-icon-label"><?php echo $icon_cur ? esc_html( substr( $icon_cur, 10 ) ) : 'Default'; ?></span></button>
								<button type="button" class="button-link gruppa-icon-reset" <?php echo $icon_cur ? '' : 'hidden'; ?>>Use default</button>
								<div class="gruppa-icon-panel" hidden>
									<input type="search" class="gruppa-icon-search" placeholder="Search icons" aria-label="Search icons">
									<div class="gruppa-icon-grid" role="listbox" aria-label="Menu icon">
										<?php foreach ( Gruppa_Menu::icons() as $ic ) : ?>
											<button type="button" class="gruppa-icon-opt<?php echo $ic === ( $icon_cur ? $icon_cur : '' ) ? ' is-current' : ''; ?>" data-icon="<?php echo esc_attr( $ic ); ?>" title="<?php echo esc_attr( substr( $ic, 10 ) ); ?>" role="option"><span class="dashicons <?php echo esc_attr( $ic ); ?>" aria-hidden="true"></span></button>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</td>
					</tr>
					<tr class="gruppa-meta gruppa-meta-first g-rest-row">
						<th>Endpoint</th>
						<td><span id="g-endpoint" data-base="<?php echo esc_attr( rest_url( Gruppa_REST::NS . '/' ) ); ?>"><?php echo esc_html( rest_url( Gruppa_REST::NS . '/' . ( $type ? $type->slug : '' ) ) ); ?></span></td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Meta Fields</h2>
			<div id="gruppa-fields">
				<?php foreach ( $fields as $i => $f ) {
					$this->field_row( $i, $f, true );
				} ?>
			</div>
			<div class="gfa-add gruppa-add-field-row">
				<select id="gruppa-add-field-type" aria-label="Field type">
					<?php foreach ( Gruppa_Fields::types() as $k => $l ) : ?>
						<?php if ( 'relation' !== $k ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="gruppa-add-field">Add Field</button>
			</div>
			</div>

			<aside class="gruppa-edit-side">
				<div class="gruppa-card gruppa-side-box">
					<p class="gruppa-side-row">
						<label for="g-status" class="screen-reader-text">Status</label>
						<select name="status" id="g-status">
							<option value="publish" <?php selected( $status, 'publish' ); ?>>Published</option>
							<option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option>
						</select>
					</p>
					<dl class="gruppa-side-meta">
						<?php if ( $type ) : ?>
							<dt>ID</dt><dd><?php echo (int) $type->id; ?></dd>
						<?php endif; ?>
						<dt>Author</dt><dd><?php echo esc_html( $author ? $author->display_name : '—' ); ?></dd>
						<?php if ( $type ) : ?>
							<dt>Created</dt><dd><?php echo esc_html( $type->created ? mysql2date( $date_fmt, $type->created ) : '—' ); ?></dd>
							<dt>Modified</dt><dd><?php echo esc_html( $type->modified ? mysql2date( $date_fmt, $type->modified ) : '—' ); ?></dd>
						<?php endif; ?>
						<dt>DB Table</dt><dd><span id="g-table" data-prefix="<?php echo esc_attr( Gruppa_DB::data_table( '' ) ); ?>"><?php echo esc_html( $table ); ?></span></dd>
						<?php if ( $type && $type->custom_pt ) : ?>
							<dt>CPT</dt><dd><?php echo esc_html( $type->post_type ); ?></dd>
						<?php endif; ?>
					</dl>
					<button type="submit" class="button button-primary gruppa-side-save"><?php echo $is_new ? 'Publish' : 'Update'; ?></button>
					<?php if ( $type ) : ?>
						<p class="gruppa-side-links">
							<?php if ( 'publish' === $type->status ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Items::page_slug( $type->slug ) ) ); ?>">Items</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( self::duplicate_url( 'type', $type->id ) ); ?>">Duplicate</a>
							<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_type_delete', $type->id ) ); ?>" onclick="return confirm('Delete this type with its table and all items?')">Delete</a>
						</p>
					<?php endif; ?>
				</div>
			</aside>
			</div>
		</form>

		<script type="text/html" id="tmpl-gruppa-field"><?php $this->field_row( '__i__', array(), false, true ); ?></script>
		<script type="text/html" id="tmpl-gruppa-option"><?php $this->option_row( '__i__', '__j__', array() ); ?></script>
		<script type="text/html" id="tmpl-gruppa-sub"><?php $this->sub_row( '__i__', '__j__', array() ); ?></script>
		<script type="text/html" id="tmpl-gruppa-cond"><?php $this->condition_row( '__i__', '__j__', array() ); ?></script>
		<script type="text/html" id="tmpl-gruppa-group"><?php $this->group_row( '__g__', array(), Gruppa_Relations::target_options( $type ? $type->slug : '' ), 0 ); ?></script>
		<script>window.gruppaFieldIndex = <?php echo (int) count( $fields ); ?>;window.gruppaReserved = <?php echo wp_json_encode( Gruppa_Types::RESERVED ); ?>;</script>
		<?php
	}

	/** Type form rows of a Data type: structure, attachment, groups. */
	private function data_rows( $type ) {
		$structure = $type ? $type->structure : 'values';
		$link      = $type ? $type->link : Gruppa_Relations::link_of( array() );
		$groups    = $type ? $type->groups : array();
		$targets   = Gruppa_Relations::target_options( $type ? $type->slug : '' );
		?>
		<tr class="g-data-row">
			<th><label for="g-structure">Records</label></th>
			<td>
				<select name="structure" id="g-structure">
					<?php foreach ( array_reverse( Gruppa_Relations::structures(), true ) as $k => $l ) : // Grouped first, then Single. ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $structure, $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Single records = one list without groups, each record with the fields of this type — simple categories (Kategórie článkov) or full records (Klienti: name, e-mail, logo). Grouped records = records split into groups (Status, Sortiment, Typ obsahu) — each group can be attached to its own content types.</p>
			</td>
		</tr>
		<tr class="g-data-row g-values-row">
			<th>Attach to</th>
			<td>
				<?php echo self::targets_html( 'link[targets][]', $targets, $link['targets'] ); // phpcs:ignore ?>
				<p class="description">Items of these entities get a box with the values to tick. Nothing selected = plain data structure.</p>
			</td>
		</tr>
		<tr class="g-data-row g-values-row">
			<th>Items list</th>
			<td><label><input type="checkbox" name="link[list]" value="1" <?php checked( $link['list'] ); ?>> Show the picked values as a column (with a filter) in the items list of these content types</label></td>
		</tr>
		<tr class="g-data-row g-values-row">
			<th><label for="g-choice">Choice</label></th>
			<td>
				<select name="link[choice]" id="g-choice">
					<?php foreach ( Gruppa_Relations::choices() as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $link['choice'], $k ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr class="g-data-row g-groups-row">
			<th>Groups</th>
			<td>
				<div id="g-groups">
					<?php foreach ( $groups as $gi => $g ) {
						$this->group_row( $gi, $g, $targets, Gruppa_Relations::count_in_group( $type->slug, $g['id'] ) );
					} ?>
				</div>
				<p><button type="button" class="button" id="g-add-group">Add Group</button></p>
				<p class="description">Drag to reorder. A group is only a way to sort values — every value has the fields of this type. The slug cannot be changed after the first save; a group with values cannot be removed.</p>
			</td>
		</tr>
		<script>window.gruppaGroupIndex = <?php echo (int) count( $groups ); ?>;</script>
		<?php
	}

	private static function targets_html( $name, $targets, $current ) {
		if ( ! $targets ) {
			return '<p class="gruppa-muted">No Content entity yet.</p>';
		}
		$html = '<div class="g-targets">';
		foreach ( $targets as $slug => $tname ) {
			$html .= sprintf(
				'<label><input type="checkbox" name="%s" value="%s" %s> %s</label>',
				esc_attr( $name ), esc_attr( $slug ), checked( in_array( $slug, (array) $current, true ), true, false ), esc_html( $tname )
			);
		}
		return $html . '</div>';
	}

	private function group_row( $gi, $g, $targets, $count ) {
		$g = wp_parse_args( $g, array( 'id' => 0, 'name' => '', 'slug' => '', 'targets' => array(), 'choice' => 'many', 'list' => 0 ) );
		$n = 'groups[' . $gi . ']';
		?>
		<div class="g-group gruppa-card" data-count="<?php echo (int) $count; ?>">
			<div class="g-group-head">
				<span class="dashicons dashicons-menu gruppa-handle"></span>
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo (int) $g['id']; ?>">
				<input type="text" class="g-group-name" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( $g['name'] ); ?>" placeholder="Name (e.g. Kategórie)" aria-label="Group name">
				<input type="text" class="g-group-slug" name="<?php echo esc_attr( $n ); ?>[slug]" value="<?php echo esc_attr( $g['slug'] ); ?>" placeholder="slug" aria-label="Group slug" pattern="[a-z0-9_]+" maxlength="40" <?php echo $g['id'] ? 'readonly' : ''; ?>>
				<span class="gruppa-muted g-group-count"><?php echo $g['id'] ? esc_html( sprintf( 1 === (int) $count ? '%d value' : '%d values', $count ) ) : ''; ?></span>
				<button type="button" class="button-link gruppa-delete g-group-remove" <?php echo $count ? 'disabled title="Move or delete its values first"' : ''; ?>>Remove</button>
			</div>
			<div class="g-group-body">
				<div class="g-group-targets"><span class="g-group-label">Attach to</span><?php echo self::targets_html( $n . '[targets][]', $targets, $g['targets'] ); // phpcs:ignore ?></div>
				<div class="g-group-opts">
					<select name="<?php echo esc_attr( $n ); ?>[choice]" aria-label="Choice">
						<?php foreach ( Gruppa_Relations::choices() as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $g['choice'], $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
					<label class="g-inline-check"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[list]" value="1" <?php checked( $g['list'] ); ?>> Column in the items list</label>
				</div>
			</div>
		</div>
		<?php
	}

	/** Repeater columns editor (in the field card). */
	private function sub_editor( $i, $subs ) {
		ob_start();
		?>
		<div class="gruppa-subs" data-index="<?php echo esc_attr( $i ); ?>">
			<div class="gruppa-subs-list">
				<?php foreach ( (array) $subs as $j => $sd ) {
					$this->sub_row( $i, $j, $sd );
				} ?>
			</div>
			<button type="button" class="button gruppa-sub-add">Add column</button>
			<p class="description">Columns of every row, e.g. Name · Position · Photo. Select: one option per line as <code>value : label</code>.</p>
		</div>
		<?php
		return ob_get_clean();
	}

	private function sub_row( $i, $j, $sd ) {
		$sd   = wp_parse_args( $sd, array( 'key' => '', 'label' => '', 'type' => 'text', 'options' => array() ) );
		$n    = 'fields[' . $i . '][sub][' . $j . ']';
		$bulk = '';
		foreach ( (array) $sd['options'] as $o ) {
			$bulk .= $o['value'] === $o['label'] ? $o['value'] . "\n" : $o['value'] . ' : ' . $o['label'] . "\n";
		}
		?>
		<div class="gruppa-sub" data-type="<?php echo esc_attr( $sd['type'] ); ?>">
			<span class="dashicons dashicons-menu gruppa-handle"></span>
			<input type="text" class="g-sub-label" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $sd['label'] ); ?>" placeholder="Label">
			<input type="text" class="g-sub-key" name="<?php echo esc_attr( $n ); ?>[key]" value="<?php echo esc_attr( $sd['key'] ); ?>" placeholder="key" pattern="[a-z0-9_]+" maxlength="40" <?php echo '' !== $sd['key'] ? 'data-touched="1"' : ''; ?>>
			<select class="g-sub-type" name="<?php echo esc_attr( $n ); ?>[type]">
				<?php foreach ( Gruppa_Fields::sub_types() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $sd['type'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button-link gruppa-delete gruppa-sub-remove" aria-label="Remove column">&times;</button>
			<textarea class="g-sub-options code" name="<?php echo esc_attr( $n ); ?>[options_bulk]" rows="3" placeholder="value : label"><?php echo esc_textarea( $bulk ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Condition rows of a field (0.33): Show item [field] · When [operator] · [value] · [and / or].
	 * The field list is filled by types.js from the fields being edited.
	 */
	private function conditions_editor( $i, $conds ) {
		$conds = $conds ? $conds : array( array( 'field' => '', 'op' => 'is', 'value' => '', 'join' => 'and' ) );
		ob_start();
		echo '<div class="gruppa-conds"><div class="gruppa-cond-list">';
		foreach ( array_values( $conds ) as $j => $c ) {
			$this->condition_row( $i, $j, $c );
		}
		echo '</div><button type="button" class="button-link gruppa-cond-add">+ Add condition</button>';
		echo '<p class="description">Show this field only when the condition is met. Empty = always shown.</p></div>';
		return ob_get_clean();
	}

	public function condition_row( $i, $j, $c ) {
		$c = wp_parse_args( $c, array( 'field' => '', 'op' => 'is', 'value' => '', 'join' => 'and' ) );
		$n = 'fields[' . $i . '][conditions][' . $j . ']';
		?>
		<div class="gruppa-cond">
			<select class="g-cond-field" name="<?php echo esc_attr( $n ); ?>[field]" data-value="<?php echo esc_attr( $c['field'] ); ?>" aria-label="Show item when">
				<option value="">Show item when…</option>
				<?php if ( '' !== $c['field'] ) : ?>
					<option value="<?php echo esc_attr( $c['field'] ); ?>" selected><?php echo esc_html( $c['field'] ); ?></option>
				<?php endif; ?>
			</select>
			<select class="g-cond-op" name="<?php echo esc_attr( $n ); ?>[op]" aria-label="Condition">
				<?php foreach ( Gruppa_Fields::condition_ops() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['op'], $k ); ?>><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" class="g-cond-value" name="<?php echo esc_attr( $n ); ?>[value]" value="<?php echo esc_attr( $c['value'] ); ?>" placeholder="Write value" aria-label="Value">
			<select class="g-cond-join" name="<?php echo esc_attr( $n ); ?>[join]" aria-label="And / or">
				<option value="and" <?php selected( $c['join'], 'and' ); ?>>and</option>
				<option value="or" <?php selected( $c['join'], 'or' ); ?>>or</option>
			</select>
			<button type="button" class="button-link gruppa-cond-remove" aria-label="Remove condition">&times;</button>
		</div>
		<?php
	}

	private function option_row( $i, $j, $o ) {
		$o    = wp_parse_args( $o, array( 'value' => '', 'label' => '' ) );
		$name = 'fields[' . $i . '][options][' . $j . ']';
		?>
		<div class="gruppa-option">
			<span class="dashicons dashicons-menu gruppa-handle"></span>
			<input type="text" class="g-opt-value" name="<?php echo esc_attr( $name ); ?>[value]" value="<?php echo esc_attr( $o['value'] ); ?>" placeholder="value">
			<input type="text" class="g-opt-label" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $o['label'] ); ?>" placeholder="label">
			<button type="button" class="button-link gruppa-option-remove" aria-label="Remove">&times;</button>
		</div>
		<?php
	}

	private function field_row( $i, $f, $saved = false, $open = false ) {
		$f     = Gruppa_Fields::normalize( $f );
		$n     = 'fields[' . $i . ']';
		$types = Gruppa_Fields::types();

		$bulk = '';
		foreach ( $f['options'] as $o ) {
			$bulk .= $o['value'] === $o['label'] ? $o['value'] . "\n" : $o['value'] . ' : ' . $o['label'] . "\n";
		}

		$allowed = $types;
		if ( $saved ) {
			$cur     = $f['type'];
			$allowed = array_filter( $types, function ( $k ) use ( $cur ) {
				return Gruppa_Types::type_change_ok( $cur, $k );
			}, ARRAY_FILTER_USE_KEY );
		}
		// 0.22: values of other types are picked through Data types; the Relation field stays only where it is used.
		if ( 'relation' !== $f['type'] || ! $saved ) {
			unset( $allowed['relation'] );
		}

		$row = function ( $label, $for, $html ) {
			printf( '<tr data-for="%s"><th>%s</th><td>%s</td></tr>', esc_attr( $for ), esc_html( $label ), $html ); // phpcs:ignore
		};
		$in = function ( $key, $val, $type = 'text', $attrs = '' ) use ( $n ) {
			return sprintf( '<input type="%s" name="%s[%s]" value="%s" %s>', esc_attr( $type ), esc_attr( $n ), esc_attr( $key ), esc_attr( (string) $val ), $attrs );
		};
		$cb = function ( $key, $on, $label = '' ) use ( $n ) {
			return sprintf( '<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>', esc_attr( $n ), esc_attr( $key ), checked( $on, true, false ), esc_html( $label ) );
		};
		?>
		<div class="gruppa-field<?php echo $open ? ' is-open' : ''; ?>" data-index="<?php echo esc_attr( $i ); ?>" data-type="<?php echo esc_attr( $f['type'] ); ?>">
			<div class="gruppa-field-head">
				<span class="dashicons dashicons-menu gruppa-handle"></span>
				<span class="gruppa-field-title"><?php echo esc_html( $f['label'] ? $f['label'] : 'New field' ); ?></span>
				<code class="gruppa-field-key"><?php echo esc_html( $f['key'] ); ?></code>
				<span class="gruppa-field-type"><?php echo esc_html( isset( $types[ $f['type'] ] ) ? $types[ $f['type'] ] : $f['type'] ); ?></span>
				<button type="button" class="button-link gruppa-field-copy" title="Copy field" aria-label="Copy field"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
				<span class="dashicons dashicons-arrow-down-alt2 gruppa-toggle"></span>
			</div>
			<div class="gruppa-field-body">
				<?php if ( $saved ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $n ); ?>[orig_key]" value="<?php echo esc_attr( $f['key'] ); ?>">
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<?php
					$row( 'Label', '*', $in( 'label', $f['label'], 'text', 'class="regular-text g-label"' ) );
					$row( 'Key', '*', $in( 'key', $f['key'], 'text', 'class="regular-text g-key" pattern="[a-z0-9_]+" maxlength="40"' . ( $saved ? ' data-touched="1"' : '' ) ) );

					$sel = sprintf( '<select class="g-type" name="%s[type]">', esc_attr( $n ) );
					foreach ( $allowed as $k => $l ) {
						$sel .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $f['type'], $k, false ), esc_html( $l ) );
					}
					$sel .= '</select>';
					$row( 'Type', '*', $sel );

					$fsel = sprintf( '<select name="%s[format]">', esc_attr( $n ) );
					foreach ( Gruppa_Fields::formats() as $k => $l ) {
						$fsel .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $f['format'], $k, false ), esc_html( $l ) );
					}
					$row( 'Format', 'text', $fsel . '</select>' );

					$row(
						'HTML',
						'html',
						sprintf( '<textarea name="%s[html]" class="large-text code" rows="5" placeholder="&lt;h3&gt;Technical details&lt;/h3&gt;&#10;&lt;p&gt;Shown between the fields when editing an item. Admin only, stores nothing.&lt;/p&gt;&#10;&lt;hr&gt;">%s</textarea>', esc_attr( $n ), esc_textarea( $f['html'] ) )
					);
					$row( 'Columns', 'repeater', $this->sub_editor( $i, $f['sub'] ) );
					$row( 'Description', 'text textarea editor number date time radio select checkbox switcher media relation repeater', $in( 'description', $f['description'], 'text', 'class="large-text"' ) );
					$row( 'Placeholder', 'text textarea number select relation', $in( 'placeholder', $f['placeholder'], 'text', 'class="regular-text"' ) );
					$row( 'Default', 'text textarea editor number date radio select checkbox', $in( 'default', 'switcher' === $f['type'] ? '' : $f['default'], 'text', 'class="regular-text"' ) );
					$row( 'Default', 'time', $in( 'default', 'time' === $f['type'] ? $f['default'] : '', 'time', '' ) );
					$row( 'Default', 'switcher', $cb( 'default_switch', '1' === (string) $f['default'] && 'switcher' === $f['type'], 'On' ) );
					$row( 'Limit', 'text textarea editor', $in( 'limit', $f['limit'] ? $f['limit'] : '', 'number', 'class="small-text" min="0"' ) );
					$row(
						'Min / Max / Step',
						'number',
						'<div class="gruppa-inline">'
						. $in( 'min', $f['min'], 'number', 'step="any" placeholder="min"' )
						. $in( 'max', $f['max'], 'number', 'step="any" placeholder="max"' )
						. $in( 'step', $f['step'], 'number', 'step="any" placeholder="step"' )
						. '</div>'
					);

					// Options.
					ob_start();
					?>
					<div class="gruppa-options" data-mode="<?php echo esc_attr( $f['options_mode'] ); ?>">
						<p class="gruppa-options-mode">
							<label><input type="radio" class="g-opt-mode" name="<?php echo esc_attr( $n ); ?>[options_mode]" value="manual" <?php checked( $f['options_mode'], 'manual' ); ?>> Manual Input</label>
							<label><input type="radio" class="g-opt-mode" name="<?php echo esc_attr( $n ); ?>[options_mode]" value="bulk" <?php checked( $f['options_mode'], 'bulk' ); ?>> Bulk Input</label>
						</p>
						<div class="gruppa-options-manual">
							<div class="gruppa-options-list">
								<?php foreach ( $f['options'] as $j => $o ) {
									$this->option_row( $i, $j, $o );
								} ?>
							</div>
							<button type="button" class="button gruppa-option-add">Add Option</button>
						</div>
						<div class="gruppa-options-bulk">
							<textarea class="large-text code g-opt-bulk" rows="6" name="<?php echo esc_attr( $n ); ?>[options_bulk]" placeholder="value : label"><?php echo esc_textarea( $bulk ); ?></textarea>
						</div>
					</div>
					<?php
					$row( 'Options', 'radio select checkbox', ob_get_clean() );
					// Relation.
					$rsel = sprintf( '<select name="%s[related]" class="g-related"><option value="">— select —</option>', esc_attr( $n ) );
					$rts  = Gruppa_Types::all();
					usort( $rts, function ( $a, $b ) {
						return ( 'relation' === $b->kind ) - ( 'relation' === $a->kind );
					} );
					foreach ( $rts as $rt ) {
						$rsel .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $rt->slug ), selected( $f['related'], $rt->slug, false ), esc_html( $rt->name . ( 'relation' === $rt->kind ? '' : ' (' . Gruppa_Types::kinds()[ $rt->kind ] . ')' ) ) );
					}
					$row( 'Related type', 'relation', $rsel . '</select>' );
					$row( 'Multiple', 'select relation', $cb( 'multiple', $f['multiple'] ) );
					$usel = sprintf( '<select name="%s[ui]">', esc_attr( $n ) );
					foreach ( array( 'select' => 'Select', 'list' => 'Checkboxes / Radio' ) as $k => $l ) {
						$usel .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $f['ui'], $k, false ), esc_html( $l ) );
					}
					$row( 'Display', 'relation', $usel . '</select>' );
					$row( 'Save as array', 'radio select checkbox', $cb( 'save_as_array', $f['save_as_array'] ) );

					// Media.
					$ret = sprintf(
						'<label><input type="radio" name="%1$s[return]" value="id" %2$s> ID</label> &nbsp; <label><input type="radio" name="%1$s[return]" value="url" %3$s> URL</label>',
						esc_attr( $n ), checked( $f['return'], 'id', false ), checked( $f['return'], 'url', false )
					);
					$row( 'Return', 'media', $ret );
					$row( 'File types', 'media', $in( 'file_types', $f['file_types'], 'text', 'class="regular-text" placeholder="jpg, png, pdf"' ) );
					$row( 'Max files', 'media', $in( 'max_files', $f['max_files'], 'number', 'class="small-text" min="0"' ) );
					$row( 'Max MB', 'media', $in( 'max_mb', $f['max_mb'] ? $f['max_mb'] : '', 'number', 'class="small-text" min="0" step="any"' ) );

					// 0.33: end of the field — Condition, Items list, Required, Remove.
					$row( 'Condition', '*', $this->conditions_editor( $i, $f['conditions'] ) );
					$row( 'Items list', 'text textarea editor number date time radio select checkbox switcher media relation repeater icon', $cb( 'list', $f['list'], 'Show as a column in the items list. Select, Radio, Checkbox, Relation and Switcher columns also get a filter above the list.' ) );
					$row( 'Required', 'text textarea editor number date time radio select checkbox media relation repeater icon', $cb( 'required', $f['required'] ) );
					?>
				</table>
				<p><button type="button" class="button-link button-link-delete gruppa-remove-field">Remove</button></p>
			</div>
		</div>
		<?php
	}
}
