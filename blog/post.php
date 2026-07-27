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

/**
 * Records the tools md_to_html() actually mounted, in first-appearance order.
 *
 * The body is rendered before $page is assembled, so this list can gate the
 * engine bundles in includes/footer.php — an embedded tool whose JavaScript
 * never loads would sit on its spinner forever. Collecting during the render
 * rather than re-scanning the source keeps the two exactly in step: a
 * shortcode inside a code fence, or inline mid-sentence, is not an embed and
 * must not pull in a bundle.
 *
 * @return array<int,string>
 */
function article_tool_embeds(?string $mounted = null): array
{
    static $slugs = [];
    if ($mounted !== null && !in_array($mounted, $slugs, true)) {
        $slugs[] = $mounted;
    }
    return $slugs;
}

/**
 * Render an in-article tool mount. The markup mirrors a tool page so the same
 * engines and the same per-tool privacy notice apply, with no second code path.
 * An unknown slug renders nothing rather than leaving a broken shell behind.
 */
function tool_embed_html(string $slug): string
{
    $tool = get_tool($slug);
    if (!$tool) {
        return '';
    }
    article_tool_embeds($slug);
    $isServer = (($tool['processing'] ?? 'client') === 'server');
    $notice = $isServer
        ? '<strong>Processed on our server.</strong> This conversion needs software a browser cannot provide. Your file is sent over HTTPS, given a random temporary name, and deleted immediately after your download.'
        : '<strong>Processed on your device.</strong> This tool runs entirely in your browser, your file is never uploaded, so nothing is stored, logged or transmitted.';

    return '<div class="tool-embed" id="tool-' . eattr($slug) . '">'
        . '<div class="tool-embed__head">'
        . '<span class="tool-embed__label">' . e($tool['name']) . '</span>'
        . '<a class="tool-embed__link" href="' . eattr(url($slug)) . '">Open full tool &rarr;</a>'
        . '</div>'
        . '<div class="tool-workspace" data-tool="' . eattr($slug) . '">'
        . '<div class="text-center" style="padding:40px"><div class="spinner" style="margin:0 auto"></div>'
        . '<p class="muted mt-4">Loading tool&hellip;</p></div>'
        . '</div>'
        . '<p class="notice notice--' . ($isServer ? 'info' : 'success') . '">' . $notice . '</p>'
        . '</div>';
}

/** Minimal, safe server-side Markdown → HTML for article bodies. */
function md_to_html(string $src): string
{
    $src = str_replace("\r\n", "\n", $src);
    $lines = explode("\n", $src);
    $html = ''; $inList = ''; $inCode = false; $para = [];
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
        // [tool:slug] on its own line — a block embed, so it must close any open
        // paragraph or list rather than be inlined into one.
        if (preg_match('/^\s*\[tool:([a-z0-9-]+)\]\s*$/', $line, $m)) {
            $flush(); if ($inList) { $html .= "</$inList>"; $inList = ''; }
            $html .= tool_embed_html($m[1]);
            continue;
        }
        if (preg_match('/^(#{2,4})\s+(.*)$/', $line, $m)) { $flush(); if ($inList) { $html .= "</$inList>"; $inList = ''; } $lvl = strlen($m[1]); $html .= "<h$lvl>" . $inline($m[2]) . "</h$lvl>"; continue; }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) { $flush(); if ($inList !== 'ul') { if ($inList) { $html .= "</$inList>"; } $html .= '<ul>'; $inList = 'ul'; } $html .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) { $flush(); if ($inList !== 'ol') { if ($inList) { $html .= "</$inList>"; } $html .= '<ol>'; $inList = 'ol'; } $html .= '<li>' . $inline($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*>\s?(.*)$/', $line, $m)) { $flush(); if ($inList) { $html .= "</$inList>"; $inList = ''; } $html .= '<blockquote>' . $inline($m[1]) . '</blockquote>'; continue; }
        if (trim($line) === '') { $flush(); if ($inList) { $html .= "</$inList>"; $inList = ''; } continue; }
        $para[] = trim($line);
    }
    $flush(); if ($inList) { $html .= "</$inList>"; }
    return $html;
}

