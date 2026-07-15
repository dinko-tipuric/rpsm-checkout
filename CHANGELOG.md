# Changelog

## 1.5.0.4 (2026-07-15) - HOTFIX: fatal na checkoutu za SVE pretplate (GLAVNI uzrok)

- woocommerce_checkout_subscription_created registriran s accepted_args=2, a handler zahtijeva 3 parametra ($recurring_cart bez defaulta) -> PHP 8 ArgumentCountError FATAL usred kreiranja pretplate na checkoutu -> WC prikaze "Došlo je do greške prilikom obrade vaše narudžbe". Pucalo za SVAKI pretplatnicki proizvod od 1.5.0.0, neovisno o atribucijskim podacima. Fix: accepted_args=3 + $recurring_cart = null default. Ostale registracije auditirane - poklapaju se.

## 1.5.0.3 (2026-07-15) - HOTFIX: ugnijezdeni save u woocommerce_new_order

- Atribucija se na woocommerce_new_order upisivala i SPREMALA odmah - a taj hook se okida USRED spremanja narudzbe (datastore create()), pa je nas $order->save() radio ugnijezdeni save u nedovrsenom checkout toku i obrada placanja je pucala (dupli "Atribucija upisana" u debug logu, order 10324). Fix: on_new_order vise nista ne pise; sav upis (atribucija + upsell korekcija) odgodjen na shutdown, gdje se narudzba svjeze ucita i sigurno spremi. Idempotencija preko _rpsm_attr_type ostaje.

## 1.5.0.2 (2026-07-15) - HOTFIX: checkout/Stripe redirect pucao

- Atribucijska REST ruta je na SVAKOM page loadu zvala `initialize_session()` + `set_customer_session_cookie(true)`, cime je pisala NOVI WC session kolacic i razbijala postojecu checkout sesiju. Posljedica: narudzba se kreirala, ali vanjski Stripe redirect nije isao (placanje stalo). Fix: REST ruta vise NE dira sesiju (no-op); atribucija se cita direktno iz `$_COOKIE` server-side u apply_attribution(). Kolacic rpsm_attr se ionako salje sa svakim zahtjevom na portal, pa REST/sesija nisu potrebni.

## 1.5.0.1 (2026-07-15) - HOTFIX: obnove pucale

- `wcs_renewal_order_created` je FILTER, ne action; handler je vracao void i time NULL-irao renewal order (wcs_create_renewal_order() vratio null), pa je "Process renewal" i automatska obnova pucala. Sad add_filter + return $renewal_order, uz defenzivnu instanceof provjeru. Akvizicijski (frontend) put nije bio pogodjen.

## 1.5.0.0 (2026-07-14) - Atribucija (SPEC-atribucija.md, Sloj 2)

Novi modul "Atribucija": narudžba pamti svoj izvor/kampanju/CTA kroz cijeli lanac www -> portal -> narudžba.

