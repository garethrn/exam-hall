<?php
/**
 * Email sent to students for accounts, passwords, and start codes.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HTML mailer.
 */
class EH_Emails {

    /**
     * Send one HTML email.
     *
     * @param string $to      Recipient.
     * @param string $subject Subject.
     * @param string $heading Heading inside the card.
     * @param string $html    Trusted inner HTML.
     * @return bool
     */
    public static function send($to, $subject, $heading, $html) {
        $subject = str_replace(array("\r", "\n"), ' ', $subject);
        add_filter('wp_mail_from', array(__CLASS__, 'from_email'));
        add_filter('wp_mail_from_name', array(__CLASS__, 'from_name'));
        add_filter('wp_mail_content_type', array(__CLASS__, 'html_type'));
        $sent = false;
        try {
            $sent = wp_mail($to, $subject, self::wrap($heading, $html));
        } finally {
            remove_filter('wp_mail_from', array(__CLASS__, 'from_email'));
            remove_filter('wp_mail_from_name', array(__CLASS__, 'from_name'));
            remove_filter('wp_mail_content_type', array(__CLASS__, 'html_type'));
        }
        return (bool) $sent;
    }

    /**
     * HTML content type.
     *
     * @return string
     */
    public static function html_type() {
        return 'text/html';
    }

    /**
     * From address.
     *
     * @param string $email Default.
     * @return string
     */
    public static function from_email($email) {
        $custom = get_option('eh_from_email');
        return is_email($custom) ? $custom : $email;
    }

    /**
     * From name.
     *
     * @param string $name Default.
     * @return string
     */
    public static function from_name($name) {
        $custom = trim((string) get_option('eh_from_name'));
        return $custom !== '' ? $custom : $name;
    }

    /**
     * Welcome a new student.
     *
     * @param WP_User $user     Student.
     * @param string  $password Temporary password.
     * @return bool
     */
    public static function welcome($user, $password) {
        $url = eh_portal_url();
        $body = '<p>' . sprintf(
            /* translators: %s: student first name */
            esc_html__('Hello %s,', 'exam-hall'),
            esc_html($user->first_name ? $user->first_name : $user->display_name)
        ) . '</p>';
        $body .= '<p>' . esc_html__('An exam administrator created your Exam Hall account. Sign in with this email address and the temporary password below.', 'exam-hall') . '</p>';
        $body .= '<p><strong>' . esc_html__('Email', 'exam-hall') . '</strong><br>' . esc_html($user->user_email) . '</p>';
        $body .= '<p><strong>' . esc_html__('Temporary password', 'exam-hall') . '</strong><br>' . esc_html($password) . '</p>';
        $body .= '<p>' . esc_html__('Before an exam starts, Exam Hall emails you a start code. The timer begins only after you enter that code.', 'exam-hall') . '</p>';
        $body .= self::button($url, __('Open Exam Hall', 'exam-hall'));
        $body .= '<p>' . esc_html__('You can change this password after signing in, or reset it from the sign-in page.', 'exam-hall') . '</p>';
        return self::send($user->user_email, __('Your Exam Hall account is ready', 'exam-hall'), __('Your exam account', 'exam-hall'), $body);
    }

    /**
     * Tell a student an administrator reset their password.
     *
     * @param WP_User $user     Student.
     * @param string  $password Temporary password.
     * @return bool
     */
    public static function admin_reset($user, $password) {
        $url = eh_portal_url();
        $body = '<p>' . sprintf(
            /* translators: %s: student first name */
            esc_html__('Hello %s,', 'exam-hall'),
            esc_html($user->first_name ? $user->first_name : $user->display_name)
        ) . '</p>';
        $body .= '<p>' . esc_html__('An exam administrator reset your Exam Hall password.', 'exam-hall') . '</p>';
        $body .= '<p><strong>' . esc_html__('Temporary password', 'exam-hall') . '</strong><br>' . esc_html($password) . '</p>';
        $body .= self::button($url, __('Sign in', 'exam-hall'));
        $body .= '<p>' . esc_html__('If you were not expecting this, contact your exam administrator.', 'exam-hall') . '</p>';
        return self::send($user->user_email, __('Your Exam Hall password was reset', 'exam-hall'), __('Password reset', 'exam-hall'), $body);
    }

    /**
     * Send a self-service reset link.
     *
     * @param WP_User $user Student.
     * @param string  $url  Reset URL.
     * @return bool
     */
    public static function forgot_password($user, $url) {
        $body = '<p>' . sprintf(
            /* translators: %s: student first name */
            esc_html__('Hello %s,', 'exam-hall'),
            esc_html($user->first_name ? $user->first_name : $user->display_name)
        ) . '</p>';
        $body .= '<p>' . esc_html__('We received a request to reset your Exam Hall password. This link expires in 24 hours.', 'exam-hall') . '</p>';
        $body .= self::button($url, __('Choose a new password', 'exam-hall'));
        $body .= '<p>' . esc_html__('If you did not ask for this, you can ignore the email. Your password will stay the same.', 'exam-hall') . '</p>';
        return self::send($user->user_email, __('Reset your Exam Hall password', 'exam-hall'), __('Reset your password', 'exam-hall'), $body);
    }

