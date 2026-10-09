<?php
/**
 * Pure exam rules. This file must not call WordPress so it can be tested on its own.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH') && !defined('EH_LOGIC_TEST')) {
    exit;
}

/**
 * Normalize a multiple-choice letter.
 *
 * @param mixed $value Raw value.
 * @return string A, B, C, D, or an empty string.
 */
function eh_normalize_choice($value) {
    $value = strtoupper(trim((string) $value));
    if ($value === '') {
        return '';
    }
    $letter = substr($value, 0, 1);
    return in_array($letter, array('A', 'B', 'C', 'D'), true) ? $letter : '';
}

/**
 * Resolve a correct-answer cell that may be a letter or the option text.
 *
 * @param mixed $raw     Raw cell.
 * @param array $options Letter => text.
 * @return string
 */
function eh_resolve_correct($raw, array $options) {
    $choice = eh_normalize_choice($raw);
    if ($choice !== '' && isset($options[$choice]) && $options[$choice] !== '') {
        return $choice;
    }
    $needle = strtolower(trim((string) $raw));
    if ($needle === '') {
        return '';
    }
    foreach ($options as $letter => $text) {
        if ($text !== '' && strtolower(trim((string) $text)) === $needle) {
            return $letter;
        }
    }
    return '';
}

/**
 * Non-empty options for a question object or array.
 *
 * @param object|array $question Question.
 * @return array
 */
function eh_question_options($question) {
    $options = array();
    $map = array(
        'A' => 'option_a',
        'B' => 'option_b',
        'C' => 'option_c',
        'D' => 'option_d',
    );
    foreach ($map as $letter => $key) {
        $text = '';
        if (is_object($question) && isset($question->$key)) {
            $text = trim((string) $question->$key);
        } elseif (is_array($question) && isset($question[$key])) {
            $text = trim((string) $question[$key]);
        }
        if ($text !== '') {
            $options[$letter] = $text;
        }
    }
    return $options;
}

/**
 * Validate classification thresholds.
 *
 * @param mixed $distinction Distinction minimum.
 * @param mixed $merit       Merit minimum.
 * @param mixed $pass        Pass minimum.
 * @return array|null
 */
function eh_validate_bands($distinction, $merit, $pass) {
    $d = filter_var(trim((string) $distinction), FILTER_VALIDATE_INT);
    $m = filter_var(trim((string) $merit), FILTER_VALIDATE_INT);
    $p = filter_var(trim((string) $pass), FILTER_VALIDATE_INT);
    if ($d === false || $m === false || $p === false) {
        return null;
    }
    if ($p < 0 || $m < 0 || $d < 1 || $d > 100 || $m > 100 || $p > 100) {
        return null;
    }
    if (!($d > $m && $m > $p)) {
        return null;
    }
    return array(
        'distinction' => $d,
        'merit' => $m,
        'pass' => $p,
    );
}

/**
 * Classify a percentage.
 *
 * @param float $percentage Score percentage.
 * @param array $bands      Thresholds and labels.
 * @return string
 */
function eh_classify($percentage, array $bands) {
    $percentage = (float) $percentage;
    if ($percentage + 0.0000001 >= (float) $bands['distinction']) {
        return (string) $bands['label_distinction'];
    }
    if ($percentage + 0.0000001 >= (float) $bands['merit']) {
        return (string) $bands['label_merit'];
    }
    if ($percentage + 0.0000001 >= (float) $bands['pass']) {
        return (string) $bands['label_pass'];
    }
    return (string) $bands['label_fail'];
}

/**
 * Grade selected answers against a question list.
 *
 * @param array $questions  Each item needs id, correct, and marks.
 * @param array $selections Question id => letter.
 * @return array
 */
