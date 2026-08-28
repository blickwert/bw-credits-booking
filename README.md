# BW Credits + Bookings

WordPress-Plugin für Yogastudios: WooCommerce-Kreditguthaben + Kurs-Buchungssystem auf Basis von ACF-verwalteten `course_slot`-Posts.

## Was das Plugin macht

Kunden kaufen Kreditpakete (z. B. 10er-Block) über WooCommerce. Jeder Credit ist eine eigene DB-Zeile. Beim Buchen eines Kursplatzes werden Credits FIFO (älteste zuerst) verbraucht. Stornierungen erstatten den Credit zurück.

## Voraussetzungen

| Abhängigkeit | Version |
|---|---|
| WordPress | ≥ 6.0 |
| PHP | ≥ 7.4 |
| WooCommerce | ≥ 7.0 |
| Advanced Custom Fields (ACF) | beliebig |
| Paid Memberships Pro *(optional)* | beliebig |

## Installation

1. ZIP herunterladen (GitHub → Releases → Assets → `Source code (zip)`)
2. WordPress Admin → Plugins → Installieren → ZIP hochladen
3. Plugin aktivieren — DB-Tabellen werden automatisch angelegt

**Auto-Update:** Das Plugin meldet sich direkt bei WordPress als Update-Quelle. Neue Releases erscheinen unter *Plugins → Updates* (Cache: 12 h).

## Datenbank

Das Plugin erstellt zwei Tabellen:

### `wp_bwallet_credits`

Jede Zeile = 1 Credit.

| Spalte | Typ | Beschreibung |
|---|---|---|
| `id` | BIGINT | Primary Key |
| `user_id` | BIGINT | WP User |
| `order_id` | BIGINT | WooCommerce Order |
| `order_item_id` | BIGINT | Order Line Item |
| `product_id` | BIGINT | WC-Produkt |
| `expires_at` | DATETIME | Ablaufdatum (NULL = unlimitiert) |
| `status` | VARCHAR(16) | `available` / `used` / `expired` |
| `source` | VARCHAR(20) | `purchase` / `membership` |
| `booking_id` | BIGINT | Verknüpfte Buchung (wenn `used`) |
| `created_at` | DATETIME | Erstellungszeitpunkt |

### `wp_bwallet_bookings`

| Spalte | Typ | Beschreibung |
|---|---|---|
| `id` | BIGINT | Primary Key |
| `user_id` | BIGINT | WP User |
| `course_slot_id` | BIGINT | Post-ID des `course_slot` |
| `status` | VARCHAR(16) | `booked` / `cancelled` |
| `created_at` | DATETIME | Buchungszeitpunkt |
| `cancelled_at` | DATETIME | Stornierungszeitpunkt |

## WooCommerce Produkt-Konfiguration

Jedes Kreditpaket ist ein einfaches WC-Produkt mit drei Zusatzfeldern (Tab *Allgemein*):

| Feld | Meta-Key | Beschreibung |
|---|---|---|
| Credit Amount | `_bw_credit_amount` | Anzahl Credits die gutgeschrieben werden |
| Valid Days | `_bw_credit_valid_days` | Gültigkeit ab Kauf (0 / leer = unlimitiert) |
| Credit Source | `_bw_credit_source` | `purchase` (Standard) oder `membership` |

Credits werden automatisch beim Status `completed` der Bestellung gutgeschrieben.

## ACF Felder (`course_slot`)

Der Custom Post Type `course_slot` benötigt folgende ACF-Felder:

| Feldname | Typ | Beschreibung |
|---|---|---|
| `start_datetime` | Date Time Picker | Kursbeginn (`Y-m-d H:i:s`) |
| `capacity` | Zahl | Maximale Teilnehmerzahl |
| `booked_count` | Zahl | Aktuell bestätigte Buchungen (Systemfeld, readonly) |
| `duration` | Zahl | Dauer in Minuten (optional) |

Taxonomien: `course_type`, `course_level`, `course_lang`

## Shortcodes

Auf der Termin-Einzelseite kann `slot_id` entfallen — dann greift automatisch der aktuelle Beitrag. Das macht die Shortcodes direkt in einem Elementor-Template verwendbar.

### `[bw_slot_action]`
**Empfohlen.** Ein Button, der je nach Zustand bucht oder storniert und nach dem Klick ohne Neuladen umschaltet. Zeigt stattdessen einen Hinweis wenn: nicht eingeloggt, Termin vorbei, ausgebucht, keine Credits vorhanden, oder Stornofrist abgelaufen.

```
[bw_slot_action]
[bw_slot_action slot_id="123" label_book="Jetzt buchen" label_cancel="Absagen"]
```

### `[bw_availability]`
Freie Plätze — **auch ohne Login sichtbar**, damit Besucher sich vor der Registrierung informieren können. Aktualisiert sich nach Buchung und Storno ohne Neuladen.

```
[bw_availability]
[bw_availability format="Noch {frei} Plätze frei" full="Leider ausgebucht"]
```

### `[bw_my_bookings]`
Liste aller Buchungen des eingeloggten Nutzers mit Status, Kurstyp/Level/Sprache und Stornieren-Button. Wird zusätzlich automatisch im WooCommerce-Konto-Dashboard angezeigt.

