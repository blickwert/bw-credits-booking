# Changelog

Alle relevanten Änderungen werden in dieser Datei dokumentiert.
New entries from v0.17.0 onward are written in English — see [0.17.0](#0170--2026-09-04).

---

## [0.21.0] – 2026-09-04

Phase 4 of the English-source migration, and the last one in this series: `README.md` is now fully English.

### Changed
- **`README.md`** is now entirely in English — installation, database schema, WooCommerce product configuration, ACF dependency, the full shortcode reference, template customization, text customization, booking logic, REST API, PMPro integration, admin, emails, and the auto-update workflow. All 15 sections translated, no content dropped or restructured.
- Two illustrative shortcode-attribute examples (`format="..."` on `[bw_credits_course_availability]`, `empty_text`/`empty_link` on `[bw_credits_user_balance]`) now show English example text, matching the rest of the document — these are just examples of custom text you can put there, not the plugin's own defaults.
- The auto-title example date (`"Monday, June 2, 10:00 – Hatha Yoga"`) now uses English weekday/month names, matching what an English-locale WordPress site actually produces — the German original showed a German-locale example (`"Montag, 2. Juni …"`).

### Note
Intentionally left as-is: the literal email placeholder tokens (`{kundenname}`, `{kurs_titel}`, `{datum}`, etc.) and the WPML String Translation context name `BW Credits Texte` — both are exact strings from the (deliberately untouched) email subsystem's actual code, not prose to translate.

`CHANGELOG.md` itself keeps its existing German entries as historical record, unchanged — only new entries are written in English, as established starting with 0.17.0.

This completes the four-phase English-source migration: source code, comments, and documentation are now English throughout, with German available as a standard WordPress translation shipped alongside every plugin update.

---

## [0.20.0] – 2026-09-04

Phase 3 of the English-source migration: all code comments across the plugin are now English, plus a few genuinely user-facing strings that earlier phases missed.

### Changed
- **All PHP/JS/CSS code comments** across the plugin — `bw-credits-booking.php`, every file under `includes/`, all eight `templates/*/*.php` files, and `assets/bwallet-frontend.js`/`.css` — are now written in English. Comments were previously out of scope for the English-source migration; the codebase is now consistently English apart from one deliberate exception (see below).
- **The Templates admin page's descriptions** (`includes/templates.php`'s template registry, shown under *BW Credits → Templates*) are now English by default, translated the same way as everything else — this mirrors the `GROUPS`/description fix from 0.18.0, since the value has to be wrapped at its source in `templates.php`, not at the display call site.
- **The Emails settings page** (`includes/emails.php`) — its email-type labels/descriptions and the settings-page chrome (headings, field labels) are now English by default, translated via the standard pipeline. This page was out of scope for phases 1–2 (a separate subsystem) but was clearly the same kind of admin-facing string, so it's fixed now rather than left inconsistent.
- **The GitHub-updater's "View Details" text** (`includes/updater.php`) — the plugin description and the changelog fallback message shown in WordPress's plugin-update modal are now translated.

### Fixed
- **Five genuine user-facing strings** that earlier phases missed, found during the comment sweep — all in `bw-credits-booking.php`: the `set_no_show()` error, three `WP_Error` messages in `grant_credits()`/`revoke_credit()`/`admin_book_slot()`, and the two `[bw_demo_book_slot]`/`[bw_demo_cancel_booking]` demo shortcodes' output text. These were plain hardcoded German strings with no translation wrapper at all; they're now `__()`-wrapped and translated like everything else.

### Note
**Deliberately left untouched**: the actual default email subject/body content in `BW_Emails::defaults()` (the customer-facing wording of the booking confirmation, cancellation, reminder, access-details, and admin-copy emails) is a separate, WPML-driven subsystem with its own placeholder syntax (`{kundenname}`, `{kurs_titel}`, etc.) — translating the business copy itself is a bigger content decision than a comment sweep, and is left for a future release if wanted.

Still to come: an English README (the CHANGELOG stays German for historical entries, English going forward, as already noted in 0.17.0).

---

## [0.19.0] – 2026-09-04

Phase 2b of the English-source migration: the admin screens and the frontend booking/cancel JS messages are now English source, with German covered by the same translation pipeline.