function eh_grade(array $questions, array $selections) {
    $score = 0.0;
    $total = 0.0;
    $detail = array();
    foreach ($questions as $question) {
        $id = (int) $question['id'];
        $marks = round((float) $question['marks'], 2);
        $correct = eh_normalize_choice($question['correct']);
        $selected = '';
        if (isset($selections[$id])) {
            $selected = eh_normalize_choice($selections[$id]);
        } elseif (isset($selections[(string) $id])) {
            $selected = eh_normalize_choice($selections[(string) $id]);
        }
        $is_correct = ($selected !== '' && $selected === $correct);
        $awarded = $is_correct ? $marks : 0.0;
        $total += $marks;
        $score += $awarded;
        $detail[] = array(
            'id' => $id,
            'selected' => $selected,
            'correct' => $correct,
            'is_correct' => $is_correct,
            'marks' => $marks,
            'awarded' => $awarded,
        );
    }
    $score = round($score, 2);
    $total = round($total, 2);
    $percentage = $total > 0 ? round(($score / $total) * 100, 2) : 0.0;
    return array(
        'score' => $score,
        'total' => $total,
        'percentage' => $percentage,
        'detail' => $detail,
    );
}

/**
 * Format a percentage for display.
 *
 * @param float $number Percentage.
 * @return string
 */
