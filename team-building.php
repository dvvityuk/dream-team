<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$admin = is_admin() ? '1' : '0';
layout_start('Team building — Q digest', 'team');
?>
    <header class="page-head">
      <p class="eyebrow">Альбом</p>
      <h1>Team building</h1>
      <p class="hero-lead">Папки с фотографиями общих выездов и встреч.</p>
    </header>

    <?php if (is_admin()): ?>
    <form id="create-folder-form" class="folder-create">
      <input type="text" id="folder-name" placeholder="Название папки" maxlength="60" required>
      <button type="submit" class="btn">Создать папку</button>
    </form>
    <?php endif; ?>

    <div id="album-root" class="album-root" data-admin="<?= $admin ?>"></div>
<?php layout_end('album.js'); ?>
