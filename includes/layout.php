<?php

function asset_href(string $file): string
{
    $path = app_root() . '/' . ltrim($file, '/');
    $version = is_file($path) ? (string) filemtime($path) : '1';
    return htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '?v=' . $version;
}

function theme_switch(): string
{
    return <<<HTML
<div class="theme-switch" role="group" aria-label="Тема оформления">
  <button type="button" data-theme-choice="dark" aria-pressed="true">Тёмная</button>
  <button type="button" data-theme-choice="light" aria-pressed="false">Светлая</button>
</div>
HTML;
}

function render_head(string $title): void
{
    $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $style = asset_href('style.css');
    $icon = asset_href('img/favicon.svg');
    echo <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <link rel="icon" href="{$icon}" type="image/svg+xml">
  <script>
    (function () {
      try {
        var saved = localStorage.getItem('qdigest-theme');
        if (saved === 'light' || saved === 'dark') {
          document.documentElement.setAttribute('data-theme', saved);
        }
      } catch (e) {}
    })();
  </script>
  <title>{$safe}</title>
  <link rel="stylesheet" href="https://fonts.cdnfonts.com/css/iqos">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="{$style}">
</head>
HTML;
}

function layout_start(string $title, string $active): void
{
    render_head($title);
    $user = htmlspecialchars((string) current_user(), ENT_QUOTES, 'UTF-8');
    $role = is_admin() ? 'полный доступ' : 'только просмотр';
    $bodyClass = is_admin() ? 'is-admin' : 'is-expert';
    $digest = $active === 'digest' ? ' class="active" aria-current="page"' : '';
    $story = $active === 'story' ? ' class="active" aria-current="page"' : '';
    $team = $active === 'team' ? ' class="active" aria-current="page"' : '';
    $edit = $active === 'edit' ? ' class="active" aria-current="page"' : '';
    $editLink = is_admin() ? '<a href="edit.php"' . $edit . '>Редактирование</a>' : '';
    $themeSwitch = theme_switch();
    echo <<<HTML
<body class="{$bodyClass}">
  <aside id="sidebar" class="sidebar">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true"></span>
      <div>
        <strong>Q digest</strong>
        <em>dream team</em>
      </div>
    </div>
    <nav class="side-nav">
      <a href="index.php"{$digest}>Q digest</a>
      <a href="storytelling.php"{$story}>storytelling</a>
      <a href="team-building.php"{$team}>Team building</a>
      {$editLink}
    </nav>
    {$themeSwitch}
    <button type="button" class="btn side-feedback js-feedback">Напиши мне</button>
    <div class="side-foot">
      <p class="who"><span>{$user}</span> {$role}</p>
      <a class="logout" href="logout.php">Выйти</a>
    </div>
  </aside>
  <div class="shell">
    <div class="shell-bar">
      <button type="button" id="menu-toggle" class="menu-toggle" aria-label="Открыть меню">Меню</button>
      {$themeSwitch}
    </div>
HTML;
}

function layout_end(string $script = ''): void
{
    $pageScript = $script === '' ? '' : '<script src="' . asset_href($script) . '"></script>';
    $theme = asset_href('theme.js');
    $ui = asset_href('ui.js');
    $feedback = asset_href('feedback.js');
    echo <<<HTML
  </div>
  <div id="feedback-modal" class="modal" hidden>
    <div class="modal-content">
      <button type="button" class="close-modal" aria-label="Закрыть">&times;</button>
      <p class="eyebrow">Анонимно</p>
      <h2>Напиши мне</h2>
      <p class="modal-note">Сообщение уйдёт на nastasya_zablotskaya@mail.ru</p>
      <form id="feedback-form">
        <textarea id="feedback-message" placeholder="Ваше пожелание по работе..." required></textarea>
        <button type="submit" class="btn">Отправить</button>
      </form>
    </div>
  </div>
  <script src="{$theme}"></script>
  <script src="{$ui}"></script>
  <script src="{$feedback}"></script>
  {$pageScript}
  <script>
    var sidebar = document.getElementById('sidebar');
    document.getElementById('menu-toggle')?.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    sidebar?.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () { sidebar.classList.remove('open'); });
    });
  </script>
</body>
</html>
HTML;
}
