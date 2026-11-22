<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$dateText = htmlspecialchars(header_date_read(), ENT_QUOTES, 'UTF-8');
$employees = employees_read();
layout_start('Q digest', 'digest');
?>
    <header class="page-head">
      <p class="eyebrow">Q store</p>
      <h1>Q digest <span id="header-date"><?= $dateText ?></span></h1>
      <p class="hero-lead">Итоги команды в одном контуре. Фото, цифры и повод сказать спасибо.</p>
    </header>

    <section id="team-list" class="team-grid" aria-live="polite">
      <?php if ($employees === []): ?>
        <p class="empty-message">В Q digest пока нет карточек.</p>
      <?php else: ?>
        <?php foreach ($employees as $employee): ?>
          <article class="employee-card">
            <?php if (preg_match('#^images/[A-Za-z0-9._-]+$#', $employee['image'])): ?>
              <button type="button" class="photo-open digest-photo-open" aria-haspopup="dialog" aria-controls="digest-photo">
                <img src="<?= htmlspecialchars($employee['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Фото <?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?>">
              </button>
            <?php endif; ?>
            <h2><?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($employee['achievements'], ENT_QUOTES, 'UTF-8') ?></p>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <dialog id="digest-photo" class="lightbox digest-lightbox" aria-label="Просмотр фотографии">
      <img alt="">
      <form method="dialog">
        <button type="submit" class="lightbox-close" aria-label="Закрыть фото" autofocus>&times;</button>
      </form>
    </dialog>
<?php layout_end('q-digest.js'); ?>
