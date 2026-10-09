<?php
/**
 * Student list.
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
            <h1><?php esc_html_e('Students', 'exam-hall'); ?></h1>
            <p class="eh-lede"><?php esc_html_e('Capture student details here. Each account gets an email address and a password that can be reset.', 'exam-hall'); ?></p>
        </div>
        <div class="eh-hero-actions">
            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=eh-students&action=new')); ?>"><?php esc_html_e('Add student', 'exam-hall'); ?></a>
        </div>
    </header>

    <section class="eh-panel">
        <h2><?php esc_html_e('Upload students', 'exam-hall'); ?></h2>
        <p class="eh-note"><?php esc_html_e('Columns: first_name, last_name, email, student_number, phone, programme, notes, status. Status is active or suspended. Each new account gets a temporary password, which is emailed and shown here for 15 minutes.', 'exam-hall'); ?></p>
        <p><a class="eh-btn" href="<?php echo esc_url(EH_URL . 'assets/samples/students-template.csv'); ?>"><?php esc_html_e('Download the CSV template', 'exam-hall'); ?></a></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="eh-upload">
            <?php wp_nonce_field('eh_import_students'); ?>
            <input type="hidden" name="action" value="eh_import_students">
            <label class="eh-file">
                <input id="eh-student-csv" type="file" name="csv" accept=".csv,text/csv" required>
                <span><?php esc_html_e('Choose a CSV file', 'exam-hall'); ?></span>
            </label>
            <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Import students', 'exam-hall'); ?></button>
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

    <?php if (!empty($imported_accounts)) : ?>
        <section class="eh-panel">
            <h2><?php esc_html_e('Temporary passwords', 'exam-hall'); ?></h2>
            <p class="eh-note"><?php esc_html_e('Pass these on if the welcome email does not arrive. This list disappears after 15 minutes.', 'exam-hall'); ?></p>
            <p><a class="eh-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=eh_download_student_passwords'), 'eh_download_student_passwords')); ?>"><?php esc_html_e('Download passwords', 'exam-hall'); ?></a></p>
            <div class="eh-table-wrap">
                <table class="eh-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Student', 'exam-hall'); ?></th>
                            <th><?php esc_html_e('Email', 'exam-hall'); ?></th>
                            <th><?php esc_html_e('Number', 'exam-hall'); ?></th>
                            <th><?php esc_html_e('Password', 'exam-hall'); ?></th>
                            <th><?php esc_html_e('Emailed', 'exam-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imported_accounts as $account) : ?>
                            <tr>
                                <td><?php echo esc_html($account['name']); ?></td>
                                <td><?php echo esc_html($account['email']); ?></td>
                                <td><?php echo esc_html($account['student_number']); ?></td>
                                <td><code class="eh-code"><?php echo esc_html($account['password']); ?></code></td>
                                <td><?php echo esc_html($account['mailed'] === 'yes' ? __('Yes', 'exam-hall') : __('No', 'exam-hall')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <form class="eh-filters" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="eh-students">
        <label>
            <span class="screen-reader-text"><?php esc_html_e('Search students', 'exam-hall'); ?></span>
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Name, email, number, or class', 'exam-hall'); ?>">
        </label>
        <button class="eh-btn" type="submit"><?php esc_html_e('Search', 'exam-hall'); ?></button>
    </form>

    <?php if (!$students) : ?>
        <div class="eh-panel">
            <p class="eh-empty"><?php echo $search !== '' ? esc_html__('No students match that search.', 'exam-hall') : esc_html__('Add the first student to send them a sign-in password.', 'exam-hall'); ?></p>
        </div>
    <?php else : ?>
        <div class="eh-table-wrap">
            <table class="eh-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Student', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Number', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Class / programme', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Status', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Sittings', 'exam-hall'); ?></th>
                        <th><?php esc_html_e('Registered', 'exam-hall'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student) : ?>
                        <?php
                        $status = get_user_meta($student->ID, 'eh_status', true);
                        if ($status !== 'suspended') {
                            $status = 'active';
                        }
                        ?>
                        <tr>
                            <td>
                                <a class="eh-row-title" href="<?php echo esc_url(admin_url('admin.php?page=eh-students&action=edit&user_id=' . (int) $student->ID)); ?>"><?php echo esc_html($student->display_name); ?></a>
                                <span class="eh-sub"><?php echo esc_html($student->user_email); ?></span>
                            </td>
                            <td><?php echo esc_html((string) get_user_meta($student->ID, 'eh_student_number', true)); ?></td>
                            <td><?php echo esc_html((string) get_user_meta($student->ID, 'eh_programme', true)); ?></td>
                            <td><span class="eh-pill eh-pill-<?php echo esc_attr($status); ?>"><?php echo esc_html($status === 'active' ? __('Active', 'exam-hall') : __('Suspended', 'exam-hall')); ?></span></td>
                            <td><?php echo esc_html((string) (isset($attempt_counts[(int) $student->ID]) ? $attempt_counts[(int) $student->ID] : 0)); ?></td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $student->user_registered)); ?></td>
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
                    'page' => 'eh-students',
                    's' => $search,
                ),
                admin_url('admin.php')
            )
        );
        ?>
    <?php endif; ?>
</div>
