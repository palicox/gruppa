=== Gruppa Payments ===
Contributors: Gruppa Taxonomy
Author URI: https://gruppa.cloud
Plugin URI: https://gruppa.cloud
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later

Gruppa Tools add-on. One-off payments and subscriptions through Stripe and PayPal without SDKs or WooCommerce: Payment action for the Gruppa CMS Form Builder, [gruppa_pay] button, PHP API, verified webhooks and every payment in its own table wp_gcms_payments.

Menu Gruppa Tools → Payments (Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify). Funguje aj bez Gruppa CMS (tlačidlo a PHP API), akcia vo formulároch potrebuje Gruppa CMS 0.28+.

== Brány ==

* Stripe — Checkout Sessions (jednorazovo aj predplatné), vlastná suma alebo Stripe Price ID, Customer Portal pre zákazníka, webhook so Stripe-Signature.
* PayPal — Orders v2 (jednorazovo, potvrdenie hneď pri návrate), predplatné cez Billing Plans (plán sa vytvorí raz pre sumu / menu / obdobie a uloží), webhook overený cez PayPal API (Webhook ID).
* Režim Test / Live, kľúče pre oba režimy. Test a live platby sú v tabuľke oddelené (stĺpec mode).
* Ďalšia brána: filter gruppa_payments/gateways + trieda odvodená od Gruppa_Pay_Gateway.

== Spustenie platby ==

* Form Builder → akcia Payment: suma a mena (alebo suma z poľa), Product name, jednorazovo / mesačne / ročne, názov platby s {placeholdermi}, pole s e-mailom. Po úhrade zapíše stav do poľa záznamu (napr. payment_status = paid, pri zrušení / vypršaní blocked), dátum „Uhradené do“ a môže záznam publikovať.
* Tlačidlo: [gruppa_pay amount="19.90" title="Členstvo"], predplatné period="month|year", Stripe cena price="price_…", jedna brána provider="stripe|paypal". Bez provider sa zobrazí tlačidlo pre každú nastavenú bránu. Parametre sú podpísané, návštevník sumu nezmení.
* PHP: Gruppa_Payments::checkout( [ … ] ), ::for_object(), ::is_paid(), ::cancel().
* Výsledok po návrate sa na stránke zobrazí sám, alebo cez [gruppa_pay_status]. [gruppa_pay_subscriptions] = predplatné prihláseného používateľa (Stripe: Spravovať, PayPal: Zrušiť).

== Databáza ==

* wp_gcms_payments — jeden riadok = jedna platba alebo predplatné. kind payment / subscription, status pending · paid · failed · canceled · refunded · expired (predplatné: pending · active · past_due · canceled · expired), amount v centoch + currency, period once / month / year, object_type + object_id (gruppa:{slug}, jet:{cct}, post, user), user_id, email, form_id, referencie brány (customer_ref, checkout_ref, payment_ref, subscription_ref), paid_at, paid_until, meta (JSON).
* Každé zaplatené obdobie predplatného je vlastný riadok (kind payment, parent_id = predplatné), takže tržby sa dajú sčítať jedným SELECTom.
* wp_gcms_payment_events — log webhookov, každá udalosť sa spracuje raz. Staršie ako 90 dní maže denný cron.
* Denná kontrola o 3:20: nedokončené platby po X hodinách → expired, predplatné po „Uhradené do“ + ochranná lehota → expired.

== Admin ==

* Payments a Subscriptions: prehľad za mesiac, filtre (stav, brána, režim, mesiac, hľadanie), CSV export (UTF-8, bodkočiarka), detail s odkazom do Stripe / PayPal, zaplatené obdobia, log webhookov, ručná zmena stavu (napr. platba prevodom), zrušenie predplatného.
* Settings: režim, mena, predvolená brána, stránky úspech / zrušenie, ochranná lehota, kľúče (tajné kľúče sa nikdy nevypisujú; prázdne = ponechať, „-“ = vymazať), URL webhookov so zoznamom udalostí, Uložiť a otestovať spojenie.

== Hooky ==

* gruppa_payments/status_changed ( $payment, $old, $new ), gruppa_payments/paid ( $payment ), gruppa_payments/updated ( $payment )
* filtre gruppa_payments/checkout_args, form_checkout_args, stripe_checkout_params, paypal_order, paypal_subscription, sync_values, update_object, currencies, gateways

