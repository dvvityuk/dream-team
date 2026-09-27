<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$next = safe_next($_GET['next'] ?? ($_POST['next'] ?? 'index.php'));
$error = (string) ($GLOBALS['session_error'] ?? '');

if ($error === '' && current_user() !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to($next);
}

if ($error === '' && !storage_ready()) {
    $error = 'Нет прав на запись в каталог data. На сервере выполните chown для www-data.';
} elseif ($error === '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (attempt_login($username, $password)) {
        session_write_close();
        redirect_to(safe_next($_POST['next'] ?? 'index.php'));
    } elseif (!empty($GLOBALS['session_error'])) {
        $error = (string) $GLOBALS['session_error'];
    } else {
        $error = 'Неверный логин или пароль';
    }
}

render_head('Вход — Q digest');
?>
<body class="login-body">
  <main class="login-card">
    <div class="brand login-brand">
      <span class="brand-mark" aria-hidden="true"></span>
      <div>
        <strong>Q digest</strong>
        <em>dream team</em>
      </div>
    </div>
    <p class="eyebrow">Закрытый контур</p>
    <h1>Вход</h1>
    <p class="login-lead">Аккаунт expert открывает просмотр. Аккаунт admin добавляет редактирование.</p>
    <?php if ($error !== ''): ?>
      <p class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars(app_web_base() . '/login.php', ENT_QUOTES, 'UTF-8') ?>" autocomplete="on">
      <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
      <label for="username">Аккаунт</label>
      <input id="username" name="username" autocomplete="username" required placeholder="expert или admin">
      <label for="password">Пароль</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit" class="btn">Войти</button>
    </form>
    <?= theme_switch() ?>
  </main>
  <script src="<?= asset_href('theme.js') ?>"></script>
</body>
</html>
