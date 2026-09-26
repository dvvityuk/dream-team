<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$dateText = htmlspecialchars(header_date_read(), ENT_QUOTES, 'UTF-8');
$employees = employees_read();
$employeesJson = json_encode(
    $employees,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($employeesJson === false) {
    $employeesJson = '[]';
}
layout_start('Q digest', 'digest');
?>
    <header class="hero">
      <div class="hero-copy">
        <p class="eyebrow">Q store</p>
        <h1>Q digest <span id="header-date"><?= $dateText ?></span></h1>
        <p class="hero-lead">Итоги команды в одном контуре. Фото, цифры и повод сказать спасибо.</p>
        <button type="button" class="btn js-feedback">Напиши мне</button>
      </div>
      <div class="hero-visual">
        <img src="img/header.png" alt="Супергерои работают в Q store">
      </div>
    </header>

    <section id="team-list" class="team-grid" aria-live="polite">
      <?php if ($employees === []): ?>
        <p class="empty-message">Нет данных о сотрудниках. Проверьте файл employees.json в каталоге сайта.</p>
      <?php else: ?>
        <?php foreach ($employees as $employee): ?>
          <article class="employee-card">
            <?php if (preg_match('#^images/[A-Za-z0-9._-]+$#', $employee['image'])): ?>
              <img src="<?= htmlspecialchars($employee['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Фото <?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <h2><?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($employee['achievements'], ENT_QUOTES, 'UTF-8') ?></p>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <script type="application/json" id="employees-bootstrap"><?= $employeesJson ?></script>

    <?php if (is_admin()): ?>
    <section id="admin-panel" class="admin-panel">
      <p class="eyebrow">Admin</p>
      <h2>Редактирование</h2>
      <p class="panel-note">Карточки обновляются сразу. На сервер они попадают после «Сохранить на сервере».</p>

      <form id="employee-form">
        <input type="text" id="name" placeholder="Имя и фамилия" required>
        <textarea id="achievements" placeholder="Достижения" required></textarea>
        <div class="image-upload-container">
          <input type="file" id="image-upload" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
          <input type="text" id="image" placeholder="Путь к фото" readonly required>
          <button type="button" id="choose-image" class="btn ghost">Выбрать фото</button>
        </div>
        <button type="submit" class="btn">Добавить сотрудника</button>
      </form>

      <div id="edit-form-container" hidden>
        <h3>Изменить сотрудника</h3>
        <form id="edit-employee-form">
          <input type="hidden" id="edit-index">
          <input type="text" id="edit-name" placeholder="Имя и фамилия" required>
          <textarea id="edit-achievements" placeholder="Достижения" required></textarea>
          <div class="image-upload-container">
            <input type="file" id="edit-image-upload" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
            <input type="text" id="edit-image" placeholder="Путь к фото" readonly required>
            <button type="button" id="choose-edit-image" class="btn ghost">Выбрать фото</button>
          </div>
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
    <?php endif; ?>
<?php layout_end('script.js'); ?>