### Changed
- **~160 previously hardcoded German strings** across `includes/admin-pages.php`, `includes/metaboxes.php`, and `includes/settings.php` — page headings, table columns, form labels, button text, confirmation dialogs, admin notices, and the shortcode reference table — are now English source, wrapped with the appropriate WordPress i18n function (`esc_html__()`, `esc_attr__()`, `_e()`, or plain `__()` depending on context) and covered by the German `.po`/`.mo`.
- **`includes/admin.php`**: the remaining ACF/WooCommerce product-field descriptions and labels are now wrapped and translated.
- **CSV attendee export** (*BW Credits → [a session] → Export*) headers are translated the same way as the on-screen table they mirror.
- **Frontend booking/cancel JS messages** (`assets/bwallet-frontend.js`) are now sourced from PHP via a new `i18n` key on the existing `BW_BWALLET` script-localization object, instead of being hardcoded in the JS file — so they're translated exactly like everything else.
- `tools/scan-source-strings.php` (added in 0.18.0) now covers all five files above; `tools/make-de-po.php`'s validation continues to fail loudly on anything left untranslated.

### Note
Still to come in a later release: translating the remaining code comments to English, and an English README (the CHANGELOG stays German for historical entries, English going forward, as already noted in 0.17.0).

---

## [0.18.0] – 2026-09-04

Phase 2a of the English-source migration: build tooling to cover the remaining hardcoded strings in the admin screens, plus two fixes surfaced along the way.

### Added
- **`tools/scan-source-strings.php`**: a dependency-free PHP scanner (using `token_get_all()`, since neither `xgettext` nor `msgfmt` exist in the build environment) that finds literal-string calls to `__()`/`esc_html__()`/`esc_html_e()`/`esc_attr__()`/`esc_attr_e()`/`_e()` using the `bw-credits-booking` text domain across the admin-facing PHP files. Unlike the text catalogue's runtime-variable strings, these are genuine literals a scanner can see safely.
- **`tools/make-pot.php`** now merges three sources into one `.pot`: the text catalogue (unchanged), `BW_Text::GROUPS` headings (new), and the scanner's output (new) — deduplicated by text across all three.
- **`tools/make-de-po.php`**'s `MISSING:`/`EXTRA:` validation now checks the combined set from all three sources, so a newly-wrapped string can never silently ship without a German translation.

### Fixed
- **`includes/text.php`'s `GROUPS` headings and the Text-Katalog admin screen's per-entry descriptions are now translatable.** Phase 1 left these two spots unwrapped because the fix could only happen at their `admin-pages.php` render call sites, not in `text.php` itself (PHP forbids function calls in `const` initializers) — done now.
- **A pre-existing bug in `includes/admin.php`**: the `course_slot` admin list's column headers (`Title`, `Start`, `Level`, `Type`, `Language`) called `__()` with no text domain, so they silently fell back to WordPress core's own translation instead of this plugin's — never actually translatable by this plugin's `.mo`, even though the text was already English. Fixed by adding the domain argument.

### Note
This is phase 2a — the actual ~190 hardcoded strings across `admin-pages.php`, `metaboxes.php`, `settings.php` and the JS booking/cancel messages are still German-only, and are covered in the next release (2b), which builds on this tooling.

---

## [0.17.0] – 2026-09-04

Phase 1 of switching the plugin's source language from German to English: the text catalogue (`includes/text.php`, admin/frontend copy shown to users) now defaults to English, with German delivered as a standard WordPress translation.

### Changed
- **Text catalogue defaults are now English.** All 57 entries in `includes/text.php` (booking/cancellation labels, error messages, account overview, etc.) — previously German — are now English source text, resolved through the existing `__()`/gettext call that was already in place.
- **German is now a real WordPress translation**, not the hardcoded default: `languages/bw-credits-booking-de_DE.po`/`.mo` ship in this release with the original German wording for every catalogue text, loaded automatically via the plugin's existing `load_plugin_textdomain()` call on any site running with German as its WordPress language. No admin setup needed. Since this plugin isn't hosted on wordpress.org, there's no separate "update translations" channel — the `.mo` file simply ships with the plugin itself and updates whenever the plugin updates, same as every other file.
- New build tooling under `tools/`: `make-pot.php` (fixed — a pre-existing bug left multi-line text unescaped in the generated `.pot`), `make-de-po.php` (generates the German `.po` from a maintained translation-memory map, validated against the live catalogue), and `make-mo.php` (a dependency-free PHP `.po`→`.mo` compiler with a built-in self-check, since this environment has no `msgfmt`).

