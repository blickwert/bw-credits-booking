<?php
if (!defined('ABSPATH')) exit;

/**
 * [bw_credits_user_credits] — Guthaben im Detail.
 *
 * Zeigt was der Kunde hat, woher es stammt und wann es verfällt. Die Zahl
 * allein ("10 Credits") sagt nichts darüber, dass davon fünf zum Monatsende
 * ablaufen — genau das führt sonst zu Rückfragen.
 */

class BW_View_Credits {

    /** Ab wann ein Ablaufdatum hervorgehoben wird */
    const SOON_DAYS = 30;

    private static function source_labels(): array {
        return [
            'purchase'   => bw_text('credits.source.purchase'),
            'membership' => bw_text('credits.source.membership'),
            'manual'     => bw_text('credits.source.manual'),
        ];
    }

    public static function render($atts) {
        $atts = shortcode_atts([
            'show_expired' => 'false',
            'empty'        => '',   // leer = Text aus dem Katalog
        ], $atts, 'bw_credits_user_credits');

        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $credits = BW_Credits_Bookings_MVP::get_user_credits($user_id);
        $show_ex = filter_var($atts['show_expired'], FILTER_VALIDATE_BOOLEAN);

        $groups = self::group($credits, $show_ex);

        BW_Credits_Bookings_MVP::ensure_assets();

        $empty_message = $atts['empty'] !== '' ? $atts['empty'] : bw_text('credits.empty');

        if (empty($groups)) {
            ob_start();
            bw_get_template('user_credits/user_credits.php', [
                'items'         => [],
                'empty_message' => $empty_message,
            ]);
            return ob_get_clean();
        }

        $now  = current_datetime();
        $soon = $now->modify('+' . self::SOON_DAYS . ' days');

        $items = [];
        foreach ($groups as $group) {
            $expires   = $group['expires_at'];
            $expires_t = $expires ? strtotime($expires) : 0;
            $is_soon   = $expires_t && $expires_t <= $soon->getTimestamp();
            $is_gone   = $group['status'] !== 'available';

            if ($is_gone) {
                $expiry_text = bw_text('credits.expired');
            } elseif (!$expires_t) {
                $expiry_text = bw_text('credits.unlimited');
            } else {
                $expiry_text = bw_text('credits.valid_until', ['datum' => wp_date('d.m.Y', $expires_t)]);
            }

            $items[] = [
                'group'        => $group,
                'is_soon'      => $is_soon,
                'is_gone'      => $is_gone,
                'source_label' => self::source_labels()[$group['source']] ?? $group['source'],
                'expiry_text'  => $expiry_text,
            ];
        }

        ob_start();
        bw_get_template('user_credits/user_credits.php', [
            'items'         => $items,
            'empty_message' => '',
        ]);
        return ob_get_clean();
    }

    /**
     * Credits nach Herkunft und Ablaufdatum bündeln — zehn einzelne Zeilen
     * für einen 10er-Block wären für den Kunden nur Rauschen.
     */
    private static function group(array $credits, bool $show_expired): array {
        $now    = time();
        $groups = [];

        foreach ($credits as $credit) {
            if ($credit['status'] === 'used') continue;

            $expires_t = $credit['expires_at'] ? strtotime($credit['expires_at']) : 0;
            $lapsed    = $expires_t && $expires_t <= $now;
            $status    = ($credit['status'] === 'expired' || $lapsed) ? 'expired' : 'available';

            if ($status === 'expired' && !$show_expired) continue;

            $key = $status . '|' . $credit['source'] . '|' . (string) $credit['expires_at'];

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'count'      => 0,
                    'source'     => $credit['source'],
                    'expires_at' => $credit['expires_at'],
                    'status'     => $status,
                ];
            }

            $groups[$key]['count']++;
        }

        // Bald ablaufende zuerst, unbegrenzte zuletzt
        uasort($groups, static function ($a, $b) {
            if ($a['status'] !== $b['status']) {
                return $a['status'] === 'available' ? -1 : 1;
            }
            $ta = $a['expires_at'] ? strtotime($a['expires_at']) : PHP_INT_MAX;
            $tb = $b['expires_at'] ? strtotime($b['expires_at']) : PHP_INT_MAX;
            return $ta <=> $tb;
        });

        return $groups;
    }
}
