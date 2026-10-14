<?php
if (!defined('ABSPATH')) exit;

/**
 * iCalendar (.ics) file for a session: times in UTC, so every calendar app shows the session in the
 * customer's own time zone — the answer to "what time is that for me?" in emails, where there is no browser.
 */
class BW_Calendar_File {

    /**
     * @param array $e uid, summary, start (DateTime), end (DateTime), description, url, location, alarm_minutes
     */
    public static function build(array $e): string {
        $utc   = new DateTimeZone('UTC');
        $start = (clone $e['start'])->setTimezone($utc);
        $end   = (clone ($e['end'] ?? $e['start']))->setTimezone($utc);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BW Credits + Bookings//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . self::text_value((string) $e['uid']),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . self::escape((string) $e['summary']),
        ];
        if (!empty($e['description'])) $lines[] = 'DESCRIPTION:' . self::escape((string) $e['description']);
        if (!empty($e['location']))    $lines[] = 'LOCATION:' . self::escape((string) $e['location']);
        if (!empty($e['url']))         $lines[] = 'URL:' . self::text_value((string) $e['url']);

        $alarm = (int) ($e['alarm_minutes'] ?? 0);
        if ($alarm > 0) {
            array_push($lines, 'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' . self::escape((string) $e['summary']), 'TRIGGER:-PT' . $alarm . 'M', 'END:VALARM');
        }
        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", array_map([__CLASS__, 'fold'], $lines)) . "\r\n";
    }

    /** RFC 5545 text escaping. */
    public static function escape(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $text);
    }

    private static function text_value(string $text): string {
        return str_replace(["\r", "\n"], '', $text);
    }

    /** Lines are at most 75 octets; the rest continues on lines that start with a space. */
    public static function fold(string $line): string {
        $parts = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $cut = $limit;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--; // do not split a UTF-8 character
            $parts[] = substr($line, 0, $cut);
            $line    = substr($line, $cut);
            $limit   = 74; // continuation lines carry a leading space
        }
        $parts[] = $line;
        return implode("\r\n ", $parts);
    }
}
