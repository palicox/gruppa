=== Gruppa Table Builder ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7.3
License: GPLv2 or later

Tables from Gruppa CMS content types, JetEngine CCT and WordPress post types with ACF / JetEngine fields: rows of a Repeater field of the current item or the items of a content type. Columns with formats, sortable headers, mobile cards, Search Filters support, shortcode and Elementor widget Gruppa Table with full styling. Works with or without Gruppa CMS.

Menu: Gruppa Tools → Table Builder (po Import/Export a Theme Builder). Funguje s Gruppa CMS aj bez neho (WordPress, JetEngine, ACF).

== Zdroje dát ==

* Content type je jeden zoznam rozdelený podľa zdroja — zobrazia sa iba zdroje, ktorých plugin je aktívny:
* Gruppa CMS — Content a Data typy (skupiny, Related to current item, Only my items, Search Filters).
* JetEngine CCT — záznamy z tabuľky jet_cct_{slug}, iba publikované. Stĺpce = polia CCT + ID, Created, Modified, Author. Repeater polia CCT ako „Rows from — Repeater“. Related to current item = pole typu Posts, ktoré obsahuje aktuálny príspevok. Link to the item page = single stránka CCT (cct_single_post_id).
* WordPress — príspevky, stránky a vlastné post typy: Title, Slug, ID, Created, Modified, Author, Excerpt, Content, Featured image, taxonómie, polia ACF a JetEngine (Meta Boxes aj polia post typov z JetEngine). Repeater ACF aj JetEngine.
* Current item pri Repeater: v single šablóne aktuálny záznam / príspevok, v JetEngine Listing Grid položka listingu.
* Hodnoty sa čítajú priamo z databázy (ako v Import/Export), takže nezáleží na verzii objektového API JetEngine. Dátumy ACF (Ymd) aj JetEngine (timestamp) sa zobrazia ako dátum, Checkbox / Select / Radio s názvami možností, Switcher ako Yes / No, Posts ako názvy príspevkov.
* Keď zdrojový plugin nie je aktívny, tabuľka na webe ukáže adminovi hlášku (napr. „JetEngine is not active.“) a nastavenia ostanú uložené.

== Ako funguje ==

* Tabuľka je šablóna: určí, odkiaľ berie riadky a ktoré stĺpce ukáže. Údaje sa neukladajú, čítajú sa živé zo záznamov.
* Rows from — Items: jeden riadok = jeden záznam typu (cenník, prehľad). Order by, Direction, Rows per page, Related to current item, Only my items ako v Listing Builder; Group a Search Filters iba pre Gruppa CMS (stránka sa načíta s parametrami).
* Rows from — Repeater: riadky Repeater poľa jedného záznamu. Item = Current (tabuľka v single šablóne typu, každý záznam ukáže svoje riadky), By ID alebo By slug (JetEngine CCT nemá slug — iba ID).
* Columns: výber poľa + Add Column / Add All. Label, Format (Automatic, Text, Number, Date, Date and time, Time, Yes / No, Image, Link, Link to the item page), Decimals, oddeľovače, text pred / za hodnotou (napr. €, kg), Align, Width, Sortable.
* Display: hlavička, na mobile karty pod sebou (label : hodnota) alebo posun do strany, prázdny text.
* Na webe: shortcode [gruppa_table table="slug"] alebo Elementor widget Gruppa Table.

== Elementor widget Gruppa Table ==

* Obsah → Table: výber tabuľky.
* Obsah → Columns: Column width = Automatic (podľa obsahu / šírok z Table Builder), Equal (všetky rovnako), Custom. Column settings: stĺpec podľa poradia (1 = prvý zľava), šírka v px / % / rem / em (pri Custom), Hide on tablet, Hide on mobile.
* Obsah → Mobile: On mobile = podľa Table Builder, Cards (každý riadok ako karta „názov stĺpca : hodnota“) alebo Table, horizontal scroll. Minimum table width — tabuľka sa neposúva do úzka, ale posúva sa do strany.
* Štýl → Table: pozadie; Outer border (typ, hrúbka, farba, radius, padding, margin), tieň; Lines inside: Horizontal lines on/off, Vertical lines on/off, farba, hrúbka, typ.
* Štýl → Header: typografia, farba textu, pozadie, padding, zarovnanie, vertikálne zarovnanie, zalamovanie, border (typ, hrúbka, farba, radius).
* Štýl → Cells: typografia, farba textu a odkazov, pozadie, padding, zarovnanie, vertikálne zarovnanie, text na jeden riadok, border, veľkosť a radius obrázkov.
* Štýl → Rows: každý druhý riadok, riadok a text pri hover. Farby riadkov sa dávajú na bunky — zebra témy (Hello Elementor) sa vynuluje.
* Štýl → Mobile cards, Pagination (aj zarovnanie).

== Databáza ==

* wp_gcms_tables (do 0.4.x wp_gruppa_tables): id, name, slug, type_slug, author, created, modified, status, order_index, settings (JSON).
* settings.origin = gruppa | jet | wp (zdroj dát; staré tabuľky bez origin = gruppa). type_slug = slug typu v danom zdroji. Štruktúra tabuľky sa nemení.

== Changelog ==

= 0.7.3 =
* Hub 1.6.2: rovnaká medzera medzi úchytom a checkboxom v zoznamoch.

= 0.7.2 =
* Tlačidlo „Add Table“ namiesto „Add New“. Hub 1.6.1: jednotné tlačidlá (výška inputu, bez tieňa a outline, hover modré pozadie).

