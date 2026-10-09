<?php
/**
 * AJAX for saving answers, the countdown, and submission.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logged-in exam requests.
 */
class EH_Ajax {

    /**
     * Register AJAX actions.
     */
    public static function hooks() {
        add_action('wp_ajax_eh_save_answer', array(__CLASS__, 'save_answer'));
        add_action('wp_ajax_eh_submit_exam', array(__CLASS__, 'submit_exam'));
        add_action('wp_ajax_eh_heartbeat', array(__CLASS__, 'heartbeat'));
    }

    /**
     * Save one answer while the sitting is still open.
     */
    public static function save_answer() {
        $attempt = self::attempt_from_request();
        if ($attempt->status === 'submitted') {
            wp_send_json_success(self::result_redirect($attempt));
        }
        if ($attempt->status !== 'in_progress') {
            wp_send_json_error(array('message' => 'closed'), 409);
        }
        if (!eh_attempt_within_grace($attempt)) {
            eh_finalize_attempt($attempt, true);
            wp_send_json_success(self::result_redirect($attempt));
        }
        $question_id = isset($_POST['question_id']) ? (int) $_POST['question_id'] : 0;
        $choice = isset($_POST['choice']) ? wp_unslash($_POST['choice']) : '';
        eh_save_selections($attempt, array($question_id => $choice));
        wp_send_json_success(self::progress($attempt));
    }

    /**
     * Submit the paper. A late request keeps the answers already stored.
     */
    public static function submit_exam() {
        $attempt = self::attempt_from_request();
        if ($attempt->status === 'submitted') {
            wp_send_json_success(self::result_redirect($attempt));
        }
        if ($attempt->status !== 'in_progress') {
            wp_send_json_error(array('message' => 'closed'), 409);
        }
        $auto = !empty($_POST['auto']);
        if (eh_attempt_within_grace($attempt)) {
            $selections = array();
            if (isset($_POST['answers']) && is_array($_POST['answers'])) {
                foreach (wp_unslash($_POST['answers']) as $question_id => $choice) {
                    $selections[(int) $question_id] = $choice;
                }
            }
            eh_save_selections($attempt, $selections);
        } else {
            $auto = true;
        }
        eh_finalize_attempt($attempt, $auto);
        wp_send_json_success(self::result_redirect($attempt));
    }

    /**
     * Tell the browser how many seconds the server still allows.
     */
    public static function heartbeat() {
        $attempt = self::attempt_from_request();
        if ($attempt->status === 'submitted') {
            wp_send_json_success(self::result_redirect($attempt));
        }
        if ($attempt->status !== 'in_progress') {
            wp_send_json_error(array('message' => 'closed'), 409);
        }
        $payload = self::progress($attempt);
        $payload['remaining'] = eh_remaining_seconds($attempt);
        wp_send_json_success($payload);
    }

    /**
     * Load the signed-in student's attempt or stop the request.
     *
     * @return object
     */
    private static function attempt_from_request() {
        $attempt_id = isset($_POST['attempt_id']) ? (int) $_POST['attempt_id'] : 0;
        if (!$attempt_id || !is_user_logged_in() || !eh_user_can_take()) {
            wp_send_json_error(array('message' => 'auth'), 403);
        }
        check_ajax_referer('eh_exam_' . $attempt_id, 'nonce');
        $attempt = EH_Repository::get_attempt($attempt_id);
        if (!$attempt || (int) $attempt->user_id !== get_current_user_id()) {
            wp_send_json_error(array('message' => 'auth'), 403);
        }
        if (get_user_meta(get_current_user_id(), 'eh_status', true) === 'suspended') {
            wp_send_json_error(array('message' => 'suspended'), 403);
        }
        return $attempt;
    }

    /**
     * Result URL payload.
     *
     * @param object $attempt Attempt.
     * @return array
     */
    private static function result_redirect($attempt) {
        return array(
            'redirect' => eh_portal_url(
                array(
                    'eh_view' => 'result',
                    'attempt_id' => (int) $attempt->id,
                )
            ),
        );
    }

    /**
     * Answered and total counts.
     *
     * @param object $attempt Attempt.
     * @return array
     */
    private static function progress($attempt) {
        return array(
            'answered' => EH_Repository::count_saved_answers($attempt->id),
            'total' => count(EH_Repository::questions_for_attempt($attempt)),
        );
    }
}
