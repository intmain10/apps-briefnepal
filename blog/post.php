<?php
/**
 * Single blog article.
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/blog.php';

$slug = preg_replace('/[^a-z0-9-]/', '', (string)($_GET['slug'] ?? ''));
$post = get_post($slug);

if (!$post) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

/** Minimal, safe server-side Markdown → HTML for article bodies. */
function md_to_html(string $src): string
{
    $src = str_replace("\r\n", "\n", $src);
    $lines = explode("\n", $src);
    $html = ''; $inList = false; $inCode = false; $para = [];
    $inline = function (string $t): string {
        $t = e($t);
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
        $t = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $t);
        $t = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $t);
        return $t;
    };
    $flush = function () use (&$para, &$html, $inline) {
        if ($para) { $html .= '<p>' . $inline(implode(' ', $para)) . '</p>'; $para = []; }
    };
    foreach ($lines as $line) {
        if (preg_match('/^```/', $line)) { $flush(); if (!$inCode) { $html .= '<pre><code>'; $inCode = true; } else { $html .= '</code></pre>'; $inCode = false; } continue; }
        if ($inCode) { $html .= e($line) . "\n"; continue; }
        if (preg_match('/^(#{2,4})\s+(.*)$/', $line, $m)) { $flush(); if ($inList) { $html .= '</ul>'; $inList = false; } $lvl = strlen($m[1]); $html .= "<h$lvl>" . $inline($m[2]) . "</h$lvl>"; continue; }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) { $flush(); if (!$inList) { $html .= '<ul>'; $inList = true; } $html .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) { $flush(); if (!$inList) { $html .= '<ul>'; $inList = true; } $html .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) { $flush(); if ($inList) { $html .= '</ul>'; $inList = false; } $html .= '<blockquote>' . $inline($m[1]) . '</blockquote>'; continue; }
        if (trim($line) === '') { $flush(); if ($inList) { $html .= '</ul>'; $inList = false; } continue; }
        $para[] = trim($line);
    }
    $flush(); if ($inList) $html .= '</ul>';
    return $html;
}

$relatedTool = get_tool($post['related'] ?? '');
$morePosts = array_slice(array_filter(get_posts(), fn($p) => $p['slug'] !== $slug), 0, 3);

$page = [
    'title'       => $post['title'] . ' | ' . SITE_NAME . ' Blog',
    'description' => $post['excerpt'],
    'canonical'   => url('blog/' . $slug),
    'og_type'     => 'article',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => 'Blog', 'url' => url('blog')],
        ['name' => $post['title'], 'url' => url('blog/' . $slug)],
    ],
    'jsonld' => [
        '@context'      => 'https://schema.org',
        '@type'         => 'BlogPosting',
        'headline'      => $post['title'],
        'description'   => $post['excerpt'],
        'datePublished' => $post['date'],
        'dateModified'  => $post['date'],
        'image'         => [url('assets/images/og-default.png')],
        'author'        => ['@type' => 'Organization', 'name' => $post['author'], 'url' => SITE_URL],
        'publisher'     => [
            '@type' => 'Organization',
            'name'  => SITE_NAME,
            'logo'  => ['@type' => 'ImageObject', 'url' => url('assets/images/logo.png'), 'width' => 1254, 'height' => 1254],
        ],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => url('blog/' . $slug)],
    ],
];

require __DIR__ . '/../includes/header.php';
?>

<div class="container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= eattr(url()) ?>">Home</a><span class="breadcrumb__sep">/</span>
    <a href="<?= eattr(url('blog')) ?>">Blog</a><span class="breadcrumb__sep">/</span>
    <span aria-current="page"><?= e($post['tag']) ?></span>
  </nav>
</div>

<article class="article container">
  <span class="post-card__tag"><?= e($post['tag']) ?></span>
  <h1 style="font-size:clamp(30px,5vw,46px);margin-top:8px"><?= e($post['title']) ?></h1>
  <p class="muted mt-2"><?= e(date('F j, Y', strtotime($post['date']))) ?> · By <?= e($post['author']) ?></p>
  <div class="article__cover"><?= icon_svg($post['icon'] ?? 'doc') ?></div>
  <div class="prose"><?= md_to_html($post['body']) ?></div>

  <?php if ($relatedTool): ?>
  <div class="widget mt-8" style="border-radius:var(--radius-lg)">
    <h3 class="widget__title">Try the tool</h3>
    <a class="btn btn--primary" href="<?= eattr(url($relatedTool['slug'])) ?>"><?= e($relatedTool['name']) ?> →</a>
  </div>
  <?php endif; ?>
</article>

<section class="section container">
  <div class="section__head"><div><h2 class="section__title">More Articles</h2></div></div>
  <div class="post-grid">
    <?php foreach ($morePosts as $p): ?>
      <a class="post-card" href="<?= eattr(url('blog/' . $p['slug'])) ?>">
        <div class="post-card__cover"><?= icon_svg($p['icon'] ?? 'doc') ?></div>
        <div class="post-card__body">
          <span class="post-card__tag"><?= e($p['tag']) ?></span>
          <h3 class="post-card__title"><?= e($p['title']) ?></h3>
          <p class="post-card__excerpt"><?= e($p['excerpt']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
