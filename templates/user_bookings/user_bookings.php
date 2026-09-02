<?php
/**
 * [bw_credits_user_bookings] — Buchungsliste
 *
 * Override: yourtheme/bw-credits-booking/user_bookings/user_bookings.php
 *
 * @var array  $items    leer = $empty_message wird gezeigt
 * @var string $empty_message
 *
 * Je Eintrag in $items:
 *   'slot_id', 'booking_id', 'status', 'is_active', 'slot_title',
 *   'permalink' (leer = kein Permalink), 'start_str', 'status_label',
 *   'meta_bits' (Kursart/Level/Sprache, gefiltert), 'can_cancel', 'show_access'
 *
 * @version 0.15.0
 */
if (!defined('ABSPATH')) exit;

if (empty($items)) : ?>
    <p class="bw-no-bookings"><?php echo esc_html($empty_message); ?></p>
<?php else : ?>
    <div class="bw-my-bookings">
        <?php foreach ($items as $item) :
            do_action('bw_before_bookings_item', $item['booking_id'], $item['slot_id']);
        ?>
            <div class="bw-booking-item bw-status-<?php echo esc_attr($item['status']); ?>">
                <div class="bw-booking-slot">
                    <?php if ($item['permalink'] !== '') : ?>
                        <a href="<?php echo esc_url($item['permalink']); ?>"><?php echo esc_html($item['slot_title']); ?></a>
                    <?php else : ?>
                        <?php echo esc_html($item['slot_title']); ?>
                    <?php endif; ?>

                    <?php if ($item['meta_bits']) : ?>
                        <span class="bw-booking-meta"><?php echo esc_html(implode(' · ', $item['meta_bits'])); ?></span>
                    <?php endif; ?>
                </div>

                <div class="bw-booking-time"><?php echo esc_html($item['start_str']); ?></div>
                <div class="bw-booking-status"><?php echo esc_html($item['status_label']); ?></div>

                <?php if ($item['can_cancel']) : ?>
                    <?php echo BW_Credits_Bookings_MVP::sc_slot_action(['course_id' => $item['slot_id']]); ?>
                <?php endif; ?>

                <?php if ($item['show_access'] && $item['is_active'] && $item['status'] === 'booked') : ?>
                    <?php echo BW_View_Access::render(['course_id' => $item['slot_id'], 'title' => '']); ?>
                <?php endif; ?>
            </div>
            <?php do_action('bw_after_bookings_item', $item['booking_id'], $item['slot_id']); ?>
        <?php endforeach; ?>
    </div>
<?php endif;
