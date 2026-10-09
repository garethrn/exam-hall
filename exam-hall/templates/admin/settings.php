<?php
/**
 * Settings.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap eh-admin">
    <?php include EH_PATH . 'templates/admin/notices.php'; ?>
    <header class="eh-admin-hero">
        <div>
            <p class="eh-kicker"><?php esc_html_e('Exam Hall', 'exam-hall'); ?></p>
            <h1><?php esc_html_e('Settings', 'exam-hall'); ?></h1>
        </div>
    </header>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eh-panel eh-narrow" enctype="multipart/form-data">
        <?php wp_nonce_field('eh_save_settings'); ?>
        <input type="hidden" name="action" value="eh_save_settings">
        <label class="eh-field">
            <span><?php esc_html_e('From name', 'exam-hall'); ?></span>
            <input type="text" name="from_name" required maxlength="80" value="<?php echo esc_attr($from_name); ?>">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('From email', 'exam-hall'); ?></span>
            <input type="email" name="from_email" required value="<?php echo esc_attr($from_email); ?>">
        </label>
        <label class="eh-field">
            <span><?php esc_html_e('Student portal page', 'exam-hall'); ?></span>
            <?php
            wp_dropdown_pages(
                array(
                    'name' => 'portal_page_id',
                    'id' => 'eh-portal-page',
                    'selected' => $portal_id,
                    'show_option_none' => __('Select a page', 'exam-hall'),
                )
            );
            ?>
            <small><?php esc_html_e('Exam Hall replaces this page with the student sign-in and exam screens.', 'exam-hall'); ?></small>
        </label>
        <h2><?php esc_html_e('Branding', 'exam-hall'); ?></h2>
        <p class="eh-note"><?php esc_html_e('The logo and company name appear on the student portal, the exam screen, and this dashboard.', 'exam-hall'); ?></p>
        <label class="eh-field">
            <span><?php esc_html_e('Company name', 'exam-hall'); ?></span>
            <input type="text" name="brand_name" maxlength="80" value="<?php echo esc_attr($brand['name']); ?>">
        </label>
        <?php if ($brand['logo'] !== '') : ?>
            <p><img class="eh-admin-logo" src="<?php echo esc_url($brand['logo']); ?>" alt=""></p>
            <label class="eh-check">
                <input type="checkbox" name="remove_logo" value="1">
                <span><?php esc_html_e('Remove the current logo', 'exam-hall'); ?></span>
            </label>
        <?php endif; ?>
        <label class="eh-field">
            <span><?php esc_html_e('Company logo', 'exam-hall'); ?></span>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
            <small><?php esc_html_e('PNG, JPG, or WebP. 2 MB maximum.', 'exam-hall'); ?></small>
        </label>

        <h2><?php esc_html_e('Certificate design', 'exam-hall'); ?></h2>
        <p class="eh-note"><?php esc_html_e('Upload a PNG. Drag the name, subject, grade, and date onto the design. Exams can then issue that certificate when a student reaches the pass mark.', 'exam-hall'); ?></p>
        <?php if (!$can_render) : ?>
            <p class="eh-note"><?php esc_html_e('This server is missing the image tools needed to write text onto a PNG.', 'exam-hall'); ?></p>
        <?php elseif (!EH_Certificate::uses_truetype()) : ?>
            <p class="eh-note"><?php esc_html_e('This server cannot draw the bundled font, so certificates use a simpler typeface. A host with FreeType support uses the smoother font.', 'exam-hall'); ?></p>
        <?php endif; ?>
        <?php if ($certificate_url) : ?>
            <div class="eh-cert-stage" id="eh-cert-stage">
                <img src="<?php echo esc_url($certificate_url); ?>" alt="<?php esc_attr_e('Certificate template', 'exam-hall'); ?>">
                <?php foreach (EH_Certificate::field_labels() as $key => $label) : ?>
                    <button type="button" class="eh-cert-field" data-field="<?php echo esc_attr($key); ?>" style="left: <?php echo esc_attr((string) $layout[$key]['x']); ?>%; top: <?php echo esc_attr((string) $layout[$key]['y']); ?>%;"><?php echo esc_html($label); ?></button>
                <?php endforeach; ?>
            </div>
            <label class="eh-check">
                <input type="checkbox" name="remove_certificate" value="1">
                <span><?php esc_html_e('Remove the current certificate design', 'exam-hall'); ?></span>
            </label>
        <?php else : ?>
            <p class="eh-note"><?php esc_html_e('Upload a design to position the fields. You can still set the sizes and colours below.', 'exam-hall'); ?></p>
        <?php endif; ?>
        <label class="eh-field">
            <span><?php esc_html_e('PNG template', 'exam-hall'); ?></span>
            <input type="file" name="certificate" accept="image/png">
            <small><?php esc_html_e('PNG only. 5 MB maximum. Up to 4000 pixels on each side.', 'exam-hall'); ?></small>
        </label>
        <?php foreach (EH_Certificate::field_labels() as $key => $label) : ?>
            <fieldset class="eh-cert-controls">
                <legend><?php echo esc_html($label); ?></legend>
                <label><?php esc_html_e('Horizontal %', 'exam-hall'); ?> <input id="<?php echo esc_attr('eh-cert-' . $key . '-x'); ?>" type="number" name="<?php echo esc_attr('cert_' . $key . '_x'); ?>" min="0" max="100" step="0.1" value="<?php echo esc_attr((string) $layout[$key]['x']); ?>"></label>
                <label><?php esc_html_e('Vertical %', 'exam-hall'); ?> <input id="<?php echo esc_attr('eh-cert-' . $key . '-y'); ?>" type="number" name="<?php echo esc_attr('cert_' . $key . '_y'); ?>" min="0" max="100" step="0.1" value="<?php echo esc_attr((string) $layout[$key]['y']); ?>"></label>
                <label><?php esc_html_e('Size %', 'exam-hall'); ?> <input type="number" name="<?php echo esc_attr('cert_' . $key . '_size'); ?>" min="1" max="12" step="0.1" value="<?php echo esc_attr((string) $layout[$key]['size']); ?>"></label>
                <label><?php esc_html_e('Colour', 'exam-hall'); ?> <input type="color" name="<?php echo esc_attr('cert_' . $key . '_color'); ?>" value="<?php echo esc_attr($layout[$key]['color']); ?>"></label>
                <label><?php esc_html_e('Align', 'exam-hall'); ?>
                    <select name="<?php echo esc_attr('cert_' . $key . '_align'); ?>">
                        <option value="left" <?php selected($layout[$key]['align'], 'left'); ?>><?php esc_html_e('Left', 'exam-hall'); ?></option>
                        <option value="center" <?php selected($layout[$key]['align'], 'center'); ?>><?php esc_html_e('Centre', 'exam-hall'); ?></option>
                        <option value="right" <?php selected($layout[$key]['align'], 'right'); ?>><?php esc_html_e('Right', 'exam-hall'); ?></option>
                    </select>
                </label>
            </fieldset>
        <?php endforeach; ?>
        <label class="eh-check">
            <input type="checkbox" name="delete_data" value="1" <?php checked($delete_data); ?>>
            <span><?php esc_html_e('Delete exams, questions, and results when this plugin is deleted. Student WordPress accounts are kept either way.', 'exam-hall'); ?></span>
        </label>
        <button class="eh-btn eh-btn-primary" type="submit"><?php esc_html_e('Save settings', 'exam-hall'); ?></button>
    </form>
    <?php if ($certificate_id && $can_render) : ?>
        <p class="eh-note"><?php esc_html_e('Save the positions, then open a preview with sample text.', 'exam-hall'); ?></p>
        <p><a class="eh-btn" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=eh_certificate_preview'), 'eh_certificate_preview')); ?>"><?php esc_html_e('Preview certificate', 'exam-hall'); ?></a></p>
    <?php endif; ?>

    <div class="eh-split">
        <section class="eh-panel">
            <h2><?php esc_html_e('Test email', 'exam-hall'); ?></h2>
            <p class="eh-note"><?php esc_html_e('Start codes and password resets use WordPress email. If this test does not arrive, install an SMTP plugin before students sit an exam.', 'exam-hall'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('eh_test_email'); ?>
                <input type="hidden" name="action" value="eh_test_email">
                <button class="eh-btn" type="submit"><?php esc_html_e('Send test to me', 'exam-hall'); ?></button>
            </form>
        </section>
        <section class="eh-panel">
            <h2><?php esc_html_e('Portal page', 'exam-hall'); ?></h2>
            <?php if ($portal_id && get_post_status($portal_id) === 'publish') : ?>
                <p><a href="<?php echo esc_url(get_permalink($portal_id)); ?>"><?php echo esc_html(get_permalink($portal_id)); ?></a></p>
            <?php else : ?>
                <p class="eh-note"><?php esc_html_e('The student page is missing.', 'exam-hall'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('eh_create_page'); ?>
                    <input type="hidden" name="action" value="eh_create_page">
                    <button class="eh-btn" type="submit"><?php esc_html_e('Create portal page', 'exam-hall'); ?></button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