- Novi modul `includes/modules/class-module-attribution.php`. Primarni put je WC SESIJA, ne skriveno polje - narudžbe na portalu nastaju i programski (buy-now, rpsm-upsell post-purchase, WCS obnove/switchevi), pa bi skriveno polje na klasičnom checkoutu sve to promašilo.
- JS (na SVAKOJ portal stranici kad je modul uključen, ne samo checkoutu) čita kolačić `rpsm_attr` (piše ga rpsm-web na www, domain=.radimposvom.com.hr) i, ako WC sesija još nema atribuciju, jednom pošalje POST na novu REST rutu `rpsm-checkout/v1/attr` (nonce + IP rate limit 20/min).
- Ruta whitelista ključeve, sanitizira (`sanitize_text_field`), cappa na 128 znakova i sprema u `WC()->session->set('rpsm_attr', ...)`. Server nikad ne vjeruje inputu i ne prepisuje sesiju ako već ima atribuciju.
- Prepis sesije u order meta na `woocommerce_checkout_create_order` (klasični checkout) I `woocommerce_new_order` prio 20 (buy-now, blocks checkout, sve programske narudžbe).
- Fallback skriveno polje `rpsm_attr_payload` (JSON) na `woocommerce_after_order_notes`, ista server-side sanitizacija - koristi se samo ako je WC sesija prazna.
- ⚠️ WCS obnove i switchevi: atribucija se kopira s parenta/pretplate (NE iz sesije) i `_rpsm_attr_type` se force-a na `renewal` - na `wcs_renewal_order_created` i na `woocommerce_subscription_checkout_switch_order_processed`. Bez ovoga bi obnove padale u "direct" ili se brojale kao nova akvizicija (napuše ROAS).
- rpsm-upsell narudžbe (post-purchase): detektirane preko postojećeg `_rpsm_upsell_parent_order` order meta (upisuje ga rpsm-upsell), provjera odgođena na `shutdown` jer ta meta stiže tek nakon prvog `woocommerce_new_order`; tip se postavlja na `upsell`, atribucija se kopira s originalne narudžbe.
- Bez privole na www nema kolačića - narudžba NE dobiva `_rpsm_attr_type` uopće ("bez privole" u izvještaju, nikad "direct").
- Admin: stupac "Izvor" u listi WC narudžbi (HPOS + Legacy CPT), cijeli lanac atribucije na order edit stranici, novi tab "Atribucija" (toggle, retencija sesije u danima, debug pregled zadnjih 50 zapisa).
- Order meta ključevi (prefiks `_rpsm_attr_`): first_source/medium/campaign/content/term/landing/ts, last_source/medium/campaign, lp, cta, click_id, type. HQ (Faza 3, van opsega ovog releasea) čita baš te ključeve.

## 1.4.1.1 (2026-07-14)

- UI kit: puna sirina admin stranica (max-width cap uklonjen; Dinkov QA ispravak).

## 1.4.1.0 (2026-07-14) - Sprint E

- UI kit skin: RPSM Admin UI kit (rpsm-admin-kit.css) kopiran u plugin i enqueue-an samo na stranici plugina, prije plugin CSS-a; Poppins font za naslove; body klasa rpsm-kit-page + wrap klasa rpsm-admin. Bez funkcionalnih promjena.

## 1.4.0.0 (2026-07-14) - Sprint D

- MULTIPRODUCT LINK: ?add-to-cart=X,Y (vise proizvoda jednim linkom) migriran iz portal snippeta u Kuponi modul; radi s &coupon=KOD bez duple primjene (postojeci dedup). ⚠️ Toggle DEFAULT OFF - ukljuciti TEK nakon gasenja snippeta (istovremeni rad duplo puni kosaricu).
- Editable cart: default mod 'summary_x' (portal ga koristi); 'table' je legacy/deprecated, brisanje u v1.5. Postojece instalacije sa spremljenom opcijom nisu dirane.
- Performance: gettext prijevodi - domain bail PRIJE lazy-loada parova (filter se okida tisucama puta po stranici, i u adminu).
- Ciscenje: scroll-block no-op inject_script + hook van; mrtve varijable u adminu van.

## 1.3.0.1 (2026-07-12)

### Bugfixes (produkcijski nalaz: kupnja već kupljenog proizvoda)

- **ERR_TOO_MANY_REDIRECTS na Buy Now za već kupljeni proizvod**: blokirani add-to-cart ostavi praznu košaricu na checkoutu, WC prazan checkout redirecta na cart URL, a editable-cart filter je cart URL vraćao natrag na checkout - beskonačna petlja. Cart URL se sada preusmjerava na checkout SAMO dok košarica nije prazna (petlja je latentno postojala za svaki dolazak na prazan checkout).
- **Vlasniku se kupnja više ne nudi**: za zaštićene proizvode koje je kupac već platio skrivaju se add-to-cart forma i Buy Now gumb (is_purchasable), katalog pokazuje "Saznaj više", a na stranici proizvoda stoji info poruka s linkom na Moj račun. Dodan per-request memo za provjeru vlasništva.

---

## 1.3.0.0 (2026-07-12)

### Novo: modul "Jednokratna kupnja"

