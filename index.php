<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$dateText = htmlspecialchars(header_date_read(), ENT_QUOTES, 'UTF-8');
$employees = employees_read();
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
        <p class="empty-message">В Q digest пока нет карточек.</p>
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
<?php layout_end(); ?>
