<?php
defined( 'ABSPATH' ) || exit;

/**
 * Listing Builder admin: list, editor (type, query, static filters), Edit template (Elementor).
 */
class Gruppa_Admin_Listings {

	const PAGE = 'gruppa-listings';
	const CAP  = 'manage_options';

	public function __construct() {
		add_action( 'admin_post_gruppa_listing_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_listing_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_gruppa_listing_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_gruppa_listing_template', array( $this, 'handle_template' ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private function action_url( $action, $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => $action, 'id' => (int) $id ), admin_url( 'admin-post.php' ) ), $action . '_' . (int) $id );
	}

	/* ---------- Handlers ---------- */

	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_listing_save' );
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = wp_unslash( $_POST );
		$res  = Gruppa_Listing::save( $data, $id );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( self::url( array( 'view' => 'edit', 'id' => $id, 'msg' => $res->get_error_code() ) ) );
			exit;
		}
		// "Save & edit template" goes straight to Elementor.
		if ( ! empty( $data['edit_template'] ) ) {
			// Raw URL (wp_nonce_url() would encode & as &amp; and the nonce would get lost).
			wp_safe_redirect( add_query_arg( array(
				'action'   => 'gruppa_listing_template',
				'id'       => $res,
				'_wpnonce' => wp_create_nonce( 'gruppa_listing_template_' . $res ),
			), admin_url( 'admin-post.php' ) ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' ) ) );
		exit;
	}

	public function handle_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_listing_status_' . $id );
		$l = Gruppa_Listing::get( $id );
		if ( $l ) {
			Gruppa_Listing::set_status( $id, 'publish' === $l->status ? 'draft' : 'publish' );
		}
		wp_safe_redirect( self::url( array( 'msg' => 'status' ) ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_listing_delete_' . $id );
		Gruppa_Listing::delete( $id );
		wp_safe_redirect( self::url( array( 'msg' => 'deleted' ) ) );
		exit;
	}

	public function handle_template() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_listing_template_' . $id );
		$l = Gruppa_Listing::get( $id );
		if ( ! $l ) {
			wp_die( 'Listing not found.' );
		}
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			wp_die( 'Elementor is not active. Activate Elementor to design the listing item.', 'Gruppa CMS', array( 'back_link' => true ) );
		}
		$pid = Gruppa_Listing::ensure_template( $l );
		if ( ! $pid ) {
			wp_die( 'Could not create the template.', 'Gruppa CMS', array( 'back_link' => true ) );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . $pid . '&action=elementor' ) );
		exit;
	}

	/* ---------- Views ---------- */

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore
		echo '<div class="wrap gruppa-wrap">';
		Gruppa_Admin::notice();
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			echo '<div class="notice notice-warning"><p>Listing templates are designed in <strong>Elementor</strong>. Without it listings show a simple title list.</p></div>';
		}
		'edit' === $view ? $this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 ) : $this->render_list(); // phpcs:ignore
		echo '</div>';
	}

	private function render_list() {
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$rows   = Gruppa_Listing::all( $status );
		$types  = array();
		foreach ( Gruppa_Types::all() as $t ) {
			$types[ $t->slug ] = $t->name;
		}
		?>
		<h1 class="wp-heading-inline">Listings</h1>
		<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Listing</a>
		<hr class="wp-header-end">
		<?php Gruppa_Admin::status_filter( self::url(), $status, Gruppa_Listing::counts() ); ?>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead>
				<tr>
					<th class="gruppa-col-handle"></th>
					<th class="gruppa-col-id">ID</th>
					<th>Name</th>
					<th>Type</th>
					<th class="gruppa-col-shortcode">Shortcode</th>
					<th>Modified</th>
					<th class="gruppa-col-actions">Actions</th>
				</tr>
			</thead>
			<tbody class="<?php echo '' === $status ? 'gruppa-sortable' : ''; ?>" data-key="_listings" data-offset="0">
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="7">No listings yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $l ) :
				$edit = self::url( array( 'view' => 'edit', 'id' => $l->id ) );
				?>
				<tr data-id="<?php echo (int) $l->id; ?>">
					<td class="gruppa-col-handle"><?php if ( '' === $status ) : ?><span class="dashicons dashicons-menu gruppa-handle"></span><?php endif; ?></td>
					<td><?php echo (int) $l->id; ?></td>
					<td><strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $l->name ); ?></a></strong><?php echo 'publish' === $l->status ? '' : ' <span class="gruppa-count">Draft</span>'; ?></td>
					<td><?php echo esc_html( isset( $types[ $l->type_slug ] ) ? $types[ $l->type_slug ] : $l->type_slug . ' (missing)' ); ?></td>
					<td><input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_listing listing="' . $l->slug . '"]' ); ?>" onclick="this.select()"></td>
					<td><?php echo esc_html( $l->modified ? mysql2date( 'Y/m/d H:i', $l->modified ) : '—' ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
						<a href="<?php echo esc_url( $this->action_url( 'gruppa_listing_template', $l->id ) ); ?>">Template</a> |
						<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'listing', $l->id ) ); ?>">Duplicate</a> |
						<a href="<?php echo esc_url( $this->action_url( 'gruppa_listing_status', $l->id ) ); ?>"><?php echo 'publish' === $l->status ? 'Draft' : 'Publish'; ?></a> |
						<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_listing_delete', $l->id ) ); ?>" onclick="return confirm('Delete this listing and its template?')">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_edit( $id ) {
		$l = $id ? Gruppa_Listing::get( $id ) : null;
		if ( $id && ! $l ) {
			Gruppa_Admin::not_found( $this->url(), 'This listing' );
			return;
		}
		$s     = $l ? $l->settings : Gruppa_Listing::defaults();
		$types = Gruppa_Types::all();
		$req   = ' <span class="gruppa-req">*</span>';
		?>
		<h1 class="wp-heading-inline"><?php echo $l ? esc_html( $l->name ) : 'Add Listing'; ?></h1>
		<a href="<?php echo esc_url( self::url() ); ?>" class="page-title-action">Back</a>
		<?php if ( $l ) : ?>
			<a href="<?php echo esc_url( $this->action_url( 'gruppa_listing_template', $l->id ) ); ?>" class="page-title-action">Edit template</a>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Listing</a>
			<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'listing', $l->id ) ); ?>" class="page-title-action">Duplicate</a>
		<?php endif; ?>
		<hr class="wp-header-end">


		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gl-form">
			<input type="hidden" name="action" value="gruppa_listing_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gruppa_listing_save' ); ?>

			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-edit-layout">
			<div class="gruppa-edit-main">
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="g-name">Name<?php echo $req; // phpcs:ignore ?></label></th>
						<td><input type="text" id="g-name" name="name" value="<?php echo esc_attr( $l ? $l->name : '' ); ?>" required></td>
					</tr>
					<tr>
						<th><label for="g-slug">Slug<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<?php if ( $l ) : ?>
								<input type="text" id="g-slug" value="<?php echo esc_attr( $l->slug ); ?>" readonly>
							<?php else : ?>
								<input type="text" id="g-slug" name="slug" pattern="[a-z0-9_]+" maxlength="40" required>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="gl-type">Content type<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<select name="type_slug" id="gl-type" required>
								<option value="">— select —</option>
								<?php foreach ( $types as $t ) : ?>
									<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $l ? $l->type_slug : '', $t->slug ); ?>><?php echo esc_html( $t->name . ' (' . Gruppa_Types::kinds()[ $t->kind ] . ( 'publish' === $t->status ? '' : ', draft' ) . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Query</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gl-orderby">Order by</label></th>
						<td><select name="settings[orderby]" id="gl-orderby" data-current="<?php echo esc_attr( $s['orderby'] ); ?>"><option value="<?php echo esc_attr( $s['orderby'] ); ?>" selected><?php echo esc_html( $s['orderby'] ); ?></option></select></td>
					</tr>
					<tr>
						<th><label for="gl-order">Direction</label></th>
						<td>
							<select name="settings[order]" id="gl-order" class="gl-order">
								<option value="asc" <?php selected( $s['order'], 'asc' ); ?>>Ascending (A → Z, 1 → 9)</option>
								<option value="desc" <?php selected( $s['order'], 'desc' ); ?>>Descending (Z → A, 9 → 1)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="gl-per">Items per page</label></th>
						<td><input type="number" id="gl-per" name="settings[per_page]" min="0" max="<?php echo (int) Gruppa_Listing::MAX_PAGE; ?>" value="<?php echo (int) $s['per_page']; ?>"><p class="description">0 = all items.</p></td>
					</tr>
					<tr id="gl-group-row">
						<th><label for="gl-group">Group</label></th>
						<td>
							<select name="settings[group]" id="gl-group" data-current="<?php echo (int) ( isset( $s['group'] ) ? $s['group'] : 0 ); ?>"><option value="0">All groups</option></select>
							<p class="description">Data type with groups: list only the values of one group, e.g. Kategórie.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gl-context">Related to current item</label></th>
						<td>
							<select name="settings[context_field]" id="gl-context" data-current="<?php echo esc_attr( $s['context_field'] ); ?>"><option value="<?php echo esc_attr( $s['context_field'] ); ?>" selected><?php echo esc_html( '' === $s['context_field'] ? '— no —' : $s['context_field'] ); ?></option></select>
							<p class="description">Optional. Choose a data link (or a Relation field) to show only items linked to the current item — the item whose page the listing is on, or the card of an outer listing. Example: listing Kategórie (values of a Data type) with a nested listing Produkty + link <code>produkty_data__kategorie</code> → each category card shows its products. Without a current item all items are shown.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gl-empty">Empty text</label></th>
						<td><input type="text" id="gl-empty" name="settings[empty_text]" value="<?php echo esc_attr( $s['empty_text'] ); ?>"></td>
					</tr>
					<tr>
						<th>Only my items</th>
						<td><label><input type="checkbox" name="settings[only_mine]" value="1" <?php checked( $s['only_mine'] ); ?>> Show only items of the logged-in user (e.g. My listings)</label></td>
					</tr>
				</table>
			</div>

			<?php if ( $l ) : ?>
			<h2 class="gruppa-fields-title">Frontend</h2>
			<div class="gruppa-card gruppa-form-embed">
				<p>
					<input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_listing listing="' . $l->slug . '"]' ); ?>" onclick="this.select()">
					<span class="gruppa-muted">Elementor: widget <em>Gruppa Listing</em> · Template:
						<?php echo Gruppa_Listing::elementor_ready( $l->template_id ) ? 'designed in Elementor' : 'not designed yet (simple title list is shown)'; ?></span>
				</p>
			</div>
		<?php endif; ?>
			</div>

			<?php $author = get_userdata( $l ? (int) $l->author : get_current_user_id() ); $fmt = 'Y/m/d H:i'; ?>
			<aside class="gruppa-edit-side">
				<div class="gruppa-card gruppa-side-box">
					<p class="gruppa-side-row">
						<label for="g-status" class="screen-reader-text">Status</label>
						<select name="status" id="g-status">
							<option value="publish" <?php selected( $l ? $l->status : 'publish', 'publish' ); ?>>Published</option>
							<option value="draft" <?php selected( $l ? $l->status : '', 'draft' ); ?>>Draft</option>
						</select>
					</p>
					<dl class="gruppa-side-meta">
						<?php if ( $l ) : ?>
							<dt>ID</dt><dd><?php echo (int) $l->id; ?></dd>
						<?php endif; ?>
						<dt>Author</dt><dd><?php echo esc_html( $author ? $author->display_name : '—' ); ?></dd>
						<?php if ( $l ) : ?>
							<dt>Created</dt><dd><?php echo esc_html( ! empty( $l->created ) ? mysql2date( $fmt, $l->created ) : '—' ); ?></dd>
							<dt>Modified</dt><dd><?php echo esc_html( ! empty( $l->modified ) ? mysql2date( $fmt, $l->modified ) : '—' ); ?></dd>
						<?php endif; ?>
					</dl>
					<div class="gruppa-side-buttons">
						<?php // Update first in the markup (Enter key), shown last. ?>
						<button type="submit" class="button button-primary gruppa-side-save"><?php echo $l ? 'Update' : 'Publish'; ?></button>
						<?php if ( class_exists( '\Elementor\Plugin' ) ) : ?>
							<button type="submit" class="button gruppa-side-save" name="edit_template" value="1"><?php echo $l ? 'Update & edit template' : 'Publish & design template'; ?></button>
						<?php endif; ?>
					</div>
					<?php if ( $l ) : ?>
						<p class="gruppa-side-links">
							<a href="<?php echo esc_url( $this->action_url( 'gruppa_listing_template', $l->id ) ); ?>">Edit template</a>
							<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'listing', $l->id ) ); ?>">Duplicate</a>
							<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_listing_delete', $l->id ) ); ?>" onclick="return confirm('Delete this listing?')">Delete</a>
						</p>
					<?php endif; ?>
				</div>
			</aside>
			</div>
		</form>
		<script>window.gruppaListing = <?php echo wp_json_encode( array(
			'typeFields' => Gruppa_Admin_Form_Actions::type_fields( true ),
			'system'     => Gruppa_Listing::SYSTEM,
			'groups'     => self::type_groups(),
		) ); ?>;</script>
		<?php
	}

	/** Groups of Data types with structure "groups": [ type slug => [ [ id, name ] ] ]. */
	private static function type_groups() {
		$out = array();
		foreach ( Gruppa_Types::all() as $t ) {
			if ( 'data' === $t->kind && 'groups' === $t->structure ) {
				foreach ( $t->groups as $g ) {
					$out[ $t->slug ][] = array( 'id' => $g['id'], 'name' => $g['name'] );
				}
			}
		}
		return $out;
	}
}
