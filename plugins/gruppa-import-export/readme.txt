=== Gruppa Import/Export ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.4
License: GPLv2 or later

CSV and JSON import and export for Gruppa CMS types, SEO Builder, JetEngine CCT and WordPress post types with ACF fields: automatic field mapping, overwrite by ID, author choice, chunked import. Without Gruppa CMS in the Gruppa Tools menu; with Gruppa CMS as Data Tools.

== Kde je v menu ==

* Gruppa Tools → Import/Export (prvé v menu: Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify) — s Gruppa CMS 0.31+ aj bez neho.
* Staršie Gruppa CMS 0.29.3–0.30 mali Import/Export v sebe (Data Tools): novší plugin tam beží namiesto vstavanej kópie, stále ako Gruppa CMS → Data Tools. Dáta sa nestratia (Import/Export nemá vlastnú tabuľku).

== Pre vývojárov ==

* Filter gruppa_ie/sources — ďalšie zdroje (trieda odvodená od Gruppa_IE_Source).
* Filter gruppa_ie/sideload_media( bool, url ) — false = obrázky z iného webu sa nesťahujú.
* Tlačidlo v hlavičke zoznamu záznamov Gruppa CMS cez akciu gruppa_cms/items_header_actions.

== Changelog ==

= 1.3.4 =
* Hub 1.6.3: všetky polia (inputy aj selecty) rovnaká výška 40 px.

= 1.3.3 =
* Hub 1.6.2: rovnaká medzera medzi úchytom a checkboxom v zoznamoch.

= 1.3.2 =
* Hub 1.6.1: jednotné tlačidlá (výška inputu, rovnaký radius, bez tieňa a outline, hover modré pozadie s bielym textom).

= 1.3.1 =
* Hub Gruppa Tools 1.6.0: rovnaký vzhľad ako Gruppa CMS 0.32.4 aj bez Gruppa CMS — farby Gruppa (accent #2952EA, hover #0604CD, nadpisy #040506, text #575770), nadpisy boxov vnútri boxu v levanduľovej hlavičke, rytmus 24 px, bunky zoznamov 12 px 24 px, jemné linky.

= 1.3.0 =
* Gruppa CMS 0.31 už Import/Export neobsahuje — plugin je vždy v menu Gruppa Tools → Import/Export (prvý). S Gruppa CMS 0.29.3–0.30 sa ako doteraz zobrazí ako Gruppa CMS → Data Tools.
* Kód importu a exportu sa nemení (1.2.3: médiá cez URL).
* Hub Gruppa Tools 1.5.0: pevné poradie menu Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify; upozornenie na starý balík Gruppa Tools.

= 1.2.3 =
* Import obrázkov: URL sa najprv páruje s knižnicou médií cieľového webu bez ohľadu na doménu — podľa cesty v uploads (napr. A01.png, 2025/09/A01.png), potom podľa názvu súboru kdekoľvek v uploads (aj ručne nahraté do 2026/11/, aj verzia -scaled). Stiahne sa len obrázok, ktorý na cieľovom webe nie je. Filter gruppa_ie/match_media_by_name — false vypne párovanie podľa názvu.

= 1.2.2 =
* Import obrázkov: jedna požiadavka beží najviac ~20 s (filter gruppa_ie/time_budget), zvyšok dávky pošle prehliadač ďalšou požiadavkou — sťahovanie veľa obrázkov už nespôsobí timeout servera; priebeh sa posúva po riadkoch. Timeout sťahovania jedného súboru 20 s (filter gruppa_ie/download_timeout). Pri zlyhaní požiadavky sa zobrazí HTTP kód a odpoveď servera.

= 1.2.1 =
* JetEngine CCT: polia Media a Gallery sa exportujú ako URL obrázkov (namiesto ID), galéria ako „url1, url2“. Platí pre všetky formáty hodnoty (ID, URL, ID + URL).
* ACF: polia Image, File a Gallery sa exportujú ako URL.
* Import obrázkov z iného webu: URL, ktorá na cieľovom webe neexistuje, sa stiahne do knižnice médií. Pôvodná URL sa uloží (meta _gruppa_ie_source_url), takže opakovaný import nevytvára duplikáty. Do CCT sa uloží vo formáte poľa (ID, URL alebo ID + URL).
* Filter gruppa_ie/sideload_media — vrátením false sa sťahovanie vypne.

= 1.2.0 =
* Rovnaký kód ako Data Tools v Gruppa CMS 0.29 (jeden súbor pre oba). S Gruppa CMS 0.29.3+ sa zobrazí ako Gruppa CMS → Data Tools, bez neho v menu Gruppa Tools. Nadpis stránky Import/Export alebo Data Tools podľa miesta.
* Gruppa Tools hub 1.4.0: poradie Import/Export, CSS Builder, Payments, Notify; upozornenie na ukončený balík Gruppa Tools.
* Odstránené hlásenie pre Gruppa CMS 0.17 a staršie.

= 1.1.1 =
* Gruppa Tools hub 1.3.0: v hlavičke stránky pribudol odkaz Tutorial (vedľa prepínača Help) na PDF príručku add-onu na gruppa.cloud.

= 1.1.0 =
* Gruppa CMS záznamy: stĺpec Group (slug skupiny) pri Data typoch so skupinami — export aj import (pri novom zázname povinný).
* Väzby na Data typy: export ako názvy (pri voľbe One jedna hodnota), import podľa názvu, slugu alebo ID; nenájdené hodnoty sa vypíšu ako upozornenie.
* Repeater sa exportuje ako JSON a importuje späť ako riadky (potrebuje Gruppa CMS 0.27.0+); Time sa exportuje ako HH:MM.

= 1.0.4 =
* Gruppa Tools hub 1.2.0: pomoc pri nastaveniach — ikonka ⓘ pri názve poľa otvorí popis v bubline; prepínač Help v hlavičke zobrazí všetky popisy pod poľami (ikonky zmiznú), zapamätá sa v prehliadači.

= 1.0.3 =
* Gruppa Tools hub 1.1.1: poradie menu Import/Export, Table Builder, CSS Builder, Notify, Media; väčšie checkboxy a radio ako v Gruppa CMS 0.22.6.

= 1.0.2 =
* Názov Gruppa Import / Export zjednotený na Gruppa Import/Export (aj v menu).
* Gruppa Tools hub 1.1.0: poradie menu Import/Export, CSS Builder, Table Builder, Notify, Media; aktuálny vzhľad Gruppa CMS.
* Funguje aj ako súčasť balíka Gruppa Tools 4.
* Nový popis pluginu.

= 1.0.1 =
* Plugin URI a Author URI: https://gruppa.cloud (oficiálny web Gruppa).

= 1.0.0 =
* Samostatný add-on vyňatý z Gruppa CMS 0.17.1 (Import / Export) — rovnaká funkčnosť aj vzhľad.
* Spoločné menu Gruppa Tools pre všetky add-ony (zdieľaný hub v priečinku /hub).
* Vlastné triedy, AJAX akcie aj slug stránky — beží aj popri starších verziách Gruppa CMS.