    /**
     * Email the code required to start an exam.
     *
     * @param WP_User $user       Student.
     * @param object  $exam       Exam.
     * @param string  $code       Start code.
     * @param string  $expires_gmt Expiry in GMT.
     * @param int     $questions  Question count.
     * @return bool
     */
    public static function start_code($user, $exam, $code, $expires_gmt, $questions) {
        $title = str_replace(array("\r", "\n"), ' ', (string) $exam->title);
        $url = eh_portal_url(
            array(
                'eh_view' => 'gate',
                'exam_id' => (int) $exam->id,
            )
        );
        $body = '<p>' . sprintf(
            /* translators: %s: student first name */
            esc_html__('Hello %s,', 'exam-hall'),
            esc_html($user->first_name ? $user->first_name : $user->display_name)
        ) . '</p>';
        $body .= '<p>' . sprintf(
            /* translators: %s: exam title */
            esc_html__('Your start code for “%s” is below. The timer starts when you enter it.', 'exam-hall'),
            esc_html($title)
        ) . '</p>';
        $body .= '<p style="font-family:Georgia,serif;font-size:36px;letter-spacing:0.28em;text-align:center;background:#f3ecdf;padding:18px 12px;border-radius:12px;margin:20px 0;">' . esc_html($code) . '</p>';
        $body .= '<p>' . sprintf(
            /* translators: 1: expiry time, 2: duration, 3: question count */
            esc_html__('This code expires at %1$s. The exam is %2$s and has %3$d questions.', 'exam-hall'),
            esc_html(eh_format_gmt($expires_gmt)),
            esc_html(eh_duration_label($exam->duration_minutes)),
            (int) $questions
        ) . '</p>';
        $body .= self::button($url, __('Enter start code', 'exam-hall'));
        $body .= '<p>' . esc_html__('If you did not request this code, you can ignore this email.', 'exam-hall') . '</p>';
        return self::send(
            $user->user_email,
            sprintf(
                /* translators: %s: exam title */
                __('Your start code for %s', 'exam-hall'),
                $title
            ),
            __('Your start code', 'exam-hall'),
            $body
        );
    }

    /**
     * Settings test email.
     *
     * @param string $to Recipient.
     * @return bool
     */
    public static function test($to) {
        $body = '<p>' . esc_html__('This is a test from Exam Hall. Start codes and password resets use this same mail path.', 'exam-hall') . '</p>';
        $body .= '<p>' . esc_html__('If you can read this, WordPress is able to send student email.', 'exam-hall') . '</p>';
        return self::send($to, __('Exam Hall test email', 'exam-hall'), __('Email is working', 'exam-hall'), $body);
    }

    /**
     * Button markup.
     *
     * @param string $url   Destination.
     * @param string $label Label.
     * @return string
     */
    private static function button($url, $label) {
        return '<p style="margin:24px 0;"><a href="' . esc_url($url) . '" style="display:inline-block;background:#1e5c57;color:#fffaf3;text-decoration:none;padding:12px 18px;border-radius:999px;font-family:Arial,sans-serif;">' . esc_html($label) . '</a></p>';
    }

    /**
     * Wrap inner HTML in the Exam Hall card.
     *
     * @param string $heading Heading.
     * @param string $inner   Inner HTML.
     * @return string
     */
    private static function wrap($heading, $inner) {
        $year = gmdate('Y');
        return '<!DOCTYPE html><html><body style="margin:0;background:#f3ecdf;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3ecdf;padding:32px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fffaf3;border:1px solid #e4d7c3;border-radius:16px;">'
            . '<tr><td style="padding:28px 28px 8px;font-family:Georgia,serif;font-size:13px;letter-spacing:0.14em;text-transform:uppercase;color:#1e5c57;">Exam Hall</td></tr>'
            . '<tr><td style="padding:0 28px 8px;font-family:Georgia,serif;font-size:28px;line-height:1.25;color:#1c1915;">' . esc_html($heading) . '</td></tr>'
            . '<tr><td style="padding:8px 28px 28px;font-family:Arial,sans-serif;font-size:16px;line-height:1.55;color:#3f382f;">' . $inner . '</td></tr>'
            . '</table>'
            . '<p style="font-family:Arial,sans-serif;font-size:12px;color:#7a7064;">Exam Hall · ' . esc_html($year) . '</p>'
            . '</td></tr></table></body></html>';
    }
}