### Breaking change for saved text overrides
- **Placeholder names inside catalogue texts changed to English** to stay consistent with the flip: `{frei}`→`{free}`, `{datum}`→`{date}`, `{credits_hinzugefuegt}`→`{credits_added}`, `{credits_verbleibend}`→`{credits_remaining}`, `{konto_link}`→`{account_link}`. If you've customized a text under *BW Credits → Texte* that uses one of these placeholders, update it to the new placeholder name — otherwise the substitution silently stops working for that override. (This does **not** affect the separate email-template placeholders under *BW Credits → E-Mails*, e.g. `{datum}`/`{kurs_link}` there — those are a different, untouched system.)

### Note
This is phase 1 of a larger, multi-release effort. Still to come in later releases: the ~100+ remaining hardcoded strings in the admin screens and the booking/cancel buttons' JS messages, a full translation of code comments, and an English README.

---

## [0.16.1] – 2026-09-02

Zwei Fehler aus dem Produktions-Log beim Stornieren einer Buchung.

### Behoben
- **`Duplicate entry '...-0' for key 'uniq_active_user_slot'` beim Stornieren.** Die Spalte `is_active` wurde seit v0.8.0 (DB-Version 3) im Code als nullable definiert, damit stornierte Buchungen (`is_active=NULL`) nie mit dem Unique-Index kollidieren. `dbDelta()` stellt bestehende Spalten aber nicht zuverlässig von `NOT NULL` auf `NULL` um — auf Sites, die vor v0.8.0 installiert und seither nur aktualisiert wurden, blieb die Spalte `NOT NULL`, wodurch ein geschriebenes `NULL` von MySQL im nicht-strict Modus still zu `0` konvertiert wurde und die zweite Stornierung desselben Termins mit einer vorherigen kollidierte. Eine neue Migration (DB-Version 4) stellt die Spalte jetzt per explizitem `ALTER TABLE ... MODIFY is_active TINYINT(1) NULL DEFAULT 1` um (statt über `dbDelta()`) und räumt betroffene Zeilen erneut auf.
- **Fataler Fehler `Call to undefined method WP_Error::get_message()`** beim Buchen/Stornieren über die REST-API oder die Demo-Shortcodes. Der korrekte Methodenname ist `get_error_message()` — vier Stellen betroffen, die Fehlerantwort kommt jetzt wie vorgesehen als saubere `400`-Antwort statt eines Serverabsturzes zurück.

---

## [0.16.0] – 2026-09-02

Drei Punkte aus dem Praxistest des Buchungs-Workflows.

### Neu
- **Login-Link führt zu My Account statt `wp-login.php`.** „Bitte einloggen um zu buchen" auf der Terminliste verlinkt jetzt auf die WooCommerce-My-Account-Seite, mit Rücksprung zum ursprünglichen Termin nach dem Login (über einen neuen `woocommerce_login_redirect`-Filter, der das Ziel gegen die eigene Domain prüft). Ohne WooCommerce bleibt `wp_login_url()` als Rückfall.
- **`{kurs_link}` und `{konto_link}`** in Buchungsbestätigung, Stornierung und Erinnerung — Link zum Termin bzw. zur My-Account-Seite, wo Kunden ihre Buchungen selbst verwalten. Beide Mails existierten bereits seit v0.8.0; ihnen fehlte nur der Weg zurück ins Konto.
- **Guthaben-Hinweis in der WooCommerce-Bestell-Mail.** Nach einem Credit-Kauf zeigt die Woo-eigene „Bestellung abgeschlossen"-Mail jetzt einen Abschnitt mit der Anzahl neu gutgeschriebener Credits, dem aktuellen Gesamtguthaben und einem Link zu My Account — über `woocommerce_email_order_details`, ohne Eingriff in Woo-Mail-Templates. Kein zusätzlicher Mail-Typ, funktioniert für HTML- und Klartext-Mails gleichermaßen.

### Hinweis
`[bw_credits_user_balance]` zeigt weiterhin absichtlich nichts an, wenn kein Credit-Produkt im Warenkorb liegt (seit v0.15.0) — kein Bug.

---

## [0.15.0] – 2026-09-02