- Odabrani proizvodi (novi tab u postavkama, product search lista) mogu se kupiti samo JEDNOM po kupcu. Provjera pokriva tri ulaza: dodavanje u košaricu (uključivo Buy Now linkove), košaricu nakon logina (stavka se uklanja uz poruku) i checkout po billing emailu (kad račun još ne postoji). Vlasništvo se gleda po korisniku I emailu kroz plaćene narudžbe - neplaćene (pending) ne blokiraju.
- Poruka kupcu je editabilna ({proizvod} placeholder) + link na Moj račun (tekst editabilan, prazno = bez linka).
- Zaštićeni proizvodi su automatski i "sold individually" (ni 2 komada u istoj košarici).
- Default lista (Dinko, 2026-07-12): Društvena prodaja, Intuicijom do CILJA, Isplaniraj nezaboravnu godinu, LIDER u nastajanju, Neodoljiva prodaja u inboxu, Odaberi pravu biz ideju, Planiraj kao CEO, Poduzetnički START, Prodajni ritam, Sljedećih tisuću, SOS: Nitko ne kupuje. Pretplate, MASTERMIND i grupe NISU na listi.
- Nastanak: produkcijski nalaz - kupac platio isti program dvaput u dva dana; WC "sold individually" štiti samo unutar iste košarice, a upsell skip-owned samo upsell blok.

---

## 1.2.0.3 (2026-07-08)

### Bugfixes

- Checkout je skakao na vrh stranice pri SVAKOJ promjeni (metoda plaćanja, unos polja...) kad je postojala poruka "dodano u košaricu" od buy-now linka: Elementor tu poruku re-renderira pri svakom AJAX osvježenju pa ju je scroll-block observer svaki put tretirao kao novu i dopustio scroll. Dva sloja: success notice-i se brišu na checkoutu prije rendera (greške ostaju), a observer scrolla samo na PRVU pojavu istog teksta poruke.

---

## 1.2.0.2 - 2026-07-06

### Bugfixes

- X za uklanjanje premješten desno uz iznos stavke (kolona Međuzbroj), u istom redu s iznosom (nowrap) - naziv proizvoda se više ne lomi zbog gumba.
- Krug oko X-a uklonjen: sada je čisti boldani copper × (hover rust), diskretan ali jasno vidljiv.

---

## 1.2.0.1 - 2026-07-06

### Bugfixes

- X gumb u sažetku se prikazivao kao goli sivi znak (theme/Elementor button reset + CSS agregat cache). Fix: jači selektori s !important na ključnim svojstvima + inline CSS fallback koji ide s HTML-om pa ga cache agregiranog CSS-a (Autoptimize) ne može promašiti.

---

## 1.2.0.0 - 2026-07-06

Uređiva košarica dobiva novi način prikaza - X gumb u sažetku narudžbe:

- Nova opcija "Način prikaza" u Kuponi i košarica tabu: **X gumb u sažetku "Tvoja narudžba"** (preporučeno) ili **zasebna tablica iznad checkouta** (staro ponašanje, default radi kompatibilnosti).
- X mod: uz svaku stavku u sažetku stoji vidljivi copper × gumb (AJAX uklanjanje + osvježavanje checkouta). Sažetak je fragment koji WooCommerce ionako osvježava, pa nema druge košarice ni problema sa sinkronizacijom dvaju prikaza.
- Uklanjanje zadnje stavke i dalje vodi na shop (postojeće ponašanje).
- X se dodaje isključivo unutar review-order konteksta - mini-cart, cart stranica i emailovi se ne diraju.
- Cilj: dugoročno se u potpunosti riješiti zasebne tablice; stari mod ostaje kao fallback.

---

## 1.1.2.0 - 2026-07-06

Sinkronizacija uređive košarice + čišćenje checkout URL-a (nalazi s RPSM Upsell staging testa):

- Uređiva košarica na vrhu checkouta sada je registrirana kao checkout fragment i osvježava se na SVAKI update_checkout (dodavanje/uklanjanje kroz upsell ponude, kuponi, promjene količine). Do sada je ostajala zaleđena na stanju s učitavanja stranice, pa se činilo da se checkout smrznuo.
- Buy Now: nakon što WooCommerce obradi ?add-to-cart na checkoutu, URL se čisti redirectom. Do sada je svaki reload stranice ponovno pokušao dodati proizvod, pa su "sold individually" artikli bacali error notice.

---

## 1.1.1.0 - 2026-07-06

