<?php
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_login();

$poster = home_image();
$size = @getimagesize(app_root() . '/' . $poster);
layout_start('Главная — Dream Team', 'home');
?>
    <main class="home-main">
      <header class="page-head home-heading">
        <h1>Бездымное будущее<br>Супергерои в деле</h1>
      </header>
      <img class="home-poster" src="<?= asset_href($poster) ?>"
           <?= $size ? $size[3] : '' ?> alt="Постер команды Dream Team">
    </main>
<?php layout_end(); ?>
