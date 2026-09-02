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

## ACF-Abhängigkeit

Das Plugin braucht ACF nur noch für **ein** Feld am gewählten Inhaltstyp
(*BW Credits → Einstellungen → Kurstermin-Inhaltstyp*):

| Feldname | Typ | Beschreibung |
|---|---|---|
| `start_datetime` | Date Time Picker | Kursbeginn, Rückgabeformat `Y-m-d H:i:s` |

`capacity` und `booked_count` sind seit v0.8.0 plugin-eigene Metaboxen
(`includes/metaboxes.php`) — **keine** ACF-Felder mehr. Falls sie noch in
einer ACF-Feldgruppe liegen, dort entfernen, sonst erscheinen sie doppelt.

Taxonomien (`course_type`, `course_level`, `course_lang`) bleiben unabhängig
von ACF extern gepflegt.

## Shortcodes

Schema: `bw_credits_{gruppe}_{name}` — **course** spricht über einen Termin, **user** über den eingeloggten Kunden, **view** ist eine zusammengesetzte Ansicht.

Auf einer Termin-Einzelseite kann `course_id` entfallen — dann greift der aktuelle Beitrag. Damit lassen sich die Shortcodes einmal in ein Elementor-Template legen.

### Kurs

#### `[bw_credits_course_list]`
Terminliste, nach Tagen gruppiert, mit freien Plätzen und Buchen-Button.

```
[bw_credits_course_list]
[bw_credits_course_list days="14" show_filter="true"]
[bw_credits_course_list type="hatha-yoga" limit="5" availability="false"]
```

| Attribut | Standard | Bedeutung |
|---|---|---|
| `limit` | 20 | maximale Anzahl Termine |
| `days` | 0 | nur die nächsten N Tage (0 = ohne Begrenzung) |
| `type` / `level` / `lang` | – | Term-Slug zum Vorfiltern |
| `show_filter` | false | Auswahlfelder für Kursart, Level und Sprache |
| `show_action` | true | Buchen-Button je Termin |
| `availability` | true | freie Plätze je Termin |
| `group_by_day` | true | Überschrift je Tag |
| `empty` | *(Text)* | Meldung wenn keine Termine vorhanden sind |

Bei `show_filter="true"` schreibt das Formular `bw_type`, `bw_level` und `bw_lang` in die URL; gesetzte Attribute werden davon überschrieben.

#### `[bw_credits_course_booking]`
Ein Button, der je nach Zustand bucht oder storniert und nach dem Klick ohne Neuladen umschaltet. Zeigt stattdessen einen Hinweis bei: nicht eingeloggt, Termin vorbei, ausgebucht, keine Credits, Stornofrist abgelaufen.

`course_id`, `label_book`, `label_cancel`, `class`

#### `[bw_credits_course_availability]`
Freie Plätze — **auch ohne Login sichtbar**. Aktualisiert sich nach Buchung und Storno.

```
[bw_credits_course_availability format="Noch {frei} Plätze frei" full="Leider ausgebucht"]
```

`course_id`, `format`, `full`

#### `[bw_credits_course_access]`
Meeting-Link und Zugangsdaten. **Sichtbar ausschließlich für eingeloggte Nutzer mit aktiver Buchung für diesen Termin** — ohne Buchung wird nichts ausgegeben, auch kein Hinweis auf die Existenz des Links.

`course_id`, `title`

### Kunde

#### `[bw_credits_user_balance]`
Verfügbares Guthaben. `format="inline"` (Standard) gibt nur die Zahl aus, `format="block"` einen beschrifteten Absatz. Wird per JavaScript aktualisiert.

Mit **`mode="empty_only"`** wird daraus eine Aufforderung zum Nachkaufen: sichtbar nur, wenn der Kunde eingeloggt ist, **schon einmal Guthaben hatte** und jetzt keines mehr hat. Wer nie Credits hatte, sieht nichts — der soll über den Shop einsteigen.

„Schon einmal Guthaben gehabt" zählt jede Herkunft mit, auch manuelle Gutschriften aus Willkommensaktionen (Newsletter-Anmeldung, Aktionszeitraum).

```
[bw_credits_user_balance mode="empty_only"]
[bw_credits_user_balance mode="empty_only" empty_text="Keine Credits übrig." empty_link="Block kaufen"]
```

Der Hinweis erscheint sofort, sobald der Kunde seinen letzten Credit verbucht — ohne Neuladen.

| Attribut | Standard | Bedeutung |
|---|---|---|
| `mode` | always | `always` oder `empty_only` |
| `format` | inline | `inline` oder `block` (nur bei `mode="always"`) |
| `label` | Verfügbare Credits: | Beschriftung vor der Zahl |
| `empty_text` | Dein Guthaben ist aufgebraucht. | Text bei leerem Guthaben |
| `empty_link` | Jetzt aufladen | Beschriftung des Shop-Links |
| `shop_url` | – | überschreibt die Einstellung *Shop-Seite* |
| `logged_out` | – | Text für nicht eingeloggte Besucher |

Das Ziel des Links kommt aus *BW Credits → Einstellungen → Shop-Seite*; ist dort nichts hinterlegt, wird die WooCommerce-Shopseite verwendet. Findet sich keine, erscheint der Hinweis ohne Link.

#### `[bw_credits_user_credits]`
Guthaben im Detail: Anzahl, Herkunft (Kauf / Mitgliedschaft / Gutschrift) und Ablaufdatum, gebündelt statt einzeln. Was in den nächsten 30 Tagen verfällt, wird hervorgehoben.

`show_expired`, `empty`

#### `[bw_credits_user_bookings]`
Buchungen des Kunden mit Status, Kurstyp/Level/Sprache, Stornieren-Button und — sofern vorhanden — den Zugangsdaten.

