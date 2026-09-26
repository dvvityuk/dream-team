document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('feedback-modal');
  const form = document.getElementById('feedback-form');
  const closeBtn = document.querySelector('.close-modal');
  if (!modal || !form) return;

  function openModal() {
    modal.hidden = false;
  }

  function closeModal() {
    modal.hidden = true;
  }

  document.querySelectorAll('.js-feedback').forEach(function (button) {
    button.addEventListener('click', openModal);
  });
  closeBtn?.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.hidden) closeModal();
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const message = document.getElementById('feedback-message').value.trim();
    if (!message) {
      showNotification('Введите текст пожелания', 'error');
      return;
    }
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Отправка...';
    fetch('send_feedback.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: message })
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          showNotification('Пожелание отправлено', 'success');
          form.reset();
          closeModal();
        } else {
          showNotification(data.error || 'Не удалось отправить письмо', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Отправить';
      });
  });
});
