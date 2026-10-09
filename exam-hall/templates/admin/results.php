<?php
/**
 * Results list.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
$export_url = wp_nonce_url(
    add_query_arg(
        array(
            'action' => 'eh_export_results',
            'exam_id' => $exam_id,
            'classification' => $classification,
            's' => $search,
        ),
        admin_url('admin-post.php')
    ),
    'eh_export_results'
);
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><?php esc_html_e('Exam Hall', 'exam-hall'); ?></p>
            <h1><?php esc_html_e('Results', 'exam-hall'); ?></h1>
        </div>
        <div class="eh-hero-actions">
            <a class="eh-btn" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'exam-hall'); ?></a>
        </div>
    </header>

    <form class="eh-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="eh-results">
        <label>
            <span><?php esc_html_e('Exam', 'exam-hall'); ?></span>
            <select name="exam_id">
                <option value="0"><?php esc_html_e('All exams', 'exam-hall'); ?></option>
                <?php foreach ($exams as $exam) : ?>
                    <option value="<?php echo esc_attr((string) $exam->id); ?>" <?php selected($exam_id, (int) $exam->id); ?>><?php echo esc_html($exam->title); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('Classification', 'exam-hall'); ?></span>
            <select name="classification">
                <option value=""><?php esc_html_e('All', 'exam-hall'); ?></option>
                <?php foreach ($classifications as $label) : ?>
                    <option value="<?php echo esc_attr($label); ?>" <?php selected($classification, $label); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('Student', 'exam-hall'); ?></span>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Name or email', 'exam-hall'); ?>">
        </label>
        <button class="eh-btn" type="submit"><?php esc_html_e('Filter', 'exam-hall'); ?></button>
    </form>

    <?php if (!$rows) : ?>
        <div class="eh-panel">
            <p class="eh-empty"><?php esc_html_e('No results match this filter.', 'exam-hall'); ?></p>
        </div>
    <?php else : ?>
        <div class="eh-table-wrap">
            <table class="eh-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Student', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Exam', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Score', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Percentage', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Classification', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Submitted', 'exam-hall'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td>
                                <a class="eh-row-title" href="<?php echo esc_url(admin_url('admin.php?page=eh-results&action=view&attempt_id=' . (int) $row->id)); ?>"><?php echo esc_html($row->display_name); ?></a>
                                <span class="eh-sub"><?php echo esc_html($row->user_email); ?></span>
                            </td>
                            <td><?php echo esc_html($row->exam_title); ?></td>
                            <td><?php echo esc_html(eh_format_marks($row->score) . ' / ' . eh_format_marks($row->total_marks)); ?></td>
                            <td><?php echo esc_html(eh_format_percentage($row->percentage)); ?></td>
                            <td>
                                <?php echo esc_html($row->classification); ?>
                                <?php if ((int) $row->auto_submitted) : ?>
                                    <span class="eh-sub"><?php esc_html_e('Time expired', 'exam-hall'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(eh_format_gmt($row->submitted_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        eh_pagination(
            $total,
            $per_page,
            $paged,
            add_query_arg(
                array(
                    'page' => 'eh-results',
                    'exam_id' => $exam_id,
                    'classification' => $classification,
                    's' => $search,
                ),
                admin_url('admin.php')
            )
        );
        ?>
    <?php endif; ?>
</div>
