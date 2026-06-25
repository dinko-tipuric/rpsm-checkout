# Changelog

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

## 1.0.0.9 — 2026-04-14

### Bugfixevi

- **SVG ikona** — dodan `assets/icon.svg`; GitHub updater sada vraća `icons['svg']` što sprječava distorziju prikaza na WordPress stranici za ažuriranja.

---


## [1.0.0.0] — 2026-03-31

### Nova funkcionalnost — inicijalni release

Konsolidacija 11 Code Snippets snippeta u jedan plugin s admin UI-om i editabilnim tekstovima.

**Moduli:**

1. **Suglasnost (Legal)** — obavezni TnC checkbox + info box napomena za pretplatne proizvode
2. **Prijevodi** — gettext override za WooCommerce i Elementor Pro stringove (key-value editor)
3. **Payment prikaz** — slike podržanih kartica ispod Stripe gumba
4. **BACS kontrola** — skrivanje BACS gatewaya za pretplatne proizvode, unlock kuponom
5. **Kuponi** — sakrivanje kupon forme nakon primjene + primjena kupona iz URL-a
6. **Email validacija** — JS prijedlog ispravke + PHP hard stop za krive email adrese
7. **Uređiva košarica** — mini košarica s qty/remove na checkout stranici
8. **Blokada scrolla** — sprječava WC auto-scroll, dozvoljava scroll samo na error/notice
9. **Buy Now** — "Idi na plaćanje" gumb na product page (simple products)
10. **Thank-you (Stripe)** — redirect na /hq s GTM dataLayer čekanjem
11. **Polja** — shipping telefon na checkoutu

**Infrastruktura:**
- Modularni sustav — isključen modul = nula overhead
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
