<?php
/**
 * Choose a new password from an emailed link.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="eh-auth">
    <p class="eh-kicker"><?php esc_html_e('Password reset', 'exam-hall'); ?></p>
    <?php if (empty($valid)) : ?>
        <h1><?php esc_html_e('This reset link is no longer valid.', 'exam-hall'); ?></h1>
        <p class="eh-lede"><?php esc_html_e('Request a new link and use it within 24 hours.', 'exam-hall'); ?></p>
        <p><a class="eh-btn eh-btn-primary" href="<?php echo esc_url(eh_portal_url(array('eh_action' => 'forgot'))); ?>"><?php esc_html_e('Request a new link', 'exam-hall'); ?></a></p>
    <?php else : ?>
        <h1><?php esc_html_e('Choose a new password.', 'exam-hall'); ?></h1>
        <form method="post" action="<?php echo esc_url(eh_portal_url(array('eh_action' => 'reset'))); ?>" class="eh-card">
            <?php wp_nonce_field('eh_portal'); ?>
            <input type="hidden" name="eh_action" value="reset">
            <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
            <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
            <label class="eh-field">
                <span><?php esc_html_e('New password', 'exam-hall'); ?></span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label class="eh-field">
                <span><?php esc_html_e('Confirm password', 'exam-hall'); ?></span>
                <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </label>
            <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Update password', 'exam-hall'); ?></button>
        </form>
    <?php endif; ?>
</section>
