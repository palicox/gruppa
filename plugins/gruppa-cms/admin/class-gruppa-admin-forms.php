<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-gruppa-admin-form-actions.php';

class Gruppa_Admin_Forms {

	const CAP  = 'manage_options';
	const PAGE = 'gruppa-forms';

	public function __construct() {
		add_action( 'admin_post_gruppa_form_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_form_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_gruppa_form_delete', array( $this, 'handle_delete' ) );
	}

	private function url( $args = array() ) {
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
		check_admin_referer( 'gruppa_form_save' );
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$res = Gruppa_Forms::save( wp_unslash( $_POST ), $id );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( $this->url( array( 'view' => 'edit', 'id' => $id, 'msg' => $res->get_error_code() ) ) );
			exit;
		}
		wp_safe_redirect( $this->url( array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' ) ) );
		exit;
	}

	public function handle_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_form_status_' . $id );
		$form = Gruppa_Forms::get( $id );
		if ( $form ) {
			Gruppa_Forms::set_status( $id, 'publish' === $form->status ? 'draft' : 'publish' );
		}
		wp_safe_redirect( $this->url( array( 'msg' => 'status' ) ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_form_delete_' . $id );
		Gruppa_Forms::delete( $id );
		wp_safe_redirect( $this->url( array( 'msg' => 'deleted' ) ) );
		exit;
	}

	/* ---------- Views ---------- */

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : '';
		echo '<div class="wrap gruppa-wrap">';
		Gruppa_Admin::notice();
		if ( 'edit' === $view ) {
			$this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
		} else {
			$this->render_list();
		}
		echo '</div>';
	}

	private function render_list() {
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : '';
		$forms  = Gruppa_Forms::all( $status );
		$types  = array();
		foreach ( Gruppa_Types::all() as $t ) {
			$types[ $t->slug ] = $t->name;
		}
		?>
		<h1 class="wp-heading-inline">Forms</h1>
		<a href="<?php echo esc_url( $this->url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Form</a>
		<hr class="wp-header-end">

		<?php Gruppa_Admin::status_filter( $this->url(), $status, Gruppa_Forms::counts() ); ?>

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
			<tbody class="<?php echo '' === $status ? 'gruppa-sortable' : ''; ?>" data-key="_forms" data-offset="0">
			<?php if ( ! $forms ) : ?>
				<tr><td colspan="7">No forms found.</td></tr>
			<?php endif; ?>
			<?php foreach ( $forms as $f ) :
				$edit   = $this->url( array( 'view' => 'edit', 'id' => $f->id ) );
				?>
				<tr data-id="<?php echo (int) $f->id; ?>">
					<td class="gruppa-col-handle"><?php if ( '' === $status ) : ?><span class="dashicons dashicons-menu gruppa-handle"></span><?php endif; ?></td>
					<td><?php echo (int) $f->id; ?></td>
					<td><strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $f->name ); ?></a></strong></td>
					<td><?php echo esc_html( isset( $types[ $f->type_slug ] ) ? $types[ $f->type_slug ] : $f->type_slug . ' (missing)' ); ?></td>
					<td><input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_form form="' . $f->slug . '"]' ); ?>" onclick="this.select()"></td>
					<td><?php echo esc_html( $f->modified ? mysql2date( 'Y/m/d H:i', $f->modified ) : '—' ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
						<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'form', $f->id ) ); ?>">Duplicate</a> |
						<a href="<?php echo esc_url( $this->action_url( 'gruppa_form_status', $f->id ) ); ?>"><?php echo 'publish' === $f->status ? 'Draft' : 'Publish'; ?></a> |
						<a class="gruppa-delete" href="<?php echo esc_url( $this->action_url( 'gruppa_form_delete', $f->id ) ); ?>">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_edit( $id ) {
		$form = $id ? Gruppa_Forms::get( $id ) : null;
		if ( $id && ! $form ) {
			Gruppa_Admin::not_found( $this->url(), 'This form' );
			return;
		}
		$is_new = ! $form;
		$s      = $form ? $form->settings : Gruppa_Forms::defaults();
		$types  = Gruppa_Types::all();
		$pre    = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		$cur    = $form ? $form->type_slug : $pre;
		$author = $form ? get_userdata( $form->author ) : wp_get_current_user();

		// Element definitions per type for the builder.
		$defs = array();
		foreach ( $types as $t ) {
			$defs[ $t->slug ] = array();
			foreach ( Gruppa_Forms::available( $t ) as $k => $a ) {
				$required = 'title' === $k;
				foreach ( $t->fields as $f ) {
					if ( $f['key'] === $k ) {
						$required = (bool) $f['required'];
					}
				}
				$defs[ $t->slug ][ $k ] = array(
					'label'    => $a['label'],
					'type'     => $a['type'],
					'system'   => $a['system'],
					'note'     => isset( $a['note'] ) ? $a['note'] : '',
					'required' => $required,
					'displays' => isset( $a['field'] ) ? (object) Gruppa_Forms::displays( $a['field'] ) : (object) array(),
				);
			}
		}

		$row = function ( $label, $html, $for = '', $class = '' ) {
			printf(
				'<tr%s><th>%s</th><td>%s</td></tr>',
				$class ? ' class="' . esc_attr( $class ) . '"' : '',
				$for ? '<label for="' . esc_attr( $for ) . '">' . $label . '</label>' : $label, // phpcs:ignore
				$html // phpcs:ignore
			);
		};
		$sel = function ( $name, $id, $options, $current ) {
			$h = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '">';
			foreach ( $options as $k => $l ) {
				$h .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $current, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			return $h . '</select>';
		};
		$req = ' <span class="gruppa-req">*</span>';
		?>
		<h1 class="wp-heading-inline"><?php echo $is_new ? 'Add Form' : esc_html( $form->name ); ?></h1>
		<a href="<?php echo esc_url( $this->url() ); ?>" class="page-title-action">Back</a>
		<?php if ( $form ) : ?>
			<a href="<?php echo esc_url( $this->url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Form</a>
			<a href="<?php echo esc_url( Gruppa_Admin::duplicate_url( 'form', $form->id ) ); ?>" class="page-title-action">Duplicate</a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<?php if ( $form ) :
			$ftype  = Gruppa_Types::get_by_slug( $form->type_slug );
			$access = $ftype ? Gruppa_Access::get( $ftype ) : null;
			$mode   = $form->settings['mode'];
			?>
			<div class="gruppa-card gruppa-form-embed">
				<p><strong>Frontend:</strong>
					<input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_form form="' . $form->slug . '"]' ); ?>" onclick="this.select()">
					<span class="gruppa-muted">Shortcode (Gutenberg: Shortcode block) · Elementor: widget <em>Gruppa Form</em></span>
				</p>
				<?php if ( 'publish' !== $form->status ) : ?>
					<p class="gruppa-muted">The form is a Draft — publish it to show it on the frontend.</p>
				<?php endif; ?>
				<?php if ( $ftype ) :
					$f_opts  = Gruppa_Forms::access_options( $mode, $ftype );
					$f_rule  = Gruppa_Forms::rule( $form, $ftype );
					$f_label = isset( Gruppa_Access::options( $mode )[ $f_rule ] ) ? Gruppa_Access::options( $mode )[ $f_rule ] : $f_rule;
					$f_guest = Gruppa_Forms::can( $form, $ftype, null, 0 );
					?>
					<p class="gruppa-muted gruppa-form-embed-who">Who can submit: <strong><?php echo esc_html( 'public' === $f_rule ? 'Everyone (visitors too)' : $f_label ); ?></strong>. Administrators always can.</p>
					<?php if ( ! $f_guest && 'create' === $mode ) : ?>
						<p class="gruppa-form-embed-warn">Visitors who are not logged in will not see this form — they get “Please log in to use this form.” For a public form (contact, reservation) set <em>Who can submit</em> to <em>Everyone</em>.</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gruppa-form-form">
			<input type="hidden" name="action" value="gruppa_form_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<input type="hidden" name="settings[layout]" id="gfb-layout-input" value="">
			<?php wp_nonce_field( 'gruppa_form_save' ); ?>

			<div class="gruppa-card">
			<table class="form-table gruppa-type-table" role="presentation">
				<?php
				$row( 'Name' . $req, '<input type="text" id="g-name" name="name" value="' . esc_attr( $form ? $form->name : '' ) . '" required>', 'g-name' );
				$row(
					'Slug' . $req,
					$is_new
						? '<input type="text" id="g-slug" name="slug" pattern="[a-z0-9_]+" maxlength="40" required>'
						: '<input type="text" id="g-slug" value="' . esc_attr( $form->slug ) . '" readonly>',
					'g-slug'
				);

				$topts = array( '' => '— select —' );
				foreach ( $types as $t ) {
					$topts[ $t->slug ] = $t->name . ( 'publish' === $t->status ? '' : ' (draft)' );
				}
				$row( 'Type' . $req, str_replace( '<select ', '<select required ', $sel( 'type_slug', 'gfb-type', $topts, $cur ) ), 'gfb-type' );
				$row( 'Mode', $sel( 'settings[mode]', 'gfb-mode', Gruppa_Forms::modes(), $s['mode'] ), 'gfb-mode' );
				$acc_type = $cur ? Gruppa_Types::get_by_slug( $cur ) : null;
				$acc_cur  = '' !== (string) $s['access'] ? $s['access'] : ( $acc_type ? Gruppa_Forms::inherited_rule( $acc_type, $s['mode'] ) : 'role:administrator' );
				$acc_html = '<select name="settings[access]" id="gfb-access">';
				foreach ( array( 'create', 'update', 'delete' ) as $acc_mode ) {
					foreach ( Gruppa_Forms::access_options( $acc_mode, $acc_type ) as $k => $l ) {
						$acc_html .= sprintf(
							'<option value="%s" data-mode="%s" %s%s>%s</option>',
							esc_attr( $k ), esc_attr( $acc_mode ),
							selected( $acc_mode === $s['mode'] && $k === $acc_cur, true, false ),
							$acc_mode === $s['mode'] ? '' : ' hidden disabled', esc_html( $l )
						);
					}
				}
				$acc_html .= '</select><p class="description">Administrators can always use the form.</p>';
				$row( 'Who can submit', $acc_html, 'gfb-access' );
				$row( 'Item', '<div class="gruppa-inline">' . $sel( 'settings[item_source]', 'gfb-item-source', array( 'param' => 'URL parameter', 'current' => 'Current item (single template)', 'user' => 'Item of current user' ), $s['item_source'] )
					. '<input type="text" name="settings[item_param]" id="gfb-item-param" value="' . esc_attr( $s['item_param'] ) . '" placeholder="item"></div>', 'gfb-item-source', 'gfb-update-row' );
				$row( 'Status', $sel( 'status', 'g-status', array( 'publish' => 'Published', 'draft' => 'Draft' ), $form ? $form->status : 'publish' ), 'g-status' );
				?>
				<tr class="gruppa-meta gruppa-meta-first"><th>Author</th><td><?php echo esc_html( $author ? $author->display_name . ' (ID ' . $author->ID . ')' : '—' ); ?></td></tr>
				<tr class="gruppa-meta"><th>Created</th><td><?php echo esc_html( $form && $form->created ? mysql2date( 'Y/m/d H:i', $form->created ) : '—' ); ?></td></tr>
				<tr class="gruppa-meta"><th>Modified</th><td><?php echo esc_html( $form && $form->modified ? mysql2date( 'Y/m/d H:i', $form->modified ) : '—' ); ?></td></tr>
				<tr class="gruppa-meta"><th>Form ID</th><td><?php echo $form ? (int) $form->id : '—'; ?></td></tr>
			</table>
			</div>

			<h2 class="gruppa-fields-title gfb-layout-title">Layout</h2>
			<div class="gfb">
				<div class="gfb-toolbar">
					<select id="gfb-add-select"></select>
					<button type="button" class="button" id="gfb-add">Add Field</button>
					<button type="button" class="button" id="gfb-add-all">Add All</button>
				</div>
				<div class="gfb-canvas" id="gfb-canvas"></div>
				<div class="gfb-inspector" id="gfb-inspector" hidden>
					<table class="form-table gruppa-type-table" role="presentation">
						<tr><th>Field</th><td><code id="gfi-key"></code> <span id="gfi-type" class="gruppa-muted"></span></td></tr>
						<tr><th><label for="gfi-width">Width</label></th><td>
							<select id="gfi-width">
								<?php foreach ( Gruppa_Forms::WIDTHS as $w ) : ?>
									<option value="<?php echo (int) $w; ?>"><?php echo (int) $w; ?> %</option>
								<?php endforeach; ?>
							</select>
						</td></tr>
						<tr id="gfi-display-row"><th><label for="gfi-display">Display as</label></th><td><select id="gfi-display"></select></td></tr>
						<tr><th><label for="gfi-label">Label</label></th><td><input type="text" id="gfi-label"></td></tr>
						<tr><th><label for="gfi-placeholder">Placeholder</label></th><td><input type="text" id="gfi-placeholder"></td></tr>
						<tr><th><label for="gfi-description">Description</label></th><td><input type="text" id="gfi-description"></td></tr>
						<tr><th><label for="gfi-hidden">Hidden</label></th><td>
							<select id="gfi-hidden"><option value="0">False</option><option value="1">True</option></select>
						</td></tr>
						<tr><th><label for="gfi-value" id="gfi-value-label">Default value</label></th><td><input type="text" id="gfi-value"><p class="description">Hidden: the value is always saved. You can use fields of this form, e.g. <code>{meno_firma} – {email}</code>. Hidden Title left empty = form name + date.</p></td></tr>
					</table>
					<p><button type="button" class="button-link button-link-delete" id="gfi-remove">Remove</button></p>
				</div>
			</div>

			<h2 class="gruppa-fields-title">After Submit</h2>
			<div class="gruppa-card">
			<table class="form-table gruppa-type-table" role="presentation">
				<?php
				$row( 'Submit text', '<input type="text" id="gfb-submit" name="settings[submit_text]" value="' . esc_attr( $s['submit_text'] ) . '">', 'gfb-submit' );
				$row( 'Submit align', $sel( 'settings[submit_align]', 'gfb-align', array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right', 'stretch' => 'Full width' ), $s['submit_align'] ), 'gfb-align' );
				$row( 'Success message', '<textarea id="gfb-success" name="settings[success_message]" rows="3">' . esc_textarea( $s['success_message'] ) . '</textarea>', 'gfb-success' );
				$row( 'After success', $sel( 'settings[after_success]', 'gfb-after', array( 'reset' => 'Reset form', 'hide' => 'Hide form', 'keep' => 'Keep values' ), $s['after_success'] ), 'gfb-after' );
				$row( 'Redirect URL', '<input type="text" id="gfb-redirect" name="settings[redirect]" value="' . esc_attr( $s['redirect'] ) . '" placeholder="https://example.com/thanks/?id={id}">', 'gfb-redirect' );
				$row( 'New items status', $sel( 'settings[new_status]', 'gfb-new-status', Gruppa_Forms::new_statuses(), $s['new_status'] ), 'gfb-new-status', 'gfb-create-row' );
				$row( 'Confirm text', '<input type="text" id="gfb-confirm" name="settings[confirm_text]" value="' . esc_attr( $s['confirm_text'] ) . '" placeholder="Are you sure?">', 'gfb-confirm', 'gfb-delete-row' );
				?>
			</table>
			</div>

			<?php Gruppa_Admin_Form_Actions::render( $s ); ?>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_new ? 'Publish' : 'Update'; ?></button>
			</p>
		</form>
		<script>
			window.gruppaFormBuilder = <?php echo wp_json_encode( array(
				'defs'       => $defs,
				'typeFields' => Gruppa_Admin_Form_Actions::type_fields( true ),
				'layout'     => $s['layout'],
				'widths' => Gruppa_Forms::WIDTHS,
			) ); ?>;
		</script>
		<?php
	}
}
