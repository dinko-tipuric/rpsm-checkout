# Changelog

## 1.10.2.1 (2026-08-09) - Dynamic tag u grupu "Post"

- Tag "RPSM: Broj stavki sadrzaja" preseljen iz custom grupe "rpsm" u
  ugradjenu grupu "Post" (mv-portal-core pattern) - custom grupa se nije
  pojavljivala u Display Conditions dropdownu.

## 1.10.2.0 (2026-08-09) - Elementor dynamic tag za prazna polja sadrzaja

- Novi dynamic tag "RPSM: Broj stavki sadrzaja" (grupa RPSM): vraca broj
  stavki odabranog polja (moduli/faq/za_koga/stats/recenzije/video) za
  proizvod iz konteksta ili eksplicitni product_id; prazno polje = prazan
  output. Namjena: Elementor Pro Display Conditions "is empty / is not
  empty" za skrivanje naslova/sekcija (npr. "Moduli" bez modula).
- Product_Content: javni count_items() + resolve_for_display(); tag klasa
  lazy-load bez vlastitog konstruktora (mv-portal-core ctor lekcija).

## 1.10.1.7 (2026-08-06) - Fix: 16px pravilo nije djelovalo

- Mobilni override polja na 16px preseljen IZA osnovnog pravila od 14px
  (ista specificnost, kasnije pobjeduje) - u 1.10.1.6 je stajao u ranijem
  media bloku pa je iOS i dalje zumirao na fokus.

## 1.10.1.6 (2026-08-06) - Mobilni: bez 88px praznine + bez auto-zooma

- Uklonjena body rezerva od 88px ispod footera (sticky se ionako sakrije
  cim je footer u viewportu, pa je rezerva bila cista praznina).
- Polja forme na mobilnom 16px umjesto 14px - iOS vise ne zumira
  stranicu na fokus polja (zoom okida font < 16px).

## 1.10.1.5 (2026-08-06) - Zivi debug overlay

- ?rpsm-debug=1 overlay se sad osvjezava svake sekunde i na scroll
  (praznina na iOS-u nastaje tek nakon interakcije - pri loadu je visak
  tocno 88px); dodani scrollY, visualViewport visina i aktivni fokus.

## 1.10.1.4 (2026-08-06) - Debug overlay za iOS prazninu

- ?rpsm-debug=1 na express stranici ispisuje overlay s visinom dokumenta,
  dnom footera i elementima koji strse ispod - dijagnostika iOS-only
  praznine nakon footera (ne reproducira se u emulaciji).

## 1.10.1.3 (2026-08-06) - Sticky traka staje prije footera

- Mobilna sticky CTA traka se skriva i kad footer udje u viewport (ne samo
  kad je checkout forma vidljiva) - preko footera nema smisla.

## 1.10.1.2 (2026-08-06) - Mobilna sticky traka: safe-area + centriranje

- Sticky CTA traka na mobilnom: dvostruko visa (padding 16px), iOS safe-area
  inset (home indicator je rezao cijenu u zaobljenom kutu ekrana), cijena i
  Naruci gumb centrirani zajedno (gap 28px) umjesto rastegnuti do rubova.
  Body padding-bottom uskladjen.

## 1.10.1.1 (2026-08-06) - HOTFIX: express login redirect

- Prijava s express stranice opet vraca NA express: wp_get_referer() vraca
  false kad je referer jednak trenutnom URL-u (login forma POSTa na samu
  express stranicu) pa gate nikad nije okidao -> wp_get_raw_referer() +
  usporedba putanja kroz wp_parse_url.

## 1.10.1.0 (2026-08-06) - Ukupna usteda ukljucuje order bump

- "Usteda" red u express sazetku sada zbraja express deal + ustede prihvacenih
  order bumpova (iz rpsm_upsell cart mete). Kad je bump u igri, label postaje
  "Ukupna usteda" (bez postotka jer se odnosi na vise stavki); samo deal
  zadrzava stari "Usteda (-20%)". Red se prikazuje i kad SAMO bump nosi
  ustedu (bez aktivnog deala).

