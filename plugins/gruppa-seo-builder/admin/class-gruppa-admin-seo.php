<?php
defined( 'ABSPATH' ) || exit;

/**
 * SEO Builder admin: list, editor (with Google / Facebook preview), settings.
 * Works with Gruppa CMS (its admin notices and styles) and without it (JetEngine CCT only).
 */
class Gruppa_Admin_SEO {

	const PAGE = 'gruppa-seo';
	const CAP  = 'manage_options';

	public function __construct() {
		add_action( 'admin_post_gruppa_seo_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gruppa_seo_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_gruppa_seo_settings', array( $this, 'handle_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ), 10, 2 );
		add_action( 'admin_post_gruppa_seo_redirect_add', array( $this, 'handle_redirect_add' ) );
		add_action( 'admin_post_gruppa_seo_redirect_delete', array( $this, 'handle_redirect_delete' ) );
		add_action( 'admin_init', array( $this, 'term_hooks' ) );
	}

	/* ---------- Box "SEO" on the edit screen of a term (1.4.0) ---------- */

	public function term_hooks() {
		foreach ( Gruppa_SEO_Owners::wp_taxonomies() as $tx ) {
			add_action( $tx . '_edit_form', array( $this, 'term_box' ), 10, 2 );
		}
	}

	public function term_box( $term, $taxonomy ) {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$otype = Gruppa_SEO_Owners::TAX . $taxonomy;
		$seo   = Gruppa_SEO::get_by_owner( $otype, (int) $term->term_id );
		echo '<h2>SEO</h2><table class="form-table" role="presentation"><tr><th>SEO Builder</th><td>';
		if ( $seo ) {
			echo '<p><strong>' . esc_html( '' !== $seo->seo_title ? $seo->seo_title : $term->name ) . '</strong></p>';
			if ( '' !== (string) $seo->seo_description ) {
				echo '<p>' . esc_html( wp_trim_words( $seo->seo_description, 24 ) ) . '</p>';
			}
			printf( '<p><a class="button" href="%s">Edit SEO</a></p>', esc_url( self::url( array( 'view' => 'edit', 'id' => (int) $seo->id ) ) ) );
		} else {
			echo '<p class="description">No SEO yet — the template of this taxonomy (Settings → Types) is used.</p>';
			printf( '<p><a class="button" href="%s">Add SEO</a></p>', esc_url( self::url( array( 'view' => 'edit', 'owner' => $otype . ':' . (int) $term->term_id ) ) ) );
		}
		echo '</td></tr></table>';
	}

	/* ---------- Redirects (1.4.0) ---------- */

	public function handle_redirect_add() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_seo_redirect_add' );
		$errors = array();
		$n      = 0;
		$lines  = array();
		if ( ! empty( $_POST['source'] ) ) {
			$lines[] = array( wp_unslash( $_POST['source'] ), isset( $_POST['target'] ) ? wp_unslash( $_POST['target'] ) : '', isset( $_POST['code'] ) ? (int) $_POST['code'] : 301 ); // phpcs:ignore
		}
		// Bulk: one per line "old new [code]" (spaces, tabs, comma or semicolon).
		foreach ( preg_split( '/\r\n|\r|\n/', isset( $_POST['bulk'] ) ? (string) wp_unslash( $_POST['bulk'] ) : '' ) as $line ) { // phpcs:ignore
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$p = preg_split( '/[\s,;]+/', $line );
			$c = isset( $p[2] ) ? (int) $p[2] : ( isset( $p[1] ) && ctype_digit( $p[1] ) ? (int) $p[1] : 301 );
			$t = isset( $p[1] ) && ! ctype_digit( $p[1] ) ? $p[1] : '';
			$lines[] = array( $p[0], $t, $c );
		}
		foreach ( $lines as $l ) {
			$res = Gruppa_SEO_Redirects::add( $l[0], $l[1], $l[2] );
			if ( is_wp_error( $res ) ) {
				$errors[] = $res->get_error_message();
			} else {
				++$n;
			}
		}
		if ( $errors ) {
			set_transient( 'gruppa_seo_error_' . get_current_user_id(), implode( ' ', array_slice( $errors, 0, 5 ) ), 60 );
		}
		wp_safe_redirect( self::url( array( 'tab' => 'redirects', 'msg' => $errors ? 'seo_error' : 'saved', 'n' => $n ) ) );
		exit;
	}

	public function handle_redirect_delete() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_seo_redirect_delete' );
		$ids = isset( $_REQUEST['ids'] ) ? array_map( 'absint', (array) $_REQUEST['ids'] ) : array(); // phpcs:ignore
		Gruppa_SEO_Redirects::delete( $ids );
		wp_safe_redirect( self::url( array( 'tab' => 'redirects', 'msg' => 'deleted' ) ) );
		exit;
	}

