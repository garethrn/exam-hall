<?php
/**
 * Student sign-in.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$email = isset($flash['email']) ? $flash['email'] : '';
?>
<section class="eh-auth">
    <p class="eh-kicker"><?php esc_html_e('Student sign in', 'exam-hall'); ?></p>
    <h1><?php esc_html_e('Your desk is ready when you are.', 'exam-hall'); ?></h1>
    <p class="eh-lede"><?php esc_html_e('Use the email address and password from your exam administrator.', 'exam-hall'); ?></p>
    <form method="post" action="<?php echo esc_url(eh_portal_url()); ?>" class="eh-card">
        <?php wp_nonce_field('eh_portal'); ?>
        <input type="hidden" name="eh_action" value="login">
        <label class="eh-hp" aria-hidden="true">Website<input type="text" name="eh_website" tabindex="-1" autocomplete="off"></label>
        <label class="eh-field">
            <span><?php esc_html_e('Email', 'exam-hall'); ?></span>
            <input type="email" name="email" required autocomplete="username" value="<?php echo esc_attr($email); ?>">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('Password', 'exam-hall'); ?></span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="eh-check">
            <input type="checkbox" name="remember" value="1">
            <span><?php esc_html_e('Keep me signed in on this device', 'exam-hall'); ?></span>
        </label>
        <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Sign in', 'exam-hall'); ?></button>
        <p class="eh-form-foot"><a href="<?php echo esc_url(eh_portal_url(array('eh_action' => 'forgot'))); ?>"><?php esc_html_e('Forgot password?', 'exam-hall'); ?></a></p>
    </form>
</section>
