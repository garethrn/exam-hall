<?php
/**
 * Standalone portal document.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
extract($eh, EXTR_SKIP);
$user = wp_get_current_user();
$logged_in = is_user_logged_in();
$logout_url = $logged_in ? wp_nonce_url(eh_portal_url(array('eh_logout' => 1)), 'eh_logout') : '';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#10224d">
    <title><?php echo esc_html($brand['name'] !== '' ? $brand['name'] : __('Exam Hall', 'exam-hall')); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(EH_URL . 'assets/css/public.css?ver=' . EH_VERSION); ?>">
    <?php if ($template === 'runner') : ?>
        <noscript><style>.eh-q{display:block !important}.eh-dots,.eh-exam-nav button[type="button"]{display:none !important}</style></noscript>
    <?php endif; ?>
</head>
<body class="eh-body eh-view-<?php echo esc_attr($template); ?>">
    <a class="eh-skip" href="#eh-main"><?php esc_html_e('Skip to content', 'exam-hall'); ?></a>
    <header class="eh-top">
        <a class="eh-brand" href="<?php echo esc_url(eh_portal_url()); ?>">
            <?php if ($brand['logo'] !== '') : ?>
                <img class="eh-logo" src="<?php echo esc_url($brand['logo']); ?>" alt="">
            <?php else : ?>
                <span class="eh-mark" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="eh-brand-name"><?php echo esc_html($brand['name'] !== '' ? $brand['name'] : __('Exam Hall', 'exam-hall')); ?></span>
        </a>
        <?php if ($logged_in && $template !== 'denied') : ?>
            <nav class="eh-nav" aria-label="<?php esc_attr_e('Student', 'exam-hall'); ?>">
                <?php if ($template !== 'runner') : ?>
                    <a href="<?php echo esc_url(eh_portal_url()); ?>" <?php echo $template === 'dashboard' ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Exams', 'exam-hall'); ?></a>
                    <a href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'account'))); ?>" <?php echo $template === 'account' ? 'aria-current="page"' : ''; ?>><?php esc_html_e('Account', 'exam-hall'); ?></a>
                <?php else : ?>
                    <span class="eh-save" id="eh-save-state" aria-live="polite"></span>
                <?php endif; ?>
                <a href="<?php echo esc_url($logout_url); ?>"><?php esc_html_e('Sign out', 'exam-hall'); ?></a>
            </nav>
        <?php endif; ?>
    </header>
    <main id="eh-main" class="eh-main">
        <?php if (!empty($can_manage) && $template === 'dashboard') : ?>
            <p class="eh-banner eh-banner-info"><?php esc_html_e('You are previewing the student portal. Students use this page to sit exams.', 'exam-hall'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=eh-dashboard')); ?>"><?php esc_html_e('Back to the dashboard', 'exam-hall'); ?></a></p>
        <?php endif; ?>
        <?php if (!empty($message)) : ?>
            <p class="eh-banner eh-banner-<?php echo esc_attr($message[0]); ?>"><?php echo esc_html($message[1]); ?></p>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <p class="eh-banner eh-banner-error"><?php echo esc_html($error); ?></p>
        <?php endif; ?>
        <?php include EH_PATH . 'templates/portal/' . $template . '.php'; ?>
    </main>
    <?php if ($template === 'runner') : ?>
        <script src="<?php echo esc_url(EH_URL . 'assets/js/public.js?ver=' . EH_VERSION); ?>"></script>
    <?php endif; ?>
</body>
</html>
