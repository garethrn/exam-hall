<?php
/**
 * Request a password reset email.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="eh-auth">
    <p class="eh-kicker"><?php esc_html_e('Password reset', 'exam-hall'); ?></p>
    <h1><?php esc_html_e('We will email you a reset link.', 'exam-hall'); ?></h1>
    <p class="eh-lede"><?php esc_html_e('Enter the email address on your student account.', 'exam-hall'); ?></p>
    <form method="post" action="<?php echo esc_url(eh_portal_url(array('eh_action' => 'forgot'))); ?>" class="eh-card">
        <?php wp_nonce_field('eh_portal'); ?>
        <input type="hidden" name="eh_action" value="forgot">
        <label class="eh-field">
            <span><?php esc_html_e('Email', 'exam-hall'); ?></span>
            <input type="email" name="email" required autocomplete="username">
        </label>
        <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Send reset link', 'exam-hall'); ?></button>
        <p class="eh-form-foot"><a href="<?php echo esc_url(eh_portal_url()); ?>"><?php esc_html_e('Back to sign in', 'exam-hall'); ?></a></p>
    </form>
</section>
