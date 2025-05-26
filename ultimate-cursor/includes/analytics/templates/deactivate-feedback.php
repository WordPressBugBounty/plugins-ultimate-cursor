<?php
/**
 * Ultimate Cursor Deactivate Feedback Template
 *
 * @package UltimateCursor
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Access the nonce from the parent class that includes this template
// This will avoid the need to call wp_create_nonce directly
?>
<div id="uc-deactivate-feedback-overlay" class="uc-deactivate-feedback-overlay"></div>
<div id="uc-deactivate-feedback" class="uc-deactivate-feedback">
    <div class="uc-deactivate-feedback-content">
        <h3><?php esc_html_e('Quick Feedback', $text_domain); ?></h3>
        <p><?php esc_html_e('If you have a moment, please let us know why you are deactivating:', $text_domain); ?></p>

        <form id="uc-deactivate-feedback-form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

            <div class="uc-feedback-reasons">
                <label>
                    <input type="radio" name="reason" value="no_longer_needed">
                    <?php esc_html_e('I no longer need the plugin', $text_domain); ?>
                </label>

                <label>
                    <input type="radio" name="reason" value="found_better">
                    <?php esc_html_e('I found a better plugin', $text_domain); ?>
                </label>

                <label>
                    <input type="radio" name="reason" value="temporary_deactivation">
                    <?php esc_html_e('This is temporary, I will be back', $text_domain); ?>
                </label>

                <label>
                    <input type="radio" name="reason" value="other">
                    <?php esc_html_e('Other', $text_domain); ?>
                </label>
            </div>

            <div class="uc-feedback-details" style="display: none;">
                <textarea name="feedback" placeholder="<?php esc_attr_e('Please share your feedback...', $text_domain); ?>"></textarea>
            </div>

            <div class="uc-feedback-buttons">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Submit & Deactivate', $text_domain); ?>
                </button>
                <button type="button" class="button uc-skip-feedback">
                    <?php esc_html_e('Skip & Deactivate', $text_domain); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.uc-deactivate-feedback-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 999998;
    display: none;
}

.uc-deactivate-feedback {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    z-index: 999999;
    max-width: 500px;
    width: 90%;
    display: none;
    animation: uc-fade-in 0.3s ease-out;
}

@keyframes uc-fade-in {
    from { opacity: 0; transform: translate(-50%, -55%); }
    to { opacity: 1; transform: translate(-50%, -50%); }
}

.uc-deactivate-feedback-content h3 {
    margin-top: 0;
    color: #23282d;
    font-size: 1.4em;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
    margin-bottom: 15px;
}

.uc-feedback-reasons {
    margin: 15px 0;
}

.uc-feedback-reasons label {
    display: flex;
    align-items: center;
    margin: 12px 0;
    padding: 10px;
    border-radius: 4px;
    transition: background 0.2s;
}

.uc-feedback-reasons label:hover {
    background: #f7f7f7;
}

.uc-feedback-reasons input[type="radio"] {
    margin-right: 10px;
}

.uc-feedback-details textarea {
    width: 100%;
    min-height: 120px;
    margin: 15px 0;
    border-radius: 4px;
    padding: 10px;
    border: 1px solid #ddd;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.07);
    transition: border-color 0.2s;
}

.uc-feedback-details textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.uc-feedback-buttons {
    margin-top: 20px;
    text-align: right;
    display: flex;
    justify-content: flex-end;
}

.uc-feedback-buttons .button {
    margin-left: 10px;
    padding: 8px 16px;
    height: auto;
    font-size: 14px;
    transition: all 0.2s;
}

.uc-feedback-buttons .button-primary {
    background: #2271b1;
    border-color: #2271b1;
}

.uc-feedback-buttons .button-primary:hover {
    background: #135e96;
    border-color: #135e96;
}

.uc-skip-feedback {
    color: #2271b1;
    border-color: #2271b1;
    background: transparent;
}

.uc-skip-feedback:hover {
    background: #f6f7f7;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Show feedback form and overlay when deactivate link is clicked
    $('tr[data-plugin="ultimate-cursor/ultimate-cursor.php"] .deactivate a').on('click', function(e) {
        e.preventDefault();
        $('#uc-deactivate-feedback-overlay').show();
        $('#uc-deactivate-feedback').show();
    });

    // Close modal when clicking on overlay
    $('#uc-deactivate-feedback-overlay').on('click', function(e) {
        if ($(e.target).is('#uc-deactivate-feedback-overlay')) {
            $('#uc-deactivate-feedback-overlay').hide();
            $('#uc-deactivate-feedback').hide();
        }
    });

    // Close on ESC key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#uc-deactivate-feedback').is(':visible')) {
            $('#uc-deactivate-feedback-overlay').hide();
            $('#uc-deactivate-feedback').hide();
        }
    });

    $('#uc-deactivate-feedback-form').on('change', 'input[name="reason"]', function() {
        if ($(this).val() === 'other') {
            $('.uc-feedback-details').fadeIn(200);
        } else {
            $('.uc-feedback-details').fadeOut(200);
        }
    });

    $('#uc-deactivate-feedback-form').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'uc_deactivate_feedback');

        // Disable buttons during submission
        $('.uc-feedback-buttons button').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    window.location.href = $('tr[data-plugin="ultimate-cursor/ultimate-cursor.php"] .deactivate a').attr('href');
                }
            }
        });
    });

    $('.uc-skip-feedback').on('click', function() {
        window.location.href = $('tr[data-plugin="ultimate-cursor/ultimate-cursor.php"] .deactivate a').attr('href');
    });
});
</script>
