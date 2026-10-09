<?php
/**
 * Database access for exams, questions, attempts, and answers.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * All Exam Hall queries.
 */
class EH_Repository {

    /**
     * Prefixed table name.
     *
     * @param string $name exams, questions, attempts, or answers.
     * @return string
     */
    public static function table($name) {
        global $wpdb;
        $tables = array(
            'exams' => $wpdb->prefix . 'eh_exams',
            'questions' => $wpdb->prefix . 'eh_questions',
            'attempts' => $wpdb->prefix . 'eh_attempts',
            'answers' => $wpdb->prefix . 'eh_answers',
            'assignments' => $wpdb->prefix . 'eh_assignments',
        );
        return $tables[$name];
    }

    /**
     * Insert an exam.
     *
     * @param array $data Exam fields.
     * @return int|WP_Error
     */
    public static function insert_exam($data) {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $row = self::exam_row($data);
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $ok = $wpdb->insert(self::table('exams'), $row);
        if (!$ok) {
            return new WP_Error('db_insert', __('The exam could not be saved.', 'exam-hall'));
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Update an exam.
     *
     * @param int   $id   Exam id.
     * @param array $data Exam fields.
     * @return bool
     */
    public static function update_exam($id, $data) {
        global $wpdb;
        $row = self::exam_row($data);
        $row['updated_at'] = gmdate('Y-m-d H:i:s');
        $updated = $wpdb->update(self::table('exams'), $row, array('id' => (int) $id));
        return $updated !== false;
    }

    /**
     * Whitelist exam columns.
     *
     * @param array $data Raw data.
     * @return array
     */
    private static function exam_row($data) {
        return array(
            'title' => $data['title'],
            'description' => $data['description'],
            'instructions' => $data['instructions'],
            'duration_minutes' => (int) $data['duration_minutes'],
            'allow_retake' => (int) $data['allow_retake'] ? 1 : 0,
            'max_attempts' => (int) $data['max_attempts'],
            'code_expiry_minutes' => (int) $data['code_expiry_minutes'],
            'show_correct' => (int) $data['show_correct'] ? 1 : 0,
            'band_distinction' => (int) $data['band_distinction'],
            'band_merit' => (int) $data['band_merit'],
            'band_pass' => (int) $data['band_pass'],
            'label_distinction' => $data['label_distinction'],
            'label_merit' => $data['label_merit'],
            'label_pass' => $data['label_pass'],
            'label_fail' => $data['label_fail'],
            'status' => $data['status'],
            'audience' => isset($data['audience']) && $data['audience'] === 'assigned' ? 'assigned' : 'all',
            'certificate_enabled' => !empty($data['certificate_enabled']) ? 1 : 0,
            'question_type' => isset($data['question_type']) && $data['question_type'] === 'true_false' ? 'true_false' : 'multiple_choice',
            'default_marks' => isset($data['default_marks']) ? eh_clean_marks($data['default_marks'], 1.0) : 1.0,
            'available_from' => !empty($data['available_from']) ? $data['available_from'] : null,
            'available_until' => !empty($data['available_until']) ? $data['available_until'] : null,
        );
    }

    /**
     * Fetch one exam.
     *
     * @param int $id Exam id.
     * @return object|null
     */
    public static function get_exam($id) {
        global $wpdb;
        $table = self::table('exams');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
    }

    /**
     * List exams.
     *
     * @param string $status Optional status.
     * @return array
     */
    public static function list_exams($status = '') {
        global $wpdb;
        $table = self::table('exams');
        if ($status !== '') {
            return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC", $status));
        }
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
    }

    /**
     * Exams a student should see.
     *
     * @param int $user_id Student id.
     * @return array
     */
    public static function exams_for_student($user_id) {
        global $wpdb;
        $exams = self::table('exams');
        $attempts = self::table('attempts');
        $assignments = self::table('assignments');
        $user_id = (int) $user_id;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$exams} WHERE (status = %s AND (audience <> %s OR id IN (SELECT exam_id FROM {$assignments} WHERE user_id = %d))) OR id IN (SELECT exam_id FROM {$attempts} WHERE user_id = %d) ORDER BY created_at DESC",
                'published',
                'assigned',
                $user_id,
                $user_id
            )
        );
    }

    /**
     * Save the question format and the marks used when a row leaves marks blank.
     *
     * @param int    $exam_id Exam id.
     * @param string $type    multiple_choice or true_false.
     * @param float  $marks   Default marks.
     */
    public static function update_question_settings($exam_id, $type, $marks) {
        global $wpdb;
        $wpdb->update(
            self::table('exams'),
            array(
                'question_type' => $type === 'true_false' ? 'true_false' : 'multiple_choice',
                'default_marks' => eh_clean_marks($marks, 1.0),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ),
            array('id' => (int) $exam_id)
        );
    }

    /**
     * Switch an exam between everyone and allocated students.
     *
     * @param int    $exam_id  Exam id.
     * @param string $audience all or assigned.
     */
    public static function set_exam_audience($exam_id, $audience) {
        global $wpdb;
        $wpdb->update(
            self::table('exams'),
            array(
                'audience' => $audience === 'assigned' ? 'assigned' : 'all',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ),
            array('id' => (int) $exam_id),
            array('%s', '%s'),
            array('%d')
        );
    }

    /**
     * Whether this student is allocated to the exam.
     *
     * @param int $exam_id Exam id.
     * @param int $user_id Student id.
     * @return bool
     */
    public static function is_assigned($exam_id, $user_id) {
        global $wpdb;
        $table = self::table('assignments');
        $found = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE exam_id = %d AND user_id = %d", (int) $exam_id, (int) $user_id));
        return (int) $found > 0;
    }

    /**
     * Student ids allocated to an exam.
     *
     * @param int $exam_id Exam id.
     * @return array
     */
    public static function assigned_user_ids($exam_id) {
        global $wpdb;
        $table = self::table('assignments');
        $ids = $wpdb->get_col($wpdb->prepare("SELECT user_id FROM {$table} WHERE exam_id = %d", (int) $exam_id));
        return array_map('intval', (array) $ids);
    }

    /**
     * Exam ids allocated to a student.
     *
     * @param int $user_id Student id.
     * @return array
     */
    public static function assigned_exam_ids($user_id) {
        global $wpdb;
        $table = self::table('assignments');
        $ids = $wpdb->get_col($wpdb->prepare("SELECT exam_id FROM {$table} WHERE user_id = %d", (int) $user_id));
        return array_map('intval', (array) $ids);
    }

    /**
     * Allocation counts keyed by exam id.
     *
     * @param array $exam_ids Exam ids.
     * @return array
     */
    public static function assignment_counts_for($exam_ids) {
        global $wpdb;
        $exam_ids = array_filter(array_map('intval', (array) $exam_ids));
        if (!$exam_ids) {
            return array();
        }
        $table = self::table('assignments');
        $in = implode(',', $exam_ids);
        $rows = $wpdb->get_results("SELECT exam_id, COUNT(*) AS total FROM {$table} WHERE exam_id IN ({$in}) GROUP BY exam_id");
        $counts = array();
        foreach ($rows as $row) {
            $counts[(int) $row->exam_id] = (int) $row->total;
        }
        return $counts;
    }

    /**
     * Add students to an exam without removing the others.
     *
     * @param int   $exam_id  Exam id.
     * @param array $user_ids Student ids.
     */
    public static function assign_students($exam_id, $user_ids) {
        global $wpdb;
        $table = self::table('assignments');
        $now = gmdate('Y-m-d H:i:s');
        foreach ((array) $user_ids as $user_id) {
            $user_id = (int) $user_id;
            if ($user_id < 1 || self::is_assigned($exam_id, $user_id)) {
                continue;
            }
            $wpdb->insert(
                $table,
                array(
                    'exam_id' => (int) $exam_id,
                    'user_id' => $user_id,
                    'assigned_at' => $now,
                ),
                array('%d', '%d', '%s')
            );
        }
    }

    /**
     * Remove students from an exam.
     *
     * @param int   $exam_id  Exam id.
     * @param array $user_ids Student ids.
     */
    public static function unassign_students($exam_id, $user_ids) {
        global $wpdb;
        $ids = array_filter(array_map('intval', (array) $user_ids));
        if (!$ids) {
            return;
        }
        $table = self::table('assignments');
        $in = implode(',', $ids);
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE exam_id = %d AND user_id IN ({$in})", (int) $exam_id));
    }

    /**
     * Replace the allocated list for one exam.
     *
     * @param int   $exam_id  Exam id.
     * @param array $user_ids Student ids.
     */
    public static function replace_exam_assignments($exam_id, $user_ids) {
        global $wpdb;
        $wpdb->delete(self::table('assignments'), array('exam_id' => (int) $exam_id), array('%d'));
        self::assign_students($exam_id, $user_ids);
    }

    /**
     * Remove every allocation for an exam.
     *
     * @param int $exam_id Exam id.
     */
    public static function delete_assignments($exam_id) {
        global $wpdb;
        $wpdb->delete(self::table('assignments'), array('exam_id' => (int) $exam_id), array('%d'));
    }

    /**
     * Update one student's allocations for exams that are limited to a list.
     *
     * @param int   $user_id  Student id.
     * @param array $exam_ids Exam ids that should stay allocated.
     */
    public static function sync_student_allocations($user_id, $exam_ids) {
        $wanted = array();
        foreach ((array) $exam_ids as $exam_id) {
            $wanted[(int) $exam_id] = true;
        }
        foreach (self::list_exams() as $exam) {
            $audience = isset($exam->audience) ? $exam->audience : 'all';
            if ($audience !== 'assigned') {
                continue;
            }
            if (isset($wanted[(int) $exam->id])) {
                self::assign_students($exam->id, array($user_id));
            } else {
                self::unassign_students($exam->id, array($user_id));
            }
        }
    }

    /**
     * Delete an exam row.
     *
     * @param int $id Exam id.
     */
    public static function delete_exam($id) {
        global $wpdb;
        $wpdb->delete(self::table('exams'), array('id' => (int) $id), array('%d'));
        self::delete_assignments($id);
    }

    /**
     * Count attempts for an exam.
     *
     * @param int    $exam_id Exam id.
     * @param string $status  Optional status.
     * @return int
     */
    public static function attempt_count($exam_id, $status = '') {
        global $wpdb;
        $table = self::table('attempts');
        if ($status !== '') {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE exam_id = %d AND status = %s", (int) $exam_id, $status));
        }
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE exam_id = %d", (int) $exam_id));
    }

    /**
     * Question counts keyed by exam id.
     *
     * @param array $exam_ids Exam ids.
     * @return array
     */
    public static function question_counts_for($exam_ids) {
        global $wpdb;
        $exam_ids = array_filter(array_map('intval', (array) $exam_ids));
        if (!$exam_ids) {
            return array();
        }
        $table = self::table('questions');
        $in = implode(',', $exam_ids);
        $rows = $wpdb->get_results("SELECT exam_id, COUNT(*) AS total FROM {$table} WHERE exam_id IN ({$in}) GROUP BY exam_id");
        $counts = array();
        foreach ($rows as $row) {
            $counts[(int) $row->exam_id] = (int) $row->total;
        }
        return $counts;
    }

    /**
     * Count questions on an exam.
     *
     * @param int $exam_id Exam id.
     * @return int
     */
    public static function count_questions($exam_id) {
        global $wpdb;
        $table = self::table('questions');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE exam_id = %d", (int) $exam_id));
    }

    /**
     * Questions in paper order.
     *
     * @param int $exam_id Exam id.
     * @return array
     */
    public static function get_questions($exam_id) {
        global $wpdb;
        $table = self::table('questions');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE exam_id = %d ORDER BY sort_order ASC, id ASC", (int) $exam_id));
    }

    /**
     * One question.
     *
     * @param int $id Question id.
     * @return object|null
     */
    public static function get_question($id) {
        global $wpdb;
        $table = self::table('questions');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
    }

    /**
     * Replace the whole question bank.
     *
     * @param int   $exam_id   Exam id.
     * @param array $questions Parsed questions.
     */
    public static function replace_questions($exam_id, $questions) {
        global $wpdb;
        $wpdb->delete(self::table('questions'), array('exam_id' => (int) $exam_id), array('%d'));
        self::append_questions($exam_id, $questions, true);
    }

    /**
     * Add questions after the current paper.
     *
     * @param int   $exam_id   Exam id.
     * @param array $questions Parsed questions.
     * @param bool  $reset     Start sort order at 1.
     */
    public static function append_questions($exam_id, $questions, $reset = false) {
        global $wpdb;
        $table = self::table('questions');
        $order = 0;
        if (!$reset) {
            $order = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(sort_order) FROM {$table} WHERE exam_id = %d", (int) $exam_id));
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach ($questions as $question) {
            $order++;
            $wpdb->insert(
                $table,
                array(
                    'exam_id' => (int) $exam_id,
                    'question_text' => $question['question_text'],
                    'option_a' => $question['option_a'],
                    'option_b' => $question['option_b'],
                    'option_c' => $question['option_c'],
                    'option_d' => $question['option_d'],
                    'correct_answer' => $question['correct_answer'],
                    'marks' => $question['marks'],
                    'sort_order' => $order,
                    'created_at' => $now,
                )
            );
        }
    }

    /**
     * Insert or update one question.
     *
     * @param array $data Question fields, including exam_id. id is optional.
     * @return int|WP_Error
     */
    public static function save_question($data) {
        global $wpdb;
        $table = self::table('questions');
        $row = array(
            'exam_id' => (int) $data['exam_id'],
            'question_text' => $data['question_text'],
            'option_a' => $data['option_a'],
            'option_b' => $data['option_b'],
            'option_c' => $data['option_c'],
            'option_d' => $data['option_d'],
            'correct_answer' => $data['correct_answer'],
            'marks' => $data['marks'],
        );
        if (!empty($data['id'])) {
            $updated = $wpdb->update($table, $row, array('id' => (int) $data['id']));
            if ($updated === false) {
                return new WP_Error('db_update', __('The question could not be saved.', 'exam-hall'));
            }
            return (int) $data['id'];
        }
        $order = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(sort_order) FROM {$table} WHERE exam_id = %d", (int) $data['exam_id']));
        $row['sort_order'] = $order + 1;
        $row['created_at'] = gmdate('Y-m-d H:i:s');
        $ok = $wpdb->insert($table, $row);
        if (!$ok) {
            return new WP_Error('db_insert', __('The question could not be saved.', 'exam-hall'));
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Delete one question.
     *
     * @param int $id Question id.
     */
    public static function delete_question($id) {
        global $wpdb;
        $wpdb->delete(self::table('questions'), array('id' => (int) $id), array('%d'));
    }

    /**
     * Delete every question on an exam.
     *
     * @param int $exam_id Exam id.
     */
    public static function delete_questions($exam_id) {
        global $wpdb;
        $wpdb->delete(self::table('questions'), array('exam_id' => (int) $exam_id), array('%d'));
    }

    /**
     * Swap a question with its neighbour.
     *
     * @param int    $question_id Question id.
     * @param string $direction   up or down.
     * @return bool
     */
    public static function move_question($question_id, $direction) {
        $question = self::get_question($question_id);
        if (!$question) {
            return false;
        }
        $all = self::get_questions($question->exam_id);
        $index = -1;
        foreach ($all as $i => $row) {
            if ((int) $row->id === (int) $question_id) {
                $index = $i;
            }
        }
        $swap_with = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index < 0 || !isset($all[$swap_with])) {
            return false;
        }
        $current = (int) $all[$index]->sort_order;
        $other = (int) $all[$swap_with]->sort_order;
        if ($current === $other) {
            $other = $direction === 'up' ? $current - 1 : $current + 1;
        }
        self::set_sort_order($all[$index]->id, $other);
        self::set_sort_order($all[$swap_with]->id, $current);
        return true;
    }

    /**
     * Update sort order.
     *
     * @param int $id    Question id.
     * @param int $order Sort order.
     */
    private static function set_sort_order($id, $order) {
        global $wpdb;
        $wpdb->update(self::table('questions'), array('sort_order' => (int) $order), array('id' => (int) $id), array('%d'), array('%d'));
    }

    /**
     * Create a code-pending attempt.
     *
     * @param array $data Attempt fields.
     * @return int|WP_Error
     */
    public static function insert_attempt($data) {
        global $wpdb;
        $ok = $wpdb->insert(
            self::table('attempts'),
            array(
                'exam_id' => (int) $data['exam_id'],
                'user_id' => (int) $data['user_id'],
                'start_code' => $data['start_code'],
                'code_expires_at' => $data['code_expires_at'],
                'code_failures' => 0,
                'code_locked_until' => null,
                'score' => 0,
                'total_marks' => 0,
                'percentage' => 0,
                'classification' => '',
                'auto_submitted' => 0,
                'status' => 'code_pending',
                'question_ids' => '',
                'created_at' => gmdate('Y-m-d H:i:s'),
            )
        );
        if (!$ok) {
            return new WP_Error('db_insert', __('The exam attempt could not be created.', 'exam-hall'));
        }
        return (int) $wpdb->insert_id;
    }

    /**
     * Update attempt columns.
     *
     * @param int   $id   Attempt id.
     * @param array $data Columns.
     * @return bool
     */
    public static function update_attempt($id, $data) {
        global $wpdb;
        $updated = $wpdb->update(self::table('attempts'), $data, array('id' => (int) $id));
        return $updated !== false;
    }

    /**
     * One attempt.
     *
     * @param int $id Attempt id.
     * @return object|null
     */
    public static function get_attempt($id) {
        global $wpdb;
        $table = self::table('attempts');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
    }

    /**
     * Newest in-progress or code-pending attempt.
     *
     * @param int $exam_id Exam id.
     * @param int $user_id Student id.
     * @return object|null
     */
    public static function get_active_attempt($exam_id, $user_id) {
        global $wpdb;
        $table = self::table('attempts');
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE exam_id = %d AND user_id = %d AND status IN ('in_progress','code_pending') ORDER BY CASE status WHEN 'in_progress' THEN 0 ELSE 1 END, id DESC LIMIT 1",
                (int) $exam_id,
                (int) $user_id
            )
        );
    }

    /**
     * Count every attempt a student has, including unfinished ones.
     *
     * @param int $user_id Student id.
     * @return int
     */
    public static function count_user_attempts($user_id) {
        global $wpdb;
        $table = self::table('attempts');
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = %d", (int) $user_id));
    }

    /**
     * Submitted attempt count for one student and exam.
     *
     * @param int $exam_id Exam id.
     * @param int $user_id Student id.
     * @return int
     */
    public static function count_submitted($exam_id, $user_id) {
        global $wpdb;
        $table = self::table('attempts');
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE exam_id = %d AND user_id = %d AND status = %s",
                (int) $exam_id,
                (int) $user_id,
                'submitted'
            )
        );
    }

    /**
     * Active attempts for a student, keyed by exam id.
     *
     * @param int $user_id Student id.
     * @return array
     */
    public static function active_attempts_for_user($user_id) {
        global $wpdb;
        $table = self::table('attempts');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND status IN ('in_progress','code_pending') ORDER BY id DESC",
                (int) $user_id
            )
        );
        $map = array();
        foreach ($rows as $row) {
            $exam_id = (int) $row->exam_id;
            if (!isset($map[$exam_id]) || $row->status === 'in_progress') {
                $map[$exam_id] = $row;
            }
        }
        return $map;
    }

    /**
     * Submitted counts keyed by exam id.
     *
     * @param int $user_id Student id.
     * @return array
     */
    public static function submitted_counts_for_user($user_id) {
        global $wpdb;
        $table = self::table('attempts');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT exam_id, COUNT(*) AS total FROM {$table} WHERE user_id = %d AND status = %s GROUP BY exam_id",
                (int) $user_id,
                'submitted'
            )
        );
        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->exam_id] = (int) $row->total;
        }
        return $map;
    }

    /**
     * Recent attempts for a student.
     *
     * @param int $user_id Student id.
     * @param int $limit   Maximum rows.
     * @return array
     */
    public static function attempts_for_user($user_id, $limit = 50) {
        global $wpdb;
        $attempts = self::table('attempts');
        $exams = self::table('exams');
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, e.title AS exam_title, e.band_pass, e.label_distinction, e.label_merit, e.label_pass, e.label_fail, e.band_distinction, e.band_merit FROM {$attempts} a INNER JOIN {$exams} e ON e.id = a.exam_id WHERE a.user_id = %d ORDER BY a.id DESC LIMIT %d",
                (int) $user_id,
                (int) $limit
            )
        );
    }

    /**
     * In-progress attempts whose deadline has passed.
     *
     * @param int $user_id Optional student id.
     * @return array
     */
    public static function overdue_attempts($user_id = 0) {
        global $wpdb;
        $table = self::table('attempts');
        $now = gmdate('Y-m-d H:i:s');
        if ($user_id) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE user_id = %d AND status = %s AND deadline_at IS NOT NULL AND deadline_at < %s",
                    (int) $user_id,
                    'in_progress',
                    $now
                )
            );
        }
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND deadline_at IS NOT NULL AND deadline_at < %s",
                'in_progress',
                $now
            )
        );
    }

    /**
     * The paper frozen onto an attempt, or the live paper if it has not started.
     *
     * @param object $attempt Attempt.
     * @return array
     */
    public static function questions_for_attempt($attempt) {
        if (!empty($attempt->question_snapshot)) {
            $data = json_decode($attempt->question_snapshot, true);
            if (is_array($data) && $data) {
                $questions = array();
                foreach ($data as $row) {
                    if (is_array($row)) {
                        $questions[] = (object) $row;
                    }
                }
                if ($questions) {
                    return $questions;
                }
            }
        }
        return self::get_questions($attempt->exam_id);
    }

    /**
     * Save one selected letter.
     *
     * @param int    $attempt_id  Attempt id.
     * @param int    $question_id Question id.
     * @param string $letter      A–D.
     */
    public static function save_selection($attempt_id, $question_id, $letter) {
        global $wpdb;
        $table = self::table('answers');
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE attempt_id = %d AND question_id = %d",
                (int) $attempt_id,
                (int) $question_id
            )
        );
        $now = gmdate('Y-m-d H:i:s');
        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'selected_answer' => $letter,
                    'updated_at' => $now,
                ),
                array('id' => $existing),
                array('%s', '%s'),
                array('%d')
            );
            return;
        }
        $wpdb->insert(
            $table,
            array(
                'attempt_id' => (int) $attempt_id,
                'question_id' => (int) $question_id,
                'selected_answer' => $letter,
                'is_correct' => 0,
                'marks_awarded' => 0,
                'updated_at' => $now,
            )
        );
    }

    /**
     * Selected letters keyed by question id.
     *
     * @param int $attempt_id Attempt id.
     * @return array
     */
    public static function selection_map($attempt_id) {
        $map = array();
        foreach (self::answer_rows($attempt_id) as $row) {
            $map[(int) $row->question_id] = (string) $row->selected_answer;
        }
        return $map;
    }

    /**
     * Answer rows keyed by question id.
     *
     * @param int $attempt_id Attempt id.
     * @return array
     */
    public static function answer_rows($attempt_id) {
        global $wpdb;
        $table = self::table('answers');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE attempt_id = %d", (int) $attempt_id));
        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->question_id] = $row;
        }
        return $map;
    }

    /**
     * Count non-empty saved answers.
     *
     * @param int $attempt_id Attempt id.
     * @return int
     */
    public static function count_saved_answers($attempt_id) {
        global $wpdb;
        $table = self::table('answers');
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE attempt_id = %d AND selected_answer <> ''",
                (int) $attempt_id
            )
        );
    }

    /**
     * Finish an attempt once. Later calls leave the first result in place.
     *
     * @param int    $attempt_id      Attempt id.
     * @param array  $graded          Grade payload.
     * @param string $classification  Classification label.
     * @param bool   $auto            Auto-submitted.
     * @return bool
     */
    public static function complete_attempt($attempt_id, $graded, $classification, $auto) {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $updated = $wpdb->update(
            self::table('attempts'),
            array(
                'score' => $graded['score'],
                'total_marks' => $graded['total'],
                'percentage' => $graded['percentage'],
                'classification' => $classification,
                'auto_submitted' => $auto ? 1 : 0,
                'status' => 'submitted',
                'submitted_at' => $now,
            ),
            array(
                'id' => (int) $attempt_id,
                'status' => 'in_progress',
            )
        );
        if (!$updated) {
            return false;
        }
        foreach ($graded['detail'] as $row) {
            self::write_graded_answer((int) $attempt_id, $row, $now);
        }
        return true;
    }

    /**
     * Store correctness for one answer.
     *
     * @param int    $attempt_id Attempt id.
     * @param array  $row        Grade detail row.
     * @param string $now        GMT timestamp.
     */
    private static function write_graded_answer($attempt_id, $row, $now) {
        global $wpdb;
        $table = self::table('answers');
        $existing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE attempt_id = %d AND question_id = %d",
                $attempt_id,
                (int) $row['id']
            )
        );
        $data = array(
            'selected_answer' => $row['selected'],
            'is_correct' => $row['is_correct'] ? 1 : 0,
            'marks_awarded' => $row['awarded'],
            'updated_at' => $now,
        );
        if ($existing) {
            $wpdb->update($table, $data, array('id' => $existing));
            return;
        }
        $data['attempt_id'] = $attempt_id;
        $data['question_id'] = (int) $row['id'];
        $wpdb->insert($table, $data);
    }

    /**
     * Filtered submitted attempts for the admin results screen.
     *
     * @param array $args Filter arguments.
     * @return array
     */
    public static function query_attempts($args) {
        global $wpdb;
        $attempts = self::table('attempts');
        $exams = self::table('exams');
        $where = array('a.status = %s');
        $params = array(isset($args['status']) ? $args['status'] : 'submitted');
        if (!empty($args['exam_id'])) {
            $where[] = 'a.exam_id = %d';
            $params[] = (int) $args['exam_id'];
        }
        if (!empty($args['user_id'])) {
            $where[] = 'a.user_id = %d';
            $params[] = (int) $args['user_id'];
        }
        if (!empty($args['classification'])) {
            $where[] = 'a.classification = %s';
            $params[] = $args['classification'];
        }
        $join = "INNER JOIN {$exams} e ON e.id = a.exam_id INNER JOIN {$wpdb->users} u ON u.ID = a.user_id";
        if (!empty($args['search'])) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        $where_sql = implode(' AND ', $where);
        $count_sql = "SELECT COUNT(*) FROM {$attempts} a {$join} WHERE {$where_sql}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 20;
        $paged = max(1, isset($args['paged']) ? (int) $args['paged'] : 1);
        $offset = ($paged - 1) * $per_page;
        $sql = "SELECT a.*, e.title AS exam_title, u.display_name, u.user_email FROM {$attempts} a {$join} WHERE {$where_sql} ORDER BY a.submitted_at DESC, a.id DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, array($per_page, $offset))));
        return array(
            'rows' => $rows,
            'total' => $total,
        );
    }

    /**
     * Classification labels that already appear on results.
     *
     * @return array
     */
    public static function classifications_in_use() {
        global $wpdb;
        $table = self::table('attempts');
        return $wpdb->get_col("SELECT DISTINCT classification FROM {$table} WHERE status = 'submitted' AND classification <> '' ORDER BY classification ASC");
    }

    /**
     * Pending start codes for the admin dashboard.
     *
     * @param int $limit Maximum rows.
     * @return array
     */
    public static function pending_codes($limit = 8) {
        global $wpdb;
        $attempts = self::table('attempts');
        $exams = self::table('exams');
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, e.title AS exam_title, u.display_name, u.user_email FROM {$attempts} a INNER JOIN {$exams} e ON e.id = a.exam_id INNER JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE a.status = %s ORDER BY a.created_at DESC LIMIT %d",
                'code_pending',
                (int) $limit
            )
        );
    }

    /**
     * Recent submitted attempts.
     *
     * @param int $limit Maximum rows.
     * @return array
     */
    public static function recent_results($limit = 8) {
        global $wpdb;
        $attempts = self::table('attempts');
        $exams = self::table('exams');
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, e.title AS exam_title, u.display_name FROM {$attempts} a INNER JOIN {$exams} e ON e.id = a.exam_id INNER JOIN {$wpdb->users} u ON u.ID = a.user_id WHERE a.status = %s ORDER BY a.submitted_at DESC LIMIT %d",
                'submitted',
                (int) $limit
            )
        );
    }

    /**
     * Dashboard totals.
     *
     * @return array
     */
    public static function stats() {
        global $wpdb;
        $counts = count_users();
        $students = isset($counts['avail_roles']['exam_student']) ? (int) $counts['avail_roles']['exam_student'] : 0;
        $exams = self::table('exams');
        $attempts = self::table('attempts');
        $published = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$exams} WHERE status = %s", 'published'));
        $submitted = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$attempts} WHERE status = %s", 'submitted'));
        $average = $wpdb->get_var($wpdb->prepare("SELECT AVG(percentage) FROM {$attempts} WHERE status = %s", 'submitted'));
        $start = new DateTime('today', wp_timezone());
        $start->setTimezone(new DateTimeZone('UTC'));
        $today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$attempts} WHERE status = %s AND submitted_at >= %s",
                'submitted',
                $start->format('Y-m-d H:i:s')
            )
        );
        return array(
            'students' => $students,
            'published' => $published,
            'submitted' => $submitted,
            'average' => $average === null ? null : round((float) $average, 1),
            'today' => $today,
        );
    }

    /**
     * Submitted counts keyed by user id.
     *
     * @param array $user_ids User ids.
     * @return array
     */
    public static function attempt_counts_for_users($user_ids) {
        global $wpdb;
        $user_ids = array_filter(array_map('intval', (array) $user_ids));
        if (!$user_ids) {
            return array();
        }
        $table = self::table('attempts');
        $in = implode(',', $user_ids);
        $rows = $wpdb->get_results("SELECT user_id, COUNT(*) AS total FROM {$table} WHERE status = 'submitted' AND user_id IN ({$in}) GROUP BY user_id");
        $map = array();
        foreach ($rows as $row) {
            $map[(int) $row->user_id] = (int) $row->total;
        }
        return $map;
    }

    /**
     * User id that already owns a student number.
     *
     * @param string $number Student number.
     * @return int
     */
    public static function student_number_owner($number) {
        global $wpdb;
        $owner = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'eh_student_number',
                $number
            )
        );
        return $owner ? (int) $owner : 0;
    }

    /**
     * Search exam student accounts.
     *
     * @param string $search   Search text.
     * @param int    $paged    Page number.
     * @param int    $per_page Page size.
     * @return array
     */
    public static function list_students($search, $paged, $per_page) {
        global $wpdb;
        $paged = max(1, (int) $paged);
        $per_page = max(1, (int) $per_page);
        $offset = ($paged - 1) * $per_page;
        $cap_key = $wpdb->prefix . 'capabilities';
        $role_like = '%' . $wpdb->esc_like('"exam_student"') . '%';
        $where = '';
        $params = array($cap_key, $role_like);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where = ' AND (u.display_name LIKE %s OR u.user_email LIKE %s OR sn.meta_value LIKE %s OR pg.meta_value LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }
        $count_sql = "SELECT COUNT(DISTINCT u.ID) FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} cap ON cap.user_id = u.ID AND cap.meta_key = %s AND cap.meta_value LIKE %s LEFT JOIN {$wpdb->usermeta} sn ON sn.user_id = u.ID AND sn.meta_key = 'eh_student_number' LEFT JOIN {$wpdb->usermeta} pg ON pg.user_id = u.ID AND pg.meta_key = 'eh_programme' WHERE 1=1 {$where}";
        $total = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        $sql = "SELECT DISTINCT u.ID FROM {$wpdb->users} u INNER JOIN {$wpdb->usermeta} cap ON cap.user_id = u.ID AND cap.meta_key = %s AND cap.meta_value LIKE %s LEFT JOIN {$wpdb->usermeta} sn ON sn.user_id = u.ID AND sn.meta_key = 'eh_student_number' LEFT JOIN {$wpdb->usermeta} pg ON pg.user_id = u.ID AND pg.meta_key = 'eh_programme' WHERE 1=1 {$where} ORDER BY u.user_registered DESC LIMIT %d OFFSET %d";
        $ids = $wpdb->get_col($wpdb->prepare($sql, array_merge($params, array($per_page, $offset))));
        $users = array();
        foreach ($ids as $id) {
            $user = get_user_by('id', (int) $id);
            if ($user) {
                $users[] = $user;
            }
        }
        return array(
            'users' => $users,
            'total' => $total,
        );
    }
}
