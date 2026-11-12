=== Gruppa Notify ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.4
License: GPLv2 or later

Gruppa Tools add-on. Native Web Push notifications without external services: notifications after Gruppa CMS Form Builder, JetFormBuilder and Contact Form 7 forms for chosen users and roles, optional push for new items of a Gruppa CMS entity or JetEngine CCT, log of sent notifications, subscribe button via [gruppa_notify_button] and device list.

== Notifikácia z formulára ==

1. Na stránku pre prihlásených (napr. frontend admin) daj shortcode [gruppa_notify_button]. Používateľ ho stlačí a povolí notifikácie — jeho zariadenie sa objaví v Notify → Devices.
2. Gruppa CMS → Form Builder → formulár → Actions → Send notification: vyber používateľov (s počtom zariadení) a/alebo roly, text s premennými (napr. {meno}) a čo sa otvorí po kliknutí (záznam v admine, stránka záznamu alebo vlastná URL).
3. Po odoslaní formulára dostanú vybraní používatelia push na všetky svoje zariadenia. Každá notifikácia sa zapíše do Notify → Log (komu, text, formulár, počet zariadení, či ju otvoril).

Komu a kam notifikácia ide, nastavuje iba admin vo formulári — návštevník to nevidí ani nemení.

== JetFormBuilder a Contact Form 7 (aj bez Gruppa CMS) ==

Notify → Forms → Add form: vyber formulár, používateľov a/alebo roly, text s premennými podľa polí formulára (napr. {meno}, {your-name}) a URL po kliknutí. Push odíde po úspešnom odoslaní (JetFormBuilder) alebo po kontrole spamu pred odoslaním e-mailu (Contact Form 7). Tab Forms sa zobrazí, keď je aktívny JetFormBuilder alebo Contact Form 7.

== Zdroj (voliteľné) ==

* Gruppa CMS entita (napr. Data entita Notifikácie) alebo JetEngine CCT — Settings → Source. Polia: User ID (Number s ID používateľa; pri entite aj Author), Message, URL, Read (Switcher, nastaví sa na 1 po kliknutí).
* Push odíde hneď po vytvorení záznamu (Gruppa CMS 0.29.4+: akcia gruppa_cms/item_created; JetEngine: jet-engine/custom-content-types/created-item/{slug}); čo hook nezachytí, dopošle cron každých 5 minút.
* Pri zmene zdroja sa staré záznamy neposielajú — počíta sa od aktuálneho najvyššieho ID.

Menu: Gruppa Tools → Notify. Shortcode pre tlačidlo: [gruppa_notify_button]

== Inštalácia ==

Ak Notify beží zo starého Gruppa Tools 3.x, aktualizujte ho na Gruppa Tools 4 (obsahuje aj Notify) alebo ho deaktivujte a zmažte. Starý plugin Gruppa Notify deaktivujte a zmažte.
Nastavenia, VAPID kľúče aj zaregistrované zariadenia zostanú (rovnaké options aj tabuľka zariadení — od 1.1.0 wp_gcms_notify_subs).

== Pre vývojárov ==

* gruppa_notify( $user_ids, array( 'title' => '', 'body' => '', 'url' => '' ) ) — pošle push a zapíše ho do Logu.
* Akcie gruppa_notify/sent ( $results, $args ) a gruppa_notify/read ( $log_row ).
* Filter gruppa_notify/i18n — texty tlačidla na frontende.

== Changelog ==

= 1.4.4 =
* Hub 1.6.2: rovnaká medzera medzi úchytom a checkboxom v zoznamoch.

= 1.4.3 =
* Hub 1.6.1: jednotné tlačidlá (výška inputu, rovnaký radius, bez tieňa a outline, hover modré pozadie s bielym textom).

