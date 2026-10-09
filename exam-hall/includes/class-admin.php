<?php
/**
 * Exam Hall screens in wp-admin.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Administrator dashboard.
 */
class EH_Admin {

    /**
     * Register menus and form handlers.
     */
    public static function hooks() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_notices', array(__CLASS__, 'missing_page_notice'));
        $actions = array(
            'eh_save_exam',
            'eh_delete_exam',
            'eh_save_question',
            'eh_delete_question',
            'eh_move_question',
            'eh_import_csv',
            'eh_save_question_settings',
            'eh_question_template',
            'eh_save_student',
            'eh_import_students',
            'eh_download_student_passwords',
            'eh_delete_student',
            'eh_reset_password',
            'eh_resend_code',
            'eh_save_settings',
            'eh_test_email',
            'eh_create_page',
            'eh_export_results',
            'eh_assign_students',
            'eh_certificate',
            'eh_certificate_preview',
        );
        foreach ($actions as $action) {
            add_action('admin_post_' . $action, array(__CLASS__, $action));
        }
    }

    /**
     * Admin menu.
     */
    public static function menu() {
        add_menu_page(
            __('Exam Hall', 'exam-hall'),
            __('Exam Hall', 'exam-hall'),
            'eh_manage_exams',
            'eh-dashboard',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-clipboard',
            26
        );
        add_submenu_page('eh-dashboard', __('Dashboard', 'exam-hall'), __('Dashboard', 'exam-hall'), 'eh_manage_exams', 'eh-dashboard', array(__CLASS__, 'render_dashboard'));
        add_submenu_page('eh-dashboard', __('Exams', 'exam-hall'), __('Exams', 'exam-hall'), 'eh_manage_exams', 'eh-exams', array(__CLASS__, 'render_exams'));
        add_submenu_page('eh-dashboard', __('Students', 'exam-hall'), __('Students', 'exam-hall'), 'eh_manage_exams', 'eh-students', array(__CLASS__, 'render_students'));
        add_submenu_page('eh-dashboard', __('Results', 'exam-hall'), __('Results', 'exam-hall'), 'eh_manage_exams', 'eh-results', array(__CLASS__, 'render_results'));
        add_submenu_page('eh-dashboard', __('Settings', 'exam-hall'), __('Settings', 'exam-hall'), 'eh_manage_exams', 'eh-settings', array(__CLASS__, 'render_settings'));
        add_submenu_page(null, __('Questions', 'exam-hall'), __('Questions', 'exam-hall'), 'eh_manage_exams', 'eh-questions', array(__CLASS__, 'render_questions'));
    }

    /**
     * Load admin CSS and JS on Exam Hall screens.
     *
     * @param string $hook Current admin page hook.
     */
    public static function assets($hook) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'eh-') !== 0) {
            return;
        }
        unset($hook);
        wp_enqueue_style('eh-font', 'https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap', array(), null);
        wp_enqueue_style('eh-admin', EH_URL . 'assets/css/admin.css', array('eh-font'), EH_VERSION);
        wp_enqueue_script('eh-admin', EH_URL . 'assets/js/admin.js', array(), EH_VERSION, true);
    }

    /**
     * Warn when the student page has been removed.
     */
    public static function missing_page_notice() {
        if (!eh_user_can_manage()) {
            return;
        }
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'eh-') !== 0) {
            return;
        }
        $page_id = (int) get_option('eh_portal_page_id');
        if ($page_id && get_post_status($page_id) === 'publish') {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('The Exam Hall student page is missing. Create it again from Exam Hall settings.', 'exam-hall') . '</p></div>';
    }

    /**
     * Dashboard.
     */
    public static function render_dashboard() {
        eh_require_manager();
        self::view(
            'dashboard',
            array(
                'stats' => EH_Repository::stats(),
                'pending' => EH_Repository::pending_codes(),
                'recent' => EH_Repository::recent_results(),
                'draft_count' => count(EH_Repository::list_exams('draft')),
                'portal_url' => eh_portal_url(),
                'brand' => eh_brand(),
                'exams' => EH_Repository::list_exams(),
                'students' => EH_Repository::list_students('', 1, 300),
            )
        );
    }

    /**
     * Exam list and editor.
     */
    public static function render_exams() {
        eh_require_manager();
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'new' || $action === 'edit') {
            $exam = null;
            if ($action === 'edit') {
                $exam = EH_Repository::get_exam(isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0);
                if (!$exam) {
                    eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
                }
            }
            self::view(
                'exam-form',
                array(
                    'exam' => $exam,
                    'flash' => eh_form_flash_get(),
                    'students' => EH_Repository::list_students('', 1, 300),
                    'assigned_ids' => $exam ? EH_Repository::assigned_user_ids($exam->id) : array(),
                )
            );
            return;
        }
        $exams = EH_Repository::list_exams();
        $ids = array();
        foreach ($exams as $exam) {
            $ids[] = (int) $exam->id;
        }
        self::view(
            'exams',
            array(
                'exams' => $exams,
                'question_counts' => EH_Repository::question_counts_for($ids),
                'assignment_counts' => EH_Repository::assignment_counts_for($ids),
            )
        );
    }

    /**
     * Question bank for one exam.
     */
    public static function render_questions() {
        eh_require_manager();
        $exam = EH_Repository::get_exam(isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0);
        if (!$exam) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'new' || $action === 'edit') {
            $question = null;
            if ($action === 'edit') {
                $question = EH_Repository::get_question(isset($_GET['question_id']) ? (int) $_GET['question_id'] : 0);
                if (!$question || (int) $question->exam_id !== (int) $exam->id) {
                    eh_admin_redirect('eh-questions', array('exam_id' => (int) $exam->id, 'eh_msg' => 'not_found'));
                }
            }
            self::view(
                'question-form',
                array(
                    'exam' => $exam,
                    'question' => $question,
                    'flash' => eh_form_flash_get(),
                )
            );
            return;
        }
        $questions = EH_Repository::get_questions($exam->id);
        $total = 0;
        foreach ($questions as $question) {
            $total += (float) $question->marks;
        }
        self::view(
            'questions',
            array(
                'exam' => $exam,
                'questions' => $questions,
                'total_marks' => $total,
                'import_errors' => eh_import_errors_get('questions'),
                'flash' => eh_form_flash_get(),
                'in_progress' => EH_Repository::attempt_count($exam->id, 'in_progress'),
            )
        );
    }

    /**
     * Student accounts.
     */
    public static function render_students() {
        eh_require_manager();
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'new' || $action === 'edit') {
            $student = null;
            if ($action === 'edit') {
                $student = get_user_by('id', isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0);
                if (!$student || !in_array('exam_student', (array) $student->roles, true)) {
                    eh_admin_redirect('eh-students', array('eh_msg' => 'not_found'));
                }
            }
            self::view(
                'student-form',
                array(
                    'student' => $student,
                    'flash' => eh_form_flash_get(),
                    'password_flash' => eh_password_flash_get(),
                    'attempts' => $student ? EH_Repository::attempts_for_user($student->ID, 20) : array(),
                    'attempt_count' => $student ? EH_Repository::count_user_attempts($student->ID) : 0,
                    'exams' => EH_Repository::list_exams(),
                    'assigned_ids' => $student ? EH_Repository::assigned_exam_ids($student->ID) : array(),
                )
            );
            return;
        }
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
        $result = EH_Repository::list_students($search, $paged, 20);
        $ids = array();
        foreach ($result['users'] as $user) {
            $ids[] = (int) $user->ID;
        }
        self::view(
            'students',
            array(
                'students' => $result['users'],
                'total' => $result['total'],
                'paged' => max(1, $paged),
                'per_page' => 20,
                'search' => $search,
                'attempt_counts' => EH_Repository::attempt_counts_for_users($ids),
                'flash' => eh_form_flash_get(),
                'import_errors' => eh_import_errors_get('students'),
                'imported_accounts' => eh_student_import_get(),
            )
        );
    }

    /**
     * Results list and one sitting.
     */
    public static function render_results() {
        eh_require_manager();
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : 'list';
        if ($action === 'view') {
            $attempt = EH_Repository::get_attempt(isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0);
            $exam = $attempt ? EH_Repository::get_exam($attempt->exam_id) : null;
            $student = $attempt ? get_user_by('id', $attempt->user_id) : null;
            if (!$attempt || !$exam || $attempt->status !== 'submitted') {
                eh_admin_redirect('eh-results', array('eh_msg' => 'not_found'));
            }
            self::view(
                'result-detail',
                array(
                    'attempt' => $attempt,
                    'exam' => $exam,
                    'student' => $student,
                    'questions' => EH_Repository::questions_for_attempt($attempt),
                    'answers' => EH_Repository::answer_rows($attempt->id),
                )
            );
            return;
        }
        $exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
        $classification = isset($_GET['classification']) ? sanitize_text_field(wp_unslash($_GET['classification'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged = isset($_GET['paged']) ? (int) $_GET['paged'] : 1;
        $result = EH_Repository::query_attempts(
            array(
                'exam_id' => $exam_id,
                'classification' => $classification,
                'search' => $search,
                'paged' => $paged,
                'per_page' => 20,
            )
        );
        self::view(
            'results',
            array(
                'rows' => $result['rows'],
                'total' => $result['total'],
                'paged' => max(1, $paged),
                'per_page' => 20,
                'exams' => EH_Repository::list_exams(),
                'classifications' => EH_Repository::classifications_in_use(),
                'exam_id' => $exam_id,
                'classification' => $classification,
                'search' => $search,
            )
        );
    }

    /**
     * Settings.
     */
    public static function render_settings() {
        eh_require_manager();
        self::view(
            'settings',
            array(
                'flash' => eh_form_flash_get(),
                'portal_id' => (int) get_option('eh_portal_page_id'),
                'from_name' => (string) get_option('eh_from_name'),
                'from_email' => (string) get_option('eh_from_email'),
                'delete_data' => get_option('eh_delete_data_on_uninstall') === '1',
                'brand' => eh_brand(),
                'certificate_id' => (int) get_option('eh_certificate_id'),
                'certificate_url' => (int) get_option('eh_certificate_id') ? wp_get_attachment_image_url((int) get_option('eh_certificate_id'), 'large') : '',
                'layout' => EH_Certificate::layout(),
                'can_render' => EH_Certificate::can_render(),
            )
        );
    }

    /**
     * Save an exam.
     */
    public static function eh_save_exam() {
        eh_guard('eh_save_exam');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $parsed = self::exam_from_post();
        $back = array('action' => $exam_id ? 'edit' : 'new');
        if ($exam_id) {
            $back['exam_id'] = $exam_id;
        }
        if (is_wp_error($parsed)) {
            eh_form_flash_set(
                array(
                    'errors' => $parsed->get_error_messages(),
                    'values' => self::posted_exam_form(),
                )
            );
            eh_admin_redirect('eh-exams', $back);
        }
        if ($exam_id) {
            if (!EH_Repository::get_exam($exam_id)) {
                eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
            }
            EH_Repository::update_exam($exam_id, $parsed);
            self::save_exam_allocations($exam_id);
            $back['eh_msg'] = 'exam_saved';
            eh_admin_redirect('eh-exams', $back);
        }
        $new_id = EH_Repository::insert_exam($parsed);
        if (is_wp_error($new_id)) {
            eh_form_flash_set(
                array(
                    'errors' => array($new_id->get_error_message()),
                    'values' => self::posted_exam_form(),
                )
            );
            eh_admin_redirect('eh-exams', array('action' => 'new'));
        }
        self::save_exam_allocations((int) $new_id);
        eh_admin_redirect('eh-exams', array('action' => 'edit', 'exam_id' => $new_id, 'eh_msg' => 'exam_saved'));
    }

    /**
     * Store the student checklist posted with an exam.
     *
     * @param int $exam_id Exam id.
     */
    private static function save_exam_allocations($exam_id) {
        if (empty($_POST['assign_present'])) {
            return;
        }
        $ids = isset($_POST['student_ids']) ? eh_student_ids_from_request(wp_unslash($_POST['student_ids'])) : array();
        EH_Repository::replace_exam_assignments($exam_id, $ids);
    }

    /**
     * Allocate or remove a bulk selection of students.
     */
    public static function eh_assign_students() {
        eh_guard('eh_assign_students');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        $ids = isset($_POST['user_ids']) ? eh_student_ids_from_request(wp_unslash($_POST['user_ids'])) : array();
        $mode = isset($_POST['assign_mode']) && $_POST['assign_mode'] === 'remove' ? 'remove' : 'assign';
        if (!$exam || !$ids) {
            eh_admin_redirect('eh-dashboard', array('eh_msg' => 'assign_none'));
        }
        if ($mode === 'remove') {
            EH_Repository::unassign_students($exam_id, $ids);
            eh_admin_redirect('eh-dashboard', array('eh_msg' => 'students_unassigned'));
        }
        EH_Repository::set_exam_audience($exam_id, 'assigned');
        EH_Repository::assign_students($exam_id, $ids);
        eh_admin_redirect('eh-dashboard', array('eh_msg' => 'students_assigned'));
    }

    /**
     * Download a certificate for one sitting.
     */
    public static function eh_certificate() {
        eh_guard('eh_certificate');
        $attempt = EH_Repository::get_attempt(isset($_GET['attempt_id']) ? (int) $_GET['attempt_id'] : 0);
        $exam = $attempt ? EH_Repository::get_exam($attempt->exam_id) : null;
        $student = $attempt ? get_user_by('id', $attempt->user_id) : null;
        $path = get_attached_file((int) get_option('eh_certificate_id'));
        if (!$attempt || !$exam || !$student || $attempt->status !== 'submitted' || !$path) {
            eh_admin_redirect('eh-results', array('eh_msg' => 'not_found'));
        }
        EH_Certificate::send($path, EH_Certificate::fields_for_attempt($attempt, $exam, $student), EH_Certificate::layout(), 'certificate-' . (int) $attempt->id . '.png', true);
    }

    /**
     * Preview the certificate design with sample text.
     */
    public static function eh_certificate_preview() {
        eh_guard('eh_certificate_preview');
        $path = get_attached_file((int) get_option('eh_certificate_id'));
        if (!$path) {
            eh_admin_redirect('eh-settings', array('eh_msg' => 'certificate_invalid'));
        }
        EH_Certificate::send($path, EH_Certificate::sample_fields(), EH_Certificate::layout(), 'certificate-preview.png', false);
    }

    /**
     * Delete an exam that has never been used.
     */
    public static function eh_delete_exam() {
        eh_guard('eh_delete_exam');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        if (!EH_Repository::get_exam($exam_id)) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        if (EH_Repository::attempt_count($exam_id) > 0) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'exam_has_attempts'));
        }
        EH_Repository::delete_questions($exam_id);
        EH_Repository::delete_exam($exam_id);
        eh_admin_redirect('eh-exams', array('eh_msg' => 'exam_deleted'));
    }

    /**
     * Save one question.
     */
    public static function eh_save_question() {
        eh_guard('eh_save_question');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        if (!$exam) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        $parsed = self::question_from_post($exam);
        $back = array(
            'exam_id' => $exam_id,
            'action' => $question_id ? 'edit' : 'new',
        );
        if ($question_id) {
            $back['question_id'] = $question_id;
        }
        if (is_wp_error($parsed)) {
            eh_form_flash_set(
                array(
                    'errors' => $parsed->get_error_messages(),
                    'values' => self::posted_question_form(),
                )
            );
            eh_admin_redirect('eh-questions', $back);
        }
        $parsed['exam_id'] = $exam_id;
        if ($question_id) {
            $existing = EH_Repository::get_question($question_id);
            if (!$existing || (int) $existing->exam_id !== $exam_id) {
                eh_admin_redirect('eh-questions', array('exam_id' => $exam_id, 'eh_msg' => 'not_found'));
            }
            $parsed['id'] = $question_id;
        }
        $saved = EH_Repository::save_question($parsed);
        if (is_wp_error($saved)) {
            eh_form_flash_set(
                array(
                    'errors' => array($saved->get_error_message()),
                    'values' => self::posted_question_form(),
                )
            );
            eh_admin_redirect('eh-questions', $back);
        }
        eh_admin_redirect('eh-questions', array('exam_id' => $exam_id, 'eh_msg' => 'question_saved'));
    }

    /**
     * Delete one question.
     */
    public static function eh_delete_question() {
        eh_guard('eh_delete_question');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $question = EH_Repository::get_question($question_id);
        if (!$question || (int) $question->exam_id !== $exam_id) {
            eh_admin_redirect('eh-questions', array('exam_id' => $exam_id, 'eh_msg' => 'not_found'));
        }
        EH_Repository::delete_question($question_id);
        eh_admin_redirect('eh-questions', array('exam_id' => $exam_id, 'eh_msg' => 'question_deleted'));
    }

    /**
     * Move a question up or down.
     */
    public static function eh_move_question() {
        eh_guard('eh_move_question');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $direction = isset($_POST['direction']) && $_POST['direction'] === 'up' ? 'up' : 'down';
        $question = EH_Repository::get_question($question_id);
        if ($question && (int) $question->exam_id === $exam_id) {
            EH_Repository::move_question($question_id, $direction);
        }
        eh_admin_redirect('eh-questions', array('exam_id' => $exam_id));
    }

    /**
     * Import questions from CSV.
     */
    public static function eh_import_csv() {
        eh_guard('eh_import_csv');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        $exam = EH_Repository::get_exam($exam_id);
        if (!$exam) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        $back = array('exam_id' => $exam_id);
        if (empty($_FILES['csv']['tmp_name']) || !empty($_FILES['csv']['error'])) {
            eh_form_flash_set(array('errors' => array(__('Choose a CSV file to upload.', 'exam-hall'))));
            eh_admin_redirect('eh-questions', $back + array('eh_msg' => 'import_failed'));
        }
        $name = isset($_FILES['csv']['name']) ? (string) $_FILES['csv']['name'] : '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            eh_form_flash_set(array('errors' => array(__('Upload a .csv file.', 'exam-hall'))));
            eh_admin_redirect('eh-questions', $back + array('eh_msg' => 'import_failed'));
        }
        $size = isset($_FILES['csv']['size']) ? (int) $_FILES['csv']['size'] : 0;
        if ($size > 2 * 1024 * 1024) {
            eh_form_flash_set(array('errors' => array(__('The CSV file must be 2 MB or smaller.', 'exam-hall'))));
            eh_admin_redirect('eh-questions', $back + array('eh_msg' => 'import_failed'));
        }
        $parsed = eh_parse_question_csv_file($_FILES['csv']['tmp_name'], eh_exam_question_type($exam), eh_exam_default_marks($exam));
        if (empty($parsed['questions'])) {
            eh_import_errors_set($parsed['errors']);
            eh_admin_redirect('eh-questions', $back + array('eh_msg' => 'import_failed'));
        }
        if (!empty($_POST['replace'])) {
            EH_Repository::replace_questions($exam_id, $parsed['questions']);
        } else {
            EH_Repository::append_questions($exam_id, $parsed['questions']);
        }
        if (!empty($parsed['errors'])) {
            eh_import_errors_set($parsed['errors']);
        }
        eh_admin_redirect(
            'eh-questions',
            array(
                'exam_id' => $exam_id,
                'eh_msg' => 'questions_imported',
                'imported' => count($parsed['questions']),
                'skipped' => count($parsed['errors']),
            )
        );
    }

    /**
     * Save the exam's question format and default marks.
     */
    public static function eh_save_question_settings() {
        eh_guard('eh_save_question_settings');
        $exam_id = isset($_POST['exam_id']) ? (int) $_POST['exam_id'] : 0;
        if (!EH_Repository::get_exam($exam_id)) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        $type = eh_normalize_question_type(eh_post_text('question_type'));
        $raw = str_replace(',', '.', eh_post_text('default_marks'));
        if (!is_numeric($raw)) {
            eh_form_flash_set(array('errors' => array(__('Enter the marks for each question.', 'exam-hall'))));
            eh_admin_redirect('eh-questions', array('exam_id' => $exam_id));
        }
        $marks = round((float) $raw, 2);
        if ($marks <= 0 || $marks > 100) {
            eh_form_flash_set(array('errors' => array(__('Marks for each question must be greater than 0 and at most 100.', 'exam-hall'))));
            eh_admin_redirect('eh-questions', array('exam_id' => $exam_id));
        }
        EH_Repository::update_question_settings($exam_id, $type, $marks);
        eh_admin_redirect('eh-questions', array('exam_id' => $exam_id, 'eh_msg' => 'question_settings_saved'));
    }

    /**
     * Download a question CSV whose columns match this exam.
     */
    public static function eh_question_template() {
        eh_guard('eh_question_template');
        $exam = EH_Repository::get_exam(isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0);
        if (!$exam) {
            eh_admin_redirect('eh-exams', array('eh_msg' => 'not_found'));
        }
        $type = eh_exam_question_type($exam);
        $marks = eh_format_marks(eh_exam_default_marks($exam));
        $filename = $type === 'true_false' ? 'exam-hall-true-false-template.csv' : 'exam-hall-multiple-choice-template.csv';
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        if ($type === 'true_false') {
            fputcsv($out, array('question', 'correct_answer', 'marks'));
            fputcsv($out, array('The Pacific Ocean is the largest ocean.', 'True', $marks));
            fputcsv($out, array('A triangle has four sides.', 'False', $marks));
        } else {
            fputcsv($out, array('question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'marks'));
            fputcsv($out, array('What is 2 + 2?', '3', '4', '5', '6', 'B', $marks));
            fputcsv($out, array('Which city is the capital of France?', 'London', 'Paris', 'Berlin', 'Madrid', 'Paris', $marks));
        }
        fclose($out);
        exit;
    }

    /**
     * Create student accounts from a CSV file.
     */
    public static function eh_import_students() {
        eh_guard('eh_import_students');
        if (empty($_FILES['csv']['tmp_name']) || !empty($_FILES['csv']['error'])) {
            eh_form_flash_set(array('errors' => array(__('Choose a CSV file to upload.', 'exam-hall'))));
            eh_admin_redirect('eh-students', array('eh_msg' => 'students_import_failed'));
        }
        $name = isset($_FILES['csv']['name']) ? (string) $_FILES['csv']['name'] : '';
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            eh_form_flash_set(array('errors' => array(__('Upload a .csv file.', 'exam-hall'))));
            eh_admin_redirect('eh-students', array('eh_msg' => 'students_import_failed'));
        }
        $size = isset($_FILES['csv']['size']) ? (int) $_FILES['csv']['size'] : 0;
        if ($size > 2 * 1024 * 1024) {
            eh_form_flash_set(array('errors' => array(__('The CSV file must be 2 MB or smaller.', 'exam-hall'))));
            eh_admin_redirect('eh-students', array('eh_msg' => 'students_import_failed'));
        }
        $parsed = eh_parse_student_csv_file($_FILES['csv']['tmp_name']);
        $created = array();
        $errors = $parsed['errors'];
        foreach ($parsed['students'] as $form) {
            $email_owner = email_exists($form['email']);
            if ($email_owner) {
                $errors[] = array(
                    'row' => 0,
                    'message' => sprintf(
                        /* translators: %s: email address */
                        __('%s is already in use.', 'exam-hall'),
                        $form['email']
                    ),
                );
                continue;
            }
            if (EH_Repository::student_number_owner($form['student_number'])) {
                $errors[] = array(
                    'row' => 0,
                    'message' => sprintf(
                        /* translators: %s: student number */
                        __('Student number %s is already in use.', 'exam-hall'),
                        $form['student_number']
                    ),
                );
                continue;
            }
            $account = self::create_student_account($form);
            if (is_wp_error($account)) {
                $errors[] = array(
                    'row' => 0,
                    'message' => $account->get_error_message(),
                );
                continue;
            }
            $created[] = array(
                'name' => $form['first_name'] . ' ' . $form['last_name'],
                'email' => $form['email'],
                'student_number' => $form['student_number'],
                'password' => $account['password'],
                'mailed' => $account['mailed'] ? 'yes' : 'no',
            );
        }
        if (!$created) {
            eh_import_errors_set($errors, 'students');
            eh_admin_redirect('eh-students', array('eh_msg' => 'students_import_failed'));
        }
        eh_student_import_set($created);
        if ($errors) {
            eh_import_errors_set($errors, 'students');
        }
        eh_admin_redirect(
            'eh-students',
            array(
                'eh_msg' => 'students_imported',
                'imported' => count($created),
                'skipped' => count($errors),
            )
        );
    }

    /**
     * Download the temporary passwords from the latest student import.
     */
    public static function eh_download_student_passwords() {
        eh_guard('eh_download_student_passwords');
        $rows = eh_student_import_get();
        if (!$rows) {
            eh_admin_redirect('eh-students', array('eh_msg' => 'not_found'));
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exam-hall-student-passwords.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('name', 'email', 'student_number', 'temporary_password', 'emailed'));
        foreach ($rows as $row) {
            fputcsv(
                $out,
                array(
                    eh_csv_safe($row['name']),
                    eh_csv_safe($row['email']),
                    eh_csv_safe($row['student_number']),
                    eh_csv_safe($row['password']),
                    eh_csv_safe($row['mailed']),
                )
            );
        }
        fclose($out);
        exit;
    }

    /**
     * Create or update a student.
     */
    public static function eh_save_student() {
        eh_guard('eh_save_student');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $form = self::posted_student_form();
        $errors = self::validate_student($form, $user_id);
        $back = array('action' => $user_id ? 'edit' : 'new');
        if ($user_id) {
            $back['user_id'] = $user_id;
        }
        if ($errors) {
            eh_form_flash_set(array('errors' => $errors, 'values' => $form));
            eh_admin_redirect('eh-students', $back);
        }
        if ($user_id) {
            $user = get_user_by('id', $user_id);
            if (!$user || !in_array('exam_student', (array) $user->roles, true)) {
                eh_admin_redirect('eh-students', array('eh_msg' => 'not_found'));
            }
            $updated = wp_update_user(
                array(
                    'ID' => $user_id,
                    'user_email' => $form['email'],
                    'display_name' => $form['first_name'] . ' ' . $form['last_name'],
                    'first_name' => $form['first_name'],
                    'last_name' => $form['last_name'],
                )
            );
            if (is_wp_error($updated)) {
                eh_form_flash_set(array('errors' => array($updated->get_error_message()), 'values' => $form));
                eh_admin_redirect('eh-students', $back);
            }
            self::save_student_meta($user_id, $form);
            self::save_student_allocations($user_id);
            eh_admin_redirect('eh-students', array('action' => 'edit', 'user_id' => $user_id, 'eh_msg' => 'student_saved'));
        }
        $account = self::create_student_account($form);
        if (is_wp_error($account)) {
            eh_form_flash_set(array('errors' => array($account->get_error_message()), 'values' => $form));
            eh_admin_redirect('eh-students', array('action' => 'new'));
        }
        self::save_student_allocations((int) $account['user_id']);
        eh_password_flash_set((int) $account['user_id'], $account['password'], $account['mailed']);
        eh_admin_redirect('eh-students', array('action' => 'edit', 'user_id' => (int) $account['user_id'], 'eh_msg' => 'student_saved'));
    }

    /**
     * Create a student and email a temporary password.
     *
     * @param array $form Validated student fields.
     * @return array|WP_Error
     */
    private static function create_student_account($form) {
        $password = eh_temporary_password();
        $created = wp_insert_user(
            array(
                'user_login' => eh_unique_login($form['email']),
                'user_pass' => $password,
                'user_email' => $form['email'],
                'display_name' => $form['first_name'] . ' ' . $form['last_name'],
                'first_name' => $form['first_name'],
                'last_name' => $form['last_name'],
                'role' => 'exam_student',
            )
        );
        if (is_wp_error($created)) {
            return $created;
        }
        self::save_student_meta((int) $created, $form);
        $user = get_user_by('id', (int) $created);
        $mailed = $user ? EH_Emails::welcome($user, $password) : false;
        return array(
            'user_id' => (int) $created,
            'password' => $password,
            'mailed' => (bool) $mailed,
        );
    }

    /**
     * Delete a student who has never started an exam.
     */
    public static function eh_delete_student() {
        eh_guard('eh_delete_student');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('exam_student', (array) $user->roles, true)) {
            eh_admin_redirect('eh-students', array('eh_msg' => 'not_found'));
        }
        if (EH_Repository::count_user_attempts($user_id) > 0) {
            eh_admin_redirect('eh-students', array('action' => 'edit', 'user_id' => $user_id, 'eh_msg' => 'student_has_attempts'));
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user_id);
        eh_admin_redirect('eh-students', array('eh_msg' => 'student_deleted'));
    }

    /**
     * Set a temporary password and email it.
     */
    public static function eh_reset_password() {
        eh_guard('eh_reset_password');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $user = get_user_by('id', $user_id);
        if (!$user || !in_array('exam_student', (array) $user->roles, true)) {
            eh_admin_redirect('eh-students', array('eh_msg' => 'not_found'));
        }
        $password = eh_temporary_password();
        wp_set_password($password, $user_id);
        $user = get_user_by('id', $user_id);
        $mailed = EH_Emails::admin_reset($user, $password);
        eh_password_flash_set($user_id, $password, $mailed);
        eh_admin_redirect('eh-students', array('action' => 'edit', 'user_id' => $user_id, 'eh_msg' => 'password_reset'));
    }

    /**
     * Email a pending start code again.
     */
    public static function eh_resend_code() {
        eh_guard('eh_resend_code');
        $attempt_id = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
        $attempt = EH_Repository::get_attempt($attempt_id);
        $exam = $attempt ? EH_Repository::get_exam($attempt->exam_id) : null;
        $user = $attempt ? get_user_by('id', $attempt->user_id) : null;
        if (!$attempt || !$exam || !$user || $attempt->status !== 'code_pending') {
            eh_admin_redirect('eh-dashboard', array('eh_msg' => 'not_found'));
        }
        if (eh_gmt_to_ts($attempt->code_expires_at) < time()) {
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
        $sent = EH_Emails::start_code($user, $exam, $attempt->start_code, $attempt->code_expires_at, EH_Repository::count_questions($exam->id));
        eh_admin_redirect('eh-dashboard', array('eh_msg' => $sent ? 'code_resent' : 'mail_failed_admin'));
    }

    /**
     * Save settings.
     */
    public static function eh_save_settings() {
        eh_guard('eh_save_settings');
        $from_name = eh_post_text('from_name');
        $from_email = sanitize_email(wp_unslash(isset($_POST['from_email']) ? $_POST['from_email'] : ''));
        $portal_id = isset($_POST['portal_page_id']) ? (int) $_POST['portal_page_id'] : 0;
        $brand_name = eh_post_text('brand_name');
        $errors = array();
        if (strlen($brand_name) > 80) {
            $errors[] = __('The company name must be 80 characters or fewer.', 'exam-hall');
        }
        if ($from_name === '' || strlen($from_name) > 80) {
            $errors[] = __('Enter a from name of up to 80 characters.', 'exam-hall');
        }
        if (!is_email($from_email)) {
            $errors[] = __('Enter a valid from email address.', 'exam-hall');
        }
        if ($portal_id && get_post_type($portal_id) !== 'page') {
            $errors[] = __('Choose a published page for the student portal.', 'exam-hall');
        }
        if ($errors) {
            eh_form_flash_set(array('errors' => $errors));
            eh_admin_redirect('eh-settings');
        }
        update_option('eh_from_name', $from_name);
        update_option('eh_from_email', $from_email);
        update_option('eh_brand_name', $brand_name);
        update_option('eh_delete_data_on_uninstall', empty($_POST['delete_data']) ? '0' : '1');
        update_option('eh_certificate_layout', wp_json_encode(EH_Certificate::layout_from_post()));
        if ($portal_id) {
            update_option('eh_portal_page_id', $portal_id);
        }
        if (!empty($_POST['remove_logo'])) {
            $old = (int) get_option('eh_logo_id');
            if ($old) {
                wp_delete_attachment($old, true);
            }
            update_option('eh_logo_id', '0');
        }
        if (!empty($_POST['remove_certificate'])) {
            $old = (int) get_option('eh_certificate_id');
            if ($old) {
                wp_delete_attachment($old, true);
            }
            update_option('eh_certificate_id', '0');
        }
        if (!empty($_FILES['logo']['name'])) {
            $size = isset($_FILES['logo']['size']) ? (int) $_FILES['logo']['size'] : 0;
            if ($size > 2 * 1024 * 1024) {
                eh_admin_redirect('eh-settings', array('eh_msg' => 'logo_invalid'));
            }
            $logo = eh_store_image_upload(
                $_FILES['logo'],
                array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                )
            );
            if (is_wp_error($logo)) {
                eh_admin_redirect('eh-settings', array('eh_msg' => 'logo_invalid'));
            }
            $old = (int) get_option('eh_logo_id');
            update_option('eh_logo_id', (string) $logo);
            if ($old && $old !== (int) $logo) {
                wp_delete_attachment($old, true);
            }
        }
        if (!empty($_FILES['certificate']['name'])) {
            $size = isset($_FILES['certificate']['size']) ? (int) $_FILES['certificate']['size'] : 0;
            $checked = wp_check_filetype(isset($_FILES['certificate']['name']) ? $_FILES['certificate']['name'] : '', array('png' => 'image/png'));
            if ($size > 5 * 1024 * 1024 || empty($checked['ext'])) {
                eh_admin_redirect('eh-settings', array('eh_msg' => 'certificate_invalid'));
            }
            $certificate = eh_store_image_upload($_FILES['certificate'], array('png' => 'image/png'));
            if (is_wp_error($certificate)) {
                eh_admin_redirect('eh-settings', array('eh_msg' => 'certificate_invalid'));
            }
            $path = get_attached_file($certificate);
            $info = $path ? getimagesize($path) : false;
            if (!$info || (int) $info[0] > 4000 || (int) $info[1] > 4000 || (isset($info['mime']) && $info['mime'] !== 'image/png')) {
                wp_delete_attachment($certificate, true);
                eh_admin_redirect('eh-settings', array('eh_msg' => 'certificate_invalid'));
            }
            $old = (int) get_option('eh_certificate_id');
            update_option('eh_certificate_id', (string) $certificate);
            if ($old && $old !== (int) $certificate) {
                wp_delete_attachment($old, true);
            }
        }
        eh_admin_redirect('eh-settings', array('eh_msg' => 'settings_saved'));
    }

    /**
     * Send a test email to the current administrator.
     */
    public static function eh_test_email() {
        eh_guard('eh_test_email');
        $user = wp_get_current_user();
        $sent = EH_Emails::test($user->user_email);
        eh_admin_redirect('eh-settings', array('eh_msg' => $sent ? 'test_email_sent' : 'test_email_failed'));
    }

    /**
     * Recreate the portal page.
     */
    public static function eh_create_page() {
        eh_guard('eh_create_page');
        $existing = (int) get_option('eh_portal_page_id');
        if ($existing && !get_post_status($existing)) {
            delete_option('eh_portal_page_id');
        }
        EH_Activator::create_page();
        eh_admin_redirect('eh-settings', array('eh_msg' => 'page_created'));
    }

    /**
     * Download the current result filter as CSV.
     */
    public static function eh_export_results() {
        eh_guard('eh_export_results');
        $result = EH_Repository::query_attempts(
            array(
                'exam_id' => isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0,
                'classification' => isset($_GET['classification']) ? sanitize_text_field(wp_unslash($_GET['classification'])) : '',
                'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
                'per_page' => 5000,
                'paged' => 1,
            )
        );
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="exam-hall-results-' . gmdate('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array('student_number', 'student_name', 'email', 'exam', 'started_gmt', 'submitted_gmt', 'score', 'total', 'percentage', 'classification', 'auto_submitted'));
        foreach ($result['rows'] as $row) {
            fputcsv(
                $out,
                array(
                    eh_csv_safe(get_user_meta($row->user_id, 'eh_student_number', true)),
                    eh_csv_safe($row->display_name),
                    eh_csv_safe($row->user_email),
                    eh_csv_safe($row->exam_title),
                    $row->started_at,
                    $row->submitted_at,
                    $row->score,
                    $row->total_marks,
                    $row->percentage,
                    eh_csv_safe($row->classification),
                    (int) $row->auto_submitted ? 'yes' : 'no',
                )
            );
        }
        fclose($out);
        exit;
    }

    /**
     * Include an admin template.
     *
     * @param string $template Template slug.
     * @param array  $data     Variables.
     */
    private static function view($template, $data) {
        $allowed = array('dashboard', 'exams', 'exam-form', 'questions', 'question-form', 'students', 'student-form', 'results', 'result-detail', 'settings');
        if (!in_array($template, $allowed, true)) {
            return;
        }
        $data['template'] = $template;
        extract($data, EXTR_SKIP);
        include EH_PATH . 'templates/admin/' . $template . '.php';
    }

    /**
     * Exam fields as the form displayed them.
     *
     * @return array
     */
    private static function posted_exam_form() {
        return array(
            'title' => eh_post_text('title'),
            'description' => eh_post_textarea('description'),
            'instructions' => eh_post_textarea('instructions'),
            'duration_minutes' => eh_post_text('duration_minutes'),
            'allow_retake' => isset($_POST['allow_retake']) ? '1' : '0',
            'max_attempts' => eh_post_text('max_attempts'),
            'code_expiry_minutes' => eh_post_text('code_expiry_minutes'),
            'show_correct' => isset($_POST['show_correct']) ? '1' : '0',
            'band_distinction' => eh_post_text('band_distinction'),
            'band_merit' => eh_post_text('band_merit'),
            'band_pass' => eh_post_text('band_pass'),
            'label_distinction' => eh_post_text('label_distinction'),
            'label_merit' => eh_post_text('label_merit'),
            'label_pass' => eh_post_text('label_pass'),
            'label_fail' => eh_post_text('label_fail'),
            'status' => eh_post_text('status'),
            'audience' => eh_post_text('audience'),
            'certificate_enabled' => isset($_POST['certificate_enabled']) ? '1' : '0',
            'question_type' => eh_post_text('question_type'),
            'default_marks' => eh_post_text('default_marks'),
            'available_from' => eh_post_text('available_from'),
            'available_until' => eh_post_text('available_until'),
        );
    }

    /**
     * Validate and normalize an exam form.
     *
     * @return array|WP_Error
     */
    private static function exam_from_post() {
        $form = self::posted_exam_form();
        $errors = array();
        if ($form['title'] === '') {
            $errors[] = __('Enter an exam title.', 'exam-hall');
        } elseif (strlen($form['title']) > 180) {
            $errors[] = __('The title must be 180 characters or fewer.', 'exam-hall');
        }
        if (strlen($form['description']) > 5000 || strlen($form['instructions']) > 5000) {
            $errors[] = __('Description and instructions must each be 5000 characters or fewer.', 'exam-hall');
        }
        $duration = (int) $form['duration_minutes'];
        if ($duration < 1 || $duration > 360) {
            $errors[] = __('Duration must be between 1 and 360 minutes.', 'exam-hall');
        }
        $allow = $form['allow_retake'] === '1' ? 1 : 0;
        $max = $allow ? (int) $form['max_attempts'] : 1;
        if ($max < 1 || $max > 20) {
            $errors[] = __('Maximum attempts must be between 1 and 20.', 'exam-hall');
        }
        $expiry = (int) $form['code_expiry_minutes'];
        if ($expiry < 5 || $expiry > 240) {
            $errors[] = __('Start codes can expire between 5 and 240 minutes.', 'exam-hall');
        }
        $bands = eh_validate_bands($form['band_distinction'], $form['band_merit'], $form['band_pass']);
        if (!$bands) {
            $errors[] = __('Set classification thresholds so distinction is above merit, and merit is above the pass mark.', 'exam-hall');
        }
        foreach (array('label_distinction', 'label_merit', 'label_pass', 'label_fail') as $key) {
            if ($form[$key] === '' || strlen($form[$key]) > 40) {
                $errors[] = __('Each classification label needs 1 to 40 characters.', 'exam-hall');
                break;
            }
        }
        $from = null;
        $until = null;
        if ($form['available_from'] !== '') {
            $from = eh_local_input_to_gmt($form['available_from']);
            if (!$from) {
                $errors[] = __('The opening time is not a valid date.', 'exam-hall');
            }
        }
        if ($form['available_until'] !== '') {
            $until = eh_local_input_to_gmt($form['available_until']);
            if (!$until) {
                $errors[] = __('The closing time is not a valid date.', 'exam-hall');
            }
        }
        if ($from && $until && eh_gmt_to_ts($until) <= eh_gmt_to_ts($from)) {
            $errors[] = __('The closing time must be after the opening time.', 'exam-hall');
        }
        $status = $form['status'];
        if (!in_array($status, array('draft', 'published', 'closed'), true)) {
            $status = 'draft';
        }
        $audience = $form['audience'] === 'assigned' ? 'assigned' : 'all';
        $question_type = eh_normalize_question_type($form['question_type']);
        $marks_raw = str_replace(',', '.', trim((string) $form['default_marks']));
        $default_marks = 1.0;
        if ($marks_raw === '' || !is_numeric($marks_raw)) {
            $errors[] = __('Enter the marks for each question.', 'exam-hall');
        } else {
            $default_marks = round((float) $marks_raw, 2);
            if ($default_marks <= 0 || $default_marks > 100) {
                $errors[] = __('Marks for each question must be greater than 0 and at most 100.', 'exam-hall');
            }
        }
        if ($errors) {
            $error = new WP_Error();
            foreach ($errors as $message) {
                $error->add('invalid_exam', $message);
            }
            return $error;
        }
        return array(
            'title' => $form['title'],
            'description' => $form['description'],
            'instructions' => $form['instructions'],
            'duration_minutes' => $duration,
            'allow_retake' => $allow,
            'max_attempts' => $max,
            'code_expiry_minutes' => $expiry,
            'show_correct' => $form['show_correct'] === '1' ? 1 : 0,
            'band_distinction' => $bands['distinction'],
            'band_merit' => $bands['merit'],
            'band_pass' => $bands['pass'],
            'label_distinction' => $form['label_distinction'],
            'label_merit' => $form['label_merit'],
            'label_pass' => $form['label_pass'],
            'label_fail' => $form['label_fail'],
            'status' => $status,
            'audience' => $audience,
            'certificate_enabled' => $form['certificate_enabled'] === '1' ? 1 : 0,
            'question_type' => $question_type,
            'default_marks' => $default_marks,
            'available_from' => $from,
            'available_until' => $until,
        );
    }

    /**
     * Question fields as posted.
     *
     * @return array
     */
    private static function posted_question_form() {
        return array(
            'question_text' => eh_post_textarea('question_text'),
            'option_a' => eh_post_textarea('option_a'),
            'option_b' => eh_post_textarea('option_b'),
            'option_c' => eh_post_textarea('option_c'),
            'option_d' => eh_post_textarea('option_d'),
            'correct_answer' => eh_post_text('correct_answer'),
            'marks' => eh_post_text('marks'),
        );
    }

    /**
     * Validate one question form.
     *
     * @return array|WP_Error
     */
    private static function question_from_post($exam) {
        $form = self::posted_question_form();
        $errors = array();
        $marks_raw = str_replace(',', '.', $form['marks']);
        $marks = is_numeric($marks_raw) ? round((float) $marks_raw, 2) : 0;
        if ($marks <= 0 || $marks > 100) {
            $errors[] = __('Marks must be greater than 0 and at most 100.', 'exam-hall');
        }
        if (trim($form['question_text']) === '') {
            $errors[] = __('Enter the question text.', 'exam-hall');
        } elseif (strlen($form['question_text']) > 5000) {
            $errors[] = __('Question text must be 5000 characters or fewer.', 'exam-hall');
        }
        if (eh_exam_question_type($exam) === 'true_false') {
            $correct = eh_normalize_choice($form['correct_answer']);
            if (!in_array($correct, array('A', 'B'), true)) {
                $errors[] = __('Choose True or False.', 'exam-hall');
            }
            if ($errors) {
                $error = new WP_Error();
                foreach ($errors as $message) {
                    $error->add('invalid_question', $message);
                }
                return $error;
            }
            return array(
                'question_text' => trim($form['question_text']),
                'option_a' => 'True',
                'option_b' => 'False',
                'option_c' => '',
                'option_d' => '',
                'correct_answer' => $correct,
                'marks' => $marks,
            );
        }
        $options = array(
            'A' => trim($form['option_a']),
            'B' => trim($form['option_b']),
            'C' => trim($form['option_c']),
            'D' => trim($form['option_d']),
        );
        $filled = array();
        foreach ($options as $letter => $text) {
            if ($text !== '') {
                $filled[$letter] = $text;
            }
        }
        if (count($filled) < 2) {
            $errors[] = __('Add at least two answer options.', 'exam-hall');
        }
        foreach ($filled as $text) {
            if (strlen($text) > 1000) {
                $errors[] = __('Each option must be 1000 characters or fewer.', 'exam-hall');
                break;
            }
        }
        $correct = eh_normalize_choice($form['correct_answer']);
        if ($correct === '' || !isset($filled[$correct])) {
            $errors[] = __('Choose the correct option, and make sure that option has text.', 'exam-hall');
        }
        if ($errors) {
            $error = new WP_Error();
            foreach ($errors as $message) {
                $error->add('invalid_question', $message);
            }
            return $error;
        }
        return array(
            'question_text' => trim($form['question_text']),
            'option_a' => isset($filled['A']) ? $filled['A'] : '',
            'option_b' => isset($filled['B']) ? $filled['B'] : '',
            'option_c' => isset($filled['C']) ? $filled['C'] : '',
            'option_d' => isset($filled['D']) ? $filled['D'] : '',
            'correct_answer' => $correct,
            'marks' => $marks,
        );
    }

    /**
     * Student fields as posted.
     *
     * @return array
     */
    private static function posted_student_form() {
        return array(
            'first_name' => eh_post_text('first_name'),
            'last_name' => eh_post_text('last_name'),
            'email' => sanitize_email(wp_unslash(isset($_POST['email']) ? $_POST['email'] : '')),
            'student_number' => strtoupper(eh_post_text('student_number')),
            'phone' => eh_post_text('phone'),
            'programme' => eh_post_text('programme'),
            'notes' => eh_post_textarea('notes'),
            'status' => eh_post_text('status') === 'suspended' ? 'suspended' : 'active',
        );
    }

    /**
     * Validate a student form.
     *
     * @param array $form    Posted fields.
     * @param int   $user_id Existing user id, or 0.
     * @return array
     */
    private static function validate_student($form, $user_id) {
        $errors = array();
        if ($form['first_name'] === '' || $form['last_name'] === '') {
            $errors[] = __('Enter the student first and last name.', 'exam-hall');
        }
        if (strlen($form['first_name']) > 60 || strlen($form['last_name']) > 60) {
            $errors[] = __('First and last names must be 60 characters or fewer.', 'exam-hall');
        }
        if (!is_email($form['email'])) {
            $errors[] = __('Enter a valid email address.', 'exam-hall');
        }
        if ($form['student_number'] === '' || strlen($form['student_number']) > 40) {
            $errors[] = __('Enter a student number of up to 40 characters.', 'exam-hall');
        }
        if (strlen($form['phone']) > 40) {
            $errors[] = __('Phone must be 40 characters or fewer.', 'exam-hall');
        }
        if (strlen($form['programme']) > 120) {
            $errors[] = __('Class / programme must be 120 characters or fewer.', 'exam-hall');
        }
        if (strlen($form['notes']) > 2000) {
            $errors[] = __('Notes must be 2000 characters or fewer.', 'exam-hall');
        }
        $owner = $form['student_number'] !== '' ? EH_Repository::student_number_owner($form['student_number']) : 0;
        if ($owner && $owner !== (int) $user_id) {
            $errors[] = __('That student number is already in use.', 'exam-hall');
        }
        $email_owner = $form['email'] !== '' ? email_exists($form['email']) : false;
        if ($email_owner && (int) $email_owner !== (int) $user_id) {
            $errors[] = __('That email address is already in use.', 'exam-hall');
        }
        return $errors;
    }

    /**
     * Store student profile meta.
     *
     * @param int   $user_id User id.
     * @param array $form    Validated form.
     */
    private static function save_student_meta($user_id, $form) {
        update_user_meta($user_id, 'first_name', $form['first_name']);
        update_user_meta($user_id, 'last_name', $form['last_name']);
        update_user_meta($user_id, 'eh_student_number', $form['student_number']);
        update_user_meta($user_id, 'eh_phone', $form['phone']);
        update_user_meta($user_id, 'eh_programme', $form['programme']);
        update_user_meta($user_id, 'eh_notes', $form['notes']);
        update_user_meta($user_id, 'eh_status', $form['status']);
    }

    /**
     * Apply the exam checkboxes on a student form.
     *
     * @param int $user_id Student id.
     */
    private static function save_student_allocations($user_id) {
        if (empty($_POST['allocate_present'])) {
            return;
        }
        $ids = isset($_POST['exam_ids']) ? array_map('intval', (array) wp_unslash($_POST['exam_ids'])) : array();
        EH_Repository::sync_student_allocations($user_id, $ids);
    }
}
