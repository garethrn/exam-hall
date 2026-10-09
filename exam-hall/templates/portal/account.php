<?php
/**
 * Student account.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="eh-account">
    <header class="eh-page-head">
        <p class="eh-kicker"><?php esc_html_e('Account', 'exam-hall'); ?></p>
        <h1><?php echo esc_html($user->display_name); ?></h1>
        <p class="eh-lede"><?php esc_html_e('Your exam administrator keeps these details. Ask them if something needs to change.', 'exam-hall'); ?></p>
    </header>
    <dl class="eh-details eh-card">
        <div><dt><?php esc_html_e('Email', 'exam-hall'); ?></dt><dd><?php echo esc_html($user->user_email); ?></dd></div>
        <?php if ($student_number !== '') : ?>
            <div><dt><?php esc_html_e('Student number', 'exam-hall'); ?></dt><dd><?php echo esc_html($student_number); ?></dd></div>
        <?php endif; ?>
        <?php if ($programme !== '') : ?>
            <div><dt><?php esc_html_e('Class / programme', 'exam-hall'); ?></dt><dd><?php echo esc_html($programme); ?></dd></div>
        <?php endif; ?>
        <?php if ($phone !== '') : ?>
            <div><dt><?php esc_html_e('Phone', 'exam-hall'); ?></dt><dd><?php echo esc_html($phone); ?></dd></div>
        <?php endif; ?>
    </dl>
    <form method="post" action="<?php echo esc_url(eh_portal_url(array('eh_view' => 'account'))); ?>" class="eh-card">
        <h2><?php esc_html_e('Change password', 'exam-hall'); ?></h2>
        <?php wp_nonce_field('eh_portal'); ?>
        <input type="hidden" name="eh_action" value="change_password">
        <label class="eh-field">
            <span><?php esc_html_e('Current password', 'exam-hall'); ?></span>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('New password', 'exam-hall'); ?></span>
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('Confirm new password', 'exam-hall'); ?></span>
            <input type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </label>
        <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Update password', 'exam-hall'); ?></button>
    </form>
</section>