### Neu
- **Template-Konsolidierung: 20 Dateien → 8.** Jeder Shortcode hat jetzt genau eine Template-Datei, benannt nach dem Shortcode selbst (`course_booking/course_booking.php` statt vormals `booking/action.php` + `booking/note.php` in getrennten Ordnern). Wer einen Shortcode anpassen will, öffnet eine Datei statt mehrere zusammengehörige zu suchen. Alle Hooks (`bw_before_slot_item` usw.) bleiben erhalten, feuern jetzt innerhalb der jeweils einen Datei.
- **„In Theme kopieren"-Button** auf *BW Credits → Templates* — kopiert eine Vorlage direkt ins aktive Theme, keine manuelle Dateiarbeit mehr nötig. Erscheint nur bei Templates ohne bestehenden Override.
- **`[bw_credits_user_balance]` erscheint nur noch mit Credit-Paket im Warenkorb.** Aus dem allgegenwärtigen Guthaben-Zähler wird ein gezielter Hinweis während des Kaufs — z. B. auf der Warenkorb- oder Checkout-Seite. Gilt unabhängig vom `mode`-Attribut und betrifft ausschließlich diesen Shortcode; die Konto-Übersicht berechnet ihr Guthaben weiterhin unabhängig davon.
- **Kommende Kurse im WooCommerce-Dashboard.** `[bw_credits_view_overview]` zeigt statt des einen nächsten gebuchten Termins jetzt eine kurze Liste kommender Kurstermine — mit Verfügbarkeit und Buchen/Stornieren-Button, durch Wiederverwendung der Terminliste. Ein bereits gebuchter Termin darin zeigt automatisch „Stornieren" statt „Buchen". Neues Attribut `next_limit` (Standard 5).

### Geändert
- Text-Katalog: `overview.next.label`/`overview.next.none` durch `overview.upcoming.label` ersetzt — der Leerfall läuft jetzt über die ohnehin vorhandene Leermeldung der Terminliste.

---

## [0.14.0] – 2026-09-02

Acht Änderungen aus einer Sammel-Rückmeldung, größter Posten ist die
Ausweitung des Template-Systems auf alle übrigen Shortcode-Ausgaben —
bisher hatte nur die Terminliste eigene Templates.

### Neu
- **16 weitere Templates** für Buchen/Stornieren-Button, Hinweise, Verfügbarkeit, Guthaben (beide Modi), Buchungsliste, Guthaben-Details, Zugangsdaten und Konto-Übersicht — die Registry wächst von 4 auf 20 Einträge, alle unter *BW Credits → Templates* sichtbar.
- Zwei neue Hooks für Eingriffe ohne Theme-Kopie: `bw_before_bookings_item`/`bw_after_bookings_item` und `bw_before_credits_item`/`bw_after_credits_item`.
- **CSS-Root-Variablen für Textfarben** (`--bw-color-primary/secondary/text/accent/success/warning/error/info`), mit Fallback auf WordPress' Global-Styles-Presets (`--wp--preset--color--*`) und dahinter einen statischen Wert. Die vier zuvor uneinheitlichen Grauwerte (`#444`/`#555`/`#666`/`#777`) sind zu einem Sekundär-Ton zusammengeführt.
- **Verfügbarkeits-Schwelle** (Einstellung, Standard 5): ab dieser Zahl freier Plätze erscheint „mehr als N Plätze frei" statt der exakten Zahl. Der dritte Zustand (`data-bw-state="many"`) aktualisiert sich wie die anderen beiden live ohne Neuladen.

