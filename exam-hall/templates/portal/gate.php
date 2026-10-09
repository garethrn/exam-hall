<?php
/**
 * Start-code gate.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$user = wp_get_current_user();
?>
<section class="eh-gate">
    <p class="eh-kicker"><?php esc_html_e('Before you begin', 'exam-hall'); ?></p>
    <h1><?php echo esc_html($exam->title); ?></h1>
    <?php if ($exam->instructions) : ?>
        <div class="eh-instructions"><?php echo nl2br(esc_html($exam->instructions)); ?></div>
    <?php endif; ?>
    <ul class="eh-meta eh-meta-large">
        <li><?php echo esc_html(eh_duration_label($exam->duration_minutes)); ?></li>
        <li><?php echo esc_html(sprintf(/* translators: %d: question count */ _n('%d question', '%d questions', $questions, 'exam-hall'), $questions)); ?></li>
        <li><?php echo esc_html(sprintf(/* translators: %d: pass percentage */ __('Pass mark %d%%', 'exam-hall'), (int) $exam->band_pass)); ?></li>
    </ul>
    <div class="eh-card">
        <h2><?php esc_html_e('Enter the start code', 'exam-hall'); ?></h2>
        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: student email */
                    __('We send the code to %s. The timer starts when the code is accepted.', 'exam-hall'),
                    $user->user_email
                )
            );
            ?>
        </p>
        <?php if ($expired) : ?>
            <p class="eh-banner eh-banner-error"><?php esc_html_e('The last code has expired. Request a new one.', 'exam-hall'); ?></p>
        <?php elseif ($locked) : ?>
            <p class="eh-banner eh-banner-error"><?php echo esc_html(sprintf(/* translators: %s: unlock time */ __('Code entry is locked until %s.', 'exam-hall'), eh_format_gmt($attempt->code_locked_until))); ?></p>
        <?php else : ?>
            <p class="eh-note"><?php echo esc_html(sprintf(/* translators: %s: expiry time */ __('Current code expires %s.', 'exam-hall'), eh_format_gmt($attempt->code_expires_at))); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(eh_portal_url(array('eh_view' => 'gate', 'exam_id' => (int) $exam->id))); ?>">
            <?php wp_nonce_field('eh_portal'); ?>
            <input type="hidden" name="eh_action" value="verify_code">
            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
            <label class="eh-field">
                <span><?php esc_html_e('Start code', 'exam-hall'); ?></span>
                <input class="eh-code-input" type="text" name="start_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,12}" required <?php disabled($expired || $locked); ?>>
            </label>
            <button class="eh-btn eh-btn-primary" type="submit" <?php disabled($expired || $locked); ?>><?php esc_html_e('Start exam', 'exam-hall'); ?></button>
        </form>
        <form method="post" action="<?php echo esc_url(eh_portal_url(array('eh_view' => 'gate', 'exam_id' => (int) $exam->id))); ?>" class="eh-inline-form">
            <?php wp_nonce_field('eh_portal'); ?>
            <input type="hidden" name="eh_action" value="resend_code">
            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
            <button class="eh-btn" type="submit"><?php echo $expired ? esc_html__('Email a new code', 'exam-hall') : esc_html__('Resend the code', 'exam-hall'); ?></button>
        </form>
    </div>
    <p class="eh-form-foot"><a href="<?php echo esc_url(eh_portal_url()); ?>"><?php esc_html_e('Back to exams', 'exam-hall'); ?></a></p>
</section>