Thank-you redirect za besplatne narudžbe:

- Narudžbe s totalom 0 € nemaju način plaćanja (WooCommerce preskoči odabir plaćanja), pa gateway-specifični thank-you hook nikad nije okinuo i korisnik je ostajao na thank-you stranici. Novi generički hook hvata narudžbe bez payment methoda s totalom 0 € i radi isti redirect na /hq.
- Auto-redirect na GTM timeout za besplatne narudžbe - GTM možda nema purchase event za 0 € pa korisnik ide na redirect automatski umjesto da čeka fallback gumb.
- Nove opcije u Thank-you tabu: toggle "Redirect za besplatne narudžbe" (default uključen) + zaseban naslov za besplatne narudžbe (bez "plaćanje je uspješno").
- Guard protiv dvostrukog rendera kad bi oba hooka okinula na istom page loadu.

---

## 1.1.0.9 - 2026-06-27

Ispravak prikaza ikona:

- Ikone i banneri sada nose verzijski parametar u URL-u (?v=verzija), pa preglednik i WordPress povlace svjezu ikonu pri svakoj novoj verziji umjesto stare iz cachea.

---

## 1.1.0.8 - 2026-06-27

Dokumentacija:

- Dodan opis plugina koji se prikazuje u "Prikazi detalje" (View details) modalu.
- Uskladjen pravopis e-maila u tekstovima.

---

## 1.1.0.7 - 2026-06-27

Brending (bez promjene ponasanja):

- Nove plugin ikone i banner (jedinstveni cross-brand identitet "solid glyph + dubina").
- Ikona + banner sada izlozeni i u update transientu i u "Prikazi detalje" (icons + banners u check_update i plugin_info).
- Uklonjeni svi em/en-dash znakovi iz koda i tekstova.

---

## 1.1.0.6 - 2026-06-25

### Bugfixevi

- **Skip-on-sale sada stvarno spriječi dvostruki popust.** Kad je proizvod na sniženju, kupon se ne samo preskače nego se i **makne iz košarice** ako je ostao iz ranijeg koraka (npr. primijenjen prije nego je proizvod stavljen na sale). Bez ovoga se događalo: proizvod na sale 299 + zaostali kupon -100 = 199. Brisanje je **cart-only** (prije kupnje) i ne dira postojeće pretplate - grandfather kupon živi na pretplati, ne u košarici.
- **Pouzdanija on-sale detekcija.** `is_on_sale()` se sada provjerava na svježem proizvodu po ID-u, a ne na cart item `data` objektu kojem WCS kod switcha override-a cijenu (zbog proracije), što je znalo vraćati `on_sale: false` iako je proizvod stvarno na sniženju.

---

## 1.1.0.5 - 2026-06-25

### Bugfixevi

- **Auto-apply na switch sada stvarno radi.** Glavni uzrok zašto se kupon nije primjenjivao: kod je ovisio o `wcs_cart_contains_subscription_switch()`, a ta WooCommerce Subscriptions funkcija NIJE učitana na `wp_loaded` hooku (dijagnostika pokazala `wcs_switch_fn_exists: false`), pa je funkcija odmah izlazila. Sada se switch detektira **direktno iz cart itema** (`subscription_switch` flag), bez ovisnosti o `wcs_*` funkcijama. Isto popravljeno i u "Prikaži kupon polje na switchu".
- **Auto-apply vješan na više hookova** (`wp_loaded` prio 99, `woocommerce_before_checkout_form`, `woocommerce_check_cart_items`, `woocommerce_add_to_cart`) da uhvati učitanu košaricu neovisno o timingu i Elementor checkoutu.

---

## 1.1.0.4 - 2026-06-25

### Dijagnostika

- **Robusnija switch dijagnostika.** Prošla verzija je logirala samo ako je switch detektiran na `wp_loaded`, što je moglo promašiti zbog timinga učitavanja košarice i Elementor checkout widgeta. Sada se snapshot košarice loga **bezuvjetno** (kad je Debug uključen i košarica učitana), na više pouzdanih hookova (`woocommerce_checkout_init`, `woocommerce_before_checkout_form`, `woocommerce_check_cart_items`, `woocommerce_add_to_cart`, te `wp_loaded` prio 99 kao zadnji safety net). Loga sve stavke košarice s `product_id`/`variation_id`/`is_switch` flagom + `current_hook` + postoji li `wcs_*` funkcija. Jedan unos po requestu.
- Time se razdvaja "pipeline logiranja ne radi" od "switch nije detektiran": `wp_loaded` safety net se okida na bilo kojoj frontend stranici kad je košarica učitana.

