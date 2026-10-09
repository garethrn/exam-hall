<?php
/**
 * Create or edit an exam.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

$values = array(
    'title' => $exam ? $exam->title : '',
    'description' => $exam ? $exam->description : '',
    'instructions' => $exam ? $exam->instructions : '',
    'duration_minutes' => $exam ? (int) $exam->duration_minutes : 60,
    'allow_retake' => $exam ? (string) (int) $exam->allow_retake : '1',
    'max_attempts' => $exam ? (int) $exam->max_attempts : 2,
    'code_expiry_minutes' => $exam ? (int) $exam->code_expiry_minutes : 30,
    'show_correct' => $exam ? (string) (int) $exam->show_correct : '1',
    'band_distinction' => $exam ? (int) $exam->band_distinction : 80,
    'band_merit' => $exam ? (int) $exam->band_merit : 70,
    'band_pass' => $exam ? (int) $exam->band_pass : 50,
    'label_distinction' => $exam ? $exam->label_distinction : __('Distinction', 'exam-hall'),
    'label_merit' => $exam ? $exam->label_merit : __('Merit', 'exam-hall'),
    'label_pass' => $exam ? $exam->label_pass : __('Pass', 'exam-hall'),
    'label_fail' => $exam ? $exam->label_fail : __('Fail', 'exam-hall'),
    'status' => $exam ? $exam->status : 'draft',
    'audience' => $exam && isset($exam->audience) ? $exam->audience : 'all',
    'certificate_enabled' => $exam && !empty($exam->certificate_enabled) ? '1' : '0',
    'question_type' => $exam ? eh_exam_question_type($exam) : 'multiple_choice',
    'default_marks' => $exam ? eh_format_marks(eh_exam_default_marks($exam)) : '1',
    'available_from' => $exam ? eh_gmt_to_local_input($exam->available_from) : '',
    'available_until' => $exam ? eh_gmt_to_local_input($exam->available_until) : '',
);
if (is_array($flash) && !empty($flash['values']) && is_array($flash['values'])) {
    $values = array_merge($values, $flash['values']);
}
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><a href="<?php echo esc_url(admin_url('admin.php?page=eh-exams')); ?>"><?php esc_html_e('Exams', 'exam-hall'); ?></a></p>
            <h1><?php echo $exam ? esc_html($exam->title) : esc_html__('New exam', 'exam-hall'); ?></h1>
        </div>
        <?php if ($exam) : ?>
            <div class="eh-hero-actions">
                <a class="eh-btn" href="<?php echo esc_url(admin_url('admin.php?page=eh-questions&exam_id=' . (int) $exam->id)); ?>"><?php esc_html_e('Questions', 'exam-hall'); ?></a>
            </div>
        <?php endif; ?>
    </header>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eh-exam-layout">
        <?php wp_nonce_field('eh_save_exam'); ?>
        <input type="hidden" name="action" value="eh_save_exam">
        <input type="hidden" name="exam_id" value="<?php echo esc_attr($exam ? (string) $exam->id : '0'); ?>">

        <div class="eh-panel">
            <label class="eh-field">
                <span><?php esc_html_e('Title', 'exam-hall'); ?></span>
                <input type="text" name="title" required maxlength="180" value="<?php echo esc_attr($values['title']); ?>">
            </label>
            <label class="eh-field">
                <span><?php esc_html_e('Description', 'exam-hall'); ?></span>
                <textarea name="description" rows="3"><?php echo esc_textarea($values['description']); ?></textarea>
            </label>
            <label class="eh-field">
                <span><?php esc_html_e('Instructions shown before the start code', 'exam-hall'); ?></span>
                <textarea name="instructions" rows="4"><?php echo esc_textarea($values['instructions']); ?></textarea>
            </label>
        </div>

        <div class="eh-stack">
            <div class="eh-panel">
                <h2><?php esc_html_e('Timing', 'exam-hall'); ?></h2>
                <label class="eh-field">
                    <span><?php esc_html_e('Duration in minutes', 'exam-hall'); ?></span>
                    <input type="number" name="duration_minutes" min="1" max="360" required value="<?php echo esc_attr((string) $values['duration_minutes']); ?>">
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Start code expires after (minutes)', 'exam-hall'); ?></span>
                    <input type="number" name="code_expiry_minutes" min="5" max="240" required value="<?php echo esc_attr((string) $values['code_expiry_minutes']); ?>">
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Opens', 'exam-hall'); ?></span>
                    <input type="datetime-local" name="available_from" value="<?php echo esc_attr($values['available_from']); ?>">
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Closes', 'exam-hall'); ?></span>
                    <input type="datetime-local" name="available_until" value="<?php echo esc_attr($values['available_until']); ?>">
                </label>
                <p class="eh-note"><?php esc_html_e('Leave the dates empty to keep the exam open whenever it is published.', 'exam-hall'); ?></p>
            </div>

            <div class="eh-panel">
                <h2><?php esc_html_e('Classification', 'exam-hall'); ?></h2>
                <div class="eh-band" data-band>
                    <label><?php esc_html_e('Distinction from', 'exam-hall'); ?> <input id="eh-band-d" type="number" name="band_distinction" min="1" max="100" value="<?php echo esc_attr((string) $values['band_distinction']); ?>"> %</label>
                    <input type="text" name="label_distinction" maxlength="40" value="<?php echo esc_attr($values['label_distinction']); ?>">
                </div>
                <div class="eh-band">
                    <label><?php esc_html_e('Merit from', 'exam-hall'); ?> <input id="eh-band-m" type="number" name="band_merit" min="0" max="99" value="<?php echo esc_attr((string) $values['band_merit']); ?>"> %</label>
                    <input type="text" name="label_merit" maxlength="40" value="<?php echo esc_attr($values['label_merit']); ?>">
                </div>
                <div class="eh-band">
                    <label><?php esc_html_e('Pass from', 'exam-hall'); ?> <input id="eh-band-p" type="number" name="band_pass" min="0" max="98" value="<?php echo esc_attr((string) $values['band_pass']); ?>"> %</label>
                    <input type="text" name="label_pass" maxlength="40" value="<?php echo esc_attr($values['label_pass']); ?>">
                </div>
                <div class="eh-band">
                    <label><?php esc_html_e('Below the pass mark', 'exam-hall'); ?></label>
                    <input type="text" name="label_fail" maxlength="40" value="<?php echo esc_attr($values['label_fail']); ?>">
                </div>
                <p class="eh-note" id="eh-band-legend"></p>
            </div>

            <div class="eh-panel">
                <h2><?php esc_html_e('Questions', 'exam-hall'); ?></h2>
                <label class="eh-field">
                    <span><?php esc_html_e('Question format', 'exam-hall'); ?></span>
                    <select name="question_type">
                        <option value="multiple_choice" <?php selected($values['question_type'], 'multiple_choice'); ?>><?php esc_html_e('Multiple choice', 'exam-hall'); ?></option>
                        <option value="true_false" <?php selected($values['question_type'], 'true_false'); ?>><?php esc_html_e('True or false', 'exam-hall'); ?></option>
                    </select>
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Marks for each question', 'exam-hall'); ?></span>
                    <input type="number" name="default_marks" min="0.25" max="100" step="0.25" required value="<?php echo esc_attr((string) $values['default_marks']); ?>">
                </label>
                <p class="eh-note"><?php esc_html_e('The CSV template matches this format. A marks cell in the file overrides the number here. Leaving marks blank uses this number.', 'exam-hall'); ?></p>
            </div>

            <div class="eh-panel">
                <h2><?php esc_html_e('Attempts', 'exam-hall'); ?></h2>
                <label class="eh-check">
                    <input type="checkbox" name="allow_retake" value="1" <?php checked($values['allow_retake'], '1'); ?>>
                    <span><?php esc_html_e('Allow students to retake this exam', 'exam-hall'); ?></span>
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Maximum attempts, including the first', 'exam-hall'); ?></span>
                    <input type="number" name="max_attempts" min="1" max="20" value="<?php echo esc_attr((string) $values['max_attempts']); ?>">
                </label>
                <label class="eh-check">
                    <input type="checkbox" name="show_correct" value="1" <?php checked($values['show_correct'], '1'); ?>>
                    <span><?php esc_html_e('Show the correct answers after submission', 'exam-hall'); ?></span>
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Who can open this exam', 'exam-hall'); ?></span>
                    <select name="audience">
                        <option value="all" <?php selected($values['audience'], 'all'); ?>><?php esc_html_e('Every student', 'exam-hall'); ?></option>
                        <option value="assigned" <?php selected($values['audience'], 'assigned'); ?>><?php esc_html_e('Only allocated students', 'exam-hall'); ?></option>
                    </select>
                </label>
                <label class="eh-check">
                    <input type="checkbox" name="certificate_enabled" value="1" <?php checked($values['certificate_enabled'], '1'); ?>>
                    <span><?php esc_html_e('Issue a certificate when the student reaches the pass mark', 'exam-hall'); ?></span>
                </label>
                <label class="eh-field">
                    <span><?php esc_html_e('Status', 'exam-hall'); ?></span>
                    <select name="status">
                        <option value="draft" <?php selected($values['status'], 'draft'); ?>><?php esc_html_e('Draft — hidden from students', 'exam-hall'); ?></option>
                        <option value="published" <?php selected($values['status'], 'published'); ?>><?php esc_html_e('Published', 'exam-hall'); ?></option>
                        <option value="closed" <?php selected($values['status'], 'closed'); ?>><?php esc_html_e('Closed', 'exam-hall'); ?></option>
                    </select>
                </label>
                <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Save exam', 'exam-hall'); ?></button>
            </div>
        </div>
        <section class="eh-panel eh-assign">
            <h2><?php esc_html_e('Allocated students', 'exam-hall'); ?></h2>
            <p class="eh-note"><?php esc_html_e('These students can open the exam when access is set to only allocated students. Saving replaces the list for this exam.', 'exam-hall'); ?></p>
            <input type="hidden" name="assign_present" value="1">
            <?php if (empty($students['users'])) : ?>
                <p class="eh-empty"><?php esc_html_e('Add a student before allocating this exam.', 'exam-hall'); ?></p>
            <?php else : ?>
                <label class="eh-field">
                    <span><?php esc_html_e('Find a student', 'exam-hall'); ?></span>
                    <input type="search" id="eh-assign-filter" placeholder="<?php esc_attr_e('Name or email', 'exam-hall'); ?>">
                </label>
                <label class="eh-check">
                    <input type="checkbox" id="eh-assign-all">
                    <span><?php esc_html_e('Select every student shown', 'exam-hall'); ?></span>
                </label>
                <div class="eh-assign-list">
                    <?php foreach ($students['users'] as $student) : ?>
                        <label class="eh-check" data-student="<?php echo esc_attr(strtolower($student->display_name . ' ' . $student->user_email)); ?>">
                            <input type="checkbox" name="student_ids[]" value="<?php echo esc_attr((string) $student->ID); ?>" <?php checked(in_array((int) $student->ID, $assigned_ids, true)); ?>>
                            <span><?php echo esc_html($student->display_name); ?> <small><?php echo esc_html($student->user_email); ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </form>
</div>
