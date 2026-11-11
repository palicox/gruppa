<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gruppa CMS → Dashboard: overview, Structure map (= Architecture table), shortcuts, Gruppa Tools, system, data settings.
 */
class Gruppa_Admin_Dashboard {

	const PAGE   = 'gruppa-dashboard';
	const CAP    = 'manage_options';
	const DELETE = 'gruppa_cms_delete_data';

	public function __construct() {
		add_action( 'admin_post_gruppa_dashboard_data', array( $this, 'handle_data' ) );
		add_action( 'admin_post_gruppa_menu_rebuild', array( $this, 'handle_rebuild' ) );
		add_action( 'admin_notices', array( __CLASS__, 'tools_notice' ) );
	}

	/**
	 * 0.31: Table Builder and SEO Builder left Gruppa CMS for Gruppa Tools. When their data exist but
	 * the plugin does not run, say which plugin to install — the data stay and show up again.
	 */
	public static function tools_notice() {
		global $pagenow, $wpdb;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
		if ( ! current_user_can( 'activate_plugins' ) || ( 'plugins.php' !== $pagenow && 0 !== strpos( $page, 'gruppa-' ) ) ) {
			return;
		}
		$has_rows = function ( $table ) use ( $wpdb ) {
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				return false;
			}
			return (bool) $wpdb->get_var( "SELECT 1 FROM `{$table}` LIMIT 1" ); // phpcs:ignore
		};
		$miss = array();
		if ( ! class_exists( 'Gruppa_TB_DB' ) && $has_rows( $wpdb->prefix . 'gcms_tables' ) ) {
			$miss[] = 'Gruppa Table Builder';
		}
		if ( ! class_exists( 'Gruppa_SEO' ) && $has_rows( $wpdb->prefix . 'gcms_seo' ) ) {
			$miss[] = 'Gruppa SEO Builder';
		}
		if ( $miss ) {
			printf(
				'<div class="notice notice-warning"><p><strong>Gruppa CMS %1$s:</strong> Table Builder and SEO Builder are Gruppa Tools plugins now. Install and activate %2$s in <a href="%3$s">Plugins</a> — your tables and SEO records stay and appear again in the Gruppa Tools menu.</p></div>',
				esc_html( GRUPPA_CMS_VERSION ),
				esc_html( implode( ' and ', $miss ) ),
				esc_url( admin_url( 'plugins.php' ) )
			);
		}
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public function handle_data() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_dashboard_data' );
		update_option( self::DELETE, empty( $_POST['delete_data'] ) ? 0 : 1, false );
		update_option( 'gruppa_tools_delete_data', empty( $_POST['delete_tools_data'] ) ? 0 : 1, false );
		wp_safe_redirect( self::url( array( 'msg' => 'saved' ) ) );
		exit;
	}

	/** Reset tree: every type back to its data links (manual changes removed). */
	public function handle_rebuild() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'gruppa_menu_rebuild' );
		Gruppa_Menu::rebuild();
		$back = isset( $_POST['back'] ) && Gruppa_Admin_Architecture::PAGE === $_POST['back'] ? Gruppa_Admin_Architecture::url( array( 'msg' => 'menu_rebuilt' ) ) : self::url( array( 'msg' => 'menu_rebuilt' ) ) . '#gruppa-structure'; // phpcs:ignore
		wp_safe_redirect( $back );
		exit;
	}

	/** Gruppa Tools (separate plugins + Theme Builder of the Gruppa theme): active / installed / not installed. */
	private static function addons() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = array();
		foreach ( get_plugins() as $file => $data ) {
			$installed[ dirname( $file ) ] = array( $file, $data['Version'] );
		}
		$active = (array) get_option( 'active_plugins', array() );
		// Gruppa Tools menu order: Import/Export 10, Theme Builder 20, Table Builder 30, SEO Builder 40, Payments 50, Notify 60.
		$list   = array(
			array( 'Import/Export', 'gruppa-import-export', 'GRUPPA_IE_VERSION', 'gruppa-import-export', 'CSV and JSON import and export for Gruppa CMS types, JetEngine CCT and WordPress with ACF.' ),
			array( 'Theme Builder', '', 'GRUPPA_THEME_VERSION', 'gruppa-theme-builder', 'Colours, fonts, buttons and forms of the Gruppa theme.' ),
			array( 'Table Builder', 'gruppa-table-builder', 'GRUPPA_TB_VERSION', 'gruppa-table-builder', 'Tables from Gruppa CMS types, JetEngine CCT, WordPress and ACF, or from Repeater rows.' ),
			array( 'SEO Builder', 'gruppa-seo-builder', 'GRUPPA_SEO_VERSION', 'gruppa-seo', 'Titles, descriptions, Open Graph and JSON-LD for items, CCT items, pages and posts.' ),
			array( 'Payments', 'gruppa-payments', 'GRUPPA_PAY_VERSION', 'gruppa-payments', 'Stripe and PayPal payments and subscriptions — form action, pay button, own payments table.' ),
			array( 'Notify', 'gruppa-notify', 'GRUPPA_NOTIFY_VERSION', 'gruppa-notify', 'Web Push notifications without external services.' ),
		);
		$out = array();
		foreach ( $list as $a ) {
			list( $name, $dir, $const, $page, $desc ) = $a;
			if ( '' === $dir ) { // Theme Builder: part of the Gruppa theme, shown in Gruppa Tools only while the theme is active.
				if ( defined( $const ) ) {
					$state = array( 'active', 'Active ' . constant( $const ), admin_url( 'admin.php?page=' . $page ), 'Open' );
				} elseif ( wp_get_theme( 'gruppa' )->exists() ) {
					$state = array( 'inactive', 'Theme installed, not active', admin_url( 'themes.php' ), 'Themes' );
				} else {
					$state = array( 'none', 'Gruppa theme not installed', '', '' );
				}
				$out[] = array( $name, $desc, $state );
				continue;
			}
			$on = defined( $const ) || ( isset( $installed[ $dir ] ) && in_array( $installed[ $dir ][0], $active, true ) );
			if ( $on ) {
				$ver   = defined( $const ) ? constant( $const ) : ( isset( $installed[ $dir ] ) ? $installed[ $dir ][1] : '' );
				$state = array( 'active', trim( 'Active ' . $ver ), admin_url( 'admin.php?page=' . $page ), 'Open' );
			} elseif ( isset( $installed[ $dir ] ) ) {
				$state = array( 'inactive', 'Installed, not active', admin_url( 'plugins.php' ), 'Activate' );
			} else {
				$state = array( 'none', 'Not installed', '', '' );
			}
			$out[] = array( $name, $desc, $state );
		}
		return $out;
	}

	private static function language() {
		$locale = get_locale();
		$file   = GRUPPA_CMS_DIR . 'languages/gruppa-cms-' . $locale . '.mo';
		if ( 0 === strpos( $locale, 'en_' ) ) {
			return array( $locale, 'English' );
		}
		return file_exists( $file ) || file_exists( WP_LANG_DIR . '/plugins/gruppa-cms-' . $locale . '.mo' )
			? array( $locale, 'translated' )
			: array( $locale, 'no translation yet — English texts' );
	}

	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$types = Gruppa_Types::all();
		$items = 0;
		$cnt   = array();
		foreach ( $types as $t ) {
			$c               = 'publish' === $t->status ? Gruppa_Items::counts( $t ) : array( 'all' => Gruppa_DB::count_items( $t->slug ), 'publish' => 0, 'draft' => 0 );
			$items          += (int) $c['all'];
			$cnt[ $t->slug ] = $c;
		}
		$n_data = 0;
		foreach ( $types as $t ) {
			$n_data += 'data' === $t->kind ? 1 : 0;
		}
		$stats = array(
			array( count( $types ) - $n_data, 'Content types', admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE ) ),
			array( $n_data, 'Data types', admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE ) ),
			array( $items, 'Items', '' ),
			array( count( Gruppa_Listing::all() ), 'Listings', admin_url( 'admin.php?page=' . Gruppa_Admin_Listings::PAGE ) ),
			array( count( Gruppa_Filters::all() ), 'Filter groups', admin_url( 'admin.php?page=' . Gruppa_Admin_Filters::PAGE ) ),
			array( count( Gruppa_Forms::all() ), 'Forms', admin_url( 'admin.php?page=' . Gruppa_Admin_Forms::PAGE ) ),
		);
		// Gruppa Tools with data on Gruppa CMS types (only when the plugin runs).
		if ( class_exists( 'Gruppa_TB_DB' ) ) {
			$stats[] = array( count( Gruppa_TB_DB::all() ), 'Tables', admin_url( 'admin.php?page=gruppa-table-builder' ) );
		}
		if ( class_exists( 'Gruppa_SEO' ) ) {
			$stats[] = array( (int) Gruppa_SEO::count(), 'SEO records', admin_url( 'admin.php?page=gruppa-seo' ) );
		}
		$lang = self::language();
		global $wp_version, $wpdb;
		?>
		<div class="wrap gruppa-wrap gruppa-dash">
			<h1 class="wp-heading-inline">Gruppa CMS <span class="gruppa-dash-ver"><?php echo esc_html( GRUPPA_CMS_VERSION ); ?></span></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE . '&view=edit' ) ); ?>" class="page-title-action">Add Content Type</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE . '&view=edit' ) ); ?>" class="page-title-action">Add Data Type</a>
			<hr class="wp-header-end">
			<?php
			Gruppa_Admin::notice();
			if ( isset( $_GET['msg'] ) && 'menu_rebuilt' === $_GET['msg'] ) { // phpcs:ignore
				echo '<div class="notice notice-success is-dismissible"><p>The tree follows the data links again. Manual changes were removed.</p></div>';
			}
			?>

			<div class="gruppa-dash-stats">
				<?php foreach ( $stats as $s ) : ?>
					<?php if ( $s[2] ) : ?>
						<a class="gruppa-card gruppa-dash-stat" href="<?php echo esc_url( $s[2] ); ?>">
					<?php else : ?>
						<div class="gruppa-card gruppa-dash-stat">
					<?php endif; ?>
						<span class="gruppa-dash-num"><?php echo esc_html( number_format_i18n( $s[0] ) ); ?></span>
						<span class="gruppa-dash-label"><?php echo esc_html( $s[1] ); ?></span>
					<?php echo $s[2] ? '</a>' : '</div>'; ?>
				<?php endforeach; ?>
			</div>

			<div class="gruppa-dash-grid">
				<div class="gruppa-dash-main">
					<?php Gruppa_Admin_Architecture::table( self::PAGE ); ?>

					<h2 class="gruppa-fields-title">Shortcuts</h2>
					<div class="gruppa-card gruppa-dash-links">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::PAGE . '&view=edit' ) ); ?>"><strong>New content type</strong><span>Items with a page — Page, Post or Custom Types</span></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin::DATA_PAGE . '&view=edit' ) ); ?>"><strong>New data type</strong><span>Values to pick — categories, records, groups</span></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Listings::PAGE . '&view=edit' ) ); ?>"><strong>New listing</strong><span>Grid of items with a card template</span></a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Filters::PAGE . '&view=edit' ) ); ?>"><strong>New filter group</strong><span>Search, select, range and date filters</span></a>
						<?php if ( class_exists( 'Gruppa_TB_DB' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=gruppa-table-builder&view=edit' ) ); ?>"><strong>New table</strong><span>Items or Repeater rows as a table</span></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Gruppa_Admin_Forms::PAGE . '&view=edit' ) ); ?>"><strong>New form</strong><span>Contact, reservation, registration, My profile</span></a>
						<?php if ( class_exists( 'Gruppa_SEO' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=gruppa-seo&tab=settings' ) ); ?>"><strong>SEO settings</strong><span>Title template, default image, organization</span></a>
						<?php endif; ?>
						<?php if ( defined( 'GRUPPA_IE_VERSION' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=gruppa-import-export' ) ); ?>"><strong>Import/Export</strong><span>CSV and JSON — Gruppa Tools</span></a>
						<?php endif; ?>
						<a href="<?php echo esc_url( Gruppa_Admin_Architecture::url() ); ?>"><strong>Architecture</strong><span>All types, their items and where they are used</span></a>
					</div>
				</div>

				<div class="gruppa-dash-side">
					<h2 class="gruppa-fields-title">Gruppa Tools</h2>
					<div class="gruppa-card gruppa-dash-addons">
						<?php foreach ( self::addons() as $a ) :
							list( $name, $desc, $st ) = $a;
							?>
							<div class="gruppa-dash-addon is-<?php echo esc_attr( $st[0] ); ?>">
								<div>
									<strong><?php echo esc_html( $name ); ?></strong>
									<span class="gruppa-dash-state"><?php echo esc_html( $st[1] ); ?></span>
									<p class="gruppa-muted"><?php echo esc_html( $desc ); ?></p>
								</div>
								<?php if ( $st[2] ) : ?>
									<a class="button" href="<?php echo esc_url( $st[2] ); ?>"><?php echo esc_html( $st[3] ); ?></a>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<h2 class="gruppa-fields-title">System</h2>
					<div class="gruppa-card">
						<dl class="gruppa-side-meta gruppa-dash-sys">
							<dt>Gruppa CMS</dt><dd><?php echo esc_html( GRUPPA_CMS_VERSION ); ?></dd>
							<dt>WordPress</dt><dd><?php echo esc_html( $wp_version ); ?></dd>
							<dt>PHP</dt><dd><?php echo esc_html( PHP_VERSION ); ?></dd>
							<dt>Database</dt><dd><?php echo esc_html( 'DB ' . get_option( 'gruppa_cms_db_version', '—' ) . ' · system ' . $wpdb->prefix . 'gcms_* · items ' . $wpdb->prefix . 'gcms_type_*' ); ?></dd>
							<dt>ACF</dt><dd><?php echo class_exists( 'ACF' ) ? 'Active' : 'Not active'; ?></dd>
							<dt>Elementor</dt><dd><?php echo did_action( 'elementor/loaded' ) ? 'Active' : 'Not active'; ?></dd>
							<dt>JetEngine</dt><dd><?php echo class_exists( 'Jet_Engine' ) ? 'Active' : 'Not active'; ?></dd>
							<dt>Website texts</dt><dd><?php echo esc_html( $lang[0] . ' · ' . $lang[1] ); ?></dd>
						</dl>
					</div>

					<h2 class="gruppa-fields-title">Data</h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gruppa-card gruppa-dash-data">
						<input type="hidden" name="action" value="gruppa_dashboard_data">
						<?php wp_nonce_field( 'gruppa_dashboard_data' ); ?>
						<label class="gruppa-dash-check">
							<input type="checkbox" name="delete_data" value="1" <?php checked( (bool) get_option( self::DELETE ) ); ?>>
							<span><strong>Delete all data when Gruppa CMS is deleted</strong></span>
						</label>
						<p class="gruppa-muted">Off: deleting the plugin keeps everything for a reinstall. On: content and data types with their items, forms, listings and filters are removed for good. Normal WordPress posts and pages linked to items, users, media and the data of Gruppa Tools stay (see the option below). Deactivating the plugin never deletes anything.</p>
						<label class="gruppa-dash-check">
							<input type="checkbox" name="delete_tools_data" value="1" <?php checked( (bool) get_option( 'gruppa_tools_delete_data' ) ); ?>>
							<span><strong>Delete the data of a Gruppa Tools plugin when it is deleted</strong></span>
						</label>
						<p class="gruppa-muted">Table Builder tables, SEO records and settings, Notify subscriptions with its keys, Payments settings and the payments table (payments stay in Stripe / PayPal). Import/Export keeps no data of its own.</p>
						<button type="submit" class="button">Save</button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
