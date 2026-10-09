<?php
/**
 * Admin notices for Exam Hall screens.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_array($flash ?? null) && !empty($flash['errors']) && is_array($flash['errors'])) {
    echo '<div class="notice notice-error"><ul class="eh-error-list">';
    foreach ($flash['errors'] as $eh_error) {
        echo '<li>' . esc_html($eh_error) . '</li>';
    }
    echo '</ul></div>';
}

$eh_code = isset($_GET['eh_msg']) ? sanitize_key(wp_unslash($_GET['eh_msg'])) : '';
if ($eh_code === 'questions_imported' || $eh_code === 'students_imported') {
    $eh_imported = isset($_GET['imported']) ? (int) $_GET['imported'] : 0;
    $eh_skipped = isset($_GET['skipped']) ? (int) $_GET['skipped'] : 0;
    if ($eh_code === 'questions_imported') {
        $eh_text = sprintf(
            /* translators: %d: imported count */
            _n('Imported %d question.', 'Imported %d questions.', $eh_imported, 'exam-hall'),
            $eh_imported
        );
    } else {
        $eh_text = sprintf(
            /* translators: %d: imported count */
            _n('Imported %d student.', 'Imported %d students.', $eh_imported, 'exam-hall'),
            $eh_imported
        );
    }
    if ($eh_skipped > 0) {
        $eh_text .= ' ' . sprintf(
            /* translators: %d: skipped row count */
            _n('%d row was skipped.', '%d rows were skipped.', $eh_skipped, 'exam-hall'),
            $eh_skipped
        );
    }
    echo '<div class="notice notice-success"><p>' . esc_html($eh_text) . '</p></div>';
} elseif ($eh_code !== '') {
    $eh_message = eh_admin_message($eh_code);
    if ($eh_message) {
        $eh_class = $eh_message[0] === 'error' ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($eh_class) . '"><p>' . esc_html($eh_message[1]) . '</p></div>';
    }
}