## 1.10.0.1 (2026-08-06) - HOTFIX: WP slashevi u tekst opcijama

- Spremanje tekst/textarea opcija nije radilo wp_unslash pa su navodnici u
  bazu ulazili kao \\" (vidljivo na owned kartici: Program \\"X\\" vec imas).
  Save sada unslasha; owned poruka dodatno stripslashes na ispisu za stare
  spremljene vrijednosti. Nakon updatea jedan Spremi u Express tabu cisti
  opciju trajno.

## 1.10.0.0 (2026-08-06) - [rpsm_express_ponude] auto popis ponuda

- NOVI SHORTCODE [rpsm_express_ponude] za roditeljsku /express/ (ili /kupi/)
  stranicu: automatski pronalazi SVE objavljene express stranice i renderira
  kartice - naslov, kratki opis (WC short description), stats chipovi iz
  Sadrzaja proizvoda, ziva cijena (get_price_html), badge "-X% ogranicena
  ponuda" kad je deal konfiguriran, copper CTA. Vlasnik proizvoda vidi
  "Vec imas" + link na Moj racun. Atributi: columns (1-3, default 2),
  exclude (page ID-evi), order (title|date|menu_order).
- Popis kesiran 10 min (transient), invalidacija na spremanju bilo koje
  stranice. Nova express stranica se pojavi sama; draft nestane.
- Stil u rpsm-product-content.css (portal skin), ucitava se samo gdje se
  shortcode koristi.

## 1.9.0.0 (2026-08-06) - Prijevodi preseljeni u rpsm-alati Lokalizacija

- UKLONJEN modul Prijevodi (gettext parovi) + admin tab - prijevodi sada
  centralno u rpsm-alati > Lokalizacija (.mo overrides svih 9 domena + editor
  parova). rpsm-alati v1.14.0.0 automatski migrira postojece parove iz opcije
  (opcija rpsm_checkout_translations_pairs ostaje u bazi kao izvor migracije).
- UKLONJEN express login-message gettext override (1.8.4.1) - pokriva ga
  centralni woocommerce .mo prijevod.
- ⚠️ DEPLOY REDOSLIJED: prvo rpsm-alati 1.14.0.0, onda ovaj update (inace
  parovi kratko ne rade izmedju dva updatea).

## 1.8.4.2 (2026-08-05) - Razmak ikona kartica bez countdowna

- Ikone kartica ispod CTA gumba: margin-top 20->32px pa razmak ne ovisi o
  deal timeru. Kad timer linija POSTOJI (aktivna ponuda), ikone se sibling
  selektorom primaknu njoj (12px) da ukupni razmak ostane isti kao dosad.

## 1.8.4.1 (2026-08-05) - HOTFIX: login blok van checkout forme

- REGRESIJA 1.8.4.0: login forma je bila hookana UNUTAR form.checkout, a
  ugnijezdeni <form> je nevaljan HTML - preglednik odbaci form.login tag
  (s njim i display:none) pa se prijava prikazivala rasklopljena i
  razlomljena bez klika na toggle. Login blok vracen izvan checkout forme.
- Zeljeni redoslijed (naslov -> login -> email) postignut obrnuto: modul
  ispisuje "Podaci za placanje" iznad login bloka (isti msgid kao
  form-billing.php), original u billing sekciji skriven CSS-om.
- Login forma stilizirana u express skinu: polja jedno ispod drugog,
  bijeli inputi na #f8f4ec bloku, copper gumb Prijava.
- Vi-forma poruka u login formi ("Ako ste vec kupovali... molimo...")
  na expressu ide u ti-formu (gettext + gettext_with_context).
- Prijava s express stranice vraca NA express stranicu (WC default salje
  na /placanje/ pa bi se izgubio express kontekst i deal cijena);
  override samo kad je referer express URL iz sesije.


