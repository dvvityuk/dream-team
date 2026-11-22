document.addEventListener('DOMContentLoaded', function () {
  const root = document.getElementById('album-root');
  if (!root) return;
  const isAdmin = root.dataset.admin === '1';
  const createForm = document.getElementById('create-folder-form');
  let folders = [];
  let viewer;

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
    viewer?.close();
    viewer = null;
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
      return '<figure class="photo-card"><button type="button" class="photo-open">' +
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
      '<dialog class="lightbox" aria-label="Просмотр фотографий">' +
        '<div class="lightbox-stage"><img alt="" draggable="false"></div>' +
        '<button type="button" class="lightbox-close" aria-label="Закрыть" autofocus>&times;</button>' +
        '<button type="button" class="lightbox-prev" aria-label="Предыдущее фото">‹</button>' +
        '<button type="button" class="lightbox-next" aria-label="Следующее фото">›</button>' +
      '</dialog>';

    document.getElementById('back-to-folders').addEventListener('click', function () { setFolder(''); });
    viewer = root.querySelector('.lightbox');
    setupViewer(viewer, folder);

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

  function setupViewer(dialog, folder) {
    const stage = dialog.querySelector('.lightbox-stage');
    const img = stage.querySelector('img');
    const prev = dialog.querySelector('.lightbox-prev');
    const next = dialog.querySelector('.lightbox-next');
    const pointers = new Map();
    let index = 0;
    let scale = 1;
    let x = 0;
    let y = 0;
    let swipeStart;
    let pinching = false;

    function updateTransform() {
      const fit = Math.min(1, stage.clientWidth / img.naturalWidth, stage.clientHeight / img.naturalHeight);
      const width = img.naturalWidth ? img.naturalWidth * fit : 0;
      const height = img.naturalHeight ? img.naturalHeight * fit : 0;
      const limitX = Math.max(0, (width * scale - stage.clientWidth) / 2);
      const limitY = Math.max(0, (height * scale - stage.clientHeight) / 2);
      x = Math.max(-limitX, Math.min(limitX, x));
      y = Math.max(-limitY, Math.min(limitY, y));
      img.style.transform = 'translate(' + x + 'px, ' + y + 'px) scale(' + scale + ')';
      stage.classList.toggle('is-zoomed', scale > 1);
    }

    function show(photoIndex) {
      const count = folder.photos.length;
      index = (photoIndex + count) % count;
      img.src = folder.photos[index].url;
      img.alt = folder.name + ' — фото ' + (index + 1) + ' из ' + count;
      scale = 1;
      x = 0;
      y = 0;
      swipeStart = null;
      updateTransform();
    }

    function changePhoto(direction) {
      if (folder.photos.length > 1) show(index + direction);
    }

    function zoom(factor, clientX, clientY) {
      const bounds = stage.getBoundingClientRect();
      const anchorX = clientX - bounds.left - bounds.width / 2;
      const anchorY = clientY - bounds.top - bounds.height / 2;
      const nextScale = Math.max(1, Math.min(5, scale * factor));
      x = anchorX - (anchorX - x) * nextScale / scale;
      y = anchorY - (anchorY - y) * nextScale / scale;
      scale = nextScale;
      updateTransform();
    }

    prev.hidden = next.hidden = folder.photos.length < 2;
    prev.addEventListener('click', function () { changePhoto(-1); });
    next.addEventListener('click', function () { changePhoto(1); });
    dialog.querySelector('.lightbox-close').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      changePhoto(event.key === 'ArrowLeft' ? -1 : 1);
    });
    dialog.addEventListener('close', function () {
      pointers.clear();
      pinching = false;
      swipeStart = null;
    });
    root.querySelectorAll('.photo-open').forEach(function (button, photoIndex) {
      button.addEventListener('click', function () {
        dialog.showModal();
        show(photoIndex);
      });
    });
    img.addEventListener('load', updateTransform);
    stage.addEventListener('wheel', function (event) {
      event.preventDefault();
      const delta = event.deltaY * (event.deltaMode === 0 ? 1 : 16);
      zoom(Math.exp(-delta * 0.003), event.clientX, event.clientY);
    }, { passive: false });

    stage.addEventListener('pointerdown', function (event) {
      if (event.button !== 0 || pointers.size === 2) return;
      stage.setPointerCapture(event.pointerId);
      pointers.set(event.pointerId, event);
      if (pointers.size === 2) {
        pinching = true;
        swipeStart = null;
      } else if (event.pointerType !== 'mouse' && scale === 1) {
        swipeStart = event;
      }
    });

    stage.addEventListener('pointermove', function (event) {
      const before = pointers.get(event.pointerId);
      if (!before) return;
      pointers.set(event.pointerId, event);
      if (pointers.size === 2) {
        const other = Array.from(pointers.values()).find(function (point) {
          return point.pointerId !== event.pointerId;
        });
        const oldDistance = Math.hypot(before.clientX - other.clientX, before.clientY - other.clientY);
        const newDistance = Math.hypot(event.clientX - other.clientX, event.clientY - other.clientY);
        x += (event.clientX - before.clientX) / 2;
        y += (event.clientY - before.clientY) / 2;
        zoom(newDistance / Math.max(1, oldDistance),
          (event.clientX + other.clientX) / 2, (event.clientY + other.clientY) / 2);
      } else if (scale > 1) {
        x += event.clientX - before.clientX;
        y += event.clientY - before.clientY;
        updateTransform();
      }
    });

    function endPointer(event) {
      if (!pointers.delete(event.pointerId) || pointers.size) return;
      if (event.type === 'pointerup' && swipeStart && !pinching && scale === 1) {
        const dx = event.clientX - swipeStart.clientX;
        const dy = event.clientY - swipeStart.clientY;
        if (Math.abs(dx) >= 45 && Math.abs(dx) > Math.abs(dy)) changePhoto(dx < 0 ? 1 : -1);
      }
      swipeStart = null;
      pinching = false;
    }

    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
    stage.addEventListener('lostpointercapture', endPointer);
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
