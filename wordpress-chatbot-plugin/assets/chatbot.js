(function () {
  if (!window.SimpleChatbotData) {
    return;
  }

  const apiBase = window.SimpleChatbotData.apiBase;
  const nonce = window.SimpleChatbotData.nonce;
  const defaultTitle = window.SimpleChatbotData.title;

  function createMessageElement(text, variant) {
    const messageEl = document.createElement('div');
    messageEl.className = `simple-chatbot__message simple-chatbot__message--${variant}`;
    messageEl.textContent = text;
    return messageEl;
  }

  function initChatbot(root) {
    const messagesEl = root.querySelector('.simple-chatbot__messages');
    const formEl = root.querySelector('.simple-chatbot__form');
    const inputEl = formEl.querySelector('input[name="message"]');
    const title = root.dataset.title || defaultTitle;

    if (!messagesEl || !formEl || !inputEl) {
      return;
    }

    function appendMessage(text, variant) {
      const el = createMessageElement(text, variant);
      messagesEl.appendChild(el);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    formEl.addEventListener('submit', function (event) {
      event.preventDefault();
      const text = inputEl.value.trim();
      if (!text) {
        return;
      }

      appendMessage(text, 'user');
      inputEl.value = '';

      fetch(`${apiBase}/message`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ message: text }),
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Hálózati hiba');
          }
          return response.json();
        })
        .then(function (data) {
          const reply = data && data.reply ? data.reply : 'Nem érkezett válasz.';
          appendMessage(reply, 'bot');
        })
        .catch(function () {
          appendMessage('Hoppá, hiba történt. Próbáld újra később.', 'bot');
        });
    });

    appendMessage(`${title} bekapcsolva. Írj egy kérdést!`, 'bot');
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.simple-chatbot').forEach(initChatbot);
  });
})();
