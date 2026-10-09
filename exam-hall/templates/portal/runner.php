<?php
/**
 * Timed exam runner.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$total = count($questions);
$initial = $total > 0 ? (1 / $total) * 100 : 0;
$config = array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => $exam_nonce,
    'attemptId' => (int) $attempt->id,
    'remaining' => (int) $remaining,
    'progressLabel' => __('Question %1$d of %2$d', 'exam-hall'),
    'answeredLabel' => __('%1$d of %2$d answered', 'exam-hall'),
);
?>
<section class="eh-runner">
    <div class="eh-exam-bar">
        <div class="eh-exam-bar-row">
            <p class="eh-exam-kicker"><?php echo esc_html($exam->title); ?></p>
            <p class="eh-timer" id="eh-timer" aria-live="polite"><?php echo esc_html(sprintf('%02d:%02d', intdiv(max(0, (int) $remaining), 60), max(0, (int) $remaining) % 60)); ?></p>
        </div>
        <div class="eh-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr((string) $total); ?>" aria-valuenow="1" aria-label="<?php esc_attr_e('Exam progress', 'exam-hall'); ?>">
            <div class="eh-progress-fill" id="eh-progress-fill" style="width: <?php echo esc_attr((string) $initial); ?>%"></div>
        </div>
        <div class="eh-exam-bar-foot">
            <p id="eh-progress-label"><?php echo esc_html(sprintf(/* translators: 1: current question, 2: total */ __('Question %1$d of %2$d', 'exam-hall'), 1, $total)); ?></p>
            <p id="eh-answered"><?php echo esc_html(sprintf(/* translators: 1: answered count, 2: total */ __('%1$d of %2$d answered', 'exam-hall'), (int) $answered, $total)); ?></p>
        </div>
    </div>

    <div class="eh-dots" role="tablist" aria-label="<?php esc_attr_e('Questions', 'exam-hall'); ?>">
        <?php foreach ($questions as $index => $question) : ?>
            <?php $chosen = isset($selections[(int) $question->id]) && $selections[(int) $question->id] !== ''; ?>
            <button type="button" class="eh-dot<?php echo $index === 0 ? ' is-current' : ''; ?><?php echo $chosen ? ' is-answered' : ''; ?>" data-jump="<?php echo esc_attr((string) $index); ?>">
                <span class="screen-reader-text"><?php echo esc_html(sprintf(/* translators: %d: question number */ __('Question %d', 'exam-hall'), $index + 1)); ?></span>
                <span aria-hidden="true"><?php echo esc_html((string) ($index + 1)); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <form id="eh-exam-form" method="post" action="<?php echo esc_url(eh_portal_url(array('eh_view' => 'exam', 'attempt_id' => (int) $attempt->id))); ?>">
        <?php wp_nonce_field('eh_portal'); ?>
        <input type="hidden" name="eh_action" value="submit_exam">
        <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $attempt->id); ?>">
        <?php foreach ($questions as $index => $question) : ?>
            <?php $options = eh_question_options($question); ?>
            <fieldset class="eh-q<?php echo $index === 0 ? ' is-current' : ''; ?>" data-id="<?php echo esc_attr((string) $question->id); ?>">
                <legend><?php echo esc_html(sprintf(/* translators: %d: question number */ __('Question %d', 'exam-hall'), $index + 1)); ?></legend>
                <h2><?php echo nl2br(esc_html($question->question_text)); ?></h2>
                <div class="eh-options">
                    <?php foreach ($options as $letter => $text) : ?>
                        <?php $checked = isset($selections[(int) $question->id]) && $selections[(int) $question->id] === $letter; ?>
                        <label class="eh-option<?php echo $checked ? ' is-selected' : ''; ?>">
                            <input type="radio" name="q[<?php echo esc_attr((string) $question->id); ?>]" value="<?php echo esc_attr($letter); ?>" <?php checked($checked); ?>>
                            <span class="eh-option-body">
                                <span class="eh-key"><?php echo esc_html($letter); ?></span>
                                <span><?php echo esc_html($text); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <div class="eh-exam-nav">
            <button class="eh-btn" type="button" id="eh-prev"><?php esc_html_e('Previous', 'exam-hall'); ?></button>
            <button class="eh-btn" type="button" id="eh-next"><?php esc_html_e('Next', 'exam-hall'); ?></button>
            <button class="eh-btn eh-btn-primary" type="button" id="eh-submit"><?php esc_html_e('Submit exam', 'exam-hall'); ?></button>
            <noscript><button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Submit exam', 'exam-hall'); ?></button></noscript>
        </div>
    </form>
    <p class="eh-live screen-reader-text" id="eh-live" aria-live="polite"></p>

    <div class="eh-modal" id="eh-submit-modal" hidden>
        <div class="eh-modal-card" role="dialog" aria-modal="true" aria-labelledby="eh-submit-title">
            <h2 id="eh-submit-title"><?php esc_html_e('Submit this exam?', 'exam-hall'); ?></h2>
            <p id="eh-submit-copy"></p>
            <div class="eh-modal-actions">
                <button class="eh-btn" type="button" id="eh-submit-cancel"><?php esc_html_e('Keep working', 'exam-hall'); ?></button>
                <button class="eh-btn eh-btn-primary" type="button" id="eh-submit-confirm"><?php esc_html_e('Submit exam', 'exam-hall'); ?></button>
            </div>
        </div>
    </div>
    <div class="eh-modal" id="eh-timeup" hidden>
        <div class="eh-modal-card">
            <h2><?php esc_html_e('Time is up', 'exam-hall'); ?></h2>
            <p><?php esc_html_e('Your answers are being submitted.', 'exam-hall'); ?></p>
        </div>
    </div>
    <script type="application/json" id="eh-exam-config"><?php echo wp_json_encode($config); ?></script>
</section>
