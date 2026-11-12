<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa CMS → Architecture (0.32): the whole structure of the website on one screen.
 *
 * Content types and data types are no longer separate items of the main WordPress menu (they moved around
 * with every new type). The main menu keeps only Gruppa CMS and Gruppa Tools; the items of every type are
 * reached here: tree of types (data types under the content types they are attached to), counts, where
 * each type is used (listings, filters, forms, tables) and the links All items / Add item / Edit type.
 * The same table is the Structure map on the Dashboard.
 */
class Gruppa_Admin_Architecture {

	const PAGE = 'gruppa-architecture';
	const CAP  = 'manage_options';

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * What builds on each type: [ slug => [ 'listings' => n, 'filters' => n, 'tables' => n, 'forms' => n ] ].
	 */
	public static function usage() {
		$out = array();
		$add = function ( $slug, $key ) use ( &$out ) {
			if ( '' === (string) $slug ) {
				return;
			}
			if ( ! isset( $out[ $slug ] ) ) {
				$out[ $slug ] = array( 'listings' => 0, 'filters' => 0, 'tables' => 0, 'forms' => 0 );
			}
			$out[ $slug ][ $key ]++;
		};
		foreach ( Gruppa_Listing::all() as $l ) {
			$add( $l->type_slug, 'listings' );
		}
		foreach ( Gruppa_Filters::all() as $f ) {
			$add( $f->type_slug, 'filters' );
		}
		foreach ( Gruppa_Forms::all() as $f ) {
			$add( $f->type_slug, 'forms' );
		}
		if ( class_exists( 'Gruppa_TB_DB' ) ) {
			foreach ( Gruppa_TB_DB::all() as $t ) {
				$origin = isset( $t->settings['origin'] ) ? $t->settings['origin'] : 'gruppa';
				if ( 'gruppa' === $origin ) {
					$add( $t->type_slug, 'tables' );
				}
			}
		}
		return $out;
	}

