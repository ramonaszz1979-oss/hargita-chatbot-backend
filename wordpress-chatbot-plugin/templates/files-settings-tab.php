<div class="simple-chatbot__section">
    <h3><?php esc_html_e('Tudásanyag fájlok', 'simple-chatbot'); ?></h3>
    <form class="simple-chatbot__upload-form">
        <input type="file" name="file" accept=".txt,.md,.pdf,text/plain,text/markdown,application/pdf" />
        <button type="submit" class="simple-chatbot__button"><?php esc_html_e('Feltöltés', 'simple-chatbot'); ?></button>
    </form>
    <ul class="simple-chatbot__list js-simple-chatbot-files"></ul>
</div>
