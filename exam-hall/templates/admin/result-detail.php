<?php
/**
 * One sitting, for an administrator.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$slug = eh_classification_slug($attempt->classification, $exam);
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><a href="<?php echo esc_url(admin_url('admin.php?page=eh-results')); ?>"><?php esc_html_e('Results', 'exam-hall'); ?></a></p>
            <h1><?php echo esc_html($exam->title); ?></h1>
            <p class="eh-lede">
                <?php if ($student) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=eh-students&action=edit&user_id=' . (int) $student->ID)); ?>"><?php echo esc_html($student->display_name); ?></a>
                    · <?php echo esc_html($student->user_email); ?>
                    <?php if (get_user_meta($student->ID, 'eh_student_number', true)) : ?>
                        · <?php echo esc_html((string) get_user_meta($student->ID, 'eh_student_number', true)); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="eh-score-summary">
            <strong><?php echo esc_html(eh_format_percentage($attempt->percentage)); ?></strong>
            <span class="eh-pill eh-pill-<?php echo esc_attr($slug); ?>"><?php echo esc_html($attempt->classification); ?></span>
            <span><?php echo esc_html(eh_format_marks($attempt->score) . ' / ' . eh_format_marks($attempt->total_marks)); ?></span>
        </div>
    </header>
    <p class="eh-note">
        <?php
        echo esc_html(
            sprintf(
                /* translators: 1: time taken, 2: submitted time */
                __('Time taken %1$s · submitted %2$s', 'exam-hall'),
                eh_elapsed_label($attempt->started_at, $attempt->submitted_at),
                eh_format_gmt($attempt->submitted_at)
            )
        );
        ?>
        <?php if ((int) $attempt->auto_submitted) : ?>
            <?php esc_html_e('The timer submitted this paper.', 'exam-hall'); ?>
        <?php endif; ?>
    </p>
    <?php if (eh_certificate_available($exam, $attempt)) : ?>
        <p>
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=eh_certificate&attempt_id=' . (int) $attempt->id), 'eh_certificate')); ?>"><?php esc_html_e('Download certificate', 'exam-hall'); ?></a>
        </p>
    <?php endif; ?>

    <ol class="eh-review">
        <?php foreach ($questions as $index => $question) : ?>
            <?php
            $answer = isset($answers[(int) $question->id]) ? $answers[(int) $question->id] : null;
            $selected = $answer ? (string) $answer->selected_answer : '';
            $correct = (string) $question->correct_answer;
            $is_correct = $answer && (int) $answer->is_correct === 1;
            $options = eh_question_options($question);
            ?>
            <li class="eh-panel <?php echo $selected === '' ? 'is-blank' : ($is_correct ? 'is-correct' : 'is-wrong'); ?>">
                <p class="eh-kicker"><?php echo esc_html(sprintf(/* translators: %d: question number */ __('Question %d', 'exam-hall'), $index + 1)); ?></p>
                <h2><?php echo nl2br(esc_html($question->question_text)); ?></h2>
                <p>
                    <?php if ($selected === '') : ?>
                        <?php esc_html_e('No answer', 'exam-hall'); ?>
                    <?php else : ?>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: letter, 2: option text */
                                __('Student answer: %1$s. %2$s', 'exam-hall'),
                                $selected,
                                isset($options[$selected]) ? $options[$selected] : ''
                            )
                        );
                        ?>
                    <?php endif; ?>
                </p>
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: letter, 2: option text */
                            __('Correct answer: %1$s. %2$s', 'exam-hall'),
                            $correct,
                            isset($options[$correct]) ? $options[$correct] : ''
                        )
                    );
                    ?>
                </p>
                <p class="eh-note"><?php echo esc_html(eh_format_marks($answer ? $answer->marks_awarded : 0) . ' / ' . eh_format_marks($question->marks)); ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
