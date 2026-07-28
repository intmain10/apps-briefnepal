<?php
/**
 * Cardly blog — index (/blog) and single article (/blog/<slug>).
 *
 * Served only on the Cardly domain; the Toolzy blog is a separate section on
 * apps.briefnepal.com. Content comes from includes/cardly_blog.php.
 *
 * @package Toolzy\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';
require_once __DIR__ . '/includes/cardly_blog.php';
require_once __DIR__ . '/includes/markdown.php';

$slug  = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$posts = cardly_posts();

/* ---------------------------------------------------------------- index ---- */
if ($slug === '') {
    $page = [
        'title'       => 'Cardly Blog, Founder Stories & Digital Identity Guides',
        'description' => 'Stories and guides from the team behind Cardly on digital business cards, QR sharing, networking and the future of professional identity.',
        'canonical'   => cardly_link('blog'),
        'image'       => url('assets/images/cardly-og.png'),
        'is_cardly'   => true,
        'breadcrumb'  => [
            ['name' => 'Cardly', 'url' => cardly_link()],
            ['name' => 'Blog',   'url' => cardly_link('blog')],
        ],
        'jsonld' => [
            '@context'    => 'https://schema.org',
            '@type'       => 'Blog',
            'name'        => 'Cardly Blog',
            'url'         => cardly_link('blog'),
            'description' => 'Founder stories and guides on digital business cards, QR sharing and professional identity.',
            'publisher'   => ['@type' => 'Organization', 'name' => 'Cardly', 'url' => cardly_link()],
            'blogPost'    => array_map(fn($p) => [
                '@type'         => 'BlogPosting',
                'headline'      => $p['title'],
                'description'   => $p['excerpt'],
                'datePublished' => $p['date'],
                'url'           => cardly_link('blog/' . $p['slug']),
                'author'        => ['@type' => 'Person', 'name' => $p['author']],
            ], $posts),
        ],
    ];

    require __DIR__ . '/includes/header.php';
    ?>
    <section class="page-head">
      <div class="container">
        <h1>The <span class="grad">Cardly</span> Blog</h1>
        <p>Founder stories, product notes and practical guides on sharing who you are, without paper cards or a wall of links.</p>
      </div>
    </section>

    <section class="section--tight container">
      <div class="post-grid">
        <?php foreach ($posts as $p): ?>
          <a class="post-card" href="<?= eattr(cardly_link('blog/' . $p['slug'])) ?>">
            <div class="post-card__cover">
              <?php if (!empty($p['cover'])): ?>
                <img src="<?= eattr(url($p['cover'] . '?v=' . OMNITOOLS_VERSION)) ?>" alt="<?= eattr($p['title']) ?>" loading="lazy" width="600" height="340">
              <?php else: ?>
                <?= icon_svg('doc') ?>
              <?php endif; ?>
            </div>
            <div class="post-card__body">
              <span class="post-card__tag"><?= e($p['tag']) ?></span>
              <h2 class="post-card__title"><?= e($p['title']) ?></h2>
              <p class="post-card__excerpt"><?= e($p['excerpt']) ?></p>
              <div class="post-card__meta"><?= e(date('M j, Y', strtotime($p['date']))) ?> · <?= e($p['author']) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-8">
        <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px">Create your free card</a>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* --------------------------------------------------------------- article --- */
$post = cardly_post($slug);
if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$bodyHtml  = md_to_html((string)($post['body'] ?? ''));
$morePosts = array_slice(array_filter($posts, fn($p) => $p['slug'] !== $slug), 0, 3);
$ogImage   = !empty($post['cover']) ? url($post['cover']) : url('assets/images/cardly-og.png');

$page = [
    'title'       => $post['title'] . ' | Cardly',
    'description' => $post['excerpt'],
    'canonical'   => cardly_link('blog/' . $slug),
    'og_type'     => 'article',
    'image'       => $ogImage,
    'is_cardly'   => true,
    'breadcrumb'  => [
        ['name' => 'Cardly',        'url' => cardly_link()],
        ['name' => 'Blog',          'url' => cardly_link('blog')],
        ['name' => $post['title'],  'url' => cardly_link('blog/' . $slug)],
    ],
    'jsonld' => [
        '@context'      => 'https://schema.org',
        '@type'         => 'BlogPosting',
        'headline'      => $post['title'],
        'description'   => $post['excerpt'],
        'datePublished' => $post['date'],
        'dateModified'  => $post['date'],
        'image'         => [$ogImage],
        'inLanguage'    => 'en',
        'author'        => [
            '@type' => 'Person',
            'name'  => $post['author'],
            'url'   => 'https://apps.briefnepal.com/shushant-singh',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name'  => 'Cardly',
            'url'   => cardly_link(),
            'logo'  => ['@type' => 'ImageObject', 'url' => url('assets/images/cardly-icon.png')],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => cardly_link('blog/' . $slug)],
        'about'            => ['@type' => 'WebApplication', 'name' => 'Cardly', 'url' => cardly_link()],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= eattr(cardly_link()) ?>">Cardly</a><span class="breadcrumb__sep">/</span>
    <a href="<?= eattr(cardly_link('blog')) ?>">Blog</a><span class="breadcrumb__sep">/</span>
    <span aria-current="page"><?= e($post['tag']) ?></span>
  </nav>
</div>

<div class="container" style="padding-bottom:56px">
  <article class="article">
    <span class="post-card__tag"><?= e($post['tag']) ?></span>
    <h1 style="font-size:clamp(30px,5vw,46px);margin-top:8px"><?= e($post['title']) ?></h1>
    <p class="muted mt-2"><?= e(date('F j, Y', strtotime($post['date']))) ?> · By <?= e($post['author']) ?>, founder of Cardly</p>

    <?php if (!empty($post['cover'])): ?>
    <div class="article__cover article__cover--photo">
      <img src="<?= eattr(url($post['cover'] . '?v=' . OMNITOOLS_VERSION)) ?>" alt="<?= eattr($post['title']) ?>" width="1200" height="630">
    </div>
    <?php endif; ?>

    <div class="prose"><?= $bodyHtml ?></div>

    <div class="widget mt-8" style="border-radius:var(--radius-lg);text-align:center">
      <h3 class="widget__title" style="justify-content:center">Build your own card</h3>
      <p class="muted" style="font-size:15px;margin-bottom:14px">One link with your contact details, socials, portfolio and a QR code. Free, no watermark, no app needed.</p>
      <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>">Create your free card →</a>
    </div>
  </article>
</div>

<?php if ($morePosts): ?>
<section class="section container">
  <div class="section__head"><div><h2 class="section__title">More from the blog</h2></div></div>
  <div class="post-grid">
    <?php foreach ($morePosts as $p): ?>
      <a class="post-card" href="<?= eattr(cardly_link('blog/' . $p['slug'])) ?>">
        <div class="post-card__cover">
          <?php if (!empty($p['cover'])): ?>
            <img src="<?= eattr(url($p['cover'] . '?v=' . OMNITOOLS_VERSION)) ?>" alt="<?= eattr($p['title']) ?>" loading="lazy" width="600" height="340">
          <?php else: ?>
            <?= icon_svg('doc') ?>
          <?php endif; ?>
        </div>
        <div class="post-card__body">
          <span class="post-card__tag"><?= e($p['tag']) ?></span>
          <h3 class="post-card__title"><?= e($p['title']) ?></h3>
          <p class="post-card__excerpt"><?= e($p['excerpt']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
