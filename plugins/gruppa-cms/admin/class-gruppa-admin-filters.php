<?php
defined( 'ABSPATH' ) || exit;

/**
 * Search Filters admin: groups of filters (cards like form actions).
 */
class Gruppa_Admin_Filters {

	const PAGE = 'gruppa-filters';
	const CAP  = 'manage_options';

	public function __construct() {
		add_action( 'admin_post_gruppa_filters_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_filters_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_gruppa_filters_delete', array( $this, 'handle_delete' ) );
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
		check_admin_referer( 'gruppa_filters_save' );
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$res = Gruppa_Filters::save( wp_unslash( $_POST ), $id );
		wp_safe_redirect( self::url( is_wp_error( $res )
			? array( 'view' => 'edit', 'id' => $id, 'msg' => $res->get_error_code() )
			: array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' ) ) );
		exit;
	}

	public function handle_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_filters_status_' . $id );
		$g = Gruppa_Filters::get( $id );
		if ( $g ) {
			Gruppa_Filters::set_status( $id, 'publish' === $g->status ? 'draft' : 'publish' );
		}
		wp_safe_redirect( self::url( array( 'msg' => 'status' ) ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_filters_delete_' . $id );
		Gruppa_Filters::delete( $id );
		wp_safe_redirect( self::url( array( 'msg' => 'deleted' ) ) );
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
		'edit' === $view ? $this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 ) : $this->render_list(); // phpcs:ignore
		echo '</div>';
	}

	private function render_list() {
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$rows   = Gruppa_Filters::all( $status );
		$types  = array();
		foreach ( Gruppa_Types::all() as $t ) {
			$types[ $t->slug ] = $t->name;
		}
		?>
		<h1 class="wp-heading-inline">Filters</h1>
		<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add New</a>
		<hr class="wp-header-end">
		<?php Gruppa_Admin::status_filter( self::url(), $status, Gruppa_Filters::counts() ); ?>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead>
				<tr>
					<th class="gruppa-col-handle"></th>
					<th class="gruppa-col-id">ID</th>
					<th>Name</th>
					<th>Type</th>
					<th>Filters</th>
					<th class="gruppa-col-shortcode">Shortcode</th>
					<th class="gruppa-col-actions">Actions</th>
				</tr>
			</thead>
			<tbody class="<?php echo '' === $status ? 'gruppa-sortable' : ''; ?>" data-key="_filters" data-offset="0">
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="7">No filter groups yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $g ) :
				$edit  = self::url( array( 'view' => 'edit', 'id' => $g->id ) );
				$names = array();
				foreach ( $g->settings['filters'] as $f ) {
					$names[] = '' !== $f['label'] ? $f['label'] : Gruppa_Filters::TYPES[ $f['type'] ];
				}
				?>
				<tr data-id="<?php echo (int) $g->id; ?>">
					<td class="gruppa-col-handle"><?php if ( '' === $status ) : ?><span class="dashicons dashicons-menu gruppa-handle"></span><?php endif; ?></td>
					<td><?php echo (int) $g->id; ?></td>
					<td><strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $g->name ); ?></a></strong><?php echo 'publish' === $g->status ? '' : ' <span class="gruppa-count">Draft</span>'; ?></td>
					<td><?php echo esc_html( isset( $types[ $g->type_slug ] ) ? $types[ $g->type_slug ] : $g->type_slug . ' (missing)' ); ?></td>
					<td><?php echo esc_html( $names ? implode( ', ', $names ) : '—' ); ?></td>
					<td><input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_filters group="' . $g->slug . '"]' ); ?>" onclick="this.select()"></td>
					<td>
						<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
						<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'filters', $g->id ) ); ?>">Duplicate</a> |
						<a href="<?php echo esc_url( $this->action_url( 'gruppa_filters_status', $g->id ) ); ?>"><?php echo 'publish' === $g->status ? 'Draft' : 'Publish'; ?></a> |
						<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_filters_delete', $g->id ) ); ?>" onclick="return confirm('Delete this filter group?')">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** One filter card. */
	public static function card( $i, $f, $open = false ) {
		$f = array_merge( Gruppa_Filters::filter_defaults( $f['type'] ), $f );
		$n = 'settings[filters][' . $i . ']';
		$t = $f['type'];
		$text = function ( $key, $label, $ph = '', $help = '' ) use ( $n, $f ) {
			printf(
				'<tr><th>%s</th><td><input type="text" name="%s" value="%s" placeholder="%s">%s</td></tr>',
				esc_html( $label ), esc_attr( $n . '[' . $key . ']' ), esc_attr( $f[ $key ] ), esc_attr( $ph ),
				$help ? '<p class="description">' . wp_kses_post( $help ) . '</p>' : ''
			);
		};
		?>
		<div class="gruppa-field gfa-card gfl-card<?php echo $open ? ' is-open' : ''; ?>" data-index="<?php echo esc_attr( $i ); ?>" data-type="<?php echo esc_attr( $t ); ?>">
			<div class="gruppa-field-head">
				<span class="dashicons dashicons-menu gruppa-handle"></span>
				<span class="gruppa-field-title"><?php echo esc_html( '' !== $f['label'] ? $f['label'] : Gruppa_Filters::TYPES[ $t ] ); ?></span>
				<span class="gruppa-field-key gfa-summary"><?php echo esc_html( 'search' === $t ? '?' . ( $f['param'] ? $f['param'] : 'q' ) . '=' : ( $f['field'] ? $f['field'] : '' ) ); ?></span>
				<span class="gruppa-field-type"><?php echo esc_html( Gruppa_Filters::TYPES[ $t ] ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2 gruppa-toggle"></span>
			</div>
			<div class="gruppa-field-body">
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[type]" value="<?php echo esc_attr( $t ); ?>">
				<table class="form-table" role="presentation">
					<?php
					$text( 'label', 'Label', Gruppa_Filters::TYPES[ $t ] );
					if ( 'search' === $t ) {
						echo '<tr><th>Search in</th><td><div class="gfl-fields" data-name="' . esc_attr( $n . '[fields][]' ) . '" data-current="' . esc_attr( wp_json_encode( array_values( (array) $f['fields'] ) ) ) . '"></div></td></tr>';
						$text( 'placeholder', 'Placeholder', 'Hľadať…' );
					} else {
						printf(
							'<tr><th>Field</th><td><select class="gfl-field" name="%s" data-kinds="%s" data-current="%s"><option value="%s" selected>%s</option></select></td></tr>',
							esc_attr( $n . '[field]' ), esc_attr( implode( ',', Gruppa_Filters::field_kinds( $t ) ) ), esc_attr( $f['field'] ), esc_attr( $f['field'] ), esc_html( '' === $f['field'] ? '— select field —' : $f['field'] )
						);
					}
					if ( 'select' === $t ) {
						$text( 'all_label', 'First option', 'Všetky' );
					}
					if ( 'range' === $t || 'date' === $t ) {
						$text( 'from_label', '"From" text', 'Od' );
						$text( 'to_label', '"To" text', 'Do' );
					}
					if ( 'range' === $t ) {
						$text( 'step', 'Step', 'any', 'E.g. 1 or 0.5. Lowest / highest values are filled in automatically.' );
					}
					$text( 'param', 'URL parameter', 'search' === $t ? 'q' : 'automatic (field key)', 'Shows in the address, e.g. <code>?kategoria=zimna</code>. Range / date add <code>_from</code> / <code>_to</code>.' );
					?>
				</table>
				<p><button type="button" class="button-link button-link-delete gfa-remove">Remove</button></p>
			</div>
		</div>
		<?php
	}

	private function render_edit( $id ) {
		$g = $id ? Gruppa_Filters::get( $id ) : null;
		if ( $id && ! $g ) {
			Gruppa_Admin::not_found( $this->url(), 'This filter group' );
			return;
		}
		$s     = $g ? $g->settings : Gruppa_Filters::defaults();
		$types = Gruppa_Types::all();
		$req   = ' <span class="gruppa-req">*</span>';
		?>
		<h1 class="wp-heading-inline"><?php echo $g ? esc_html( $g->name ) : 'Add Filter Group'; ?></h1>
		<a href="<?php echo esc_url( self::url() ); ?>" class="page-title-action">Back</a>
		<?php if ( $g ) : ?>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add New</a>
			<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'filters', $g->id ) ); ?>" class="page-title-action">Duplicate</a>
		<?php endif; ?>
		<hr class="wp-header-end">


		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gfl-form">
			<input type="hidden" name="action" value="gruppa_filters_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gruppa_filters_save' ); ?>

			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-edit-layout">
			<div class="gruppa-edit-main">
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th><label for="g-name">Name<?php echo $req; // phpcs:ignore ?></label></th><td><input type="text" id="g-name" name="name" value="<?php echo esc_attr( $g ? $g->name : '' ); ?>" required></td></tr>
					<tr>
						<th><label for="g-slug">Slug<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<?php if ( $g ) : ?>
								<input type="text" id="g-slug" value="<?php echo esc_attr( $g->slug ); ?>" readonly>
							<?php else : ?>
								<input type="text" id="g-slug" name="slug" pattern="[a-z0-9_]+" maxlength="40" required>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="gfl-type">Content type<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<select name="type_slug" id="gfl-type" required>
								<option value="">— select —</option>
								<?php foreach ( $types as $t ) : ?>
									<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $g ? $g->type_slug : '', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Same type as the listing the filters control.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gfl-apply">Apply</label></th>
						<td>
							<select name="settings[apply]" id="gfl-apply">
								<option value="instant" <?php selected( $s['apply'], 'instant' ); ?>>Instantly on change</option>
								<option value="button" <?php selected( $s['apply'], 'button' ); ?>>With a button</option>
							</select>
						</td>
					</tr>
					<tr class="gfl-button-row">
						<th><label for="gfl-button">Button text</label></th>
						<td><input type="text" id="gfl-button" name="settings[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>"></td>
					</tr>
					<tr>
						<th>Reset</th>
						<td>
							<input type="text" name="settings[reset_text]" value="<?php echo esc_attr( $s['reset_text'] ); ?>" placeholder="Zrušiť filtre" class="gfl-reset-text" aria-label="Reset link text">
							<label class="gfl-reset-check"><input type="checkbox" name="settings[reset]" value="1" <?php checked( $s['reset'] ); ?>> Show a reset link</label>
						</td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Filters</h2>
			<div id="gfa-list">
				<?php foreach ( $s['filters'] as $i => $f ) {
					self::card( $i, $f );
				} ?>
			</div>
			<div class="gfa-add">
				<select id="gfl-add-type">
					<?php foreach ( Gruppa_Filters::TYPES as $k => $l ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="gfl-add">Add Filter</button>
			</div>

			<?php if ( $g ) : ?>
			<h2 class="gruppa-fields-title">Frontend</h2>
			<div class="gruppa-card gruppa-form-embed">
				<p>
					<input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_filters group="' . $g->slug . '"]' ); ?>" onclick="this.select()">
					<span class="gruppa-muted">Elementor: widget <em>Gruppa Search</em> + in widget <em>Gruppa Listing</em> set <em>Filter group</em> to this group.</span>
				</p>
			</div>
		<?php endif; ?>
			</div>

			<?php $author = get_userdata( $g ? (int) $g->author : get_current_user_id() ); $fmt = 'Y/m/d H:i'; ?>
			<aside class="gruppa-edit-side">
				<div class="gruppa-card gruppa-side-box">
					<p class="gruppa-side-row">
						<label for="g-status" class="screen-reader-text">Status</label>
						<select name="status" id="g-status">
							<option value="publish" <?php selected( $g ? $g->status : 'publish', 'publish' ); ?>>Published</option>
							<option value="draft" <?php selected( $g ? $g->status : '', 'draft' ); ?>>Draft</option>
						</select>
					</p>
					<dl class="gruppa-side-meta">
						<?php if ( $g ) : ?>
							<dt>ID</dt><dd><?php echo (int) $g->id; ?></dd>
						<?php endif; ?>
						<dt>Author</dt><dd><?php echo esc_html( $author ? $author->display_name : '—' ); ?></dd>
						<?php if ( $g ) : ?>
							<dt>Created</dt><dd><?php echo esc_html( ! empty( $g->created ) ? mysql2date( $fmt, $g->created ) : '—' ); ?></dd>
							<dt>Modified</dt><dd><?php echo esc_html( ! empty( $g->modified ) ? mysql2date( $fmt, $g->modified ) : '—' ); ?></dd>
						<?php endif; ?>
					</dl>
					<button type="submit" class="button button-primary gruppa-side-save"><?php echo $g ? 'Update' : 'Publish'; ?></button>
					<?php if ( $g ) : ?>
						<p class="gruppa-side-links">
							<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'filters', $g->id ) ); ?>">Duplicate</a>
							<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_filters_delete', $g->id ) ); ?>" onclick="return confirm('Delete this filter group?')">Delete</a>
						</p>
					<?php endif; ?>
				</div>
			</aside>
			</div>
		</form>
		<?php foreach ( array_keys( Gruppa_Filters::TYPES ) as $t ) : ?>
			<script type="text/html" id="tmpl-gfl-<?php echo esc_attr( $t ); ?>"><?php self::card( '__i__', Gruppa_Filters::filter_defaults( $t ), true ); ?></script>
		<?php endforeach; ?>
		<script>window.gruppaFilters = <?php echo wp_json_encode( array( 'typeFields' => Gruppa_Admin_Form_Actions::type_fields( true ) ) ); ?>;</script>
		<?php
	}
}
