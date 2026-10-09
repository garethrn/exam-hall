<?php
/**
 * WordPress helpers for Exam Hall.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the current user may manage exams.
 *
 * @return bool
 */
function eh_user_can_manage() {
    return current_user_can('eh_manage_exams') || current_user_can('manage_options');
}

/**
 * Whether the current user is a student who can sit exams.
 *
 * @return bool
 */
function eh_user_can_take() {
    return is_user_logged_in() && current_user_can('eh_take_exams');
}

/**
 * Stop the request unless the user can manage Exam Hall.
 */
function eh_require_manager() {
    if (!eh_user_can_manage()) {
        wp_die(
            esc_html__('You do not have permission to manage Exam Hall.', 'exam-hall'),
            esc_html__('Exam Hall', 'exam-hall'),
            array('response' => 403)
        );
    }
}

/**
 * Verify an admin form nonce after the capability check.
 *
 * @param string $action Nonce action.
 */
function eh_guard($action) {
    eh_require_manager();
    check_admin_referer($action);
}

/**
 * Portal page URL.
 *
 * @param array $args Query arguments.
 * @return string
 */
function eh_portal_url($args = array()) {
    $page_id = (int) get_option('eh_portal_page_id');
    $url = $page_id ? get_permalink($page_id) : home_url('/');
    if (!$url) {
        $url = home_url('/');
    }
    if ($args) {
        $url = add_query_arg($args, $url);
    }
    return $url;
}

/**
 * Redirect inside wp-admin and stop.
 *
 * @param string $page Menu slug.
 * @param array  $args Extra query arguments.
 */
function eh_admin_redirect($page, $args = array()) {
    $args['page'] = $page;
    $url = add_query_arg($args, admin_url('admin.php'));
    if (!headers_sent()) {
        wp_safe_redirect($url);
        exit;
    }
    echo '<script>window.location.replace(' . wp_json_encode($url) . ');</script>';
    echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Continue', 'exam-hall') . '</a></p>';
    exit;
}

/**
 * Read a sanitized text field from POST.
 *
 * @param string $key Field name.
 * @return string
 */
function eh_post_text($key) {
    if (!isset($_POST[$key])) {
        return '';
    }
    return sanitize_text_field(wp_unslash($_POST[$key]));
}

/**
 * Read a sanitized textarea from POST.
 *
 * @param string $key Field name.
 * @return string
 */
function eh_post_textarea($key) {
    if (!isset($_POST[$key])) {
        return '';
    }
    return sanitize_textarea_field(wp_unslash($_POST[$key]));
}

/**
 * Client address used only for short-lived rate limits.
 *
 * @return string
 */
function eh_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0';
}

/**
 * Count an action and report whether it is still allowed.
 *
 * @param string $bucket  Bucket name.
 * @param int    $max     Maximum attempts.
 * @param int    $seconds Window length.
 * @return bool
 */
function eh_rate_limit_hit($bucket, $max, $seconds) {
    $key = 'eh_rl_' . md5($bucket);
    $count = (int) get_transient($key);
    if ($count >= $max) {
        return false;
    }
    set_transient($key, $count + 1, $seconds);
    return true;
}

/**
 * Clear a rate-limit bucket.
 *
 * @param string $bucket Bucket name.
 */
function eh_rate_limit_clear($bucket) {
    delete_transient('eh_rl_' . md5($bucket));
}

/**
 * Store posted form values after a failed admin save.
 *
 * @param array $data Flash payload.
 */
function eh_form_flash_set($data) {
    set_transient('eh_form_flash_' . get_current_user_id(), $data, 2 * MINUTE_IN_SECONDS);
}

/**
 * Read and consume posted form values.
 *
 * @return array|null
 */
function eh_form_flash_get() {
    $key = 'eh_form_flash_' . get_current_user_id();
    $data = get_transient($key);
    if ($data !== false) {
        delete_transient($key);
    }
    return is_array($data) ? $data : null;
}

/**
 * Remember a temporary password long enough to show it once.
 *
 * @param int    $user_id  Student id.
 * @param string $password Temporary password.
 * @param bool   $mailed   Whether email was accepted.
 */
function eh_password_flash_set($user_id, $password, $mailed) {
    set_transient(
        'eh_pw_flash_' . get_current_user_id(),
        array(
            'user_id' => (int) $user_id,
            'password' => (string) $password,
            'mailed' => (bool) $mailed,
        ),
        2 * MINUTE_IN_SECONDS
    );
}

