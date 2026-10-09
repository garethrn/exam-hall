<?php
/**
 * Remove Exam Hall data when the site owner opted in.
 *
 * @package ExamHall
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (get_option('eh_delete_data_on_uninstall') !== '1') {
    return;
}

global $wpdb;

$tables = array(
    $wpdb->prefix . 'eh_answers',
    $wpdb->prefix . 'eh_attempts',
    $wpdb->prefix . 'eh_assignments',
    $wpdb->prefix . 'eh_questions',
    $wpdb->prefix . 'eh_exams',
);
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('eh_student_number','eh_phone','eh_programme','eh_notes','eh_status')");

$page_id = (int) get_option('eh_portal_page_id');
if ($page_id) {
    wp_delete_post($page_id, true);
}

delete_option('eh_portal_page_id');
delete_option('eh_from_name');
delete_option('eh_from_email');
delete_option('eh_delete_data_on_uninstall');
delete_option('eh_db_version');
delete_option('eh_seeded');
delete_option('eh_brand_name');
delete_option('eh_logo_id');
delete_option('eh_certificate_id');
delete_option('eh_certificate_layout');

remove_role('exam_student');
$admin = get_role('administrator');
if ($admin) {
    $admin->remove_cap('eh_manage_exams');
}