### Geändert
- **Auto-Titel enthält nur noch den Namen der Kursart** (z. B. „Hatha Yoga") statt zusätzlich Datum und Uhrzeit — die Startzeit wird in Terminliste und Buchungsliste bereits separat angezeigt. Ohne zugeordnete Kursart bleibt ein vorhandener Titel unverändert. `bw_slot_title_format` entfällt, ersetzt durch den Filter `bw_slot_title`.
- Katalogtext `booking.note.no_credits`: „Du hast keine Credits mehr." → „Du hast keine Credits." — passt jetzt auch für Kunden ohne bisherige Buchung.
- **My-Account-Übersicht**: CSS-Klassen von `bw-overview*` auf `bw-credits-overview*` umbenannt; neuer Link „Guthaben aufladen" neben „Meine Bestellungen".
- **README**: ACF-Abschnitt korrigiert — nur `start_datetime` ist noch ein ACF-Feld, `capacity`/`booked_count` sind seit v0.8.0 plugin-eigene Metaboxen, das nirgends gelesene `duration`-Feld ist raus.

### Entfernt
- **Shop-URL-Einstellung** vollständig entfernt, inklusive des Shortcode-Attributs `shop_url`. Alle Aufladen-Links nutzen jetzt ausschließlich `wc_get_page_permalink('shop')`.
- **Alte Shortcode-Namen** (`bw_course_slots`, `bw_slot_action`, `bw_availability`, `bw_my_bookings`, `bw_balance_inline`, `bw_credits_balance`, `bw_book_button`, `bw_cancel_button`) sind nicht mehr registriert — kein Alias-Mechanismus, keine Nutzungserfassung, kein Admin-Hinweis mehr. Seiten die noch alte Namen verwenden, zeigen ab dieser Version nichts mehr an.

---

## [0.13.0] – 2026-09-02

Trennt die letzte der drei Ebenen: **Struktur** (Markup) ist jetzt vom Code
getrennt, nach WooCommerce-Vorbild im Theme überschreibbar. Wortlaut (0.12.0)
und Sprache waren bereits getrennt — die Reihenfolge war mit Absicht:
die Templates entstehen dadurch ohne ein einziges deutsches Wort, eine
Theme-Kopie legt also nur das Layout fest, nie die Formulierung.

### Neu
- **Template-System** (`includes/templates.php`) — `bw_locate_template()` und `bw_get_template()` suchen Child-Theme → Parent-Theme → Plugin, exakt wie bei WooCommerce.
- **Vier Templates für die Terminliste** unter `templates/course-list/`: `list.php` (Rahmen, Tagesgruppierung), `item.php` (eine Terminzeile), `filter.php` (Filterformular), `empty.php` (Meldung ohne Treffer). Jedes mit `@version`-Header und dokumentierten `@var`-Variablen.
- **Seite BW Credits → Templates** — zeigt je Template ob eine Theme-Kopie existiert und markiert sie als veraltet, sobald ihr `@version` hinter der Plugin-Version zurückliegt. Ohne das fällt eine vergessene alte Kopie oft erst Monate später auf, wenn sie eine neue Funktion verschluckt.
- **Drei Hooks** für Eingriffe ohne Theme-Kopie: `bw_before_course_list` / `bw_after_course_list`, `bw_before_slot_item` / `bw_after_slot_item`, und der Filter `bw_course_list_query_args` für die zugrundeliegende `WP_Query`.

### Geändert
- `includes/course-list.php` enthält kein Markup mehr — nur noch Abfrage-Logik und `bw_get_template()`-Aufrufe.

### Nicht enthalten
Buchungsliste und Konto-Übersicht folgen als Templates in einer späteren Version, sobald sich das Muster an der Terminliste bewährt hat.

---

## [0.12.0] – 2026-09-02

Trennt drei Ebenen, die bisher im Code vermischt waren: **Struktur** (Markup),
**Wortlaut** (welche Worte) und **Sprache** (Übersetzung). Diese Version bringt
Wortlaut und Sprache; die Templates folgen in 0.13.0 — dann entstehen sie ohne
ein einziges deutsches Wort darin.

### Neu
- **Text-Katalog** (`includes/text.php`) mit 54 Einträgen. Jeder Text hat einen Schlüssel, einen Standard, eine Beschreibung und eine Gruppe. Ein neuer Text braucht einen einzigen Array-Eintrag und erscheint dadurch automatisch auf der Einstellungsseite, in der WPML-Registrierung und in der `.pot`.
- **Seite BW Credits → Texte** — alle Texte nach Gruppen sortiert, mit Standard als Platzhalter. Gespeichert wird nur, was tatsächlich abweicht: eine einzige Option statt 54 Datenbankeinträgen.
- **Übersetzbarkeit** — Textdomain `bw-credits-booking`, `load_plugin_textdomain()`, `languages/bw-credits-booking.pot`. Zusätzlich WPML-Registrierung im Kontext *BW Credits Texte*.
- **`tools/make-pot.php`** erzeugt die `.pot` aus dem Katalog. Nötig, weil die Standards zur Laufzeit als Variable an `__()` gehen — das übersetzt korrekt, ist für `xgettext` aber unsichtbar.

### Behoben
- **Fehlermeldungen erschienen beim Kunden auf Englisch.** Wer einen vollen Kurs buchen wollte, bekam „Slot is full.", wer kein Guthaben hatte „No available credits." — beides ging über die REST-Schnittstelle direkt in die Meldungszeile auf der Seite. Betraf 23 Meldungen.
- **Derselbe Fehlercode lieferte je nach Pfad unterschiedlichen Text** — `bw_booking_not_found`, `bw_not_active`, `bw_cancel_failed` und `bw_bookedcount_failed` existierten in deutscher und englischer Fassung nebeneinander. Jetzt eine Quelle je Code.

### Geändert
- Shortcode-Attribute für Beschriftungen sind standardmäßig leer und greifen auf den Katalog zurück. Gesetzte Attribute wirken unverändert.
- Nicht enthalten: Adminbereich und die beiden Demo-Shortcodes behalten ihre festen Texte.

---

## [0.11.0] – 2026-09-01

### Neu
- **`[bw_credits_user_balance mode="empty_only"]`** — die Guthaben-Anzeige wird zur Aufforderung zum Nachkaufen: sichtbar nur wenn der Kunde eingeloggt ist, schon einmal Guthaben hatte und jetzt keines mehr hat. Wer nie Credits hatte, sieht nichts.

  „Schon einmal Guthaben gehabt" zählt jede Herkunft mit — auch manuelle Gutschriften aus Willkommensaktionen wie Newsletter-Anmeldung oder Aktionszeitraum. Geprüft wird über `total` aus `get_credit_summary()`, ohne neue Datenhaltung.

  Der Hinweis erscheint **sofort** wenn der letzte Credit verbucht wird. Beide Zustände stehen im Markup und werden über `data-bw-state` umgeschaltet — dasselbe Muster wie bei der Verfügbarkeitsanzeige. Ohne das erschiene der Hinweis erst nach einem Neuladen, also gerade nicht in dem Moment in dem er zählt.

  Neue Attribute: `mode`, `empty_text`, `empty_link`, `shop_url`.

- **Einstellung Shop-Seite** (`bw_shop_url`) — wohin Kunden zum Aufladen geschickt werden. Leer lassen nutzt die WooCommerce-Shopseite.

### Geändert
- Der Hinweis „Du hast keine Credits mehr" bei `[bw_credits_course_booking]` verlinkt jetzt auf die Shop-Seite. Bisher stand dort eine Aufforderung ohne Ziel.

---

## [0.10.0] – 2026-09-01

Vereinheitlicht die Shortcode-Namen und schließt drei Lücken, die beim
Durchspielen des Kundenprozesses aufgefallen sind.

### Namensschema

Alle Frontend-Shortcodes folgen jetzt `bw_credits_{gruppe}_{name}` mit drei
Gruppen: **course** (spricht über einen Termin), **user** (über den
eingeloggten Kunden), **view** (zusammengesetzte Ansicht).

| Alt | Neu |
|---|---|
| `bw_course_slots` | `bw_credits_course_list` |
| `bw_slot_action` | `bw_credits_course_booking` |
| `bw_availability` | `bw_credits_course_availability` |
| `bw_my_bookings` | `bw_credits_user_bookings` |
| `bw_balance_inline` | `bw_credits_user_balance` |
| `bw_credits_balance` | `bw_credits_user_balance` mit `format="block"` |
| `bw_book_button` / `bw_cancel_button` | `bw_credits_course_booking` |

Die alten Namen funktionieren weiterhin. Der frühere Parameter `slot_id` wird
automatisch auf `course_id` übersetzt.

### Neu
- **`[bw_credits_course_access]`** — Meeting-Link und Zugangsdaten im Frontend, sichtbar ausschließlich für eingeloggte Nutzer mit aktiver Buchung für diesen Termin. Bisher erreichten die Zugangsdaten den Kunden nur per E-Mail.
- **`[bw_credits_user_credits]`** — Guthaben im Detail: Herkunft und Ablaufdatum, gebündelt statt einzeln. Was in den nächsten 30 Tagen verfällt, wird hervorgehoben. Bisher sah der Kunde nur eine Zahl.
- **`[bw_credits_view_overview]`** — Guthaben, nächster Termin samt Zugangsdaten und Einstiegslinks. Steht automatisch im WooCommerce-Konto-Dashboard.
- **Seite BW Credits → Shortcodes** — vollständige Referenz aller Shortcodes und eine Liste der Seiten, die noch alte Namen verwenden, mit Bearbeiten-Link.

### Geändert
- `bw_balance_inline` und `bw_credits_balance` sind zu `bw_credits_user_balance` mit `format="inline|block"` zusammengefasst — beide taten dasselbe in unterschiedlichem Markup.
- `[bw_credits_user_bookings]` zeigt bei gebuchten Terminen die Zugangsdaten mit an (`show_access="false"` schaltet das ab).
- Das Konto-Dashboard zeigt die Übersicht über der Buchungsliste.
- Die Shortcode-Registrierung liegt jetzt zentral in `includes/shortcodes.php`.

### Hinweis
`[bw_demo_book_slot]` und `[bw_demo_cancel_booking]` sind unverändert. Sie
führen die Buchung beim **Seitenaufruf** aus, nicht auf Klick — ein
eingeloggter Besucher verbraucht damit ungewollt einen Credit. Sie gehören
nicht auf öffentliche Seiten.

---

## [0.9.0] – 2026-08-28

Übernimmt Funktionen, die bisher als externe Snippets liefen.

### Neu
- **`[bw_course_slots]`** — Terminliste mit kommenden Terminen, gruppiert nach Tagen, mit freien Plätzen und Buchen-Button. Optionale Auswahlfelder für Kursart, Level und Sprache (`show_filter="true"`), Vorfilterung über Attribute, Begrenzung auf die nächsten N Tage.
- **Classic Editor** für Kurstermine — die Metaboxen stehen damit an der gewohnten Stelle statt in der unteren Leiste des Block-Editors

### Geändert
- **Auto-Titel** jetzt `"Montag, 2. Juni 10:00 – Hatha Yoga"` statt `"2.6.26 10:00 – Hatha Yoga – German"`. Wochentag und Monat kommen über `wp_date()` aus der WordPress-Locale; das Format ist über den Filter `bw_slot_title_format` anpassbar. Die Sprache steht weiterhin als eigene Spalte in der Listenansicht.
- Die Zeitberechnung im Auto-Titel nutzt die WordPress-Zeitzone statt der Serverzeit

### Hinweis zu externen Snippets

Mit dieser Version können folgende Snippets entfallen — sie sind im Plugin enthalten und kollidieren sonst:

| Snippet | Grund |
|---|---|
| `booked_count` readonly + Woo-Produktfelder | doppelt — die Produktfelder erscheinen sonst zweimal |
| Auto-Titel für `course_slot` | doppelt — beide schreiben `post_title`, das Ergebnis hängt von der Ladereihenfolge ab |
| `[bw_course_slot_output]` | war Platzhalter mit Beispieldaten, ersetzt durch `[bw_course_slots]` |
| `[bw_course_slots]` (Snippet-Fassung) | ersetzt durch die Plugin-Fassung |
| Gutenberg-Abschaltung | im Plugin, nutzt den eingestellten Inhaltstyp |

---

## [0.8.0] – 2026-08-28

### Neu — Adminbereich
- **Menü BW Credits** mit Einstellungen, Termine, Buchungen, Credits, E-Mails (Berechtigung `manage_options`)
- **Inhaltstyp der Termine frei wählbar** — das Plugin ist nicht mehr an einen von ACF registrierten `course_slot` gebunden
- **Metaboxen am Termin**: Kapazität (mit Fallback auf Standardwert und Überbuchungs-Warnung), Online-Zugang, Teilnehmerliste
- **Teilnehmerliste** mit Stornieren, „Nicht erschienen" und CSV-Export der Anwesenheitsliste
- **Credits-Verwaltung**: Guthaben einsehen, manuell gutschreiben (`source = manual`) und einzeln entwerten
- **Walk-in-Buchungen** durch den Admin, optional als Freiplatz ohne Credit-Abzug

### Neu — E-Mails
- Fünf Typen mit Schalter, Betreff und Text: Buchung, Storno, Erinnerung, Zugangsdaten, Admin-Kopie
- **Erinnerungs-Cron** stündlich, `reminded_at` verhindert Doppelversand
- **Zugangsdaten-Versand** ereignisgesteuert: beim ersten Eintragen des Meeting-Links an alle bestehenden Buchungen, bei späteren Buchungen sofort; `access_sent_at` verhindert Doppelversand
- WPML String Translation, Sprache des Termins bestimmt die Sprache der Mail

### Neu — Frontend
- **`[bw_slot_action]`** — ein Button der je nach Zustand bucht oder storniert und ohne Neuladen umschaltet
- **`[bw_availability]`** — freie Plätze, auch ohne Login sichtbar, aktualisiert sich nach Buchung und Storno
- Beide erkennen die Slot-ID automatisch aus dem aktuellen Beitrag
- `[bw_my_bookings]` zeigt zusätzlich Kurstyp, Level und Sprache und erscheint automatisch im WooCommerce-Konto-Dashboard

### Neu — WooCommerce
- Bestellung erstattet oder storniert → noch verfügbare Credits daraus werden entwertet, verbrauchte bleiben unangetastet

### Behoben
- **Zweites Storno pro Benutzer und Termin schlug fehl** — der Unique-Index `(user_id, slot_id, is_active)` kollidierte, weil beim Stornieren `is_active = 0` gesetzt wurde. Jetzt `NULL`, wovon MySQL beliebig viele zulässt.
- **Assets luden auf Page-Builder-Seiten nicht** — die Shortcode-Erkennung prüfte `post_content`, Elementor und Oxygen legen ihren Inhalt aber in Postmeta ab. Assets werden jetzt vom Shortcode selbst eingebunden.
- **`booked_count` wurde veraltet gecacht** — Raw-SQL-Schreibzugriffe invalidieren jetzt den Meta-Cache
- **Abgelaufener Nonce bei Full-Page-Caching** — das JS holt bei 401/403 einen frischen Nonce und wiederholt den Request einmal

### Datenbank
- Migration v3: `reminded_at` und `access_sent_at` in `bwallet_bookings`, `is_active` NULL-fähig
- `CREDIT_SOURCES` kennt zusätzlich `manual`

---

## [0.7.0] – 2026-05-28

### Neu
- **PMPro Membership Integration** (`includes/membership.php`): Optionale Unterstützung für Paid Memberships Pro. Credits mit `source = membership` laufen automatisch ab wenn die Mitgliedschaft gekündigt wird. Vollständig in `function_exists()` gekapselt — kein Fehler ohne PMPro.
- **Credit Source Feld** in WooCommerce-Produkten: Neues Dropdown-Feld `_bw_credit_source` (Purchase / Membership) im Tab Allgemein.
- **DB-Migration v2**: Neue Spalte `source VARCHAR(20) DEFAULT 'purchase'` in `wp_bwallet_credits`. Bestehende Credits erhalten automatisch den Default `purchase`.

### Geändert
- `handle_order_completed()` liest `_bw_credit_source` vom Produkt und übergibt den Wert an `add_credit_units()`
- `add_credit_units()` akzeptiert neuen `source`-Parameter (validiert: `purchase` | `membership`)
- Neue Konstante `PM_CREDIT_SOURCE`, `DB_VERSION = 2`

---

## [0.6.0] – 2026-05-28

### Neu
- **GitHub Auto-Updater** (`includes/updater.php`): WordPress zeigt neue Releases automatisch unter *Plugins → Updates* an (GitHub Releases API, Cache 12 h).
- Fix WooCommerce Helper Warning (`Undefined array key 1`): Updater trägt Plugin jetzt immer in `no_update[]` ein und liefert vollständige Update-Objekte mit allen Standard-WP-Feldern.

---

## [0.5.0] – 2026-05-28

### Neu
- **`[bw_my_bookings]` Shortcode**: Zeigt alle Buchungen des eingeloggten Nutzers mit Status-Badges (grün/rot) und Stornieren-Button.
- **Admin-Columns** für `course_slot`: Start, Level, Type, Language — alle sortierbar (Datum via `meta_key`, Taxonomien via SQL JOIN).
- **Auto-Titel** für `course_slot` beim Speichern via `acf/save_post`: Format `"23.2.26 17:00 – Hatha Yoga – German"`, Rekursionsschutz via `static $running`.
- **`booked_count` readonly** im ACF-Admin-Formular (via `acf/prepare_field`).
- WooCommerce Produktfelder Credit Amount + Valid Days ins Plugin integriert (aus Snippets).

### Admin
- Snippets aus der WordPress-Codebase in `includes/admin.php` konsolidiert

---

## [0.4.0] – 2026-05-27

### Neu
- **Vergangenheitssperre**: Buchungen für Slots deren `start_datetime` in der Vergangenheit liegt werden mit HTTP 400 abgelehnt.
- `get_slot_start_datetime()` erkennt beide ACF-Formate (`Y-m-d H:i:s` und `Y-m-d H:i`).

---

## [0.3.0] – 2026-05-27

### Initial-Release
- `wp_bwallet_credits` Tabelle: 1 Credit = 1 Zeile, FIFO-Verbrauch, Ablaufdatum, Status (`available` / `used` / `expired`)
- `wp_bwallet_bookings` Tabelle: Buchungen mit `booked` / `cancelled` Status
- WooCommerce Order Completed Hook → Credits gutschreiben
- REST Endpunkte: `/book`, `/cancel`, `/balance` (Nonce-gesichert)
- DB-Transaktion mit `SELECT … FOR UPDATE` für Race-Condition-Schutz bei Buchungen
- Shortcodes: `[bw_book_button]`, `[bw_cancel_button]`, `[bw_balance_inline]`, `[bw_credits_balance]`
- Frontend CSS + JS (`assets/bwallet-frontend.css`, `assets/bwallet-frontend.js`)
