<?php
/**
 * Student details.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

$values = array(
    'first_name' => $student ? $student->first_name : '',
    'last_name' => $student ? $student->last_name : '',
    'email' => $student ? $student->user_email : '',
    'student_number' => $student ? (string) get_user_meta($student->ID, 'eh_student_number', true) : '',
    'phone' => $student ? (string) get_user_meta($student->ID, 'eh_phone', true) : '',
    'programme' => $student ? (string) get_user_meta($student->ID, 'eh_programme', true) : '',
    'notes' => $student ? (string) get_user_meta($student->ID, 'eh_notes', true) : '',
    'status' => $student && get_user_meta($student->ID, 'eh_status', true) === 'suspended' ? 'suspended' : 'active',
);
if (is_array($flash) && !empty($flash['values']) && is_array($flash['values'])) {
    $values = array_merge($values, $flash['values']);
}
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><a href="<?php echo esc_url(admin_url('admin.php?page=eh-students')); ?>"><?php esc_html_e('Students', 'exam-hall'); ?></a></p>
            <h1><?php echo $student ? esc_html($student->display_name) : esc_html__('Add student', 'exam-hall'); ?></h1>
        </div>
    </header>

    <?php if ($student && $password_flash && (int) $password_flash['user_id'] === (int) $student->ID) : ?>
        <div class="eh-callout eh-callout-strong">
            <p>
                <?php
                echo $password_flash['mailed']
                    ? esc_html__('This temporary password was emailed to the student. It is shown here once in case the message is delayed.', 'exam-hall')
                    : esc_html__('The email could not be sent. Give the student this temporary password directly.', 'exam-hall');
                ?>
            </p>
            <p class="eh-password"><?php echo esc_html($password_flash['password']); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eh-panel eh-narrow">
        <?php wp_nonce_field('eh_save_student'); ?>
        <input type="hidden" name="action" value="eh_save_student">
        <input type="hidden" name="user_id" value="<?php echo esc_attr($student ? (string) $student->ID : '0'); ?>">
        <div class="eh-two">
            <label class="eh-field">
                <span><?php esc_html_e('First name', 'exam-hall'); ?></span>
                <input type="text" name="first_name" required maxlength="60" value="<?php echo esc_attr($values['first_name']); ?>">
            </label>
            <label class="eh-field">
                <span><?php esc_html_e('Last name', 'exam-hall'); ?></span>
                <input type="text" name="last_name" required maxlength="60" value="<?php echo esc_attr($values['last_name']); ?>">
            </label>
        </div>
        <label class="eh-field">
            <span><?php esc_html_e('Email', 'exam-hall'); ?></span>
            <input type="email" name="email" required value="<?php echo esc_attr($values['email']); ?>">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('Student number', 'exam-hall'); ?></span>
            <input type="text" name="student_number" required maxlength="40" value="<?php echo esc_attr($values['student_number']); ?>">
        </label>
        <div class="eh-two">
            <label class="eh-field">
                <span><?php esc_html_e('Phone', 'exam-hall'); ?></span>
                <input type="text" name="phone" maxlength="40" value="<?php echo esc_attr($values['phone']); ?>">
            </label>
            <label class="eh-field">
                <span><?php esc_html_e('Class / programme', 'exam-hall'); ?></span>
                <input type="text" name="programme" maxlength="120" value="<?php echo esc_attr($values['programme']); ?>">
            </label>
        </div>
        <label class="eh-field">
            <span><?php esc_html_e('Notes', 'exam-hall'); ?></span>
            <textarea name="notes" rows="3"><?php echo esc_textarea($values['notes']); ?></textarea>
            <small><?php esc_html_e('Notes stay in the admin dashboard. Students do not see them.', 'exam-hall'); ?></small>
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('Status', 'exam-hall'); ?></span>
            <select name="status">
                <option value="active" <?php selected($values['status'], 'active'); ?>><?php esc_html_e('Active', 'exam-hall'); ?></option>
                <option value="suspended" <?php selected($values['status'], 'suspended'); ?>><?php esc_html_e('Suspended', 'exam-hall'); ?></option>
            </select>
        </label>
        <?php if (!$student) : ?>
            <p class="eh-note"><?php esc_html_e('Saving creates the account and emails a temporary password. You will also see that password once on the next screen.', 'exam-hall'); ?></p>
        <?php endif; ?>
        <section class="eh-assign">
            <h2><?php esc_html_e('Allocated exams', 'exam-hall'); ?></h2>
            <p class="eh-note"><?php esc_html_e('Tick the exams this student should be able to open. Exams set to every student stay available without a tick.', 'exam-hall'); ?></p>
            <input type="hidden" name="allocate_present" value="1">
            <?php if (!$exams) : ?>
                <p class="eh-empty"><?php esc_html_e('Create an exam before allocating one.', 'exam-hall'); ?></p>
            <?php else : ?>
                <?php foreach ($exams as $item) : ?>
                    <?php $open = !isset($item->audience) || $item->audience !== 'assigned'; ?>
                    <label class="eh-check">
                        <input type="checkbox" name="exam_ids[]" value="<?php echo esc_attr((string) $item->id); ?>" <?php checked($open || in_array((int) $item->id, $assigned_ids, true)); ?> <?php disabled($open); ?>>
                        <span>
                            <?php echo esc_html($item->title); ?>
                            <small><?php echo esc_html($open ? __('Open to every student', 'exam-hall') : __('Allocated students only', 'exam-hall')); ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
        <button class="eh-btn eh-btn-primary" type="submit"><?php echo $student ? esc_html__('Save student', 'exam-hall') : esc_html__('Create student', 'exam-hall'); ?></button>
    </form>

    <?php if ($student) : ?>
        <section class="eh-panel">
            <h2><?php esc_html_e('Password', 'exam-hall'); ?></h2>
            <p class="eh-note"><?php esc_html_e('Resetting sets a new temporary password, emails it, and signs the student out of other sessions.', 'exam-hall'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-confirm="<?php esc_attr_e('Reset this student password and email a new one?', 'exam-hall'); ?>">
                <?php wp_nonce_field('eh_reset_password'); ?>
                <input type="hidden" name="action" value="eh_reset_password">
                <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $student->ID); ?>">
                <button class="eh-btn" type="submit"><?php esc_html_e('Reset password', 'exam-hall'); ?></button>
            </form>
        </section>

        <section class="eh-panel">
            <h2><?php esc_html_e('Exam history', 'exam-hall'); ?></h2>
            <?php if (!$attempts) : ?>
                <p class="eh-empty"><?php esc_html_e('This student has not started an exam yet.', 'exam-hall'); ?></p>
            <?php else : ?>
                <div class="eh-table-wrap">
                    <table class="eh-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Exam', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('Status', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('Result', 'exam-hall'); ?></th>
                                <th><?php esc_html_e('When', 'exam-hall'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt) : ?>
                                <tr>
                                    <td><?php echo esc_html($attempt->exam_title); ?></td>
                                    <td><?php echo esc_html(str_replace('_', ' ', $attempt->status)); ?></td>
                                    <td>
                                        <?php if ($attempt->status === 'submitted') : ?>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=eh-results&action=view&attempt_id=' . (int) $attempt->id)); ?>">
                                                <?php echo esc_html(eh_format_percentage($attempt->percentage) . ' · ' . $attempt->classification); ?>
                                            </a>
                                        <?php elseif ($attempt->status === 'code_pending') : ?>
                                            <code class="eh-code"><?php echo esc_html($attempt->start_code); ?></code>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html(eh_format_gmt($attempt->submitted_at ? $attempt->submitted_at : $attempt->created_at)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($attempt_count === 0) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-confirm="<?php esc_attr_e('Delete this student account? This cannot be undone.', 'exam-hall'); ?>">
                <?php wp_nonce_field('eh_delete_student'); ?>
                <input type="hidden" name="action" value="eh_delete_student">
                <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $student->ID); ?>">
                <button class="eh-link-button eh-danger" type="submit"><?php esc_html_e('Delete student', 'exam-hall'); ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