## 1.8.4.0 (2026-07-20) - Login notice unutar forme

- "Kupac povratnik / prijava" notice se na expressu seli s vrha stranice
  IZMEDJU naslova "Podaci za placanje" i email polja (hook
  woocommerce_before_checkout_billing_form, odmah iza h3). Tekst se mijenja
  kroz Prijevodi tab (gettext) - default WC prijevod je "Kupac povratnik?".


## 1.8.3.3 (2026-07-20) - Sticky header prati deal traku

- Elementor sticky header se spusta za visinu deal trake NASIM CSS-om
  (body.rpsm-express-dealbar-on + .elementor-sticky--active top override)
  umjesto fiksnog Elementor Offseta - kad traka nestane (istek/bez ponude),
  header se sam vrati na vrh. U Elementoru sticky Offset postaviti na 0.


## 1.8.3.2 (2026-07-20) - Sadrzajni blokovi: X marker + trajanje desno

- "Nije za tebe ako": X (✗) marker umjesto crtice.
- Moduli accordion: trajanje poravnato skroz desno (dvije auto margine su
  dijelile prostor pa je visjelo u sredini); +/- tik iza trajanja.


## 1.8.3.1 (2026-07-20) - Deal traka +10px gore/dolje

- Deal traka: padding 10->20px vertikalno (mobilno 8->16px), body offset
  uskladjen (44->64px, mobilno 40->56px).


## 1.8.3.0 (2026-07-20) - Admin countdown reset (testiranje)

- Adminu (manage_woocommerce) se express countdown resetira SVAKIM
  ucitavanjem stranice (+ cisti se expiry ack flag) - uvijek svjeza traka
  za testiranje, bez brisanja sesije. Diskretna oznaka "admin: reset svakim
  ucitavanjem" u traci da se ponasanje ne pomijesa s bugom. Kupci imaju
  normalan sticky countdown.


## 1.8.2.1 (2026-07-20) - HOTFIX: timer ispod CTA ostajao --:--

- Linija "Popust istjece za" zivi u payment fragmentu koji WC zamijeni na
  svaki update_order_review - kesirana NodeList je drzala mrtve elemente.
  Timer elemente sada trazimo svjeze u svakom otkucaju.


## 1.8.2.0 (2026-07-20) - Countdown pozicioniranje po industrijskom standardu

- Countdown premjesten po patternu SamCart/Deadline Funnel/ThriveCart:
  (1) FIKSNA TRAKA NA VRHU EKRANA (mahogany, uvijek vidljiva) s gold pillom
  "-20% - usteda 5,40 EUR" + naslovom + timerom; (2) zeleni "Usteda" red u
  sazetku narudzbe iznad totala; (3) mala timer linija ispod Naruci gumba.
  Svi prikazi sinkronizirani na isti countdown; na istek traka nestaje,
  poruka isteka se pokaze u formi i fragmenti vrate punu cijenu.
- Slim R1 header CSS za express (uz r1-racun 1.1.4.0): naslov + toggle u
  jednom redu, bez ikone; opisni tekst preseljen iznad forme (vidi se tek
  na "Da").


## 1.8.1.0 (2026-07-20) - Express: bez rucnog unosa kupona

- Novi toggle "Sakrij unos kupona" (default ON): na express stranicama nema
  "Imate kupon?" polja - kupon dolazi iskljucivo kroz URL (?coupon=KOD,
  postojeci Kuponi modul) ili ga nema. Standardni checkout netaknut.


## 1.8.0.0 (2026-07-20) - Ogranicena ponuda (countdown popust) + samo kartica

