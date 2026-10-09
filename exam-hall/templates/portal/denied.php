<?php
/**
 * Signed-in user who is not a student.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="eh-auth">
    <h1><?php esc_html_e('This portal is for students.', 'exam-hall'); ?></h1>
    <p class="eh-lede"><?php esc_html_e('Sign in with the student email address from your exam administrator.', 'exam-hall'); ?></p>
    <p><a class="eh-btn" href="<?php echo esc_url(wp_logout_url(eh_portal_url())); ?>"><?php esc_html_e('Sign out', 'exam-hall'); ?></a></p>
</section>
