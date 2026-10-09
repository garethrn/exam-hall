<?php
/**
 * Admin dashboard.
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
            <p class="eh-kicker"><?php echo esc_html($brand['name'] !== '' ? $brand['name'] : __('Exam Hall', 'exam-hall')); ?></p>
            <h1><?php esc_html_e('Dashboard', 'exam-hall'); ?></h1>
            <?php if ($brand['logo'] !== '') : ?>
                <img class="eh-admin-logo" src="<?php echo esc_url($brand['logo']); ?>" alt="">
            <?php endif; ?>
            <p class="eh-lede"><?php esc_html_e('Students sign in, request a start code by email, and sit a timed paper. Results are marked as soon as they submit.', 'exam-hall'); ?></p>
        </div>
        <div class="eh-hero-actions">
            <a class="eh-btn" href="<?php echo esc_url($portal_url); ?>"><?php esc_html_e('Open student portal', 'exam-hall'); ?></a>
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=eh-exams&action=new')); ?>"><?php esc_html_e('New exam', 'exam-hall'); ?></a>
        </div>
    </header>

    <?php if ($draft_count > 0) : ?>
        <div class="eh-callout">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: number of draft exams */
                        _n('%d exam is still a draft. Publish it before students can see it.', '%d exams are still drafts. Publish them before students can see them.', $draft_count, 'exam-hall'),
                        $draft_count
                    )
                );
                ?>
            </p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=eh-exams')); ?>"><?php esc_html_e('Review exams', 'exam-hall'); ?></a>
        </div>
    <?php endif; ?>

    <section class="eh-stats" aria-label="<?php esc_attr_e('Summary', 'exam-hall'); ?>">
        <article>
            <p><?php esc_html_e('Students', 'exam-hall'); ?></p>
            <strong><?php echo esc_html((string) $stats['students']); ?></strong>
        </article>
        <article>
            <p><?php esc_html_e('Published exams', 'exam-hall'); ?></p>
            <strong><?php echo esc_html((string) $stats['published']); ?></strong>
        </article>
        <article>
            <p><?php esc_html_e('Completed sittings', 'exam-hall'); ?></p>
            <strong><?php echo esc_html((string) $stats['submitted']); ?></strong>
            <span><?php echo esc_html(sprintf(/* translators: %d: sittings today */ __('%d today', 'exam-hall'), $stats['today'])); ?></span>
        </article>
        <article>
            <p><?php esc_html_e('Average score', 'exam-hall'); ?></p>
            <strong><?php echo $stats['average'] === null ? '—' : esc_html(eh_format_percentage($stats['average'])); ?></strong>
        </article>
    </section>

    <div class="eh-split">
        <section class="eh-panel">
            <div class="eh-panel-head">
                <h2><?php esc_html_e('Start codes waiting', 'exam-hall'); ?></h2>
            </div>
            <?php if (!$pending) : ?>
                <p class="eh-empty"><?php esc_html_e('No student is waiting on a start code.', 'exam-hall'); ?></p>
            <?php else : ?>
                <div class="eh-table-wrap">
                    <table class="eh-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Student', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('Exam', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('Code', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('Expires', 'exam-hall'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending as $row) : ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($row->display_name); ?>
                                        <span class="eh-sub"><?php echo esc_html($row->user_email); ?></span>
                                    </td>
                                    <td><?php echo esc_html($row->exam_title); ?></td>
                                    <td><code class="eh-code"><?php echo esc_html($row->start_code); ?></code></td>
                                    <td><?php echo esc_html(eh_format_gmt($row->code_expires_at)); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('eh_resend_code'); ?>
                                            <input type="hidden" name="action" value="eh_resend_code">
                                            <input type="hidden" name="attempt_id" value="<?php echo esc_attr((string) $row->id); ?>">
                                            <button class="eh-btn eh-btn-small" type="submit"><?php esc_html_e('Resend', 'exam-hall'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="eh-note"><?php esc_html_e('If email is not reaching students, read the code from this table. It is not shown on the student screen.', 'exam-hall'); ?></p>
            <?php endif; ?>
        </section>

        <section class="eh-panel">
            <div class="eh-panel-head">
                <h2><?php esc_html_e('Recent results', 'exam-hall'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=eh-results')); ?>"><?php esc_html_e('All results', 'exam-hall'); ?></a>
            </div>
            <?php if (!$recent) : ?>
                <p class="eh-empty"><?php esc_html_e('Results appear here after the first sitting.', 'exam-hall'); ?></p>
            <?php else : ?>
                <ul class="eh-result-list">
                    <?php foreach ($recent as $row) : ?>
                        <li>
                            <div>
                                <strong><?php echo esc_html($row->display_name); ?></strong>
                                <span><?php echo esc_html($row->exam_title); ?></span>
                            </div>
                            <div class="eh-result-score">
                                <b><?php echo esc_html(eh_format_percentage($row->percentage)); ?></b>
                                <span><?php echo esc_html($row->classification); ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>

    <section class="eh-panel eh-assign">
        <div class="eh-panel-head">
            <h2><?php esc_html_e('Allocate an exam', 'exam-hall'); ?></h2>
        </div>
        <p class="eh-note"><?php esc_html_e('Select students, choose an exam, and allocate it. Allocating limits that exam to the students on its list. You can also allocate exams from a student’s profile.', 'exam-hall'); ?></p>
        <?php if (!$students['users'] || !$exams) : ?>
            <p class="eh-empty"><?php esc_html_e('Add a student and an exam before allocating.', 'exam-hall'); ?></p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('eh_assign_students'); ?>
                <input type="hidden" name="action" value="eh_assign_students">
                <div class="eh-assign-bar">
                    <label class="eh-field">
                        <span><?php esc_html_e('Exam', 'exam-hall'); ?></span>
                        <select name="exam_id" required>
                            <option value=""><?php esc_html_e('Choose an exam', 'exam-hall'); ?></option>
                            <?php foreach ($exams as $exam) : ?>
                                <option value="<?php echo esc_attr((string) $exam->id); ?>"><?php echo esc_html($exam->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="eh-field">
                        <span><?php esc_html_e('Find a student', 'exam-hall'); ?></span>
                        <input type="search" id="eh-assign-filter" placeholder="<?php esc_attr_e('Name or email', 'exam-hall'); ?>">
                    </label>
                </div>
                <label class="eh-check">
                    <input type="checkbox" id="eh-assign-all">
                    <span><?php esc_html_e('Select every student shown', 'exam-hall'); ?></span>
                </label>
                <div class="eh-assign-list">
                    <?php foreach ($students['users'] as $student) : ?>
                        <label class="eh-check" data-student="<?php echo esc_attr(strtolower($student->display_name . ' ' . $student->user_email)); ?>">
                            <input type="checkbox" name="user_ids[]" value="<?php echo esc_attr((string) $student->ID); ?>">
                            <span><?php echo esc_html($student->display_name); ?> <small><?php echo esc_html($student->user_email); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if ((int) $students['total'] > count($students['users'])) : ?>
                    <p class="eh-note"><?php esc_html_e('The first 300 students are listed here. Allocate the rest from each student profile.', 'exam-hall'); ?></p>
                <?php endif; ?>
                <div class="eh-hero-actions">
                    <button class="eh-btn eh-btn-primary" type="submit" name="assign_mode" value="assign"><?php esc_html_e('Allocate selected', 'exam-hall'); ?></button>
                    <button class="eh-btn" type="submit" name="assign_mode" value="remove"><?php esc_html_e('Remove selected', 'exam-hall'); ?></button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
