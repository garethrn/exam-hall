<?php
/**
 * Student result.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$slug = eh_classification_slug($attempt->classification, $exam);
$percent = max(0, min(100, (float) $attempt->percentage));
?>
<section class="eh-result">
    <p class="eh-kicker"><?php echo esc_html($exam->title); ?></p>
    <div class="eh-result-hero">
        <div class="eh-score-ring" style="--p: <?php echo esc_attr((string) $percent); ?>">
            <strong><?php echo esc_html(eh_format_percentage($attempt->percentage)); ?></strong>
        </div>
        <div>
            <h1 class="eh-badge eh-badge-<?php echo esc_attr($slug); ?>"><?php echo esc_html($attempt->classification); ?></h1>
            <p class="eh-lede">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: score, 2: total marks */
                        __('You scored %1$s out of %2$s.', 'exam-hall'),
                        eh_format_marks($attempt->score),
                        eh_format_marks($attempt->total_marks)
                    )
                );
                ?>
            </p>
            <p>
                <?php if ($passed) : ?>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: pass percentage */
                            __('You reached the pass mark of %d%%.', 'exam-hall'),
                            (int) $exam->band_pass
                        )
                    );
                    ?>
                <?php else : ?>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %d: pass percentage */
                            __('You did not reach the pass mark of %d%%.', 'exam-hall'),
                            (int) $exam->band_pass
                        )
                    );
                    ?>
                <?php endif; ?>
            </p>
            <p class="eh-note">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: time taken */
                        __('Time taken %s.', 'exam-hall'),
                        eh_elapsed_label($attempt->started_at, $attempt->submitted_at)
                    )
                );
                ?>
                <?php if ((int) $attempt->auto_submitted) : ?>
                    <?php esc_html_e('The timer submitted your paper.', 'exam-hall'); ?>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="eh-result-actions eh-no-print">
        <a class="eh-btn" href="<?php echo esc_url(eh_portal_url()); ?>"><?php esc_html_e('Back to exams', 'exam-hall'); ?></a>
        <button class="eh-btn" type="button" onclick="window.print()"><?php esc_html_e('Print result', 'exam-hall'); ?></button>
        <?php if (eh_certificate_available($exam, $attempt)) : ?>
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(wp_nonce_url(eh_portal_url(array('eh_certificate' => (int) $attempt->id)), 'eh_certificate_' . (int) $attempt->id)); ?>"><?php esc_html_e('Download certificate', 'exam-hall'); ?></a>
        <?php elseif (!empty($exam->certificate_enabled) && $passed && !(int) get_option('eh_certificate_id')) : ?>
            <p class="eh-note"><?php esc_html_e('A certificate is allocated for this result. The design template has not been uploaded yet.', 'exam-hall'); ?></p>
        <?php elseif (!empty($exam->certificate_enabled) && !$passed) : ?>
            <p class="eh-note"><?php esc_html_e('A certificate is issued when you reach the pass mark.', 'exam-hall'); ?></p>
        <?php endif; ?>
        <?php if ($can_take && $access['can_continue']) : ?>
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'exam', 'attempt_id' => (int) $access['active']->id))); ?>"><?php esc_html_e('Continue exam', 'exam-hall'); ?></a>
        <?php elseif ($can_take && $access['can_enter_code']) : ?>
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'gate', 'exam_id' => (int) $exam->id))); ?>"><?php esc_html_e('Enter start code', 'exam-hall'); ?></a>
        <?php elseif ($can_take && $access['can_start'] && $access['reason'] === 'retake') : ?>
            <form method="post" action="<?php echo esc_url(eh_portal_url()); ?>">
                <?php wp_nonce_field('eh_portal'); ?>
                <input type="hidden" name="eh_action" value="request_code">
                <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Retake exam', 'exam-hall'); ?></button>
            </form>
        <?php elseif ($can_take && $access['reason'] === 'maxed') : ?>
            <p class="eh-note"><?php esc_html_e('You have used every attempt for this exam.', 'exam-hall'); ?></p>
        <?php endif; ?>
    </div>

    <ol class="eh-review">
        <?php foreach ($questions as $index => $question) : ?>
            <?php
            $answer = isset($answers[(int) $question->id]) ? $answers[(int) $question->id] : null;
            $selected = $answer ? (string) $answer->selected_answer : '';
            $options = eh_question_options($question);
            $is_correct = $answer && (int) $answer->is_correct === 1;
            $state = $selected === '' ? 'blank' : ($is_correct ? 'correct' : 'wrong');
            ?>
            <li class="eh-review-item is-<?php echo esc_attr($state); ?>">
                <p class="eh-kicker">
                    <?php echo esc_html(sprintf(/* translators: %d: question number */ __('Question %d', 'exam-hall'), $index + 1)); ?>
                    ·
                    <?php
                    if ($state === 'blank') {
                        esc_html_e('Not answered', 'exam-hall');
                    } elseif ($state === 'correct') {
                        esc_html_e('Correct', 'exam-hall');
                    } else {
                        esc_html_e('Incorrect', 'exam-hall');
                    }
                    ?>
                </p>
                <h2><?php echo nl2br(esc_html($question->question_text)); ?></h2>
                <p>
                    <?php if ($selected === '') : ?>
                        <?php esc_html_e('You did not choose an answer.', 'exam-hall'); ?>
                    <?php else : ?>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: letter, 2: option text */
                                __('Your answer: %1$s. %2$s', 'exam-hall'),
                                $selected,
                                isset($options[$selected]) ? $options[$selected] : ''
                            )
                        );
                        ?>
                    <?php endif; ?>
                </p>
                <?php if ((int) $exam->show_correct === 1 && $state !== 'correct') : ?>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: letter, 2: option text */
                                __('Correct answer: %1$s. %2$s', 'exam-hall'),
                                $question->correct_answer,
                                isset($options[$question->correct_answer]) ? $options[$question->correct_answer] : ''
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>
                <p class="eh-note"><?php echo esc_html(eh_format_marks($answer ? $answer->marks_awarded : 0) . ' / ' . eh_format_marks($question->marks)); ?></p>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
