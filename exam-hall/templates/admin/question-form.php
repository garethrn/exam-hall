<?php
/**
 * One question.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

$values = array(
    'question_text' => $question ? $question->question_text : '',
    'option_a' => $question ? $question->option_a : '',
    'option_b' => $question ? $question->option_b : '',
    'option_c' => $question ? $question->option_c : '',
    'option_d' => $question ? $question->option_d : '',
    'correct_answer' => $question ? $question->correct_answer : 'A',
    'marks' => $question ? eh_format_marks($question->marks) : eh_format_marks(eh_exam_default_marks($exam)),
);
$eh_true_false = eh_exam_question_type($exam) === 'true_false';
if ($eh_true_false && !$question) {
    $values['correct_answer'] = 'A';
    $values['option_a'] = 'True';
    $values['option_b'] = 'False';
}
if (is_array($flash) && !empty($flash['values']) && is_array($flash['values'])) {
    $values = array_merge($values, $flash['values']);
}
$back = admin_url('admin.php?page=eh-questions&exam_id=' . (int) $exam->id);
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><a href="<?php echo esc_url($back); ?>"><?php echo esc_html($exam->title); ?></a></p>
            <h1><?php echo $question ? esc_html__('Edit question', 'exam-hall') : esc_html__('Add question', 'exam-hall'); ?></h1>
        </div>
    </header>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eh-panel eh-narrow">
        <?php wp_nonce_field('eh_save_question'); ?>
        <input type="hidden" name="action" value="eh_save_question">
        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
        <input type="hidden" name="question_id" value="<?php echo esc_attr($question ? (string) $question->id : '0'); ?>">
        <label class="eh-field">
            <span><?php esc_html_e('Question', 'exam-hall'); ?></span>
            <textarea name="question_text" rows="4" required><?php echo esc_textarea($values['question_text']); ?></textarea>
        </label>
        <?php if ($eh_true_false) : ?>
            <fieldset class="eh-field">
                <legend><?php esc_html_e('Correct answer', 'exam-hall'); ?></legend>
                <label class="eh-check">
                    <input type="radio" name="correct_answer" value="A" <?php checked($values['correct_answer'], 'A'); ?>>
                    <span><?php esc_html_e('True', 'exam-hall'); ?></span>
                </label>
                <label class="eh-check">
                    <input type="radio" name="correct_answer" value="B" <?php checked($values['correct_answer'], 'B'); ?>>
                    <span><?php esc_html_e('False', 'exam-hall'); ?></span>
                </label>
            </fieldset>
        <?php else : ?>
            <?php foreach (array('A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d') as $letter => $name) : ?>
                <div class="eh-option-edit">
                    <label class="eh-radio-label">
                        <input type="radio" name="correct_answer" value="<?php echo esc_attr($letter); ?>" <?php checked($values['correct_answer'], $letter); ?>>
                        <span><?php echo esc_html($letter); ?></span>
                    </label>
                    <label class="eh-field">
                        <span><?php echo esc_html(sprintf(/* translators: %s: option letter */ __('Option %s', 'exam-hall'), $letter)); ?></span>
                        <textarea name="<?php echo esc_attr($name); ?>" rows="2"><?php echo esc_textarea($values[$name]); ?></textarea>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <label class="eh-field">
            <span><?php esc_html_e('Marks', 'exam-hall'); ?></span>
            <input type="number" name="marks" min="0.25" max="100" step="0.25" required value="<?php echo esc_attr((string) $values['marks']); ?>">
        </label>
        <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Save question', 'exam-hall'); ?></button>
    </form>
</div>
