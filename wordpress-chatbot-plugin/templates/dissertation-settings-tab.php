<?php
/**
 * Disszertációs segéd fül sablonja.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="simple-chatbot__section simple-chatbot__dissertation">
    <div class="simple-chatbot__dissertation-header">
        <div>
            <h3><?php esc_html_e('Disszertációs dolgozat segéd', 'simple-chatbot'); ?></h3>
            <p class="simple-chatbot__muted"><?php esc_html_e('Válassz témát vagy adj kérdést, és a chatbot részletes segédletet készít a most fejlesztett rendszerhez.', 'simple-chatbot'); ?></p>
        </div>
        <button type="button" class="simple-chatbot__button simple-chatbot__button--ghost js-simple-chatbot-dissertation-toggle">
            <?php esc_html_e('Segéd megnyitása', 'simple-chatbot'); ?>
        </button>
    </div>
    <div class="simple-chatbot__dissertation-panel js-simple-chatbot-dissertation-panel" hidden>
        <div class="simple-chatbot__pill-row">
            <button type="button" class="simple-chatbot__pill simple-chatbot__pill--compact js-simple-chatbot-dissertation-option" data-topic="A chatbot technikai leírása"><?php esc_html_e('A chatbot technikai leírása', 'simple-chatbot'); ?></button>
            <button type="button" class="simple-chatbot__pill simple-chatbot__pill--compact js-simple-chatbot-dissertation-option" data-topic="A chatbot működési elve"><?php esc_html_e('A chatbot működési elve', 'simple-chatbot'); ?></button>
            <button type="button" class="simple-chatbot__pill simple-chatbot__pill--compact js-simple-chatbot-dissertation-option" data-topic="A chatbot megvalósítási folyamata"><?php esc_html_e('A chatbot megvalósítási folyamata', 'simple-chatbot'); ?></button>
        </div>
        <label class="simple-chatbot__inline-label">
            <span><?php esc_html_e('Saját kérdés vagy fókusz (nem kötelező)', 'simple-chatbot'); ?></span>
            <textarea class="simple-chatbot__dissertation-question js-simple-chatbot-dissertation-question" rows="3" placeholder="<?php esc_attr_e('Pl. milyen módszertani megközelítést válasszak?', 'simple-chatbot'); ?>"></textarea>
        </label>
        <div class="simple-chatbot__form-actions">
            <button type="button" class="simple-chatbot__button simple-chatbot__button--primary js-simple-chatbot-dissertation-send"><?php esc_html_e('Segédlet kérése', 'simple-chatbot'); ?></button>
        </div>
        <p class="simple-chatbot__muted"><?php esc_html_e('Téma választásakor azonnal segédlet készül. Ha csak kérdést írsz be, a chatbot a Hargita megyei turisztikai chatbot projektre szabott választ ad.', 'simple-chatbot'); ?></p>
    </div>
</div>