	/** URL of the items list of a type (under a content type when it is shown there). */
	public static function items_url( $t, $parent = '' ) {
		return admin_url( '' !== $parent ? Gruppa_Menu::child_url( $t->slug, $parent ) : 'admin.php?page=' . Gruppa_Admin_Items::page_slug( $t->slug ) );
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		?>
		<div class="wrap gruppa-wrap gruppa-dash gruppa-arch">
			<h1 class="wp-heading-inline">Architecture</h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE . '&view=edit' ) ); ?>" class="page-title-action">Add Content Type</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE . '&view=edit' ) ); ?>" class="page-title-action">Add Data Type</a>
			<hr class="wp-header-end">
			<?php
			Gruppa_Admin::notice();
			if ( isset( $_GET['msg'] ) && 'menu_rebuilt' === $_GET['msg'] ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>The tree follows the data links again. Manual changes were removed.</p></div>';
			}
			?>
			<div class="gruppa-arch-layout">
				<div class="gruppa-arch-main"><?php self::table( self::PAGE ); ?></div>
				<?php self::nav(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Right panel: only the menu — every published type and the types under it, with item counts.
	 * Shown in Architecture and next to every items list (0.32.5).
	 *
	 * @param string $current        Slug of the type whose items are open ('' = none).
	 * @param string $current_parent Slug of its parent in the menu ('' = top level).
	 */
	public static function nav( $current = '', $current_parent = '' ) {
		$tree = Gruppa_Menu::tree( 'publish' );
		?>
		<aside class="gruppa-arch-nav" aria-label="Types">
			<div class="gruppa-arch-nav-head"><h2 class="gruppa-arch-nav-title">Menu</h2></div>
			<div class="gruppa-arch-nav-body">
			<?php if ( ! $tree ) : ?>
				<p class="gruppa-arch-nav-empty">No published type yet.</p>
			<?php else : ?>
				<ul>
					<?php foreach ( $tree as $node ) :
						list( $t, $kids ) = $node;
						?>
						<li>
							<a href="<?php echo esc_url( self::items_url( $t ) ); ?>"<?php echo $current === $t->slug && '' === $current_parent ? ' class="is-current" aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr( Gruppa_Menu::icon( $t ) ); ?>" aria-hidden="true"></span><?php echo esc_html( $t->name ); ?><span class="gruppa-arch-nav-n"><?php echo (int) Gruppa_DB::count_items( $t->slug ); ?></span></a>
							<?php if ( $kids ) : ?>
								<ul>
									<?php foreach ( $kids as $k ) : ?>
										<li><a href="<?php echo esc_url( self::items_url( $k, $t->slug ) ); ?>"<?php echo $current === $k->slug && $current_parent === $t->slug ? ' class="is-current" aria-current="page"' : ''; ?>><span class="dashicons <?php echo esc_attr( Gruppa_Menu::icon( $k ) ); ?>" aria-hidden="true"></span><?php echo esc_html( $k->name ); ?><span class="gruppa-arch-nav-n"><?php echo (int) Gruppa_Menu::counts_under( $k, $t->slug )['all']; ?></span></a></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="gruppa-arch-nav-add">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE . '&view=edit' ) ); ?>">+ Content Type</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE . '&view=edit' ) ); ?>">+ Data Type</a>
			</p>
			</div>
		</aside>
		<?php
	}

	/**
	 * Tree of types with counts, usage and links.
	 *
	 * @param string $back Page to return to after "Reset tree" (Architecture or Dashboard).
	 */
	public static function table( $back ) {
		$types = Gruppa_Types::all();
		$kinds = Gruppa_Types::kinds();
		$use   = self::usage();
		$tree  = Gruppa_Menu::resolve( '' );
		$pages = array(
			'listings' => array( Gruppa_Admin_Listings::PAGE, 'listing', 'listings' ),
			'filters'  => array( Gruppa_Admin_Filters::PAGE, 'filter group', 'filter groups' ),
			'tables'   => array( 'gruppa-table-builder', 'table', 'tables' ),
			'forms'    => array( Gruppa_Admin_Forms::PAGE, 'form', 'forms' ),
		);
		$cnt    = array();
		$manual = 0;
		foreach ( $types as $t ) {
			$cnt[ $t->slug ] = 'publish' === $t->status ? Gruppa_Items::counts( $t ) : array( 'all' => Gruppa_DB::count_items( $t->slug ), 'publish' => 0, 'draft' => 0 );
			$manual         += Gruppa_Menu::is_manual( $t ) ? 1 : 0;
		}
		?>
		<div class="gruppa-card gruppa-box" id="gruppa-structure">
		<div class="gruppa-box-head">
			<h2 class="gruppa-box-title"><?php echo self::PAGE === $back ? 'Structure' : 'Structure map'; ?></h2>
			<?php if ( $types && $manual ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gruppa-dash-rebuild" onsubmit="return confirm('Put every type back by its data links? Manual changes in the tree will be removed.');">
					<span class="gruppa-muted"><?php echo esc_html( sprintf( 1 === $manual ? '%d type placed manually' : '%d types placed manually', $manual ) ); ?></span>
					<input type="hidden" name="action" value="gruppa_menu_rebuild">
					<input type="hidden" name="back" value="<?php echo esc_attr( $back ); ?>">
					<?php wp_nonce_field( 'gruppa_menu_rebuild' ); ?>
					<button type="submit" class="button">Reset tree</button>
				</form>
			<?php elseif ( $types ) : ?>
				<span class="gruppa-muted gruppa-dash-rebuild">The tree follows the data links</span>
			<?php endif; ?>
		</div>
		<?php if ( ! $types ) : ?>
			<div class="gruppa-dash-empty gruppa-box-body">
				<p><strong>Start with a type</strong> — e.g. Products (content type) and Categories (data type). A type defines the fields; items, listings, filters, tables and forms build on it.</p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE . '&view=edit' ) ); ?>" class="button button-primary">Add Content Type</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE . '&view=edit' ) ); ?>" class="button">Add Data Type</a>
			</div>
		</div>
			<?php
			return;
		endif;
		?>
		<table class="wp-list-table widefat gruppa-table gruppa-dash-types gruppa-dash-structure gruppa-box-table">
			<thead>
				<tr>
					<th>Type</th>
					<th>Kind</th>
					<th class="gruppa-dash-n">Published</th>
					<th class="gruppa-dash-n">Draft</th>
					<th>Used by</th>
					<th class="gruppa-dash-actions"></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( Gruppa_Menu::tree( '' ) as $node ) :
				$list = array( array( $node[0], '' ) );
				foreach ( $node[1] as $kid ) {
					$list[] = array( $kid, $node[0]->slug );
				}
				foreach ( $list as $pair ) :
					list( $t, $parent ) = $pair;
					$c         = $parent && 'publish' === $t->status ? Gruppa_Menu::counts_under( $t, $parent ) : $cnt[ $t->slug ];
					$live      = 'publish' === $t->status;
					$items_url = self::items_url( $t, $parent );
					$edit_url  = admin_url( 'admin.php?page=' . Gruppa_Admin::page_of( $t->kind ) . '&view=edit&id=' . (int) $t->id );
					$u         = isset( $use[ $t->slug ] ) ? $use[ $t->slug ] : array();
					$notes     = array();
					if ( ! $live ) {
						$notes[] = array( '', 'Draft' );
					}
					if ( $parent && Gruppa_Menu::is_manual( $t ) ) {
						$notes[] = array( '', 'Placed manually' );
					}
					if ( ! $parent && 'data' === $t->kind && ! Gruppa_Menu::auto_parents( $t ) && ! array_sum( $u ) && empty( $tree['children'][ $t->slug ] ) ) {
						$notes[] = array( 'is-warn', 'Not used yet' );
					}
					?>
					<tr class="<?php echo $parent ? 'is-child' : 'is-root'; ?>">
						<td>
							<span class="dashicons <?php echo esc_attr( Gruppa_Menu::icon( $t ) ); ?> gruppa-tree-icon" aria-hidden="true"></span>
							<strong><a class="row-title" href="<?php echo esc_url( $live ? $items_url : $edit_url ); ?>"><?php echo esc_html( $t->name ); ?></a></strong>
							<?php foreach ( $notes as $n ) : ?>
								<span class="gruppa-badge <?php echo esc_attr( $n[0] ); ?>"><?php echo esc_html( $n[1] ); ?></span>
							<?php endforeach; ?>
						</td>
						<td><?php echo esc_html( isset( $kinds[ $t->kind ] ) ? $kinds[ $t->kind ] : $t->kind ); ?></td>
						<td class="gruppa-dash-n"><?php echo $live ? (int) $c['publish'] : '—'; ?></td>
						<td class="gruppa-dash-n"><?php echo $live ? (int) $c['draft'] : '—'; ?></td>
						<td class="gruppa-dash-uses">
							<?php
							$any = false;
							foreach ( $pages as $k => $pg ) {
								if ( empty( $u[ $k ] ) ) {
									continue;
								}
								$any = true;
								printf( '<a href="%s">%d %s</a>', esc_url( admin_url( 'admin.php?page=' . $pg[0] ) ), (int) $u[ $k ], esc_html( 1 === (int) $u[ $k ] ? $pg[1] : $pg[2] ) );
							}
							if ( ! $any ) {
								echo '<span class="gruppa-muted">—</span>';
							}
							?>
						</td>
						<td class="gruppa-dash-actions">
							<?php if ( $live ) : ?>
								<a href="<?php echo esc_url( $items_url ); ?>">All items</a> |
								<a href="<?php echo esc_url( add_query_arg( 'view', 'edit', $items_url ) ); ?>">Add item</a> |
							<?php endif; ?>
							<a href="<?php echo esc_url( $edit_url ); ?>">Edit type</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="gruppa-muted gruppa-help-text">Data types attached to a content type sit under it. Order and placement: <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE ) ); ?>">Content Types</a> and <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE ) ); ?>">Data Types</a>.</p>
		<?php
	}
}
