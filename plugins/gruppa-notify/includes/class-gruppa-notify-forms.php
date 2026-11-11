<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Notify — notifications from JetFormBuilder and Contact Form 7 (1.4.0).
 *
 * Notify → Forms: one rule per form = who gets the push (users, roles), title,
 * message with {field} placeholders and the link opened on click. Works without
 * Gruppa CMS. Gruppa CMS forms use the Form Builder action "Send notification".
 */
class Gruppa_Notify_Forms {

	const OPT = 'gruppa_notify_forms';

	public static function init() {
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'on_cf7' ), 20, 1 );
		add_action( 'jet-form-builder/form-handler/after-send', array( __CLASS__, 'on_jfb' ), 20, 2 );

		if ( is_admin() ) {
			add_action( 'admin_post_gruppa_notify_form_save', array( __CLASS__, 'admin_save' ) );
			add_action( 'admin_post_gruppa_notify_form_delete', array( __CLASS__, 'admin_delete' ) );
		}
	}

	/* ======================================================
	 * Form plugins
	 * ==================================================== */

	public static function has_jfb() {
		return function_exists( 'jet_fb_handler' ) || post_type_exists( 'jet-form-builder' );
	}

	public static function has_cf7() {
		return defined( 'WPCF7_VERSION' );
	}

	public static function available() {
		return self::has_jfb() || self::has_cf7();
	}

	/** [ 'JetFormBuilder' => [ 'jfb:12' => 'Contact' ], 'Contact Form 7' => [ 'cf7:5' => '…' ] ] */
	public static function forms() {
		$out = array();
		$map = array(
			'JetFormBuilder' => array( 'jfb', 'jet-form-builder', self::has_jfb() ),
			'Contact Form 7' => array( 'cf7', 'wpcf7_contact_form', self::has_cf7() ),
		);
		foreach ( $map as $group => $m ) {
			if ( ! $m[2] ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $m[1],
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 200,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			$out[ $group ] = array();
			foreach ( $posts as $p ) {
				$out[ $group ][ $m[0] . ':' . $p->ID ] = '' !== $p->post_title ? $p->post_title : '#' . $p->ID;
			}
		}
		return $out;
	}

	public static function form_name( $value ) {
		list( $kind, $id ) = self::parse( $value );
		$p = $id ? get_post( $id ) : null;
		return $p ? $p->post_title : $value;
	}

	public static function parse( $value ) {
		$parts = explode( ':', (string) $value, 2 );
		return array( sanitize_key( $parts[0] ), isset( $parts[1] ) ? (int) $parts[1] : 0 );
	}

	/** Field names of a form (for the placeholder hint). */
	public static function fields( $value ) {
		list( $kind, $id ) = self::parse( $value );
		$names = array();
		if ( 'cf7' === $kind && class_exists( 'WPCF7_ContactForm' ) ) {
			$form = WPCF7_ContactForm::get_instance( $id );
			if ( $form ) {
				foreach ( $form->scan_form_tags() as $tag ) {
					if ( ! empty( $tag->name ) ) {
						$names[] = $tag->name;
					}
				}
			}
		} elseif ( 'jfb' === $kind ) {
			$p = get_post( $id );
			if ( $p ) {
				$walk = function ( $blocks ) use ( &$walk, &$names ) {
					foreach ( $blocks as $b ) {
						if ( ! empty( $b['attrs']['name'] ) && 0 === strpos( (string) $b['blockName'], 'jet-forms/' ) ) {
							$names[] = $b['attrs']['name'];
						}
						if ( ! empty( $b['innerBlocks'] ) ) {
							$walk( $b['innerBlocks'] );
						}
					}
				};
				$walk( parse_blocks( $p->post_content ) );
			}
		}
		return array_values( array_unique( array_map( 'sanitize_text_field', $names ) ) );
	}

	/* ======================================================
	 * Rules
	 * ==================================================== */

	public static function rules() {
		$r = get_option( self::OPT, array() );
		return is_array( $r ) ? $r : array();
	}

	public static function rule( $id ) {
		foreach ( self::rules() as $r ) {
			if ( (int) $r['id'] === (int) $id ) {
				return $r;
			}
		}
		return null;
	}

	public static function defaults() {
		return array(
			'id'      => 0,
			'enabled' => 1,
			'form'    => '',
			'users'   => array(),
			'roles'   => array(),
			'title'   => '',
			'body'    => 'New submission: {form}',
			'url'     => '',
		);
	}

	public static function admin_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_notify_form_save' );
		$in    = isset( $_POST['rule'] ) ? wp_unslash( (array) $_POST['rule'] ) : array(); // phpcs:ignore
		$rules = self::rules();
		$id    = isset( $in['id'] ) ? (int) $in['id'] : 0;
		$roles = array_keys( wp_roles()->roles );

		$rule = array(
			'id'      => $id ? $id : ( $rules ? max( wp_list_pluck( $rules, 'id' ) ) + 1 : 1 ),
			'enabled' => empty( $in['enabled'] ) ? 0 : 1,
			'form'    => preg_match( '/^(cf7|jfb):\d+$/', isset( $in['form'] ) ? $in['form'] : '' ) ? $in['form'] : '',
			'users'   => array_values( array_unique( array_filter( array_map( 'intval', isset( $in['users'] ) ? (array) $in['users'] : array() ) ) ) ),
			'roles'   => array_values( array_intersect( array_map( 'sanitize_key', isset( $in['roles'] ) ? (array) $in['roles'] : array() ), $roles ) ),
			'title'   => sanitize_text_field( isset( $in['title'] ) ? $in['title'] : '' ),
			'body'    => sanitize_textarea_field( isset( $in['body'] ) ? $in['body'] : '' ),
			'url'     => sanitize_text_field( isset( $in['url'] ) ? $in['url'] : '' ),
		);

		$found = false;
		foreach ( $rules as $i => $r ) {
			if ( (int) $r['id'] === $rule['id'] ) {
				$rules[ $i ] = $rule;
				$found       = true;
			}
		}
		if ( ! $found ) {
			$rules[] = $rule;
		}
		update_option( self::OPT, array_values( $rules ), false );
		wp_safe_redirect( Gruppa_Notify_Module::page_url( array( 'tab' => 'forms', 'gn_msg' => rawurlencode( 'Saved.' ) ) ) );
		exit;
	}

	public static function admin_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_notify_form_delete' );
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		update_option(
			self::OPT,
			array_values(
				array_filter(
					self::rules(),
					function ( $r ) use ( $id ) {
						return (int) $r['id'] !== $id;
					}
				)
			),
			false
		);
		wp_safe_redirect( Gruppa_Notify_Module::page_url( array( 'tab' => 'forms', 'gn_msg' => rawurlencode( 'Deleted.' ) ) ) );
		exit;
	}

	/* ======================================================
	 * Submissions
	 * ==================================================== */

	/** Contact Form 7: after validation and spam check, before the mail. */
	public static function on_cf7( $contact_form ) {
		if ( ! class_exists( 'WPCF7_Submission' ) || ! is_object( $contact_form ) ) {
			return;
		}
		$sub  = WPCF7_Submission::get_instance();
		$data = $sub ? (array) $sub->get_posted_data() : array();
		self::handle( 'cf7:' . (int) $contact_form->id(), $data, $contact_form->title() );
	}

	/** JetFormBuilder: after the form actions, only for a successful submission. */
	public static function on_jfb( $handler, $is_success = true ) {
		if ( ! $is_success || ! is_object( $handler ) ) {
			return;
		}
		$form_id = method_exists( $handler, 'get_form_id' ) ? (int) $handler->get_form_id() : ( isset( $handler->form_id ) ? (int) $handler->form_id : 0 );
		$data    = array();
		if ( function_exists( 'jet_fb_context' ) ) {
			$data = (array) jet_fb_context()->get_request();
		} elseif ( function_exists( 'jet_fb_action_handler' ) && isset( jet_fb_action_handler()->request_data ) ) {
			$data = (array) jet_fb_action_handler()->request_data;
		}
		self::handle( 'jfb:' . $form_id, $data, get_the_title( $form_id ) );
	}

	private static function handle( $form, array $data, $form_title ) {
		foreach ( self::rules() as $r ) {
			if ( empty( $r['enabled'] ) || $r['form'] !== $form ) {
				continue;
			}
			$ids = array_map( 'intval', (array) $r['users'] );
			if ( ! empty( $r['roles'] ) ) {
				$ids = array_merge( $ids, array_map( 'intval', get_users( array( 'role__in' => (array) $r['roles'], 'fields' => 'ID' ) ) ) );
			}
			$ids = array_values( array_filter( array_unique( array_filter( $ids ) ), 'get_userdata' ) );
			if ( ! $ids ) {
				continue;
			}
			list( $kind ) = self::parse( $form );
			$url          = trim( self::replace( $r['url'], $data, $form_title ) );
			Gruppa_Notify_Module::notify(
				$ids,
				array(
					'title'    => self::replace( $r['title'], $data, $form_title ),
					'body'     => self::replace( $r['body'], $data, $form_title ),
					'url'      => $url,
					'source'   => 'form',
					'label'    => ( 'cf7' === $kind ? 'CF7' : 'JetFormBuilder' ) . ': ' . $form_title,
					'ref_type' => $form,
					'ref_id'   => 0,
				)
			);
		}
	}

	/** {field}, {form}, {site_name}, {date}. Values are plain text. */
	public static function replace( $text, array $data, $form_title ) {
		return preg_replace_callback(
			'/\{([A-Za-z0-9_\-]+)\}/',
			function ( $m ) use ( $data, $form_title ) {
				switch ( $m[1] ) {
					case 'form':
						$v = $form_title;
						break;
					case 'site_name':
						$v = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
						break;
					case 'date':
						$v = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
						break;
					default:
						if ( ! array_key_exists( $m[1], $data ) ) {
							return $m[0];
						}
						$v = $data[ $m[1] ];
						$v = is_array( $v ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $v, 'is_scalar' ) ), 'strlen' ) ) : (string) $v;
				}
				return sanitize_text_field( $v );
			},
			(string) $text
		);
	}

	/* ======================================================
	 * Admin — Notify → Forms
	 * ==================================================== */

	public static function render() {
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore
		if ( 'edit' === $view ) {
			self::render_edit();
			return;
		}
		$forms = self::forms();
		$all   = array();
		foreach ( $forms as $list ) {
			$all += $list;
		}
		$rules = self::rules();
		?>
		<p class="gn-forms-intro gruppa-muted">A push after a form of <?php echo esc_html( implode( ' or ', array_filter( array( self::has_jfb() ? 'JetFormBuilder' : '', self::has_cf7() ? 'Contact Form 7' : '' ) ) ) ); ?> is submitted.<?php echo class_exists( 'Gruppa_Forms' ) ? ' Gruppa CMS forms: Form Builder → Actions → Send notification.' : ''; ?></p>
		<table class="wp-list-table widefat fixed gruppa-table gn-forms">
			<thead>
				<tr>
					<th>Form</th>
					<th>Recipients</th>
					<th>Message</th>
					<th class="gn-col-num">Active</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rules ) : ?>
				<tr><td colspan="4">No form yet. <a href="<?php echo esc_url( Gruppa_Notify_Module::page_url( array( 'tab' => 'forms', 'view' => 'edit' ) ) ); ?>">Add form</a></td></tr>
			<?php endif; ?>
			<?php foreach ( $rules as $r ) : ?>
				<?php
				list( $kind ) = self::parse( $r['form'] );
				$edit         = Gruppa_Notify_Module::page_url( array( 'tab' => 'forms', 'view' => 'edit', 'id' => $r['id'] ) );
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( isset( $all[ $r['form'] ] ) ? $all[ $r['form'] ] : self::form_name( $r['form'] ) . ( $r['form'] ? ' (missing)' : '' ) ); ?></a></strong>
						<span class="gn-forms-kind"><?php echo esc_html( 'cf7' === $kind ? 'Contact Form 7' : 'JetFormBuilder' ); ?></span>
						<div class="row-actions">
							<a href="<?php echo esc_url( $edit ); ?>">Edit</a> |
							<a class="gruppa-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gruppa_notify_form_delete&id=' . (int) $r['id'] ), 'gruppa_notify_form_delete' ) ); ?>" onclick="return confirm('Delete this form notification?');">Delete</a>
						</div>
					</td>
					<td><?php echo esc_html( self::recipients_text( $r ) ); ?></td>
					<td><?php echo esc_html( $r['body'] ); ?></td>
					<td><?php echo $r['enabled'] ? '<span class="gn-read">Yes</span>' : '<span class="gruppa-muted">No</span>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	public static function recipients_text( $r ) {
		$parts = array();
		foreach ( (array) $r['users'] as $id ) {
			$u       = get_userdata( $id );
			$parts[] = $u ? $u->display_name : '#' . $id;
		}
		foreach ( (array) $r['roles'] as $role ) {
			$parts[] = 'role ' . ( isset( wp_roles()->roles[ $role ] ) ? translate_user_role( wp_roles()->roles[ $role ]['name'] ) : $role );
		}
		return $parts ? implode( ', ', $parts ) : '—';
	}

	private static function render_edit() {
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		$r     = array_merge( self::defaults(), (array) ( $id ? self::rule( $id ) : array() ) );
		$forms = self::forms();
		$map   = array();
		foreach ( $forms as $list ) {
			foreach ( array_keys( $list ) as $value ) {
				$map[ $value ] = self::fields( $value );
			}
		}
		$counts = Gruppa_Notify_Module::device_counts();
		$chosen = array_map( 'intval', (array) $r['users'] );
		$ids    = array_unique( array_merge( array_keys( $counts ), $chosen ) );
		$users  = $ids ? get_users( array( 'include' => $ids, 'orderby' => 'display_name', 'fields' => array( 'ID', 'display_name' ) ) ) : array();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gruppa_notify_form_save">
			<input type="hidden" name="rule[id]" value="<?php echo (int) $r['id']; ?>">
			<?php wp_nonce_field( 'gruppa_notify_form_save' ); ?>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gn-form">Form <span class="gruppa-req">*</span></label></th>
						<td>
							<select name="rule[form]" id="gn-form" required>
								<option value="">— select form —</option>
								<?php foreach ( $forms as $group => $list ) : ?>
									<optgroup label="<?php echo esc_attr( $group ); ?>">
										<?php foreach ( $list as $value => $name ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $r['form'], $value ); ?>><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th>Users</th>
						<td>
							<?php if ( ! $users ) : ?>
								<p class="gn-fa-empty">No user has turned on notifications yet. Add the <code>[gruppa_notify_button]</code> shortcode to a page for logged-in users.</p>
							<?php else : ?>
								<div class="gn-fa-list">
									<?php foreach ( $users as $u ) : ?>
										<?php $c = isset( $counts[ $u->ID ] ) ? $counts[ $u->ID ] : 0; ?>
										<label><input type="checkbox" name="rule[users][]" value="<?php echo (int) $u->ID; ?>" <?php checked( in_array( (int) $u->ID, $chosen, true ) ); ?>> <?php echo esc_html( $u->display_name ); ?> <span class="gn-fa-count<?php echo $c ? '' : ' is-none'; ?>"><?php echo esc_html( $c ? sprintf( 1 === $c ? '%d device' : '%d devices', $c ) : 'no device' ); ?></span></label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<p class="description">Users who turned on notifications with the button on the website.</p>
						</td>
					</tr>
					<tr>
						<th>Roles</th>
						<td>
							<div class="gn-fa-list gn-fa-roles">
								<?php foreach ( wp_roles()->roles as $slug => $role ) : ?>
									<label><input type="checkbox" name="rule[roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $r['roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $role['name'] ) ); ?></label>
								<?php endforeach; ?>
							</div>
							<p class="description">Every user of these roles — also users added later.</p>
						</td>
					</tr>
					<tr class="gruppa-meta-first">
						<th><label for="gn-r-title">Title</label></th>
						<td>
							<input type="text" id="gn-r-title" name="rule[title]" value="<?php echo esc_attr( $r['title'] ); ?>" placeholder="<?php echo esc_attr( Gruppa_Notify_Module::get( 'title' ) ? Gruppa_Notify_Module::get( 'title' ) : get_bloginfo( 'name' ) ); ?>">
							<p class="description">Empty = Title from Settings.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gn-r-body">Message</label></th>
						<td>
							<textarea id="gn-r-body" name="rule[body]" rows="3"><?php echo esc_textarea( $r['body'] ); ?></textarea>
							<p class="description">Placeholders: {form}, {site_name}, {date} and the fields of the form<span id="gn-r-fields"></span></p>
						</td>
					</tr>
					<tr>
						<th><label for="gn-r-url">On click open</label></th>
						<td>
							<input type="text" id="gn-r-url" name="rule[url]" value="<?php echo esc_attr( $r['url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
							<p class="description">URL, may contain placeholders. Empty = home page.</p>
						</td>
					</tr>
					<tr>
						<th>Active</th>
						<td><label class="gruppa-switch"><input type="checkbox" name="rule[enabled]" value="1" <?php checked( $r['enabled'] ); ?>><span></span></label></td>
					</tr>
				</table>
			</div>
			<p class="submit">
				<button type="submit" class="button button-primary">Save</button>
				<a class="button" href="<?php echo esc_url( Gruppa_Notify_Module::page_url( array( 'tab' => 'forms' ) ) ); ?>">Back</a>
			</p>
		</form>
		<script>
		(function () {
			var map = <?php echo wp_json_encode( $map ); ?>, sel = document.getElementById('gn-form'), out = document.getElementById('gn-r-fields');
			function fill() {
				var f = map[sel.value] || [];
				out.textContent = f.length ? ': ' + f.map(function (k) { return '{' + k + '}'; }).join(' ') : '.';
			}
			sel.addEventListener('change', fill);
			fill();
		})();
		</script>
		<?php
	}
}
