<?php
/**
 * Activation, roles, and the sample exam.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin lifecycle.
 */
class EH_Activator {

    /**
     * Run on activation.
     */
    public static function activate() {
        self::create_tables();
        self::ensure_schema();
        self::ensure_roles();
        self::default_options();
        self::create_page();
        self::maybe_seed();
        update_option('eh_db_version', EH_VERSION);
        flush_rewrite_rules();
    }

    /**
     * Flush rewrite rules. Data stays in place.
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Bring an older install up to the current schema.
     */
    public static function maybe_upgrade() {
        if (get_option('eh_db_version') === EH_VERSION) {
            return;
        }
        self::create_tables();
        self::ensure_schema();
        self::ensure_roles();
        self::default_options();
        update_option('eh_db_version', EH_VERSION);
    }

    /**
     * Create or repair the student role and the manager capability.
     */
    public static function ensure_roles() {
        $student = get_role('exam_student');
        if (!$student) {
            add_role(
                'exam_student',
                __('Exam Student', 'exam-hall'),
                array(
                    'read' => true,
                    'eh_take_exams' => true,
                )
            );
        } else {
            if (!$student->has_cap('read')) {
                $student->add_cap('read');
            }
            if (!$student->has_cap('eh_take_exams')) {
                $student->add_cap('eh_take_exams');
            }
        }
        $admin = get_role('administrator');
        if ($admin && !$admin->has_cap('eh_manage_exams')) {
            $admin->add_cap('eh_manage_exams');
        }
    }

    /**
     * Default options that should survive reactivation.
     */
    public static function default_options() {
        add_option('eh_from_name', 'Exam Hall');
        add_option('eh_from_email', get_option('admin_email'));
        add_option('eh_delete_data_on_uninstall', '0');
        add_option('eh_brand_name', '');
        add_option('eh_logo_id', '0');
        add_option('eh_certificate_id', '0');
    }

    /**
     * Create the four plugin tables.
     */
    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $exams = $wpdb->prefix . 'eh_exams';
        $questions = $wpdb->prefix . 'eh_questions';
        $attempts = $wpdb->prefix . 'eh_attempts';
        $answers = $wpdb->prefix . 'eh_answers';

