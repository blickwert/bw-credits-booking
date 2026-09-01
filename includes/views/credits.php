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

    private const SOURCE_LABELS = [
        'purchase'   => 'Kauf',
        'membership' => 'Mitgliedschaft',
        'manual'     => 'Gutschrift',
    ];

    public static function render($atts) {
        $atts = shortcode_atts([
            'show_expired' => 'false',
            'empty'        => 'Du hast aktuell kein Guthaben.',
        ], $atts, 'bw_credits_user_credits');

        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $credits = BW_Credits_Bookings_MVP::get_user_credits($user_id);
        $show_ex = filter_var($atts['show_expired'], FILTER_VALIDATE_BOOLEAN);

        $groups = self::group($credits, $show_ex);

        BW_Credits_Bookings_MVP::ensure_assets();

        if (empty($groups)) {
            return '<p class="bw-credits-empty">' . esc_html($atts['empty']) . '</p>';
        }

        $now  = current_datetime();
        $soon = $now->modify('+' . self::SOON_DAYS . ' days');

        ob_start();
        echo '<ul class="bw-credits-list">';

        foreach ($groups as $group) {
            $expires   = $group['expires_at'];
            $expires_t = $expires ? strtotime($expires) : 0;
            $is_soon   = $expires_t && $expires_t <= $soon->getTimestamp();
            $is_gone   = $group['status'] !== 'available';
            ?>
            <li class="bw-credits-item<?php echo $is_gone ? ' bw-credits-item--gone' : ''; ?>">
                <span class="bw-credits-amount"><?php echo (int) $group['count']; ?></span>

                <span class="bw-credits-source">
                    <?php echo esc_html(self::SOURCE_LABELS[$group['source']] ?? $group['source']); ?>
                </span>

                <span class="bw-credits-expiry<?php echo $is_soon && !$is_gone ? ' bw-credits-expiry--soon' : ''; ?>">
                    <?php
                    if ($is_gone) {
                        echo 'abgelaufen';
                    } elseif (!$expires_t) {
                        echo 'unbegrenzt gültig';
                    } else {
                        printf('gültig bis %s', esc_html(wp_date('d.m.Y', $expires_t)));
                    }
                    ?>
                </span>
            </li>
            <?php
        }

        echo '</ul>';
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
