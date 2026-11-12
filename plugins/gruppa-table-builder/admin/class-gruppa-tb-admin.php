<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa Tools → Table Builder: list of tables and the table editor (Gruppa CMS admin look).
 * 0.7.0: separate plugin again (Gruppa CMS 0.31 has no built-in copy); menu item through the Gruppa Tools hub.
 */
class Gruppa_TB_Admin {

	const PAGE = 'gruppa-table-builder';
	const CAP  = 'manage_options';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		Gruppa_Tools_Hub::add_module(
			array(
				'slug'     => self::PAGE,
				'title'    => 'Table Builder',
				'callback' => array( $this, 'render' ),
				'version'  => GRUPPA_TB_VERSION,
			)
		);
		add_filter( 'plugin_action_links_' . plugin_basename( GRUPPA_TB_FILE ), array( $this, 'plugin_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 30 );
		add_action( 'admin_post_gruppa_tb_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_tb_status', array( $this, 'handle_status' ) );
		add_action( 'admin_post_gruppa_tb_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_gruppa_tb_duplicate', array( $this, 'handle_duplicate' ) );
	}

	public function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Tables</a>' );
		return $links;
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	private static function action_url( $action, $id ) {
		return wp_nonce_url( add_query_arg( array( 'action' => $action, 'id' => (int) $id ), admin_url( 'admin-post.php' ) ), $action . '_' . (int) $id );
	}

	private static function is_page() {
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore
	}

	public function assets() {
		if ( ! self::is_page() ) {
			return;
		}
		if ( ! wp_style_is( 'gruppa-admin', 'registered' ) ) {
			wp_register_style( 'gruppa-admin', GRUPPA_TB_URL . 'hub/gruppa-ui.css', array(), GRUPPA_TB_VERSION );
		}
		wp_enqueue_style( 'gruppa-tb-admin', GRUPPA_TB_URL . 'admin/tb-admin.css', array( 'gruppa-admin' ), GRUPPA_TB_VERSION );
		if ( isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) { // phpcs:ignore
			wp_enqueue_script( 'gruppa-tb-admin', GRUPPA_TB_URL . 'admin/tb-admin.js', array( 'jquery', 'jquery-ui-sortable' ), GRUPPA_TB_VERSION, true );
		}
	}

	/* ---------- Handlers ---------- */

	private static function guard( $nonce ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $nonce );
	}

