<?php
/**
 * Blog index.
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog.php';

$posts = get_posts();

$page = [
    'title'       => 'Blog, Guides, Tips & Tutorials | ' . SITE_NAME,
    'description' => 'Practical guides and tutorials on images, PDF, developer tools, SEO and more from the ' . SITE_NAME . ' team.',
    'canonical'   => url('blog'),
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => 'Blog', 'url' => url('blog')],
    ],
];

require __DIR__ . '/../includes/header.php';
?>

<section class="page-head">
  <div class="container">
    <h1>The Toolzy Blog</h1>
    <p>Guides, tips and deep-dives to help you get more from free web tools.</p>
  </div>
</section>

<section class="section--tight container">
  <div class="post-grid">
    <?php foreach ($posts as $p): ?>
      <a class="post-card" href="<?= eattr(url('blog/' . $p['slug'])) ?>">
        <div class="post-card__cover"><?= icon_svg($p['icon'] ?? 'doc') ?></div>
        <div class="post-card__body">
          <span class="post-card__tag"><?= e($p['tag']) ?></span>
          <h2 class="post-card__title"><?= e($p['title']) ?></h2>
          <p class="post-card__excerpt"><?= e($p['excerpt']) ?></p>
          <div class="post-card__meta"><?= e(date('M j, Y', strtotime($p['date']))) ?> · <?= e($p['author']) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