$relatedTool = get_tool($post['related'] ?? '');
$morePosts = array_slice(array_filter(get_posts(), fn($p) => $p['slug'] !== $slug), 0, 3);

// Render the body up front: what it mounts decides which engine bundles the
// footer has to load, and $page is assembled before any output is echoed.
$bodyHtml  = md_to_html((string)($post['body'] ?? ''));
$embeds    = article_tool_embeds();
$embedsPdf = false;
foreach ($embeds as $es) {
    if ((get_tool($es)['category'] ?? '') === 'pdf') {
        $embedsPdf = true;
        break;
    }
}
// The sidebar promotes whatever the article is actually about: the first
// embedded tool if there is one, otherwise the post's declared related tool.
$sidebarTool = $embeds ? get_tool($embeds[0]) : $relatedTool;
$sidebarKin  = $sidebarTool ? related_tools($sidebarTool['slug'], 5) : [];

$page = [
    'title'       => $post['title'] . ' | ' . SITE_NAME . ' Blog',
    'description' => $post['excerpt'],
    'canonical'   => url('blog/' . $slug),
    'og_type'     => 'article',
    'is_tool'     => (bool) $embeds,
    'is_pdf'      => $embedsPdf,
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

<div class="container">
  <div class="tool-layout">
    <article class="tool-main article article--wide">
      <span class="post-card__tag"><?= e($post['tag']) ?></span>
      <h1 style="font-size:clamp(30px,5vw,46px);margin-top:8px"><?= e($post['title']) ?></h1>
      <p class="muted mt-2"><?= e(date('F j, Y', strtotime($post['date']))) ?> · By <?= e($post['author']) ?></p>
      <?php if (!$embeds): ?>
      <div class="article__cover"><?= icon_svg($post['icon'] ?? 'doc') ?></div>
      <?php endif; ?>
      <div class="prose"><?= $bodyHtml ?></div>

      <?php if ($relatedTool && !$embeds): ?>
      <div class="widget mt-8" style="border-radius:var(--radius-lg)">
        <h3 class="widget__title">Try the tool</h3>
        <a class="btn btn--primary" href="<?= eattr(url($relatedTool['slug'])) ?>"><?= e($relatedTool['name']) ?> →</a>
      </div>
      <?php endif; ?>
    </article>

    <aside class="tool-sidebar">
      <?php if (ADSENSE_ENABLED): ?><div class="ad-slot">Advertisement</div><?php endif; ?>

      <?php if ($sidebarTool): ?>
      <div class="widget">
        <h3 class="widget__title"><?= $embeds ? 'Tool in this guide' : 'Try the tool' ?></h3>
        <p class="muted" style="font-size:14px;margin-bottom:12px"><?= e($sidebarTool['desc']) ?></p>
        <a class="btn btn--primary btn--block" href="<?= $embeds ? '#tool-' . eattr($sidebarTool['slug']) : eattr(url($sidebarTool['slug'])) ?>"><?= e($sidebarTool['name']) ?> →</a>
      </div>
      <?php endif; ?>

      <?php if ($sidebarKin): ?>
      <div class="widget">
        <h3 class="widget__title">Related Tools</h3>
        <div class="widget__list">
          <?php foreach ($sidebarKin as $r): ?>
            <a href="<?= eattr(url($r['slug'])) ?>"><span class="dot" style="background:<?= eattr(category_of($r['slug'])['color'] ?? 'var(--accent)') ?>"></span><?= e($r['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="widget">
        <h3 class="widget__title">All Tools</h3>
        <a class="btn btn--ghost btn--block" href="<?= eattr(url('tools')) ?>">Browse <?= tools_count() ?>+ free tools</a>
      </div>
    </aside>
  </div>
</div>

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
