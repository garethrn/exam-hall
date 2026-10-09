<?php
/**
 * Student exam list.
 *
 * @package ExamHall
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="eh-dashboard">
    <header class="eh-page-head">
        <p class="eh-kicker"><?php esc_html_e('Your exams', 'exam-hall'); ?></p>
        <h1><?php echo esc_html(wp_get_current_user()->display_name); ?></h1>
    </header>

    <?php if (!$cards) : ?>
        <div class="eh-card eh-empty-card">
            <h2><?php esc_html_e('Nothing is open yet.', 'exam-hall'); ?></h2>
            <p><?php esc_html_e('When your administrator publishes an exam, it will show up here. You will need the start code from your email before the timer begins.', 'exam-hall'); ?></p>
        </div>
    <?php else : ?>
        <div class="eh-exam-grid">
            <?php foreach ($cards as $card) : ?>
                <?php
                $exam = $card['exam'];
                $access = $card['access'];
                $last = $card['last'];
                ?>
                <article class="eh-exam-card">
                    <header>
                        <h2><?php echo esc_html($exam->title); ?></h2>
                        <?php if ($exam->description) : ?>
                            <p><?php echo esc_html(wp_trim_words($exam->description, 28)); ?></p>
                        <?php endif; ?>
                    </header>
                    <ul class="eh-meta">
                        <li><?php echo esc_html(eh_duration_label($exam->duration_minutes)); ?></li>
                        <li>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: question count */
                                    _n('%d question', '%d questions', $access['questions'], 'exam-hall'),
                                    $access['questions']
                                )
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: pass percentage */
                                    __('Pass mark %d%%', 'exam-hall'),
                                    (int) $exam->band_pass
                                )
                            );
                            ?>
                        </li>
                    </ul>
                    <p class="eh-attempts">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: used attempts, 2: maximum attempts */
                                __('Attempts used: %1$d of %2$d', 'exam-hall'),
                                (int) $access['submitted'],
                                (int) $access['max']
                            )
                        );
                        ?>
                    </p>
                    <?php if ($last) : ?>
                        <p class="eh-last">
                            <a href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'result', 'attempt_id' => (int) $last->id))); ?>">
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: 1: percentage, 2: classification */
                                        __('Last result %1$s · %2$s', 'exam-hall'),
                                        eh_format_percentage($last->percentage),
                                        $last->classification
                                    )
                                );
                                ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    <div class="eh-card-action">
                        <?php if (!$can_take) : ?>
                            <p class="eh-note"><?php esc_html_e('Students request a start code from this card.', 'exam-hall'); ?></p>
                        <?php elseif ($access['can_continue']) : ?>
                            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'exam', 'attempt_id' => (int) $access['active']->id))); ?>"><?php esc_html_e('Continue exam', 'exam-hall'); ?></a>
                        <?php elseif ($access['can_enter_code']) : ?>
                            <a class="eh-btn eh-btn-primary" href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'gate', 'exam_id' => (int) $exam->id))); ?>"><?php esc_html_e('Enter start code', 'exam-hall'); ?></a>
                        <?php elseif ($access['can_start']) : ?>
                            <form method="post" action="<?php echo esc_url(eh_portal_url()); ?>">
                                <?php wp_nonce_field('eh_portal'); ?>
                                <input type="hidden" name="eh_action" value="request_code">
                                <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $exam->id); ?>">
                                <button class="eh-btn eh-btn-primary" type="submit">
                                    <?php echo $access['reason'] === 'retake' ? esc_html__('Retake exam', 'exam-hall') : esc_html__('Email me a start code', 'exam-hall'); ?>
                                </button>
                            </form>
                        <?php elseif ($access['reason'] === 'upcoming') : ?>
                            <p class="eh-note"><?php echo esc_html(sprintf(/* translators: %s: opening time */ __('Opens %s', 'exam-hall'), eh_format_gmt($exam->available_from))); ?></p>
                        <?php elseif ($access['reason'] === 'ended' || $access['reason'] === 'closed') : ?>
                            <p class="eh-note"><?php esc_html_e('This exam is closed.', 'exam-hall'); ?></p>
                        <?php elseif ($access['reason'] === 'maxed') : ?>
                            <p class="eh-note"><?php esc_html_e('You have used every attempt.', 'exam-hall'); ?></p>
                        <?php elseif ($access['reason'] === 'no_questions') : ?>
                            <p class="eh-note"><?php esc_html_e('This exam is not open yet.', 'exam-hall'); ?></p>
                        <?php elseif ($access['reason'] === 'not_assigned') : ?>
                            <p class="eh-note"><?php esc_html_e('This exam is not allocated to you.', 'exam-hall'); ?></p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($can_take) : ?>
        <section class="eh-history">
            <h2><?php esc_html_e('Results', 'exam-hall'); ?></h2>
            <?php if (!$results) : ?>
                <p class="eh-note"><?php esc_html_e('Your percentage and classification will be listed here after you submit.', 'exam-hall'); ?></p>
            <?php else : ?>
                <ul class="eh-history-list">
                    <?php foreach ($results as $attempt) : ?>
                        <li>
                            <a href="<?php echo esc_url(eh_portal_url(array('eh_view' => 'result', 'attempt_id' => (int) $attempt->id))); ?>">
                                <span><?php echo esc_html($attempt->exam_title); ?></span>
                                <strong><?php echo esc_html(eh_format_percentage($attempt->percentage)); ?></strong>
                                <em><?php echo esc_html($attempt->classification); ?></em>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