= 1.4.2 =
* Hub Gruppa Tools 1.6.0: rovnaký vzhľad ako Gruppa CMS 0.32.4 aj bez Gruppa CMS — farby Gruppa (accent #2952EA, hover #0604CD, nadpisy #040506, text #575770), nadpisy boxov vnútri boxu v levanduľovej hlavičke, rytmus 24 px, bunky zoznamov 12 px 24 px, jemné linky.

= 1.4.1 =
* Hub Gruppa Tools 1.5.0: pevné poradie menu Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify; upozornenie na starý balík Gruppa Tools.
* Bez zmeny funkcií.

= 1.4.0 =
* Nový tab Forms: notifikácie po odoslaní formulára JetFormBuilder alebo Contact Form 7 — aj bez Gruppa CMS. Príjemcovia (používatelia, roly), text s premennými z polí formulára, URL po kliknutí, zapnutie / vypnutie. Zápis do Logu.

= 1.3.0 =
* Form Builder (Gruppa CMS 0.28+): akcia Send notification — príjemcovia podľa používateľov a rolí, text s premennými, po kliknutí záznam v admine / stránka záznamu / vlastná URL, podmienky ako pri ostatných akciách.
* Nový tab Log (tabuľka wp_gcms_notify_log): každá notifikácia s príjemcom, textom, zdrojom, počtom zariadení a časom otvorenia; Clear log.
* Zdroj (entita / CCT) je voliteľný — predvolene None. Doterajšie nastavenie zdroja ostáva.
* Funkcia gruppa_notify() pre vlastný kód; Send test to me sa tiež zapisuje do Logu.

= 1.2.0 =
* Nový zdroj: Gruppa CMS entita (popri JetEngine CCT). Settings → Source so skupinami Gruppa CMS a JetEngine CCT; polia sa ponúkajú podľa zvoleného zdroja. Uložené nastavenie CCT funguje ďalej (hodnota bez prefixu = JetEngine).
* Okamžitý push pre Gruppa CMS cez akciu gruppa_cms/item_created (Gruppa CMS 0.29.4+), inak cron každých 5 minút.
* Zmena zdroja nepošle staré záznamy.

= 1.1.1 =
* Gruppa Tools hub 1.4.0: poradie v menu Import/Export, CSS Builder, Payments, Notify (Notify 40); upozornenie na ukončený balík Gruppa Tools.

= 1.1.0 =
* Tabuľka wp_gruppa_notify_subs → wp_gcms_notify_subs (systémový prefix Gruppa CMS 0.28), option verzie DB gruppa_tools_notify_db → gruppa_notify_db_version. Zaregistrované zariadenia ostávajú, migrácia beží pri prvom načítaní.

= 1.0.7 =
* Gruppa Tools hub 1.3.0: v hlavičke stránky pribudol odkaz Tutorial (vedľa prepínača Help) na PDF príručku add-onu na gruppa.cloud.

= 1.0.6 =
* Odstránenie dát: nový uninstall.php — pri zmazaní pluginu sa tabuľka odberov, nastavenia, VAPID kľúče a cron odstránia iba ak je zapnuté „Delete the data of a Gruppa Tools add-on when it is deleted“ (Gruppa CMS → Dashboard → Data). Inak ostáva všetko pre opätovnú inštaláciu.

= 1.0.5 =
* Gruppa Tools hub 1.2.0: pomoc pri nastaveniach — ikonka ⓘ pri názve poľa otvorí popis v bubline; prepínač Help v hlavičke zobrazí všetky popisy pod poľami (ikonky zmiznú), zapamätá sa v prehliadači.

= 1.0.4 =
* Gruppa Tools hub 1.1.1: poradie menu Import/Export, Table Builder, CSS Builder, Notify, Media; väčšie checkboxy a radio ako v Gruppa CMS 0.22.6.

= 1.0.3 =
* Oprava: aktivácia samostatného Gruppa Notify pri aktívnom Gruppa Tools 4 (alebo druhej kópii Notify) už nespôsobí fatal error „Cannot redeclare gruppa_notify_legacy()“.
* Poradie v menu Gruppa Tools: štvrté (po Table Builder).
* Gruppa Tools hub 1.1.0: aktuálny vzhľad Gruppa CMS.
* Funguje aj ako súčasť balíka Gruppa Tools 4 — nahrádza starý Gruppa Tools 3.x, nastavenia, kľúče aj zariadenia ostávajú.
* Nový popis pluginu.

= 1.0.2 =
* Plugin URI a Author URI: https://gruppa.cloud (oficiálny web Gruppa).

= 1.0.1 =
* VAPID kľúče sa vygenerujú aj na serveroch bez openssl.cnf (Local, XAMPP, MAMP, Windows) — plugin použije vlastný minimálny config. Platí aj pre šifrovanie pri odosielaní.
* Ak generovanie aj tak zlyhá, hláška ukáže verziu OpenSSL a presnú chybu.
* Jasnejšia hláška, keď JetEngine nie je aktívny / nemá Custom Content Types.

= 1.0.0 =
* Samostatný add-on vyňatý z Gruppa Tools 3.2.2 (tab Notify).
* Admin v štýle Gruppa CMS: taby Settings / Devices, karta s nastavením, zoznam zariadení ako tabuľka.
* Zoznam CCT priamo z wp_jet_post_types (JetEngine API ako záloha).
