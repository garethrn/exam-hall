<?php
/**
 * Question bank.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><a href="<?php echo esc_url(admin_url('admin.php?page=eh-exams&action=edit&exam_id=' . (int) $exam->id)); ?>"><?php echo esc_html($exam->title); ?></a></p>
            <h1><?php esc_html_e('Questions', 'exam-hall'); ?></h1>
            <p class="eh-lede">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: question count, 2: total marks */
                        __('%1$d questions · %2$s marks', 'exam-hall'),
                        count($questions),
                        eh_format_marks($total_marks)
                    )
                );
                ?>
            </p>
        </div>
        <div class="eh-hero-actions">
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=eh-questions&exam_id=' . (int) $exam->id . '&action=new')); ?>"><?php esc_html_e('Add question', 'exam-hall'); ?></a>
        </div>
    </header>

    <?php if ($in_progress > 0) : ?>
        <div class="eh-callout">
            <p><?php esc_html_e('Students are sitting this exam now. Their paper was frozen when they started, so edits here apply to the next sitting.', 'exam-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php
    $eh_type = eh_exam_question_type($exam);
    $eh_default_marks = eh_format_marks(eh_exam_default_marks($exam));
    $eh_template = wp_nonce_url(admin_url('admin-post.php?action=eh_question_template&exam_id=' . (int) $exam->id), 'eh_question_template');
    ?>
    <section class="eh-panel">
        <h2><?php esc_html_e('Question format', 'exam-hall'); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('eh_save_question_settings'); ?>
            <input type="hidden" name="action" value="eh_save_question_settings">
            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
            <div class="eh-two">
                <label class="eh-field">
                    <span><?php esc_html_e('Format', 'exam-hall'); ?></span>
                    <select name="question_type">
                        <option value="multiple_choice" <?php selected($eh_type, 'multiple_choice'); ?>><?php esc_html_e('Multiple choice', 'exam-hall'); ?></option>
                        <option value="true_false" <?php selected($eh_type, 'true_false'); ?>><?php esc_html_e('True or false', 'exam-hall'); ?></option>
                    </select>
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Marks for each question', 'exam-hall'); ?></span>
                    <input type="number" name="default_marks" min="0.25" max="100" step="0.25" required value="<?php echo esc_attr($eh_default_marks); ?>">
                </label>
            </div>
            <p class="eh-note">
                <?php
                if ($eh_type === 'true_false') {
                    esc_html_e('Students choose True or False. Download the true or false template, fill in the correct answer, and upload it here. Leave marks blank to use the number above, or set marks on a row.', 'exam-hall');
                } else {
                    esc_html_e('Students choose from the options in the file. The correct answer can be A, B, C, D, or the exact option text. Leave marks blank to use the number above, or set marks on a row.', 'exam-hall');
                }
                ?>
            </p>
            <button class="eh-btn" type="submit"><?php esc_html_e('Save format', 'exam-hall'); ?></button>
        </form>
    </section>

    <section class="eh-panel">
        <h2><?php esc_html_e('Upload a CSV', 'exam-hall'); ?></h2>
        <p><a class="eh-btn" href="<?php echo esc_url($eh_template); ?>"><?php esc_html_e('Download the CSV template', 'exam-hall'); ?></a></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="eh-upload" data-confirm-replace="<?php esc_attr_e('Replace the current question bank? Students who have not started will see the new questions.', 'exam-hall'); ?>">
            <?php wp_nonce_field('eh_import_csv'); ?>
            <input type="hidden" name="action" value="eh_import_csv">
            <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
            <label class="eh-file">
                <input id="eh-csv-file" type="file" name="csv" accept=".csv,text/csv" required>
                <span id="eh-csv-name"><?php esc_html_e('Choose a CSV file', 'exam-hall'); ?></span>
            </label>
            <label class="eh-check">
                <input type="checkbox" name="replace" value="1" checked>
                <span><?php esc_html_e('Replace existing questions', 'exam-hall'); ?></span>
            </label>
            <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Import', 'exam-hall'); ?></button>
        </form>
        <?php if (!empty($import_errors)) : ?>
            <ul class="eh-error-list">
                <?php foreach ($import_errors as $error) : ?>
                    <li>
                        <?php
                        if (!empty($error['row'])) {
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: row number, 2: error message */
                                    __('Row %1$d: %2$s', 'exam-hall'),
                                    (int) $error['row'],
                                    $error['message']
                                )
                            );
                        } else {
                            echo esc_html($error['message']);
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if (!$questions) : ?>
        <div class="eh-panel">
            <p class="eh-empty"><?php esc_html_e('This exam has no questions yet. Import a CSV or add one by hand.', 'exam-hall'); ?></p>
        </div>
    <?php else : ?>
        <div class="eh-table-wrap">
            <table class="eh-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('#', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Question', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Answer', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Marks', 'exam-hall'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $index => $question) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($index + 1)); ?></td>
                            <td><?php echo esc_html(wp_html_excerpt($question->question_text, 140, '…')); ?></td>
                            <td><code class="eh-code"><?php echo esc_html($question->correct_answer); ?></code></td>
                            <td><?php echo esc_html(eh_format_marks($question->marks)); ?></td>
                            <td class="eh-row-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=eh-questions&exam_id=' . (int) $exam->id . '&action=edit&question_id=' . (int) $question->id)); ?>"><?php esc_html_e('Edit', 'exam-hall'); ?></a>
                                <?php if ($index > 0) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('eh_move_question'); ?>
                                        <input type="hidden" name="action" value="eh_move_question">
                                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                                        <input type="hidden" name="question_id" value="<?php echo esc_attr((string) $question->id); ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="eh-link-button"><?php esc_html_e('Up', 'exam-hall'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($index < count($questions) - 1) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                        <?php wp_nonce_field('eh_move_question'); ?>
                                        <input type="hidden" name="action" value="eh_move_question">
                                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                                        <input type="hidden" name="question_id" value="<?php echo esc_attr((string) $question->id); ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="eh-link-button"><?php esc_html_e('Down', 'exam-hall'); ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-confirm="<?php esc_attr_e('Delete this question?', 'exam-hall'); ?>">
                                    <?php wp_nonce_field('eh_delete_question'); ?>
                                    <input type="hidden" name="action" value="eh_delete_question">
                                    <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                                    <input type="hidden" name="question_id" value="<?php echo esc_attr((string) $question->id); ?>">
                                    <button type="submit" class="eh-link-button"><?php esc_html_e('Delete', 'exam-hall'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