function eh_format_percentage($number) {
    $number = round((float) $number, 2);
    if (abs($number - round($number)) < 0.001) {
        return (string) ((int) round($number)) . '%';
    }
    $text = number_format($number, 2, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return $text . '%';
}

/**
 * Format a mark without trailing zeros.
 *
 * @param float $number Marks.
 * @return string
 */
function eh_format_marks($number) {
    $number = round((float) $number, 2);
    if (abs($number - round($number)) < 0.001) {
        return (string) ((int) round($number));
    }
    $text = number_format($number, 2, '.', '');
    return rtrim(rtrim($text, '0'), '.');
}

/**
 * Guard CSV cells that spreadsheet apps would treat as formulas.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function eh_csv_safe($value) {
    $value = (string) $value;
    if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Readable temporary password.
 *
 * @return string
 */
function eh_temporary_password() {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < 10; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

/**
 * Six-digit exam start code.
 *
 * @return string
 */
function eh_generate_start_code() {
    do {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } while ($code === '000000');
    return $code;
}

/**
 * Exam question format.
 *
 * @param mixed $value Raw value.
 * @return string multiple_choice or true_false.
 */
function eh_normalize_question_type($value) {
    return $value === 'true_false' ? 'true_false' : 'multiple_choice';
}

/**
 * Question format stored on an exam.
 *
 * @param object|array|null $exam Exam.
 * @return string
 */
function eh_exam_question_type($exam) {
    $raw = 'multiple_choice';
    if (is_object($exam) && isset($exam->question_type)) {
        $raw = $exam->question_type;
    } elseif (is_array($exam) && isset($exam['question_type'])) {
        $raw = $exam['question_type'];
    }
    return eh_normalize_question_type($raw);
}

/**
 * Marks used when a question or CSV row does not set its own.
 *
 * @param object|array|null $exam Exam.
 * @return float
 */
function eh_exam_default_marks($exam) {
    $raw = 1;
    if (is_object($exam) && isset($exam->default_marks)) {
        $raw = $exam->default_marks;
    } elseif (is_array($exam) && isset($exam['default_marks'])) {
        $raw = $exam['default_marks'];
    }
    return eh_clean_marks($raw, 1.0);
}

/**
 * A mark between 0 and 100, or the fallback when the value is unusable.
 *
 * @param mixed $raw      Raw mark.
 * @param float $fallback Fallback.
 * @return float
 */
function eh_clean_marks($raw, $fallback = 1.0) {
    $fallback = round((float) $fallback, 2);
    if ($fallback <= 0 || $fallback > 100) {
        $fallback = 1.0;
    }
    if (is_string($raw)) {
        $raw = str_replace(',', '.', trim($raw));
    }
    if ($raw === '' || $raw === null || !is_numeric($raw)) {
        return $fallback;
    }
    $marks = round((float) $raw, 2);
    if ($marks <= 0 || $marks > 100) {
        return $fallback;
    }
    return $marks;
}

/**
 * Map a true/false answer onto A (True) or B (False).
 *
 * @param mixed $raw Raw cell.
 * @return string
 */
function eh_resolve_true_false($raw) {
    $value = strtolower(trim((string) $raw));
    if (in_array($value, array('true', 't', 'yes', 'y', 'a'), true)) {
        return 'A';
    }
    if (in_array($value, array('false', 'f', 'no', 'n', 'b'), true)) {
        return 'B';
    }
    return '';
}

/**
 * Fixed answers for a true or false question.
 *
 * @return array
 */
function eh_true_false_options() {
    return array(
        'A' => 'True',
        'B' => 'False',
    );
}

/**
 * Parse question rows that have already been split into cells.
 *
 * @param array  $rows          CSV rows.
 * @param string $type          multiple_choice or true_false.
 * @param float  $default_marks Marks used when the marks cell is empty.
 * @return array{questions: array, errors: array}
 */
function eh_parse_question_rows(array $rows, $type = 'multiple_choice', $default_marks = 1.0) {
    $type = eh_normalize_question_type($type);
    $default_marks = eh_clean_marks($default_marks, 1.0);
    $questions = array();
    $errors = array();
    $header_map = null;
    $line = 0;
    $stopped = false;

    foreach ($rows as $row) {
        $line++;
        if (!is_array($row)) {
            continue;
        }
        $clean = array();
        foreach ($row as $cell) {
            $clean[] = $cell === null ? '' : (string) $cell;
        }
        if ($line === 1 && isset($clean[0])) {
            $clean[0] = preg_replace('/^\xEF\xBB\xBF/', '', $clean[0]);
        }
        if (eh_csv_row_empty($clean)) {
            continue;
        }
        if ($header_map === null && eh_csv_looks_like_header($clean)) {
            $header_map = eh_csv_header_map($clean);
            $header_error = eh_question_header_error($header_map, $type, $line);
            if ($header_error !== '') {
                return array(
                    'questions' => array(),
                    'errors' => array(
                        array(
                            'row' => $line,
                            'message' => $header_error,
                        ),
                    ),
                );
            }
            continue;
        }
        if ($header_map === null) {
            $header_map = $type === 'true_false'
                ? array(
                    'question' => 0,
                    'correct_answer' => 1,
                    'marks' => 2,
                )
                : array(
                    'question' => 0,
                    'option_a' => 1,
                    'option_b' => 2,
                    'option_c' => 3,
                    'option_d' => 4,
                    'correct_answer' => 5,
                    'marks' => 6,
                );
        }
        if (count($questions) >= 500) {
            $stopped = true;
            continue;
        }
        $parsed = eh_csv_row_to_question($clean, $header_map, $type, $default_marks);
        if (is_string($parsed)) {
            $errors[] = array(
                'row' => $line,
                'message' => $parsed,
            );
            continue;
        }
        $questions[] = $parsed;
    }

    if ($stopped) {
        $errors[] = array(
            'row' => 0,
            'message' => 'Only the first 500 questions were imported. Split the rest into another file.',
        );
    }

    return array(
        'questions' => $questions,
        'errors' => $errors,
    );
}

/**
 * Explain a question header that does not match the exam format.
 *
 * @param array  $map  Header map.
 * @param string $type Question format.
 * @param int    $line Unused row number kept for callers that want it.
 * @return string
 */
function eh_question_header_error(array $map, $type, $line = 0) {
    unset($line);
    if ($type === 'true_false') {
        if (isset($map['option_a']) || isset($map['option_b'])) {
            return 'This file looks like a multiple choice sheet. Download the true or false template for this exam.';
        }
        if (!isset($map['question'], $map['correct_answer'])) {
            return 'The header must include question and correct_answer columns.';
        }
        return '';
    }
    if (!isset($map['option_a'], $map['option_b']) && isset($map['question'], $map['correct_answer'])) {
        return 'This file looks like a true or false sheet. Download the multiple choice template for this exam.';
    }
    if (!isset($map['question'], $map['option_a'], $map['option_b'], $map['correct_answer'])) {
        return 'The header must include question, option_a, option_b, and correct_answer columns.';
    }
    return '';
}

/**
 * Parse a CSV file of questions.
 *
 * @param string $path          File path.
 * @param string $type          multiple_choice or true_false.
 * @param float  $default_marks Marks used when the marks cell is empty.
 * @return array{questions: array, errors: array}
 */
function eh_parse_question_csv_file($path, $type = 'multiple_choice', $default_marks = 1.0) {
    if (!is_readable($path)) {
        return array(
            'questions' => array(),
            'errors' => array(
                array(
                    'row' => 0,
                    'message' => 'The file could not be read.',
                ),
            ),
        );
    }
    $handle = fopen($path, 'r');
    if (!$handle) {
        return array(
            'questions' => array(),
            'errors' => array(
                array(
                    'row' => 0,
                    'message' => 'The file could not be read.',
                ),
            ),
        );
    }
    $rows = array();
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = $data;
    }
    fclose($handle);
    return eh_parse_question_rows($rows, $type, $default_marks);
}

/**
 * Whether every cell is empty.
 *
 * @param array $row Cells.
 * @return bool
 */
function eh_csv_row_empty(array $row) {
    foreach ($row as $cell) {
        if (trim((string) $cell) !== '') {
            return false;
        }
    }
    return true;
}

/**
 * Whether a row is a header.
 *
 * @param array $row Cells.
 * @return bool
 */
function eh_csv_looks_like_header(array $row) {
    $known = array(
        'question',
        'question_text',
        'questions',
        'prompt',
        'option_a',
        'option_b',
        'option_c',
        'option_d',
        'a',
        'b',
        'c',
        'd',
        'correct_answer',
        'correct',
        'answer',
        'correct_option',
        'marks',
        'mark',
        'points',
        'score',
    );
    $hits = 0;
    foreach ($row as $cell) {
        $key = strtolower(trim((string) $cell));
        if (in_array($key, $known, true)) {
            $hits++;
        }
    }
    return $hits >= 2;
}

/**
 * Map header labels to column indexes.
 *
 * @param array $row Header cells.
 * @return array
 */
function eh_csv_header_map(array $row) {
    $aliases = array(
        'question' => array('question', 'question_text', 'questions', 'prompt'),
        'option_a' => array('option_a', 'a', 'option a', 'choice_a'),
        'option_b' => array('option_b', 'b', 'option b', 'choice_b'),
        'option_c' => array('option_c', 'c', 'option c', 'choice_c'),
        'option_d' => array('option_d', 'd', 'option d', 'choice_d'),
        'correct_answer' => array('correct_answer', 'answer', 'correct', 'correct_option'),
        'marks' => array('marks', 'mark', 'points', 'score'),
    );
    $map = array();
    foreach ($row as $index => $cell) {
        $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell)));
        foreach ($aliases as $field => $names) {
            if (in_array($key, $names, true)) {
                $map[$field] = $index;
            }
        }
    }
    return $map;
}

