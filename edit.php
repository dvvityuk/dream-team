<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff.php';
require_login();
if (!is_admin()) {
    redirect_to('index.php');
}

$directory = staff_load();
$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
$staffJson = json_encode($people, $flags);
$employeesJson = json_encode(employees_read(), $flags);
if ($staffJson === false) {
    $staffJson = '[]';
}
if ($employeesJson === false) {
    $employeesJson = '[]';
}
$dateText = htmlspecialchars(header_date_read(), ENT_QUOTES, 'UTF-8');
layout_start('Редактирование — Q digest', 'edit');
?>
    <header class="hero">
      <div class="hero-copy">
        <p class="eyebrow">Admin</p>
        <h1>Редактирование</h1>
        <p class="hero-lead">Список сотрудников, карточки Q digest и настройки контура.</p>
      </div>
    </header>

    <section id="staff-panel" class="admin-panel">
      <h2>Список сотрудников</h2>
      <p class="panel-note">Фамилия, имя, должность и фото. Отсюда берутся люди для Q digest и storytelling.</p>
      <form id="staff-form">
        <input type="hidden" id="staff-edit-id" value="">
        <input type="text" id="staff-last" placeholder="Фамилия" required maxlength="80">
        <input type="text" id="staff-first" placeholder="Имя" required maxlength="80">
        <input type="text" id="staff-position" placeholder="Должность" required maxlength="80">
        <div class="image-upload-container">
          <input type="file" id="staff-image-upload" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
          <input type="text" id="staff-image" placeholder="Путь к фото" readonly required>
          <button type="button" id="staff-choose-image" class="btn ghost">Выбрать фото</button>
        </div>
        <div class="button-row">
          <button type="submit" class="btn" id="staff-submit">Добавить в список</button>
          <button type="button" class="btn ghost" id="staff-cancel" hidden>Отмена</button>
        </div>
      </form>
      <div id="staff-list"></div>
    </section>

    <section id="admin-panel" class="admin-panel">
      <h2>Q digest</h2>
      <p class="panel-note">Выберите сотрудника из списка и опишите достижения. На сервер карточки попадают после «Сохранить на сервере».</p>

      <form id="employee-form">
        <select id="staff-id" required>
          <option value="">Выберите сотрудника</option>
        </select>
        <img id="staff-preview" class="staff-preview" alt="" hidden>
        <textarea id="achievements" placeholder="Достижения" required></textarea>
        <button type="submit" class="btn">Добавить в Q digest</button>
      </form>

      <div id="edit-form-container" hidden>
        <h3>Изменить карточку</h3>
        <form id="edit-employee-form">
          <input type="hidden" id="edit-index">
          <select id="edit-staff-id" required>
            <option value="">Выберите сотрудника</option>
          </select>
          <img id="edit-staff-preview" class="staff-preview" alt="" hidden>
          <textarea id="edit-achievements" placeholder="Достижения" required></textarea>
          <div class="button-row">
            <button type="submit" class="btn">Сохранить изменения</button>
            <button type="button" id="cancel-edit" class="btn ghost">Отмена</button>
          </div>
        </form>
      </div>

      <div id="employee-list"></div>
      <button type="button" id="save-json" class="btn">Сохранить на сервере</button>

      <div class="admin-grid">
        <section class="admin-card">
          <h3>Дата в заголовке</h3>
          <p class="panel-note">Сейчас: <strong id="header-date"><?= $dateText ?></strong></p>
          <input type="text" id="header-date-input" placeholder="Например: июль 2026" maxlength="80">
          <button type="button" id="update-date-btn" class="btn">Обновить дату</button>
        </section>

        <section class="admin-card">
          <h3>Почта для «Напиши мне»</h3>
          <form id="email-settings-form">
            <input type="email" id="sender-email" placeholder="Email отправителя" required>
            <p class="locked-mail">Получатель: nastasya_zablotskaya@mail.ru</p>
            <input type="text" id="email-subject" placeholder="Тема письма" required maxlength="150">
            <button type="submit" class="btn">Сохранить настройки</button>
          </form>
        </section>

        <section class="admin-card">
          <h3>Пароль аккаунта expert</h3>
          <form id="expert-password-form">
            <input type="password" id="expert-password" placeholder="Новый пароль" autocomplete="new-password" required minlength="4" maxlength="72">
            <button type="submit" class="btn">Сохранить пароль</button>
          </form>
        </section>
      </div>
    </section>
    <script type="application/json" id="staff-bootstrap"><?= $staffJson ?></script>
    <script type="application/json" id="employees-bootstrap"><?= $employeesJson ?></script>
<?php layout_end('script.js'); ?>
