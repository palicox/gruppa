=== Gruppa SEO Builder ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later

SEO title, meta description, robots, canonical, Open Graph, Twitter / X and JSON-LD for WordPress pages, posts and custom post types, items of JetEngine CCT with a single page and Gruppa CMS content types, with Google and Facebook preview and an SEO box in the editor.

== Popis ==

Plugin Gruppa Tools, menu Gruppa Tools → SEO Builder. Funguje na každom WordPresse; s JetEngine a Gruppa CMS pribudnú ich záznamy.

* Owner SEO = záznam s vlastnou stránkou:
  * JetEngine CCT so single stránkou (stĺpec cct_single_post_id) — owner_type jet:{slug CCT}, owner_id = _ID záznamu. Titulok, adresa a typ stránky sa berú z prepojeného príspevku, dátumy a autor z cct_created, cct_modified, cct_author_id.
  * Gruppa CMS 0.29.6+ — záznamy Content entít, owner_type = slug entity (ako doteraz).
  * WordPress — stránky a príspevky (wp:page, wp:post; filter gruppa_seo/wp_post_types), ktoré nepatria záznamu entity ani CCT.
* Dáta: tabuľka wp_gcms_seo, option gruppa_seo_settings, slug stránky gruppa-seo — rovnaké ako SEO Builder v Gruppa CMS. Bez Gruppa CMS si plugin tabuľku vytvorí sám (option gruppa_seo_db_version); keď sa Gruppa CMS nainštaluje neskôr, použije tie isté dáta.
* Definície CCT sa čítajú priamo z wp_jet_post_types (status content-type), záznamy z wp_jet_cct_{slug} — ako v Table Builder a Import/Export.
* Menu cez spoločný hub Gruppa Tools 1.4.0 (priečinok /hub, identická kópia ako v Notify a Payments). Bez Gruppa CMS je Gruppa Tools samostatné menu.
* S Gruppa CMS 0.29.6+: plugin s rovnakou alebo novšou verziou ako vstavaný SEO Builder (0.29.11 = 1.2.1) beží namiesto neho a SEO Builder zmizne z menu Gruppa CMS. Po deaktivácii pluginu beží vstavaný s tými istými funkciami (od 0.29.7). Staršie Gruppa CMS → beží vstavaný SEO Builder a plugin zobrazí upozornenie.
* Zmazaný CCT záznam: jeho SEO ostane v zozname ako „(missing)“ a dá sa zmazať (záznamy Gruppa CMS sa zmažú automaticky ako doteraz).
* Deaktivácia ani zmazanie pluginu nič nemaže.

== Funkcie ==

* Zoznam SEO záznamov s vyhľadávaním, Add New, Owner = záznam JetEngine CCT alebo Content entity (jeden záznam = jedno SEO), zoskupený podľa CCT / entity.
* Polia: robots, SEO title, meta description (počítadlá znakov), keywords, canonical; Open Graph (title, description, image, type); Twitter / X (card, title, description, image); vlastný JSON-LD. Prázdne polia preberajú hodnoty (OG ← SEO, Twitter ← OG, obrázok ← predvolený).
* Náhľad Google a Facebook / LinkedIn / WhatsApp.
* Settings: šablóna titulku ({title} {sep} {site} {type}), predvolený OG obrázok, Twitter / X účet, organizácia + logo, overenie Google Search Console a Bing.
* Web: titulok, robots, canonical cez filtre WordPressu, meta description a keywords, og:*, twitter:*, JSON-LD (WebSite, WebPage, Article, Organization), noindex záznamy mimo sitemapy. Pri aktívnom Yoast / Rank Math / AIOSEO / SEOPress nič nevypisuje.

== Changelog ==

= 1.4.2 =
* Tlačidlo „Add SEO“ namiesto „Add New“. Hub 1.6.1: jednotné tlačidlá (výška inputu, bez tieňa a outline, hover modré pozadie).