        $sql = "CREATE TABLE {$exams} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(190) NOT NULL,
            description text NOT NULL,
            instructions text NOT NULL,
            duration_minutes smallint(5) unsigned NOT NULL DEFAULT 60,
            allow_retake tinyint(1) NOT NULL DEFAULT 1,
            max_attempts smallint(5) unsigned NOT NULL DEFAULT 2,
            code_expiry_minutes smallint(5) unsigned NOT NULL DEFAULT 30,
            show_correct tinyint(1) NOT NULL DEFAULT 1,
            band_distinction tinyint(3) unsigned NOT NULL DEFAULT 80,
            band_merit tinyint(3) unsigned NOT NULL DEFAULT 70,
            band_pass tinyint(3) unsigned NOT NULL DEFAULT 50,
            label_distinction varchar(40) NOT NULL DEFAULT 'Distinction',
            label_merit varchar(40) NOT NULL DEFAULT 'Merit',
            label_pass varchar(40) NOT NULL DEFAULT 'Pass',
            label_fail varchar(40) NOT NULL DEFAULT 'Fail',
            status varchar(20) NOT NULL DEFAULT 'draft',
            audience varchar(20) NOT NULL DEFAULT 'all',
            certificate_enabled tinyint(1) NOT NULL DEFAULT 0,
            question_type varchar(20) NOT NULL DEFAULT 'multiple_choice',
            default_marks decimal(8,2) NOT NULL DEFAULT 1.00,
            available_from datetime DEFAULT NULL,
            available_until datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) {$charset};
        CREATE TABLE {$questions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            question_text text NOT NULL,
            option_a text NOT NULL,
            option_b text NOT NULL,
            option_c text NOT NULL,
            option_d text NOT NULL,
            correct_answer char(1) NOT NULL,
            marks decimal(8,2) NOT NULL DEFAULT 1.00,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY exam_id (exam_id)
        ) {$charset};
        CREATE TABLE {$attempts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            start_code varchar(12) NOT NULL DEFAULT '',
            code_expires_at datetime DEFAULT NULL,
            code_failures smallint(5) unsigned NOT NULL DEFAULT 0,
            code_locked_until datetime DEFAULT NULL,
            started_at datetime DEFAULT NULL,
            deadline_at datetime DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            score decimal(10,2) NOT NULL DEFAULT 0.00,
            total_marks decimal(10,2) NOT NULL DEFAULT 0.00,
            percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            classification varchar(50) NOT NULL DEFAULT '',
            auto_submitted tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'code_pending',
            question_ids text NOT NULL,
            question_snapshot longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY exam_user (exam_id, user_id),
            KEY user_status (user_id, status)
        ) {$charset};
        CREATE TABLE {$answers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            attempt_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            selected_answer char(1) NOT NULL DEFAULT '',
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            marks_awarded decimal(8,2) NOT NULL DEFAULT 0.00,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY attempt_question (attempt_id, question_id)
        ) {$charset};
        CREATE TABLE {$wpdb->prefix}eh_assignments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            assigned_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY exam_user (exam_id, user_id),
            KEY user_id (user_id)
        ) {$charset};";

        dbDelta($sql);
    }

    /**
     * Add columns dbDelta may leave behind on an existing install.
     */
    public static function ensure_schema() {
        global $wpdb;
        $exams = $wpdb->prefix . 'eh_exams';
        $sample = $wpdb->get_row("SELECT * FROM {$exams} LIMIT 1", ARRAY_A);
        if (is_array($sample) && !array_key_exists('audience', $sample)) {
            $wpdb->query("ALTER TABLE {$exams} ADD COLUMN audience varchar(20) NOT NULL DEFAULT 'all'");
        }
        if (is_array($sample) && !array_key_exists('certificate_enabled', $sample)) {
            $wpdb->query("ALTER TABLE {$exams} ADD COLUMN certificate_enabled tinyint(1) NOT NULL DEFAULT 0");
        }
        $columns = $wpdb->get_col("DESC {$exams}", 0);
        if (is_array($columns) && !in_array('question_type', $columns, true)) {
            $wpdb->query("ALTER TABLE {$exams} ADD COLUMN question_type varchar(20) NOT NULL DEFAULT 'multiple_choice'");
        }
        if (is_array($columns) && !in_array('default_marks', $columns, true)) {
            $wpdb->query("ALTER TABLE {$exams} ADD COLUMN default_marks decimal(8,2) NOT NULL DEFAULT 1.00");
        }
        $assignments = $wpdb->prefix . 'eh_assignments';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $assignments));
        if ($exists !== $assignments) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset = $wpdb->get_charset_collate();
            dbDelta("CREATE TABLE {$assignments} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                exam_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                assigned_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY exam_user (exam_id, user_id),
                KEY user_id (user_id)
            ) {$charset};");
        }
    }

    /**
     * Publish the student portal page if it does not exist yet.
     *
     * @return int
     */
    public static function create_page() {
        $existing = (int) get_option('eh_portal_page_id');
        if ($existing && get_post_status($existing)) {
            return $existing;
        }
        $page_id = wp_insert_post(
            array(
                'post_title' => 'Exam Hall',
                'post_name' => 'exam-hall',
                'post_content' => '[exam_hall]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
            ),
            true
        );
        if (is_wp_error($page_id)) {
            return 0;
        }
        update_option('eh_portal_page_id', (int) $page_id);
        return (int) $page_id;
    }

    /**
     * Seed one draft exam the first time the plugin is activated.
     */
    public static function maybe_seed() {
        if (get_option('eh_seeded')) {
            return;
        }
        $questions = array(
            array(
                'question_text' => 'What is the capital of France?',
                'option_a' => 'London',
                'option_b' => 'Paris',
                'option_c' => 'Berlin',
                'option_d' => 'Madrid',
                'correct_answer' => 'B',
                'marks' => 1,
            ),
            array(
                'question_text' => 'Which planet is known as the Red Planet?',
                'option_a' => 'Venus',
                'option_b' => 'Jupiter',
                'option_c' => 'Mars',
                'option_d' => 'Saturn',
                'correct_answer' => 'C',
                'marks' => 1,
            ),
            array(
                'question_text' => 'What is 15 × 4?',
                'option_a' => '45',
                'option_b' => '60',
                'option_c' => '54',
                'option_d' => '50',
                'correct_answer' => 'B',
                'marks' => 1,
            ),
            array(
                'question_text' => 'Water freezes at what temperature in Celsius?',
                'option_a' => '0',
                'option_b' => '32',
                'option_c' => '100',
                'option_d' => '-10',
                'correct_answer' => 'A',
                'marks' => 1,
            ),
            array(
                'question_text' => 'Which gas do plants absorb from the air?',
                'option_a' => 'Oxygen',
                'option_b' => 'Nitrogen',
                'option_c' => 'Carbon dioxide',
                'option_d' => 'Hydrogen',
                'correct_answer' => 'C',
                'marks' => 2,
            ),
        );
        $exam_id = EH_Repository::insert_exam(
            array(
                'title' => 'Sample: General knowledge',
                'description' => 'A short practice paper so you can see the student flow. Replace these questions before a real sitting.',
                'instructions' => 'You will need the start code from your email. Answer every question. Unanswered questions score zero. The exam submits itself when the timer ends.',
                'duration_minutes' => 10,
                'allow_retake' => 1,
                'max_attempts' => 3,
                'code_expiry_minutes' => 30,
                'show_correct' => 1,
                'band_distinction' => 80,
                'band_merit' => 70,
                'band_pass' => 50,
                'label_distinction' => 'Distinction',
                'label_merit' => 'Merit',
                'label_pass' => 'Pass',
                'label_fail' => 'Fail',
                'status' => 'draft',
                'available_from' => null,
                'available_until' => null,
            )
        );
        if (is_wp_error($exam_id)) {
            return;
        }
        EH_Repository::append_questions($exam_id, $questions, true);
        update_option('eh_seeded', '1');
    }
}