	public function handle_save() {
		self::guard( 'gruppa_tb_save' );
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$post = wp_unslash( $_POST );
		// Content type select: "origin:slug" (e.g. jet:byty, wp:page, gruppa:destinacie).
		$tkey   = isset( $post['type_key'] ) ? (string) $post['type_key'] : '';
		$parts  = explode( ':', $tkey, 2 );
		$origin = 2 === count( $parts ) ? sanitize_key( $parts[0] ) : 'gruppa';
		$tslug  = 2 === count( $parts ) ? $parts[1] : $tkey;
		$res    = Gruppa_TB_DB::save(
			array(
				'name'      => isset( $post['name'] ) ? $post['name'] : '',
				'slug'      => isset( $post['slug'] ) ? $post['slug'] : '',
				'origin'    => $origin,
				'type_slug' => $tslug,
				'status'    => isset( $post['status'] ) ? $post['status'] : 'publish',
				'settings'  => isset( $post['settings'] ) && is_array( $post['settings'] ) ? $post['settings'] : array(),
			),
			$id
		);
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( self::url( array( 'view' => 'edit', 'id' => $id, 'msg' => $res->get_error_code() ) ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'view' => 'edit', 'id' => $res, 'msg' => $id ? 'saved' : 'created' ) ) );
		exit;
	}

	public function handle_status() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_tb_status_' . $id );
		$t = Gruppa_TB_DB::get( $id );
		if ( $t ) {
			Gruppa_TB_DB::set_status( $id, 'publish' === $t->status ? 'draft' : 'publish' );
		}
		wp_safe_redirect( self::url( array( 'msg' => 'status' ) ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_tb_delete_' . $id );
		Gruppa_TB_DB::delete( $id );
		wp_safe_redirect( self::url( array( 'msg' => 'deleted' ) ) );
		exit;
	}

	public function handle_duplicate() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		self::guard( 'gruppa_tb_duplicate_' . $id );
		$new = Gruppa_TB_DB::duplicate( $id );
		wp_safe_redirect( $new ? self::url( array( 'view' => 'edit', 'id' => $new, 'msg' => 'duplicated' ) ) : self::url( array( 'msg' => 'missing' ) ) );
		exit;
	}

	private static function notice() {
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
		$map = array(
			'saved'      => array( 'success', 'Saved.' ),
			'created'    => array( 'success', 'Table created. Add it to a page with the shortcode or the Elementor widget Gruppa Table.' ),
			'status'     => array( 'success', 'Status changed.' ),
			'deleted'    => array( 'success', 'Deleted.' ),
			'duplicated' => array( 'success', 'Copy created as Draft.' ),
			'missing'    => array( 'error', 'Table not found.' ),
			'name'       => array( 'error', 'Enter a name.' ),
			'type'       => array( 'error', 'Select a content type. The saved content type does not exist any more, or its plugin (Gruppa CMS / JetEngine) is not active.' ),
			'db'         => array( 'error', 'The table could not be saved.' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
	}

	/* ---------- Pages ---------- */

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		echo '<div class="wrap gruppa-wrap gruppa-tb">';
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore
		'edit' === $view ? $this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 ) : $this->render_list(); // phpcs:ignore
		echo '</div>';
	}

	private function render_list() {
		$status = isset( $_GET['status'] ) && in_array( $_GET['status'], array( 'publish', 'draft' ), true ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
		$rows   = Gruppa_TB_DB::all( $status );
		$counts = Gruppa_TB_DB::counts();
		?>
		<h1 class="wp-heading-inline">Table Builder</h1>
		<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Table</a>
		<hr class="wp-header-end">
		<?php self::notice(); ?>
		<nav class="gruppa-tabs">
			<?php foreach ( array( '' => 'All', 'publish' => 'Published', 'draft' => 'Draft' ) as $k => $label ) : ?>
				<a href="<?php echo esc_url( $k ? self::url( array( 'status' => $k ) ) : self::url() ); ?>" class="<?php echo $status === $k ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?> <span class="gruppa-tab-count"><?php echo (int) $counts[ $k ? $k : 'all' ]; ?></span></a>
			<?php endforeach; ?>
		</nav>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead>
				<tr>
					<th class="column-title">Name</th>
					<th>Rows from</th>
					<th>Content type</th>
					<th>Shortcode</th>
					<th style="width:110px">Status</th>
					<th style="width:260px">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6">No tables yet. <a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>">Add the first one</a>.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $t ) :
					$source = Gruppa_TB::source_of( $t );
					$live   = $source && $source->available();
					$src    = Gruppa_TB::sources()[ $t->settings['source'] ];
					if ( 'repeater' === $t->settings['source'] && $live ) {
						$reps = $source->repeaters( $t->type_slug );
						$src .= isset( $reps[ $t->settings['field'] ] ) ? ' · ' . $reps[ $t->settings['field'] ]['label'] : '';
					}
					$tname = $live ? $source->type_name( $t->type_slug ) : $t->type_slug;
					$sname = $source ? $source->label() : $t->settings['origin'];
					?>
					<tr>
						<td class="column-title"><strong><a href="<?php echo esc_url( self::url( array( 'view' => 'edit', 'id' => $t->id ) ) ); ?>"><?php echo esc_html( $t->name ); ?></a></strong><br><span class="gruppa-muted"><?php echo esc_html( $t->slug ); ?></span></td>
						<td><?php echo esc_html( $src ); ?></td>
						<td><?php echo esc_html( $tname ); ?><br><span class="gruppa-muted"><?php echo esc_html( $sname . ( $live ? '' : ' — not active' ) ); ?></span></td>
						<td><input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_table table="' . $t->slug . '"]' ); ?>" onclick="this.select()"></td>
						<td><?php echo 'publish' === $t->status ? 'Published' : 'Draft'; ?></td>
						<td>
							<a href="<?php echo esc_url( self::url( array( 'view' => 'edit', 'id' => $t->id ) ) ); ?>">Edit</a> |
							<a href="<?php echo esc_url( self::action_url( 'gruppa_tb_duplicate', $t->id ) ); ?>">Duplicate</a> |
							<a href="<?php echo esc_url( self::action_url( 'gruppa_tb_status', $t->id ) ); ?>"><?php echo 'publish' === $t->status ? 'Draft' : 'Publish'; ?></a> |
							<a class="gruppa-delete" href="<?php echo esc_url( self::action_url( 'gruppa_tb_delete', $t->id ) ); ?>" onclick="return confirm('Delete this table?')">Delete</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** Column card. Placeholders __i__ / __key__ / __name__ / __ftype__ / __type__ are filled by JS for new columns. */
	private static function card( $i, $c, $name, $ftype, $ntype = 'text', $open = false ) {
		$c = wp_parse_args( $c, Gruppa_TB::column_defaults() );
		$n = 'settings[columns][' . $i . ']';
		?>
		<div class="gruppa-field gfa-card gtb-card<?php echo $open ? ' is-open' : ''; ?>" data-key="<?php echo esc_attr( $c['key'] ); ?>" data-type="<?php echo esc_attr( $ntype ); ?>">
			<div class="gruppa-field-head">
				<span class="dashicons dashicons-menu gruppa-handle"></span>
				<span class="gruppa-field-title"><?php echo esc_html( '' !== $c['label'] ? $c['label'] : $name ); ?></span>
				<span class="gruppa-field-key gfa-summary"><?php echo esc_html( $c['key'] ); ?></span>
				<span class="gruppa-field-type"><?php echo esc_html( $ftype ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2 gruppa-toggle"></span>
			</div>
			<div class="gruppa-field-body">
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[key]" value="<?php echo esc_attr( $c['key'] ); ?>">
				<table class="form-table" role="presentation">
					<tr><th>Label</th><td><input type="text" class="gtb-label" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php echo esc_attr( $name ); ?>"></td></tr>
					<tr><th>Format</th><td>
						<select name="<?php echo esc_attr( $n ); ?>[format]" class="gtb-format">
							<?php foreach ( Gruppa_TB::FORMATS as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['format'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Automatic = by field type (number, date, time, yes/no, image). „Link to the item page“ only for items.</p>
					</td></tr>
					<tr class="gtb-num-row"><th>Decimals</th><td><input type="number" min="0" max="6" name="<?php echo esc_attr( $n ); ?>[decimals]" value="<?php echo esc_attr( $c['decimals'] ); ?>" placeholder="as stored"></td></tr>
					<tr class="gtb-num-row"><th>Decimal separator</th><td><input type="text" maxlength="3" name="<?php echo esc_attr( $n ); ?>[dec_point]" value="<?php echo esc_attr( $c['dec_point'] ); ?>" placeholder="site default — type , or ."></td></tr>
					<tr class="gtb-num-row"><th>Thousands separator</th><td><input type="text" maxlength="3" name="<?php echo esc_attr( $n ); ?>[thousands_sep]" value="<?php echo esc_attr( $c['thousands_sep'] ); ?>" placeholder="site default — type a space, . or ,"></td></tr>
					<tr><th>Before / after</th><td>
						<div class="gruppa-inline">
							<input type="text" name="<?php echo esc_attr( $n ); ?>[prefix]" value="<?php echo esc_attr( $c['prefix'] ); ?>" placeholder="e.g. od">
							<input type="text" name="<?php echo esc_attr( $n ); ?>[suffix]" value="<?php echo esc_attr( $c['suffix'] ); ?>" placeholder="e.g. € or kg">
						</div>
					</td></tr>
					<tr><th>Align</th><td>
						<select name="<?php echo esc_attr( $n ); ?>[align]">
							<?php foreach ( array( 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c['align'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Width</th><td><input type="text" name="<?php echo esc_attr( $n ); ?>[width]" value="<?php echo esc_attr( $c['width'] ); ?>" placeholder="auto — e.g. 120 or 20%"></td></tr>
					<tr><th>Sortable</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[sortable]" value="1" <?php checked( $c['sortable'] ); ?>> Visitors sort the rows by a click on the column header</label></td></tr>
				</table>
				<p><button type="button" class="button-link button-link-delete gtb-remove">Remove</button></p>
			</div>
		</div>
		<?php
	}

	/** Field type name shown on a column card. */
	private static function type_label( $meta ) {
		static $ftl = null;
		if ( null === $ftl ) {
			$ftl = class_exists( 'Gruppa_Fields' ) ? Gruppa_Fields::types() : array();
		}
		$raw = isset( $meta['raw'] ) ? $meta['raw'] : $meta['type'];
		if ( isset( $ftl[ $raw ] ) ) {
			return $ftl[ $raw ];
		}
		return 'system' === $raw ? ucfirst( $meta['type'] ) : ucfirst( $raw );
	}

	/** Column list for the editor: [ [ key, label, type label, normalized type ], … ]. */
	private static function col_list( $cols ) {
		$out = array();
		foreach ( $cols as $k => $m ) {
			$out[] = array( (string) $k, $m['label'], self::type_label( $m ), $m['type'] );
		}
		return $out;
	}

	/** Everything the editor needs per content type, keyed "origin:slug". */
	private static function type_data() {
		$out = array();
		foreach ( Gruppa_TB_Source::available_sources() as $src ) {
			foreach ( $src->types() as $slug => $name ) {
				$reps = array();
				foreach ( $src->repeaters( $slug ) as $k => $r ) {
					$reps[] = array( (string) $k, $r['label'], self::col_list( $r['sub'] ) );
				}
				$out[ $src->id() . ':' . $slug ] = array(
					'origin'    => $src->id(),
					'items'     => self::col_list( $src->columns( $slug ) ),
					'repeaters' => $reps,
					'order'     => $src->order_options( $slug ),
					'context'   => $src->context_options( $slug ),
					'groups'    => $src->groups( $slug ),
					'slugItem'  => $src->has_slug(),
					'filters'   => $src->has_filters(),
				);
			}
		}
		return $out;
	}

	private function render_edit( $id ) {
		$t = $id ? Gruppa_TB_DB::get( $id ) : null;
		if ( $id && ! $t ) {
			echo '<h1 class="wp-heading-inline">Not found</h1><hr class="wp-header-end"><div class="notice notice-warning"><p>This table does not exist — it may have been deleted.</p></div>';
			echo '<p><a href="' . esc_url( self::url() ) . '" class="button">Back to the list</a></p>';
			return;
		}
		$s       = $t ? $t->settings : Gruppa_TB::defaults();
		$source  = $t ? Gruppa_TB::source_of( $t ) : null;
		$avail   = $t ? Gruppa_TB::available( $source, $t->type_slug, $s['source'], $s['field'] ) : array();
		$req     = ' <span class="gruppa-req">*</span>';
		$cur_key = $t ? $s['origin'] . ':' . $t->type_slug : '';
		?>
		<h1 class="wp-heading-inline"><?php echo $t ? esc_html( $t->name ) : 'Add Table'; ?></h1>
		<a href="<?php echo esc_url( self::url() ); ?>" class="page-title-action">Back</a>
		<?php if ( $t ) : ?>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add Table</a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php self::notice(); ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="gtb-form">
			<input type="hidden" name="action" value="gruppa_tb_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gruppa_tb_save' ); ?>

			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-edit-layout">
			<div class="gruppa-edit-main">
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th><label for="g-name">Name<?php echo $req; // phpcs:ignore ?></label></th><td><input type="text" id="g-name" name="name" value="<?php echo esc_attr( $t ? $t->name : '' ); ?>" required></td></tr>
					<tr>
						<th><label for="g-slug">Slug<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<?php if ( $t ) : ?>
								<input type="text" id="g-slug" value="<?php echo esc_attr( $t->slug ); ?>" readonly>
							<?php else : ?>
								<input type="text" id="g-slug" name="slug" pattern="[a-z0-9_\-]+" maxlength="40" required placeholder="filled from the name">
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="gtb-type">Content type<?php echo $req; // phpcs:ignore ?></label></th>
						<td>
							<select name="type_key" id="gtb-type" required>
								<option value="">— select —</option>
								<?php
								$found = false;
								foreach ( Gruppa_TB_Source::available_sources() as $src ) :
									$types = $src->types();
									if ( ! $types ) {
										continue;
									}
									?>
									<optgroup label="<?php echo esc_attr( $src->label() ); ?>">
										<?php foreach ( $types as $slug => $name ) :
											$k     = $src->id() . ':' . $slug;
											$found = $found || $k === $cur_key;
											?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cur_key, $k ); ?>><?php echo esc_html( $name . ( 'wp' === $src->id() ? ' (' . $slug . ')' : '' ) ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
								<?php if ( $t && ! $found ) : ?>
									<option value="<?php echo esc_attr( $cur_key ); ?>" selected><?php echo esc_html( $t->type_slug . ' — ' . ( $source ? $source->missing_text() : 'unknown source' ) ); ?></option>
								<?php endif; ?>
							</select>
							<p class="description">Gruppa CMS types, JetEngine CCT and WordPress post types (with ACF and JetEngine fields) — every active source is listed.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gtb-source">Rows from</label></th>
						<td>
							<select name="settings[source]" id="gtb-source">
								<?php foreach ( Gruppa_TB::sources() as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['source'], $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">Items = one row per item (price list, overview). Repeater = the rows of one item's Repeater field (technical details, day-by-day programme).</p>
						</td>
					</tr>
					<tr class="gtb-rep-row">
						<th><label for="gtb-field">Repeater field<?php echo $req; // phpcs:ignore ?></label></th>
						<td><select name="settings[field]" id="gtb-field" data-current="<?php echo esc_attr( $s['field'] ); ?>"><option value="">— select —</option></select></td>
					</tr>
					<tr class="gtb-rep-row">
						<th><label for="gtb-item">Item</label></th>
						<td>
							<div class="gruppa-inline">
								<select name="settings[item_source]" id="gtb-item">
									<option value="current" <?php selected( $s['item_source'], 'current' ); ?>>Current item (the page the table is on)</option>
									<option value="id" <?php selected( $s['item_source'], 'id' ); ?>>By ID</option>
									<option value="slug" id="gtb-item-slug" <?php selected( $s['item_source'], 'slug' ); ?>>By slug</option>
								</select>
								<input type="text" name="settings[item_value]" id="gtb-item-value" value="<?php echo esc_attr( $s['item_value'] ); ?>" placeholder="ID or slug">
							</div>
							<p class="description">Current item: put the table into the single template of the type — every item shows its own rows. Inside a JetEngine Listing Grid it is the item of the listing.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gtb-mobile">On mobile</label></th>
						<td>
							<select name="settings[mobile]" id="gtb-mobile">
								<option value="cards" <?php selected( $s['mobile'], 'cards' ); ?>>Cards under each other (label : value)</option>
								<option value="scroll" <?php selected( $s['mobile'], 'scroll' ); ?>>Table, scroll sideways</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="gtb-empty">Empty text</label></th>
						<td><input type="text" id="gtb-empty" name="settings[empty_text]" value="<?php echo esc_attr( $s['empty_text'] ); ?>"></td>
					</tr>
					<tr>
						<th>Header row</th>
						<td><label><input type="checkbox" name="settings[header]" value="1" <?php checked( $s['header'] ); ?>> Show column names above the rows</label></td>
					</tr>
				</table>
			</div>

			<div class="gtb-items-only">
				<h2 class="gruppa-fields-title">Query</h2>
				<div class="gruppa-card">
					<table class="form-table gruppa-type-table" role="presentation">
						<tr>
							<th><label for="gtb-orderby">Order by</label></th>
							<td><select name="settings[orderby]" id="gtb-orderby" data-current="<?php echo esc_attr( $s['orderby'] ); ?>"></select></td>
						</tr>
						<tr>
							<th><label for="gtb-order">Direction</label></th>
							<td>
								<select name="settings[order]" id="gtb-order">
									<option value="asc" <?php selected( $s['order'], 'asc' ); ?>>Ascending (A → Z, 1 → 9)</option>
									<option value="desc" <?php selected( $s['order'], 'desc' ); ?>>Descending (Z → A, 9 → 1)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="gtb-per">Rows per page</label></th>
							<td><input type="number" id="gtb-per" name="settings[per_page]" min="1" max="500" value="<?php echo (int) $s['per_page']; ?>"></td>
						</tr>
						<tr id="gtb-group-row">
							<th><label for="gtb-group">Group</label></th>
							<td><select name="settings[group]" id="gtb-group" data-current="<?php echo (int) $s['group']; ?>"><option value="0">All groups</option></select></td>
						</tr>
						<tr id="gtb-context-row">
							<th><label for="gtb-context">Related to current item</label></th>
							<td>
								<select name="settings[context_field]" id="gtb-context" data-current="<?php echo esc_attr( $s['context_field'] ); ?>"><option value="">— no —</option></select>
								<p class="description">Optional. Only items linked to the item whose page the table is on — like in Listing Builder. JetEngine / ACF: a Posts field that holds the current post.</p>
							</td>
						</tr>
						<tr>
							<th>Only my items</th>
							<td><label><input type="checkbox" name="settings[only_mine]" value="1" <?php checked( $s['only_mine'] ); ?>> Show only items of the logged-in user</label></td>
						</tr>
					</table>
				</div>
			</div>


			<h2 class="gruppa-fields-title">Columns</h2>
			<div id="gfa-list" class="gtb-columns">
				<?php foreach ( $s['columns'] as $i => $c ) {
					$m = isset( $avail[ $c['key'] ] ) ? $avail[ $c['key'] ] : Gruppa_TB_Source::meta( $c['key'], 'text' );
					self::card( $i, $c, $m['label'], self::type_label( $m ), $m['type'] );
				} ?>
			</div>
			<div class="gfa-add">
				<select id="gtb-add-key" aria-label="Column"></select>
				<button type="button" class="button" id="gtb-add">Add Column</button>
				<button type="button" class="button" id="gtb-add-all">Add All</button>
			</div>


			<?php if ( $t ) : ?>
				<h2 class="gruppa-fields-title">Frontend</h2>
				<div class="gruppa-card gruppa-form-embed">
					<p>
						<input type="text" readonly class="gruppa-shortcode" value="<?php echo esc_attr( '[gruppa_table table="' . $t->slug . '"]' ); ?>" onclick="this.select()">
						<span class="gruppa-muted">Elementor: widget <em>Gruppa Table</em>.<?php echo $source && $source->has_filters() ? ' Filter groups (Filter Builder) of the same entity filter the table too.' : ''; ?></span>
					</p>
				</div>
			<?php endif; ?>
			</div>

			<?php $author = get_userdata( $t ? (int) $t->author : get_current_user_id() ); $fmt = 'Y/m/d H:i'; ?>
			<aside class="gruppa-edit-side">
				<div class="gruppa-card gruppa-side-box">
					<p class="gruppa-side-row">
						<label for="g-status" class="screen-reader-text">Status</label>
						<select name="status" id="g-status">
							<option value="publish" <?php selected( $t ? $t->status : 'publish', 'publish' ); ?>>Published</option>
							<option value="draft" <?php selected( $t ? $t->status : '', 'draft' ); ?>>Draft</option>
						</select>
					</p>
					<dl class="gruppa-side-meta">
						<?php if ( $t ) : ?>
							<dt>ID</dt><dd><?php echo (int) $t->id; ?></dd>
						<?php endif; ?>
						<dt>Author</dt><dd><?php echo esc_html( $author ? $author->display_name : '—' ); ?></dd>
						<?php if ( $t ) : ?>
							<dt>Created</dt><dd><?php echo esc_html( $t->created ? mysql2date( $fmt, $t->created ) : '—' ); ?></dd>
							<dt>Modified</dt><dd><?php echo esc_html( $t->modified ? mysql2date( $fmt, $t->modified ) : '—' ); ?></dd>
						<?php endif; ?>
					</dl>
					<button type="submit" class="button button-primary gruppa-side-save"><?php echo $t ? 'Update' : 'Publish'; ?></button>
					<?php if ( $t ) : ?>
						<p class="gruppa-side-links">
							<a href="<?php echo esc_url( self::action_url( 'gruppa_tb_duplicate', $t->id ) ); ?>">Duplicate</a>
							<a class="gruppa-delete" href="<?php echo esc_url( self::action_url( 'gruppa_tb_delete', $t->id ) ); ?>" onclick="return confirm('Delete this table?')">Delete</a>
						</p>
					<?php endif; ?>
				</div>
			</aside>
			</div>
		</form>
		<script type="text/html" id="tmpl-gtb-card"><?php self::card( '__i__', array( 'key' => '__key__' ), '__name__', '__ftype__', '__type__', true ); ?></script>
		<script>window.gruppaTB = <?php echo wp_json_encode( array( 'types' => self::type_data(), 'index' => count( $s['columns'] ) ) ); ?>;</script>
		<?php
	}
}