= 1.4.1 =
* Hub Gruppa Tools 1.6.0: rovnaký vzhľad ako Gruppa CMS 0.32.4 aj bez Gruppa CMS — farby Gruppa (accent #2952EA, hover #0604CD, nadpisy #040506, text #575770), nadpisy boxov vnútri boxu v levanduľovej hlavičke, rytmus 24 px, bunky zoznamov 12 px 24 px, jemné linky.

= 1.4.0 =
* Šablóny podľa typu (Settings → Types): titulok, popis, robots a obrázok pre každý typ — Gruppa CMS content typy, JetEngine CCT, post typy, taxonómie. Premenné {title} {sep} {site} {type} {excerpt} {description} {date} {year} {field:key} (pole záznamu Gruppa CMS, stĺpec CCT, post meta / ACF, term meta). Poradie: vlastná hodnota → šablóna typu → globálna šablóna. Obrázok: vlastný → typu → náhľadový obrázok stránky → predvolený.
* Presmerovania (záložka Redirects): 301 / 302 / 307 / 410, stará adresa ako cesta alebo celé URL, * na konci = všetko, čo tak začína (aj prenesenie zvyšku adresy do novej), hromadné pridanie po riadkoch, počítadlo a posledné použitie. Tabuľka wp_gcms_seo_redirects. Funguje aj pri aktívnom inom SEO plugine.
* Taxonómie (Settings → Taxonomies): SEO pre archívy kategórií, štítkov a vlastných taxonómií (owner tax:{taxonómia}), box SEO na úprave termínu, kanonická adresa, CollectionPage v JSON-LD.
* Sitemap: zapnutie / vypnutie sitemap WordPressu, autorské stránky (predvolene vypnuté), len typy so SEO; noindex stránky, termíny a typy sa do sitemap nedostanú.
* Breadcrumbs: BreadcrumbList v JSON-LD (predvolene zapnuté), shortcode [gruppa_breadcrumbs] (home, sep, current), popis pre domovskú stránku a oddeľovač v Settings.

= 1.3.0 =
* Gruppa CMS 0.31 už SEO Builder neobsahuje — plugin je jediný SEO Builder, menu Gruppa Tools → SEO Builder (štvrté). S Gruppa CMS 0.29.6–0.30 beží ako doteraz namiesto vstavanej kópie.
* Settings → Post types: SEO pre stránky, príspevky aj vlastné post typy (verejné, bez médií a šablón builderov; vlastné post typy Gruppa CMS sú pokryté cez content typy). Predvolené Page + Post, filter gruppa_seo/wp_post_types ostáva.
* Box SEO v editore WordPressu (stránky, príspevky, zvolené post typy a single stránky JetEngine CCT): SEO titulok, popis, robots a tlačidlo Edit SEO / Add SEO.
* Tabuľku wp_gcms_seo vytvára plugin vždy sám (aj s Gruppa CMS).
* Zmazanie pluginu maže SEO záznamy a nastavenia iba pri zapnutej voľbe Gruppa CMS → Dashboard → Data → „Delete the data of a Gruppa Tools plugin when it is deleted“.
* Hub Gruppa Tools 1.5.0: pevné poradie menu Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify; upozornenie na starý balík Gruppa Tools.

= 1.2.1 =
* Edit SEO: boxy Owner a Search spojené do Main Settings — prvé pole Owner, potom Robots, SEO title… Rovnaké súbory ako SEO Builder v Gruppa CMS 0.29.11.
* Stĺpec popisov má v každom boxe rovnakú šírku (aj v boxoch bez ⓘ), polia začínajú na jednej línii.

= 1.2.0 =
* WordPress ako tretí zdroj: stránky a príspevky (wp:page, wp:post). Plugin funguje aj bez JetEngine a Gruppa CMS.
* Rovnaké súbory ako SEO Builder v Gruppa CMS 0.29.7 (jeden kód na dvoch miestach). Zdroj pre Import/Export registruje Gruppa_SEO::init().
* Import: owner aj wp:page:#ID / wp:page:slug, adresa stránky aj pre WordPress stránky.

= 1.1.1 =
* Import SEO záznamov z CSV / JSON cez Gruppa Import/Export (samostatný plugin aj Data Tools v Gruppa CMS), aj bez Gruppa CMS. Zdroj „SEO Builder“, stĺpce seo_* sa namapujú automaticky. Owner: WP post ID stránky, adresa stránky (celá alebo /domov/), jet:{cct}:#ID, jet:{cct}:{slug stránky}, pri Gruppa CMS aj entita:slug / entita:#ID. Existujúce SEO toho istého ownera sa prepíše.

= 1.1.0 =
* Funguje aj bez Gruppa CMS: SEO pre záznamy JetEngine CCT so single stránkou (owner jet:{slug}). S Gruppa CMS sú v ponuke jeho entity aj CCT.
* Bez Gruppa CMS si plugin vytvorí tabuľku wp_gcms_seo sám (rovnaká štruktúra ako v Gruppa CMS).
* Upozornenie len keď nie je aktívny ani JetEngine, ani Gruppa CMS.
* Hlavička stránky 32 px a varovanie pred odchodom s neuloženými zmenami aj bez Gruppa CMS (CSS a dirty.js v plugine).

= 1.0.2 =
* Gruppa CMS sa hľadá až na plugins_loaded, keď sú načítané všetky pluginy. Upozornenie „needs Gruppa CMS 0.29.6“ sa už nezobrazí na webe, kde sa SEO Builder načíta pred Gruppa CMS (iné poradie v zozname aktívnych pluginov). Upozornenie ukazuje, či Gruppa CMS nie je aktívny, alebo akú verziu má.

= 1.0.1 =
* Pribalený hub Gruppa Tools 1.4.0: SEO Builder je vždy v menu Gruppa Tools (poradie 50), aj keď nie je aktívny iný add-on.

= 1.0.0 =
* Prvá verzia: SEO Builder z Gruppa CMS 0.29.5 ako samostatný plugin v menu Gruppa Tools, kód a dáta bez zmeny.
