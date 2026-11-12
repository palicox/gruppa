# Gruppa Tools hub 1.6.2

Shared folder bundled — identical — in every Gruppa Tools plugin: **Import/Export, Table Builder, SEO Builder,
Payments, Notify**. Several plugins may ship different versions; only the newest is loaded, once. "Gruppa Tools"
is the name of the shared admin menu, not a plugin (the Gruppa Tools 4–6 bundle is no longer developed).
The Gruppa theme adds **Theme Builder** to the same menu while the theme is active.

Every Gruppa Tools plugin works with Gruppa CMS and without it — on plain WordPress, JetEngine and ACF.

## New plugin in 4 steps

1. Copy this `hub/` folder into the plugin.
2. In the main plugin file: `require_once __DIR__ . '/hub/loader.php';`
3. On `plugins_loaded` (priority 2+) register the page:

```php
Gruppa_Tools_Hub::add_module( array(
	'slug'     => 'gruppa-payments',       // admin page slug
	'title'    => 'Payments',              // menu label
	'callback' => array( $this, 'render' ),
	'version'  => GRUPPA_PAY_VERSION,
	// 'order'    => 70,                   // only for modules not listed in Gruppa_Tools_Hub::ORDER
	// 'tutorial' => 'https://…/manual.pdf', // optional; default https://gruppa.cloud/{slug_with_underscores}_tutorial.pdf
) );
```

4. Render with the Gruppa CMS markup — the stylesheet (handle `gruppa-admin`) is loaded automatically:
   `.wrap.gruppa-wrap` › `h1.wp-heading-inline` + `hr.wp-header-end` › `nav.gruppa-tabs` › `.gruppa-card` with
   `table.form-table.gruppa-type-table`, lists as `table.wp-list-table.widefat.fixed.gruppa-table`, switch `label.gruppa-switch`.
   Own page CSS: enqueue with dependency `array( 'gruppa-admin' )`.

## Menu order (`Gruppa_Tools_Hub::ORDER`)

| Order | Module | Page slug |
|---|---|---|
| 10 | Import/Export | gruppa-import-export |
| 20 | Theme Builder (Gruppa theme, only while active) | gruppa-theme-builder |
| 30 | Table Builder | gruppa-table-builder |
| 40 | SEO Builder | gruppa-seo |
| 50 | Payments | gruppa-payments |
| 60 | Notify | gruppa-notify |

The fixed order wins over the `order` a plugin passes (older hub copies kept their own numbers).

## Database

Tables use the Gruppa system prefix `wp_gcms_{name}`: `wp_gcms_tables` (Table Builder), `wp_gcms_seo` (SEO Builder),
`wp_gcms_notify_subs`, `wp_gcms_payments`, `wp_gcms_payment_events`. Items of Gruppa CMS types are `wp_gcms_type_{slug}`.
Each plugin deletes its own data when it is deleted, only with Gruppa CMS → Dashboard → Data → "Delete the data of
a Gruppa Tools plugin when it is deleted" (option `gruppa_tools_delete_data`).

## Behaviour

* Menu "Gruppa Tools" sits right below "Gruppa CMS"; without Gruppa CMS on its own (position 26).
* The top-level link opens the first module (lowest order).
* `gruppa-ui.css` = copy of the Gruppa CMS admin.css (0.32.4: Gruppa colours, 24px rhythm, cells 12px 24px); with Gruppa CMS active, its own file is used.
* `boxes.js` (1.6.0) = box titles inside the boxes (h2.gruppa-fields-title before a .gruppa-card or a list table); same file as in Gruppa CMS.
* Header: **Help** switch (ⓘ bubbles) and **Tutorial** link (`Gruppa_Tools_Hub::tutorial_url( $slug )`, filter `gruppa_tools/tutorial_url`, '' hides it).
* Notice: old all-in-one Gruppa Tools 1.x–3.x and the discontinued Gruppa Tools 4–6 bundle.
* Helpers: `Gruppa_Tools_Hub::url( $slug, $args )`, `::cms_active()`, `::has_module( $slug )`, `::modules()`, `::menu_slug()`.
* When releasing a hub change, bump `$gruppa_tools_hub_version` in loader.php and copy the folder to all plugins.
