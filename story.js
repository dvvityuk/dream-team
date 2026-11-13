document.addEventListener('DOMContentLoaded', function () {
  const list = document.getElementById('story-list');
  const modal = document.getElementById('story-modal');
  const editor = document.getElementById('story-editor');
  const form = document.getElementById('story-form');
  const facts = document.getElementById('story-facts');
  const photo = document.getElementById('story-photo');
  const nameNode = document.getElementById('story-name');
  const positionNode = document.getElementById('story-position');
  const editButton = document.getElementById('story-edit');
  const fieldAdmin = document.getElementById('story-field-admin');
  const fieldList = document.getElementById('story-field-list');
  const addFieldButton = document.getElementById('story-add-field');
  const saveFieldsButton = document.getElementById('story-save-fields');
  const isAdmin = document.body.classList.contains('is-admin');
  if (!list || !modal || !form) return;

  let directory = { fields: [], people: [] };
  let currentId = '';
  const embedded = document.getElementById('story-bootstrap');
  if (embedded && embedded.textContent) {
    try {
      const parsed = JSON.parse(embedded.textContent);
      if (parsed && Array.isArray(parsed.people) && Array.isArray(parsed.fields)) {
        directory = parsed;
      }
    } catch (error) {
      directory = { fields: [], people: [] };
    }
  }

  function personById(id) {
    return directory.people.find(function (person) { return person.id === id; }) || null;
  }

  function safeImage(src) {
    return typeof src === 'string' && /^images\/[A-Za-z0-9._-]+$/.test(src) ? src : '';
  }

  const dialog = modal.querySelector('.story-dialog');

  function fitPhoto() {
    if (!dialog || modal.hidden || photo.hidden) return;
    photo.style.maxHeight = Math.round(window.innerHeight * 0.62) + 'px';
    let guard = 0;
    while (dialog.scrollHeight > dialog.clientHeight + 1 && guard < 4) {
      const height = photo.getBoundingClientRect().height;
      const extra = dialog.scrollHeight - dialog.clientHeight;
      const next = Math.max(72, Math.round(height - extra));
      if (next >= height - 1) break;
      photo.style.maxHeight = next + 'px';
      guard += 1;
    }
  }

  function closeModal() {
    modal.hidden = true;
    modal.classList.remove('is-open');
    editor.hidden = true;
    editButton.textContent = 'Редактировать информацию';
    photo.style.maxHeight = '';
    if (dialog) dialog.scrollTop = 0;
    currentId = '';
  }

  function renderFacts(person) {
    facts.innerHTML = '';
    directory.fields.forEach(function (field) {
      const block = document.createElement('section');
      block.className = 'story-fact';
      const value = (person.answers && person.answers[field.id]) || '';
      block.innerHTML = '<h3>' + escapeHtml(field.label) + '</h3><p>' +
        (value ? escapeHtml(value) : 'Пока не заполнено') + '</p>';
      facts.appendChild(block);
    });
  }

  function renderEditor(person) {
    form.innerHTML = '';
    directory.fields.forEach(function (field) {
      const label = document.createElement('label');
      label.className = 'story-label';
      label.textContent = field.label;
      const area = document.createElement('textarea');
      area.name = field.id;
      area.maxLength = 5000;
      area.value = (person.answers && person.answers[field.id]) || '';
      form.appendChild(label);
      form.appendChild(area);
    });
    const save = document.createElement('button');
    save.type = 'submit';
    save.className = 'btn';
    save.textContent = 'Сохранить информацию';
    form.appendChild(save);
    if (!isAdmin || !fieldAdmin) return;
    fieldAdmin.hidden = false;
    fieldList.innerHTML = '';
    directory.fields.forEach(function (field) {
      const row = document.createElement('div');
      row.className = 'button-row story-field-row';
      row.innerHTML =
        '<input type="text" data-field-id="' + escapeHtml(field.id) + '" value="' + escapeHtml(field.label) + '" maxlength="80" required>' +
        '<button type="button" class="btn danger" data-remove-field="' + escapeHtml(field.id) + '">Удалить</button>';
      fieldList.appendChild(row);
    });
  }

  function openModal(id) {
    const person = personById(id);
    if (!person) return;
    currentId = id;
    const src = safeImage(person.image);
    if (src) {
      photo.src = src;
      photo.alt = person.lastName + ' ' + person.firstName;
      photo.hidden = false;
    } else {
      photo.hidden = true;
      photo.removeAttribute('src');
    }
    nameNode.textContent = person.lastName + ' ' + person.firstName;
    positionNode.textContent = person.position;
    renderFacts(person);
    editor.hidden = true;
    editButton.textContent = 'Редактировать информацию';
    photo.style.maxHeight = '';
    if (dialog) dialog.scrollTop = 0;
    modal.hidden = false;
    requestAnimationFrame(function () {
      modal.classList.add('is-open');
      fitPhoto();
    });
  }

  list.addEventListener('click', function (event) {
    const card = event.target.closest('[data-story]');
    if (!card) return;
    openModal(card.getAttribute('data-story'));
  });

  document.getElementById('story-close')?.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });

  editButton?.addEventListener('click', function () {
    const person = personById(currentId);
    if (!person) return;
    if (editor.hidden) {
      renderEditor(person);
      editor.hidden = false;
      editButton.textContent = 'Скрыть редактирование';
    } else {
      editor.hidden = true;
      editButton.textContent = 'Редактировать информацию';
    }
    requestAnimationFrame(fitPhoto);
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const person = personById(currentId);
    if (!person) return;
    const answers = {};
    directory.fields.forEach(function (field) {
      const input = form.querySelector('[name="' + field.id + '"]');
      answers[field.id] = input ? input.value.trim() : '';
    });
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    fetch('save_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: person.id, answers: answers })
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          person.answers = answers;
          renderFacts(person);
          editor.hidden = true;
          editButton.textContent = 'Редактировать информацию';
          requestAnimationFrame(fitPhoto);
          showNotification('Информация сохранена', 'success');
        } else {
          showNotification(data.error || 'Не удалось сохранить', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      })
      .finally(function () {
        button.disabled = false;
      });
  });

  addFieldButton?.addEventListener('click', function () {
    const row = document.createElement('div');
    row.className = 'button-row story-field-row';
    row.innerHTML =
      '<input type="text" data-field-id="" placeholder="Название поля" maxlength="80" required>' +
      '<button type="button" class="btn danger" data-remove-field="">Удалить</button>';
    fieldList.appendChild(row);
  });

  fieldList?.addEventListener('click', function (event) {
    const button = event.target.closest('[data-remove-field]');
    if (!button) return;
    button.closest('.story-field-row')?.remove();
  });

  saveFieldsButton?.addEventListener('click', function () {
    const fields = [];
    fieldList.querySelectorAll('[data-field-id]').forEach(function (input) {
      fields.push({ id: input.getAttribute('data-field-id') || '', label: input.value.trim() });
    });
    saveFieldsButton.disabled = true;
    fetch('save_fields.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fields: fields })
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          directory.fields = data.fields;
          directory.people = data.people;
          const person = personById(currentId);
          if (person) {
            renderFacts(person);
            renderEditor(person);
            requestAnimationFrame(fitPhoto);
          }
          showNotification('Список полей обновлён', 'success');
        } else {
          showNotification(data.error || 'Не удалось сохранить поля', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      })
      .finally(function () {
        saveFieldsButton.disabled = false;
      });
  });

  photo.addEventListener('load', fitPhoto);
  window.addEventListener('resize', fitPhoto);
});
