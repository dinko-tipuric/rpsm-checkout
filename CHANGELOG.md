# Changelog

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
