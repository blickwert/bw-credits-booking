# Changelog

Alle relevanten Änderungen werden in dieser Datei dokumentiert.

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
