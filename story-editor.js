const storyAdmin = document.getElementById('storytelling-panel');

storyAdmin.addEventListener('change', function (event) {
  if (event.target.name === 'avatar' && event.target.files.length > 0) {
    event.target.form.elements.avatarMode.value = 'custom';
  }
});

storyAdmin.addEventListener('submit', async function (event) {
  event.preventDefault();
  const form = event.target;
  const values = new FormData(form, event.submitter);
  const action = values.get('action');
  if (action === 'remove' && !confirm('Убрать участника из Storytelling вместе с его вопросами и ответами?')) return;
  const status = form.querySelector('[role="status"]');
  const buttons = [...form.querySelectorAll('button[type="submit"]')];
  buttons.forEach(button => { button.disabled = true; });
  status.textContent = 'Сохранение…';
  try {
    const response = await fetch(form.getAttribute('action'), { method: 'POST', body: values });
    const result = await response.json();
    if (!response.ok || !result.success) {
      status.textContent = result.error || 'Не удалось сохранить.';
      return;
    }
    if (action === 'hero') {
      status.textContent = 'Герой месяца сохранён.';
      return;
    }
    const refreshed = await fetch('edit.php', { cache: 'no-store' });
    if (!refreshed.ok) throw new Error('Не удалось обновить список');
    const page = new DOMParser().parseFromString(await refreshed.text(), 'text/html');
    const updated = page.getElementById('storytelling-panel');
    if (!updated) throw new Error('Панель Storytelling отсутствует');
    const id = values.get('staffId');
    if (action === 'update') {
      const member = form.closest('[data-story-member]');
      const replacement = updated.querySelector('[data-story-member="' + id + '"]');
      replacement.open = true;
      member.replaceWith(replacement);
      replacement.querySelector('[role="status"]').textContent = 'Участник сохранён.';
      return;
    }
    const openMembers = new Set([...storyAdmin.querySelectorAll('details[open]')].map(member => member.dataset.storyMember));
    const selectedHero = storyAdmin.querySelector('[data-story-hero-select]').value;
    const selectedVisual = storyAdmin.querySelector('[name="heroVisual"]').value;
    storyAdmin.replaceChildren(...updated.childNodes);
    const heroSelect = storyAdmin.querySelector('[data-story-hero-select]');
    if ([...heroSelect.options].some(option => option.value === selectedHero)) heroSelect.value = selectedHero;
    storyAdmin.querySelector('[name="heroVisual"]').value = selectedVisual;
    storyAdmin.querySelectorAll('[data-story-member]').forEach(member => {
      member.open = openMembers.has(member.dataset.storyMember) || (action === 'add' && member.dataset.storyMember === id);
    });
    storyAdmin.querySelector('[data-story-add-select]').form.querySelector('[role="status"]').textContent =
      action === 'add' ? 'Участник добавлен.' : 'Участник удалён.';
  } catch {
    status.textContent = 'Не удалось обновить данные. Проверьте соединение и обновите страницу.';
  } finally {
    buttons.forEach(button => { button.disabled = false; });
  }
});

document.addEventListener('staff-updated', function (event) {
  const people = new Map(event.detail.map(person => [person.id, person.label]));
  const members = new Set();
  storyAdmin.querySelectorAll('[data-story-member]').forEach(member => {
    const id = member.dataset.storyMember;
    if (people.has(id)) {
      member.querySelector('summary').textContent = people.get(id);
      members.add(id);
    } else {
      member.remove();
    }
  });
  const selects = [
    [storyAdmin.querySelector('[data-story-hero-select]'), true],
    [storyAdmin.querySelector('[data-story-add-select]'), false]
  ];
  selects.forEach(([select, isHero]) => {
    const value = select.value;
    select.length = 1;
    for (const [id, label] of people) {
      if (members.has(id) === isHero) select.add(new Option(label, id));
    }
    select.value = [...select.options].some(option => option.value === value) ? value : '';
  });
});

document.getElementById('home-image-form').addEventListener('submit', async function (event) {
  event.preventDefault();
  const status = this.querySelector('[role="status"]');
  const submit = this.querySelector('button');
  submit.disabled = true;
  status.textContent = 'Загрузка…';
  try {
    const response = await fetch(this.action, { method: 'POST', body: new FormData(this) });
    const result = await response.json();
    if (!response.ok || !result.success) {
      status.textContent = result.error || 'Не удалось загрузить изображение.';
      return;
    }
    document.getElementById('home-image-preview').src = result.path;
    this.reset();
    status.textContent = 'Изображение главной страницы обновлено.';
  } catch {
    status.textContent = 'Не удалось загрузить изображение.';
  } finally {
    submit.disabled = false;
  }
});
