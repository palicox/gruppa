<?php
/**
 * Import/Export — Gruppa Tools plugin, menu Gruppa Tools → Import/Export (hub), with or without Gruppa CMS.
 * Gruppa CMS 0.31+ has no built-in copy. Gruppa CMS 0.29.3–0.30 (constant GRUPPA_CMS_DATA_TOOLS) still shows
 * this plugin as Gruppa CMS → Data Tools when it is newer than the built-in module.
 *
 * Sources: Gruppa CMS types + SEO Builder (when Gruppa CMS is active), JetEngine CCT,
 * WordPress post types (+ ACF). Every type is addressed as "source:slug".
 */

defined( 'ABSPATH' ) || exit;

final class Gruppa_IE {

	const PAGE  = 'gruppa-import-export';
	const ORDER = 10;
	const CAP   = 'manage_options';
	const NONCE = 'gruppa_ie_nonce';
	const CHUNK = 50;

	/** Roles that can be assigned as author of imported items. */
	const AUTHOR_ROLES = array( 'administrator', 'editor', 'author' );

	/** @var Gruppa_IE_Source[]|null */
	private $sources = null;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Shown by Gruppa CMS 0.29.3–0.30 as Data Tools (they define GRUPPA_CMS_DATA_TOOLS). */
	public static function in_cms() {
		return defined( 'GRUPPA_CMS_DATA_TOOLS' );
	}

	private function __construct() {
		if ( ! self::in_cms() && class_exists( 'Gruppa_Tools_Hub' ) ) {
			Gruppa_Tools_Hub::add_module(
				array(
					'slug'     => self::PAGE,
					'title'    => 'Import/Export',
					'callback' => array( $this, 'render' ),
					'version'  => GRUPPA_IE_VERSION,
				)
			);
		}
		if ( ! defined( 'GRUPPA_CMS_FILE' ) || GRUPPA_IE_FILE !== GRUPPA_CMS_FILE ) {
			add_filter( 'plugin_action_links_' . plugin_basename( GRUPPA_IE_FILE ), array( $this, 'action_links' ) );
		}
		add_action( 'admin_post_gruppa_ie_export', array( $this, 'handle_export' ) );
		add_action( 'wp_ajax_gruppa_ie_parse', array( $this, 'ajax_parse' ) );
		add_action( 'wp_ajax_gruppa_ie_import', array( $this, 'ajax_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_menu', array( $this, 'redirect_old_page' ), 1 );
		add_action( 'gruppa_cms/items_header_actions', array( $this, 'items_button' ) );
	}

	/* ---------- Integration ---------- */

	public function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( self::PAGE !== $page ) {
			return;
		}
		wp_enqueue_style( 'gruppa-import-export', GRUPPA_IE_URL . 'assets/import-export.css', array( 'gruppa-admin' ), GRUPPA_IE_VERSION );
		wp_enqueue_script( 'gruppa-import-export', GRUPPA_IE_URL . 'assets/import-export.js', array( 'jquery' ), GRUPPA_IE_VERSION, true );
	}

