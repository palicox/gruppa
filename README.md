# Gruppa

Ekosystém Gruppa pre WordPress — Gruppa Taxonomy, https://gruppa.cloud

| Balík | Priečinok | Verzia |
|---|---|---|
| Gruppa CMS (hlavný plugin) | `plugins/gruppa-cms` | 0.32.4 |
| Gruppa Import/Export | `plugins/gruppa-import-export` | 1.3.1 |
| Gruppa Table Builder | `plugins/gruppa-table-builder` | 0.7.1 |
| Gruppa SEO Builder | `plugins/gruppa-seo-builder` | 1.4.1 |
| Gruppa Payments | `plugins/gruppa-payments` | 1.1.1 |
| Gruppa Notify | `plugins/gruppa-notify` | 1.4.2 |
| Téma Gruppa (Theme Builder) | `themes/gruppa` | 1.3.2 |
| Hub Gruppa Tools | `hub` | 1.6.0 |

## Štruktúra

- **Gruppa CMS** — Dashboard, Content Types, Data Types, Listings, Filters, Forms, Architecture.
- **Gruppa Tools** — samostatné pluginy v spoločnom menu „Gruppa Tools“: Import/Export, Theme Builder (téma), Table Builder, SEO Builder, Payments, Notify. Fungujú s Gruppa CMS aj bez neho (WordPress, JetEngine, ACF).
- **hub/** — hlavná kópia spoločného hubu (menu, štýl, boxy, Help). Po zmene zvýš `$gruppa_tools_hub_version` v `hub/loader.php` a spusti `bin/sync-hub.sh`.

## Vydanie

1. Zvýš verziu v hlavičke pluginu, v konštante `*_VERSION` a v `readme.txt` (Stable tag + Changelog).
2. `bin/release.sh` → zipy v `dist/` (nie sú v Gite).
3. Commit a tag `{slug}-{verzia}`, napr. `gruppa-cms-0.32.4`.
