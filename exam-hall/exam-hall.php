<?php
/**
 * Plugin Name:       Exam Hall
 * Description:       Timed student examinations with emailed start codes, CSV question import, automatic grading, and an admin dashboard.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Exam Hall
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       exam-hall
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}

define('EH_VERSION', '1.2.0');
define('EH_FILE', __FILE__);
define('EH_PATH', plugin_dir_path(__FILE__));
define('EH_URL', plugin_dir_url(__FILE__));

require_once EH_PATH . 'includes/logic.php';
require_once EH_PATH . 'includes/functions.php';
require_once EH_PATH . 'includes/class-repository.php';
require_once EH_PATH . 'includes/class-emails.php';
require_once EH_PATH . 'includes/class-certificate.php';
require_once EH_PATH . 'includes/class-activator.php';
require_once EH_PATH . 'includes/class-admin.php';
require_once EH_PATH . 'includes/class-portal.php';
require_once EH_PATH . 'includes/class-ajax.php';
require_once EH_PATH . 'includes/class-plugin.php';

register_activation_hook(EH_FILE, array('EH_Activator', 'activate'));
register_deactivation_hook(EH_FILE, array('EH_Activator', 'deactivate'));

add_action('plugins_loaded', array('EH_Plugin', 'init'));