/**
 * Read and consume the one-time password notice.
 *
 * @return array|null
 */
function eh_password_flash_get() {
    $key = 'eh_pw_flash_' . get_current_user_id();
    $data = get_transient($key);
    if ($data !== false) {
        delete_transient($key);
    }
    return is_array($data) ? $data : null;
}

/**
 * Store import row errors for the next screen.
 *
 * @param array  $errors Row errors.
 * @param string $bucket questions or students.
 */
function eh_import_errors_set($errors, $bucket = 'questions') {
    set_transient(eh_import_errors_key($bucket), $errors, 2 * MINUTE_IN_SECONDS);
}

/**
 * Read and consume import row errors.
 *
 * @param string $bucket questions or students.
 * @return array
 */
function eh_import_errors_get($bucket = 'questions') {
    $key = eh_import_errors_key($bucket);
    $data = get_transient($key);
    if ($data !== false) {
        delete_transient($key);
    }
    return is_array($data) ? $data : array();
}

/**
 * Transient key for one import report.
 *
 * @param string $bucket questions or students.
 * @return string
 */
function eh_import_errors_key($bucket) {
    $bucket = $bucket === 'students' ? 'students' : 'questions';
    return 'eh_import_errors_' . $bucket . '_' . get_current_user_id();
}

/**
 * Remember passwords created by a student CSV import.
 *
 * @param array $rows Account rows.
 */
function eh_student_import_set($rows) {
    set_transient('eh_student_import_' . get_current_user_id(), $rows, 15 * MINUTE_IN_SECONDS);
}

/**
 * Read passwords from the latest student CSV import.
 *
 * @return array
 */
function eh_student_import_get() {
    $data = get_transient('eh_student_import_' . get_current_user_id());
    return is_array($data) ? $data : array();
}

/**
 * Portal flash payload, keyed so it is not shared across people.
 *
 * @return string
 */
function eh_portal_flash_key() {
    return 'eh_pflash_' . md5(eh_client_ip() . '|' . get_current_user_id());
}

/**
 * Store a portal flash message.
 *
 * @param array $data Flash payload.
 */
function eh_portal_flash_set($data) {
    set_transient(eh_portal_flash_key(), $data, MINUTE_IN_SECONDS);
}

/**
 * Read and consume a portal flash message.
 *
 * @return array|null
 */
function eh_portal_flash_get() {
    $key = eh_portal_flash_key();
    $data = get_transient($key);
    if ($data !== false) {
        delete_transient($key);
    }
    return is_array($data) ? $data : null;
}

/**
 * Known portal status messages.
 *
 * @param string $code Message code.
 * @return array|null
 */
function eh_portal_message($code) {
    $messages = array(
        'logged_out' => array('info', __('You have signed out.', 'exam-hall')),
        'reset_sent' => array('info', __('If an account exists for that email, a reset link is on its way.', 'exam-hall')),
        'reset_ok' => array('info', __('Your password was updated. Sign in with the new password.', 'exam-hall')),
        'password_changed' => array('info', __('Your password was changed.', 'exam-hall')),
        'code_sent' => array('info', __('A start code was sent to your email address.', 'exam-hall')),
        'mail_failed' => array('error', __('The start code could not be emailed. Contact your exam administrator.', 'exam-hall')),
        'code_invalid' => array('error', __('That start code is not correct.', 'exam-hall')),
        'code_locked' => array('error', __('Too many incorrect codes. Wait 10 minutes and try again.', 'exam-hall')),
        'code_expired' => array('error', __('That start code has expired. Request a new one.', 'exam-hall')),
        'too_many_logins' => array('error', __('Too many sign-in attempts. Wait 15 minutes and try again.', 'exam-hall')),
        'suspended' => array('error', __('This student account is suspended. Contact your exam administrator.', 'exam-hall')),
        'exam_unavailable' => array('error', __('That exam is not available.', 'exam-hall')),
        'resend_wait' => array('error', __('Please wait a minute before requesting another email.', 'exam-hall')),
        'invalid_login' => array('error', __('That email and password do not match an active student account.', 'exam-hall')),
        'password_short' => array('error', __('Use at least 8 characters for the new password.', 'exam-hall')),
        'password_mismatch' => array('error', __('The new passwords do not match.', 'exam-hall')),
        'password_current' => array('error', __('The current password is not correct.', 'exam-hall')),
        'password_same' => array('error', __('Choose a password that is different from the current one.', 'exam-hall')),
        'reset_invalid' => array('error', __('This reset link is invalid or has expired. Request a new one.', 'exam-hall')),
        'no_questions' => array('error', __('This exam has no questions yet.', 'exam-hall')),
    );
    $code = sanitize_key((string) $code);
    return isset($messages[$code]) ? $messages[$code] : null;
}