/**
 * Convert one CSV row into a question or an error string.
 *
 * @param array $row Row cells.
 * @param array $map Header map.
 * @return array|string
 */
function eh_csv_row_to_question(array $row, array $map, $type = 'multiple_choice', $default_marks = 1.0) {
    $type = eh_normalize_question_type($type);
    $default_marks = eh_clean_marks($default_marks, 1.0);
    $question = eh_csv_cell($row, $map, 'question');
    if ($type === 'true_false') {
        return eh_csv_true_false_question($question, eh_csv_cell($row, $map, 'correct_answer'), eh_csv_cell($row, $map, 'marks'), $default_marks);
    }
    $options = array(
        'A' => eh_csv_cell($row, $map, 'option_a'),
        'B' => eh_csv_cell($row, $map, 'option_b'),
        'C' => eh_csv_cell($row, $map, 'option_c'),
        'D' => eh_csv_cell($row, $map, 'option_d'),
    );
    $filled = array();
    foreach ($options as $letter => $text) {
        if ($text !== '') {
            $filled[$letter] = $text;
        }
    }
    if ($question === '') {
        return 'Enter the question text.';
    }
    if (strlen($question) > 5000) {
        return 'Question text must be 5000 characters or fewer.';
    }
    if (count($filled) < 2) {
        return 'Each question needs at least two answer options.';
    }
    foreach ($filled as $text) {
        if (strlen($text) > 1000) {
            return 'Each option must be 1000 characters or fewer.';
        }
    }
    $correct = eh_resolve_correct(eh_csv_cell($row, $map, 'correct_answer'), $filled);
    if ($correct === '') {
        return 'The correct answer must be A, B, C, or D, or the exact text of an option.';
    }
    $marks = eh_csv_marks(eh_csv_cell($row, $map, 'marks'), $default_marks);
    if (is_string($marks)) {
        return $marks;
    }
    return array(
        'question_text' => $question,
        'option_a' => isset($filled['A']) ? $filled['A'] : '',
        'option_b' => isset($filled['B']) ? $filled['B'] : '',
        'option_c' => isset($filled['C']) ? $filled['C'] : '',
        'option_d' => isset($filled['D']) ? $filled['D'] : '',
        'correct_answer' => $correct,
        'marks' => $marks,
    );
}