### `[bw_balance_inline]`
Gibt die aktuelle Credit-Anzahl als Zahl aus (für Inline-Verwendung im Text).

### `[bw_credits_balance]`
Zeigt das Credit-Guthaben als Block.

### `[bw_book_button slot_id="123"]` / `[bw_cancel_button booking_id="456"]`
Einzelne Buttons. Durch `[bw_slot_action]` weitgehend abgelöst, bleiben aber funktionsfähig.

## Buchungslogik

- **Race Conditions**: Buchung läuft in einer DB-Transaktion mit `SELECT … FOR UPDATE` auf der Kapazitätsprüfung
- **Vergangenheitssperre**: Buchungen für bereits begonnene/vergangene Slots werden abgelehnt
- **FIFO Credit-Verbrauch**: Credits mit früherem `expires_at` werden zuerst verbraucht
- **Stornofenster**: Konfigurierbar in Stunden vor Kursbeginn (`bw_booking_cancel_cutoff_hours`, Standard: 2)
- **Credit-Rückgabe**: Bei Stornierung wird der `used` Credit automatisch auf `available` zurückgesetzt

## REST API

Alle Endpunkte unter `/wp-json/bw-credits/v1/`:

| Methode | Pfad | Beschreibung |
|---|---|---|
| POST | `/book` | Slot buchen (`slot_id`) |
| POST | `/cancel` | Buchung stornieren (`booking_id`) |
| GET | `/balance` | Credit-Guthaben des eingeloggten Nutzers |

Alle Endpunkte erfordern `nonce`-Header (`X-WP-Nonce`).

## PMPro Membership Integration (optional)

Wenn Paid Memberships Pro aktiv ist:

- Membership-Produkte können `Credit Source: Membership` gesetzt bekommen
- Credits mit `source = membership` laufen automatisch ab wenn die Mitgliedschaft gekündigt wird (`pmpro_after_change_membership_level` → Level 0)
- `purchase`-Credits (Einzelkäufe, Blöcke) bleiben davon unberührt
- Rollover: bei monatlicher Verlängerung werden neue Credits gutgeschrieben, bestehende bleiben erhalten

**Ohne PMPro**: Kein Fehler — der Membership-Code ist vollständig in `function_exists()` gekapselt.

## Admin

Menü **BW Credits** (Berechtigung `manage_options`):

| Seite | Inhalt |
|---|---|
| Einstellungen | Inhaltstyp der Termine, Standard-Kapazität, Storno-Frist, Erinnerungs-Vorlauf |
| Termine | Alle Termine mit Belegung und Auslastung, Filter kommend/vergangen |
| Buchungen | Gefilterte Liste, Storno, Formular für Walk-in-Buchungen |
| Credits | Benutzersuche, Guthaben einsehen, manuell gutschreiben und entwerten |
| E-Mails | Betreff und Text aller Benachrichtigungen |

**Am Kurstermin** (Metaboxen):
- **Kapazität** — leer lassen nutzt den Standardwert; Belegung readonly daneben, Warnung bei Überbuchung
- **Online-Zugang** — Meeting-Link und Zugangsdaten, mit Knopf zum erneuten Senden
- **Teilnehmer** — Liste mit Stornieren, „Nicht erschienen" und CSV-Export der Anwesenheitsliste

**Listenansicht**: Spalten Start, Level, Type, Language — alle sortierbar. Der Titel wird beim Speichern automatisch erzeugt: `"23.2.26 17:00 – Hatha Yoga – German"`.

## E-Mails

Fünf Typen, jeweils mit eigenem Schalter, Betreff und Text unter *BW Credits → E-Mails*:

| Typ | Auslöser |
|---|---|
| Buchungsbestätigung | nach erfolgreicher Buchung |
| Stornobestätigung | nach Storno |
| Erinnerung | X Stunden vor Kursbeginn (stündlicher Cron) |
| Zugangsdaten | siehe unten |
| Admin-Kopie | jede neue Buchung (standardmäßig aus) |

Platzhalter: `{kundenname}` `{kurs_titel}` `{datum}` `{uhrzeit}` `{credits_verbleibend}` `{meeting_link}` `{zugangsdaten}`

### Zugangsdaten für Online-Kurse

Der Versand ist ereignisgesteuert:

1. Kursleiter trägt den Meeting-Link am Termin ein und speichert → alle bestehenden Teilnehmer erhalten die Zugangsdaten
2. Wer **danach** noch bucht, bekommt sie sofort mit der Buchungsbestätigung
3. `access_sent_at` pro Buchung verhindert Doppelversand
4. Knopf **Zugangsdaten erneut senden**, falls sich der Link nachträglich ändert

### WPML

Betreff und Text werden bei aktivem WPML String Translation im Kontext *BW Credits* registriert. Die Sprache des Termins bestimmt die Sprache der Mail.

## Auto-Update Workflow

```
1. Code committen + pushen
2. git tag v0.8.0 && git push origin v0.8.0
3. GitHub: Releases → "Draft a new release" → Tag wählen → Changelog → Publish
4. WordPress zeigt Update in "Plugins → Updates" (Cache max. 12 h)
```
