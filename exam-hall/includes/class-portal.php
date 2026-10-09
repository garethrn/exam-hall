<?php
/**
 * Student-facing exam portal.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the portal as its own page, independent of the theme.
 */
class EH_Portal {

    /**
     * Register the front-end takeover and shortcode.
     */
    public static function hooks() {
        add_action('template_redirect', array(__CLASS__, 'maybe_render'), 0);
        add_shortcode('exam_hall', array(__CLASS__, 'shortcode'));
    }

    /**
     * Link people to the real portal page if the shortcode is used elsewhere.
     *
     * @return string
     */
    public static function shortcode() {
        $url = eh_portal_url();
        return '<p><a href="' . esc_url($url) . '">' . esc_html__('Open Exam Hall', 'exam-hall') . '</a></p>';
    }

    /**
     * Replace the selected portal page with the exam application.
     */
    public static function maybe_render() {
        $page_id = (int) get_option('eh_portal_page_id');
        if (!$page_id || !is_page($page_id)) {
            return;
        }
        if (isset($_GET['eh_certificate'])) {
            self::send_certificate();
        }
        nocache_headers();
        if (isset($_GET['eh_logout'])) {
            self::logout();
        }
        if (is_user_logged_in() && eh_user_can_take() && get_user_meta(get_current_user_id(), 'eh_status', true) === 'suspended') {
            wp_logout();
            self::redirect(array('eh_msg' => 'suspended'));
        }
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eh_action'])) {
            self::handle_post();
        }
        if (is_user_logged_in() && (eh_user_can_take() || eh_user_can_manage())) {
            eh_expire_overdue(get_current_user_id());
            self::render_authenticated();
        }
        if (is_user_logged_in()) {
            self::screen('denied', array());
        }
        self::render_guest();
    }

    /**
     * Sign-in, password reset, or the code gate for visitors.
     */
    private static function render_guest() {
        $action = isset($_GET['eh_action']) ? sanitize_key(wp_unslash($_GET['eh_action'])) : 'login';
        if ($action === 'forgot') {
            self::screen('forgot', array());
        }
        if ($action === 'reset') {
            self::render_reset();
        }
        self::screen('login', array());
    }

    /**
     * Screens for a signed-in student or a previewing administrator.
     */
    private static function render_authenticated() {
        $action = isset($_GET['eh_action']) ? sanitize_key(wp_unslash($_GET['eh_action'])) : '';
        if ($action === 'reset') {
            self::render_reset();
        }
        $view = isset($_GET['eh_view']) ? sanitize_key(wp_unslash($_GET['eh_view'])) : 'dashboard';
        if ($view === 'account') {
            self::render_account();
        }
        if ($view === 'gate') {
            self::render_gate();
        }
        if ($view === 'exam') {
            self::render_exam();
        }
        if ($view === 'result') {
            self::render_result();
        }
        self::render_dashboard();
    }

    /**
     * Student home.
     */
    private static function render_dashboard() {
        $user_id = get_current_user_id();
        $exams = eh_user_can_take() ? EH_Repository::exams_for_student($user_id) : EH_Repository::list_exams('published');
        $ids = array();
        foreach ($exams as $exam) {
            $ids[] = (int) $exam->id;
        }
        $question_counts = EH_Repository::question_counts_for($ids);
        $active = eh_user_can_take() ? EH_Repository::active_attempts_for_user($user_id) : array();
        $submitted = eh_user_can_take() ? EH_Repository::submitted_counts_for_user($user_id) : array();
        $history = eh_user_can_take() ? EH_Repository::attempts_for_user($user_id, 40) : array();
        $latest = array();
        foreach ($history as $attempt) {
            if ($attempt->status === 'submitted' && !isset($latest[(int) $attempt->exam_id])) {
                $latest[(int) $attempt->exam_id] = $attempt;
            }
        }
        $cards = array();
        foreach ($exams as $exam) {
            $cards[] = array(
                'exam' => $exam,
                'access' => eh_exam_access(
                    $exam,
                    $user_id,
                    array(
                        'questions' => isset($question_counts[(int) $exam->id]) ? $question_counts[(int) $exam->id] : 0,
                        'active' => isset($active[(int) $exam->id]) ? $active[(int) $exam->id] : null,
                        'submitted' => isset($submitted[(int) $exam->id]) ? $submitted[(int) $exam->id] : 0,
                    )
                ),
                'last' => isset($latest[(int) $exam->id]) ? $latest[(int) $exam->id] : null,
            );
        }
        $results = array();
        foreach ($history as $attempt) {
            if ($attempt->status === 'submitted') {
                $results[] = $attempt;
            }
        }
        self::screen(
            'dashboard',
            array(
                'cards' => $cards,
                'results' => $results,
                'can_take' => eh_user_can_take(),
            )
        );
    }

    /**
     * Account details and password change.
     */
    private static function render_account() {
        $user = wp_get_current_user();
        self::screen(
            'account',
            array(
                'user' => $user,
                'student_number' => (string) get_user_meta($user->ID, 'eh_student_number', true),
                'phone' => (string) get_user_meta($user->ID, 'eh_phone', true),
                'programme' => (string) get_user_meta($user->ID, 'eh_programme', true),
                'can_take' => eh_user_can_take(),
            )
        );
    }

    /**
     * Enter the emailed start code.
     */
    private static function render_gate() {
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        $attempt = ($exam && eh_user_can_take()) ? EH_Repository::get_active_attempt($exam->id, get_current_user_id()) : null;
        if (!$exam || !$attempt || $attempt->status !== 'code_pending') {
            if ($attempt && $attempt->status === 'in_progress') {
                self::redirect(array('eh_view' => 'exam', 'attempt_id' => (int) $attempt->id));
            }
            self::redirect(array());
        }
        self::screen(
            'gate',
            array(
                'exam' => $exam,
                'attempt' => $attempt,
                'questions' => EH_Repository::count_questions($exam->id),
                'expired' => eh_gmt_to_ts($attempt->code_expires_at) < time(),
                'locked' => eh_gmt_to_ts($attempt->code_locked_until) > time(),
            )
        );
    }

    /**
     * The timed paper.
     */
    private static function render_exam() {
        $attempt_id = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;
        $attempt = EH_Repository::get_attempt($attempt_id);
        if (!$attempt || (int) $attempt->user_id !== get_current_user_id() || !eh_user_can_take()) {
            self::redirect(array());
        }
        if ($attempt->status === 'submitted') {
            self::redirect(array('eh_view' => 'result', 'attempt_id' => (int) $attempt->id));
        }
        if ($attempt->status !== 'in_progress') {
            self::redirect(array('eh_view' => 'gate', 'exam_id' => (int) $attempt->exam_id));
        }
        if (!eh_attempt_within_grace($attempt, 0) && eh_remaining_seconds($attempt) <= 0) {
            eh_finalize_attempt($attempt, true);
            self::redirect(array('eh_view' => 'result', 'attempt_id' => (int) $attempt->id));
        }
        $exam = EH_Repository::get_exam($attempt->exam_id);
        $questions = EH_Repository::questions_for_attempt($attempt);
        self::screen(
            'runner',
            array(
                'exam' => $exam,
                'attempt' => $attempt,
                'questions' => $questions,
                'selections' => EH_Repository::selection_map($attempt->id),
                'answered' => EH_Repository::count_saved_answers($attempt->id),
                'remaining' => eh_remaining_seconds($attempt),
                'exam_nonce' => wp_create_nonce('eh_exam_' . (int) $attempt->id),
            )
        );
    }

    /**
     * Percentage, classification, and question review.
     */
    private static function render_result() {
        $attempt_id = isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0;
        $attempt = EH_Repository::get_attempt($attempt_id);
        $owns = $attempt && (int) $attempt->user_id === get_current_user_id();
        if (!$attempt || (!$owns && !eh_user_can_manage())) {
            self::redirect(array());
        }
        if ($attempt->status !== 'submitted') {
            if ($owns && $attempt->status === 'in_progress') {
                self::redirect(array('eh_view' => 'exam', 'attempt_id' => (int) $attempt->id));
            }
            self::redirect(array());
        }
        $exam = EH_Repository::get_exam($attempt->exam_id);
        if (!$exam) {
            self::redirect(array());
        }
        $access = eh_exam_access($exam, (int) $attempt->user_id);
        self::screen(
            'result',
            array(
                'exam' => $exam,
                'attempt' => $attempt,
                'questions' => EH_Repository::questions_for_attempt($attempt),
                'answers' => EH_Repository::answer_rows($attempt->id),
                'access' => $access,
                'can_take' => $owns && eh_user_can_take(),
                'passed' => (float) $attempt->percentage + 0.0000001 >= (float) $exam->band_pass,
            )
        );
    }

    /**
     * Password reset screen.
     */
    private static function render_reset() {
        $login = isset($_GET['login']) ? wp_unslash($_GET['login']) : '';
        $key = isset($_GET['key']) ? wp_unslash($_GET['key']) : '';
        $user = ($login !== '' && $key !== '') ? check_password_reset_key($key, $login) : new WP_Error('invalid_key', 'invalid');
        self::screen(
            'reset',
            array(
                'login' => $login,
                'key' => $key,
                'valid' => $user instanceof WP_User,
            )
        );
    }

    /**
     * Dispatch a portal form.
     */
    private static function handle_post() {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'eh_portal')) {
            eh_portal_flash_set(array('error' => __('The form expired. Reload the page and try again.', 'exam-hall')));
            $target = wp_get_referer();
            wp_safe_redirect($target ? wp_validate_redirect($target, eh_portal_url()) : eh_portal_url());
            exit;
        }
        $action = sanitize_key(wp_unslash($_POST['eh_action']));
        switch ($action) {
            case 'login':
                self::login();
                break;
            case 'forgot':
                self::forgot();
                break;
            case 'reset':
                self::reset_password();
                break;
            case 'request_code':
                self::request_code();
                break;
            case 'resend_code':
                self::resend_code();
                break;
            case 'verify_code':
                self::verify_code();
                break;
            case 'change_password':
                self::change_password();
                break;
            case 'submit_exam':
                self::submit_exam();
                break;
        }
    }

    /**
     * Sign a student in with their email address.
     */
    private static function login() {
        $email = sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : ''));
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        if (!empty($_POST['eh_website'])) {
            self::fail_login($email);
        }
        if (!eh_rate_limit_hit('login_' . eh_client_ip(), 8, 15 * MINUTE_IN_SECONDS)) {
            eh_portal_flash_set(array('email' => $email));
            self::redirect(array('eh_msg' => 'too_many_logins'));
        }
        $user = get_user_by('email', $email);
        if (!$user || !in_array('exam_student', (array) $user->roles, true)) {
            self::fail_login($email);
        }
        $signed = wp_signon(
            array(
                'user_login' => $user->user_login,
                'user_password' => $password,
                'remember' => !empty($_POST['remember']),
            ),
            is_ssl()
        );
        if (is_wp_error($signed)) {
            eh_portal_flash_set(array('email' => $email));
            self::redirect(array('eh_msg' => $signed->get_error_code() === 'eh_suspended' ? 'suspended' : 'invalid_login'));
        }
        eh_rate_limit_clear('login_' . eh_client_ip());
        self::redirect(array());
    }

    /**
     * Same response whether or not the email exists.
     *
     * @param string $email Email kept for the form.
     */
    private static function fail_login($email) {
        eh_portal_flash_set(array('email' => $email));
        self::redirect(array('eh_msg' => 'invalid_login'));
    }

    /**
     * Email a password reset link.
     */
    private static function forgot() {
        $email = sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : ''));
        if (eh_rate_limit_hit('forgot_' . eh_client_ip(), 5, 15 * MINUTE_IN_SECONDS)) {
            $user = get_user_by('email', $email);
            if ($user && in_array('exam_student', (array) $user->roles, true) && get_user_meta($user->ID, 'eh_status', true) !== 'suspended') {
                $key = get_password_reset_key($user);
                if (!is_wp_error($key)) {
                    $url = eh_portal_url(
                        array(
                            'eh_action' => 'reset',
                            'key' => $key,
                            'login' => $user->user_login,
                        )
                    );
                    $sent = EH_Emails::forgot_password($user, $url);
                    if (!$sent) {
                        error_log('Exam Hall could not email a password reset for user ' . (int) $user->ID);
                    }
                }
            }
        }
        self::redirect(array('eh_action' => 'forgot', 'eh_msg' => 'reset_sent'));
    }

    /**
     * Store a password chosen from a reset link.
     */
    private static function reset_password() {
        $login = isset($_POST['login']) ? wp_unslash($_POST['login']) : '';
        $key = isset($_POST['key']) ? wp_unslash($_POST['key']) : '';
        $password = isset($_POST['password']) ? (string) wp_unslash($_POST['password']) : '';
        $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';
        $back = array(
            'eh_action' => 'reset',
            'key' => $key,
            'login' => $login,
        );
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            self::redirect(array('eh_action' => 'forgot', 'eh_msg' => 'reset_invalid'));
        }
        if (strlen($password) < 8) {
            self::redirect($back + array('eh_msg' => 'password_short'));
        }
        if ($password !== $confirm) {
            self::redirect($back + array('eh_msg' => 'password_mismatch'));
        }
        reset_password($user, $password);
        self::redirect(array('eh_msg' => 'reset_ok'));
    }

    /**
     * Create or refresh a start code and email it.
     */
    private static function request_code() {
        self::require_student();
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        if (!$exam) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        $user_id = get_current_user_id();
        $access = eh_exam_access($exam, $user_id);
        if ($access['can_enter_code'] && $access['active']) {
            if (!eh_rate_limit_hit('code_req_' . $user_id, 8, 10 * MINUTE_IN_SECONDS)) {
                self::redirect(array('eh_view' => 'gate', 'exam_id' => $exam_id, 'eh_msg' => 'resend_wait'));
            }
            self::deliver_code($exam, $access['active'], false);
        }
        if (!$access['can_start']) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        if (!eh_rate_limit_hit('code_req_' . $user_id, 8, 10 * MINUTE_IN_SECONDS)) {
            self::redirect(array('eh_msg' => 'resend_wait'));
        }
        $lock_key = 'eh_lock_' . $user_id . '_' . $exam_id;
        if (get_transient($lock_key)) {
            $existing = EH_Repository::get_active_attempt($exam_id, $user_id);
            if ($existing) {
                self::deliver_code($exam, $existing, false);
            }
        }
        set_transient($lock_key, 1, 20);
        $existing = EH_Repository::get_active_attempt($exam_id, $user_id);
        if ($existing) {
            delete_transient($lock_key);
            self::deliver_code($exam, $existing, false);
        }
        $expires = gmdate('Y-m-d H:i:s', time() + ((int) $exam->code_expiry_minutes * MINUTE_IN_SECONDS));
        $attempt_id = EH_Repository::insert_attempt(
            array(
                'exam_id' => (int) $exam->id,
                'user_id' => $user_id,
                'start_code' => eh_generate_start_code(),
                'code_expires_at' => $expires,
            )
        );
        delete_transient($lock_key);
        if (is_wp_error($attempt_id)) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        self::deliver_code($exam, EH_Repository::get_attempt($attempt_id), false);
    }

    /**
     * Email the current code again, or replace it if it expired.
     */
    private static function resend_code() {
        self::require_student();
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        $attempt = $exam ? EH_Repository::get_active_attempt($exam->id, get_current_user_id()) : null;
        if (!$exam || !$attempt || $attempt->status !== 'code_pending') {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        if (get_transient('eh_resend_' . (int) $attempt->id)) {
            self::redirect(array('eh_view' => 'gate', 'exam_id' => $exam_id, 'eh_msg' => 'resend_wait'));
        }
        set_transient('eh_resend_' . (int) $attempt->id, 1, MINUTE_IN_SECONDS);
        self::deliver_code($exam, $attempt, false);
    }

    /**
     * Check the start code and begin the timer.
     */
    private static function verify_code() {
        self::require_student();
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        $user_id = get_current_user_id();
        $attempt = $exam ? EH_Repository::get_active_attempt($exam_id, $user_id) : null;
        if ($attempt && $attempt->status === 'in_progress') {
            self::redirect(array('eh_view' => 'exam', 'attempt_id' => (int) $attempt->id));
        }
        if (!$exam || !$attempt || $attempt->status !== 'code_pending') {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        $gate = array(
            'eh_view' => 'gate',
            'exam_id' => $exam_id,
        );
        $now = time();
        $from = eh_gmt_to_ts($exam->available_from);
        $until = eh_gmt_to_ts($exam->available_until);
        if ($exam->status !== 'published' || ($from && $now < $from) || ($until && $now > $until)) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        if (eh_gmt_to_ts($attempt->code_locked_until) > $now) {
            self::redirect($gate + array('eh_msg' => 'code_locked'));
        }
        if (eh_gmt_to_ts($attempt->code_expires_at) < $now) {
            self::redirect($gate + array('eh_msg' => 'code_expired'));
        }
        $code = preg_replace('/\s+/', '', eh_post_text('start_code'));
        if (!hash_equals((string) $attempt->start_code, (string) $code)) {
            $failures = (int) $attempt->code_failures + 1;
            $update = array('code_failures' => $failures);
            $message = 'code_invalid';
            if ($failures >= 5) {
                $update['code_failures'] = 0;
                $update['code_locked_until'] = gmdate('Y-m-d H:i:s', $now + (10 * MINUTE_IN_SECONDS));
                $message = 'code_locked';
            }
            EH_Repository::update_attempt($attempt->id, $update);
            self::redirect($gate + array('eh_msg' => $message));
        }
        $questions = EH_Repository::get_questions($exam->id);
        $snapshot = eh_snapshot_questions($questions);
        if (!$questions || $snapshot === '') {
            self::redirect($gate + array('eh_msg' => 'no_questions'));
        }
        $ids = array();
        foreach ($questions as $question) {
            $ids[] = (int) $question->id;
        }
        EH_Repository::update_attempt(
            $attempt->id,
            array(
                'status' => 'in_progress',
                'started_at' => gmdate('Y-m-d H:i:s', $now),
                'deadline_at' => gmdate('Y-m-d H:i:s', $now + ((int) $exam->duration_minutes * MINUTE_IN_SECONDS)),
                'question_ids' => implode(',', $ids),
                'question_snapshot' => $snapshot,
                'code_failures' => 0,
                'code_locked_until' => null,
            )
        );
        self::redirect(array('eh_view' => 'exam', 'attempt_id' => (int) $attempt->id));
    }

    /**
     * Change the signed-in student's password.
     */
    private static function change_password() {
        if (!is_user_logged_in()) {
            self::redirect(array());
        }
        $user = wp_get_current_user();
        $current = isset($_POST['current_password']) ? (string) wp_unslash($_POST['current_password']) : '';
        $new = isset($_POST['new_password']) ? (string) wp_unslash($_POST['new_password']) : '';
        $confirm = isset($_POST['confirm_password']) ? (string) wp_unslash($_POST['confirm_password']) : '';
        $back = array('eh_view' => 'account');
        if (!wp_check_password($current, $user->user_pass, $user->ID)) {
            self::redirect($back + array('eh_msg' => 'password_current'));
        }
        if (strlen($new) < 8) {
            self::redirect($back + array('eh_msg' => 'password_short'));
        }
        if ($new !== $confirm) {
            self::redirect($back + array('eh_msg' => 'password_mismatch'));
        }
        if ($new === $current) {
            self::redirect($back + array('eh_msg' => 'password_same'));
        }
        wp_set_password($new, $user->ID);
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        self::redirect($back + array('eh_msg' => 'password_changed'));
    }

    /**
     * Grade a paper submitted without JavaScript.
     */
    private static function submit_exam() {
        self::require_student();
        $attempt_id = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
        $attempt = EH_Repository::get_attempt($attempt_id);
        if (!$attempt || (int) $attempt->user_id !== get_current_user_id()) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        if ($attempt->status === 'submitted') {
            self::redirect(array('eh_view' => 'result', 'attempt_id' => $attempt_id));
        }
        if ($attempt->status !== 'in_progress') {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        $auto = false;
        if (eh_attempt_within_grace($attempt)) {
            $selections = array();
            if (isset($_POST['q']) && is_array($_POST['q'])) {
                foreach (wp_unslash($_POST['q']) as $question_id => $choice) {
                    $selections[(int) $question_id] = $choice;
                }
            }
            eh_save_selections($attempt, $selections);
        } else {
            $auto = true;
        }
        eh_finalize_attempt($attempt, $auto);
        self::redirect(array('eh_view' => 'result', 'attempt_id' => $attempt_id));
    }

    /**
     * Email a start code, replacing it when it has expired.
     *
     * @param object $exam      Exam.
     * @param object $attempt   Pending attempt.
     * @param bool   $force_new Always generate a new code.
     */
    private static function deliver_code($exam, $attempt, $force_new) {
        if (!$attempt) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        $user = wp_get_current_user();
        if ($force_new || eh_gmt_to_ts($attempt->code_expires_at) < time()) {
            $code = eh_generate_start_code();
            $expires = gmdate('Y-m-d H:i:s', time() + ((int) $exam->code_expiry_minutes * MINUTE_IN_SECONDS));
            EH_Repository::update_attempt(
                $attempt->id,
                array(
                    'start_code' => $code,
                    'code_expires_at' => $expires,
                    'code_failures' => 0,
                    'code_locked_until' => null,
                )
            );
            $attempt->start_code = $code;
            $attempt->code_expires_at = $expires;
        }
        $sent = EH_Emails::start_code(
            $user,
            $exam,
            $attempt->start_code,
            $attempt->code_expires_at,
            EH_Repository::count_questions($exam->id)
        );
        if (!$sent) {
            error_log('Exam Hall could not email a start code for attempt ' . (int) $attempt->id);
        }
        self::redirect(
            array(
                'eh_view' => 'gate',
                'exam_id' => (int) $exam->id,
                'eh_msg' => $sent ? 'code_sent' : 'mail_failed',
            )
        );
    }

    /**
     * Stop a request that is not from an active student.
     */
    private static function require_student() {
        if (!eh_user_can_take() || get_user_meta(get_current_user_id(), 'eh_status', true) === 'suspended') {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
    }

    /**
     * Sign out and return to the portal.
     */
    private static function logout() {
        if (!is_user_logged_in()) {
            self::redirect(array());
        }
        check_admin_referer('eh_logout');
        wp_logout();
        self::redirect(array('eh_msg' => 'logged_out'));
    }

    /**
     * Redirect to the portal and stop.
     *
     * @param array $args Query arguments.
     */
    private static function redirect($args) {
        wp_safe_redirect(eh_portal_url($args));
        exit;
    }

    /**
     * Render a portal screen and stop.
     *
     * @param string $template Template slug.
     * @param array  $data     Template data.
     */
    /**
     * Download the signed-in student's certificate.
     */
    private static function send_certificate() {
        $attempt_id = isset($_GET['eh_certificate']) ? (int) $_GET['eh_certificate'] : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!is_user_logged_in() || !eh_user_can_take() || !wp_verify_nonce($nonce, 'eh_certificate_' . $attempt_id)) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        $attempt = EH_Repository::get_attempt($attempt_id);
        $exam = $attempt ? EH_Repository::get_exam($attempt->exam_id) : null;
        $student = wp_get_current_user();
        $path = get_attached_file((int) get_option('eh_certificate_id'));
        if (!$attempt || !$exam || (int) $attempt->user_id !== (int) $student->ID || !eh_certificate_available($exam, $attempt) || !$path) {
            self::redirect(array('eh_msg' => 'exam_unavailable'));
        }
        EH_Certificate::send($path, EH_Certificate::fields_for_attempt($attempt, $exam, $student), EH_Certificate::layout(), 'certificate-' . $attempt_id . '.png', true);
    }

    private static function screen($template, $data) {
        $allowed = array('login', 'forgot', 'reset', 'dashboard', 'gate', 'runner', 'result', 'account', 'denied');
        if (!in_array($template, $allowed, true)) {
            $template = 'login';
        }
        $flash = eh_portal_flash_get();
        $code = isset($_GET['eh_msg']) ? sanitize_key(wp_unslash($_GET['eh_msg'])) : '';
        $data['template'] = $template;
        $data['message'] = eh_portal_message($code);
        $data['flash'] = is_array($flash) ? $flash : array();
        $data['error'] = (is_array($flash) && !empty($flash['error'])) ? $flash['error'] : '';
        $data['can_manage'] = eh_user_can_manage();
        $data['brand'] = eh_brand();
        $eh = $data;
        include EH_PATH . 'templates/portal/layout.php';
        exit;
    }
}
