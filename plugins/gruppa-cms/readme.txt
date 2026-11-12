=== Gruppa CMS ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.33.0
License: GPLv2 or later

Data layer for WordPress: content types and data types in their own database tables with a field builder, listings, filters and forms, REST API, access rules, Elementor widgets, dynamic tags and Block Bindings.

Menu Gruppa CMS: Dashboard, Content Types, Data Types, Listings, Filters, Forms, čiara, Architecture.
Typy nie sú v hlavnom menu WordPressu (v ňom je len Gruppa CMS a Gruppa Tools). Všetky typy a ich záznamy sú v Gruppa CMS → Architecture: strom typov (data typ pripojený ku content typu je pod ním), počty, použitie a vpravo čierny panel s menu typov.

Gruppa Tools (samostatné pluginy, spoločné menu Gruppa Tools hneď pod Gruppa CMS): Import/Export, Theme Builder (téma Gruppa, len keď je aktívna), Table Builder, SEO Builder, Payments, Notify. Každý funguje s Gruppa CMS aj bez neho (WordPress, JetEngine, ACF). Balík Gruppa Tools sa ďalej nevyvíja.

== Databáza ==

* Všetky tabuľky Gruppa majú prefix wp_gcms_:
* wp_gcms_{name} — systémové tabuľky Gruppa CMS: types, forms, listings, filters, relations; Gruppa Tools: tables (Table Builder), seo (SEO Builder), notify_subs, payments, payment_events.
* wp_gcms_type_{slug} — záznamy entít (napr. wp_gcms_type_clanky). Entita tak nikdy nekoliduje so systémovou tabuľkou.
* Strom menu: settings.menu entity ({ manual: 1, parents: [...] }, bez kľúča = podľa väzieb), poradie = order_index. DB sa v 0.29 nemení (DB 12).

== Changelog ==

= 0.33.0 =
* Meta fields: hlavička poľa — úchyt | Label | slug | typ, vpravo ikona kopírovania a šípka. Nové: kopírovanie poľa (vloží sa hneď pod originál s kľúčom *_copy).
* Meta fields: podmienky zobrazenia (Show item when… / podmienka / hodnota / and-or). Skryté pole sa nevaliduje ani ako povinné. Poradie na konci poľa: Condition, Item list, Required, Remove.
* Nový typ poľa Icon (Dashicons picker ako pri Menu icon). Repeater má nové stĺpce Editor a Icon.
* Názvy tlačidiel: Add / Edit Content Type, Add / Edit Data Type, Add Item / Edit Item, Add Listing, Add Filter Group, Add Form; skratky na Dashboarde „Add …“.
* Structure map: akcie „All items | Add item | Edit type“. Šípky ← → sú v jednom riadku s akciami.
* Tlačidlá: rovnaká výška ako textový input, rovnaký radius aj pri hover/focus, bez outline a tieňa, bez animácie; obrysové tlačidlá pri hover modré pozadie a biely text.
* Čierny panel Menu aj na editácii položky. Pri novom type v hlavičke Back + Add Content/Data Type.

