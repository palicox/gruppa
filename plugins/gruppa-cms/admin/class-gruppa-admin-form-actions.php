<?php
defined( 'ABSPATH' ) || exit;

/**
 * Dynamic Forms editor — "Actions" section (cards like the field builder).
 */
class Gruppa_Admin_Form_Actions {

	/**
	 * Field data for the action selects: [ type slug => [ [ key, label, type, related, format ] ] ].
	 *
	 * @param bool $with_links Add data links of Content types (Listing Builder, Search Filters).
	 */
	public static function type_fields( $with_links = false ) {
		$out = array();
		foreach ( Gruppa_Types::all() as $t ) {
			$list = array(
				array( 'key' => 'title', 'label' => 'Title', 'type' => 'text', 'related' => '', 'format' => '' ),
				array( 'key' => 'status', 'label' => 'Status', 'type' => 'status', 'related' => '', 'format' => '' ),
			);
			foreach ( $t->fields as $f ) {
				if ( Gruppa_Fields::is_virtual( $f ) ) {
					continue;
				}
				$list[] = array(
					'key'     => $f['key'],
					'label'   => $f['label'],
					'type'    => $f['type'],
					'related' => 'relation' === $f['type'] ? $f['related'] : '',
					'format'  => isset( $f['format'] ) ? $f['format'] : '',
				);
			}
			if ( $with_links ) {
				foreach ( Gruppa_Relations::links( $t ) as $l ) {
					$list[] = array(
						'key'     => $l['key'],
						'label'   => $l['label'] . ' (' . $l['data_name'] . ')',
						'type'    => 'relation',
						'related' => $l['data'],
						'format'  => '',
						'link'    => 1,
					);
				}
			}
			$out[ $t->slug ] = $list;
		}
		return $out;
	}

