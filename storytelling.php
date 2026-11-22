<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/staff.php';
require_once __DIR__ . '/includes/storytelling.php';
require_login();

$directory = staff_load();
$story = storytelling_load($directory);
$people = storytelling_people($directory, $story);
$hero = $people[$story['hero']] ?? null;
$participants = $people;
unset($participants[$story['hero']]);
$showHeroAvatar = $hero !== null && $story['heroVisual'] === 'avatar';
$heroMedia = $showHeroAvatar ? $hero['avatar'] : 'img/superhero_Q_smooth.webm';

function render_story_avatar(string $path, string $name, string $class, bool $motion = false): void
{
    $src = asset_href($path);
    $class = storytelling_escape($class);
    $name = storytelling_escape($name);
    if (storytelling_video($path)) {
        $playback = $motion ? ' autoplay' : '';
        echo "<video class=\"$class\" src=\"$src\" aria-label=\"$name\" width=\"900\" height=\"985\" muted playsinline preload=\"metadata\"$playback></video>";
    } else {
        echo "<img class=\"$class\" src=\"$src\" alt=\"$name\">";
    }
}

function render_story_questions(array $pairs, string $group, bool $overlay = false): void
{
    $class = $overlay ? 'story-qa story-qa-overlay' : 'story-qa';
    ?>
    <div class="<?= $class ?>" data-story-qa>
      <div class="story-questions" role="group" aria-label="Вопросы герою">
        <?php foreach ($pairs as $index => $pair): ?>
          <?php if ($pair['question'] !== ''): ?>
            <?php $answer = $group . '-answer-' . $index; ?>
            <button type="button" class="story-question" style="--story-column: <?= $index + 1 ?>"
                    aria-expanded="false" aria-controls="<?= $answer ?>"><?= storytelling_escape($pair['question']) ?></button>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="story-answers">
        <?php foreach ($pairs as $index => $pair): ?>
          <?php if ($pair['question'] !== ''): ?>
            <div class="story-answer" id="<?= $group ?>-answer-<?= $index ?>" aria-hidden="true"><?= storytelling_escape($pair['answer'] !== '' ? $pair['answer'] : 'Ответ пока не добавлен.') ?></div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
}

layout_start('Storytelling — Q digest', 'story');
?>
    <main>
      <header class="page-head">
        <h1>Storytelling</h1>
      </header>
      <section class="story-feature" aria-labelledby="story-hero-title">
        <h2 id="story-hero-title">Герой этого месяца</h2>
        <?php if ($hero !== null): ?>
          <div class="story-hero-person">
            <p class="story-hero-name"><?= storytelling_escape(trim($hero['lastName'] . ' ' . $hero['firstName'])) ?></p>
            <?php if ($hero['position'] !== ''): ?>
              <p class="story-position"><?= storytelling_escape($hero['position']) ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="story-scene">
          <?php render_story_avatar($heroMedia, 'Герой этого месяца', 'story-feature-media', true); ?>
          <?php if (!$showHeroAvatar && $hero !== null): ?>
            <?php render_story_questions($hero['pairs'], 'hero', true); ?>
          <?php endif; ?>
        </div>
        <?php if ($showHeroAvatar): ?>
          <?php render_story_questions($hero['pairs'], 'hero'); ?>
        <?php endif; ?>
      </section>

      <section aria-labelledby="story-participants-title">
        <h2 id="story-participants-title">Участники Storytelling</h2>
        <div id="story-list" class="team-grid">
          <?php if ($participants === []): ?>
            <p class="empty-message">Список участников пуст.</p>
          <?php endif; ?>
          <?php foreach ($participants as $id => $person): ?>
            <?php $name = $person['lastName'] . ' ' . $person['firstName']; ?>
            <button type="button" class="employee-card story-card" data-story-open="story-<?= $id ?>" aria-haspopup="dialog">
              <?php render_story_avatar($person['avatar'], '', 'story-card-media', true); ?>
              <span class="story-last"><?= storytelling_escape($name) ?></span>
              <span class="story-role"><?= storytelling_escape($person['position']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </section>

      <?php foreach ($participants as $id => $person): ?>
        <?php $name = $person['lastName'] . ' ' . $person['firstName']; ?>
        <dialog class="story-native-dialog modal-content" id="story-<?= $id ?>" aria-labelledby="story-name-<?= $id ?>">
          <form method="dialog">
            <button class="close-modal" aria-label="Закрыть">&times;</button>
          </form>
          <?php render_story_avatar($person['avatar'], $name, 'story-photo'); ?>
          <h2 id="story-name-<?= $id ?>"><?= storytelling_escape($name) ?></h2>
          <p class="story-position"><?= storytelling_escape($person['position']) ?></p>
          <?php render_story_questions($person['pairs'], 'story-' . $id); ?>
        </dialog>
      <?php endforeach; ?>
    </main>
<?php layout_end('story.js'); ?>