= 0.32.5 =
* Oprava: Menu icon — výber ikon sa znova zobrazuje celý. Box Main Settings (gruppa-boxed) už panel neoreže; hlavička boxu má zaoblené horné rohy.
* Zoznam záznamov entity má vpravo čierny panel Menu z Architecture. Otvorená entita je v ňom zvýraznená (#2952EA), takže sa medzi entitami dá prepínať priamo zo zoznamu. Hlavička (nadpis, Search, Tutorial, Help) ostáva na celú šírku, panel začína na úrovni zoznamu.
* Zoznam záznamov: záložky All / Published / Draft a ich linka na celú šírku. Zoznam aj panel Menu začínajú 24 px pod linkou, medzi nimi 24 px. Bulk actions, Apply a počet záznamov sú pod zoznamom.

= 0.32.4 =
* Hlavička boxu bez linky (oddelí ju levanduľové pozadie). Čierny panel Menu: deliace čiary tmavšie #2A2B36 — jemné, ale viditeľné.
* Dashboard → Shortcuts: názvy skratiek v accent farbe #2952EA, hover #0604CD.

= 0.32.3 =
* Všetky nadpisy boxov sú vnútri boxu v hlavičke ako Structure map (12 px 24 px, #F6F7FB) — na všetkých obrazovkách Gruppa CMS aj Gruppa Tools (admin/assets/boxes.js, nadpis h2.gruppa-fields-title nad boxom alebo tabuľkou). Zoznamy riadkov polí ostávajú.
* Jeden rytmus 24 px: medzery medzi boxmi, pod hlavičkou stránky, v mriežke Dashboardu, vnútorný okraj boxov. Bunky zoznamov 12 px 24 px.
* Linky: hlavička boxu a rámik #E3E5EE, riadky a hlavička tabuľky jemné #F1F2F6.
* Záložky All / Published / Draft začínajú na línii nadpisu stránky.
* Zoznamy typov nemajú pevnú šírku stĺpcov — Actions sa už neskryje.

= 0.32.2 =
* Architecture a Structure map na Dashboarde: nadpis boxu je vnútri boxu v hlavičke so svetlým levanduľovým pozadím #F6F7FB a linkou #D5E5FE (vpravo „The tree follows the data links“ / Reset tree). Čierny panel Menu má rovnakú hlavičku, oba boxy začínajú na jednej línii.

= 0.32.1 =
* Zoznamy kompaktnejšie: bunky 8 px / 16 px (začiatok aj koniec riadku rovnako), zaoblenie 8 px, každý riadok na jednom riadku (bez zalomenia).
* Content Types / Data Types: Post type len „Page Type / Post Type / Custom Type“, Records „Grouped / Single“, pri data type pod content typom len „Data“; stĺpec Menu prázdny, keď nie je čo ukázať (bez „—“ a „Level 1“). Akcie začínajú pod nadpisom stĺpca, šípky ← → sú za Delete.
* Záložky: aktívna modrá #2952EA, ostatné čierne, hover modrá.
* Počet za názvom: svetlé levanduľové pozadie #F6F7FB, jemná linka #D5E5FE, číslo #2952EA. Ikony v strome: úroveň 1 #0604CD, úroveň 2 #2952EA.

= 0.32.0 =
* Typy už nie sú v hlavnom menu WordPressu — menu sa pri každom novom type neposúva. Hlavné menu: Gruppa CMS, Gruppa Tools.
* Gruppa CMS → čiara → Architecture: celá štruktúra webu na jednej obrazovke — strom typov, Published / Draft, Used by (listingy, filtre, formuláre, tabuľky), All items · Add item · Edit type; vpravo čierny panel len s menu typov a počtami záznamov. Rovnaká tabuľka je Structure map na Dashboarde. Rebuild menu → Reset tree.
* Stránky záznamov sú skryté stránky (options.php) a zvýrazňujú Gruppa CMS → Architecture.
* Farby Gruppa v celom admine Gruppa CMS: odkazy, tlačidlá a ikony #2952EA, hover #0604CD, nadpisy (aj malé) #040506, text #575770.

= 0.31.0 =
* Uzavretie pluginu. Menu: Dashboard, Content Types, Data Types, Listings, Filters, Forms.
* Entity Builder rozdelený na Content Types a Data Types — rovnaký editor, druh určuje obrazovka (selektor Type zrušený). Content: Post type v poradí Page Types / Post Types / Custom Types. Data: prvé pole Records (Grouped records / Single records). DB sa nemení (DB 12), existujúce typy ostávajú.
* Strom menu: Content Types ukazuje content typy s typmi pod nimi (← →), Data Types data typy na úrovni 1 (poradie ťahaním) a pod nimi zoznam data typov pripojených ku content typom. Uloženie jednej obrazovky nemení poradie typov z druhej (Gruppa_Menu::save — miesta podľa pôvodného poradia).
* Import/Export (Data Tools), Table Builder a SEO Builder odstránené z Gruppa CMS — sú to samostatné pluginy Gruppa Tools. Gruppa_Modules zrušené. Dáta ostávajú (wp_gcms_tables, wp_gcms_seo); keď existujú a plugin nebeží, admin upozorní, ktorý nainštalovať.
* Dashboard: počty Content / Data types, Tables a SEO len pri aktívnom plugine, sekcia Gruppa Tools (Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify), System s ACF.
* Odinštalovanie maže len tabuľky Gruppa CMS (types, forms, listings, filters, relations); tabuľky Gruppa Tools maže každý plugin sám.

= 0.30.2 =
* Elementor widget Gruppa Text: nadpis má aj triedu elementor-heading-title ako štandardný widget Nadpis — triedy témy na widgete (napr. .big) ho štýlujú rovnako.

= 0.30.1 =
* Entity Builder → Menu icon: ikona v tlačidle je pevne vycentrovaná, admin CSS iných pluginov ju už neposúva.

= 0.30.0 =
* Odstránené prechody zo starých verzií (pred 0.28): premenovanie tabuliek wp_gruppa_* / wp_gruppa__* na wp_gcms_*, typ entity „relation“, pole Parent, stĺpec name → title, „Who can submit = Same as content type“, wp_gruppa_tables v Table Builderi, option gruppa_core_db_version a gruppa_cms_legacy_tables. Gruppa CMS 0.30 sa inštaluje nanovo alebo aktualizuje z 0.28.1 a novšej (DB 12). Odinštalovanie maže len tabuľky wp_gcms_*.

= 0.29.11 =
* Table Builder: box Display zrušený — On mobile, Empty text a Header row (checkbox posledný) sú v Main Settings. Poradie boxov: Main Settings, Query, Columns.
* SEO Builder (vstavaný 1.2.1): boxy Owner a Search spojené do Main Settings; prvé pole je Owner, potom Robots, SEO title… Zdieľaný súbor admin/class-gruppa-admin-seo.php — samostatný plugin Gruppa SEO Builder treba aktualizovať na 1.2.1.
* Filter Builder: medzera medzi checkboxom Show a reset link a textom ako v Listing Builder.
* Admin: stĺpec popisov má v každom boxe rovnakú šírku (aj v boxoch bez ⓘ) — polia začínajú na jednej zvislej línii na celej stránke.

= 0.29.10 =
* Entity Builder: úvodný text o strome (a poznámka pod Structure map) sa zobrazí len pri zapnutom Help — nová trieda gruppa-help-text.
* Entity Builder: Menu icon je posledné pole v Main Settings (Type a Post type ostávajú spolu). Stĺpec Name v strome je širší, názov podentity sa nezalamuje.
* Filter Builder: box Behaviour zrušený — Apply, Button text a Reset sú v Main Settings. Pri Reset je najprv text odkazu, pod ním checkbox „Show a reset link“.

= 0.29.9 =
* Elementor — nastavenia dynamických tagov Gruppa (Field, URL, Image, Gallery): popisy v jednom stĺpci, polia začínajú na rovnakej zvislej línii, dlhé popisy sa zalomia (Decimal / separator). Field už nie je v samostatnom riadku. Trieda gruppa-tag-ctrl na každom ovládaní, štýly v modules/elementor/assets/editor.css.
* Gruppa Field: odstránené poznámky pod Decimals, Decimal separator a Thousands separator. Prázdna voľba poľa je „— Select field —“.

= 0.29.8 =
* Entity Builder → Menu icon: vlastná ikona entity z WordPress Dashicons (výber s vyhľadávaním, „Use default“). Ikona sa použije v hlavnom menu WordPressu, v strome entít a v Structure map. Uloženie settings.icon; bez kľúča = predvolená ikona podľa druhu (Content / Data).
* Elementor: poradie widgetov v kategórii Gruppa CMS — Text, Search, Listing, Table, Form, Login. Gruppa Table sa registruje cez filter gruppa_cms/elementor/widget_instances.
* Elementor: vlastné ikony widgetov (Phosphor Icons, MIT) — Regular, pri hoveri Duotone (modules/elementor/assets/editor-icons.css).
* Dashboard → Structure map: tlačidlo Rebuild menu sa zobrazí len pri ručných zmenách v strome (s počtom); inak text „Menu follows the data links“.

= 0.29.7 =
* SEO Builder: Gruppa CMS a plugin Gruppa SEO Builder 1.2.0 majú rovnaké súbory (includes/class-gruppa-seo.php, class-gruppa-seo-owners.php, class-gruppa-seo-ie-source.php, admin/class-gruppa-admin-seo.php, seo.js) — ako Import/Export. Vstavaný SEO Builder je 1.2.0; keď beží plugin, Gruppa CMS svoju kópiu nenačíta.
* SEO Builder — owner môže byť záznam Content entity, záznam JetEngine CCT so single stránkou (jet:{cct}) alebo stránka / príspevok WordPressu (wp:page, wp:post; filter gruppa_seo/wp_post_types). Stránky a príspevky, ktoré patria záznamu entity alebo CCT, sú len pod ním.
* SEO Builder — import cez Data Tools: owner ako WP post ID, adresa stránky, jet:{cct}:#ID / slug, wp:page:#ID / slug, entita:slug / #ID.
* Gruppa Visibility: nové podmienky Post / page ID (is / is not), User ID (is / is not), pri poli „is enabled / is disabled (switcher)“; „is not empty“ = „exists (is not empty)“. Viac ID oddelených čiarkou.
* Gruppa Visibility: prvok s pravidlami sa nevkladá do cache prvkov Elementora (3.22+) — inak sa výsledok pre prvého návštevníka (prihlásený, rola, používateľ) ukazoval všetkým.
* Widgety Gruppa Search, Form, Login → Style → Checkbox / Radio: „Hide box (text only)“ — políčko sa skryje (ostáva dostupné klávesnicou a čítačkám), klikateľný je celý text. Nové Option border, Option border radius a v tabs Normal / Hover / Checked Option background a Option border color (štýl ako tlačidlá / chips).
* Gruppa Search: odstránené upozornenie Elementora „Cannot redeclare control reset_border_color“ (farba okraja tlačidla Reset je v tabs Normal / Hover).

= 0.29.6 =
* SEO Builder môže bežať aj ako samostatný plugin Gruppa SEO Builder (menu Gruppa Tools). Keď je aktívny a má rovnakú alebo novšiu verziu ako vstavaný SEO Builder (1.0.0), Gruppa CMS svoju kópiu nenačíta a SEO Builder zmizne z menu Gruppa CMS. Staršia verzia pluginu sa nepoužije a zobrazí sa upozornenie. Dáta sú spoločné (wp_gcms_seo, gruppa_seo_settings), slug stránky ostáva gruppa-seo, takže box SEO v zázname aj Data Tools fungujú rovnako.
* Gruppa_Modules::seo_source(); Dashboard → System ukazuje, odkiaľ beží SEO Builder.

= 0.29.5 =
* Form Builder: popis akcie z rozšírenia (Payments, Notify → Send notification) v hlavičke karty ostane po načítaní a úprave formulára.

= 0.29.4 =
* Nové akcie pre rozšírenia: gruppa_cms/item_saved ( $type, $id, $is_new ) a gruppa_cms/item_created ( $type, $id ) po uložení záznamu (admin, Form Builder, Data Tools, REST, PHP). Používa ich Gruppa Notify 1.2 na okamžitý push.

= 0.29.3 =
* Data Tools: vyhráva novšia kópia Import/Export. Keď je aktívny samostatný Gruppa Import/Export a je novší ako vstavaný modul (1.2.0), Gruppa CMS svoju kópiu nenačíta a zobrazí samostatný plugin, stále ako Gruppa CMS → Data Tools. Rovnaká alebo staršia verzia: beží vstavaný modul a upozornenie odporučí plugin zmazať. Konštanta GRUPPA_CMS_DATA_TOOLS, Gruppa_Modules::ie_source().
* Import/Export modul a samostatný plugin 1.2.0 majú rovnaký súbor class-gruppa-ie.php.
* Dashboard: Extensions = CSS Builder, Payments, Notify (poradie menu Gruppa Tools), Media odstránené; System ukazuje, odkiaľ beží Data Tools.

= 0.29.2 =
* Slug entity sa dá zmeniť, kým entita nemá žiadne záznamy. Prázdna tabuľka sa nahradí wp_gcms_type_{nový slug}, vlastný post type (CPT) dostane nový názov a všetko, čo entitu používa, prejde na nový slug: formuláre, listingy, filter groups, tabuľky Table Builder, väzby a strom menu iných entít, staré Relation polia, väzby a SEO. Hook gruppa_cms/type_slug_changed( $old, $new ).
* Duplikovaná entita (slug …_copy): slug sa pri písaní názvu dopĺňa automaticky (Pages (copy) → Products = products), kým ho neupravíš ručne.
* Entita so záznamami má slug pevný (poznámka pod poľom); pokus o zmenu vráti chybu „The slug can be changed only while the entity has no items.“

= 0.29.1 =
* Hlavička stránok: vyhľadávacie pole a tlačidlo Search majú výšku 32 px ako ostatné tlačidlá; celý riadok (tlačidlá, vyhľadávanie, Tutorial, Help) je zarovnaný na rovnaký vertikálny stred.

= 0.29.0 =
* Jeden plugin: Table Builder (predtým add-on, 0.5.0 → modul 0.6.0) a Import/Export (1.1.1 → modul Data Tools 1.2.0) sú súčasťou Gruppa CMS. Triedy, hooky (gruppa_tb/*, gruppa_ie/*), slugy stránok, tabuľka wp_gcms_tables a option gruppa_tb_db_version ostávajú. Samostatné pluginy a balík Gruppa Tools sa pri aktívnom Gruppa CMS preskočia (Gruppa CMS definuje GRUPPA_TB_VERSION / GRUPPA_IE_VERSION prvý) a zobrazí sa upozornenie, že ich treba deaktivovať a zmazať.
* Zmazanie starého samostatného Table Builder alebo Gruppa Tools už nezmaže tabuľky Table Builder (ochrana cez pre_uninstall_plugin). Tabuľky Table Builder sa teraz riadia voľbou „Delete all data when Gruppa CMS is deleted“.
* Názvy v menu: Content Types → Entity Builder, Search Filters → Filter Builder, Dynamic Forms → Form Builder, Import/Export → Data Tools. Poradie: Dashboard, Entity Builder, Listing Builder, Filter Builder, Table Builder, Form Builder, SEO Builder, Data Tools. Slugy stránok sa nemenia.
* Entity v hlavnom menu WordPressu: každá publikovaná entita má vlastnú položku (All items, Add New) v bloku medzi Komentármi a Gruppa CMS. Data entita pripojená ku Content entite je jej podpoložka; pripojená k viacerým je pod každou z nich a pri Grouped records ukazuje len skupiny pripojené k danému rodičovi (?parent=). Nepripojená entita je na úrovni 1.
* Entity Builder = strom menu: ťahanie mení poradie, → vloží entitu pod entitu nad ňou, ← ju vráti na úroveň 1. Ručná zmena sa uloží do nastavení entity a má prednosť pred väzbami. Dve úrovne: entita s podpoložkami sa nedá vnoriť. Stĺpce Kind a Menu (Data link / Manual / zdieľaná ⇄).
* Dashboard: Structure map (strom entít, počty, kde sa entita používa — listingy, filtre, tabuľky, formuláre; upozornenie „Not used yet“), tlačidlo Rebuild menu (vráti strom podľa väzieb), nové skratky (New entity, New table, Import / export, Structure map), panel Extensions (Notify, Payments, CSS Builder, Media), riadok Database ukazuje wp_gcms_type_*.

= 0.28.1 =
* Databáza: tabuľky záznamov Content Types wp_gruppa_{slug} → wp_gcms_type_{slug}, takže všetko z Gruppa je pod wp_gcms_. Premenujú sa automaticky (DB 12), len tabuľky existujúcich Content Types — tabuľky add-onov sa nedotknú. Náhľad názvu tabuľky v úprave Content Type ukazuje nový názov.

= 0.28.0 =
* Databáza: systémové tabuľky wp_gruppa_{types, forms, listings, filters, seo, relations} → wp_gcms_*. Premenujú sa automaticky (DB 11), aj staré názvy wp_gruppa__* spred 0.15; ak Content Type používal systémový slug, jeho tabuľka záznamov ostane nedotknutá. Upozornenie o „legacy“ tabuľkách zaniklo. Rezervované slugy rozšírené o tables, payments, notify_subs, media, css_styles, gcms.
* Dynamic Forms: API pre akcie add-onov — filtre gruppa_cms/form_action_defaults, form_action_sanitize, form_action_summary, akcia gruppa_cms/form_action_fields (pomocníci Gruppa_Admin_Form_Actions::row/text/select/check/field_select sú verejné), gruppa_cms/form_redirect a gruppa_cms/form_response. Využíva ich akcia Payment v Gruppa Payments.
* Dashboard → Gruppa Tools: Import/Export, Table Builder, Notify, Payments. CSS Builder a Media odstránené (pokračujú ako Gruppa Frontend), text pri mazaní dát upravený. Info o databáze ukazuje oba prefixy.

= 0.27.0 =
* Import/Export: Repeater sa importuje ako riadky (aj z JSON v CSV), čísla v pod-poliach sa prevedú („7,25“ → 7.25), Time sa normalizuje.
* Dashboard → Data: nová voľba „Delete the data of a Gruppa Tools add-on when it is deleted“ pre add-ony Table Builder, Media, CSS Builder a Notify.

= 0.26.1 =
* Elementor widget Gruppa Search: ikona lupy (eicon-search) v štýle ostatných widgetov.

= 0.26.0 =
* Pomoc pri nastaveniach na všetkých obrazovkách: popisy pod poľami sú skryté, ikonka ⓘ na pravom okraji stĺpca s názvami ich ukáže v bubline (myš, klik, klávesnica). Rozloženie polí sa nemení.
* V hlavičke vpravo: Tutorial ↗ (https://gruppa.cloud/gruppa_cms_tutorial.pdf, filter gruppa_cms/tutorial_url) a prepínač Help — zapnutý zobrazí všetky popisy pod poľami a ikonky skryje; zapamätá sa v prehliadači.

= 0.25.2 =
* Zoznam záznamov: stĺpec väzby už neopakuje názov Data typu, keď je rovnaký ako názov stĺpca.
* Bočný panel záznamu: box „SEO optimization“, tlačidlo Add SEO / Edit SEO modré na celú šírku ako Update.

= 0.25.1 =
* Bočný panel (záznamy, Content Types, Listing, Search Filters): bez nadpisu „Status“, ostal len výber stavu.
* Zoznam záznamov: tlačidlo Add Type vedľa Edit Type.

= 0.25.0 =
* Dynamic tagy Gruppa (Field, URL, Image, Gallery): Item → Linked item. Pole záznamu priradeného cez väzbu, napr. Klienti › Logo klienta aktuálneho projektu (aj v karte listingu).
* Data typ: väzba One (select with search) — výber z rozbaľovacieho zoznamu s vyhľadávaním, vhodné pre veľa záznamov (klienti, značky).
* Structure: „Single records“ a „Grouped records“ (predtým Values only / Groups and values); popis vysvetľuje, že položka môže byť plný záznam s vlastnými poľami.
* Data typ: Items list (stĺpec v zozname záznamov) má vlastný riadok nad Choice.
* Čísla v dynamickom tagu Gruppa Field: Decimals, Decimal separator a Thousands separator ako textové polia (napíšeš , . alebo medzeru) — rovnaké ako v Table Builder.

= 0.24.0 =
* Gruppa Visibility: v záložke Advanced každého Elementor containera, sekcie, stĺpca a widgetu. Show when / Hide when, všetky alebo aspoň jedno pravidlo.
* Pravidlá: pole aktuálneho záznamu (aj karta v Gruppa Listing; is, is not, contains, greater / less than, is empty / not empty — porovnáva hodnotu, názov možnosti a pri väzbách názov, slug aj ID), prihlásený návštevník, rola, URL parameter. Viac hodnôt oddelených čiarkou = ktorákoľvek.
* Skrytý prvok sa na webe vôbec nevypíše (žiadna medzera, nič v zdrojovom kóde). V editore je vždy viditeľný.

= 0.23.2 =
* Dynamic tag Gruppa Field: pri číselnom poli Number: decimals, decimal separator (čiarka / bodka) a thousands separator (medzera, bodka, čiarka), napr. 1 290,50.
* Gruppa Text: Hide when empty (predvolene zapnuté) — prázdna hodnota = widget sa na webe nezobrazí ani nenechá medzeru. V editore ostane slabý popis „empty“, aby sa dal vybrať.

= 0.23.1 =
* Dashboard: Gruppa Table Builder 0.2 — popis a stav (aj z balíka Gruppa Tools 4.2).

= 0.23.0 =
* Listing Builder: bočný panel — Status, ID, Author, Created, Modified, tlačidlá Update & edit template a Update (posledné), odkazy Edit template, Duplicate, Delete. Enter v poli stále ukladá cez Update.
* Query: Order by a Direction (vzostupne / zostupne) v dvoch riadkoch pod sebou, Only my items je posledné.
* Listing a Search Filters: box Frontend (shortcode) je na konci stránky, pole so shortcode má šírku podľa obsahu.

= 0.22.9 =
* Content Types: pridanie poľa ako vo filtroch a akciách — výber typu poľa a Add Field; nové pole má typ hneď nastavený.
* Search Filters: bočný panel ako pri Content Types (Status, ID, Author, Created, Modified, Publish / Update, Duplicate, Delete).
* Polia, filtre a akcie majú stĺpec s názvami rovnako široký ako Main Settings, vstupy sú zarovnané pod sebou.

= 0.22.8 =
* Content Types: úprava typu má bočný panel ako úprava záznamu — Status, ID, Author, Created, Modified, DB Table, veľké tlačidlo Publish / Update a odkazy Items, Duplicate, Delete.
* Elementor Gruppa Search: sekcia Reset button — typografia, podčiarknutie, farba textu, pozadia a rámika (Normal / Hover), tieň, rámik, radius, padding, margin a medzera medzi Filter a Reset.
* Rovnomerné medzery: možnosti a Add Option, Add Field, Add Group, Add column.

= 0.22.7 =
* Oprava: REST trasa typov (/gruppa/v1/{typ}/{id}) prekrývala /gruppa/v1/listing/{id}. Filtrovanie Search Filters bez obnovenia stránky, Load more a stránkovanie listingu cez AJAX vracali „Type not found“ a listing sa nemenil.
* Search Filters sa automaticky prepoja s listingom rovnakého typu obsahu, ak v widgete Gruppa Listing nie je vybraná skupina filtrov (aj po obnovení stránky s parametrom v URL).

= 0.22.6 =
* Poradie menu: Dashboard, Content Types, Listing Builder, Search Filters, Dynamic Forms, SEO Builder (aj štatistiky na Dashboarde).
* Content Types: Type má pri novom type výber „— select —“ (povinné), Content sa už nepredvolí. Post type: Post, Page, CPT.
* Checkboxy a radio v administrácii: 1,25rem, rámik 1px #949494, radius 4px.
* Time a ďalšie vstupy majú rovnaký radius ako ostatné polia. Add Option je väčšie tlačidlo, možnosti majú väčší odstup.
* HTML pole: popis nahradil placeholder v textarea. Items list: zrozumiteľnejší popis.
* Dashboard: Table Builder „Coming soon“, kým nie je v balíku Gruppa Tools.

= 0.22.5 =
* Gruppa Text (Title, Link): ikona z knižnice ikon Elementoru alebo vlastné SVG. Pozícia pred textom, za textom alebo nad textom. Štýl Icon: veľkosť, odstup od textu, zvislé zarovnanie, farba a farba pri hoveri.

= 0.22.4 =
* Dynamic Forms: skrytý Title s prázdnou hodnotou blokoval vytvorenie záznamu (chyba pri neviditeľnom poli). Teraz sa názov vygeneruje ako pri formulári bez Title (názov formulára + dátum).
* Skryté hodnoty môžu používať polia formulára, napr. Title = „{meno_firma} – {email}“.
* Chyby polí, ktoré nie sú vo formulári viditeľné, sa vypíšu v hlásení nad tlačidlom.

= 0.22.3 =
* Dynamic Forms: väzby na Data typy (skupiny a hodnoty) sú dostupné ako polia formulára. Display as: Radio buttons / Checkboxes ↔ Select podľa voľby One / Many.
* Návštevník vyberá iba z publikovaných hodnôt skupiny; neplatné a nepublikované ID sa ignorujú.
* Skryté pole s väzbou prijme ID, názvy alebo slugy oddelené čiarkou (napr. „Hory, Pláž“).
* V úprave záznamu sa väzby predvyplnia; väzba, ktorá nie je vo formulári, sa nemení.
* {kľúč_väzby} a {all_fields} vypíšu názvy hodnôt; väzby sú dostupné aj v podmienkach akcií.

= 0.22.2 =
* Elementor widget Gruppa Text (Textové pole): obsah s HTML (editor, dynamický tag) sa už nedoplňuje o <br> za každým riadkom. Obyčajný text bez HTML zalamuje riadky ako doteraz.

= 0.22.1 =
* Nové pole HTML: obsah (nadpisy, text, <hr> ako oddeľovač) zadaný pri tvorbe poľa sa zobrazí medzi poľami v úprave záznamu. Nemá stĺpec v databáze, neukladá hodnotu.
* Nové pole Time: čas HH:MM (stĺpec TIME), na webe vo formáte času z nastavení WordPressu.
* Nové pole Repeater: riadky so stĺpcami Text, Textarea, Number, Date, Time, Switcher, Select, Image / file; pridávanie, mazanie a ťahanie riadkov. Uloží sa ako JSON, v REST pole objektov, dynamic tag vypíše riadok na riadok.
* HTML a Repeater zatiaľ nie sú v Dynamic Forms, filtroch a zoradení listingu. Typ uloženého Repeater / HTML poľa sa nedá zmeniť.
* Skupiny Data typu v nastaveniach typu na plnú šírku.

= 0.22.0 =
* Content Types: druhy typov Content a Data. Druh Relation je zrušený — existujúce typy sa pri aktualizácii zmenia na Data (Values only, bez pripojenia).
* Parent odstránený (nemal funkciu). Poradie polí typu: Name, Slug, Type.
* Data typ: Structure „Values only“ alebo „Groups and values“. Hodnoty majú stĺpce group_id, group_title, group_slug; skupina je logické delenie, polia sú spoločné pre celý Data typ.
* Pripojenie (Attach to) ku Content typom pri skupine alebo pri celom Data type, aj k viacerým typom naraz; výber One (radio) alebo Many (checkboxy); voliteľný stĺpec v zozname záznamov.
* Úprava záznamu Content typu: box pre každú pripojenú skupinu pod hlavnými poľami (vyhľadávanie pri viac ako 8 hodnotách, odkaz Manage).
* Hodnoty Data typu: taby podľa skupín, stĺpec Group, Add New s predvolenou skupinou, hromadná akcia Move to group. Skupina s hodnotami sa nedá odstrániť, premenovanie prepíše group_title.
* Nová tabuľka wp_gruppa_relations (item_type, item_id, data_type, value_id). Zmazanie záznamu alebo hodnoty zmaže jej väzby, kópia záznamu ich prenesie.
* Väzby fungujú ako pole Relation s kľúčom {data}__{skupina}: dynamic tags, Listing Builder (Related to current item), Search Filters (parameter = slug skupiny), filtre a stĺpce v zozname záznamov, REST (relations v zázname, zápis cez ID, názvy alebo slugy, filter[{data}__{skupina}]).
* Listing Builder: pri Data type so skupinami voľba Group (zoznam hodnôt jednej skupiny).
* Pole Relation sa už nedá pridať; existujúce Relation polia fungujú ďalej.
* REST API: riadky Read / Create / Update / Delete sa zobrazia len pri REST API = True.
* Dynamic Forms: voľba „Same as content type“ zrušená — formulár má vždy vlastné „Who can submit“; existujúce formuláre dostali pravidlo, ktoré mal ich typ.
* DB verzia 10. Slugy relations a relation sú rezervované.

= 0.21.3 =
* Dashboard → Gruppa Tools: všetkých 5 add-onov v poradí menu (Import/Export, CSS Builder, Table Builder, Notify, Media), stav rozpozná aj add-ony z balíka Gruppa Tools 4.
* Názov Import / Export zjednotený na Import/Export.

= 0.21.2 =
* Plugin header: Plugin URI a Author URI zmenené na oficiálny web https://gruppa.cloud.

= 0.21.1 =
* Gruppa Text: nové pole Max characters — text nad limit sa oreže (predvolene na celé slovo) a ukončí „…“, na webe aj v náhľade editora.

= 0.21.0 =
* Nový Elementor widget Gruppa Text: Title / Textarea / Link v jednom widgete. Tag h1–h6, div, p, span; zarovnanie vrátane do bloku; typografia, farba a hover, podčiarknutie odkazu, tieň a obrys textu, max. šírka, max. počet riadkov (orezanie „…“), blend mode; margin, padding, pozadie, rámik, zaoblenie, box shadow. Text aj odkaz podporujú dynamic tags.
* Widget Gruppa Search Filters premenovaný na Gruppa Search (iba názov, uložené stránky fungujú bez zmeny).

= 0.20.0 =
* Dashboard (prvá položka menu Gruppa CMS): počty typov, záznamov, formulárov, listingov, filtrov a SEO; prehľad typov obsahu; skratky; stav add-onov Gruppa Tools; systém (verzie, Elementor, JetEngine, jazyk webu).
* Mazanie dát pri odinštalovaní: voľba na Dashboarde (predvolene vypnutá). Zapnutá = pri zmazaní pluginu sa odstránia typy, záznamy, formuláre, listingy (aj šablóny), filtre a SEO. Bežné WordPress príspevky a stránky, používatelia, médiá a dáta add-onov ostanú. Deaktivácia nikdy nič nemaže.

= 0.19.0 =
* Záznamy — úprava: polia vľavo, vpravo panel, ktorý ostáva na mieste pri rolovaní (Status, ID, autor, dátumy, Update, View, Duplicate, Delete) a box SEO (Add / Edit SEO priamo zo záznamu).
* Záznamy — zoznam: pri poli nová voľba „Items list“ = stĺpec v zozname (Select / Relation ako názvy, Switcher ✓, Media náhľad, dátum). Polia Select, Radio, Checkbox, Relation a Switcher v zozname sa dajú aj filtrovať (rozbaľovacie menu nad tabuľkou, „Clear filters“).
* Záznamy — hromadné akcie: Publish, Move to Draft, Duplicate, Delete (so zaškrtávaním a „vybrať všetko“). Slug sa zobrazuje pod názvom, stĺpce Slug / Author odstránené.
* Relation a Select s viacerými hodnotami sa v admine zobrazujú ako zaškrtávacie políčka (nad 8 možností s vyhľadávaním) namiesto výberu cez Ctrl+klik. Web používa naďalej nastavenie Display.
* Duplicate: typy obsahu (nastavenia a polia, bez záznamov), formuláre, listingy (aj s kópiou Elementor šablóny), skupiny Search Filters a záznamy. Kópia je vždy Draft „… (copy)“ s voľným slugom.
* Dynamic Forms: nové nastavenie „Who can submit“ (Same as content type / Everyone / Logged in / rola; pri Update a Delete aj Author). Úprava formulára upozorní, keď ho odhlásení návštevníci neuvidia; adminovi sa na webe nad takým formulárom zobrazí poznámka.
* Texty na webe (formuláre, validácia, listing, filtre, prihlásenie) sú v jazyku webu — pribalená slovenčina (languages/gruppa-cms-sk_SK), šablóna gruppa-cms.pot pre ďalšie jazyky. Uložené predvolené texty (Send, Thank you., Nothing found., Zrušiť filtre…) sa zobrazia v jazyku webu automaticky.
* Varovanie pred odchodom z úpravy s neuloženými zmenami (typy, záznamy, formuláre, listingy, filtre, SEO).
* Pole Order odstránené z úprav (poradie sa mení ťahaním v zozname).
* Neexistujúci záznam: hlásenie a tlačidlo „Back to the list“ namiesto holého „Not found.“
* Polia URL / e-mail / telefón / heslo v admine na celú šírku (napr. SEO Canonical URL).
* Oprava: pri mazaní listingu / skupiny filtrov sa už nezobrazujú dve potvrdenia za sebou.

= 0.18.0 =
* Import / Export vyňatý z Gruppa CMS — teraz samostatný add-on Gruppa Import / Export (menu Gruppa Tools pod Gruppa CMS). Funkčnosť, zdroje (Gruppa CMS, SEO Builder, JetEngine CCT, WordPress / ACF) aj vzhľad ostali rovnaké.
* Po aktualizácii nainštalujte Gruppa Import / Export, ak Import / Export používate. Staré odkazy (?page=gruppa-io) add-on presmeruje.
* Tlačidlo „Import / Export“ v hlavičke zoznamu záznamov pridáva add-on cez nový hook gruppa_cms/items_header_actions (bez add-onu sa nezobrazí).
* Gruppa_Transfer v jadre ostáva len ako prevod hodnôt polí (coerce) pre formuláre a add-ony.

= 0.17.1 =
* Rich text editor vo formulári: vlastná predvolená výška a dá sa naťahovať myšou (úchyt vpravo dole). Nastavenia Text fields (výška textarea, resize, padding…) sa ho už netýkajú; border a radius naďalej áno.

= 0.17.0 =
* Search Filters (menu pod Listing Builder): skupiny filtrov pre content type. Filtre ako karty (pridať, zoradiť, rozkliknúť, odstrániť): Search (hľadá v Title a vybraných textových poliach), Select, Checkbox (viac možností naraz), Range (číslo od – do, najnižšia / najvyššia hodnota sa doplní sama), Date (od – do, aj Created / Modified).
* Možnosti Select / Checkbox sa berú z poľa: Relation → publikované súvisiace záznamy (napr. Zimná, Letná), Select / Radio / Checkbox → ich možnosti, Switcher → Áno / Nie, Text → použité hodnoty. Žiadne ID.
* Správanie: filtrovať hneď pri zmene (vyhľadávanie s krátkym oneskorením) alebo tlačidlom; odkaz „Zrušiť filtre“ všetko vyčistí.
* Widget Gruppa Search Filters (výber skupiny, „Filtre v riadku“ alebo pod sebou; štýly Form, Label, Text fields, Select, Checkbox, Button, Reset link) a shortcode [gruppa_filters group="slug"].
* Widget Gruppa Listing: nové pole „Search Filters“ — skupina, ktorá listing ovláda (shortcode: filters="slug"). Listing sa obnoví bez načítania stránky, stránkovanie a „Načítať viac“ rešpektujú filtre, nový filter začína od 1. strany.
* Filtre sú v URL s čitateľnými hodnotami (?kategoria=zimna,letna&cena_from=500&q=alpy) — odkaz sa dá poslať, po obnovení stránky filtre ostanú; bez JavaScriptu funguje ako bežný formulár.
* Viac widgetov rovnakej skupiny na stránke sa navzájom zosynchronizuje.
* Systémová tabuľka wp_gruppa_filters.

= 0.16.4 =
* Oprava: rich text editor vo formulári sa zobrazoval ako obyčajná textarea (od 0.12.8) — nastavenie písma s dvojitými úvodzovkami rozbilo konfiguráciu TinyMCE.

= 0.16.3 =
* Oprava: „Update & edit template“ / „Publish & design template“ hlásilo „Odkaz vypršal“ (nonce v presmerovaní bol zakódovaný ako &amp;).
* Listing: pevné filtre odstránené (boli mätúce; uložené staré filtre sa už neuplatňujú). Návštevnícke filtre budú v samostatnom module Search Filters.
* Listing: nová voľba „Related to current item“ — Relation pole (napr. kategorie_dovoleniek); na stránke záznamu (napr. kategória Zimná) ukáže len k nemu priradené položky, inde všetky.
* Listing bez výsledkov: editor vidí vysvetlenie, prečo (len publikované, Only my items, Related to current item).

= 0.16.2 =
* Listing filtre: pri Relation poli sa dá zadať názov alebo slug súvisiaceho záznamu (Zimná, zimna) namiesto ID; pri Select / Radio / Checkbox aj popis možnosti namiesto hodnoty. Platí aj pre {get:…} (?kategoria=zimna). Neznámy názov = žiadna zhoda.

= 0.16.1 =
* Listing — hodnoty filtrov z kontextu: {current_id} (ID záznamu, na ktorého stránke listing je), {current:pole} (jeho pole, napr. {current:kategoria}; pri viacerých hodnotách stačí zhoda s ktoroukoľvek), {get:parameter} (z URL, napr. ?kategoria=5). Ak hodnota chýba (nie je aktuálny záznam, chýba parameter), filter sa preskočí.
* Listing vnútri šablóny iného listingu berie ako „current“ položku cyklu (napr. súvisiace záznamy v karte).
* „Načítať viac“ posiela kontext stránky, takže filtre s {current…} a {get:…} platia aj pre ďalšie strany.

= 0.16.0 =
* Listing Builder (menu pod Dynamic Forms): listing = content type (Content aj Data) + query + šablóna položky.
* Query: zoradenie (Order, Title, Created, Modified, ID, Random alebo pole; čísla zoradené ako čísla), počet na stránku, „len moje záznamy“, pevné filtre (=, ≠, obsahuje, >, ≥, <, ≤, prázdne, neprázdne; pri multi poliach a Relation „obsahuje hodnotu“; premenné {user_id}, {today}, {now}), text pri prázdnom výsledku. Vždy len publikované záznamy.
* Šablóna položky v Elementore (Edit template / Publish & design template) — skrytý post typ gruppa_listing, otvorí sa Elementor Canvas. Gruppa dynamic tagy (Field, Image, URL, Gallery) s Item = Current čítajú položku cyklu; v editore šablóny sa zobrazí prvý záznam ako náhľad. Funguje s Elementorom free.
* Widget Gruppa Listing: výber listingu, počet, stĺpce (desktop 3 / tablet 2 / mobil 1), medzery, rovnaká výška kariet, stránkovanie čísla / „Načítať viac“ (bez načítania stránky) / žiadne, text pri prázdnom výsledku; štýl stránkovania a tlačidla (Normal / Hover / Active).
* Shortcode [gruppa_listing listing="slug" per_page="6" columns="3" pagination="numbers|load_more|none"].
* Bez Elementor šablóny sa zobrazí jednoduchý zoznam názvov. Šablóny nie sú verejné stránky (404 pre návštevníkov, mimo sitemapy). Listing sa nedá vložiť do vlastnej šablóny.
* Systémová tabuľka wp_gruppa_listings; rezervované slugy aj listings, listing, templates, filters, queries.
* Pripravené pre Gruppa Filter: filter gruppa_cms/listing_request_filters a gruppa_cms/listing_where.

= 0.15.2 =
* Form builder: náhľad odosielacieho tlačidla neutrálne tmavosivý (#3c434a, biely text), neklikateľný, s popisom pri prejdení myšou.

= 0.15.1 =
* Admin: vyhľadávanie v jednom riadku s nadpisom (vpravo) — SEO Builder aj zoznamy záznamov. Na mobile pod nadpisom na celú šírku.

= 0.15.0 =
* SEO Builder (menu nad Import / Export): zoznam SEO záznamov + Add New, Owner = záznam Content typu (zoskupený podľa typu, jeden záznam = jedno SEO; tie, čo už SEO majú, sú vypnuté).
* Systémové polia: seo_robots (index, follow / index, nofollow / noindex, follow / noindex, nofollow), seo_title, seo_description (počítadlá znakov), seo_keywords, seo_canonical; Open Graph (title, description, image, type); Twitter / X (card, title, description, image); vlastný JSON-LD. Prázdne polia preberajú hodnoty (OG ← SEO, Twitter ← OG, obrázok ← predvolený).
* Náhľad v editore: Google výsledok a karta pri zdieľaní (Facebook, LinkedIn, WhatsApp), mení sa pri písaní; noindex je v náhľade vyznačený.
* Settings: šablóna titulku pre záznamy bez SEO ({title} {sep} {site} {type}), predvolený OG obrázok, Twitter / X účet, organizácia + logo (štruktúrované dáta), overenie Google Search Console a Bing (kód alebo celý meta tag).
* Web: titulok, robots (+ max-image-preview:large) a canonical cez filtre WordPressu (bez duplicít), description, keywords, og:* (locale, url, site_name, rozmery a alt obrázka), twitter:*, JSON-LD (WebSite, WebPage, Article pri príspevkoch, Organization). noindex záznamy vynechané z WordPress sitemapy. Pri aktívnom Yoast / Rank Math / AIOSEO / SEOPress Gruppa SEO nič nevypisuje (upozornenie v admine).
* Záznam (edit): tlačidlo Add SEO / Edit SEO v hlavičke. Zmazanie záznamu zmaže aj jeho SEO.
* Systémové tabuľky jednotne wp_gruppa_{názov}: wp_gruppa_types, wp_gruppa_forms (predtým wp_gruppa__forms — pri aktualizácii sa premenuje aj s dátami), wp_gruppa_seo.
* Rezervované slugy content typov (types, forms, seo, settings, options, meta, users, user, log, logs, actions, submissions, notify, io, system, cms, core) — „This slug is used by the system“. Ak už content type taký slug má, systémová tabuľka si nechá starý názov a admin zobrazí upozornenie.
* Import / Export: zdroj „Gruppa CMS — SEO Builder“. Owner pri importe: WP post ID (napr. z JetEngine SEO CCT), typ:slug alebo typ:#ID; existujúce SEO ownera sa prepíše. Stĺpce seo_* sa namapujú automaticky.

= 0.14.0 =
* Gruppa Login — Elementor widget a shortcode [gruppa_login]: prihlásenie (email alebo meno + heslo, Zapamätať si ma), zabudnuté heslo a nastavenie nového hesla priamo na stránke (odkaz v emaile vedie späť na tú istú stránku), pre prihláseného „Logged in as … · Log out“.
* Login: presmerovanie po prihlásení / odhlásení, odkaz na registráciu, všetky texty upraviteľné, po novom hesle voliteľne rovno prihlási. Ochrana: 5 neúspešných pokusov = 15 min blokovanie (podľa IP), jedna spoločná chybová hláška, zabudnuté heslo neprezradí, či účet existuje, presmerovanie len v rámci webu.
* Widget Gruppa Login: rovnaké štýly ako Gruppa Form (Form, Label, Text fields, Checkbox, Button) + Links & texts; „Editor preview“ prepne v editore Login / Lost password / Logged in.
* Dynamic Forms: zdroj záznamu „Item of current user“ (Update / Delete) — posledný záznam, ktorého autorom je prihlásený používateľ (napr. Môj profil). Záznam sa určuje na serveri, nie z URL.
* Prístup: nové pravidlo „Author of the item (own items)“ pre Update a Delete — používateľ upravuje / maže len svoje záznamy (formuláre aj REST API); vidí aj svoje koncepty.
* Štýlovacie ovládače widgetov presunuté do spoločného traitu (Gruppa_Form_Style).

= 0.13.0 =
* Dynamic Forms — sekcia Actions v editore formulára: karty ako pri poliach (pridať, zoradiť ťahaním, zapnúť / vypnúť, rozkliknúť, odstrániť). Akcie bežia po uložení záznamu, zhora nadol.
* Email: komu (viac adries, {admin_email}, pole napr. {email} pre automatickú odpoveď), predmet, správa, HTML / text, Reply-To, From. {all_fields} vloží prehľadnú tabuľku vyplnených polí. Akcií Email môže byť viac.
* Webhook: POST JSON alebo form data na URL (Make, n8n, Zapier…) — event, form, type, item, user, čas, web. Voliteľný podpis X-Gruppa-Signature (HMAC SHA-256).
* Update related item: cez Relation pole zmení pole súvisiaceho záznamu — nastaviť / zvýšiť / znížiť (napr. voľné miesta školenia −1), aj status.
* Register user: z polí formulára vytvorí WP účet (len hostia; pred uložením — existujúci email = chyba pri poli), meno, heslo alebo email „nastaviť heslo“, rola (bez admin rolí), prihlásenie, autor záznamu.
* Podmienky pri každej akcii: všetky / ktorákoľvek; je, nie je, obsahuje, je prázdne, nie je prázdne, väčšie, menšie.
* Premenné všade (predmet, správa, komu, Reply-To, hodnota, presmerovanie, správa po odoslaní): {title}, {id}, {slug}, {status}, {permalink}, {form}, {type}, {site_name}, {site_url}, {admin_email}, {date}, {user_email}, {user_name}, {all_fields}, {kľúč_poľa} (čitateľné hodnoty — názvy možností a záznamov).
* Moderácia: Stav nových záznamov — Published / Draft pre hostí / vždy Draft (admin sa nemoderuje).
* Mode Delete: formulár s jedným tlačidlom zmaže záznam (z URL alebo aktuálny), potvrdenie s vlastným textom, platí pravidlo Delete na type; akcie (email, webhook…) dostanú zmazaný záznam.
* Háčiky: gruppa_cms/form_action_types, gruppa_cms/form_action_{type}, gruppa_cms/form_webhook_payload.

= 0.12.8 =
* Rich text editor vo formulári preberá border z Text fields (typ, hrúbka, farba Normal / Hover / Focus, radius).
* Písmo v editore: neutrálne bezpätkové (systémové sans-serif, 15 px) namiesto WordPress serifu.

= 0.12.7 =
* Rich text editor vo formulári: flat sivá lišta (bez rámčekov, tmavé ikony, jemný sivý hover, bez spodnej stavovej lišty, žiadne tiene), štýly tlačidiel témy / Elementor kitu do nej už nepresakujú.

= 0.12.6 =
* Widget Gruppa Form — nové sekcie Style: Select fields (dedí z Text fields, vyplníš len rozdiely), Checkbox, Radio, Switcher, Media upload.
* Checkbox / Radio: rozloženie (vedľa seba / pod sebou), medzera medzi možnosťami, veľkosť boxu, medzera box ↔ text, typografia, padding, margin, typ a hrúbka borderu, radius (checkbox), Normal / Hover / Checked (pozadie, border, text, farba fajky / bodky, veľkosť bodky).
* Switcher: šírka, výška, veľkosť a radius gombíka, radius dráhy, Normal / Hover / Checked (farba dráhy a gombíka).
* Media upload: vlastné tlačidlo (predvolene preberá štýl tlačidiel Elementoru / témy) + názvy vybraných súborov; v Style typografia, farby Normal / Hover, border, radius, padding, štýl názvov a poznámky, veľkosť náhľadov.
* Builder: pri Media poli sa Placeholder mení na „Button text“ a Description na „Note“ (nahradí automatický text typy · max MB · počet).
* Checkboxy a radio sa kreslia vlastným CSS (rovnaký vzhľad v každom prehliadači, dajú sa štýlovať).

= 0.12.5 =
* Dynamic Forms (zoznam): odstránené stĺpce Slug, Mode, Author, Status. Ostáva ID, Name, Type, Shortcode (širší, celý viditeľný), Modified, Actions.

= 0.12.4 =
* Dynamic Forms — Media upload na frontende, len pre prihlásených (hostia vidia „Log in to upload files.“). Kontrola typov súborov, max MB a max počtu súborov v prehliadači aj na serveri; WordPress overí skutočný typ súboru. Súbory idú do knižnice médií (autor = prihlásený používateľ). Pri update móde sa dajú existujúce súbory odstrániť (×) a pridať nové. Pri chybe sa nahraté súbory zmažú.
* Builder: „Display as“ pri poli — Editor ↔ Textarea, Select ↔ Radio, Select (multiple) ↔ Checkboxes, Checkboxes ↔ Select (multiple), Switcher ↔ Checkbox, Relation Select ↔ zoznam.
* Editor pole sa na frontende zobrazuje ako rich text editor (TinyMCE, zjednodušená lišta) — predtým vždy textarea.
* V builderi sú dostupné aj Media polia.

= 0.12.3 =
* Widget Gruppa Form — sekcia Form: Width (%, px, vw), Alignment, Column gap, Row gap, Outline none (odstráni focus outline prehliadača zo všetkých polí aj tlačidla).
* Zo sekcie Form odstránené Border radius, Field border, Button / accent (sú v sekciách Text fields a Button). Pôvodný Gap nahradili Column / Row gap.
* Text fields: prepínač outline presunutý do sekcie Form.

= 0.12.2 =
* Elementor widget Gruppa Form — Style: nové sekcie Label, Text fields a Button.
* Label: typografia, farba, farba hviezdičky (required), pozadie, zarovnanie, margin, padding, border (typ, hrúbka, farba), border radius.
* Text fields (text, email, tel, password, number, date, textarea/editor; select zatiaľ dedí): typografia, zarovnanie textu, Normal / Hover / Focus (farba textu, placeholder, pozadie, border, tieň, skryť outline), border radius, padding, margin, výška poľa, výška textarea, resize.
* Button: typografia, zarovnanie (vrátane full width), šírka, výška, Normal / Hover (farba, pozadie, border, tieň, stmavenie), border, radius, padding, margin, transition.
* Button má vlastnú triedu a silnejšie selektory — štýly témy / Elementor kitu (napr. ružový hover v Hello) ho už neprepíšu.

= 0.12.1 =
* Dynamic Forms na frontende: shortcode [gruppa_form form="slug"] (Gutenberg: blok Shortcode) a Elementor widget Gruppa Form (výber formulára, medzery, radius, farby, typografia).
* Odoslanie cez REST /gruppa/v1/form-submit/{form} — netreba zapínať REST API na type, platia pravidlá Create / Update daného typu.
* Layout z buildera (šírky, label, placeholder, popis), skryté polia vynúti server, chyby pri konkrétnych poliach, správa po odoslaní / reset / skrytie / presmerovanie s {id}, {slug}, {title}, {pole}.
* Update mód: záznam z URL parametra (?item=ID) alebo aktuálny záznam (single šablóna), polia predvyplnené.
* Formulár bez poľa Title: názov sa vygeneruje (Názov formulára – dátum).
* Antispam honeypot, akcia gruppa_cms/form_submitted pre ďalšie akcie (email…).
* Admin: shortcode v zozname formulárov aj v editore, kto môže formulár odoslať.

= 0.12.0 =
* Nový Content Type: po Publish presmeruje rovno na jeho záznamy (pri Draft ostane na nastaveniach typu).
* Content Type (edit): v hlavičke tlačidlá Items a Add New.
* Záznam (edit): po uložení ostane na zázname, v hlavičke Názov · Back · View · Add New.
* Zoznam záznamov: v hlavičke Edit Type a Import / Export (s predvoleným typom).
* Import / Export integrovaný z Gruppa Tools 3.2.2 (bez Notify) — menu Gruppa CMS hneď pod Dynamic Forms, UI v štýle Gruppa CMS.
* Zdroje: Gruppa CMS, JetEngine CCT, WordPress post types + ACF; mapovanie polí, prepis podľa ID, voľba autora, dávkový import po 50.
* Filter gruppa_cms/io_sources pre ďalšie zdroje. Beží aj popri Gruppa Tools (vlastné triedy aj AJAX akcie).

= 0.11.0 =
* Nový typ poľa Relation: Related type (výber content typu, Relation typy navrchu), Multiple, Display (Select / Checkboxes-Radio), Required, Placeholder.
* Ukladá ID záznamov (jedno ID, pri Multiple JSON pole). Validácia: len existujúce záznamy zo zvoleného typu.
* Výstup: Elementor Field tag / Block Bindings vypíšu názvy (oddelené separátorom), URL tag → permalink prvého záznamu. REST vracia ID (int alebo pole), schéma obsahuje "related".
* Import (Gruppa Tools): Relation prijme ID aj názvy, napr. "Horské|Cestné".

= 0.10.0 =
* Post type pri type Content: Post / Page / Custom Post.
* Custom Post: Gruppa zaregistruje vlastný post type (názov = slug typu, max. 20 znakov; pri kolízii prefix g_). URL /<slug>/<záznam>/, v admin menu skrytý (záznamy len v Gruppa CMS), v Elementor Theme Builder conditions dostupný → vlastná single šablóna.
* Zmena post typu pri existujúcom type presunie WP príspevky existujúcich záznamov na nový post type.
* Rewrite pravidlá sa obnovia automaticky po uložení/zmazaní Custom typu.

= 0.9.9 =
* Post type pri type Content: len Post a Page. Šablóny, listingy a formuláre z builderov vyradené (vznikali v nich prázdne, neviditeľné príspevky). Ďalšie typy cez filter gruppa_cms/single_post_types.

= 0.9.8 =
* Add Content Type: nadpis "Main Settings" nad prvým boxom.
* Admin: všetky tlačidlá (Add New, Back, Add Field, Publish…) border-radius 6px.
* Post type: Floating Elements (Elementor) vyradené zo zoznamu; filter gruppa_cms/single_post_types.

= 0.9.7 =
* Content Types: pole Type (Content / Data / Relation) namiesto Single post. Content = záznam so šablónou (post type), Data = bez šablóny, Relation = hodnoty na výber v iných typoch (logika v ďalšej verzii).
* Content Types: pole Parent (max. 2 úrovne). Zobrazí sa až keď existuje iný typ; typ, ktorý už má deti, parent mať nemôže.
* Add Content Type: všetko v jednom boxe (Name, Slug, Order, Parent, Type, Status, REST API, Read/Create/Update/Delete, Author, Created, Modified, DB Table, Endpoint). Sekcia "Fields" → "Meta Fields".
* Admin: šírka max. 1440 px (pod tým celá šírka), boxy border-radius 12px, polia 6px, rovnaký vertikálny aj horizontálny padding.
* Admin: žiadne sivé pozadia pod textom (slug, DB table, počty).
* "Order index" → "Order". REST schéma typu vracia kind a parent.

= 0.9.6 =
* Admin: rovnaký vzhľad ako Gruppa Tools — filtre All / Published / Draft ako underline taby s počtami, zoznamy a formuláre v bielych kartách.
* Admin: "Content types" → "Content Types".

= 0.9.5 =
* Plugin header: description, author Gruppa Taxonomy, save4web.com.

= 0.9.4 =
* Elementor: Field select has a "— Vyberte pole —" placeholder and full width, so the first field (Title) can be selected.

= 0.9.3 =
* Admin: Export / Import page removed (moved to Gruppa Tools). Import core kept for item saving.
* Admin: "Types" renamed to "Content types".

= 0.9.2 =
* Admin: consistent spacing between status filter and list tables.

= 0.9.1 =
* Text field formats: Email, Phone, URL, Password (validated; passwords hashed, never exposed).

= 0.9.0 =
* Dynamic Forms (backend): forms list, editor, layout builder, REST form rules (_gruppa_form).
* REST validation errors keyed by field.

= 0.8.0 =
* Renamed to Gruppa CMS, modular structure (modules/blocks, modules/elementor).
* Elementor: "Gruppa CMS" widget category and widget loader.

= 0.7.0 =
* Elementor dynamic tags, Block Bindings source.

= 0.6.0 =
* Access per Type, REST API.

= 0.5.0 =
* Export / Import (CSV, JSON).