/**
 * Read a mapped cell.
 *
 * @param array  $row   Row.
 * @param array  $map   Header map.
 * @param string $field Field name.
 * @return string
 */
function eh_csv_cell(array $row, array $map, $field) {
    if (!isset($map[$field])) {
        return '';
    }
    $index = $map[$field];
    if (!isset($row[$index])) {
        return '';
    }
    return trim((string) $row[$index]);
}

/**
 * Build a true or false question, or return an error string.
 *
 * @param string $question      Prompt.
 * @param string $answer        Raw correct answer.
 * @param string $marks_raw     Raw marks cell.
 * @param float  $default_marks Fallback marks.
 * @return array|string
 */
function eh_csv_true_false_question($question, $answer, $marks_raw, $default_marks) {
    if ($question === '') {
        return 'Enter the question text.';
    }
    if (strlen($question) > 5000) {
        return 'Question text must be 5000 characters or fewer.';
    }
    $correct = eh_resolve_true_false($answer);
    if ($correct === '') {
        return 'The correct answer must be True or False.';
    }
    $marks = eh_csv_marks($marks_raw, $default_marks);
    if (is_string($marks)) {
        return $marks;
    }
    return array(
        'question_text' => $question,
        'option_a' => 'True',
        'option_b' => 'False',
        'option_c' => '',
        'option_d' => '',
        'correct_answer' => $correct,
        'marks' => $marks,
    );
}

/**
 * Read a marks cell. An empty cell uses the exam default.
 *
 * @param string $raw     Cell text.
 * @param float  $default Exam default.
 * @return float|string
 */
function eh_csv_marks($raw, $default) {
    if (trim((string) $raw) === '') {
        return eh_clean_marks($default, 1.0);
    }
    $normalized = str_replace(',', '.', trim((string) $raw));
    if (!is_numeric($normalized)) {
        return 'Marks must be a number.';
    }
    $marks = round((float) $normalized, 2);
    if ($marks <= 0 || $marks > 100) {
        return 'Marks must be greater than 0 and at most 100.';
    }
    return $marks;
}

/**
 * Parse student rows that have already been split into cells.
 *
 * @param array $rows CSV rows.
 * @return array{students: array, errors: array}
 */
function eh_parse_student_rows(array $rows) {
    $students = array();
    $errors = array();
    $header_map = null;
    $seen_email = array();
    $seen_number = array();
    $line = 0;
    $stopped = false;

    foreach ($rows as $row) {
        $line++;
        if (!is_array($row)) {
            continue;
        }
        $clean = array();
        foreach ($row as $cell) {
            $clean[] = $cell === null ? '' : (string) $cell;
        }
        if ($line === 1 && isset($clean[0])) {
            $clean[0] = preg_replace('/^\xEF\xBB\xBF/', '', $clean[0]);
        }
        if (eh_csv_row_empty($clean)) {
            continue;
        }
        if ($header_map === null) {
            $header_map = eh_student_header_map($clean);
            if (!isset($header_map['first_name'], $header_map['last_name'], $header_map['email'], $header_map['student_number'])) {
                return array(
                    'students' => array(),
                    'errors' => array(
                        array(
                            'row' => $line,
                            'message' => 'The header must include first_name, last_name, email, and student_number columns.',
                        ),
                    ),
                );
            }
            continue;
        }
        if (count($students) >= 500) {
            $stopped = true;
            continue;
        }
        $parsed = eh_csv_row_to_student($clean, $header_map);
        if (is_string($parsed)) {
            $errors[] = array(
                'row' => $line,
                'message' => $parsed,
            );
            continue;
        }
        $email_key = strtolower($parsed['email']);
        $number_key = strtolower($parsed['student_number']);
        if (isset($seen_email[$email_key])) {
            $errors[] = array(
                'row' => $line,
                'message' => 'That email address is repeated in this file.',
            );
            continue;
        }
        if (isset($seen_number[$number_key])) {
            $errors[] = array(
                'row' => $line,
                'message' => 'That student number is repeated in this file.',
            );
            continue;
        }
        $seen_email[$email_key] = true;
        $seen_number[$number_key] = true;
        $students[] = $parsed;
    }

    if ($header_map === null || (!$students && !$errors)) {
        $errors[] = array(
            'row' => 0,
            'message' => 'The file has no student rows.',
        );
    }
    if ($stopped) {
        $errors[] = array(
            'row' => 0,
            'message' => 'Only the first 500 students were imported. Split the rest into another file.',
        );
    }

    return array(
        'students' => $students,
        'errors' => $errors,
    );
}

