<?php
/**
 * Plugin bootstrap.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wires Exam Hall into WordPress.
 */
class EH_Plugin {

    /**
     * Register hooks.
     */
    public static function init() {
        EH_Activator::maybe_upgrade();
        add_action('init', array(__CLASS__, 'textdomain'));
        add_action('admin_init', array('EH_Activator', 'ensure_roles'));
        add_filter('user_has_cap', array(__CLASS__, 'map_cap'), 10, 4);
        add_filter('login_redirect', array(__CLASS__, 'login_redirect'), 10, 3);
        add_filter('wp_authenticate_user', array(__CLASS__, 'block_suspended'), 10, 2);
        add_filter('show_admin_bar', array(__CLASS__, 'admin_bar'));
        add_action('admin_init', array(__CLASS__, 'redirect_students'));
        add_filter('plugin_action_links_' . plugin_basename(EH_FILE), array(__CLASS__, 'links'));
        EH_Admin::hooks();
        EH_Portal::hooks();
        EH_Ajax::hooks();
    }

    /**
     * Load translations.
     */
    public static function textdomain() {
        load_plugin_textdomain('exam-hall', false, dirname(plugin_basename(EH_FILE)) . '/languages');
    }

    /**
     * Site administrators can manage exams even before the role cap is copied.
     *
     * @param array   $allcaps All capabilities.
     * @param array   $caps    Required capabilities.
     * @param array   $args    Capability check arguments.
     * @param WP_User $user    User.
     * @return array
     */
    public static function map_cap($allcaps, $caps, $args, $user) {
        unset($args, $user);
        if (in_array('eh_manage_exams', $caps, true) && !empty($allcaps['manage_options'])) {
            $allcaps['eh_manage_exams'] = true;
        }
        return $allcaps;
    }

    /**
     * Send students to the portal after they use wp-login.php.
     *
     * @param string           $redirect  Default destination.
     * @param string           $requested Requested destination.
     * @param WP_User|WP_Error $user      User.
     * @return string
     */
    public static function login_redirect($redirect, $requested, $user) {
        unset($requested);
        if ($user instanceof WP_User && in_array('exam_student', (array) $user->roles, true) && !user_can($user, 'eh_manage_exams')) {
            return eh_portal_url();
        }
        return $redirect;
    }

    /**
     * Block suspended students at sign-in.
     *
     * @param WP_User|WP_Error $user     User.
     * @param string           $password Password.
     * @return WP_User|WP_Error
     */
    public static function block_suspended($user, $password) {
        unset($password);
        if ($user instanceof WP_User && get_user_meta($user->ID, 'eh_status', true) === 'suspended') {
            return new WP_Error(
                'eh_suspended',
                __('This student account is suspended. Contact your exam administrator.', 'exam-hall')
            );
        }
        return $user;
    }

    /**
     * Hide the WordPress toolbar for students.
     *
     * @param bool $show Whether to show the toolbar.
     * @return bool
     */
    public static function admin_bar($show) {
        if (is_user_logged_in() && current_user_can('eh_take_exams') && !eh_user_can_manage()) {
            return false;
        }
        return $show;
    }

    /**
     * Keep students out of wp-admin.
     */
    public static function redirect_students() {
        if (wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (current_user_can('eh_take_exams') && !eh_user_can_manage()) {
            wp_safe_redirect(eh_portal_url());
            exit;
        }
    }

    /**
     * Plugin list shortcuts.
     *
     * @param array $links Existing links.
     * @return array
     */
    public static function links($links) {
        $portal = '<a href="' . esc_url(eh_portal_url()) . '">' . esc_html__('Student portal', 'exam-hall') . '</a>';
        $settings = '<a href="' . esc_url(admin_url('admin.php?page=eh-settings')) . '">' . esc_html__('Settings', 'exam-hall') . '</a>';
        array_unshift($links, $portal, $settings);
        return $links;
    }
}
