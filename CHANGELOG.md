# Changelog

Alle relevanten Änderungen werden in dieser Datei dokumentiert.

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
