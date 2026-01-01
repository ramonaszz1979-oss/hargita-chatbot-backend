<?php
/**
 * Weboldalak beállítások fül sablon.
 */
?>
<div class="simple-chatbot__section">
    <h3><?php esc_html_e('Tudásanyag weboldalak', 'simple-chatbot'); ?></h3>
    <p><?php esc_html_e('A webes tudásanyag a Site archív oldalról kerül beolvasásra.', 'simple-chatbot'); ?></p>
    <a class="simple-chatbot__button" href="https://hargita.smartonlineedu.com/site-arhiver/" target="_blank" rel="noopener noreferrer">
        <?php esc_html_e('Site archív megnyitása', 'simple-chatbot'); ?>
    </a>
    <div class="simple-chatbot__site-archive-form">
        <?php if (shortcode_exists('site_text_archiver_form')) : ?>
            <?php echo do_shortcode('[site_text_archiver_form]'); ?>
        <?php else : ?>
            <p class="simple-chatbot__notice">[site_text_archiver_form] <?php esc_html_e('shortcode nem elérhető.', 'simple-chatbot'); ?></p>
        <?php endif; ?>
    </div>
</div>
