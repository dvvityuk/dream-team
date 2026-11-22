document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('feedback-modal');
  const form = document.getElementById('feedback-form');
  if (!modal || !form) return;

  const closeBtn = modal.querySelector('.close-modal');
  const submitBtn = form.querySelector('button[type="submit"]');
  const messageField = document.getElementById('feedback-message');
  const status = document.getElementById('feedback-status');
  let opener;

  function openModal(event) {
    opener = event.currentTarget;
    modal.hidden = false;
    messageField.focus();
  }

  function closeModal() {
    if (submitBtn.disabled) return;
    modal.hidden = true;
    opener?.focus();
  }

  document.querySelectorAll('.js-feedback').forEach(function (button) {
    button.addEventListener('click', openModal);
  });
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  modal.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeModal();
  });

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (submitBtn.disabled) return;

    const message = messageField.value.trim();
    if (!message || Array.from(message).length > 5000) {
      status.textContent = message
        ? 'Максимальная длина сообщения — 5000 символов'
        : 'Введите текст пожелания';
      messageField.focus();
      return;
    }

    submitBtn.disabled = true;
    messageField.readOnly = true;
    status.textContent = 'Отправка…';
    try {
      const response = await fetch('send_feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
      });
      if (response.status === 401) {
        status.textContent = 'Сессия завершилась. Войдите в другой вкладке и повторите отправку.';
        return;
      }

      const data = await response.json();
      if (response.ok && data.success === true) {
        form.reset();
        status.textContent = 'Сервер принял сообщение.';
      } else {
        status.textContent = data.error || 'Не удалось отправить сообщение. Попробуйте ещё раз.';
      }
    } catch {
      status.textContent = 'Не удалось получить ответ сервера. Текст сохранён в форме.';
    } finally {
      submitBtn.disabled = false;
      messageField.readOnly = false;
    }
  });
});