/**
 * Parse a CSV file of students.
 *
 * @param string $path File path.
 * @return array{students: array, errors: array}
 */
function eh_parse_student_csv_file($path) {
    if (!is_readable($path)) {
        return array(
            'students' => array(),
            'errors' => array(
                array(
                    'row' => 0,
                    'message' => 'The file could not be read.',
                ),
            ),
        );
    }
    $handle = fopen($path, 'r');
    if (!$handle) {
        return array(
            'students' => array(),
            'errors' => array(
                array(
                    'row' => 0,
                    'message' => 'The file could not be read.',
                ),
            ),
        );
    }
    $rows = array();
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = $data;
    }
    fclose($handle);
    return eh_parse_student_rows($rows);
}

/**
 * Map a student header onto field names.
 *
 * @param array $row Header cells.
 * @return array
 */
function eh_student_header_map(array $row) {
    $aliases = array(
        'first_name' => array('first_name', 'firstname', 'first', 'given_name'),
        'last_name' => array('last_name', 'lastname', 'last', 'surname', 'family_name'),
        'email' => array('email', 'email_address', 'e-mail'),
        'student_number' => array('student_number', 'student_no', 'student_id', 'number'),
        'phone' => array('phone', 'telephone', 'mobile'),
        'programme' => array('programme', 'program', 'class', 'course'),
        'notes' => array('notes', 'note'),
        'status' => array('status'),
    );
    $map = array();
    foreach ($row as $index => $cell) {
        $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell)));
        $key = str_replace(' ', '_', $key);
        foreach ($aliases as $field => $names) {
            if (in_array($key, $names, true)) {
                $map[$field] = $index;
            }
        }
    }
    return $map;
}

/**
 * Convert one CSV row into a student or an error string.
 *
 * @param array $row Row cells.
 * @param array $map Header map.
 * @return array|string
 */
function eh_csv_row_to_student(array $row, array $map) {
    $first = eh_csv_cell($row, $map, 'first_name');
    $last = eh_csv_cell($row, $map, 'last_name');
    $email = strtolower(eh_csv_cell($row, $map, 'email'));
    $number = strtoupper(eh_csv_cell($row, $map, 'student_number'));
    $phone = eh_csv_cell($row, $map, 'phone');
    $programme = eh_csv_cell($row, $map, 'programme');
    $notes = eh_csv_cell($row, $map, 'notes');
    $status = strtolower(eh_csv_cell($row, $map, 'status'));
    if ($first === '' || $last === '') {
        return 'Enter the student first and last name.';
    }
    if (strlen($first) > 60 || strlen($last) > 60) {
        return 'First and last names must be 60 characters or fewer.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    if ($number === '' || strlen($number) > 40) {
        return 'Enter a student number of up to 40 characters.';
    }
    if (strlen($phone) > 40) {
        return 'Phone must be 40 characters or fewer.';
    }
    if (strlen($programme) > 120) {
        return 'Class / programme must be 120 characters or fewer.';
    }
    if (strlen($notes) > 2000) {
        return 'Notes must be 2000 characters or fewer.';
    }
    if ($status === '') {
        $status = 'active';
    }
    if (!in_array($status, array('active', 'suspended'), true)) {
        return 'Status must be active or suspended.';
    }
    return array(
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'student_number' => $number,
        'phone' => $phone,
        'programme' => $programme,
        'notes' => $notes,
        'status' => $status,
    );
}
