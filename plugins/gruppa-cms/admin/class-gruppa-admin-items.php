<?php
defined( 'ABSPATH' ) || exit;

class Gruppa_Admin_Items {

	const CAP = 'manage_options';

	private $errors = array();
	private $posted = null;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	public static function page_slug( $type_slug ) {
		return 'gruppa-item-' . $type_slug;
	}

	private function current_type() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		if ( 0 !== strpos( $page, 'gruppa-item-' ) ) {
			return null;
		}
		$type = Gruppa_Types::get_by_slug( substr( $page, strlen( 'gruppa-item-' ) ) );
		return ( $type && 'publish' === $type->status ) ? $type : null;
	}

	/** Admin URL of the entity's items. Keeps ?parent= (entity shown under a parent in the menu, 0.29). */
	private function url( $type, $args = array() ) {
		$base   = array( 'page' => self::page_slug( $type->slug ) );
		$parent = Gruppa_Menu::request_parent( $type->slug );
		if ( $parent ) {
			$base['parent'] = $parent;
		}
		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}

	/** Hidden input of the current ?parent= for GET forms on the list. */
	private static function parent_input( $type ) {
		$parent = Gruppa_Menu::request_parent( $type->slug );
		if ( $parent ) {
			printf( '<input type="hidden" name="parent" value="%s">', esc_attr( $parent ) );
		}
	}

	/* ---------- Handlers (admin_init) ---------- */

	public function handle() {
		$type = $this->current_type();
		if ( ! $type || ! current_user_can( self::CAP ) ) {
			return;
		}

		// Save.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['gruppa_item_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gruppa_item_nonce'] ) ), 'gruppa_item_save_' . $type->slug ) ) {
				wp_die( 'Invalid nonce' );
			}
			$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
			$post = wp_unslash( $_POST );

			list( $row, $errors ) = Gruppa_Items::prepare( $type, $post );
			if ( $errors ) {
				$this->errors = $errors;
				$this->posted = $post;
				return;
			}
			$res = Gruppa_Items::save( $type, $row, $id );
			if ( is_wp_error( $res ) ) {
				$this->errors = array( $res->get_error_message() );
				$this->posted = $post;
				return;
			}
			Gruppa_Relations::save_posted( $type, $res, isset( $post['gl'] ) ? $post['gl'] : array() );
			wp_safe_redirect( $this->url( $type, array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' ) ) );
			exit;
		}

		// Bulk actions.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['gruppa_bulk_nonce'] ) ) {
			check_admin_referer( 'gruppa_items_bulk_' . $type->slug, 'gruppa_bulk_nonce' );
			$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
			$ids    = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();
			$back   = remove_query_arg( array( 'msg', 'n' ), wp_get_referer() ? wp_get_referer() : $this->url( $type ) );
			$move = 0 === strpos( $action, 'group_' ) ? Gruppa_Relations::group( $type, (int) substr( $action, 6 ) ) : null;
			if ( $move && $ids ) {
				global $wpdb;
				$cols = Gruppa_Relations::group_columns( $type, $move['id'] );
				foreach ( $ids as $bid ) {
					$wpdb->update( Gruppa_DB::data_table( $type->slug ), $cols, array( 'id' => (int) $bid ) );
				}
				wp_safe_redirect( add_query_arg( array( 'msg' => 'bulk_group', 'n' => count( $ids ) ), $back ) );
				exit;
			}
			if ( ! $ids || ! in_array( $action, array( 'publish', 'draft', 'duplicate', 'delete' ), true ) ) {
				wp_safe_redirect( add_query_arg( 'msg', $ids ? 'bulk_none' : 'bulk_empty', $back ) );
				exit;
			}
			$n = 0;
			foreach ( $ids as $bid ) {
				if ( ! Gruppa_Items::get( $type, $bid ) ) {
					continue;
				}
				if ( 'delete' === $action ) {
					Gruppa_Items::delete( $type, $bid );
				} elseif ( 'duplicate' === $action ) {
					Gruppa_Items::duplicate( $type, $bid );
				} else {
					Gruppa_Items::set_status( $type, $bid, $action );
				}
				$n++;
			}
			wp_safe_redirect( add_query_arg( array( 'msg' => 'bulk_' . $action, 'n' => $n ), $back ) );
			exit;
		}

		// Row actions.
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '';
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $do && $id ) {
			check_admin_referer( 'gruppa_item_' . $do . '_' . $id );
			$item = Gruppa_Items::get( $type, $id );
			if ( $item ) {
				if ( 'status' === $do ) {
					Gruppa_Items::set_status( $type, $id, 'publish' === $item->status ? 'draft' : 'publish' );
					$msg = 'status';
				} elseif ( 'delete' === $do ) {
					Gruppa_Items::delete( $type, $id );
					$msg = 'deleted';
				} elseif ( 'duplicate' === $do ) {
					$copy = Gruppa_Items::duplicate( $type, $id );
					if ( ! is_wp_error( $copy ) ) {
						wp_safe_redirect( $this->url( $type, array( 'view' => 'edit', 'id' => $copy, 'msg' => 'duplicated' ) ) );
						exit;
					}
					$msg = 'db';
				}
			}
			wp_safe_redirect( $this->url( $type, array( 'msg' => isset( $msg ) ? $msg : 'missing' ) ) );
			exit;
		}
	}

	/* ---------- Views ---------- */

	public function render() {
		$type = $this->current_type();
		if ( ! $type ) {
			echo '<div class="wrap gruppa-wrap">';
			Gruppa_Admin::not_found( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE ), 'This entity' );
			echo '</div>';
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';
		echo '<div class="wrap gruppa-wrap">';
		Gruppa_Admin::notice();
		if ( $this->errors ) {
			echo '<div class="notice notice-error"><p>' . implode( '<br>', array_map( 'esc_html', $this->errors ) ) . '</p></div>';
		}
		if ( 'edit' === $view ) {
			$this->render_edit( $type, isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		} else {
			$this->render_list( $type );
		}
		echo '</div>';
	}

	private function row_action( $type, $do, $id ) {
		return wp_nonce_url( $this->url( $type, array( 'do' => $do, 'id' => (int) $id ) ), 'gruppa_item_' . $do . '_' . (int) $id );
	}

	/** Fields shown as columns of the items list. */
	private static function list_fields( $type ) {
		return array_values( array_filter( Gruppa_Types::fields_all( $type ), function ( $f ) {
			return ! empty( $f['list'] ) && ! Gruppa_Fields::is_password( $f );
		} ) );
	}

	/** Listed fields that can be filtered: [ key => [ label, choices ] ]. */
	private static function filter_fields( $type ) {
		$out = array();
		foreach ( self::list_fields( $type ) as $f ) {
			if ( Gruppa_Fields::has_options( $f['type'] ) ) {
				$out[ $f['key'] ] = array( $f, wp_list_pluck( $f['options'], 'label', 'value' ) );
			} elseif ( 'relation' === $f['type'] ) {
				$out[ $f['key'] ] = array( $f, Gruppa_Fields::relation_options( $f ) );
			} elseif ( 'switcher' === $f['type'] ) {
				$out[ $f['key'] ] = array( $f, array( '1' => 'Yes', '0' => 'No' ) );
			}
		}
		return $out;
	}

	/** Short, readable value of a field for a list cell. */
	private static function cell( $f, $raw ) {
		switch ( $f['type'] ) {
			case 'switcher':
				return (int) $raw ? '<span class="dashicons dashicons-yes gruppa-yes" aria-label="Yes"></span>' : '<span class="gruppa-muted">—</span>';
			case 'media':
				$ids = array_slice( array_filter( array_map( 'absint', Gruppa_Fields::decode( array_merge( $f, array( 'max_files' => 0 ) ), $raw ) ) ), 0, 3 );
				$out = '';
				foreach ( $ids as $aid ) {
					$out .= wp_get_attachment_image( $aid, array( 40, 40 ), true, array( 'class' => 'gruppa-thumb' ) );
				}
				return $out ? $out : '<span class="gruppa-muted">—</span>';
			case 'relation':
				$opts   = Gruppa_Fields::relation_options( $f );
				$labels = array();
				foreach ( Gruppa_Fields::relation_ids( $f, $raw ) as $rid ) {
					if ( isset( $opts[ $rid ] ) ) {
						$labels[] = $opts[ $rid ];
					}
				}
				return $labels ? esc_html( implode( ', ', $labels ) ) : '<span class="gruppa-muted">—</span>';
			case 'radio':
			case 'select':
			case 'checkbox':
				$map    = wp_list_pluck( $f['options'], 'label', 'value' );
				$labels = array();
				foreach ( (array) Gruppa_Fields::decode( $f, $raw ) as $v ) {
					$labels[] = isset( $map[ $v ] ) ? $map[ $v ] : $v;
				}
				$labels = array_filter( $labels, 'strlen' );
				return $labels ? esc_html( implode( ', ', $labels ) ) : '<span class="gruppa-muted">—</span>';
			case 'date':
				return $raw ? esc_html( mysql2date( get_option( 'date_format' ), $raw ) ) : '<span class="gruppa-muted">—</span>';
			case 'time':
				return $raw ? esc_html( substr( (string) $raw, 0, 5 ) ) : '<span class="gruppa-muted">—</span>';
			case 'repeater':
				$n = count( Gruppa_Fields::decode( $f, $raw ) );
				return $n ? esc_html( sprintf( 1 === $n ? '%d row' : '%d rows', $n ) ) : '<span class="gruppa-muted">—</span>';
			case 'number':
				return null === $raw || '' === $raw ? '<span class="gruppa-muted">—</span>' : esc_html( (string) ( $raw + 0 ) );
		}
		$text = trim( wp_strip_all_tags( (string) $raw ) );
		return '' === $text ? '<span class="gruppa-muted">—</span>' : esc_html( wp_html_excerpt( $text, 60, '…' ) );
	}

	private function render_list( $type ) {
		$paged  = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$s      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : '';

		$cols    = self::list_fields( $type );
		$filters = self::filter_fields( $type );
		$active  = array();
		$where   = array();
		foreach ( $filters as $key => $fl ) {
			$v = isset( $_GET[ 'gf_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'gf_' . $key ] ) ) : ''; // phpcs:ignore
			if ( '' !== $v && isset( $fl[1][ $v ] ) ) {
				$active[ $key ] = $v;
				$where[]        = Gruppa_Listing::condition( $key, $fl[0], 'eq', $v );
			}
		}

		// Data type with groups: tab per group.
		$groups = 'data' === $type->kind && 'groups' === $type->structure ? $type->groups : array();
		// Under a parent in the menu: only the groups attached to that parent (0.29).
		$parent  = Gruppa_Menu::request_parent( $type->slug );
		$allowed = $groups ? Gruppa_Menu::groups_for( $type, $parent ) : null;
		if ( null !== $allowed ) {
			$groups = array_values( array_filter( $groups, function ( $g ) use ( $allowed ) {
				return in_array( (int) $g['id'], $allowed, true );
			} ) );
		}
		$gcur   = isset( $_GET['group'] ) ? (int) $_GET['group'] : 0; // phpcs:ignore
		if ( $gcur && ( ! Gruppa_Relations::group( $type, $gcur ) || ( null !== $allowed && ! in_array( $gcur, $allowed, true ) ) ) ) {
			$gcur = 0;
		}
		global $wpdb;
		if ( $gcur ) {
			$where[] = $wpdb->prepare( 'group_id = %d', $gcur );
		} elseif ( null !== $allowed ) {
			$where[] = 'group_id IN (' . implode( ',', array_map( 'intval', $allowed ) ) . ')';
		}

		list( $rows, $total, $offset ) = Gruppa_Items::query( $type, array( 'page' => $paged, 's' => $s, 'status' => $status, 'where' => $where ) );
		$pages    = (int) ceil( $total / 50 );
		$sortable = '' === $s && '' === $status && ! $active && ( ! $groups || $gcur );
		$ncols    = 6 + count( $cols ) + ( $groups && ! $gcur ? 1 : 0 );
		$base     = $this->url( $type, $gcur ? array( 'group' => $gcur ) : array() );
		?>
		<form method="get" class="gruppa-head-search">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::page_slug( $type->slug ) ); ?>">
			<?php self::parent_input( $type ); ?>
			<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" aria-label="Search items"><button type="submit" class="button">Search</button>
		</form>
		<h1 class="wp-heading-inline"><?php echo esc_html( $type->name ); ?><?php if ( $parent ) : $ptype = Gruppa_Types::get_by_slug( $parent ); ?><span class="gruppa-item-context"><?php echo esc_html( '· ' . ( $ptype ? $ptype->name : $parent ) ); ?></span><?php endif; ?></h1>
		<a href="<?php echo esc_url( $this->url( $type, array_filter( array( 'view' => 'edit', 'group' => $gcur ? $gcur : ( $groups && 1 === count( $groups ) ? (int) $groups[0]['id'] : 0 ) ) ) ) ); ?>" class="page-title-action">Add Item</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::page_of( $type->kind ) . '&view=edit&id=' . (int) $type->id ) ); ?>" class="page-title-action"><?php echo 'data' === $type->kind ? 'Edit Data Type' : 'Edit Content Type'; ?></a>
		<?php
		/**
		 * Extra header buttons of an items list (e.g. "Import/Export" from the Gruppa Import/Export add-on).
		 *
		 * @param object $type Content type.
		 */
		do_action( 'gruppa_cms/items_header_actions', $type );
		?>
		<hr class="wp-header-end">

		<?php if ( $groups ) : ?>
			<nav class="gruppa-tabs gruppa-group-tabs">
				<a href="<?php echo esc_url( $this->url( $type ) ); ?>" class="<?php echo $gcur ? '' : 'is-active'; ?>">All groups <span class="gruppa-tab-count"><?php echo (int) ( null === $allowed ? Gruppa_DB::count_items( $type->slug ) : array_sum( array_map( function ( $g ) use ( $type ) { return Gruppa_Relations::count_in_group( $type->slug, $g['id'] ); }, $groups ) ) ); ?></span></a>
				<?php foreach ( $groups as $g ) : ?>
					<a href="<?php echo esc_url( $this->url( $type, array( 'group' => $g['id'] ) ) ); ?>" class="<?php echo $gcur === (int) $g['id'] ? 'is-active' : ''; ?>"><?php echo esc_html( $g['name'] ); ?> <span class="gruppa-tab-count"><?php echo (int) Gruppa_Relations::count_in_group( $type->slug, $g['id'] ); ?></span></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<?php
		$counts = null === $allowed ? Gruppa_Items::counts( $type ) : Gruppa_Menu::counts_under( $type, $parent ); // Only the groups shown under this parent.
		Gruppa_Admin::status_filter( $base, $status, $counts );
		?>
		<?php // 0.32.5: tabs and their line full width; the list and the Menu panel start 24px under the line, 24px apart. ?>
		<div class="gruppa-arch-layout gruppa-items-layout"><div class="gruppa-arch-main">

		<?php if ( $filters ) : ?>
			<form method="get" class="gruppa-list-filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::page_slug( $type->slug ) ); ?>">
				<?php self::parent_input( $type ); ?>
				<?php if ( $status ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>"><?php endif; ?>
				<?php if ( '' !== $s ) : ?><input type="hidden" name="s" value="<?php echo esc_attr( $s ); ?>"><?php endif; ?>
				<?php foreach ( $filters as $key => $fl ) : ?>
					<select name="gf_<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $fl[0]['label'] ); ?>" onchange="this.form.submit()">
						<option value=""><?php echo esc_html( $fl[0]['label'] . ': all' ); ?></option>
						<?php foreach ( $fl[1] as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( isset( $active[ $key ] ) ? $active[ $key ] : '', (string) $v ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endforeach; ?>
				<noscript><button type="submit" class="button">Filter</button></noscript>
				<?php if ( $active ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array_filter( array( 'status' => $status, 's' => $s ) ), $base ) ); ?>" class="gruppa-list-filters-reset">Clear filters</a>
				<?php endif; ?>
			</form>
		<?php endif; ?>

		<form method="post" class="gruppa-bulk-form gruppa-no-dirty" action="<?php echo esc_url( add_query_arg( array( 'paged' => $paged > 1 ? $paged : null ) + array_filter( array( 'status' => $status, 's' => $s ) ), $base ) ); ?>">
			<?php wp_nonce_field( 'gruppa_items_bulk_' . $type->slug, 'gruppa_bulk_nonce' ); ?>
			<table class="wp-list-table widefat fixed gruppa-table gruppa-items-table">
				<thead>
					<tr>
						<th class="gruppa-col-handle"></th>
						<td class="manage-column check-column gruppa-col-check"><input type="checkbox" class="gruppa-check-all" aria-label="Select all"></td>
						<th class="gruppa-col-id">ID</th>
						<th class="gruppa-col-title">Title</th>
						<?php if ( $groups && ! $gcur ) : ?>
							<th class="gruppa-col-field">Group</th>
						<?php endif; ?>
						<?php foreach ( $cols as $f ) : ?>
							<th class="gruppa-col-field gruppa-col-<?php echo esc_attr( $f['type'] ); ?>"><?php echo esc_html( $f['label'] ); ?><?php if ( Gruppa_Relations::is_link( $f ) && $f['link']['data_name'] !== $f['label'] ) : ?> <span class="gruppa-muted gruppa-col-src"><?php echo esc_html( $f['link']['data_name'] ); ?></span><?php endif; ?></th>
						<?php endforeach; ?>
						<th class="gruppa-col-status">Status</th>
						<th class="gruppa-col-actions">Actions</th>
					</tr>
				</thead>
				<tbody class="<?php echo $sortable ? 'gruppa-sortable' : ''; ?>" data-key="<?php echo esc_attr( $type->slug ); ?>" data-offset="<?php echo (int) $offset; ?>">
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="<?php echo (int) $ncols; ?>"><?php echo $active || '' !== $s ? 'No items match the filters.' : 'No items yet.'; ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) :
					$edit = $this->url( $type, array( 'view' => 'edit', 'id' => $r->id ) );
					?>
					<tr data-id="<?php echo (int) $r->id; ?>">
						<td class="gruppa-col-handle"><?php if ( $sortable ) : ?><span class="dashicons dashicons-menu gruppa-handle"></span><?php endif; ?></td>
						<th scope="row" class="check-column gruppa-col-check"><input type="checkbox" name="ids[]" value="<?php echo (int) $r->id; ?>" aria-label="<?php echo esc_attr( 'Select ' . $r->title ); ?>"></th>
						<td><?php echo (int) $r->id; ?></td>
						<td>
							<strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $r->title ); ?></a></strong>
							<span class="gruppa-row-slug"><?php echo esc_html( $r->slug ); ?></span>
						</td>
						<?php if ( $groups && ! $gcur ) : ?>
							<td class="gruppa-col-field"><?php echo isset( $r->group_title ) && '' !== $r->group_title ? esc_html( $r->group_title ) : '<span class="gruppa-muted">—</span>'; ?></td>
						<?php endif; ?>
						<?php foreach ( $cols as $f ) : ?>
							<td class="gruppa-col-field"><?php echo self::cell( $f, isset( $r->{$f['key']} ) ? $r->{$f['key']} : null ); // phpcs:ignore ?></td>
						<?php endforeach; ?>
						<td><?php echo 'publish' === $r->status ? 'Published' : 'Draft'; ?></td>
						<td>
							<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
							<?php if ( $r->wp_post_id && get_post( (int) $r->wp_post_id ) ) : ?>
								<a href="<?php echo esc_url( get_permalink( (int) $r->wp_post_id ) ); ?>" target="_blank">View</a> |
							<?php endif; ?>
							<a href="<?php echo esc_url( $this->row_action( $type, 'duplicate', $r->id ) ); ?>">Duplicate</a> |
							<a href="<?php echo esc_url( $this->row_action( $type, 'status', $r->id ) ); ?>"><?php echo 'publish' === $r->status ? 'Draft' : 'Publish'; ?></a> |
							<a class="gruppa-delete" href="<?php echo esc_url( $this->row_action( $type, 'delete', $r->id ) ); ?>">Delete</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div class="tablenav bottom gruppa-bulk-bar">
				<select name="bulk_action" aria-label="Bulk actions">
					<option value="">Bulk actions</option>
					<option value="publish">Publish</option>
					<option value="draft">Move to Draft</option>
					<option value="duplicate">Duplicate</option>
					<option value="delete">Delete</option>
					<?php if ( $groups ) : ?>
						<optgroup label="Move to group">
							<?php foreach ( $groups as $g ) : ?>
								<option value="group_<?php echo (int) $g['id']; ?>"><?php echo esc_html( $g['name'] ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endif; ?>
				</select>
				<button type="submit" class="button gruppa-bulk-apply">Apply</button>
				<span class="gruppa-muted gruppa-bulk-count"><?php echo esc_html( sprintf( '%d items', $total ) ); ?></span>
			</div>
		</form>

		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom"><div class="tablenav-pages">
				<?php
				echo paginate_links( array( // phpcs:ignore
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $pages,
				) );
				?>
			</div></div>
		<?php endif; ?>
		</div>
		<?php Gruppa_Admin_Architecture::nav( $type->slug, (string) Gruppa_Menu::request_parent( $type->slug ) ); ?>
		</div>
		<?php
	}

	private function render_edit( $type, $id ) {
		$item = $id ? Gruppa_Items::get( $type, $id ) : null;
		if ( $id && ! $item ) {
			Gruppa_Admin::not_found( $this->url( $type ), 'This item' );
			return;
		}
		$p      = $this->posted;
		$is_new = ! $item;

		$title  = $p ? ( isset( $p['title'] ) ? $p['title'] : '' ) : ( $item ? $item->title : '' );
		$slug   = $p ? ( isset( $p['slug'] ) ? $p['slug'] : '' ) : ( $item ? $item->slug : '' );
		$status = $p ? ( isset( $p['status'] ) ? $p['status'] : 'publish' ) : ( $item ? $item->status : 'publish' );
		$view   = $item && $item->wp_post_id && get_post( (int) $item->wp_post_id ) ? get_permalink( (int) $item->wp_post_id ) : '';
		$author = $item ? get_userdata( (int) $item->author ) : wp_get_current_user();
		$fmt    = 'Y/m/d H:i';
		?>
		<h1 class="wp-heading-inline"><?php echo $is_new ? 'Add Item' : 'Edit Item'; ?> <span class="gruppa-muted gruppa-h1-sub"><?php echo esc_html( $is_new ? $type->name : $item->title ); ?></span></h1>
		<a href="<?php echo esc_url( $this->url( $type ) ); ?>" class="page-title-action">Back</a>
		<?php if ( $item ) : ?>
			<a href="<?php echo esc_url( $this->url( $type, array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Item</a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<form method="post" action="<?php echo esc_url( $this->url( $type, array( 'view' => 'edit', 'id' => $id ) ) ); ?>" class="gruppa-item-form">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gruppa_item_save_' . $type->slug, 'gruppa_item_nonce' ); ?>

			<div class="gruppa-edit-layout">
				<div class="gruppa-edit-main">
					<div class="gruppa-card">
					<table class="form-table gruppa-form-table" role="presentation">
						<tr>
							<th><label for="gi-title">Title <span class="gruppa-req">*</span></label></th>
							<td><input type="text" id="gi-title" name="title" value="<?php echo esc_attr( $title ); ?>" required></td>
						</tr>
						<tr>
							<th><label for="gi-slug">Slug <span class="gruppa-req">*</span></label></th>
							<td><input type="text" id="gi-slug" name="slug" required value="<?php echo esc_attr( $slug ); ?>" <?php echo $is_new ? '' : 'data-touched="1"'; ?>></td>
						</tr>
						<?php if ( 'data' === $type->kind && 'groups' === $type->structure ) :
							$gsel = $p ? ( isset( $p['group_id'] ) ? (int) $p['group_id'] : 0 ) : ( $item ? (int) $item->group_id : ( isset( $_GET['group'] ) ? (int) $_GET['group'] : 0 ) ); // phpcs:ignore
							?>
							<tr>
								<th><label for="gi-group">Group <span class="gruppa-req">*</span></label></th>
								<td>
									<select name="group_id" id="gi-group" required>
										<option value="">— select —</option>
										<?php foreach ( $type->groups as $g ) : ?>
											<option value="<?php echo (int) $g['id']; ?>" <?php selected( $gsel, (int) $g['id'] ); ?>><?php echo esc_html( $g['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endif; ?>
						<?php foreach ( $type->fields as $f ) :
							if ( $p ) {
								$val = isset( $p['gf'][ $f['key'] ] ) ? $p['gf'][ $f['key'] ] : ( Gruppa_Fields::is_multi( $f ) ? array() : '' );
								if ( 'media' === $f['type'] ) {
									$val = array_filter( explode( ',', (string) $val ) );
								}
							} elseif ( $item ) {
								$val = Gruppa_Fields::decode( $f, isset( $item->{$f['key']} ) ? $item->{$f['key']} : null );
							} else {
								$val = Gruppa_Fields::default_value( $f );
							}
							// 0.33: conditions → items.js shows / hides the row while editing.
							$cond = ' data-gkey="' . esc_attr( $f['key'] ) . '"' . ( ! empty( $f['conditions'] ) ? " data-cond='" . esc_attr( wp_json_encode( $f['conditions'] ) ) . "'" : '' );
							?>
							<?php if ( Gruppa_Fields::is_virtual( $f ) ) : ?>
								<tr class="gruppa-html-row"<?php echo $cond; // phpcs:ignore ?>><td colspan="2"><?php Gruppa_Fields::render_input( $f, null ); ?></td></tr>
							<?php elseif ( 'repeater' === $f['type'] ) : ?>
								<tr class="gruppa-repeater-row-field"<?php echo $cond; // phpcs:ignore ?>>
									<th><?php echo esc_html( $f['label'] ); ?><?php echo $f['required'] ? ' <span class="gruppa-req">*</span>' : ''; ?></th>
									<td><?php self::render_field( $f, $val ); ?></td>
								</tr>
							<?php else : ?>
							<tr<?php echo $cond; // phpcs:ignore ?>>
								<th><label for="gf-<?php echo esc_attr( $f['key'] ); ?>"><?php echo esc_html( $f['label'] ); ?><?php echo $f['required'] ? ' <span class="gruppa-req">*</span>' : ''; ?></label></th>
								<td><?php self::render_field( $f, $val ); ?></td>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</table>
					</div>
					<?php $this->link_boxes( $type, $item, $p ); ?>
				</div>

				<aside class="gruppa-edit-side">
					<div class="gruppa-card gruppa-side-box">
						<p class="gruppa-side-row">
							<label for="gi-status" class="screen-reader-text">Status</label>
							<select name="status" id="gi-status">
								<option value="publish" <?php selected( $status, 'publish' ); ?>>Published</option>
								<option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option>
							</select>
						</p>
						<dl class="gruppa-side-meta">
							<?php if ( $item ) : ?>
								<dt>ID</dt><dd><?php echo (int) $item->id; ?></dd>
							<?php endif; ?>
							<dt>Author</dt><dd><?php echo esc_html( $author ? $author->display_name : '—' ); ?></dd>
							<?php if ( $item ) : ?>
								<dt>Created</dt><dd><?php echo esc_html( $item->created ? mysql2date( $fmt, $item->created ) : '—' ); ?></dd>
								<dt>Modified</dt><dd><?php echo esc_html( $item->modified ? mysql2date( $fmt, $item->modified ) : '—' ); ?></dd>
							<?php endif; ?>
						</dl>
						<button type="submit" class="button button-primary gruppa-side-save"><?php echo $is_new ? 'Publish' : 'Update'; ?></button>
						<?php if ( $item ) : ?>
							<p class="gruppa-side-links">
								<?php if ( $view ) : ?>
									<a href="<?php echo esc_url( $view ); ?>" target="_blank">View<span class="screen-reader-text"> (opens in a new tab)</span> ↗</a>
								<?php endif; ?>
								<a href="<?php echo esc_url( $this->row_action( $type, 'duplicate', $item->id ) ); ?>">Duplicate</a>
								<a class="gruppa-delete" href="<?php echo esc_url( $this->row_action( $type, 'delete', $item->id ) ); ?>">Delete</a>
							</p>
						<?php endif; ?>
					</div>

					<?php if ( $item && 'content' === $type->kind && class_exists( 'Gruppa_Admin_SEO' ) ) :
						$seo = Gruppa_SEO::get_by_owner( $type->slug, (int) $item->id );
						?>
						<div class="gruppa-card gruppa-side-box">
							<p class="gruppa-side-title">SEO optimization</p>
							<?php if ( $seo ) : ?>
								<p class="gruppa-side-seo"><?php echo esc_html( '' !== (string) $seo->seo_title ? $seo->seo_title : $item->title ); ?></p>
								<a href="<?php echo esc_url( Gruppa_Admin_SEO::url( array( 'view' => 'edit', 'id' => (int) $seo->id ) ) ); ?>" class="button button-primary gruppa-side-save">Edit SEO</a>
							<?php else : ?>
								<p class="gruppa-muted">Not set — Google gets the title and text of the page.</p>
								<a href="<?php echo esc_url( Gruppa_Admin_SEO::url( array( 'view' => 'edit', 'owner' => $type->slug . ':' . (int) $item->id ) ) ); ?>" class="button button-primary gruppa-side-save">Add SEO</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</aside>
			</div>
		</form>
		<?php
	}

	/** Boxes of the data links of a Content type: values of each attached group to tick. */
	private function link_boxes( $type, $item, $posted ) {
		foreach ( Gruppa_Relations::links( $type ) as $l ) {
			$opts = Gruppa_Relations::options( $l );
			if ( $posted ) {
				$cur = isset( $posted['gl'][ $l['key'] ] ) ? array_map( 'intval', (array) $posted['gl'][ $l['key'] ] ) : array();
			} else {
				$cur = $item ? Gruppa_Fields::relation_ids( Gruppa_Relations::field( $l ), isset( $item->{$l['key']} ) ? $item->{$l['key']} : '' ) : array();
			}
			$data   = Gruppa_Types::get_by_slug( $l['data'] );
			$manage = $data && 'publish' === $data->status ? add_query_arg( array_filter( array( 'page' => self::page_slug( $l['data'] ), 'parent' => $type->slug, 'group' => $l['group'] ) ), admin_url( 'admin.php' ) ) : '';
			$name   = 'gl[' . $l['key'] . '][]';
			$one    = 'one' === $l['choice'];
			$count  = count( $opts );
			?>
			<div class="gruppa-card gruppa-link-box">
				<div class="gruppa-link-head">
					<h3 class="gruppa-link-title"><?php echo esc_html( $l['label'] ); ?></h3>
					<span class="gruppa-muted"><?php echo esc_html( $l['data_name'] . ' · ' . ( $one ? 'one' : 'many' ) ); ?></span>
					<?php if ( $manage ) : ?>
						<a href="<?php echo esc_url( $manage ); ?>" target="_blank" class="gruppa-link-manage">Manage<span class="screen-reader-text"> (opens in a new tab)</span> ↗</a>
					<?php endif; ?>
				</div>
				<?php if ( ! $opts ) : ?>
					<p class="gruppa-muted">No values yet.</p>
				<?php elseif ( $one && isset( $l['ui'] ) && 'select' === $l['ui'] ) : ?>
					<div class="gruppa-choice gruppa-choice--select">
						<?php if ( $count > 8 ) : ?>
							<input type="search" class="gruppa-select-search" placeholder="Search…" aria-label="Search">
						<?php endif; ?>
						<select name="<?php echo esc_attr( $name ); ?>" class="gruppa-link-select">
							<option value="">— None —</option>
							<?php foreach ( $opts as $vid => $vt ) : ?>
								<option value="<?php echo (int) $vid; ?>" <?php selected( in_array( (int) $vid, $cur, true ) ); ?>><?php echo esc_html( $vt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php else : ?>
					<div class="gruppa-choice<?php echo $count > 8 ? ' has-search' : ''; ?>">
						<?php if ( $count > 8 ) : ?>
							<input type="search" class="gruppa-choice-search" placeholder="Search…" aria-label="Search">
						<?php endif; ?>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="">
						<fieldset class="gruppa-relation-list">
							<?php if ( $one ) : ?>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="" <?php checked( ! $cur ); ?>> <span class="gruppa-muted">None</span></label>
							<?php endif; ?>
							<?php foreach ( $opts as $vid => $vt ) : ?>
								<label><input type="<?php echo $one ? 'radio' : 'checkbox'; ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo (int) $vid; ?>" <?php checked( in_array( (int) $vid, $cur, true ) ); ?>> <?php echo esc_html( $vt ); ?></label>
							<?php endforeach; ?>
						</fieldset>
					</div>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Admin input of a field. Multiple Relation / Select = checkboxes (with search when long)
	 * instead of a Ctrl+click list; the frontend keeps the field's own Display setting.
	 */
	public static function render_field( $f, $val ) {
		if ( 'relation' === $f['type'] && Gruppa_Fields::is_multi( $f ) ) {
			$f['ui'] = 'list';
			$count   = count( Gruppa_Fields::relation_options( $f ) );
			echo '<div class="gruppa-choice' . ( $count > 8 ? ' has-search' : '' ) . '">';
			if ( $count > 8 ) {
				echo '<input type="search" class="gruppa-choice-search" placeholder="Search…" aria-label="Search">';
			}
			Gruppa_Fields::render_input( $f, $val );
			echo '</div>';
			return;
		}
		if ( 'select' === $f['type'] && ! empty( $f['multiple'] ) ) {
			$f['type'] = 'checkbox';
			$count     = count( $f['options'] );
			echo '<div class="gruppa-choice gruppa-choice--options' . ( $count > 8 ? ' has-search' : '' ) . '">';
			if ( $count > 8 ) {
				echo '<input type="search" class="gruppa-choice-search" placeholder="Search…" aria-label="Search">';
			}
			printf( '<input type="hidden" name="gf[%s][]" value="">', esc_attr( $f['key'] ) );
			Gruppa_Fields::render_input( $f, $val );
			echo '</div>';
			return;
		}
		Gruppa_Fields::render_input( $f, $val );
	}
}
