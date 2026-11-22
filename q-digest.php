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
        <?php foreach (array_values($employees) as $index => $employee): ?>
          <article class="employee-card digest-card">
            <?php if (preg_match('#^images/[A-Za-z0-9._-]+$#', $employee['image'])): ?>
              <img src="<?= htmlspecialchars($employee['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Фото <?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <h2 id="digest-hero-<?= $index ?>"><?= htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($employee['achievements'], ENT_QUOTES, 'UTF-8') ?></p>
            <button type="button" class="digest-card-open" aria-labelledby="digest-hero-<?= $index ?>" aria-haspopup="dialog" aria-controls="digest-hero"></button>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <dialog id="digest-hero" class="lightbox digest-lightbox" aria-labelledby="digest-hero-name">
      <div class="digest-lightbox-content">
        <img alt="" hidden>
        <div class="digest-lightbox-copy">
          <h2 id="digest-hero-name"></h2>
          <p></p>
        </div>
      </div>
      <form method="dialog">
        <button type="submit" class="lightbox-close" aria-label="Закрыть карточку" autofocus>&times;</button>
      </form>
    </dialog>
<?php layout_end('q-digest.js'); ?>
