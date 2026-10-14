<?php
/**
 * Regression test: BW_Calendar_File — the .ics attached to booking and access mails. Times in UTC, RFC 5545
 * escaping and line folding (75 octets, never inside a UTF-8 character), CRLF line ends.
 */
define('ABSPATH', true);
require __DIR__ . '/../includes/calendar-file.php';

$pass = 0; $fail = 0;
function check($label, $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; printf("PASS  %s\n", $label); } else { $fail++; printf("FAIL  %s\n", $label); }
}

$start = new DateTime('2026-10-25 08:30:00', new DateTimeZone('Europe/Madrid'));
$ics = BW_Calendar_File::build([
    'uid' => 'bw-42-1@example.test', 'summary' => 'Hatha Yoga, Ground & Connect; beginners',
    'start' => $start, 'end' => (clone $start)->modify('+50 minutes'),
    'description' => "08:30 (Madrid time)\n\nZoom link: https://zoom.test/j/1", 'location' => 'https://zoom.test/j/1',
    'url' => 'https://zoom.test/j/1', 'alarm_minutes' => 60,
]);

check('begins and ends as a calendar with one event', str_starts_with($ics, "BEGIN:VCALENDAR\r\n") && str_ends_with($ics, "END:VCALENDAR\r\n") && substr_count($ics, 'BEGIN:VEVENT') === 1);
check('start and end are written in UTC (Madrid winter time = UTC+1)', str_contains($ics, "DTSTART:20261025T073000Z\r\n") && str_contains($ics, "DTEND:20261025T082000Z\r\n"));
check('summary: comma and semicolon are escaped', str_contains($ics, 'SUMMARY:Hatha Yoga\\, Ground & Connect\; beginners'));
check('description: line breaks become \\n', str_contains($ics, 'DESCRIPTION:08:30 (Madrid time)\\n\\nZoom link: https://zoom.test/j/1'));
check('location and url are present', str_contains($ics, "LOCATION:https://zoom.test/j/1\r\n") && str_contains($ics, "URL:https://zoom.test/j/1\r\n"));
check('reminder 60 minutes before', str_contains($ics, "BEGIN:VALARM\r\n") && str_contains($ics, "TRIGGER:-PT60M\r\n"));
check('every line ends with CRLF', !preg_match('/(?<!\r)\n/', $ics));

$long = BW_Calendar_File::build(['uid' => 'x', 'summary' => str_repeat('äöü', 60), 'start' => $start]);
$ok = true; $unfolded = '';
foreach (explode("\r\n", rtrim($long, "\r\n")) as $line) { if (strlen($line) > 75) $ok = false; $unfolded .= ($line !== '' && $line[0] === ' ') ? substr($line, 1) : "\n" . $line; }
check('long lines are folded to at most 75 octets', $ok);
check('folding never splits a UTF-8 character', str_contains($unfolded, 'SUMMARY:' . str_repeat('äöü', 60)));

check('no description / location / alarm: those lines are left out', (function () use ($start) {
    $e = BW_Calendar_File::build(['uid' => 'x', 'summary' => 'S', 'start' => $start]);
    return !str_contains($e, 'DESCRIPTION:') && !str_contains($e, 'LOCATION:') && !str_contains($e, 'VALARM');
})());

printf("\n%d/%d checks passed\n", $pass, $pass + $fail);
exit($fail ? 1 : 0);
