# Changelog

Alle relevanten Änderungen werden in dieser Datei dokumentiert.

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
