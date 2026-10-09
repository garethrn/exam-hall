<?php
/**
 * Exam list.
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
            <p class="eh-kicker"><?php esc_html_e('Exam Hall', 'exam-hall'); ?></p>
            <h1><?php esc_html_e('Exams', 'exam-hall'); ?></h1>
        </div>
        <div class="eh-hero-actions">
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=eh-exams&action=new')); ?>"><?php esc_html_e('New exam', 'exam-hall'); ?></a>
        </div>
    </header>

    <?php if (!$exams) : ?>
        <div class="eh-panel">
            <p class="eh-empty"><?php esc_html_e('Create an exam, add questions from a CSV, then publish it.', 'exam-hall'); ?></p>
        </div>
    <?php else : ?>
        <div class="eh-table-wrap">
            <table class="eh-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Exam', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Status', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Time', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Questions', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Attempts', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Pass mark', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Access', 'exam-hall'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $exam) : ?>
                        <?php $attempts = EH_Repository::attempt_count($exam->id); ?>
                        <tr>
                            <td>
                                <a class="eh-row-title" href="<?php echo esc_url(admin_url('admin.php?page=eh-exams&action=edit&exam_id=' . (int) $exam->id)); ?>"><?php echo esc_html($exam->title); ?></a>
                                <span class="eh-sub">
                                    <?php
                                    echo esc_html(eh_exam_question_type($exam) === 'true_false' ? __('True or false', 'exam-hall') : __('Multiple choice', 'exam-hall'));
                                    echo ' · ';
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: marks per question */
                                            __('%s marks each', 'exam-hall'),
                                            eh_format_marks(eh_exam_default_marks($exam))
                                        )
                                    );
                                    ?>
                                </span>
                            </td>
                            <td><span class="eh-pill eh-pill-<?php echo esc_attr($exam->status); ?>"><?php echo esc_html(ucfirst($exam->status)); ?></span></td>
                            <td><?php echo esc_html(eh_duration_label($exam->duration_minutes)); ?></td>
                            <td><?php echo esc_html((string) (isset($question_counts[(int) $exam->id]) ? $question_counts[(int) $exam->id] : 0)); ?></td>
                            <td><?php echo esc_html((string) $attempts); ?></td>
                            <td><?php echo esc_html((string) (int) $exam->band_pass); ?>%</td>
                            <td>
                                <?php
                                if (isset($exam->audience) && $exam->audience === 'assigned') {
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %d: allocated students */
                                            _n('%d student', '%d students', isset($assignment_counts[(int) $exam->id]) ? $assignment_counts[(int) $exam->id] : 0, 'exam-hall'),
                                            isset($assignment_counts[(int) $exam->id]) ? $assignment_counts[(int) $exam->id] : 0
                                        )
                                    );
                                } else {
                                    esc_html_e('Everyone', 'exam-hall');
                                }
                                ?>
                            </td>
                            <td class="eh-row-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=eh-questions&exam_id=' . (int) $exam->id)); ?>"><?php esc_html_e('Questions', 'exam-hall'); ?></a>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=eh-results&exam_id=' . (int) $exam->id)); ?>"><?php esc_html_e('Results', 'exam-hall'); ?></a>
                                <?php if ($attempts === 0) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-confirm="<?php esc_attr_e('Delete this exam and its questions?', 'exam-hall'); ?>">
                                        <?php wp_nonce_field('eh_delete_exam'); ?>
                                        <input type="hidden" name="action" value="eh_delete_exam">
                                        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                                        <button type="submit" class="eh-link-button"><?php esc_html_e('Delete', 'exam-hall'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
