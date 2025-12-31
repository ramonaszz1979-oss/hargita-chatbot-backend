(function () {
  if (!window.SimpleChatbotData) {
    return;
  }

  const apiBase = window.SimpleChatbotData.apiBase;
  const nonce = window.SimpleChatbotData.nonce;
  const defaultTitle = window.SimpleChatbotData.title;
  const canManage = !!window.SimpleChatbotData.canManage;

  function request(path, options) {
    return fetch(`${apiBase}${path}`, {
      ...options,
      headers: {
        'X-WP-Nonce': nonce,
        ...(options && options.headers ? options.headers : {}),
      },
      credentials: 'same-origin',
    }).then(function (response) {
      if (!response.ok) {
        const error = new Error('Request failed');
        error.status = response.status;
        throw error;
      }
      return response.json();
    });
  }

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

      request('/message', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: text }),
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

    const settingsBtn = root.querySelector('.js-simple-chatbot-settings');
    const previewBtn = root.querySelector('.js-simple-chatbot-preview');
    const modal = root.querySelector('.simple-chatbot__modal');
    const closeBtn = root.querySelector('.simple-chatbot__close');
    const settingsForm = root.querySelector('.simple-chatbot__settings-form');
    const uploadForm = root.querySelector('.simple-chatbot__upload-form');
    const urlForm = root.querySelector('.simple-chatbot__url-form');
    const filesList = root.querySelector('.js-simple-chatbot-files');
    const urlsList = root.querySelector('.js-simple-chatbot-urls');
    const noticeEl = root.querySelector('.js-simple-chatbot-notice');

    function toggleModal(show) {
      if (!modal) {
        return;
      }

      if (show) {
        modal.hidden = false;
        modal.classList.add('is-open');
      } else {
        modal.classList.remove('is-open');
        modal.hidden = true;
      }
    }

    function setNotice(text, type) {
      if (!noticeEl) {
        return;
      }

      if (!text) {
        noticeEl.hidden = true;
        noticeEl.textContent = '';
        noticeEl.className = 'simple-chatbot__notice js-simple-chatbot-notice';
        return;
      }

      noticeEl.textContent = text;
      noticeEl.hidden = false;
      noticeEl.className = `simple-chatbot__notice js-simple-chatbot-notice simple-chatbot__notice--${type || 'info'}`;
    }

    function renderFiles(files) {
      if (!filesList) {
        return;
      }

      filesList.innerHTML = '';

      if (!files || files.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Még nincs feltöltött fájl.';
        filesList.appendChild(li);
        return;
      }

      files.forEach(function (file) {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = file.url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = file.title || file.url;

        const del = document.createElement('button');
        del.type = 'button';
        del.textContent = 'Törlés';
        del.className = 'simple-chatbot__pill';
        del.addEventListener('click', function () {
          request(`/knowledge/file/${file.id}`, { method: 'DELETE' })
            .then(function (data) {
              renderFiles(data.files);
              setNotice(data.message, 'success');
            })
            .catch(function () {
              setNotice('Nem sikerült törölni a fájlt.', 'error');
            });
        });

        li.appendChild(link);
        li.appendChild(del);
        filesList.appendChild(li);
      });
    }

    function renderUrls(urls) {
      if (!urlsList) {
        return;
      }

      urlsList.innerHTML = '';

      if (!urls || urls.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Még nincs megadott URL.';
        urlsList.appendChild(li);
        return;
      }

      urls.forEach(function (url) {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.textContent = url;

        const del = document.createElement('button');
        del.type = 'button';
        del.textContent = 'Törlés';
        del.className = 'simple-chatbot__pill';
        del.addEventListener('click', function () {
          request('/knowledge/url', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: url }),
          })
            .then(function (data) {
              renderUrls(data.urls);
              setNotice(data.message, 'success');
            })
            .catch(function () {
              setNotice('Nem sikerült törölni az URL-t.', 'error');
            });
        });

        li.appendChild(link);
        li.appendChild(del);
        urlsList.appendChild(li);
      });
    }

    function fillSettings(data) {
      if (!data) {
        return;
      }

      if (settingsForm) {
        const titleInput = settingsForm.querySelector('input[name="title"]');
        const apiInput = settingsForm.querySelector('input[name="api_key"]');
        const behaviorInput = settingsForm.querySelector('textarea[name="behavior"]');

        if (titleInput) titleInput.value = data.title || '';
        if (apiInput) apiInput.value = data.api_key || '';
        if (behaviorInput) behaviorInput.value = data.behavior || '';
      }

      renderFiles(data.files);
      renderUrls(data.urls);
    }

    function loadSettings() {
      setNotice('Beállítások betöltése...', 'info');
      request('/settings', { method: 'GET' })
        .then(function (data) {
          fillSettings(data);
          setNotice('', '');
        })
        .catch(function () {
          setNotice('Nem sikerült betölteni a beállításokat.', 'error');
        });
    }

    if (settingsBtn) {
      settingsBtn.addEventListener('click', function () {
        if (!canManage) {
          setNotice('Nincs jogosultságod a beállítások módosításához.', 'error');
          return;
        }
        toggleModal(true);
        loadSettings();
      });
    }

    if (previewBtn) {
      previewBtn.addEventListener('click', function () {
        root.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        toggleModal(false);
      });
    }

    if (settingsForm) {
      settingsForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(settingsForm);
        const payload = {
          title: formData.get('title') || '',
          api_key: formData.get('api_key') || '',
          behavior: formData.get('behavior') || '',
        };

        request('/settings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(function () {
            setNotice('Beállítások elmentve.', 'success');
          })
          .catch(function () {
            setNotice('Nem sikerült menteni a beállításokat.', 'error');
          });
      });
    }

    if (uploadForm) {
      uploadForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(uploadForm);

        request('/knowledge/upload', {
          method: 'POST',
          body: formData,
        })
          .then(function (data) {
            renderFiles(data.files);
            uploadForm.reset();
            setNotice(data.message, 'success');
          })
          .catch(function () {
            setNotice('Nem sikerült feltölteni a fájlt.', 'error');
          });
      });
    }

    if (urlForm) {
      urlForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const formData = new FormData(urlForm);
        const url = formData.get('url');

        request('/knowledge/url', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ url: url }),
        })
          .then(function (data) {
            renderUrls(data.urls);
            urlForm.reset();
            setNotice(data.message, 'success');
          })
          .catch(function () {
            setNotice('Nem sikerült hozzáadni az URL-t.', 'error');
          });
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.simple-chatbot').forEach(initChatbot);
  });
})();
