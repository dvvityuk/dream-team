document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('album-root');
  if (!root) return;
  const isAdmin = root.dataset.admin === '1';
  const createForm = document.getElementById('create-folder-form');
  let folders = [];

  function currentFolderId() {
    return new URLSearchParams(window.location.search).get('folder') || '';
  }

  function setFolder(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('folder', id);
    else url.searchParams.delete('folder');
    history.pushState({}, '', url);
    render();
  }

  function load() {
    return fetch('albums_api.php?v=' + Date.now())
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        folders = data.success && Array.isArray(data.folders) ? data.folders : [];
        render();
      })
      .catch(function () {
        root.innerHTML = '<p class="error-message">Не удалось загрузить альбом.</p>';
      });
  }

  function render() {
    const id = currentFolderId();
    if (!id) {
      renderGrid();
      return;
    }
    const folder = folders.find(function (item) { return item.id === id; });
    if (!folder) {
      root.innerHTML = '<p class="empty-message">Папка не найдена.</p><button type="button" class="btn ghost" id="back-to-folders">Ко всем папкам</button>';
      document.getElementById('back-to-folders').addEventListener('click', function () { setFolder(''); });
      return;
    }
    renderFolder(folder);
  }

  function renderGrid() {
    if (createForm) createForm.hidden = false;
    if (folders.length === 0) {
      root.innerHTML = '<p class="empty-message">Папок пока нет.</p>';
      return;
    }
    root.innerHTML = '<div class="folder-grid"></div>';
    const grid = root.querySelector('.folder-grid');
    folders.forEach(function (folder) {
      const cover = folder.photos[0] ? folder.photos[0].url : '';
      const card = document.createElement('button');
      card.type = 'button';
      card.className = 'folder-card';
      card.innerHTML =
        '<span class="folder-cover">' +
          (cover ? '<img src="' + escapeHtml(cover) + '" alt="">' : '<span class="folder-empty">Нет фото</span>') +
        '</span>' +
        '<span class="folder-meta"><strong>' + escapeHtml(folder.name) + '</strong><em>' +
          folder.photos.length + ' фото</em></span>';
      card.addEventListener('click', function () { setFolder(folder.id); });
      grid.appendChild(card);
    });
  }

  function renderFolder(folder) {
    if (createForm) createForm.hidden = true;
    const photos = folder.photos.map(function (photo) {
      const remove = isAdmin
        ? '<button type="button" class="btn danger photo-delete" data-file="' + escapeHtml(photo.file) + '">Удалить</button>'
        : '';
      return '<figure class="photo-card"><button type="button" class="photo-open" data-src="' + escapeHtml(photo.url) + '">' +
        '<img src="' + escapeHtml(photo.url) + '" alt="' + escapeHtml(folder.name) + '"></button>' + remove + '</figure>';
    }).join('');
    const tools = isAdmin
      ? '<form id="upload-photo-form" class="upload-bar"><input id="album-photos" type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple required><button type="submit" class="btn">Загрузить фотографии</button></form>' +
        '<button type="button" id="delete-folder" class="btn danger">Удалить папку</button>'
      : '';
    root.innerHTML =
      '<div class="folder-toolbar"><button type="button" class="btn ghost" id="back-to-folders">Все папки</button>' + tools + '</div>' +
      '<h2 class="folder-title">' + escapeHtml(folder.name) + '</h2>' +
      (folder.photos.length ? '<div class="photo-grid">' + photos + '</div>' : '<p class="empty-message">В этой папке пока нет фотографий.</p>') +
      '<div id="lightbox" class="lightbox" hidden><button type="button" class="lightbox-close" aria-label="Закрыть">&times;</button><img alt=""></div>';

    document.getElementById('back-to-folders').addEventListener('click', function () { setFolder(''); });
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = lightbox.querySelector('img');
    root.querySelectorAll('.photo-open').forEach(function (button) {
      button.addEventListener('click', function () {
        lightboxImg.src = button.getAttribute('data-src');
        lightbox.hidden = false;
      });
    });
    lightbox.querySelector('.lightbox-close').addEventListener('click', function () { lightbox.hidden = true; });
    lightbox.addEventListener('click', function (event) {
      if (event.target === lightbox) lightbox.hidden = true;
    });

    const uploadForm = document.getElementById('upload-photo-form');
    uploadForm?.addEventListener('submit', function (event) {
      event.preventDefault();
      const input = document.getElementById('album-photos');
      if (!input.files || input.files.length === 0) return;
      const formData = new FormData();
      formData.append('folder', folder.id);
      Array.from(input.files).forEach(function (file) { formData.append('photos[]', file); });
      const submit = uploadForm.querySelector('button');
      submit.disabled = true;
      submit.textContent = 'Загрузка...';
      fetch('upload_album.php', { method: 'POST', body: formData })
        .then(readJson)
        .then(function (data) {
          if (!data) return;
          if (data.success) {
            showNotification('Фотографии добавлены', 'success');
            load();
          } else {
            showNotification(data.error || 'Не удалось загрузить', 'error');
          }
        })
        .catch(function () {
          showNotification('Нет соединения с сервером', 'error');
        })
        .finally(function () {
          submit.disabled = false;
          submit.textContent = 'Загрузить фотографии';
        });
    });

    document.getElementById('delete-folder')?.addEventListener('click', function () {
      if (!confirm('Удалить папку «' + folder.name + '» вместе с фотографиями?')) return;
      postAlbum({ action: 'delete_folder', id: folder.id }).then(function (data) {
        if (!data || !data.success) {
          showNotification((data && data.error) || 'Не удалось удалить папку', 'error');
          return;
        }
        showNotification('Папка удалена', 'success');
        setFolder('');
        load();
      });
    });

    root.querySelectorAll('.photo-delete').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!confirm('Удалить это фото?')) return;
        postAlbum({ action: 'delete_photo', id: folder.id, file: button.getAttribute('data-file') })
          .then(function (data) {
            if (!data || !data.success) {
              showNotification((data && data.error) || 'Не удалось удалить фото', 'error');
              return;
            }
            load();
          });
      });
    });
  }

  function postAlbum(payload) {
    return fetch('albums_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(readJson).catch(function () {
      showNotification('Нет соединения с сервером', 'error');
      return null;
    });
  }

  createForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const input = document.getElementById('folder-name');
    const name = input.value.trim();
    if (!name) return;
    postAlbum({ action: 'create', name: name }).then(function (data) {
      if (!data) return;
      if (data.success) {
        input.value = '';
        showNotification('Папка создана', 'success');
        load();
      } else {
        showNotification(data.error || 'Не удалось создать папку', 'error');
      }
    });
  });

  window.addEventListener('popstate', render);
  load();
});
