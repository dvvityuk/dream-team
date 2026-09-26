function showNotification(message, type) {
  const notification = document.createElement('div');
  notification.className = 'notification ' + type;
  notification.textContent = message;
  document.body.appendChild(notification);
  setTimeout(function () {
    notification.classList.add('fade-out');
    setTimeout(function () {
      notification.remove();
    }, 400);
  }, 3200);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function readJson(response) {
  if (response.status === 401) {
    window.location.assign('login.php');
    return Promise.resolve(null);
  }
  return response.json();
}
