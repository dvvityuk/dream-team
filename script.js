document.addEventListener('DOMContentLoaded', function () {
  const adminPanel = document.getElementById('admin-panel');
  const isAdmin = Boolean(adminPanel);
  const employeeForm = document.getElementById('employee-form');
  const employeeList = document.getElementById('employee-list');
  const saveJsonBtn = document.getElementById('save-json');
  const chooseImageBtn = document.getElementById('choose-image');
  const imageUpload = document.getElementById('image-upload');
  const imagePathInput = document.getElementById('image');
  const headerDateElement = document.getElementById('header-date');
  const headerDateInput = document.getElementById('header-date-input');
  const updateDateBtn = document.getElementById('update-date-btn');
  const editFormContainer = document.getElementById('edit-form-container');
  const editEmployeeForm = document.getElementById('edit-employee-form');
  const editIndexInput = document.getElementById('edit-index');
  const editNameInput = document.getElementById('edit-name');
  const editAchievementsInput = document.getElementById('edit-achievements');
  const editImageInput = document.getElementById('edit-image');
  const chooseEditImageBtn = document.getElementById('choose-edit-image');
  const editImageUpload = document.getElementById('edit-image-upload');
  const cancelEditBtn = document.getElementById('cancel-edit');
  const emailSettingsForm = document.getElementById('email-settings-form');
  const senderEmailInput = document.getElementById('sender-email');
  const emailSubjectInput = document.getElementById('email-subject');
  const expertPasswordForm = document.getElementById('expert-password-form');

  let employees = [];
  let isUploading = false;
  const embeddedEmployees = document.getElementById('employees-bootstrap');
  if (embeddedEmployees && embeddedEmployees.textContent) {
    try {
      const parsed = JSON.parse(embeddedEmployees.textContent);
      if (Array.isArray(parsed)) employees = parsed;
    } catch (error) {
      employees = [];
    }
  }

  function safeImage(src) {
    return typeof src === 'string' && /^images\/[A-Za-z0-9._-]+$/.test(src) ? src : '';
  }

  function loadEmployeesData() {
    fetch('get_employees.php?v=' + Date.now())
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        employees = Array.isArray(data) ? data : [];
        renderEmployeeList();
        renderTeamCards();
      })
      .catch(function () {
        if (employees.length > 0) return;
        const container = document.getElementById('team-list');
        if (container) {
          container.innerHTML = '<p class="error-message">Не удалось загрузить сотрудников.</p>';
        }
      });

    fetch('get_header_date.php?v=' + Date.now())
      .then(readJson)
      .then(function (data) {
        if (data && data.date && headerDateElement) {
          headerDateElement.textContent = data.date;
        }
      })
      .catch(function () {});

    if (isAdmin) loadEmailSettings();
  }

  function loadEmailSettings() {
    fetch('get_email_settings.php?v=' + Date.now())
      .then(readJson)
      .then(function (data) {
        if (!data || !data.success) return;
        senderEmailInput.value = data.settings.sender || '';
        emailSubjectInput.value = data.settings.subject || '';
      })
      .catch(function () {});
  }

  function uploadImage(fileInput, pathInput, button, label) {
    if (!fileInput.files || !fileInput.files[0]) return;
    if (isUploading) {
      showNotification('Дождитесь завершения загрузки', 'error');
      return;
    }
    isUploading = true;
    button.textContent = 'Загрузка...';
    button.disabled = true;
    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    fetch('upload_image.php', { method: 'POST', body: formData })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          pathInput.value = data.path;
          showNotification('Фото загружено', 'success');
        } else {
          showNotification(data.error || 'Ошибка загрузки', 'error');
        }
      })
      .catch(function () {
        showNotification('Ошибка при загрузке изображения', 'error');
      })
      .finally(function () {
        isUploading = false;
        button.textContent = label;
        button.disabled = false;
        fileInput.value = '';
      });
  }

  chooseImageBtn?.addEventListener('click', function () {
    imageUpload.click();
  });
  imageUpload?.addEventListener('change', function () {
    uploadImage(imageUpload, imagePathInput, chooseImageBtn, 'Выбрать фото');
  });
  chooseEditImageBtn?.addEventListener('click', function () {
    editImageUpload.click();
  });
  editImageUpload?.addEventListener('change', function () {
    uploadImage(editImageUpload, editImageInput, chooseEditImageBtn, 'Выбрать фото');
  });

  employeeForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const employee = {
      name: document.getElementById('name').value.trim(),
      achievements: document.getElementById('achievements').value.trim(),
      image: document.getElementById('image').value.trim()
    };
    if (!safeImage(employee.image)) {
      showNotification('Загрузите фото', 'error');
      return;
    }
    employees.push(employee);
    renderEmployeeList();
    renderTeamCards();
    employeeForm.reset();
    showNotification('Сотрудник добавлен. Сохраните изменения на сервере.', 'success');
  });

  window.editEmployee = function (index) {
    const employee = employees[index];
    editIndexInput.value = String(index);
    editNameInput.value = employee.name;
    editAchievementsInput.value = employee.achievements;
    editImageInput.value = employee.image;
    employeeForm.hidden = true;
    editFormContainer.hidden = false;
  };

  editEmployeeForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const index = Number(editIndexInput.value);
    employees[index] = {
      name: editNameInput.value.trim(),
      achievements: editAchievementsInput.value.trim(),
      image: editImageInput.value.trim()
    };
    renderEmployeeList();
    renderTeamCards();
    cancelEdit();
    showNotification('Изменения в списке. Сохраните их на сервере.', 'success');
  });

  cancelEditBtn?.addEventListener('click', cancelEdit);
  function cancelEdit() {
    editFormContainer.hidden = true;
    employeeForm.hidden = false;
    editEmployeeForm.reset();
  }

  saveJsonBtn?.addEventListener('click', function () {
    saveJsonBtn.textContent = 'Сохранение...';
    saveJsonBtn.disabled = true;
    fetch('save_json.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(employees)
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          showNotification('Данные сохранены', 'success');
          loadEmployeesData();
        } else {
          showNotification(data.error || 'Ошибка сохранения', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      })
      .finally(function () {
        saveJsonBtn.textContent = 'Сохранить на сервере';
        saveJsonBtn.disabled = false;
      });
  });

  updateDateBtn?.addEventListener('click', function () {
    const newDate = headerDateInput.value.trim();
    if (!newDate) {
      showNotification('Введите дату', 'error');
      return;
    }
    fetch('save_header_date.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ date: newDate })
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          headerDateElement.textContent = newDate;
          headerDateInput.value = '';
          showNotification('Дата обновлена', 'success');
        } else {
          showNotification(data.error || 'Не удалось сохранить дату', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      });
  });

  emailSettingsForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const payload = {
      sender: senderEmailInput.value.trim(),
      subject: emailSubjectInput.value.trim()
    };
    fetch('save_email_settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          showNotification('Настройки почты сохранены', 'success');
        } else {
          showNotification(data.error || 'Ошибка сохранения', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      });
  });

  expertPasswordForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const password = document.getElementById('expert-password').value;
    fetch('change_expert_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: password })
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          expertPasswordForm.reset();
          showNotification('Пароль expert обновлён', 'success');
        } else {
          showNotification(data.error || 'Не удалось сохранить пароль', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      });
  });

  function renderEmployeeList() {
    if (!employeeList) return;
    employeeList.innerHTML = '';
    if (employees.length === 0) {
      employeeList.innerHTML = '<p class="empty-message">Нет сотрудников</p>';
      return;
    }
    employees.forEach(function (employee, index) {
      const item = document.createElement('div');
      item.className = 'employee-item';
      const image = safeImage(employee.image);
      item.innerHTML =
        '<div class="employee-info">' +
          (image ? '<img src="' + escapeHtml(image) + '" alt="" class="employee-thumb">' : '') +
          '<div class="employee-details"><h3>' + escapeHtml(employee.name) + '</h3><p>' +
            escapeHtml(employee.achievements) + '</p></div></div>' +
        '<div class="button-row">' +
          '<button type="button" class="btn ghost" data-edit="' + index + '">Изменить</button>' +
          '<button type="button" class="btn danger" data-delete="' + index + '">Удалить</button>' +
        '</div>';
      employeeList.appendChild(item);
    });
  }

  employeeList?.addEventListener('click', function (event) {
    const edit = event.target.closest('[data-edit]');
    const remove = event.target.closest('[data-delete]');
    if (edit) editEmployee(Number(edit.getAttribute('data-edit')));
    if (remove) deleteEmployee(Number(remove.getAttribute('data-delete')));
  });

  function renderTeamCards() {
    const container = document.getElementById('team-list');
    if (!container) return;
    container.innerHTML = '';
    if (employees.length === 0) {
      container.innerHTML = '<p class="empty-message">Нет данных о сотрудниках</p>';
      return;
    }
    employees.forEach(function (employee) {
      const card = document.createElement('article');
      card.className = 'employee-card';
      const image = safeImage(employee.image);
      card.innerHTML =
        (image ? '<img src="' + escapeHtml(image) + '" alt="Фото ' + escapeHtml(employee.name) + '">' : '') +
        '<h2>' + escapeHtml(employee.name) + '</h2>' +
        '<p>' + escapeHtml(employee.achievements) + '</p>';
      container.appendChild(card);
    });
  }

  window.deleteEmployee = function (index) {
    const name = employees[index] ? employees[index].name : '';
    if (!confirm('Удалить сотрудника ' + name + '?')) return;
    employees.splice(index, 1);
    renderEmployeeList();
    renderTeamCards();
    showNotification('Сотрудник удалён из списка. После сохранения на сервере его фото будет удалено из images.', 'warning');
  };

  renderEmployeeList();
  renderTeamCards();
  loadEmployeesData();
});