== Odinštalovanie ==

* Dáta sa mažú iba ak je zapnuté „Delete the data of a Gruppa Tools add-on when it is deleted“ v Gruppa CMS → Dashboard → Data. Platby ostávajú v Stripe / PayPal.

== Changelog ==

= 1.1.3 =
* Hub 1.6.2: rovnaká medzera medzi úchytom a checkboxom v zoznamoch.

= 1.1.2 =
* Hub 1.6.1: jednotné tlačidlá (výška inputu, rovnaký radius, bez tieňa a outline, hover modré pozadie s bielym textom).

= 1.1.1 =
* Hub Gruppa Tools 1.6.0: rovnaký vzhľad ako Gruppa CMS 0.32.4 aj bez Gruppa CMS — farby Gruppa (accent #2952EA, hover #0604CD, nadpisy #040506, text #575770), nadpisy boxov vnútri boxu v levanduľovej hlavičke, rytmus 24 px, bunky zoznamov 12 px 24 px, jemné linky.

= 1.1.0 =
* JetFormBuilder bez Gruppa CMS: Payments → Forms — pravidlo pre formulár (suma alebo suma z poľa, mena, jednorazovo / predplatné, produkt, e-mail a meno z polí, URL po platbe). Po úspešnom odoslaní formulára (po všetkých jeho akciách) návštevník prejde na Stripe Checkout / PayPal (AJAX aj reload).
* Platba patrí príspevku, ktorý formulár vytvoril (Insert / Update Post), prihlásenému používateľovi alebo ničomu; stav platby, „Uhradené do“ a publikovanie sa zapíšu do post / user meta.

= 1.0.4 =
* Hub Gruppa Tools 1.5.0: pevné poradie menu Import/Export, Theme Builder, Table Builder, SEO Builder, Payments, Notify; upozornenie na starý balík Gruppa Tools.
* Bez zmeny funkcií.

= 1.0.3 =
* Gruppa Tools hub 1.4.0: poradie v menu Import/Export, CSS Builder, Payments, Notify (Payments 30); upozornenie na ukončený balík Gruppa Tools.
* Texty: Dynamic Forms → Form Builder (názov v Gruppa CMS 0.29).

= 1.0.2 =
* Každá platba má zákazníka v Stripe (Customers): podľa emailu sa nájde existujúci alebo vytvorí nový s menom. Bez emailu sa pri jednorazovej platbe zákazník vytvorí na platobnej stránke (customer_creation = always).
* Akcia Payment: nové pole Name field (meno zákazníka v Stripe; prázdne = Title záznamu).
* Product name s premennými (napr. staré predvolené „{form}: {title}“) už nevytvára produkt pre každý záznam — produkt dostane názov formulára, detail záznamu ostáva v zozname platieb.

= 1.0.1 =
* Jednoduchšie nastavenie — cena žije na webe, v Stripe netreba nič vytvárať: Stripe produkt sa vytvorí automaticky raz pre každý názov (Product name) a ďalšie platby ho použijú znova, takže Product catalog sa nezapĺňa. Ak produkt v Stripe niekto zmaže, vytvorí sa znova.
* Akcia Payment: riadok Amount (suma + mena), voliteľne Amount from a field, Billing, Product name (predvolene názov formulára). Riadok Gateway len keď sú nastavené obe brány. Stripe Price ID sa vo formulári už neponúka (akcie uložené s ním fungujú ďalej). Pole stavu, hodnoty pri úhrade / zrušení, Paid until a Publish v samostatných riadkoch.
* Settings: PayPal zbalený ako „PayPal (optional)“ — prázdne kľúče = len Stripe. Default gateway sa ukáže, až keď sú nastavené obe brány.

= 1.0.0 =
* Prvá verzia. Vychádza z platieb BlueWeb.one 0.6 (Stripe API bez SDK, overenie podpisu, Checkout, Customer Portal, denný cron s lehotou), prerobené na samostatný add-on: ľubovoľný objekt namiesto webapps, vlastná tabuľka wp_gcms_payments, PayPal, akcia v Dynamic Forms a tlačidlo.
