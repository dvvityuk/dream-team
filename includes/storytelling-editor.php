<section id="storytelling-panel" class="admin-panel story-admin">
  <h2>Storytelling</h2>
  <p class="panel-note">Имя, должность и основной аватар участников синхронизируются со списком сотрудников.</p>

  <form class="story-admin-form" action="save_storytelling.php" method="post">
    <input type="hidden" name="csrf" value="<?= storytelling_escape($storyToken) ?>">
    <input type="hidden" name="action" value="hero">
    <h3>Герой этого месяца</h3>
    <label>Участник
      <select name="staffId" data-story-hero-select>
        <option value="">Герой не выбран</option>
        <?php foreach ($storyPeople as $id => $person): ?>
          <option value="<?= $id ?>" <?= $id === $story['hero'] ? 'selected' : '' ?>><?= storytelling_escape(staff_display_name($person)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Медиа героя
      <select name="heroVisual">
        <option value="video">Ролик superhero_Q_smooth.webm</option>
        <option value="avatar" <?= $story['heroVisual'] === 'avatar' ? 'selected' : '' ?>>Аватар выбранного участника</option>
      </select>
    </label>
    <button type="submit" class="btn">Сохранить героя месяца</button>
    <p class="story-form-status" role="status"></p>
  </form>

  <h3>Участники Storytelling</h3>
  <form class="story-admin-form" action="save_storytelling.php" method="post">
    <input type="hidden" name="csrf" value="<?= storytelling_escape($storyToken) ?>">
    <input type="hidden" name="action" value="add">
    <label>Сотрудник из общего списка
      <select name="staffId" data-story-add-select required>
        <option value="">Выберите сотрудника</option>
        <?php foreach ($directory['people'] as $person): ?>
          <?php if (!isset($storyPeople[$person['id']])): ?>
            <option value="<?= $person['id'] ?>"><?= storytelling_escape(staff_display_name($person)) ?></option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn">Добавить в Storytelling</button>
    <p class="story-form-status" role="status"></p>
  </form>

  <?php foreach ($storyPeople as $id => $person): ?>
    <?php $customAvatar = $story['members'][$id]['avatar']; ?>
    <details class="story-admin-member" data-story-member="<?= $id ?>">
      <summary><?= storytelling_escape(staff_display_name($person)) ?></summary>
      <form class="story-admin-form" action="save_storytelling.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= storytelling_escape($storyToken) ?>">
        <input type="hidden" name="staffId" value="<?= $id ?>">
        <label>Аватар
          <select name="avatarMode">
            <option value="main">Основной аватар</option>
            <option value="custom" <?= $customAvatar !== '' ? 'selected' : '' ?>>Отдельный аватар для Storytelling</option>
          </select>
        </label>
        <label>Загрузить отдельный аватар
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp,image/avif,video/webm,video/mp4">
        </label>
        <?php if ($customAvatar !== ''): ?>
          <a href="<?= storytelling_escape($customAvatar) ?>" target="_blank" rel="noopener">Открыть текущий отдельный аватар</a>
        <?php endif; ?>
        <p class="panel-note">JPEG, PNG, GIF, WebP, AVIF, WebM и MP4 до 8 МБ.</p>
        <?php foreach ($person['pairs'] as $index => $pair): ?>
          <fieldset>
            <legend>Табличка <?= $index + 1 ?></legend>
            <label>Вопрос
              <input name="question[]" maxlength="120" value="<?= storytelling_escape($pair['question']) ?>">
            </label>
            <label>Ответ
              <textarea name="answer[]" maxlength="5000"><?= storytelling_escape($pair['answer']) ?></textarea>
            </label>
          </fieldset>
        <?php endforeach; ?>
        <div class="button-row">
          <button type="submit" name="action" value="update" class="btn">Сохранить участника</button>
          <button type="submit" name="action" value="remove" class="btn danger" formnovalidate>Убрать из Storytelling</button>
        </div>
        <p class="story-form-status" role="status"></p>
      </form>
    </details>
  <?php endforeach; ?>
</section>