---

## 1.1.0.3 - 2026-06-25

### Bugfixevi / dijagnostika

- **Debug zapisi za switch sada rade neovisno o auto-apply toggle-u.** Ranije je sva switch dijagnostika živjela unutar `auto_apply_switch_coupons()`, koji se pokreće samo ako je uključen toggle "Auto-primijeni kupon na switch" - pa ako je bio isključen, log je ostajao prazan. Dodana zasebna `log_switch_diagnostics()` koja se okida čim je Debug mod uključen i switch je u košarici, neovisno o toggle-u i kuponima. Zapisuje stvarne `product_id`/`variation_id`/naziv svake switch stavke + konfigurirane ciljeve + primijenjene kupone (jednom po requestu).
- Podsjetnik: log se piše u `wp-content/uploads/rpsm-checkout/debug-YYYY-MM.log` i čita u adminu (RPSM Checkout > Debug > Zadnjih 100 unosa).

---

## 1.1.0.2 - 2026-06-25

### Bugfixevi

- **Skip-on-sale više NE briše kupon** - uklonjena `remove_coupon` logika koja je mogla maknuti kupon iz switch košarice. Skip-on-sale sada samo sprječava NOVU primjenu kad je proizvod na sniženju; nikad ne briše već primijenjeni kupon i nikad ne dira postojeće pretplate (grandfatherane cijene su sigurne).

### Dokumentacija / točnost (prema službenom WCS ponašanju)

- Help tekstovi ispravljeni prema tome kako WooCommerce Subscriptions stvarno radi:
  - **"Kupon za sve obnove (grandfather)"** je glavno polje za trajni popust - tip "Recurring Product Discount" skida iznos sa svake obnove i grandfathera cijenu (399 → 299 trajno).
  - **"Jednokratni kupon"** (Fiksni popust na košaricu) skida SAMO upfront iznos switcha, NE obnove - ne grandfathera. Opcionalan sweetener.
  - Skip-on-sale: kad je proizvod na sniženju, switch sam grandfathera sniženu cijenu na pretplatu, pa kupon nije potreban (izbjegava dvostruki popust).
- Napomena: za grandfathering 299 koristi recurring kupon ili sale price; fiksni/postotni kupon NE mijenja recurring iznos pretplate.

---

## 1.1.0.1 - 2026-06-25

### Bugfixevi

- **Auto-apply na switch nije okidao kod grouped proizvoda** - kod switcha cart item nosi ID DJETETA (mjesečna/polugodišnja pretplata unutar grupe), a ne ID grouped wrappera, pa se nije poklapalo s ciljanim proizvodom. Dodana detaljna dijagnostika kroz Debug klasu: kad switch ne odgovara ciljevima, u log se zapišu stvarni `product_id`/`variation_id`/naziv svake switch stavke, pa se točan cilj može odabrati u adminu. Uključi Debug mod (tab Debug) za dijagnozu.

### Nova funkcionalnost

- **Preskoči kupon ako je proizvod na popustu** (novi toggle, default uključeno) - ako ciljani proizvod već ima sniženu cijenu (sale price), switch kupon se ne primjenjuje da se popust ne zbroji. Primjer: redovna 399, sniženo na 299 - kupon se preskače; ako nije na popustu (399), kupon odobrava popust na 299. Ako je kupon ranije primijenjen pa proizvod naknadno ode na popust, kupon se automatski makne.

---

## 1.1.0.0 - 2026-06-22

### Nova funkcionalnost - kuponi kod promjene pretplate (switch)

Rješava problem da kod prelaska s mjesečne na polugodišnju pretplatu (WooCommerce Subscriptions switch) nije bilo načina za unos kupona, pa se popust nije mogao ponuditi.