- NOVO: Ogranicena ponuda na express stranicama (SPEC Dio 5): per-proizvod
  konfiguracija u meta boxu Prodajna stranica (postotak ili fiksni iznos,
  trajanje u minutama, tekstovi). Countdown krece od PRVOG posjeta (deadline
  u WC sesiji, refresh ne resetira), gold traka iznad forme, precrtana puna
  cijena u sazetku. SERVER JE AUTORITET: cijena se racuna u svakom
  calculate_totals passu; JS countdown je samo prikaz, na istek fragmenti
  vrate punu cijenu. Istek izmedju rendera i submita JEDNOM blokira checkout
  s obavijesti (drugi submit prolazi po redovnoj cijeni). Pretplatni
  proizvodi preskoceni. Order meta _rpsm_express_deal kad je kupljeno s
  popustom. Bez kupona - cisti price override (nema interakcija s coupon
  modulima ni Minimaxom).
- NOVO: "Samo karticno placanje" toggle (default ON): na express stranicama
  se nudi iskljucivo prvi gateway (Stripe) - virman/BACS skriveni; standardni
  checkout netaknut.


## 1.7.2.3 (2026-07-17) - Ikone kartica 20px + notice trake bez top bordera

- Razmak CTA gumb -> ikone kartica sada cilja .card_notice (pravi selektor
  payment display modula) s 20px.
- Login/kupon toggle trake: maknut WC-ov debeli copper top border i ikona,
  zaobljene na #f8f4ec s 14px tekstom - u tonu ostatka forme.


## 1.7.2.2 (2026-07-17) - HOTFIX: legal checkbox lom teksta

- Flex na checkbox labelu lomio je inline komade (tekst/link/abbr) u
  zasebne kutije pa se "web-stranice" raspadao u novi red s velikim
  razmakom. Zamijenjeno hanging-indentom: checkbox apsolutno lijevo,
  tekst tece prirodno u 12px/16px.


## 1.7.2.1 (2026-07-17) - Clobber garancija + mikro-tipografija

- CLOBBER FIX: ako je express proizvod vec bio u kosarici UZ druge stavke
  (naknadni add-to-cart), rani izlaz je preskakao ciscenje pa je checkout
  imao 2+ proizvoda. Sada clobber garantira: kosarica = tocno express
  proizvod. (Dinkov nalaz u dev testu.)
- Sitni print na 12px: Stripe gateway opis, privacy tekst, oba legal
  checkboxa (uz line-height 16px i flex poravnanje); +12px razmak izmedju
  CTA gumba i ikona kartica; +32px razmak narudzbe od R1 sekcije.


## 1.7.2.0 (2026-07-17) - Express: skin forme po portalskom checkoutu

- Express checkout skin prepisan s tokenima IZMJERENIMA na /placanje/
  stranici (ne mockup aproksimacija): bijela kartica sa soft sjenom,
  inputi bez rubova na #f9fafa radius 5px, naslovi 24px/600, notice trake
  bez ikone na #f8f4ec s copper linkovima, borderless order tablica s
  tankim separatorima, payment sekcija na cream pozadini, copper CTA
  radius 5px. Sve scopeano na .rpsm-express-checkout.


## 1.7.1.1 (2026-07-17) - Express: forma u punoj sirini

- WC col2-set (naplata/dostava+napomene) je na expressu ostavljao prazan
  desni polustupac (napomene skrivene, dostave nema) pa je forma bila
  stisnuta na pola. col-1/col-2 sada pune sirine unutar express wrappera.


## 1.7.1.0 (2026-07-17) - Express: bez napomena + bazni izgled forme

- Napomene uz narudzbu se vise ne prikazuju na express stranicama (toggle
  "Sakrij napomene uz narudzbu", default ON; standardni checkout netaknut).
- Bazni kartica-stil oko checkout forme (.rpsm-express-checkout): cream
  pozadina, uniformni rubovi, copper CTA - da forma izgleda pristojno i
  prije Elementor slaganja; sve scopeano na wrapper.


## 1.7.0.1 (2026-07-17) - HOTFIX: express kontekst u AJAX-u + wp_slash na meta JSON