	/** Old Gruppa CMS link (?page=gruppa-io) → this page, unless Gruppa CMS ≤ 0.17 still has its own. */
	public function redirect_old_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( 'gruppa-io' !== $page || wp_doing_ajax() ) {
			return;
		}
		$args = array();
		foreach ( array( 'tab', 'type' ) as $k ) {
			if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore
				$args[ $k ] = sanitize_text_field( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore
			}
		}
		wp_safe_redirect( $this->url( $args ) );
		exit;
	}


	/** "Import/Export" button in the header of a Gruppa CMS items list. */
	public function items_button( $type ) {
		if ( ! current_user_can( self::CAP ) || empty( $type->slug ) ) {
			return;
		}
		printf( '<a href="%s" class="page-title-action">Import/Export</a>', esc_url( $this->url( array( 'type' => 'gruppa:' . $type->slug ) ) ) );
	}


	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( $this->url() ) . '">Open</a>' );
		return $links;
	}

	private function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/* ---------- Sources ---------- */

	/** @return Gruppa_IE_Source[] id => source (only available ones) */
	public function sources() {
		if ( null === $this->sources ) {
			$this->sources = array();
			$all           = apply_filters( 'gruppa_ie/sources', array( new Gruppa_IE_Source_Gruppa(), new Gruppa_IE_Source_SEO(), new Gruppa_IE_Source_Jet(), new Gruppa_IE_Source_ACF() ) );
			foreach ( $all as $src ) {
				if ( $src instanceof Gruppa_IE_Source && $src->available() ) {
					$this->sources[ $src->id() ] = $src;
				}
			}
		}
		return $this->sources;
	}

	/** @return array|null [ source, slug ] */
	private function resolve( $ref ) {
		$parts = explode( ':', (string) $ref, 2 );
		if ( 2 !== count( $parts ) ) {
			return null;
		}
		$sources = $this->sources();
		$src     = isset( $sources[ $parts[0] ] ) ? $sources[ $parts[0] ] : null;
		return $src && $src->has_type( $parts[1] ) ? array( $src, $parts[1] ) : null;
	}

	private function authors() {
		return get_users( array(
			'role__in' => self::AUTHOR_ROLES,
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
	}

	private function valid_author( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : false;
		return $user && array_intersect( self::AUTHOR_ROLES, (array) $user->roles );
	}

	/* ---------- Page ---------- */

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs = array(
			'import' => 'Import',
			'export' => 'Export',
		);
		$tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'import'; // phpcs:ignore

		$groups = array();
		$fields = array();
		$ids    = array();
		foreach ( $this->sources() as $sid => $src ) {
			$types = $src->types();
			if ( ! $types ) {
				continue;
			}
			$groups[ $sid ] = array( $src->label(), $types );
			$ids[ $sid ]    = $src->id_key();
			if ( 'import' === $tab ) {
				foreach ( array_keys( $types ) as $slug ) {
					$fields[ $sid . ':' . $slug ] = $src->fields( $slug );
				}
			}
		}

		// Preselect a type: ?type=gruppa:slug (e.g. from a content type's items list).
		$current = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore
		if ( $current && ! $this->resolve( $current ) ) {
			$current = '';
		}
		?>
		<div class="wrap gruppa-wrap gruppa-io">
			<h1 class="wp-heading-inline"><?php echo self::in_cms() ? 'Data Tools' : 'Import/Export'; ?></h1>
			<hr class="wp-header-end">

			<nav class="gruppa-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( $this->url( array_filter( array( 'tab' => $key, 'type' => $current ) ) ) ); ?>" class="<?php echo $key === $tab ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( ! $groups ) {
				echo '<div class="gruppa-card"><p>No content types found.</p></div>';
			} elseif ( 'export' === $tab ) {
				$this->render_export( $groups, $current );
			} else {
				$this->render_import( $groups, $current );
			}
			?>
		</div>
		<script>window.gruppaIE = <?php echo wp_json_encode( array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( self::NONCE ),
			'chunk'  => self::CHUNK,
			'fields' => $fields,
			'ids'    => $ids,
		) ); ?>;</script>
		<?php
	}

	private function type_select( $id, $groups, $current ) {
		printf( '<select id="%s" name="type" required><option value="">— Select content type —</option>', esc_attr( $id ) );
		foreach ( $groups as $sid => $g ) {
			printf( '<optgroup label="%s">', esc_attr( $g[0] ) );
			foreach ( $g[1] as $slug => $t ) {
				$val = $sid . ':' . $slug;
				printf( '<option value="%s" %s>%s (%d)</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $t['name'] ), (int) $t['count'] );
			}
			echo '</optgroup>';
		}
		echo '</select>';
	}

	private function render_import( $groups, $current ) {
		$me = get_current_user_id();
		?>
		<div class="gruppa-card gruppa-io-import">
			<table class="gruppa-io-map">
				<thead>
					<tr>
						<th>
							<span class="gruppa-io-step">1 · File columns</span>
							<label for="gio-file" class="button">Choose CSV / JSON</label>
							<input type="file" id="gio-file" class="screen-reader-text" accept=".csv,.json,text/csv,application/json">
							<span id="gio-file-info" class="gruppa-muted"></span>
						</th>
						<th class="gruppa-io-arrow"></th>
						<th>
							<span class="gruppa-io-step">2 · Import into — target fields</span>
							<?php $this->type_select( 'gio-type', $groups, $current ); ?>
						</th>
					</tr>
				</thead>
				<tbody id="gio-rows"></tbody>
			</table>

			<div class="gruppa-io-foot">
				<label class="gruppa-io-author">Author
					<select id="gio-author">
						<?php foreach ( $this->authors() as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>" <?php selected( $me, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><input type="checkbox" id="gio-keep-author" checked> keep the original author when overwriting by ID</label>
				<span class="gruppa-io-spacer"></span>
				<span id="gio-progress-text" class="gruppa-muted"></span>
				<button type="button" class="button button-primary" id="gio-run" disabled>Import</button>
			</div>
			<div class="gruppa-io-progress" id="gio-progress" hidden><div></div></div>
		</div>
		<div id="gio-result"></div>
		<?php
	}

	private function render_export( $groups, $current ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gruppa-io-export">
			<input type="hidden" name="action" value="gruppa_ie_export">
			<?php wp_nonce_field( 'gruppa_ie_export' ); ?>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gio-x-type">Content type <span class="gruppa-req">*</span></label></th>
						<td><?php $this->type_select( 'gio-x-type', $groups, $current ); ?></td>
					</tr>
					<tr>
						<th><label for="gio-x-status">Status</label></th>
						<td>
							<select name="status" id="gio-x-status">
								<option value="">All</option>
								<option value="publish">Published</option>
								<option value="draft">Draft</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Format</th>
						<td>
							<span class="gruppa-seg">
								<label><input type="radio" name="format" value="csv" checked><span>CSV</span></label>
								<label><input type="radio" name="format" value="json"><span>JSON</span></label>
							</span>
						</td>
					</tr>
					<tr class="gio-csv">
						<th><label for="gio-x-delim">Delimiter</label></th>
						<td>
							<select name="delimiter" id="gio-x-delim">
								<option value="semicolon">;  (Excel)</option>
								<option value="comma">,</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>System columns</th>
						<td><label><input type="checkbox" name="system" value="1" checked> ID, status, author, dates</label></td>
					</tr>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Export</button></p>
		</form>
		<?php
	}

	/* ---------- Export ---------- */

	public function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'gruppa_ie_export' );

		$ref = $this->resolve( isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '' );
		if ( ! $ref ) {
			wp_die( 'Select content type.', 'Gruppa Import/Export', array( 'back_link' => true ) );
		}
		list( $src, $slug ) = $ref;

		$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		$format = isset( $_POST['format'] ) && 'json' === $_POST['format'] ? 'json' : 'csv';
		$delim  = isset( $_POST['delimiter'] ) && 'comma' === $_POST['delimiter'] ? ',' : ';';
		$system = ! empty( $_POST['system'] );

		$data = $src->export( $slug, $status, $system );
		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( $data->get_error_message() ), 'Gruppa Import/Export', array( 'back_link' => true ) );
		}
		list( $cols, $items ) = $data;

		$types = $src->types();
		$file  = $slug . '-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
		nocache_headers();

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $file . '"' );
			echo wp_json_encode(
				array(
					'source'   => $src->id(),
					'type'     => $slug,
					'name'     => $types[ $slug ]['name'],
					'exported' => current_time( 'mysql' ),
					'count'    => count( $items ),
					'items'    => $items,
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			exit;
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $file . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM for Excel.
		fputcsv( $out, $cols, $delim, '"', '\\' );
		foreach ( $items as $item ) {
			$line = array();
			foreach ( $cols as $c ) {
				$v      = isset( $item[ $c ] ) ? $item[ $c ] : '';
				$line[] = is_array( $v ) || is_object( $v ) ? wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : (string) $v;
			}
			fputcsv( $out, $line, $delim, '"', '\\' );
		}
		fclose( $out );
		exit;
	}

	/* ---------- Parse uploaded file ---------- */

	public function ajax_parse() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'Forbidden.', 403 );
		}
		if ( empty( $_FILES['file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore
			wp_send_json_error( 'Upload failed.' );
		}

		$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ); // phpcs:ignore
		$raw  = file_get_contents( $_FILES['file']['tmp_name'] ); // phpcs:ignore
		if ( false === $raw || '' === trim( $raw ) ) {
			wp_send_json_error( 'The file is empty.' );
		}

		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( ! $this->is_utf8( $raw ) ) {
			$raw = $this->from_cp1250( $raw );
		}

		$ext     = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$is_json = 'json' === $ext || in_array( substr( ltrim( $raw ), 0, 1 ), array( '[', '{' ), true );
		$res     = $is_json ? $this->parse_json( $raw ) : $this->parse_csv( $raw );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}
		wp_send_json_success( array(
			'format'  => $is_json ? 'JSON' : 'CSV',
			'headers' => $res[0],
			'rows'    => $res[1],
		) );
	}

	private function is_utf8( $s ) {
		return function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $s, 'UTF-8' ) : (bool) preg_match( '//u', $s );
	}

	private function from_cp1250( $s ) {
		if ( function_exists( 'iconv' ) ) {
			$c = @iconv( 'CP1250', 'UTF-8//TRANSLIT', $s ); // phpcs:ignore
			if ( false !== $c && '' !== $c ) {
				return $c;
			}
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $s, 'UTF-8', 'ISO-8859-2' );
		}
		return $s;
	}

	private function parse_json( $raw ) {
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'json', 'Invalid JSON: ' . json_last_error_msg() );
		}
		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$data = $data['items'];
		} elseif ( ! isset( $data[0] ) ) {
			$data = array( $data );
		}
		$headers = array();
		$rows    = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( array_keys( $item ) as $k ) {
				$headers[ (string) $k ] = true;
			}
			$rows[] = $item;
		}
		if ( ! $rows ) {
			return new WP_Error( 'json', 'JSON contains no items.' );
		}
		return array( array_keys( $headers ), $rows );
	}

	private function parse_csv( $raw ) {
		$first = strtok( $raw, "\n" );
		$delim = ',';
		$best  = 0;
		foreach ( array( ',', ';', "\t" ) as $d ) {
			$n = substr_count( $first, $d );
			if ( $n > $best ) {
				$best  = $n;
				$delim = $d;
			}
		}

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $raw );
		rewind( $fh );

		$headers = fgetcsv( $fh, 0, $delim, '"', '\\' );
		if ( ! $headers ) {
			fclose( $fh );
			return new WP_Error( 'csv', 'CSV is empty.' );
		}
		$headers = array_map( 'trim', $headers );

		$rows = array();
		while ( false !== ( $line = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) ) { // phpcs:ignore
			if ( array( null ) === $line ) {
				continue;
			}
			$row = array();
			foreach ( $headers as $i => $h ) {
				if ( '' !== $h ) {
					$row[ $h ] = isset( $line[ $i ] ) ? $line[ $i ] : '';
				}
			}
			$filled = array_filter( $row, function ( $v ) {
				return '' !== trim( (string) $v );
			} );
			if ( $filled ) {
				$rows[] = $row;
			}
		}
		fclose( $fh );

		if ( ! $rows ) {
			return new WP_Error( 'csv', 'CSV contains no data rows.' );
		}
		return array( array_values( array_filter( $headers, 'strlen' ) ), $rows );
	}

	/* ---------- Import (AJAX, chunked) ---------- */

	public function ajax_import() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'Forbidden.', 403 );
		}

		$ref     = $this->resolve( isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '' );
		$mapping = json_decode( wp_unslash( isset( $_POST['mapping'] ) ? $_POST['mapping'] : '' ), true ); // phpcs:ignore
		$rows    = json_decode( wp_unslash( isset( $_POST['rows'] ) ? $_POST['rows'] : '' ), true ); // phpcs:ignore
		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$author  = isset( $_POST['author'] ) ? absint( $_POST['author'] ) : 0;
		$keep    = ! empty( $_POST['keep_author'] ) && '0' !== $_POST['keep_author'];

		if ( ! $ref ) {
			wp_send_json_error( 'The selected content type does not exist.' );
		}
		if ( ! is_array( $mapping ) || ! is_array( $rows ) || ! $mapping ) {
			wp_send_json_error( 'Missing import data.' );
		}
		if ( ! $this->valid_author( $author ) ) {
			wp_send_json_error( 'Invalid author.' );
		}

		list( $src, $slug ) = $ref;
		$res = $src->import( $slug, $mapping, $rows, $offset, $author, $keep );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}
		wp_send_json_success( $res );
	}
}
