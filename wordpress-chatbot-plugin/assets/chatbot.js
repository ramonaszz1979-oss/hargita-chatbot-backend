(function () {
  if (!window.SimpleChatbotData) {
    return;
  }

  const apiBase = window.SimpleChatbotData.apiBase;
  const nonce = window.SimpleChatbotData.nonce;
  const defaultTitle = window.SimpleChatbotData.title;
  const defaultWelcome = window.SimpleChatbotData.welcomeMessage || `${defaultTitle} bekapcsolva. Írj egy kérdést!`;
  const canManage = !!window.SimpleChatbotData.canManage;
  const embedUrl = window.SimpleChatbotData.embedUrl || `${window.location.origin}/?simple_chatbot_embed=1`;
  const embedCode =
    window.SimpleChatbotData.embedCode ||
    `<style>
  .simple-chatbot-floating-frame {
    position: fixed;
    bottom: 16px;
    right: 16px;
    width: 380px;
    max-width: 90vw;
    height: 520px;
    max-height: 80vh;
    border: 0;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    overflow: hidden;
  }
</style>
<iframe class="simple-chatbot-floating-frame" src="${embedUrl}" loading="lazy" title="Chatbot"></iframe>`;

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

  function renderMessageBody(text) {
    const body = document.createElement('div');
    body.className = 'simple-chatbot__message-body';

    if (!text && text !== 0) {
      return body;
    }

    const normalized = String(text).trim();

    if (!normalized) {
      return body;
    }

    const lines = normalized.split(/\r?\n/);
    let currentList = null;

    function createLinkButton(url, label) {
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      anchor.textContent = label || url;
      anchor.className = 'simple-chatbot__link-button';
      return anchor;
    }

    function appendInlineContent(container, content) {
      if (!content) {
        return;
      }

      const fragment = document.createDocumentFragment();
      const pattern = /\*\*([^*]+)\*\*|__([^_]+)__|\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s]+)/g;
      let lastIndex = 0;
      let match;

      while ((match = pattern.exec(content))) {
        if (match.index > lastIndex) {
          fragment.appendChild(document.createTextNode(content.slice(lastIndex, match.index)));
        }

        const boldText = match[1] || match[2];
        const linkLabel = match[3] || match[5];
        const url = match[4] || match[5];

        if (boldText) {
          const strong = document.createElement('strong');
          strong.textContent = boldText;
          fragment.appendChild(strong);
        } else if (url) {
          fragment.appendChild(createLinkButton(url, linkLabel));
        }

        lastIndex = pattern.lastIndex;
      }

      if (lastIndex < content.length) {
        fragment.appendChild(document.createTextNode(content.slice(lastIndex)));
      }

      container.appendChild(fragment);
    }

    function appendParagraph(content) {
      const p = document.createElement('p');
      appendInlineContent(p, content);
      body.appendChild(p);
    }

    lines.forEach(function (line) {
      const trimmed = line.trim();

      if (!trimmed) {
        currentList = null;
        return;
      }

      const numbered = trimmed.match(/^(\d+)[\).:]?\s+(.*)$/);
      const bulleted = trimmed.match(/^[-*•]\s+(.*)$/);

      if (numbered) {
        if (!currentList || currentList.tagName !== 'UL') {
          currentList = document.createElement('ul');
          body.appendChild(currentList);
        }

        const li = document.createElement('li');
        appendInlineContent(li, numbered[2] || numbered[0]);
        currentList.appendChild(li);
        return;
      }

      if (bulleted) {
        if (!currentList || currentList.tagName !== 'UL') {
          currentList = document.createElement('ul');
          body.appendChild(currentList);
        }

        const li = document.createElement('li');
        appendInlineContent(li, bulleted[1] || bulleted[0]);
        currentList.appendChild(li);
        return;
      }

      currentList = null;
      appendParagraph(trimmed);
    });

    return body;
  }

  function createMessageElement(text, variant) {
    const messageEl = document.createElement('div');
    messageEl.className = `simple-chatbot__message simple-chatbot__message--${variant}`;
    const body = renderMessageBody(text);
    messageEl.appendChild(body);
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

    function sendMessage(text) {
      if (!text) {
        return Promise.resolve();
      }

      appendMessage(text, 'user');
      inputEl.value = '';

      return request('/message', {
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
    }

    formEl.addEventListener('submit', function (event) {
      event.preventDefault();
      const text = inputEl.value.trim();
      sendMessage(text);
    });

    const welcomeText = root.dataset.welcome || defaultWelcome || `${title} bekapcsolva. Írj egy kérdést!`;
    appendMessage(welcomeText, 'bot');

    function normalizeUrl(url) {
      if (!url || typeof url !== 'string') {
        return '';
      }

      var trimmed = url.trim();

      if (!trimmed) {
        return '';
      }

      if (!/^https?:\/\//i.test(trimmed)) {
        trimmed = 'https://' + trimmed.replace(/^\/+/, '');
      }

      return trimmed;
    }

    const settingsBtn = root.querySelector('.js-simple-chatbot-settings');
    const previewBtn = root.querySelector('.js-simple-chatbot-preview');
    const modal = root.querySelector('.simple-chatbot__modal');
    const closeBtn = root.querySelector('.simple-chatbot__close');
    const settingsForm = root.querySelector('.simple-chatbot__settings-form');
    const uploadForm = root.querySelector('.simple-chatbot__upload-form');
    const filesList = root.querySelector('.js-simple-chatbot-files');
    const noticeEl = root.querySelector('.js-simple-chatbot-notice');
    const tabs = root.querySelectorAll('.simple-chatbot__tab');
    const tabPanels = root.querySelectorAll('.simple-chatbot__tab-panel');
    const processForm = root.querySelector('.simple-chatbot__process-form');
    const processList = root.querySelector('.js-simple-chatbot-processes');
    const processNewItems = processForm ? processForm.querySelector('.js-simple-chatbot-new-items') : null;
    const processAddItemBtn = processForm ? processForm.querySelector('.js-add-process-item') : null;
    const processNav = root.querySelector('.js-simple-chatbot-process-nav');
    const processChips = root.querySelector('.js-simple-chatbot-section-chips');
    const processChoices = root.querySelector('.js-simple-chatbot-choices');
    const embedToggle = root.querySelector('.js-simple-chatbot-embed-toggle');
    const embedPanel = root.querySelector('.js-simple-chatbot-embed-panel');
    const embedCodeField = root.querySelector('.js-simple-chatbot-embed-code');
    const embedCopyBtn = root.querySelector('.js-simple-chatbot-copy-embed');
    const dissertationToggle = root.querySelector('.js-simple-chatbot-dissertation-toggle');
    const dissertationPanel = root.querySelector('.js-simple-chatbot-dissertation-panel');
    const dissertationOptions = root.querySelectorAll('.js-simple-chatbot-dissertation-option');
    const dissertationQuestion = root.querySelector('.js-simple-chatbot-dissertation-question');
    const dissertationSendBtn = root.querySelector('.js-simple-chatbot-dissertation-send');
    const dissertationResult = root.querySelector('.js-simple-chatbot-dissertation-result');
    const dissertationResultBody = root.querySelector('.js-simple-chatbot-dissertation-answer');
    const dissertationStatus = root.querySelector('.js-simple-chatbot-dissertation-status');
    const dissertationCopyBtn = root.querySelector('.js-simple-chatbot-dissertation-copy');

    let processSections = [];
    let activeProcessSectionId = null;
    let processFinished = false;
    let activeDissertationTopic = '';
    let lastDissertationReply = '';

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

    function hydrateEmbedCode() {
      if (!embedCodeField) {
        return;
      }

      embedCodeField.value = embedCode;
    }

    function copyEmbedSnippet() {
      if (!embedCodeField) {
        return;
      }

      const snippet = embedCodeField.value || embedCode;

      if (!snippet) {
        return;
      }

      function fallbackCopy(text) {
        const temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('aria-hidden', 'true');
        temp.style.position = 'fixed';
        temp.style.opacity = '0';
        document.body.appendChild(temp);
        temp.select();
        try {
          document.execCommand('copy');
          setNotice('Kód kimásolva a vágólapra.', 'success');
        } catch (error) {
          setNotice('A kód másolása nem sikerült.', 'error');
        }
        document.body.removeChild(temp);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(snippet)
          .then(function () {
            setNotice('Kód kimásolva a vágólapra.', 'success');
          })
          .catch(function () {
            fallbackCopy(snippet);
          });
      } else {
        fallbackCopy(snippet);
      }
    }

    function toggleDissertationPanel() {
      if (!dissertationPanel) {
        return;
      }

      const willShow = dissertationPanel.hidden;
      dissertationPanel.hidden = !willShow;

      if (willShow && dissertationQuestion) {
        dissertationQuestion.focus();
      }
    }

    function setDissertationTopic(topic, button) {
      activeDissertationTopic = topic || '';

      dissertationOptions.forEach(function (option) {
        option.classList.toggle('is-active', option === button);
        option.setAttribute('aria-pressed', option === button ? 'true' : 'false');
      });
    }

    function setDissertationStatus(text) {
      if (!dissertationStatus) {
        return;
      }

      dissertationStatus.textContent = text || '';
      dissertationStatus.hidden = !text;
    }

    function renderDissertationReply(text) {
      if (!dissertationResult || !dissertationResultBody) {
        return;
      }

      dissertationResult.hidden = false;
      dissertationResultBody.innerHTML = '';
      const body = renderMessageBody(text || 'Nem érkezett válasz.');
      dissertationResultBody.appendChild(body);
      lastDissertationReply = text || '';
    }

    function copyDissertationReply() {
      if (!lastDissertationReply) {
        return;
      }

      function fallbackCopy(text) {
        const temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('aria-hidden', 'true');
        temp.style.position = 'fixed';
        temp.style.opacity = '0';
        document.body.appendChild(temp);
        temp.select();
        try {
          document.execCommand('copy');
          setNotice('Válasz kimásolva.', 'success');
        } catch (error) {
          setNotice('Nem sikerült a válasz másolása.', 'error');
        }
        document.body.removeChild(temp);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard
          .writeText(lastDissertationReply)
          .then(function () {
            setNotice('Válasz kimásolva.', 'success');
          })
          .catch(function () {
            fallbackCopy(lastDissertationReply);
          });
      } else {
        fallbackCopy(lastDissertationReply);
      }
    }

    function buildDissertationPrompt(topic) {
      const extra = dissertationQuestion ? dissertationQuestion.value.trim() : '';
      const resolvedTopic = topic || 'A Hargita megyei WordPress chatbot részletes szakmai bemutatása';
      const lines = [
        `Készíts részletes, reális disszertációs dolgozat segédletet a következő témában: ${resolvedTopic}.`,
        'A segédlet szóljon a most készülő Hargita megyei turisztikai WordPress chatbot projektről: OpenAI Chat Completions API-t használ, a wp-content/uploads/site-text-archives mappából és webarchív oldalakról tölt be tudást, start/end alapú folyamat-szekciókat és kattintható válaszlehetőségeket jelenít meg, valamint Google űrlap CTA gombokkal vezeti a kitöltést.',
        'Készíts magyar nyelvű, publikálható vázlatot fejezetcímekkel (pl. Bevezetés, Célkitűzések, Rendszerarchitektúra, Tudásanyag kezelése, Folyamatvezérelt UI, Integráció és beágyazás, Biztonság, Tesztelés, Eredmények, Összegzés).',
        'Írd le a működési elvet (adatáramlás: felhasználói üzenet → frontend JS formázás → REST → OpenAI → válasz megjelenítés), a megvalósítás fő lépéseit (WordPress plugin, shortcode, REST végpontok, tudás-cache, folyamat szerkesztő, iframe-es beágyazási kód), valamint a technikai stack-et.',
        'Adj kutatási kérdéseket, módszertani javaslatot (pl. kvalitatív tesztek, válasz-minőség mérése, teljesítmény-optimalizálás), adatforrás ötleteket, ütemtervet, kockázatkezelést és értékelési szempontokat.',
        'Használj felsorolásokat és számozást csak indokolt helyen, hogy áttekinthető, szerkeszthető segédlet jöjjön létre, amit beilleszthetünk a dolgozat megfelelő fejezeteibe.',
      ];

      if (extra) {
        lines.push(`Kiegészítő kérés/kérdés: ${extra}`);
      }

      return lines.join('\n');
    }

    function sendDissertationPrompt(topic) {
      const chosenTopic = topic || activeDissertationTopic || 'A Hargita megyei WordPress chatbot részletes szakmai bemutatása';
      const prompt = buildDissertationPrompt(chosenTopic);

      setNotice(`Disszertációs segédlet készül: ${chosenTopic}`, 'info');
      setDissertationStatus('Segédlet készül...');
      if (dissertationSendBtn) {
        dissertationSendBtn.disabled = true;
      }
      if (dissertationQuestion) {
        dissertationQuestion.value = '';
      }
      return request('/message', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: prompt }),
      })
        .then(function (data) {
          const reply = data && data.reply ? data.reply : 'Nem érkezett válasz.';
          renderDissertationReply(reply);
          setNotice('Disszertációs segédlet elkészült.', 'success');
          setDissertationStatus('');
        })
        .catch(function () {
          renderDissertationReply('Hoppá, hiba történt. Próbáld újra később.');
          setNotice('Hiba történt a segédlet lekérésekor.', 'error');
          setDissertationStatus('');
        })
        .finally(function () {
          if (dissertationSendBtn) {
            dissertationSendBtn.disabled = false;
          }
        });
    }

    function ensureActiveSection(sections) {
      if (!Array.isArray(sections) || sections.length === 0) {
        activeProcessSectionId = null;
        processFinished = false;
        return;
      }

      const startSection = sections.find(function (section) {
        return section && section.is_start;
      });

      const desiredId = startSection ? startSection.id : sections[0].id || null;

      const hasActive = sections.some(function (section) {
        return section && section.id && section.id === activeProcessSectionId;
      });

      if (!hasActive) {
        activeProcessSectionId = desiredId;
        processFinished = false;
      }
    }

    function buildSectionOptions(selectEl, selectedId) {
      if (!selectEl) {
        return;
      }

      selectEl.innerHTML = '';
      const none = document.createElement('option');
      none.value = '';
      none.textContent = 'Nincs ugrás';
      selectEl.appendChild(none);

      (processSections || []).forEach(function (section) {
        const option = document.createElement('option');
        option.value = section.id || '';
        option.textContent = section.title || 'Szekció';
        selectEl.appendChild(option);
      });

      if (selectedId) {
        selectEl.value = selectedId;
      }
    }

    function normalizeProcesses(sections) {
      if (!Array.isArray(sections)) {
        return [];
      }

      return sections
        .map(function (section) {
          if (!section || typeof section !== 'object') {
            return null;
          }

          var title = '';
          var isStart = !!section.is_start;

          if (typeof section.title === 'string') {
            title = section.title;
          } else if (
            section.title &&
            typeof section.title === 'object' &&
            typeof section.title.rendered === 'string'
          ) {
            title = section.title.rendered;
          }

          if (!title) {
            return null;
          }

          var items = Array.isArray(section.items) ? section.items : [];

          var cleanItems = items
            .map(function (item) {
              if (typeof item === 'string') {
                return { label: item, target: '', is_end: false };
              }

              if (!item || typeof item !== 'object') {
                return null;
              }

              var label = typeof item.label === 'string' ? item.label : '';
              var target = typeof item.target === 'string' ? item.target : '';
              var isEnd = !!item.is_end;

              if (!label) {
                return null;
              }

              return { label: label, target: target, is_end: isEnd };
            })
            .filter(function (item) {
              return item !== null;
            });

          var id = section.id && typeof section.id === 'string' ? section.id : '';

          if (!id) {
            id = 'section_' + Math.random().toString(36).slice(2, 10);
          }

          return {
            id: id,
            title: title,
            items: cleanItems,
            is_start: isStart,
            form_url: normalizeUrl(section.form_url),
            form_label: section && typeof section.form_label === 'string' ? section.form_label : '',
          };
        })
        .filter(function (section) {
          return section !== null;
        });
    }

    function updateProcessSections(sections) {
      processSections = normalizeProcesses(sections);
      processFinished = false;
      ensureActiveSection(processSections);
      renderProcesses(processSections);
      renderProcessNavigation(processSections);
    }

    function activateTab(name) {
      if (!tabs || !tabPanels) {
        return;
      }

      tabs.forEach(function (tab) {
        const isActive = tab.dataset.tabTarget === name;
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      tabPanels.forEach(function (panel) {
        const isActive = panel.dataset.tabPanel === name;
        panel.classList.toggle('is-active', isActive);
        panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
    }

    activateTab('general');

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

    function handleProcessChoice(item) {
      if (!item || !item.label) {
        return;
      }

      sendMessage(item.label).then(function () {
        if (item.is_end) {
          processFinished = true;
          activeProcessSectionId = null;
          renderProcessNavigation(processSections);
          return;
        }

        if (!item.target) {
          return;
        }

        const target = (processSections || []).find(function (section) {
          return section && section.id === item.target;
        });

        if (target) {
          activeProcessSectionId = target.id;
          renderProcessNavigation(processSections);
        }
      });
    }

    function renderProcessNavigation(sections) {
      if (!processNav || !processChips || !processChoices) {
        return;
      }

      processChips.innerHTML = '';
      processChoices.innerHTML = '';

      const hasSections = Array.isArray(sections) && sections.length > 0;
      processNav.hidden = !hasSections;

      if (!hasSections) {
        return;
      }

      ensureActiveSection(sections);

      if (processFinished || !activeProcessSectionId) {
        const done = document.createElement('div');
        done.className = 'simple-chatbot__muted';
        done.textContent = 'A folyamat véget ért.';

        const restart = document.createElement('button');
        restart.type = 'button';
        restart.className = 'simple-chatbot__pill';
        restart.textContent = 'Újrakezdés';
        restart.addEventListener('click', function () {
          processFinished = false;
          ensureActiveSection(processSections);
          renderProcessNavigation(processSections);
        });

        processChoices.appendChild(done);
        processChoices.appendChild(restart);
        return;
      }

      const active = sections.find(function (section) {
        return section && section.id === activeProcessSectionId;
      });

      if (!active) {
        return;
      }

      const activeChip = document.createElement('div');
      activeChip.className = 'simple-chatbot__pill simple-chatbot__pill--ghost is-active';
      activeChip.textContent = active.title || 'Szekció';
      processChips.appendChild(activeChip);

      const items = Array.isArray(active.items) ? active.items : [];

      var normalizedFormUrl = normalizeUrl(active.form_url);
      var formLabel = active.form_label || 'Űrlap megnyitása';

      if (normalizedFormUrl) {
        const formButton = document.createElement('a');
        formButton.className = 'simple-chatbot__pill simple-chatbot__pill--cta simple-chatbot__pill--full';
        formButton.href = normalizedFormUrl;
        formButton.target = '_blank';
        formButton.rel = 'noopener noreferrer';
        formButton.textContent = formLabel;
        processChoices.appendChild(formButton);
      }

      if (items.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'simple-chatbot__muted';
        empty.textContent = 'Nincsenek pontok ebben a szekcióban.';
        processChoices.appendChild(empty);
        return;
      }

      items.forEach(function (item) {
        const label = item && typeof item === 'object' ? item.label : item;
        const isEnd = item && typeof item === 'object' && item.is_end;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'simple-chatbot__choice';
        button.textContent = label || 'Pont';

        button.addEventListener('click', function () {
          handleProcessChoice({
            label: label,
            target: item && item.target ? item.target : '',
            is_end: !!isEnd,
          });
        });

        processChoices.appendChild(button);
      });
    }

    function addNewProcessRow(value) {
      if (!processNewItems || !processAddItemBtn) {
        return;
      }

      const row = document.createElement('div');
      row.className = 'simple-chatbot__process-new-row';

      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'process_item';
      input.placeholder = 'Válasz lehetőség';
      if (value) {
        input.value = value;
      }

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'simple-chatbot__icon-button simple-chatbot__icon-button--ghost';
      removeBtn.textContent = '×';
      removeBtn.setAttribute('aria-label', 'Válasz lehetőség törlése');

      removeBtn.addEventListener('click', function () {
        row.remove();
        const remaining = processNewItems.querySelectorAll('input[name="process_item"]');
        if (remaining.length === 0) {
          addNewProcessRow('');
        }
      });

      row.appendChild(input);
      row.appendChild(removeBtn);

      processNewItems.insertBefore(row, processAddItemBtn);
    }

    function resetNewProcessRows(values) {
      if (!processNewItems || !processAddItemBtn) {
        return;
      }

      processNewItems.querySelectorAll('.simple-chatbot__process-new-row').forEach(function (row) {
        row.remove();
      });

      const defaults = Array.isArray(values) && values.length > 0 ? values : [''];
      defaults.forEach(function (val) {
        addNewProcessRow(val || '');
      });
    }

    if (processAddItemBtn) {
      processAddItemBtn.addEventListener('click', function () {
        addNewProcessRow('');
      });
    }

    if (processNewItems) {
      resetNewProcessRows(['']);
    }

    function renderProcesses(sections) {
      if (!processList) {
        return;
      }

      function updateItem(sectionIndex, itemIndex, changes) {
        const next = processSections.slice();
        const updated = Object.assign({}, next[sectionIndex]);
        const updatedItems = Array.isArray(updated.items) ? updated.items.slice() : [];
        const currentItem = Object.assign({ label: '', target: '', is_end: false }, updatedItems[itemIndex] || {});
        updatedItems[itemIndex] = Object.assign({}, currentItem, changes);
        updated.items = updatedItems;
        next[sectionIndex] = updated;
        persistProcesses(next, 'Pont frissítve.');
      }

      function markStartSection(sectionIndex) {
        const next = processSections.map(function (section, idx) {
          return Object.assign({}, section, { is_start: idx === sectionIndex });
        });
        persistProcesses(next, 'Start szekció beállítva.');
      }

      processList.innerHTML = '';

      if (!sections || sections.length === 0) {
        const li = document.createElement('li');
        li.textContent = 'Még nincs mentett szekció.';
        processList.appendChild(li);
        return;
      }

      sections.forEach(function (section, index) {
        const li = document.createElement('li');
        li.className = 'simple-chatbot__process-item';

        const header = document.createElement('div');
        header.className = 'simple-chatbot__process-head';

        const titleWrap = document.createElement('div');
        titleWrap.className = 'simple-chatbot__process-title';

        const titleSpan = document.createElement('span');
        titleSpan.textContent = section.title || 'Szekció';
        titleWrap.appendChild(titleSpan);

        const startLabel = document.createElement('label');
        startLabel.className = 'simple-chatbot__toggle simple-chatbot__toggle--start';
        const startRadio = document.createElement('input');
        startRadio.type = 'radio';
        startRadio.name = 'simple-chatbot-start';
        startRadio.checked = !!section.is_start;
        startRadio.addEventListener('change', function (event) {
          event.stopPropagation();
          markStartSection(index);
        });
        startLabel.appendChild(startRadio);
        const startText = document.createElement('span');
        startText.textContent = 'Start';
        startLabel.appendChild(startText);

        titleWrap.appendChild(startLabel);
        header.appendChild(titleWrap);

        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'simple-chatbot__process-actions';

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'simple-chatbot__pill';
        removeBtn.textContent = 'Szekció törlése';
        removeBtn.addEventListener('click', function () {
          const next = processSections.slice();
          next.splice(index, 1);
          persistProcesses(next, 'Szekció törölve.');
        });

        actionsWrap.appendChild(removeBtn);
        header.appendChild(actionsWrap);
        li.appendChild(header);

        const formRow = document.createElement('div');
        formRow.className = 'simple-chatbot__process-form-row';
        const formLabel = document.createElement('span');
        formLabel.className = 'simple-chatbot__muted';
        formLabel.textContent = 'Űrlap link és gombfelirat (opcionális)';

        const formControls = document.createElement('div');
        formControls.className = 'simple-chatbot__process-form-controls';

        const formInput = document.createElement('input');
        formInput.type = 'url';
        formInput.placeholder = 'https://docs.google.com/forms/...';
        formInput.value = section.form_url || '';

        const formText = document.createElement('input');
        formText.type = 'text';
        formText.placeholder = 'Gomb felirata';
        formText.value = section.form_label || '';

        const formOpen = document.createElement('a');
        formOpen.className = 'simple-chatbot__pill simple-chatbot__pill--cta simple-chatbot__pill--compact';
        formOpen.target = '_blank';
        formOpen.rel = 'noopener noreferrer';
        formOpen.textContent = 'Megnyitás';

        function syncFormButton() {
          const normalized = normalizeUrl(formInput.value);
          if (normalized) {
            formOpen.href = normalized;
            formOpen.hidden = false;
          } else {
            formOpen.hidden = true;
          }
        }

        syncFormButton();

        formInput.addEventListener('change', function (event) {
          event.preventDefault();
          event.stopPropagation();
          const next = processSections.slice();
          const updated = Object.assign({}, next[index], {
            form_url: normalizeUrl(formInput.value),
            form_label: formText.value || next[index].form_label || '',
          });
          next[index] = updated;
          persistProcesses(next, 'Űrlap link frissítve.');
          syncFormButton();
        });

        formText.addEventListener('change', function (event) {
          event.preventDefault();
          event.stopPropagation();
          const next = processSections.slice();
          const updated = Object.assign({}, next[index], {
            form_label: formText.value || '',
            form_url: normalizeUrl(formInput.value),
          });
          next[index] = updated;
          persistProcesses(next, 'Űrlap gomb felirata frissítve.');
        });

        formControls.appendChild(formInput);
        formControls.appendChild(formText);
        formControls.appendChild(formOpen);
        formRow.appendChild(formLabel);
        formRow.appendChild(formControls);
        li.appendChild(formRow);

        const items = Array.isArray(section.items) ? section.items : [];
        const list = document.createElement('ol');

        if (items.length === 0) {
          const empty = document.createElement('li');
          empty.textContent = 'Nincs hozzáadott pont.';
          list.appendChild(empty);
        } else {
          items.forEach(function (item, itemIndex) {
            const label = item && typeof item === 'object' ? item.label : item;
            const target = item && typeof item === 'object' && item.target ? item.target : '';
            const isEnd = item && typeof item === 'object' && item.is_end;
            const itemLi = document.createElement('li');
            itemLi.className = 'simple-chatbot__process-choice-row';

            const itemLabel = document.createElement('input');
            itemLabel.className = 'simple-chatbot__process-choice-input';
            itemLabel.type = 'text';
            itemLabel.value = label || 'Pont';
            itemLabel.addEventListener('change', function (event) {
              event.preventDefault();
              event.stopPropagation();
              const newValue = itemLabel.value.trim();
              if (!newValue) {
                return;
              }
              updateItem(index, itemIndex, { label: newValue });
            });

            const targetSelect = document.createElement('select');
            buildSectionOptions(targetSelect, target);
            targetSelect.addEventListener('change', function (event) {
              event.preventDefault();
              event.stopPropagation();
              const next = processSections.slice();
              const updated = Object.assign({}, next[index]);
              const updatedItems = Array.isArray(updated.items) ? updated.items.slice() : [];
              updatedItems[itemIndex] = Object.assign({}, updatedItems[itemIndex], {
                label: itemLabel.value || label,
                target: targetSelect.value,
              });
              updated.items = updatedItems;
              next[index] = updated;
              persistProcesses(next, 'Pont frissítve.');
            });

            const endLabel = document.createElement('label');
            endLabel.className = 'simple-chatbot__toggle simple-chatbot__toggle--end';
            const endToggle = document.createElement('input');
            endToggle.type = 'checkbox';
            endToggle.checked = !!isEnd;
            endToggle.addEventListener('change', function (event) {
              event.stopPropagation();
              updateItem(index, itemIndex, { is_end: !!endToggle.checked, label: itemLabel.value || label });
            });
            endLabel.appendChild(endToggle);
            const endText = document.createElement('span');
            endText.textContent = 'End';
            endLabel.appendChild(endText);

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'simple-chatbot__pill';
            deleteBtn.textContent = 'Törlés';
            deleteBtn.addEventListener('click', function (event) {
              event.preventDefault();
              event.stopPropagation();
              const next = processSections.slice();
              const updated = Object.assign({}, next[index]);
              const updatedItems = Array.isArray(updated.items) ? updated.items.slice() : [];
              updatedItems.splice(itemIndex, 1);
              updated.items = updatedItems;
              next[index] = updated;
              persistProcesses(next, 'Pont törölve.');
            });

            itemLi.appendChild(itemLabel);
            itemLi.appendChild(targetSelect);
            itemLi.appendChild(endLabel);
            itemLi.appendChild(deleteBtn);
            list.appendChild(itemLi);
          });
        }

        li.appendChild(list);

        const itemForm = document.createElement('form');
        itemForm.className = 'simple-chatbot__process-inline-form';
        const itemInput = document.createElement('input');
        itemInput.type = 'text';
        itemInput.name = 'new_item';
        itemInput.placeholder = 'Új válasz lehetőség';
        const itemTarget = document.createElement('select');
        buildSectionOptions(itemTarget, '');
        const itemEndLabel = document.createElement('label');
        itemEndLabel.className = 'simple-chatbot__toggle simple-chatbot__toggle--end';
        const itemEnd = document.createElement('input');
        itemEnd.type = 'checkbox';
        itemEndLabel.appendChild(itemEnd);
        const itemEndText = document.createElement('span');
        itemEndText.textContent = 'End';
        itemEndLabel.appendChild(itemEndText);
        const addBtn = document.createElement('button');
        addBtn.type = 'submit';
        addBtn.textContent = '+';
        addBtn.className = 'simple-chatbot__icon-button';
        addBtn.setAttribute('aria-label', 'Új válasz lehetőség hozzáadása');

        itemForm.appendChild(itemInput);
        itemForm.appendChild(itemTarget);
        itemForm.appendChild(itemEndLabel);
        itemForm.appendChild(addBtn);

        itemForm.addEventListener('submit', function (event) {
          event.preventDefault();
          event.stopPropagation();
          const value = itemInput.value.trim();

          if (!value) {
            return;
          }

          const next = processSections.slice();
          const updated = Object.assign({}, next[index]);
          const updatedItems = Array.isArray(updated.items) ? updated.items.slice() : [];
          updatedItems.push({
            label: value,
            target: itemTarget.value,
            is_end: !!itemEnd.checked,
          });
          updated.items = updatedItems;
          next[index] = updated;

          persistProcesses(next, 'Új pont hozzáadva.');
          itemInput.value = '';
          itemTarget.value = '';
          itemEnd.checked = false;
        });

        li.appendChild(itemForm);
        processList.appendChild(li);
      });
    }

    function persistProcesses(nextState, successMessage) {
      setNotice('Folyamat mentése...', 'info');

      return request('/processes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sections: nextState }),
      })
        .then(function (data) {
          updateProcessSections(data.sections || []);
          setNotice(successMessage || data.message || 'Folyamatok mentve.', 'success');
        })
        .catch(function () {
          setNotice('Nem sikerült menteni a folyamatokat.', 'error');
        });
    }

    function loadProcesses(showNotice) {
      if (!processList && !processNav) {
        return;
      }

      if (showNotice) {
        setNotice('Folyamatok betöltése...', 'info');
      }

      request('/processes', { method: 'GET' })
        .then(function (data) {
          updateProcessSections(data.sections || []);
          if (showNotice) {
            setNotice('', '');
          }
        })
        .catch(function () {
          if (showNotice) {
            setNotice('Nem sikerült betölteni a folyamat szekciókat.', 'error');
          }
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
        const welcomeInput = settingsForm.querySelector('textarea[name="welcome_message"]');

        if (titleInput) titleInput.value = data.title || '';
        if (apiInput) apiInput.value = data.api_key || '';
        if (behaviorInput) behaviorInput.value = data.behavior || '';
        if (welcomeInput) welcomeInput.value = data.welcome_message || '';
      }

      if (data.welcome_message) {
        root.dataset.welcome = data.welcome_message;
      }

      renderFiles(data.files);

      if (data.processes) {
        updateProcessSections(data.processes || []);
      }
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
        activateTab('general');
        loadSettings();
        loadProcesses(true);
      });
    }

    if (previewBtn) {
      previewBtn.addEventListener('click', function () {
        root.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    }

    if (tabs && tabs.length > 0) {
      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          const target = tab.dataset.tabTarget || 'general';
          activateTab(target);
        });
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        toggleModal(false);
      });
    }

    if (modal) {
      modal.addEventListener('click', function (event) {
        const isOutsideCard = !event.target.closest('.simple-chatbot__modal-card');

        if (isOutsideCard) {
          toggleModal(false);
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          toggleModal(false);
        }
      });
    }

    if (embedToggle && embedPanel) {
      embedToggle.addEventListener('click', function () {
        const willShow = embedPanel.hidden;
        if (willShow) {
          hydrateEmbedCode();
        }
        embedPanel.hidden = !embedPanel.hidden;
        embedToggle.setAttribute('aria-expanded', willShow ? 'true' : 'false');
      });
    }

    if (embedCopyBtn) {
      embedCopyBtn.addEventListener('click', function () {
        hydrateEmbedCode();
        copyEmbedSnippet();
      });
    }

    if (dissertationToggle && dissertationPanel) {
      dissertationToggle.addEventListener('click', function () {
        toggleDissertationPanel();
        dissertationToggle.setAttribute('aria-expanded', dissertationPanel.hidden ? 'false' : 'true');
      });
    }

    if (dissertationOptions && dissertationOptions.length) {
      dissertationOptions.forEach(function (button) {
        button.addEventListener('click', function () {
          const topic = button.dataset.topic || button.textContent || '';
          setDissertationTopic(topic, button);
          sendDissertationPrompt(topic);
        });
      });
    }

    if (dissertationSendBtn) {
      dissertationSendBtn.addEventListener('click', function () {
        const fallbackTopic = activeDissertationTopic || (dissertationOptions[0] ? dissertationOptions[0].dataset.topic || dissertationOptions[0].textContent : '');
        if (fallbackTopic) {
          setDissertationTopic(fallbackTopic, null);
        }
        sendDissertationPrompt(fallbackTopic);
      });
    }

    if (dissertationCopyBtn) {
      dissertationCopyBtn.addEventListener('click', function () {
        copyDissertationReply();
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
          welcome_message: formData.get('welcome_message') || '',
        };

        request('/settings', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        })
          .then(function (data) {
            if (data) {
              fillSettings({
                title: data.title,
                api_key: data.api_key,
                behavior: data.behavior,
                welcome_message: data.welcome_message,
                files: data.files,
              });
            }
            setNotice('Beállítások elmentve.', 'success');
          })
          .catch(function () {
            setNotice('Nem sikerült menteni a beállításokat.', 'error');
          });
      });
    }

    if (processForm) {
      processForm.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const formData = new FormData(processForm);
        const title = (formData.get('process_title') || '').trim();
        const isStart = formData.get('process_is_start') === 'on';
        const formUrl = normalizeUrl(formData.get('process_form_url') || '');
        const formLabel = (formData.get('process_form_label') || '').trim();
        const itemInputs = processForm.querySelectorAll('.simple-chatbot__process-new-row input[name="process_item"]');
        const items = Array.prototype.slice.call(itemInputs)
          .map(function (input) {
            return (input.value || '').trim();
          })
          .filter(function (value) {
            return value !== '';
          })
          .map(function (value) {
            return { label: value, target: '' };
          });

        if (!title) {
          setNotice('Adj meg szekció címet.', 'error');
          return;
        }

        const next = processSections
          .slice()
          .map(function (section) {
            return Object.assign({}, section, { is_start: isStart ? false : section.is_start });
          });

        next.push({ title: title, items: items, is_start: isStart, form_url: formUrl, form_label: formLabel });
        persistProcesses(next, 'Szekció hozzáadva.');
        processForm.reset();
        resetNewProcessRows(['']);
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

    loadProcesses(false);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.simple-chatbot').forEach(initChatbot);
  });
})();