- Express kontekst se gubio u wc-ajax fragmentima ('wp' detekcija se tamo ne
  izvrsava) pa je prvi update_order_review vracao gateway redoslijed (virman
  natrag na prvo mjesto) i upsell compact na standardni prikaz. Fix: express
  flag (product_id) u WC sesiji; is_express()/product_id() ga citaju u AJAX
  requestovima; flag se brise na PRAVOM checkoutu da postavke ne procure.
- Sadrzaj proizvoda: update_post_meta radi stripslashes pa su \r\n escape
  sekvence u JSON-u postajale literalno "rn" u za/nije natuknicama i
  textarea poljima. Fix: wp_slash oko JSON-a. NAPOMENA: postojece podatke
  unesene na 1.7.0.0 treba ponovno spremiti na proizvodu.


## 1.7.0.0 (2026-07-17) - Express stranica + Sadrzaj proizvoda

- NOVI MODUL Express (default OFF): stranice sa [rpsm_express product_id=X]
  shortcodeom postaju checkout. woocommerce_is_checkout filter pali sve
  postojece module (legal, kuponi, upsell, R1, atribucija...); auto-add s
  clobberom PRIJE WC empty-cart redirecta; vlasniku zasticenog proizvoda se
  umjesto forme prikazuje "vec posjedujes" kartica (v1.3.0.1 guard); gateway
  reorder (kartica prva) SAMO u express kontekstu; sticky mobilna CTA traka
  s totalom kroz WC fragmente; noindex + canonical na proizvod; X za
  uklanjanje stavki skriven (kosarica zakljucana). Helper
  rpsm_checkout_is_express() za druge pluginove (rpsm-upsell compact).
- NOVI MODUL Sadrzaj proizvoda (default ON, inertan bez podataka): meta box
  "Prodajna stranica" na proizvodu (chipovi, za/nije liste, moduli repeater,
  FAQ repeater, recenzije repeater tekst/ime/titula, video URL) + shortcodovi
  rpsm_product_stats/za_koga/moduli/faq/recenzije/video. FAQ spaja globalna
  pitanja (novi Sadrzaj tab u postavkama) sa specificnima; na stranici
  proizvoda emitira FAQPage schema.org. Prazna sekcija ne renderira nista.
- Admin: novi tabovi Express (postavke + auto-detekcija express stranica u
  post_content i _elementor_data) i Sadrzaj (toggle + globalna FAQ pitanja).
- Spec: SPEC-express-checkout.md (mockup artifact = izvor istine za layout).


## 1.6.0.0 (2026-07-17) - Atribucijski capture i NA portalu

- Kolacic rpsm_attr se sada pise i na portalu, ne samo na www. Dosad je portal
  kolacic samo CITAO, pa je promet koji slijece direktno na WC product page
  (oglasi za Prodajni ritam, biz ARENA product put, upsell mailovi na product
  page) prolazio kroz GA4 ali narudzba je ostajala "(nepoznato)".
- Ista pravila kao na www (port iz rpsm-web modula): UTM/click ID/referrer se
  cita odmah a kolacic pise tek po privoli (portalov Complianz, fail-closed);
  first touch se NIKAD ne prepisuje; referrer s *.radimposvom.com.hr se ignorira
  (www->portal prijelaz nije novi izvor). Bez CTA capture dijela (www-specifican).
- Nove opcije: "Hvataj izvor i NA portalu" (default ON) + consent kategorija
  portalovog bannera (default statistics). Novi row_select admin helper.

## 1.5.0.5 (2026-07-15) - Renewal type safety net + dijagnostika

- Renewal narudzbe su dobivale type=acquisition umjesto renewal: WCS data copier kopira svu _rpsm_attr_* meta s pretplate (ukljucujuci tip), a nas wcs_renewal_order_created callback se prema debug logu nije izvrsio (uzrok jos nejasan). Fix: shutdown safety net - za svaku novu narudzbu koja je wcs_order_contains_renewal() autoritativno se kopira atribucija s pretplate i force-a type=renewal, neovisno o filteru. Filter ostaje registriran + entry-log za dijagnozu.

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
