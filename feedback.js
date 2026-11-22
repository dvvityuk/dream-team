document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('feedback-modal');
  const form = document.getElementById('feedback-form');
  if (!modal || !form) return;

  const closeBtn = modal.querySelector('.close-modal');
  const submitBtn = form.querySelector('button[type="submit"]');
  const messageField = document.getElementById('feedback-message');
  const status = document.getElementById('feedback-status');
  const result = document.getElementById('feedback-result');
  const resultTitle = document.getElementById('feedback-result-title');
  const resultMessage = document.getElementById('feedback-result-message');
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

  function showResult(sent, message) {
    if (sent) {
      form.reset();
      closeModal();
    }
    resultTitle.textContent = sent ? 'Сообщение отправлено' : 'Не удалось отправить';
    resultMessage.textContent = message;
    result.showModal();
  }

  result.addEventListener('close', function () {
    if (!modal.hidden) messageField.focus();
  });

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
      showResult(false, message
        ? 'Максимальная длина сообщения — 5000 символов'
        : 'Введите текст пожелания');
      return;
    }

    submitBtn.disabled = true;
    messageField.readOnly = true;
    status.textContent = 'Отправка…';
    let notice;
    let sent = false;
    try {
      const response = await fetch('send_feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
      });
      if (response.status === 401) {
        notice = 'Сессия завершилась. Войдите в другой вкладке и повторите отправку.';
      } else {
        const data = await response.json();
        sent = response.ok && data.success === true;
        notice = sent
          ? 'Почтовый сервер принял письмо к доставке.'
          : data.error || 'Не удалось отправить сообщение. Попробуйте ещё раз.';
      }
    } catch {
      notice = 'Не удалось получить ответ сервера. Текст сохранён в форме.';
    } finally {
      submitBtn.disabled = false;
      messageField.readOnly = false;
      status.textContent = '';
    }
    showResult(sent, notice);
  });
});
