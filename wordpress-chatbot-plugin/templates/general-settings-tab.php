<?php
/**
 * Általános beállítások fül sablonja.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form class="simple-chatbot__settings-form">
    <label>
        <span><?php esc_html_e('Chatbot címe', 'simple-chatbot'); ?></span>
        <input type="text" name="title" />
    </label>
    <label>
        <span><?php esc_html_e('OpenAI API-kulcs', 'simple-chatbot'); ?></span>
        <input type="password" name="api_key" autocomplete="off" />
    </label>
    <label>
        <span><?php esc_html_e('Chatbot viselkedés', 'simple-chatbot'); ?></span>
        <textarea name="behavior" rows="4"></textarea>
    </label>
    <label>
        <span><?php esc_html_e('Beköszönő üzenet', 'simple-chatbot'); ?></span>
        <textarea name="welcome_message" rows="2" placeholder="Chatbot bekapcsolva. Írj egy kérdést!"></textarea>
    </label>
    <div class="simple-chatbot__form-actions">
        <button type="submit" class="simple-chatbot__button simple-chatbot__button--primary"><?php esc_html_e('Mentés', 'simple-chatbot'); ?></button>
    </div>
</form>
<div class="simple-chatbot__section simple-chatbot__embed-help">
    <h3><?php esc_html_e('Elhelyezési útmutató', 'simple-chatbot'); ?></h3>
    <p class="simple-chatbot__muted"><?php esc_html_e('Használd az alábbi kódot, ha a chatbotot minden oldaladon, például a láblécben szeretnéd megjeleníteni.', 'simple-chatbot'); ?></p>
    <button type="button" class="simple-chatbot__button simple-chatbot__button--ghost js-simple-chatbot-embed-toggle">
        <?php esc_html_e('Beágyazási kód megnyitása', 'simple-chatbot'); ?>
    </button>
    <div class="simple-chatbot__embed-panel js-simple-chatbot-embed-panel" hidden>
        <p><?php esc_html_e('Másold ki, majd illeszd be a sablon láblécébe vagy globális HTML blokkba:', 'simple-chatbot'); ?></p>
        <div class="simple-chatbot__code-copy">
            <textarea class="simple-chatbot__code-block js-simple-chatbot-embed-code" rows="3" readonly aria-label="<?php esc_attr_e('Beillesztendő kód', 'simple-chatbot'); ?>"></textarea>
            <button type="button" class="simple-chatbot__button simple-chatbot__button--secondary js-simple-chatbot-copy-embed"><?php esc_html_e('Kód másolása', 'simple-chatbot'); ?></button>
        </div>
        <p class="simple-chatbot__muted"><?php esc_html_e('A shortcode továbbra is használható egyedi oldalakon: [simple_chatbot]', 'simple-chatbot'); ?></p>
    </div>
</div>