`limit`, `show_access`

### Ansicht

#### `[bw_credits_view_overview]`
Guthaben, nächster gebuchter Termin (mit Zugangsdaten) und Einstiegslinks. Steht automatisch im WooCommerce-Konto-Dashboard.

`show_balance`, `show_next`, `show_links`, `list_url`

## Templates anpassen

Das komplette Markup jeder Ausgabe liegt in eigenständigen Dateien und lässt sich im Theme überschreiben — nach demselben Muster wie WooCommerce:

```
wp-content/plugins/bw-credits-booking/templates/
  course-list/    list.php, item.php, filter.php, empty.php
  bookings/       list.php, item.php, empty.php
  credits/        list.php, item.php, empty.php
  balance/        simple.php, states.php
  booking/        action.php, note.php
  overview/       wrapper.php, balance.php, next.php, links.php
  access/         box.php
  availability.php
```

**Überschreiben:** Datei nach `wp-content/themes/<dein-theme>/bw-credits-booking/<pfad>.php` kopieren und anpassen — z. B. `bw-credits-booking/bookings/item.php`. WordPress findet die Kopie automatisch — zuerst im Child-Theme, dann im Parent-Theme, sonst die Plugin-Version.

Die Templates enthalten **keinen Wortlaut** — jeder Text kommt über `bw_text()` aus dem [Text-Katalog](#texte-anpassen). Eine Theme-Kopie legt also nur das Layout fest, nie die Formulierung.

**Status behalten:** *BW Credits → Templates* listet alle 20 Templates, zeigt welche im Theme überschrieben sind, und markiert eine Kopie als veraltet, sobald ihr `@version`-Header hinter der Plugin-Version zurückliegt.

### Für kleine Eingriffe ohne Theme-Kopie

| Hook | Zweck |
|---|---|
| `bw_before_course_list` / `bw_after_course_list` *(Action)* | um die Terminliste herum |
| `bw_before_slot_item` / `bw_after_slot_item` *(Action, `$slot`)* | vor bzw. nach jeder Terminzeile |
| `bw_course_list_query_args` *(Filter)* | die `WP_Query`-Argumente der Terminliste anpassen — z. B. Sortierung ändern oder Termine ausschließen |
| `bw_before_bookings_item` / `bw_after_bookings_item` *(Action, `$booking_id`, `$slot_id`)* | vor bzw. nach jeder Zeile der Buchungsliste |
| `bw_before_credits_item` / `bw_after_credits_item` *(Action, `$group`)* | vor bzw. nach jeder Zeile der Guthaben-Details |

```php
// Bereits gebuchte Termine aus der Liste ausblenden
add_filter('bw_course_list_query_args', function ($args, $atts, $selected) {
    // eigene Logik
    return $args;
}, 10, 3);
```

## Texte anpassen

Alle 54 Texte, die Kunden im Frontend sehen, liegen in einem zentralen Katalog und lassen sich unter *BW Credits → Texte* ändern — ohne Code anzufassen. Dazu zählen auch die Fehlermeldungen, die beim Buchen und Stornieren erscheinen.

Ein leeres Feld nutzt den Standardtext. Platzhalter in geschweiften Klammern bleiben erhalten, etwa `{frei}` in „{frei} freie Plätze" oder `{datum}` in „gültig bis {datum}".

### Drei Ebenen

| Ebene | Womit |
|---|---|
| Einzelne Platzierung | Shortcode-Attribut, z. B. `label_book="Platz reservieren"` |
| Ganze Seite | *BW Credits → Texte* |
| Andere Sprache | WPML String Translation oder eine `.po`-Datei |

Die Auflösung läuft von oben nach unten: Ein gesetztes Shortcode-Attribut gewinnt, sonst greift der Admin-Text, sonst der übersetzte Standard.

### Übersetzung

Das Plugin nutzt die Textdomain `bw-credits-booking`. Die Vorlage liegt unter `languages/bw-credits-booking.pot` und wird aus dem Katalog erzeugt:

```
php tools/make-pot.php
```

Bei aktivem WPML erscheinen alle Texte zusätzlich unter *String Translation* im Kontext **BW Credits Texte**.

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
| Einstellungen | Inhaltstyp der Termine, Standard-Kapazität, Storno-Frist, Erinnerungs-Vorlauf, Shop-Seite |
| Termine | Alle Termine mit Belegung und Auslastung, Filter kommend/vergangen |
| Buchungen | Gefilterte Liste, Storno, Formular für Walk-in-Buchungen |
| Credits | Benutzersuche, Guthaben einsehen, manuell gutschreiben und entwerten |
| E-Mails | Betreff und Text aller Benachrichtigungen |

**Am Kurstermin** (Metaboxen):
- **Kapazität** — leer lassen nutzt den Standardwert; Belegung readonly daneben, Warnung bei Überbuchung
- **Online-Zugang** — Meeting-Link und Zugangsdaten, mit Knopf zum erneuten Senden
- **Teilnehmer** — Liste mit Stornieren, „Nicht erschienen" und CSV-Export der Anwesenheitsliste

**Listenansicht**: Spalten Start, Level, Type, Language — alle sortierbar.

**Auto-Titel**: Beim Speichern wird der Titel erzeugt als `"Montag, 2. Juni 10:00 – Hatha Yoga"`. Wochentag und Monat kommen aus der WordPress-Locale. Das Format lässt sich über den Filter `bw_slot_title_format` ändern:

```php
add_filter('bw_slot_title_format', fn() => 'D, j.n. H:i');
```

**Editor**: Kurstermine werden im Classic Editor bearbeitet, damit die Metaboxen an der gewohnten Stelle stehen.

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