	public static function render( $settings ) {
		$actions = isset( $settings['actions'] ) ? (array) $settings['actions'] : array();
		?>
		<h2 class="gruppa-fields-title">Actions</h2>
		<p class="gruppa-muted gfa-intro">Run after the form is submitted, top to bottom. Saving to the entity always happens first.</p>
		<div id="gfa-list">
			<?php foreach ( $actions as $i => $a ) {
				self::card( $i, $a );
			} ?>
		</div>
		<div class="gfa-add">
			<select id="gfa-add-type">
				<?php foreach ( Gruppa_Form_Actions::types() as $k => $l ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button" id="gfa-add">Add Action</button>
		</div>
		<?php foreach ( array_keys( Gruppa_Form_Actions::types() ) as $t ) : ?>
			<script type="text/html" id="tmpl-gfa-<?php echo esc_attr( $t ); ?>"><?php self::card( '__i__', Gruppa_Form_Actions::defaults( $t ), true ); ?></script>
		<?php endforeach; ?>
		<script type="text/html" id="tmpl-gfa-cond"><?php self::condition( '__i__', '__j__', array() ); ?></script>
		<script>window.gruppaFormActionsIndex = <?php echo (int) count( $actions ); ?>;</script>
		<?php
	}

	/** Select of form-type fields, filled by JS (kind: any | text | relation | password). */
	public static function field_select( $name, $current, $kind = 'any', $empty = '— select field —' ) {
		printf(
			'<select class="gfa-field" name="%s" data-current="%s" data-kind="%s" data-empty="%s"><option value="%s" selected>%s</option></select>',
			esc_attr( $name ), esc_attr( $current ), esc_attr( $kind ), esc_attr( $empty ),
			esc_attr( $current ), esc_html( '' === $current ? $empty : $current )
		);
	}

	public static function row( $label, $cb, $help = '' ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		$cb();
		if ( $help ) {
			echo '<p class="description">' . wp_kses_post( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function text( $name, $value, $ph = '', $type = 'text' ) {
		printf( '<input type="%s" name="%s" value="%s" placeholder="%s">', esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), esc_attr( $ph ) );
	}

	public static function select( $name, $options, $current, $class = '' ) {
		printf( '<select name="%s" class="%s">', esc_attr( $name ), esc_attr( $class ) );
		foreach ( $options as $k => $l ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( (string) $current, (string) $k, false ), esc_html( $l ) );
		}
		echo '</select>';
	}

	public static function check( $name, $on, $label ) {
		printf( '<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>', esc_attr( $name ), checked( $on, true, false ), esc_html( $label ) );
	}

	public static function summary( $a ) {
		switch ( $a['type'] ) {
			case 'email':
				return $a['to'];
			case 'webhook':
				return $a['url'];
			case 'update_item':
				return $a['relation'] && $a['target'] ? $a['relation'] . ' → ' . $a['target'] : '';
			case 'register':
				return 'role: ' . $a['role'];
		}
		return (string) apply_filters( 'gruppa_cms/form_action_summary', '', $a );
	}

	public static function card( $i, $a, $open = false ) {
		$a     = array_merge( Gruppa_Form_Actions::defaults( $a['type'] ), $a );
		$n     = 'settings[actions][' . $i . ']';
		$types = Gruppa_Form_Actions::types();
		$ph    = '{title}, {id}, {permalink}, {all_fields}, {form}, {site_name}, {date}, {user_email} or any field key, e.g. {email}';
		?>
		<div class="gruppa-field gfa-card<?php echo $open ? ' is-open' : ''; ?><?php echo $a['enabled'] ? '' : ' is-disabled'; ?>" data-index="<?php echo esc_attr( $i ); ?>" data-type="<?php echo esc_attr( $a['type'] ); ?>">
			<div class="gruppa-field-head">
				<span class="dashicons dashicons-menu gruppa-handle"></span>
				<span class="gruppa-field-title"><?php echo esc_html( '' !== $a['title'] ? $a['title'] : $types[ $a['type'] ] ); ?></span>
				<span class="gruppa-field-key gfa-summary"><?php echo esc_html( self::summary( $a ) ); ?></span>
				<span class="gruppa-field-type"><?php echo esc_html( $types[ $a['type'] ] ); ?></span>
				<label class="gfa-enabled" title="Enabled"><input type="hidden" name="<?php echo esc_attr( $n ); ?>[enabled]" value="0"><span class="gruppa-switch"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $a['enabled'] ); ?>><span></span></span></label>
				<span class="dashicons dashicons-arrow-down-alt2 gruppa-toggle"></span>
			</div>
			<div class="gruppa-field-body">
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[type]" value="<?php echo esc_attr( $a['type'] ); ?>">
				<table class="form-table" role="presentation">
					<?php
					self::row( 'Name', function () use ( $n, $a, $types ) {
						self::text( $n . '[title]', $a['title'], $types[ $a['type'] ] );
					} );

					switch ( $a['type'] ) {
						case 'email':
							self::row( 'To', function () use ( $n, $a ) {
								self::text( $n . '[to]', $a['to'], 'office@example.com, {email}' );
							}, 'Comma separated. Use {admin_email} or a field, e.g. {email}, for an auto-reply.' );
							self::row( 'Subject', function () use ( $n, $a ) {
								self::text( $n . '[subject]', $a['subject'] );
							} );
							self::row( 'Message', function () use ( $n, $a, $ph ) {
								printf( '<textarea name="%s" rows="7">%s</textarea>', esc_attr( $n . '[message]' ), esc_textarea( $a['message'] ) );
								echo '<p class="description gfa-placeholders">Placeholders: ' . esc_html( $ph ) . '<span class="gfa-ph-fields"></span></p>';
							} );
							self::row( 'Format', function () use ( $n, $a ) {
								self::select( $n . '[format]', array( 'html' => 'HTML', 'plain' => 'Plain text' ), $a['format'] );
							} );
							self::row( 'Reply-To', function () use ( $n, $a ) {
								self::text( $n . '[reply_to]', $a['reply_to'], '{email}' );
							} );
							self::row( 'From', function () use ( $n, $a ) {
								echo '<div class="gruppa-inline">';
								self::text( $n . '[from_name]', $a['from_name'], 'Name (optional)' );
								self::text( $n . '[from_email]', $a['from_email'], 'email@yourdomain.com (optional)', 'email' );
								echo '</div>';
							}, 'Empty = WordPress default. Use an address on your own domain, otherwise mail may end up in spam.' );
							break;

						case 'webhook':
							self::row( 'URL', function () use ( $n, $a ) {
								self::text( $n . '[url]', $a['url'], 'https://hook.make.com/…', 'url' );
							} );
							self::row( 'Format', function () use ( $n, $a ) {
								self::select( $n . '[format]', array( 'json' => 'JSON', 'form' => 'Form data' ), $a['format'] );
							}, 'Sends: event, form, type, item (all fields), user, submitted, site.' );
							self::row( 'Secret', function () use ( $n, $a ) {
								self::text( $n . '[secret]', $a['secret'], 'optional' );
							}, 'Adds header X-Gruppa-Signature: sha256=HMAC(body, secret).' );
							break;

						case 'update_item':
							self::row( 'Related item', function () use ( $n, $a ) {
								self::field_select( $n . '[relation]', $a['relation'], 'relation', '— Relation field —' );
							}, 'Item(s) chosen in this Relation field are updated.' );
							self::row( 'Field', function () use ( $n, $a ) {
								printf(
									'<select class="gfa-target" name="%s" data-current="%s"><option value="%s" selected>%s</option></select>',
									esc_attr( $n . '[target]' ), esc_attr( $a['target'] ), esc_attr( $a['target'] ), esc_html( '' === $a['target'] ? '— select —' : $a['target'] )
								);
							} );
							self::row( 'Change', function () use ( $n, $a ) {
								echo '<div class="gruppa-inline">';
								self::select( $n . '[op]', array( 'set' => 'Set to', 'inc' => 'Increase by', 'dec' => 'Decrease by' ), $a['op'] );
								self::text( $n . '[value]', $a['value'], 'value, number or {field}' );
								echo '</div>';
							}, 'E.g. Decrease by 1 → free places of a training. Status: publish / draft.' );
							break;

						case 'register':
							$roles = Gruppa_Form_Actions::safe_roles();
							self::row( 'Email field', function () use ( $n, $a ) {
								self::field_select( $n . '[email_field]', $a['email_field'], 'text' );
							}, 'Runs for guests only, before the item is saved. Existing email → error at this field.' );
							self::row( 'Username field', function () use ( $n, $a ) {
								self::field_select( $n . '[username_field]', $a['username_field'], 'text', '— from email —' );
							} );
							self::row( 'Password field', function () use ( $n, $a ) {
								self::field_select( $n . '[password_field]', $a['password_field'], 'text', '— send "set password" email —' );
							} );
							self::row( 'First / last name', function () use ( $n, $a ) {
								echo '<div class="gruppa-inline">';
								self::field_select( $n . '[first_name_field]', $a['first_name_field'], 'text', '— none —' );
								self::field_select( $n . '[last_name_field]', $a['last_name_field'], 'text', '— none —' );
								echo '</div>';
							} );
							self::row( 'Role', function () use ( $n, $a, $roles ) {
								self::select( $n . '[role]', $roles, $a['role'] );
							}, 'Roles that can manage the site or users are not offered.' );
							self::row( 'After registration', function () use ( $n, $a ) {
								self::check( $n . '[login]', $a['login'], 'Log the user in' );
								echo '<br>';
								self::check( $n . '[author]', $a['author'], 'Set the new user as author of the item' );
							} );
							break;
					}

					if ( ! in_array( $a['type'], array( 'email', 'webhook', 'update_item', 'register' ), true ) ) {
						/*
						 * Add-on action settings. Use Gruppa_Admin_Form_Actions::row() / text() / select() /
						 * check() / field_select() so they look like the built-in actions.
						 */
						do_action( 'gruppa_cms/form_action_fields', $n, $a );
					}

					self::row( 'Conditions', function () use ( $n, $i, $a ) {
						echo '<div class="gfa-conds">';
						echo '<p class="gfa-cond-match">Run when ';
						self::select( $n . '[cond_match]', array( 'all' => 'all', 'any' => 'any' ), $a['cond_match'] );
						echo ' of these match. No conditions = always.</p>';
						echo '<div class="gfa-cond-list">';
						foreach ( $a['conditions'] as $j => $c ) {
							self::condition( $i, $j, $c );
						}
						echo '</div><button type="button" class="button button-small gfa-cond-add">Add Condition</button></div>';
					} );
					?>
				</table>
				<p><button type="button" class="button-link button-link-delete gfa-remove">Remove</button></p>
			</div>
		</div>
		<?php
	}

	public static function condition( $i, $j, $c ) {
		$c = array_merge( array( 'field' => '', 'op' => 'eq', 'value' => '' ), $c );
		$n = 'settings[actions][' . $i . '][conditions][' . $j . ']';
		echo '<div class="gfa-cond">';
		self::field_select( $n . '[field]', $c['field'], 'any' );
		self::select( $n . '[op]', Gruppa_Form_Actions::OPS, $c['op'], 'gfa-op' );
		self::text( $n . '[value]', $c['value'], 'value' );
		echo '<button type="button" class="button-link gfa-cond-remove" aria-label="Remove">&times;</button></div>';
	}
}
