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
<div class="notice notice-info is-dismissible wpxero-analytics-notice" data-nonce="<?php echo esc_attr($nonce); ?>">
    <div class="wpxero-notice-content">
        <h3><?php echo esc_html($notice_title); ?></h3>
        <p><?php echo esc_html($notice_content); ?></p>
        <p>
            <button class="button button-primary wpxero-enable-analytics" data-action="enable">
                <?php esc_html_e('Yes, I\'d like to help', $text_domain); ?>
            </button>
            <button class="button wpxero-disable-analytics" data-action="disable">
                <?php esc_html_e('No, thanks', $text_domain); ?>
            </button>
        </p>
    </div>
</div>

<style>
    .wpxero-analytics-notice {
        padding: 15px;
        margin: 15px 0;
    }

    .wpxero-analytics-notice h3 {
        margin: 0;
    }

    .wpxero-notice-content {
        margin: 0;
    }

    .wpxero-notice-content p {
        margin: 10px 0;
    }

    button.button-primary.wpxero-enable-analytics,
    button.wpxero-disable-analytics {
        margin-right: 3px !important;
    }
</style>

<script>
    jQuery(document).ready(function($) {
        $('.wpxero-analytics-notice').on('click', '.button', function(e) {
            e.preventDefault();

            const $notice = $(this).closest('.wpxero-analytics-notice');
            const action = $(this).data('action');
            const nonce = $notice.data('nonce');

            // First, handle the analytics action
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpxero_analytics_data',
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
                                action: 'wpxero_dismiss_notice',
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