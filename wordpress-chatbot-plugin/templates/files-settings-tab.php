<?php
/**
 * Tudásanyag fájlok beállítások fül sablon.
 */
?>
<div class="simple-chatbot__section">
    <h3><?php esc_html_e('Tudásanyag fájlok', 'simple-chatbot'); ?></h3>
    <div class="simple-chatbot__site-archive-form">
        <?php if (shortcode_exists('file_text_archive')) : ?>
            <?php echo do_shortcode('[file_text_archive]'); ?>
        <?php else : ?>
            <p class="simple-chatbot__notice">[file_text_archive] <?php esc_html_e('shortcode nem elérhető.', 'simple-chatbot'); ?></p>
        <?php endif; ?>
    </div>
</div>
