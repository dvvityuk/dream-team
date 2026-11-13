<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff.php';
require_login();

$directory = staff_load();
$people = [];
foreach ($directory['people'] as $person) {
    $people[] = staff_public_person($person);
}
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
$payload = json_encode([
    'fields' => $directory['fields'],
    'people' => $people,
], $flags);
if ($payload === false) {
    $payload = '{"fields":[],"people":[]}';
}
layout_start('storytelling — Q digest', 'story');
?>
    <header class="hero">
      <div class="hero-copy">
        <p class="eyebrow">Q store</p>
        <h1>storytelling</h1>
        <p class="hero-lead">Люди команды. Нажмите на карточку, чтобы открыть информацию об эксперте.</p>
      </div>
    </header>

    <section id="story-list" class="team-grid" aria-live="polite">
      <?php if ($people === []): ?>
        <p class="empty-message">Список сотрудников пуст.</p>
      <?php else: ?>
        <?php foreach ($people as $person): ?>
          <button type="button" class="employee-card story-card" data-story="<?= htmlspecialchars($person['id'], ENT_QUOTES, 'UTF-8') ?>">
            <?php if (preg_match('#^images/[A-Za-z0-9._-]+$#', $person['image'])): ?>
              <img src="<?= htmlspecialchars($person['image'], ENT_QUOTES, 'UTF-8') ?>" alt="Фото <?= htmlspecialchars($person['lastName'] . ' ' . $person['firstName'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
            <span class="story-last"><?= htmlspecialchars($person['lastName'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="story-first"><?= htmlspecialchars($person['firstName'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="story-role"><?= htmlspecialchars($person['position'], ENT_QUOTES, 'UTF-8') ?></span>
          </button>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <div id="story-modal" class="modal story-modal" hidden>
      <div class="modal-content story-dialog" role="dialog" aria-modal="true" aria-labelledby="story-name">
        <button type="button" class="close-modal" id="story-close" aria-label="Закрыть">&times;</button>
        <img id="story-photo" class="story-photo" alt="" hidden>
        <p class="eyebrow">Эксперт</p>
        <h2 id="story-name"></h2>
        <p id="story-position" class="story-position"></p>
        <div id="story-facts"></div>
        <div id="story-editor" hidden>
          <p class="modal-note">В нескольких предложениях...</p>
          <form id="story-form"></form>
          <?php if (is_admin()): ?>
          <div id="story-field-admin" hidden>
            <h3>Поля информации</h3>
            <div id="story-field-list"></div>
            <div class="button-row">
              <button type="button" class="btn ghost" id="story-add-field">Добавить поле</button>
              <button type="button" class="btn" id="story-save-fields">Сохранить список полей</button>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn" id="story-edit">Редактировать информацию</button>
      </div>
    </div>
    <script type="application/json" id="story-bootstrap"><?= $payload ?></script>
<?php layout_end('story.js'); ?>