	private function render_redirects() {
		$s    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$rows = Gruppa_SEO_Redirects::all( $s );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gruppa_seo_redirect_add">
			<?php wp_nonce_field( 'gruppa_seo_redirect_add' ); ?>
			<h2 class="gruppa-fields-title">Add redirect</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-r-source">Old address</label></th>
						<td><input type="text" id="gseo-r-source" name="source" placeholder="/stara-stranka/ · /blog/*"><p class="description">Path on this website (or the whole old URL). A * at the end matches everything that starts with it.</p></td>
					</tr>
					<tr>
						<th><label for="gseo-r-target">New address</label></th>
						<td><input type="text" id="gseo-r-target" name="target" placeholder="/nova-stranka/ · https://…"><p class="description">Path or full URL. With a * at the end of the old address, a * here adds the rest of the address.</p></td>
					</tr>
					<tr>
						<th><label for="gseo-r-code">Type</label></th>
						<td>
							<select name="code" id="gseo-r-code">
								<?php foreach ( Gruppa_SEO_Redirects::CODES as $c => $l ) : ?>
									<option value="<?php echo (int) $c; ?>"><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="gseo-r-bulk">Bulk</label></th>
						<td><textarea id="gseo-r-bulk" name="bulk" rows="4" placeholder="/stara-stranka/ /nova-stranka/ 301&#10;/zrusene/ 410"></textarea><p class="description">One per line: old address, new address, type (default 301). The same old address again = update.</p></td>
					</tr>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Add</button></p>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gruppa_seo_redirect_delete">
			<?php wp_nonce_field( 'gruppa_seo_redirect_delete' ); ?>
			<table class="wp-list-table widefat fixed gruppa-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" aria-label="Select all" onclick="var c=this.checked;this.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(function(x){x.checked=c;});"></td>
						<th>Old address</th>
						<th>New address</th>
						<th class="gruppa-col-robots">Type</th>
						<th class="gruppa-col-robots">Hits</th>
						<th>Last hit</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="6">No redirects yet.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<th class="check-column"><input type="checkbox" name="ids[]" value="<?php echo (int) $r->id; ?>" aria-label="Select"></th>
						<td><a href="<?php echo esc_url( home_url( $r->source ) ); ?>" target="_blank"><?php echo esc_html( $r->source ); ?></a></td>
						<td><?php echo esc_html( '' !== $r->target ? $r->target : '—' ); ?></td>
						<td><?php echo (int) $r->code; ?></td>
						<td><?php echo (int) $r->hits; ?></td>
						<td><?php echo esc_html( $r->last_hit ? mysql2date( 'Y/m/d H:i', $r->last_hit ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $rows ) : ?>
				<p><button type="submit" class="button" onclick="return confirm('Delete the selected redirects?')">Delete selected</button></p>
			<?php endif; ?>
		</form>
		<?php
	}

	/* ---------- Box "SEO" in the WordPress editor (1.3.0) ---------- */

	/**
	 * Pages, posts and custom post types with SEO (Settings → Post types), and the single pages of
	 * JetEngine CCT items: the box shows the SEO of the page and opens it in SEO Builder.
	 */
	public function meta_box( $post_type, $post ) {
		if ( ! current_user_can( self::CAP ) || ! $post instanceof WP_Post || 'attachment' === $post_type ) {
			return;
		}
		$is_new = 'auto-draft' === $post->post_status;
		$owner  = $is_new ? null : Gruppa_SEO_Owners::from_post( $post->ID );
		if ( ! $owner && ! ( $is_new && in_array( $post_type, Gruppa_SEO_Owners::wp_post_types(), true ) ) ) {
			return;
		}
		add_meta_box( 'gruppa-seo-box', 'SEO', array( $this, 'render_box' ), $post_type, 'side', 'default', array( 'owner' => $owner ) );
	}

	public function render_box( $post, $box ) {
		$owner = isset( $box['args']['owner'] ) ? $box['args']['owner'] : null;
		if ( ! $owner ) {
			echo '<p class="description">Save the page first, then add its SEO.</p>';
			return;
		}
		$conflict = Gruppa_SEO::conflict();
		if ( $conflict ) {
			echo '<p class="description">' . esc_html( $conflict ) . ' is active — Gruppa SEO Builder writes nothing on the website.</p>';
		}
		$seo = Gruppa_SEO::get_by_owner( $owner[0], $owner[1] );
		if ( $seo ) {
			$title = '' !== $seo->seo_title ? $seo->seo_title : get_the_title( $post );
			echo '<p><strong>' . esc_html( $title ) . '</strong></p>';
			echo '<p>' . ( '' !== (string) $seo->seo_description ? esc_html( wp_trim_words( $seo->seo_description, 24 ) ) : '<span class="description">No meta description.</span>' ) . '</p>';
			echo '<p class="description">Robots: ' . esc_html( Gruppa_SEO::ROBOTS[ Gruppa_SEO::robots( $seo->seo_robots ) ] ) . '</p>';
			printf( '<p><a class="button" href="%s">Edit SEO</a></p>', esc_url( self::url( array( 'view' => 'edit', 'id' => (int) $seo->id ) ) ) );
			return;
		}
		echo '<p class="description">No SEO yet — the title template and the default image of SEO Builder are used.</p>';
		printf( '<p><a class="button" href="%s">Add SEO</a></p>', esc_url( self::url( array( 'view' => 'edit', 'owner' => $owner[0] . ':' . (int) $owner[1] ) ) ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/* ---------- Handlers ---------- */

	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_seo_save' );
		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$data = wp_unslash( $_POST );
		if ( ! $id && ! empty( $data['owner'] ) && false !== strpos( $data['owner'], ':' ) ) {
			list( $data['owner_type'], $data['owner_id'] ) = Gruppa_SEO_Owners::split( $data['owner'] ); // "jet:produkty:5" too.
		}
		$res = Gruppa_SEO::save( $data, $id );
		if ( is_wp_error( $res ) ) {
			set_transient( 'gruppa_seo_error_' . get_current_user_id(), $res->get_error_message(), 60 );
			$back = array( 'view' => 'edit', 'id' => $id, 'msg' => 'seo_error' );
			if ( ! $id && ! empty( $data['owner'] ) ) {
				$back['owner'] = sanitize_text_field( $data['owner'] );
			}
			wp_safe_redirect( self::url( $back ) );
			exit;
		}
		wp_safe_redirect( self::url( array( 'view' => 'edit', 'id' => $res, 'msg' => 'saved' ) ) );
		exit;
	}

	public function handle_delete() {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_seo_delete_' . $id );
		Gruppa_SEO::delete( $id );
		wp_safe_redirect( self::url( array( 'msg' => 'deleted' ) ) );
		exit;
	}

	public function handle_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_seo_settings' );
		Gruppa_SEO::save_settings( isset( $_POST['seo'] ) ? wp_unslash( $_POST['seo'] ) : array() ); // phpcs:ignore
		wp_safe_redirect( self::url( array( 'tab' => 'settings', 'msg' => 'saved' ) ) );
		exit;
	}

	/* ---------- Views ---------- */

	/** Gruppa CMS notice (Saved / Deleted); the same markup without Gruppa CMS. */
	private static function notice() {
		if ( class_exists( 'Gruppa_Admin' ) ) {
			Gruppa_Admin::notice();
			return;
		}
		$msg = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore
		$map = array(
			'saved'   => array( 'success', 'Saved.' ),
			'deleted' => array( 'success', 'Deleted.' ),
			'missing' => array( 'error', 'Not found.' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $map[ $msg ][0] ), esc_html( $map[ $msg ][1] ) );
		}
	}

	/** "Content entities" / "JetEngine CCT with a single page" / both — whatever is active. */
	private static function sources_text() {
		$src = array();
		if ( Gruppa_SEO_Owners::cms() ) {
			$src[] = 'Content entities (they have a page)';
		}
		if ( Gruppa_SEO_Owners::jet() ) {
			$src[] = 'JetEngine CCT with a single page';
		}
		$wp = array();
		foreach ( Gruppa_SEO_Owners::wp_post_types() as $pt ) {
			$o    = get_post_type_object( $pt );
			$wp[] = $o ? strtolower( $o->labels->name ) : $pt;
		}
		if ( $wp ) {
			$src[] = 'WordPress ' . implode( ' and ', $wp );
		}
		$last = array_pop( $src );
		return $src ? implode( ', ', $src ) . ' and ' . $last : $last;
	}

	private static function not_found( $back_url, $what ) {
		if ( class_exists( 'Gruppa_Admin' ) ) {
			Gruppa_Admin::not_found( $back_url, $what );
			return;
		}
		printf(
			'<h1 class="wp-heading-inline">Not found</h1><hr class="wp-header-end"><div class="notice notice-warning"><p>%s does not exist — it may have been deleted.</p></div><p><a href="%s" class="button">Back to the list</a></p>',
			esc_html( $what ), esc_url( $back_url )
		);
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : ''; // phpcs:ignore
		$tab  = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array( 'settings', 'redirects' ), true ) ? sanitize_key( $_GET['tab'] ) : 'list'; // phpcs:ignore
		echo '<div class="wrap gruppa-wrap gruppa-seo">';
		self::notice();
		if ( isset( $_GET['msg'] ) && 'seo_error' === $_GET['msg'] ) { // phpcs:ignore
			$err = get_transient( 'gruppa_seo_error_' . get_current_user_id() );
			delete_transient( 'gruppa_seo_error_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ? $err : 'Could not save.' ) . '</p></div>';
		}
		$conflict = Gruppa_SEO::conflict();
		if ( $conflict ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html( $conflict ) . '</strong> is active — Gruppa SEO does not output anything on the website, so tags are not duplicated. Deactivate it to use SEO Builder.</p></div>';
		}
		if ( 'edit' === $view ) {
			$this->render_edit( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 ); // phpcs:ignore
		} else {
			if ( 'settings' !== $tab ) {
				$s = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
				?>
				<form method="get" class="gruppa-head-search">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
					<?php if ( 'redirects' === $tab ) : ?><input type="hidden" name="tab" value="redirects"><?php endif; ?>
					<input type="search" name="s" value="<?php echo esc_attr( $s ); ?>" aria-label="Search SEO"><button type="submit" class="button">Search</button>
				</form>
				<?php
			}
			?>
			<h1 class="wp-heading-inline">SEO Builder</h1>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add SEO</a>
			<hr class="wp-header-end">
			<nav class="gruppa-tabs">
				<a href="<?php echo esc_url( self::url() ); ?>" class="<?php echo 'list' === $tab ? 'is-active' : ''; ?>">SEO <span class="gruppa-tab-count"><?php echo (int) Gruppa_SEO::count(); ?></span></a>
				<a href="<?php echo esc_url( self::url( array( 'tab' => 'redirects' ) ) ); ?>" class="<?php echo 'redirects' === $tab ? 'is-active' : ''; ?>">Redirects <span class="gruppa-tab-count"><?php echo (int) Gruppa_SEO_Redirects::count(); ?></span></a>
				<a href="<?php echo esc_url( self::url( array( 'tab' => 'settings' ) ) ); ?>" class="<?php echo 'settings' === $tab ? 'is-active' : ''; ?>">Settings</a>
			</nav>
			<?php
			if ( 'settings' === $tab ) {
				$this->render_settings();
			} elseif ( 'redirects' === $tab ) {
				$this->render_redirects();
			} else {
				$this->render_list();
			}
		}
		echo '</div>';
	}

	private function owner_label( $r, $groups ) {
		$g = isset( $groups[ $r->owner_type ] ) ? $groups[ $r->owner_type ] : null;
		if ( ! $g ) {
			return array( $r->owner_type . ' #' . $r->owner_id . ' (missing)', '' );
		}
		$o = Gruppa_SEO::owner( $r->owner_type, (int) $r->owner_id );
		return array( $o ? $o[1]->title : '#' . $r->owner_id . ' (missing)', isset( $g['short'] ) ? $g['short'] : $g['name'] );
	}

	private function render_list() {
		$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
		$rows  = Gruppa_SEO::all( $s );
		$types = Gruppa_SEO::owners();
		?>
		<table class="wp-list-table widefat fixed gruppa-table">
			<thead>
				<tr>
					<th class="gruppa-col-id">ID</th>
					<th>Owner</th>
					<th>SEO title</th>
					<th class="gruppa-col-robots">Robots</th>
					<th>Modified</th>
					<th class="gruppa-col-actions">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="6">No SEO yet. Add SEO → choose an item (e.g. Stránky → Domov).</td></tr>
			<?php endif; ?>
			<?php foreach ( $rows as $r ) :
				list( $title, $tname ) = $this->owner_label( $r, $types );
				$edit = self::url( array( 'view' => 'edit', 'id' => $r->id ) );
				$url  = Gruppa_SEO::owner_url( $r->owner_type, $r->owner_id );
				$del  = wp_nonce_url( add_query_arg( array( 'action' => 'gruppa_seo_delete', 'id' => (int) $r->id ), admin_url( 'admin-post.php' ) ), 'gruppa_seo_delete_' . (int) $r->id );
				?>
				<tr>
					<td><?php echo (int) $r->id; ?></td>
					<td><strong><a class="row-title" href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ); ?></a></strong> <span class="gruppa-count"><?php echo esc_html( $tname ); ?></span></td>
					<td><?php echo esc_html( '' !== $r->seo_title ? $r->seo_title : '—' ); ?></td>
					<td><?php echo esc_html( Gruppa_SEO::ROBOTS[ Gruppa_SEO::robots( $r->seo_robots ) ] ); ?></td>
					<td><?php echo esc_html( $r->modified ? mysql2date( 'Y/m/d H:i', $r->modified ) : '—' ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit ); ?>">Edit</a>
						<?php if ( $url ) : ?> | <a href="<?php echo esc_url( $url ); ?>" target="_blank">View</a><?php endif; ?>
						| <a class="gruppa-delete" href="<?php echo esc_url( $del ); ?>" onclick="return confirm('Delete SEO of this item?')">Delete</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ---------- Editor ---------- */

	private static function image_field( $name, $aid, $hint = '' ) {
		$src = $aid ? wp_get_attachment_image_url( (int) $aid, 'medium' ) : '';
		printf(
			'<div class="gseo-image%s" data-hint="%s"><input type="hidden" name="%s" value="%d"><img src="%s" alt=""><span class="gseo-image-hint">%s</span><span class="gseo-image-actions"><button type="button" class="button gseo-image-pick">Select image</button> <button type="button" class="button-link button-link-delete gseo-image-remove">Remove</button></span></div>',
			$src ? ' has-image' : '', esc_attr( $hint ), esc_attr( $name ), (int) $aid, esc_url( $src ), esc_html( $hint )
		);
	}

	private function render_edit( $id ) {
		$seo = $id ? Gruppa_SEO::get( $id ) : null;
		if ( $id && ! $seo ) {
			self::not_found( self::url(), 'This SEO record' );
			return;
		}
		$set    = Gruppa_SEO::settings();
		$owners = Gruppa_SEO::owners();
		$taken  = array();
		foreach ( Gruppa_SEO::all() as $r ) {
			$taken[ $r->owner_type . ':' . $r->owner_id ] = (int) $r->id;
		}
		$pre = isset( $_GET['owner'] ) ? sanitize_text_field( wp_unslash( $_GET['owner'] ) ) : ''; // phpcs:ignore
		if ( ! $seo && $pre && isset( $taken[ $pre ] ) ) {
			// Item already has SEO → open it.
			$seo = Gruppa_SEO::get( $taken[ $pre ] );
			$id  = (int) $seo->id;
		}
		$v = function ( $k, $def = '' ) use ( $seo ) {
			return $seo && isset( $seo->{$k} ) && null !== $seo->{$k} ? $seo->{$k} : $def;
		};

		// Owner data for the previews.
		$meta = array();
		foreach ( $owners as $otype => $g ) {
			foreach ( $g['items'] as $iid => $ititle ) {
				$pid = $g['posts'][ $iid ];
				$meta[ $otype . ':' . $iid ] = array( 'title' => $ititle, 'type' => $g['name'], 'url' => $pid ? (string) get_permalink( $pid ) : ( 'tax' === $g['source'] ? Gruppa_SEO::owner_url( $otype, $iid ) : '' ) );
			}
		}
		$cur = $seo ? $seo->owner_type . ':' . $seo->owner_id : $pre;
		$default_img = $set['og_image'] ? wp_get_attachment_image_url( (int) $set['og_image'], 'large' ) : '';
		$heading     = 'Add SEO';
		if ( $seo && isset( $meta[ $cur ] ) ) {
			$heading = 'SEO: ' . $meta[ $cur ]['title'];
		}
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
		<a href="<?php echo esc_url( self::url() ); ?>" class="page-title-action">Back</a>
		<?php if ( $seo && ! empty( $meta[ $cur ]['url'] ) ) : ?>
			<a href="<?php echo esc_url( $meta[ $cur ]['url'] ); ?>" class="page-title-action" target="_blank">View</a>
		<?php endif; ?>
		<?php if ( $seo ) :
			list( $ct, $ci ) = Gruppa_SEO_Owners::split( $cur );
			?>
			<a href="<?php echo esc_url( Gruppa_SEO_Owners::edit_url( $ct, $ci ) ); ?>" class="page-title-action">Edit item</a>
			<a href="<?php echo esc_url( self::url( array( 'view' => 'edit' ) ) ); ?>" class="page-title-action">Add SEO</a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gseo-form">
			<input type="hidden" name="action" value="gruppa_seo_save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<?php wp_nonce_field( 'gruppa_seo_save' ); ?>

			<h2 class="gruppa-fields-title">Main Settings</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-owner">Owner <span class="gruppa-req">*</span></label></th>
						<td>
							<?php if ( $seo ) : ?>
								<input type="hidden" id="gseo-owner" value="<?php echo esc_attr( $cur ); ?>">
								<strong><?php echo esc_html( isset( $meta[ $cur ] ) ? $meta[ $cur ]['type'] . ' → ' . $meta[ $cur ]['title'] : $cur . ' (missing)' ); ?></strong>
							<?php elseif ( ! $owners ) : ?>
								<p>No items with a page yet. SEO is available for items of <?php echo esc_html( self::sources_text() ); ?>.</p>
							<?php else : ?>
								<select name="owner" id="gseo-owner" required>
									<option value="">— Select item —</option>
									<?php foreach ( $owners as $slug => $g ) : ?>
										<optgroup label="<?php echo esc_attr( $g['name'] ); ?>">
											<?php foreach ( $g['items'] as $iid => $ititle ) :
												$key = $slug . ':' . $iid;
												?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cur, $key ); ?> <?php disabled( isset( $taken[ $key ] ) ); ?>><?php echo esc_html( $ititle . ( isset( $taken[ $key ] ) ? ' (has SEO)' : '' ) ); ?></option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>
								</select>
								<p class="description">Only items of <?php echo esc_html( self::sources_text() ); ?>. Items that already have SEO are disabled.</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="gseo-robots">Robots</label></th>
						<td>
							<select name="seo_robots" id="gseo-robots">
								<?php foreach ( Gruppa_SEO::ROBOTS as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( Gruppa_SEO::robots( $v( 'seo_robots', 'index,follow' ) ), $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">noindex = hidden from Google and left out of the sitemap.</p>
						</td>
					</tr>
					<tr>
						<th><label for="gseo-title">SEO title</label></th>
						<td><input type="text" id="gseo-title" name="seo_title" value="<?php echo esc_attr( $v( 'seo_title' ) ); ?>" data-count="60"><span class="gseo-count" data-for="gseo-title"></span></td>
					</tr>
					<tr>
						<th><label for="gseo-desc">Meta description</label></th>
						<td><textarea id="gseo-desc" name="seo_description" rows="3" data-count="155"><?php echo esc_textarea( $v( 'seo_description' ) ); ?></textarea><span class="gseo-count" data-for="gseo-desc"></span></td>
					</tr>
					<tr>
						<th><label for="gseo-keywords">Keywords</label></th>
						<td><input type="text" id="gseo-keywords" name="seo_keywords" value="<?php echo esc_attr( $v( 'seo_keywords' ) ); ?>" placeholder="keyword, keyword"></td>
					</tr>
					<tr>
						<th><label for="gseo-canonical">Canonical URL</label></th>
						<td><input type="url" id="gseo-canonical" name="seo_canonical" value="<?php echo esc_attr( $v( 'seo_canonical' ) ); ?>"><p class="description">Empty = address of the item.</p></td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Preview</h2>
			<div class="gseo-previews">
				<div class="gruppa-card gseo-google">
					<div class="gseo-preview-label">Google</div>
					<div class="gseo-g-site"><span class="gseo-g-favicon"></span><span><span class="gseo-g-name"></span><span class="gseo-g-url"></span></span></div>
					<div class="gseo-g-title"></div>
					<div class="gseo-g-desc"></div>
					<div class="gseo-g-noindex" hidden>noindex — this page will not appear in Google.</div>
				</div>
				<div class="gruppa-card gseo-facebook">
					<div class="gseo-preview-label">Facebook · LinkedIn · WhatsApp</div>
					<div class="gseo-fb">
						<div class="gseo-fb-img"></div>
						<div class="gseo-fb-body">
							<div class="gseo-fb-domain"></div>
							<div class="gseo-fb-title"></div>
							<div class="gseo-fb-desc"></div>
						</div>
					</div>
				</div>
			</div>

			<h2 class="gruppa-fields-title">Open Graph</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-og-title">OG title</label></th>
						<td><input type="text" id="gseo-og-title" name="seo_og_title" value="<?php echo esc_attr( $v( 'seo_og_title' ) ); ?>" placeholder="Empty = SEO title"></td>
					</tr>
					<tr>
						<th><label for="gseo-og-desc">OG description</label></th>
						<td><textarea id="gseo-og-desc" name="seo_og_description" rows="2" placeholder="Empty = meta description"><?php echo esc_textarea( $v( 'seo_og_description' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th>OG image</th>
						<td>
							<?php self::image_field( 'seo_og_image', (int) $v( 'seo_og_image', 0 ), $default_img ? 'Empty = default image from Settings' : 'Recommended 1200 × 630 px' ); ?>
						</td>
					</tr>
					<tr>
						<th><label for="gseo-og-type">OG type</label></th>
						<td>
							<select name="seo_og_type" id="gseo-og-type">
								<option value="">Automatic (Posts = article, other = website)</option>
								<?php foreach ( Gruppa_SEO::OG_TYPES as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'seo_og_type' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Twitter / X</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-tw-card">Card</label></th>
						<td>
							<select name="seo_twitter_card" id="gseo-tw-card">
								<option value="">Automatic (large image)</option>
								<?php foreach ( Gruppa_SEO::CARDS as $k => $l ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $v( 'seo_twitter_card' ), $k ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="gseo-tw-title">Title</label></th>
						<td><input type="text" id="gseo-tw-title" name="seo_twitter_title" value="<?php echo esc_attr( $v( 'seo_twitter_title' ) ); ?>" placeholder="Empty = OG title"></td>
					</tr>
					<tr>
						<th><label for="gseo-tw-desc">Description</label></th>
						<td><textarea id="gseo-tw-desc" name="seo_twitter_description" rows="2" placeholder="Empty = OG description"><?php echo esc_textarea( $v( 'seo_twitter_description' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th>Image</th>
						<td><?php self::image_field( 'seo_twitter_image', (int) $v( 'seo_twitter_image', 0 ), 'Empty = OG image' ); ?></td>
					</tr>
				</table>
			</div>

			<h2 class="gruppa-fields-title">Advanced</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-schema">Custom JSON-LD</label></th>
						<td>
							<textarea id="gseo-schema" name="seo_schema" rows="6" class="code" placeholder='{"@context":"https://schema.org","@type":"LocalBusiness", …}'><?php echo esc_textarea( $v( 'seo_schema' ) ); ?></textarea>
							<p class="description">Empty = automatic structured data (WebSite, WebPage, Article, Organization from Settings). Filled = replaces it.</p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit"><button type="submit" class="button button-primary"><?php echo $seo ? 'Update' : 'Publish'; ?></button></p>
		</form>
		<script>window.gruppaSEO = <?php echo wp_json_encode( array(
			'owners'    => $meta,
			'template'  => $set['title_template'],
			'sep'       => $set['separator'],
			'site'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'home'      => home_url( '/' ),
			'defaultImg' => $default_img,
		) ); ?>;</script>
		<?php
	}

	/* ---------- Settings ---------- */

	private function render_settings() {
		$s = Gruppa_SEO::settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gseo-form">
			<input type="hidden" name="action" value="gruppa_seo_settings">
			<?php wp_nonce_field( 'gruppa_seo_settings' ); ?>
			<h2 class="gruppa-fields-title">Titles</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-tpl">Title template</label></th>
						<td><input type="text" id="gseo-tpl" name="seo[title_template]" value="<?php echo esc_attr( $s['title_template'] ); ?>"><p class="description">For items without their own SEO title. {title}, {sep}, {site}, {type}</p></td>
					</tr>
					<tr>
						<th><label for="gseo-sep">Separator</label></th>
						<td><input type="text" id="gseo-sep" name="seo[separator]" value="<?php echo esc_attr( $s['separator'] ); ?>" class="small-text"></td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Post types and taxonomies</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th>Post types</th>
						<td>
							<div class="gseo-pts">
								<?php foreach ( Gruppa_SEO_Owners::wp_post_type_choices() as $pt => $label ) : ?>
									<label><input type="checkbox" name="seo[post_types][]" value="<?php echo esc_attr( $pt ); ?>" <?php checked( in_array( $pt, (array) $s['post_types'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</div>
							<p class="description">Pages of these post types can have SEO (list, Add SEO, box SEO in the editor). Items of Gruppa CMS content types and JetEngine CCT with a single page always can.</p>
						</td>
					</tr>
					<tr>
						<th>Taxonomies</th>
						<td>
							<div class="gseo-pts">
								<?php foreach ( Gruppa_SEO_Owners::wp_taxonomy_choices() as $tx => $label ) : ?>
									<label><input type="checkbox" name="seo[taxonomies][]" value="<?php echo esc_attr( $tx ); ?>" <?php checked( in_array( $tx, (array) $s['taxonomies'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</div>
							<p class="description">Archive pages of these taxonomies (e.g. a category) can have SEO — box SEO on the edit screen of the term.</p>
						</td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Types</h2>
			<div class="gruppa-card">
				<p class="gruppa-muted gruppa-help-text">Defaults for pages without their own SEO value. Placeholders: {title} {sep} {site} {type} {excerpt} {description} {date} {year} {field:key}. Empty = the title template above, no description, index, follow, featured image or the default image.</p>
				<table class="wp-list-table widefat fixed gruppa-table gseo-types">
					<thead><tr><th>Type</th><th>Title template</th><th>Description template</th><th class="gruppa-col-robots">Robots</th><th>Image</th></tr></thead>
					<tbody>
					<?php foreach ( Gruppa_SEO_Owners::type_list() as $otype => $tlabel ) :
						$t = Gruppa_SEO::type_template( $otype );
						$n = 'seo[types][' . $otype . ']';
						?>
						<tr>
							<td><strong><?php echo esc_html( $tlabel ); ?></strong><br><code><?php echo esc_html( $otype ); ?></code></td>
							<td><input type="text" name="<?php echo esc_attr( $n ); ?>[title]" value="<?php echo esc_attr( $t['title'] ); ?>" placeholder="<?php echo esc_attr( $s['title_template'] ); ?>" aria-label="Title template"></td>
							<td><textarea name="<?php echo esc_attr( $n ); ?>[description]" rows="2" placeholder="{excerpt}" aria-label="Description template"><?php echo esc_textarea( $t['description'] ); ?></textarea></td>
							<td>
								<select name="<?php echo esc_attr( $n ); ?>[robots]" aria-label="Robots">
									<option value="">Default</option>
									<?php foreach ( Gruppa_SEO::ROBOTS as $k => $l ) : ?>
										<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $t['robots'], $k ); ?>><?php echo esc_html( $l ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><?php self::image_field( $n . '[image]', (int) $t['image'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Social</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr><th>Default OG image</th><td><?php self::image_field( 'seo[og_image]', (int) $s['og_image'], 'Used when an item has no image. 1200 × 630 px' ); ?></td></tr>
					<tr>
						<th><label for="gseo-tw">Twitter / X account</label></th>
						<td><input type="text" id="gseo-tw" name="seo[twitter_site]" value="<?php echo esc_attr( $s['twitter_site'] ); ?>" placeholder="@account"></td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Organization</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-org">Name</label></th>
						<td><input type="text" id="gseo-org" name="seo[org_name]" value="<?php echo esc_attr( $s['org_name'] ); ?>" placeholder="Gigastars creative s.r.o."><p class="description">Adds Organization to structured data.</p></td>
					</tr>
					<tr><th>Logo</th><td><?php self::image_field( 'seo[org_logo]', (int) $s['org_logo'], 'Square or wide logo, PNG / JPG' ); ?></td></tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Verification</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th><label for="gseo-google">Google Search Console</label></th>
						<td><input type="text" id="gseo-google" name="seo[google_verify]" value="<?php echo esc_attr( $s['google_verify'] ); ?>" placeholder="code or the whole &lt;meta&gt; tag"></td>
					</tr>
					<tr>
						<th><label for="gseo-bing">Bing Webmaster</label></th>
						<td><input type="text" id="gseo-bing" name="seo[bing_verify]" value="<?php echo esc_attr( $s['bing_verify'] ); ?>" placeholder="code or the whole &lt;meta&gt; tag"></td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Sitemap</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th>Sitemap</th>
						<td>
							<label><input type="checkbox" name="seo[sitemap]" value="1" <?php checked( ! empty( $s['sitemap'] ) ); ?>> WordPress sitemap on</label>
							<?php if ( ! empty( $s['sitemap'] ) ) : ?> · <a href="<?php echo esc_url( home_url( '/wp-sitemap.xml' ) ); ?>" target="_blank">wp-sitemap.xml</a><?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>Content</th>
						<td>
							<label><input type="checkbox" name="seo[sitemap_only]" value="1" <?php checked( ! empty( $s['sitemap_only'] ) ); ?>> Only the post types and taxonomies with SEO (above) and Gruppa CMS content types</label><br>
							<label><input type="checkbox" name="seo[sitemap_users]" value="1" <?php checked( ! empty( $s['sitemap_users'] ) ); ?>> Author pages</label>
							<p class="description">Pages and terms with noindex, and types set to noindex above, are never in the sitemap.</p>
						</td>
					</tr>
				</table>
			</div>
			<h2 class="gruppa-fields-title">Breadcrumbs</h2>
			<div class="gruppa-card">
				<table class="form-table gruppa-type-table" role="presentation">
					<tr>
						<th>Structured data</th>
						<td><label><input type="checkbox" name="seo[breadcrumbs]" value="1" <?php checked( ! empty( $s['breadcrumbs'] ) ); ?>> BreadcrumbList in JSON-LD (Google shows the path instead of the URL)</label></td>
					</tr>
					<tr>
						<th><label for="gseo-bc-home">Home label</label></th>
						<td><input type="text" id="gseo-bc-home" name="seo[bc_home]" value="<?php echo esc_attr( $s['bc_home'] ); ?>" placeholder="Home"></td>
					</tr>
					<tr>
						<th><label for="gseo-bc-sep">Separator</label></th>
						<td><input type="text" id="gseo-bc-sep" name="seo[bc_sep]" value="<?php echo esc_attr( $s['bc_sep'] ); ?>" class="small-text"><p class="description">On the website: shortcode [gruppa_breadcrumbs] (also in the Elementor Shortcode widget), options home="…" sep="…" current="0".</p></td>
					</tr>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Save Settings</button></p>
		</form>
		<?php
	}
}
