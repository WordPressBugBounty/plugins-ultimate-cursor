<?php
/**
 * Ultimate Cursor Analytics Notice Template
 *
 * @package UltimateCursor
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="notice notice-info is-dismissible uc-analytics-notice" data-nonce="<?php echo esc_attr($nonce); ?>">
    <div class="uc-notice-content">
		<h3><?php echo esc_html($notice_title); ?></h3>
        <p><?php echo esc_html($notice_content); ?></p>
        <p>
            <button class="button button-primary uc-enable-analytics" data-action="enable">
                <?php esc_html_e('Yes, I\'d like to help', $text_domain); ?>
            </button>
            <button class="button uc-disable-analytics" data-action="disable">
                <?php esc_html_e('No, thanks', $text_domain); ?>
            </button>
        </p>
    </div>
</div>

<style>
.uc-analytics-notice {
    padding: 15px;
    margin: 15px 0;
}
.uc-analytics-notice h3 {
   margin: 0;
}

.uc-notice-content {
    margin: 0;
}

.uc-notice-content p {
    margin: 10px 0;
}

button.button-primary.uc-enable-analytics,
button.uc-disable-analytics {
    margin-right: 3px !important;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.uc-analytics-notice').on('click', '.button', function(e) {
        e.preventDefault();

        const $notice = $(this).closest('.uc-analytics-notice');
        const action = $(this).data('action');
        const nonce = $notice.data('nonce');

        // First, handle the analytics action
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'uc_analytics_data',
                action_type: action,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Then dismiss the notice
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'uc_dismiss_notice',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                $notice.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            }
                        }
                    });
                }
            }
        });
    });
});
</script>