/**
 * Admin status messages.
 *
 * @param string $code Message code.
 * @return array|null
 */
function eh_admin_message($code) {
    $messages = array(
        'exam_saved' => array('success', __('Exam saved.', 'exam-hall')),
        'exam_deleted' => array('success', __('Exam deleted.', 'exam-hall')),
        'exam_has_attempts' => array('error', __('This exam already has student activity, so it cannot be deleted. Close it instead.', 'exam-hall')),
        'question_saved' => array('success', __('Question saved.', 'exam-hall')),
        'question_deleted' => array('success', __('Question deleted.', 'exam-hall')),
        'questions_imported' => array('success', __('Questions imported.', 'exam-hall')),
        'import_failed' => array('error', __('No questions were imported. Check the file and try again.', 'exam-hall')),
        'student_saved' => array('success', __('Student saved.', 'exam-hall')),
        'student_deleted' => array('success', __('Student account deleted.', 'exam-hall')),
        'student_has_attempts' => array('error', __('This student has exam activity and was not deleted. Suspend the account instead.', 'exam-hall')),
        'password_reset' => array('success', __('A new temporary password was created.', 'exam-hall')),
        'settings_saved' => array('success', __('Settings saved.', 'exam-hall')),
        'test_email_sent' => array('success', __('Test email sent. Check the inbox for this account.', 'exam-hall')),
        'test_email_failed' => array('error', __('WordPress could not send the test email. Install an SMTP plugin and try again.', 'exam-hall')),
        'page_created' => array('success', __('Student portal page created.', 'exam-hall')),
        'code_resent' => array('success', __('Start code emailed again.', 'exam-hall')),
        'mail_failed_admin' => array('error', __('The email could not be sent. The start code is still shown below so you can pass it on.', 'exam-hall')),
        'not_found' => array('error', __('That record could not be found.', 'exam-hall')),
        'question_settings_saved' => array('success', __('Question format and marks saved.', 'exam-hall')),
        'students_imported' => array('success', __('Students imported.', 'exam-hall')),
        'students_import_failed' => array('error', __('No students were imported. Check the file and try again.', 'exam-hall')),
        'students_assigned' => array('success', __('Those students are now allocated to the exam. It is limited to the allocated list.', 'exam-hall')),
        'students_unassigned' => array('success', __('Those students were removed from the exam.', 'exam-hall')),
        'assign_none' => array('error', __('Choose an exam and at least one student.', 'exam-hall')),
        'certificate_invalid' => array('error', __('Upload a PNG certificate design, up to 5 MB.', 'exam-hall')),
        'logo_invalid' => array('error', __('Upload a PNG, JPG, or WebP logo, up to 2 MB.', 'exam-hall')),
    );
    $code = sanitize_key((string) $code);
    return isset($messages[$code]) ? $messages[$code] : null;
}

/**
 * Logo and company name shown on the portal and dashboard.
 *
 * @return array
 */
function eh_brand() {
    $logo_id = (int) get_option('eh_logo_id');
    $logo = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
    return array(
        'name' => (string) get_option('eh_brand_name'),
        'logo' => $logo ? $logo : '',
    );
}

/**
 * A passed sitting can download a certificate.
 *
 * @param object $exam    Exam.
 * @param object $attempt Attempt.
 * @return bool
 */
function eh_certificate_available($exam, $attempt) {
    if (!$exam || !$attempt || $attempt->status !== 'submitted') {
        return false;
    }
    if (empty($exam->certificate_enabled)) {
        return false;
    }
    if ((float) $attempt->percentage + 0.0000001 < (float) $exam->band_pass) {
        return false;
    }
    return (int) get_option('eh_certificate_id') > 0 && EH_Certificate::can_render();
}

/**
 * Store an uploaded image in the media library.
 *
 * @param array $file  One $_FILES entry.
 * @param array $mimes Allowed mime types.
 * @return int|WP_Error Attachment id.
 */