Sve opcije su u admin tabu **Kuponi**, sve opt-in (default isključeno):

- **Auto-primijeni kupon na switch** - kad cart sadrži switch na ciljani proizvod, plugin sam primijeni konfigurirane kupone. Nula ručnog unosa, kupac ne može zaboraviti kupon.
- **Ciljani proizvodi** - WooCommerce product-search dropdown (Select2) za odabir proizvoda/varijanti na koje se prelazi (npr. polugodišnji). Auto-apply se okida samo ako switch sadrži neki od ovih. U bazi se sprema kao comma-separated lista ID-eva.
- **Jednokratni kupon** - kod kupona za jednokratni popust na sam prelazak (WooCommerce tip "Fiksni popust na košaricu"). Primjenjuje se samo na upfront iznos switcha.
- **Kupon za sve obnove** - kod kupona za popust na sve buduće obnove (WooCommerce Subscriptions tip "Recurring Product Discount" / "% Discount"). Oba kupona mogu biti primijenjena istovremeno.
- **Prikaži kupon polje na switchu** - prisilno renderira standardno WooCommerce kupon polje na checkoutu dok traje switch (zaobilazi Elementorov Coupon toggle). Skip ako je WC-ova kupon forma već hookana (bez duplikata) i ako je kupon već primijenjen (poštuje "Sakrij kupon ako je primijenjen").

**Napomena:** ako jednokratni popust spusti iznos switcha na 0€, primjenjuje se postojeći mehanizam za očuvanje Stripe payment methoda kod 0€ switcha (snippet `fix_switch_preserve_payment_method`).

---

## 1.0.0.9 - 2026-04-14

### Bugfixevi

- **SVG ikona** - dodan `assets/icon.svg`; GitHub updater sada vraća `icons['svg']` što sprječava distorziju prikaza na WordPress stranici za ažuriranja.

---


## [1.0.0.0] - 2026-03-31

### Nova funkcionalnost - inicijalni release

Konsolidacija 11 Code Snippets snippeta u jedan plugin s admin UI-om i editabilnim tekstovima.

**Moduli:**

1. **Suglasnost (Legal)** - obavezni TnC checkbox + info box napomena za pretplatne proizvode
2. **Prijevodi** - gettext override za WooCommerce i Elementor Pro stringove (key-value editor)
3. **Payment prikaz** - slike podržanih kartica ispod Stripe gumba
4. **BACS kontrola** - skrivanje BACS gatewaya za pretplatne proizvode, unlock kuponom
5. **Kuponi** - sakrivanje kupon forme nakon primjene + primjena kupona iz URL-a
6. **Email validacija** - JS prijedlog ispravke + PHP hard stop za krive email adrese
7. **Uređiva košarica** - mini košarica s qty/remove na checkout stranici
8. **Blokada scrolla** - sprječava WC auto-scroll, dozvoljava scroll samo na error/notice
9. **Buy Now** - "Idi na plaćanje" gumb na product page (simple products)
10. **Thank-you (Stripe)** - redirect na /hq s GTM dataLayer čekanjem
11. **Polja** - shipping telefon na checkoutu

**Infrastruktura:**
- Modularni sustav - isključen modul = nula overhead
- Admin UI s 9 tabova i editabilnim tekstovima
- Debug klasa po globalnom RPSM standardu
- GitHub auto-updater v2
- HPOS kompatibilnost deklarirana

**Zamjenjuje snippete:**
- `radimposvom_checkout_tnc_checkbox` / `radimposvom_checkout_tnc_validation`
- `radimposvom_show_stripe_card_logos` / `radimposvom_trigger_checkout_update_on_payment_change`
- `radimposvom_hide_coupon_if_applied`
- `radimposvom_apply_coupon_from_url`
- `radimposvom_checkout_editable_cart` (6 funkcija)
- `radimposvom_checkout_scroll_block`
- `radimposvom_buy_now_button`
- `radimposvom_email_typo_check` (JS + PHP)
- `radimposvom_stripe_thankyou_redirect`
- `radimposvom_wc_translations` + `radimposvom_elementor_translations`
- `radimposvom_shipping_phone_field` / `radimposvom_shipping_phone_display`
