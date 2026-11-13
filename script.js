document.addEventListener('DOMContentLoaded', function () {
  const adminPanel = document.getElementById('admin-panel');
  const isAdmin = Boolean(adminPanel);
  const employeeForm = document.getElementById('employee-form');
  const employeeList = document.getElementById('employee-list');
  const saveJsonBtn = document.getElementById('save-json');
  const staffSelect = document.getElementById('staff-id');
  const staffPreview = document.getElementById('staff-preview');
  const headerDateElement = document.getElementById('header-date');
  const headerDateInput = document.getElementById('header-date-input');
  const updateDateBtn = document.getElementById('update-date-btn');
  const editFormContainer = document.getElementById('edit-form-container');
  const editEmployeeForm = document.getElementById('edit-employee-form');
  const editIndexInput = document.getElementById('edit-index');
  const editStaffSelect = document.getElementById('edit-staff-id');
  const editStaffPreview = document.getElementById('edit-staff-preview');
  const editAchievementsInput = document.getElementById('edit-achievements');
  const cancelEditBtn = document.getElementById('cancel-edit');
  const emailSettingsForm = document.getElementById('email-settings-form');
  const senderEmailInput = document.getElementById('sender-email');
  const emailSubjectInput = document.getElementById('email-subject');
  const expertPasswordForm = document.getElementById('expert-password-form');
  const staffForm = document.getElementById('staff-form');
  const staffList = document.getElementById('staff-list');
  const staffEditId = document.getElementById('staff-edit-id');
  const staffLast = document.getElementById('staff-last');
  const staffFirst = document.getElementById('staff-first');
  const staffPosition = document.getElementById('staff-position');
  const staffImage = document.getElementById('staff-image');
  const staffImageUpload = document.getElementById('staff-image-upload');
  const staffChooseImage = document.getElementById('staff-choose-image');
  const staffSubmit = document.getElementById('staff-submit');
  const staffCancel = document.getElementById('staff-cancel');

  let employees = [];
  let staffPeople = [];
  let isUploading = false;

  function readEmbedded(id) {
    const node = document.getElementById(id);
    if (!node || !node.textContent) return [];
    try {
      const parsed = JSON.parse(node.textContent);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  employees = readEmbedded('employees-bootstrap');
  staffPeople = readEmbedded('staff-bootstrap');

  function safeImage(src) {
    return typeof src === 'string' && /^images\/[A-Za-z0-9._-]+$/.test(src) ? src : '';
  }

  function staffById(id) {
    return staffPeople.find(function (person) { return person.id === id; }) || null;
  }

  function present(employee) {
    const person = staffById(employee.staffId);
    return {
      staffId: employee.staffId,
      achievements: employee.achievements,
      name: person ? person.label : 'Сотрудник удалён из списка',
      image: person ? person.image : ''
    };
  }

  function fillSelect(select, selected) {
    if (!select) return;
    const current = selected || select.value;
    select.innerHTML = '<option value="">Выберите сотрудника</option>';
    staffPeople.forEach(function (person) {
      const option = document.createElement('option');
      option.value = person.id;
      option.textContent = person.label;
      select.appendChild(option);
    });
    select.value = staffById(current) ? current : '';
  }

  function showPreview(select, image) {
    if (!select || !image) return;
    const person = staffById(select.value);
    const src = person ? safeImage(person.image) : '';
    if (!src) {
      image.hidden = true;
      image.removeAttribute('src');
      return;
    }
    image.src = src;
    image.alt = person.label;
    image.hidden = false;
  }

  function refreshSelects() {
    fillSelect(staffSelect);
    fillSelect(editStaffSelect, editStaffSelect ? editStaffSelect.value : '');
    showPreview(staffSelect, staffPreview);
    showPreview(editStaffSelect, editStaffPreview);
  }

  function loadEmployeesData() {
    fetch('get_employees.php?v=' + Date.now())
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        employees = Array.isArray(data) ? data : [];
        renderEmployeeList();
      })
      .catch(function () {});

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
        if (!data || !data.success || !senderEmailInput) return;
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

  staffChooseImage?.addEventListener('click', function () {
    staffImageUpload.click();
  });
  staffImageUpload?.addEventListener('change', function () {
    uploadImage(staffImageUpload, staffImage, staffChooseImage, 'Выбрать фото');
  });

  function resetStaffForm() {
    staffForm.reset();
    staffEditId.value = '';
    staffImage.value = '';
    staffSubmit.textContent = 'Добавить в список';
    staffCancel.hidden = true;
  }

  function renderStaffList() {
    if (!staffList) return;
    staffList.innerHTML = '';
    if (staffPeople.length === 0) {
      staffList.innerHTML = '<p class="empty-message">Список сотрудников пуст</p>';
      return;
    }
    staffPeople.forEach(function (person) {
      const item = document.createElement('div');
      item.className = 'employee-item';
      const image = safeImage(person.image);
      item.innerHTML =
        '<div class="employee-info">' +
          (image ? '<img src="' + escapeHtml(image) + '" alt="" class="employee-thumb">' : '') +
          '<div class="employee-details"><h3>' + escapeHtml(person.lastName) + ' ' + escapeHtml(person.firstName) +
          '</h3><p>' + escapeHtml(person.position) + '</p></div></div>' +
        '<div class="button-row">' +
          '<button type="button" class="btn ghost" data-staff-edit="' + escapeHtml(person.id) + '">Изменить</button>' +
          '<button type="button" class="btn danger" data-staff-delete="' + escapeHtml(person.id) + '">Удалить</button>' +
        '</div>';
      staffList.appendChild(item);
    });
  }

  function applyStaff(people) {
    staffPeople = Array.isArray(people) ? people : [];
    renderStaffList();
    refreshSelects();
    renderEmployeeList();
  }

  staffForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const id = staffEditId.value;
    const payload = {
      action: id ? 'update' : 'create',
      id: id,
      person: {
        lastName: staffLast.value.trim(),
        firstName: staffFirst.value.trim(),
        position: staffPosition.value.trim(),
        image: staffImage.value.trim()
      }
    };
    if (!safeImage(payload.person.image)) {
      showNotification('Загрузите фото', 'error');
      return;
    }
    staffSubmit.disabled = true;
    fetch('save_staff.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(readJson)
      .then(function (data) {
        if (!data) return;
        if (data.success) {
          applyStaff(data.people);
          resetStaffForm();
          showNotification(id ? 'Сотрудник обновлён' : 'Сотрудник добавлен в список', 'success');
        } else {
          showNotification(data.error || 'Не удалось сохранить', 'error');
        }
      })
      .catch(function () {
        showNotification('Нет соединения с сервером', 'error');
      })
      .finally(function () {
        staffSubmit.disabled = false;
      });
  });

  staffCancel?.addEventListener('click', resetStaffForm);

  staffList?.addEventListener('click', function (event) {
    const edit = event.target.closest('[data-staff-edit]');
    const remove = event.target.closest('[data-staff-delete]');
    if (edit) {
      const person = staffById(edit.getAttribute('data-staff-edit'));
      if (!person) return;
      staffEditId.value = person.id;
      staffLast.value = person.lastName;
      staffFirst.value = person.firstName;
      staffPosition.value = person.position;
      staffImage.value = person.image;
      staffSubmit.textContent = 'Сохранить сотрудника';
      staffCancel.hidden = false;
      staffForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (remove) {
      const id = remove.getAttribute('data-staff-delete');
      const person = staffById(id);
      if (!person || !confirm('Удалить из списка ' + person.lastName + ' ' + person.firstName + '?')) return;
      fetch('save_staff.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', id: id })
      })
        .then(readJson)
        .then(function (data) {
          if (!data) return;
          if (data.success) {
            employees = employees.filter(function (employee) { return employee.staffId !== id; });
            applyStaff(data.people);
            if (staffEditId.value === id) resetStaffForm();
            showNotification('Сотрудник удалён из списка', 'success');
          } else {
            showNotification(data.error || 'Не удалось удалить', 'error');
          }
        })
        .catch(function () {
          showNotification('Нет соединения с сервером', 'error');
        });
    }
  });

  staffSelect?.addEventListener('change', function () {
    showPreview(staffSelect, staffPreview);
  });
  editStaffSelect?.addEventListener('change', function () {
    showPreview(editStaffSelect, editStaffPreview);
  });

  employeeForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const staffId = staffSelect.value;
    const achievements = document.getElementById('achievements').value.trim();
    if (!staffById(staffId)) {
      showNotification('Выберите сотрудника из списка', 'error');
      return;
    }
    if (employees.some(function (employee) { return employee.staffId === staffId; })) {
      showNotification('Этот сотрудник уже есть в Q digest', 'error');
      return;
    }
    if (!achievements) {
      showNotification('Опишите достижения', 'error');
      return;
    }
    employees.push({ staffId: staffId, achievements: achievements });
    renderEmployeeList();
    employeeForm.reset();
    showPreview(staffSelect, staffPreview);
    showNotification('Карточка добавлена. Сохраните изменения на сервере.', 'success');
  });

  window.editEmployee = function (index) {
    const employee = employees[index];
    editIndexInput.value = String(index);
    fillSelect(editStaffSelect, employee.staffId);
    editAchievementsInput.value = employee.achievements;
    showPreview(editStaffSelect, editStaffPreview);
    employeeForm.hidden = true;
    editFormContainer.hidden = false;
  };

  editEmployeeForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    const index = Number(editIndexInput.value);
    const staffId = editStaffSelect.value;
    const achievements = editAchievementsInput.value.trim();
    if (!staffById(staffId)) {
      showNotification('Выберите сотрудника из списка', 'error');
      return;
    }
    if (employees.some(function (employee, itemIndex) {
      return itemIndex !== index && employee.staffId === staffId;
    })) {
      showNotification('Этот сотрудник уже есть в Q digest', 'error');
      return;
    }
    employees[index] = { staffId: staffId, achievements: achievements };
    renderEmployeeList();
    cancelEdit();
    showNotification('Изменения в списке. Сохраните их на сервере.', 'success');
  });

  cancelEditBtn?.addEventListener('click', cancelEdit);
  function cancelEdit() {
    editFormContainer.hidden = true;
    employeeForm.hidden = false;
    editEmployeeForm.reset();
    showPreview(editStaffSelect, editStaffPreview);
  }

  saveJsonBtn?.addEventListener('click', function () {
    saveJsonBtn.textContent = 'Сохранение...';
    saveJsonBtn.disabled = true;
    const payload = employees.map(function (employee) {
      return { staffId: employee.staffId, achievements: employee.achievements };
    });
    fetch('save_json.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
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
          if (headerDateElement) headerDateElement.textContent = newDate;
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
      employeeList.innerHTML = '<p class="empty-message">В Q digest пока никого нет</p>';
      return;
    }
    employees.forEach(function (employee, index) {
      const view = present(employee);
      const item = document.createElement('div');
      item.className = 'employee-item';
      const image = safeImage(view.image);
      item.innerHTML =
        '<div class="employee-info">' +
          (image ? '<img src="' + escapeHtml(image) + '" alt="" class="employee-thumb">' : '') +
          '<div class="employee-details"><h3>' + escapeHtml(view.name) + '</h3><p>' +
            escapeHtml(view.achievements) + '</p></div></div>' +
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

  window.deleteEmployee = function (index) {
    const view = employees[index] ? present(employees[index]) : { name: '' };
    if (!confirm('Убрать из Q digest ' + view.name + '?')) return;
    employees.splice(index, 1);
    renderEmployeeList();
    showNotification('Карточка убрана из списка. Сохраните изменения на сервере.', 'warning');
  };

  refreshSelects();
  renderStaffList();
  renderEmployeeList();
  if (isAdmin) loadEmployeesData();
});