= 0.7.1 =
* Hub Gruppa Tools 1.6.0: rovnaký vzhľad ako Gruppa CMS 0.32.4 aj bez Gruppa CMS — farby Gruppa (accent #2952EA, hover #0604CD, nadpisy #040506, text #575770), nadpisy boxov vnútri boxu v levanduľovej hlavičke, rytmus 24 px, bunky zoznamov 12 px 24 px, jemné linky.

= 0.7.0 =
* Znova samostatný plugin Gruppa Tools (Gruppa CMS 0.31 už Table Builder neobsahuje). Kód = Table Builder 0.6.0 z Gruppa CMS 0.30.2: Main Settings (On mobile, Empty text, Header row), poradie boxov Main Settings, Query, Columns.
* Menu Gruppa Tools → Table Builder (tretie, po Import/Export a Theme Builder). Tabuľky (wp_gcms_tables) ostávajú.
* Elementor: s Gruppa CMS sa widget Gruppa Table zaradí medzi widgety Gruppa CMS (Text, Search, Listing, Table, Form, Login) s ikonou Phosphor; bez Gruppa CMS sa registruje sám.
* Hub Gruppa Tools 1.5.0: pevné poradie menu Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify; upozornenie na starý balík Gruppa Tools.

= 0.5.0 =
* Tabuľka wp_gruppa_tables → wp_gcms_tables (prefix Gruppa CMS 0.28+). Premenuje sa automaticky pri prvom načítaní, aj na webe. Content Type so slugom „tables“ už nemôže kolidovať s Table Builderom.

= 0.4.0 =
* Elementor widget Gruppa Table prerobený: sekcie Columns a Mobile v obsahu, Outer border ako štandardný border (typ, hrúbka, farba, radius, padding, margin), čiary vo vnútri zvlášť vodorovné a zvislé (on/off, farba, hrúbka, typ), Header a Cells s borderom, radiusom, zarovnaním a vertikálnym zarovnaním.
* Šírka stĺpcov: Automatic / Equal / Custom (px, %, rem, em) a skrytie stĺpca na tablete / mobile.
* Na mobile: karty alebo tabuľka s horizontálnym posunom priamo vo widgete, minimálna šírka tabuľky.
* Oprava: farba „Every second row“ sa nedala zmeniť a prvý riadok pod hlavičkou bol sivý — téma (Hello Elementor) farbí nepárne riadky na bunkách. Tabuľka to teraz vynuluje a farby riadkov dáva na bunky.
* Tabuľka používa border-collapse: separate (radius na bunkách funguje).
* Zrušené staré nastavenia widgetu: Outer border Yes/No, Border radius (slider), Lines between rows — nahradené novými, treba ich nastaviť znova.

= 0.3.1 =
* Slug novej tabuľky sa vyplní z názvu (bez diakritiky, ako v Gruppa CMS). Pomlčka sa zmení na podčiarkovník, formulár ju už neodmietne.
* WordPress zdroj nezobrazuje interné post typy Gruppa CMS (gruppa_listing). Filter gruppa_tb/wp_exclude_post_types.
* Jasnejšia hláška, keď uložený typ už neexistuje alebo jeho plugin nie je aktívny.

= 0.3.0 =
* Zdroje dát ako v Import/Export: Gruppa CMS, JetEngine CCT a WordPress post typy s poliami ACF a JetEngine. Table Builder funguje aj bez Gruppa CMS.
* Content type: jeden zoznam rozdelený podľa zdroja (Gruppa CMS / JetEngine CCT / WordPress + ACF / JetEngine).
* Repeater: ACF repeater, JetEngine repeater (CCT aj meta polia príspevkov); Current item aj v JetEngine Listing Grid a na single stránke CCT.
* Nový formát stĺpca Date and time. Galéria ukáže všetky obrázky. Yes / No rozumie aj „true / false“ z JetEngine.
* Oprava: medzera ako Thousands separator sa už pri ukladaní nestratí.
* Elementor widget Gruppa Table sa registruje aj bez Gruppa CMS.
* Existujúce tabuľky ostávajú bez zmeny (zdroj Gruppa CMS).

= 0.2.5 =
* Gruppa Tools hub 1.3.0: v hlavičke stránky pribudol odkaz Tutorial (vedľa prepínača Help) na PDF príručku add-onu na gruppa.cloud.

= 0.2.4 =
* Odstránenie dát: nový uninstall.php — pri zmazaní pluginu sa jeho tabuľka a nastavenia odstránia iba ak je zapnuté „Delete the data of a Gruppa Tools add-on when it is deleted“ (Gruppa CMS → Dashboard → Data). Inak ostáva všetko pre opätovnú inštaláciu.

= 0.2.3 =
* Gruppa Tools hub 1.2.0: pomoc pri nastaveniach — ikonka ⓘ pri názve poľa otvorí popis v bubline; prepínač Help v hlavičke zobrazí všetky popisy pod poľami (ikonky zmiznú), zapamätá sa v prehliadači.

= 0.2.2 =
* Bočný panel bez nadpisu „Status“ (ako Gruppa CMS 0.25.1).

= 0.2.1 =
* Stĺpec Number: Decimal separator a Thousands separator (napíšeš , . alebo medzeru); prázdne = formát webu. Rovnaké polia ako v dynamickom tagu Gruppa Field.

= 0.2.0 =
* Prvá funkčná verzia: tabuľky zo záznamov alebo z Repeater poľa, stĺpce s formátmi, triedenie klikom na hlavičku, karty na mobile, stránkovanie, Search Filters, shortcode a Elementor widget Gruppa Table.
* Admin v štýle Gruppa CMS: Main Settings, Columns, Query, Display, bočný panel (Status, Publish / Update, Duplicate, Delete), Frontend na konci.
* Gruppa Tools hub 1.1.1, poradie v menu 20 (po Import/Export).

= 0.1.0 =
* Prvá vývojová verzia: položka Table Builder v menu Gruppa Tools, Gruppa Tools hub 1.1.0.