function eh_store_image_upload($file, $mimes) {
    if (empty($file['tmp_name']) || !empty($file['error'])) {
        return new WP_Error('eh_upload', __('The file could not be uploaded.', 'exam-hall'));
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $upload = wp_handle_upload(
        $file,
        array(
            'test_form' => false,
            'mimes' => $mimes,
        )
    );
    if (!empty($upload['error'])) {
        return new WP_Error('eh_upload', $upload['error']);
    }
    $attachment_id = wp_insert_attachment(
        array(
            'post_mime_type' => $upload['type'],
            'post_title' => sanitize_file_name(pathinfo($file['name'], PATHINFO_FILENAME)),
            'post_status' => 'inherit',
        ),
        $upload['file']
    );
    if (is_wp_error($attachment_id) || !$attachment_id) {
        return new WP_Error('eh_upload', __('The file could not be stored.', 'exam-hall'));
    }
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
    return (int) $attachment_id;
}

/**
 * Keep only real exam-student ids from a posted list.
 *
 * @param array $ids Raw ids.
 * @return array
 */
function eh_student_ids_from_request($ids) {
    $clean = array();
    foreach ((array) $ids as $id) {
        $id = (int) $id;
        $user = $id ? get_user_by('id', $id) : null;
        if ($user && in_array('exam_student', (array) $user->roles, true)) {
            $clean[] = $id;
        }
    }
    return array_values(array_unique($clean));
}

/**
 * Classification bands from an exam row.
 *
 * @param object $exam Exam.
 * @return array
 */
function eh_bands_from_exam($exam) {
    return array(
        'distinction' => (int) $exam->band_distinction,
        'merit' => (int) $exam->band_merit,
        'pass' => (int) $exam->band_pass,
        'label_distinction' => (string) $exam->label_distinction,
        'label_merit' => (string) $exam->label_merit,
        'label_pass' => (string) $exam->label_pass,
        'label_fail' => (string) $exam->label_fail,
    );
}

/**
 * CSS slug for a classification label.
 *
 * @param string $label Label.
 * @param object $exam  Exam.
 * @return string
 */
function eh_classification_slug($label, $exam) {
    if ($label === $exam->label_distinction) {
        return 'distinction';
    }
    if ($label === $exam->label_merit) {
        return 'merit';
    }
    if ($label === $exam->label_pass) {
        return 'pass';
    }
    return 'fail';
}

/**
 * Format a stored GMT datetime in the site timezone.
 *
 * @param string $gmt    MySQL datetime in GMT.
 * @param string $format Optional PHP date format.
 * @return string
 */
function eh_format_gmt($gmt, $format = '') {
    if (!$gmt) {
        return '—';
    }
    if ($format === '') {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }
    $local = get_date_from_gmt($gmt);
    if (!$local) {
        return '—';
    }
    return mysql2date($format, $local);
}

/**
 * Convert a GMT datetime to a datetime-local input value.
 *
 * @param string $gmt MySQL datetime in GMT.
 * @return string
 */
function eh_gmt_to_local_input($gmt) {
    if (!$gmt) {
        return '';
    }
    $local = get_date_from_gmt($gmt);
    if (!$local) {
        return '';
    }
    return mysql2date('Y-m-d\TH:i', $local);
}

/**
 * Convert a datetime-local value from the site timezone to GMT.
 *
 * @param string $input Local input.
 * @return string|null
 */
function eh_local_input_to_gmt($input) {
    $input = trim((string) $input);
    if ($input === '') {
        return null;
    }
    $input = str_replace('T', ' ', $input);
    if (strlen($input) === 16) {
        $input .= ':00';
    }
    $stamp = strtotime($input);
    if (!$stamp) {
        return null;
    }
    return get_gmt_from_date($input);
}

/**
 * Unix timestamp for a GMT datetime.
 *
 * @param string $gmt MySQL datetime.
 * @return int
 */
function eh_gmt_to_ts($gmt) {
    if (!$gmt) {
        return 0;
    }
    $stamp = strtotime($gmt . ' UTC');
    return $stamp ? (int) $stamp : 0;
}

/**
 * Human duration from a number of minutes.
 *
 * @param int $minutes Minutes.
 * @return string
 */
function eh_duration_label($minutes) {
    $minutes = (int) $minutes;
    if ($minutes < 60) {
        /* translators: %d: number of minutes */
        return sprintf(_n('%d minute', '%d minutes', $minutes, 'exam-hall'), $minutes);
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    if ($rest === 0) {
        /* translators: %d: number of hours */
        return sprintf(_n('%d hour', '%d hours', $hours, 'exam-hall'), $hours);
    }
    /* translators: 1: hours, 2: minutes */
    return sprintf(__('%1$d h %2$d min', 'exam-hall'), $hours, $rest);
}

/**
 * Elapsed time between two GMT datetimes.
 *
 * @param string $start_gmt Start.
 * @param string $end_gmt   End.
 * @return string
 */
function eh_elapsed_label($start_gmt, $end_gmt) {
    $start = eh_gmt_to_ts($start_gmt);
    $end = eh_gmt_to_ts($end_gmt);
    if (!$start || !$end || $end < $start) {
        return '—';
    }
    $diff = $end - $start;
    $minutes = (int) floor($diff / 60);
    $seconds = $diff % 60;
    if ($minutes >= 60) {
        /* translators: 1: hours, 2: minutes */
        return sprintf(__('%1$dh %2$dm', 'exam-hall'), intdiv($minutes, 60), $minutes % 60);
    }
    /* translators: 1: minutes, 2: seconds */
    return sprintf(__('%1$dm %2$ds', 'exam-hall'), $minutes, $seconds);
}

/**
 * Seconds left before an attempt deadline.
 *
 * @param object $attempt Attempt.
 * @return int
 */
function eh_remaining_seconds($attempt) {
    $deadline = eh_gmt_to_ts($attempt->deadline_at);
    if (!$deadline) {
        return 0;
    }
    return max(0, $deadline - time());
}

/**
 * Whether an in-progress attempt is still inside the submit grace period.
 *
 * @param object $attempt Attempt.
 * @param int    $grace   Extra seconds allowed for a slow submit.
 * @return bool
 */
function eh_attempt_within_grace($attempt, $grace = 15) {
    if ($attempt->status !== 'in_progress') {
        return false;
    }
    $deadline = eh_gmt_to_ts($attempt->deadline_at);
    if (!$deadline) {
        return false;
    }
    return time() <= ($deadline + (int) $grace);
}

/**
 * Build a unique WordPress username from an email address.
 *
 * @param string $email Email.
 * @return string
 */
function eh_unique_login($email) {
    $local = strstr((string) $email, '@', true);
    $base = sanitize_user((string) $local, true);
    if ($base === '') {
        $base = 'student';
    }
    $base = substr($base, 0, 40);
    $login = $base;
    $suffix = 1;
    while (username_exists($login)) {
        $suffix++;
        $login = $base . $suffix;
    }
    return $login;
}

/**
 * Decide what a student can do with an exam right now.
 *
 * Optional context keys: questions (int), active (object|null), submitted (int).
 *
 * @param object $exam    Exam.
 * @param int    $user_id Student id.
 * @param array  $context Preloaded counts.
 * @return array
 */
function eh_exam_access($exam, $user_id, $context = array()) {
    $questions = isset($context['questions']) ? (int) $context['questions'] : EH_Repository::count_questions($exam->id);
    if (array_key_exists('active', $context)) {
        $active = $context['active'];
    } else {
        $active = EH_Repository::get_active_attempt($exam->id, $user_id);
    }
    $submitted = isset($context['submitted']) ? (int) $context['submitted'] : EH_Repository::count_submitted($exam->id, $user_id);
    $now = time();
    $from = eh_gmt_to_ts($exam->available_from);
    $until = eh_gmt_to_ts($exam->available_until);
    $max = (int) $exam->allow_retake === 1 ? (int) $exam->max_attempts : 1;

    $access = array(
        'questions' => $questions,
        'submitted' => $submitted,
        'max' => $max,
        'active' => $active,
        'can_start' => false,
        'can_continue' => false,
        'can_enter_code' => false,
        'reason' => '',
    );

    if ($active && $active->status === 'in_progress') {
        $access['can_continue'] = true;
        $access['reason'] = 'in_progress';
        return $access;
    }
    if ($exam->status !== 'published') {
        $access['reason'] = $exam->status === 'closed' ? 'closed' : 'draft';
        return $access;
    }
    if ($from && $now < $from) {
        $access['reason'] = 'upcoming';
        return $access;
    }
    if ($until && $now > $until) {
        $access['reason'] = 'ended';
        return $access;
    }
    $audience = isset($exam->audience) ? $exam->audience : 'all';
    $allocated = $audience !== 'assigned' || EH_Repository::is_assigned($exam->id, $user_id);
    if (!$allocated && !($active && $active->status === 'code_pending')) {
        $access['reason'] = 'not_assigned';
        return $access;
    }
    if ($active && $active->status === 'code_pending') {
        $access['can_enter_code'] = true;
        $access['reason'] = 'code_pending';
        return $access;
    }
    if ($questions < 1) {
        $access['reason'] = 'no_questions';
        return $access;
    }
    if ($submitted >= $max) {
        $access['reason'] = 'maxed';
        return $access;
    }
    $access['can_start'] = true;
    $access['reason'] = $submitted > 0 ? 'retake' : 'ready';
    return $access;
}

/**
 * Persist selected answers that belong to the attempt's paper.
 *
 * @param object $attempt    Attempt.
 * @param array  $selections Question id => letter.
 */
function eh_save_selections($attempt, array $selections) {
    $questions = EH_Repository::questions_for_attempt($attempt);
    $allowed = array();
    foreach ($questions as $question) {
        $allowed[(int) $question->id] = eh_question_options($question);
    }
    foreach ($selections as $question_id => $choice) {
        $question_id = (int) $question_id;
        if (!isset($allowed[$question_id])) {
            continue;
        }
        $letter = eh_normalize_choice($choice);
        if ($letter === '' || !isset($allowed[$question_id][$letter])) {
            continue;
        }
        EH_Repository::save_selection($attempt->id, $question_id, $letter);
    }
}

/**
 * Mark an in-progress attempt and store the result.
 *
 * @param object $attempt Attempt row.
 * @param bool   $auto    True when time ran out.
 * @return object Updated attempt.
 */
function eh_finalize_attempt($attempt, $auto = false) {
    if (!$attempt || $attempt->status === 'submitted') {
        return $attempt;
    }
    $exam = EH_Repository::get_exam($attempt->exam_id);
    $questions = EH_Repository::questions_for_attempt($attempt);
    $grade_questions = array();
    foreach ($questions as $question) {
        $grade_questions[] = array(
            'id' => (int) $question->id,
            'correct' => $question->correct_answer,
            'marks' => $question->marks,
        );
    }
    $graded = eh_grade($grade_questions, EH_Repository::selection_map($attempt->id));
    $classification = $exam ? eh_classify($graded['percentage'], eh_bands_from_exam($exam)) : '';
    EH_Repository::complete_attempt($attempt->id, $graded, $classification, $auto);
    $fresh = EH_Repository::get_attempt($attempt->id);
    return $fresh ? $fresh : $attempt;
}

/**
 * Close any sitting whose timer has already run out.
 *
 * @param int $user_id Student id. Zero closes every overdue sitting.
 */
function eh_expire_overdue($user_id = 0) {
    $rows = EH_Repository::overdue_attempts($user_id);
    foreach ($rows as $attempt) {
        eh_finalize_attempt($attempt, true);
    }
}

/**
 * Snapshot the live paper so a sitting stays stable.
 *
 * @param array $questions Question rows.
 * @return string
 */
function eh_snapshot_questions($questions) {
    $snapshot = array();
    foreach ($questions as $question) {
        $snapshot[] = array(
            'id' => (int) $question->id,
            'question_text' => (string) $question->question_text,
            'option_a' => (string) $question->option_a,
            'option_b' => (string) $question->option_b,
            'option_c' => (string) $question->option_c,
            'option_d' => (string) $question->option_d,
            'correct_answer' => (string) $question->correct_answer,
            'marks' => (float) $question->marks,
        );
    }
    $json = wp_json_encode($snapshot);
    return is_string($json) ? $json : '';
}

/**
 * Render a pager.
 *
 * @param int    $total    Total rows.
 * @param int    $per_page Page size.
 * @param int    $paged    Current page.
 * @param string $base_url URL without the page argument.
 */
function eh_pagination($total, $per_page, $paged, $base_url) {
    $pages = (int) ceil($total / max(1, $per_page));
    if ($pages <= 1) {
        return;
    }
    echo '<nav class="eh-pager" aria-label="' . esc_attr__('Pages', 'exam-hall') . '">';
    $start = max(1, $paged - 2);
    $end = min($pages, $paged + 2);
    if ($paged > 1) {
        echo '<a href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '">' . esc_html__('Previous', 'exam-hall') . '</a>';
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === (int) $paged) {
            echo '<span aria-current="page">' . esc_html((string) $i) . '</span>';
        } else {
            echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html((string) $i) . '</a>';
        }
    }
    if ($paged < $pages) {
        echo '<a href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '">' . esc_html__('Next', 'exam-hall') . '</a>';
    }
    echo '</nav>';
}
