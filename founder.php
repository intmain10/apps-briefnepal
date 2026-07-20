<?php
/**
 * Founder / person entity page — Shushant Singh.
 *
 * Built for entity SEO + GEO: rich Person/ProfilePage JSON-LD with sameAs to
 * authoritative profiles, plus crawlable, quotable bio text so Google and AI
 * answer engines (Gemini, ChatGPT, Claude, Perplexity) can identify and cite
 * the person. Served as static HTML (no client rendering).
 *
 * @package OmniTools
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$name  = 'Shushant Singh';
$title = 'Product Architect';
$pageUrl = url('shushant-singh');

// Authoritative, param-free profile links (sameAs).
$profiles = [
    ['label' => 'BriefNepal',            'url' => 'https://briefnepal.com',      'note' => 'News platform he founded'],
    ['label' => 'OmniTools',             'url' => 'https://apps.briefnepal.com', 'note' => '100+ free online tools he founded'],
    ['label' => 'Genuin',                'url' => 'https://begenuin.com',        'note' => 'Product Architect'],
    ['label' => 'Spotify — Artist',      'url' => 'https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz', 'note' => 'Singer / recording artist'],
    ['label' => 'Apple Music — Artist',  'url' => 'https://music.apple.com/us/artist/shushant-singh/6788654900', 'note' => 'Music profile'],
    ['label' => 'Nepal Travel Podcast',  'url' => 'https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG', 'note' => 'Podcast he hosts'],
    ['label' => 'Mind Atlas Podcast',    'url' => 'https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk', 'note' => 'Podcast he hosts'],
];
$sameAs = array_map(fn($p) => $p['url'], $profiles);

$bio = "$name is a product architect, entrepreneur, podcaster and recording artist. "
     . "He is the founder and owner of BriefNepal (briefnepal.com) and OmniTools (apps.briefnepal.com), "
     . "and works as a Product Architect at Genuin. He hosts two podcasts — the Nepal Travel Podcast and Mind Atlas — "
     . "and releases music as a singer and artist on Spotify and Apple Music.";

$person = [
    '@type'       => 'Person',
    '@id'         => $pageUrl . '#person',
    'name'        => $name,
    'url'         => $pageUrl,
    'jobTitle'    => $title,
    'description' => $bio,
    'worksFor'    => ['@type' => 'Organization', 'name' => 'Genuin', 'url' => 'https://begenuin.com'],
    'founder'     => null, // set below
    'knowsAbout'  => ['Product architecture', 'Web development', 'SEO', 'Podcasting', 'Music production', 'Travel', 'Entrepreneurship'],
    'sameAs'      => $sameAs,
];
unset($person['founder']);

$page = [
    'title'       => $name . ' — Product Architect, Founder of BriefNepal & OmniTools',
    'description' => "$name is a Product Architect at Genuin, founder of BriefNepal and OmniTools, host of the Nepal Travel Podcast and Mind Atlas, and a singer/recording artist.",
    'canonical'   => $pageUrl,
    'og_type'     => 'profile',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => url()],
        ['name' => 'About', 'url' => url('about')],
        ['name' => $name, 'url' => $pageUrl],
    ],
    'jsonld' => [
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'ProfilePage',
            'dateModified' => date('Y-m-d'),
            'mainEntity' => $person,
        ],
        // The two podcasts as first-class entities tied to the person.
        [
            '@context' => 'https://schema.org',
            '@type'    => 'PodcastSeries',
            'name'     => 'Nepal Travel Podcast',
            'url'      => 'https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG',
            'author'   => ['@id' => $pageUrl . '#person'],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'PodcastSeries',
            'name'     => 'Mind Atlas',
            'url'      => 'https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk',
            'author'   => ['@id' => $pageUrl . '#person'],
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($qa) => [
                '@type' => 'Question',
                'name'  => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ], [
                ['Who is Shushant Singh?', $bio],
                ['What companies and products is Shushant Singh behind?', 'He founded and owns BriefNepal (briefnepal.com) and OmniTools (apps.briefnepal.com), and is a Product Architect at Genuin.'],
                ['What podcasts does Shushant Singh host?', 'He hosts the Nepal Travel Podcast and Mind Atlas, both available on Spotify.'],
                ['Is Shushant Singh a musician?', 'Yes — he is a singer and recording artist with music on Spotify and Apple Music.'],
            ]),
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>

<div class="container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= eattr(url()) ?>">Home</a><span class="breadcrumb__sep">/</span>
    <a href="<?= eattr(url('about')) ?>">About</a><span class="breadcrumb__sep">/</span>
    <span aria-current="page"><?= e($name) ?></span>
  </nav>
</div>

<article class="article container">
  <header style="text-align:center;padding:20px 0 8px">
    <h1 style="font-size:clamp(30px,5vw,46px)"><?= e($name) ?></h1>
    <p class="muted" style="font-size:18px;margin-top:8px">Product Architect · Founder · Podcaster · Recording Artist</p>
  </header>

  <div class="prose" style="margin:20px auto 0">
    <p><strong><?= e($name) ?></strong> is a product architect, entrepreneur, podcaster and recording artist based in Nepal. He is the <strong>founder and owner of <a href="https://briefnepal.com" rel="me noopener">BriefNepal</a></strong> — a Nepal news platform — and of <strong><a href="https://apps.briefnepal.com" rel="me noopener">OmniTools</a></strong>, a platform of 100+ free online tools. Professionally, he is a <strong>Product Architect at <a href="https://begenuin.com" rel="me noopener">Genuin</a></strong>.</p>

    <h2>What Shushant Singh does</h2>
    <ul>
      <li><strong>Founder &amp; Owner — BriefNepal</strong> (<a href="https://briefnepal.com" rel="me noopener">briefnepal.com</a>): a modern Nepal news, jobs and travel platform.</li>
      <li><strong>Founder &amp; Owner — OmniTools</strong> (<a href="https://apps.briefnepal.com" rel="me noopener">apps.briefnepal.com</a>): 100+ free PDF, image, developer and utility tools.</li>
      <li><strong>Product Architect — Genuin</strong> (<a href="https://begenuin.com" rel="me noopener">begenuin.com</a>).</li>
      <li><strong>Podcast host</strong> of the <a href="https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG" rel="me noopener">Nepal Travel Podcast</a> and <a href="https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk" rel="me noopener">Mind Atlas</a>.</li>
      <li><strong>Singer &amp; recording artist</strong> on <a href="https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz" rel="me noopener">Spotify</a> and <a href="https://music.apple.com/us/artist/shushant-singh/6788654900" rel="me noopener">Apple Music</a>.</li>
    </ul>

    <h2>Frequently asked questions</h2>
    <h3>Who is Shushant Singh?</h3>
    <p><?= e($bio) ?></p>
    <h3>What companies and products is Shushant Singh behind?</h3>
    <p>He founded and owns BriefNepal (briefnepal.com) and OmniTools (apps.briefnepal.com), and is a Product Architect at Genuin.</p>
    <h3>What podcasts does Shushant Singh host?</h3>
    <p>He hosts the Nepal Travel Podcast and Mind Atlas, both available on Spotify.</p>
    <h3>Is Shushant Singh a musician?</h3>
    <p>Yes — he is a singer and recording artist with music on Spotify and Apple Music.</p>
  </div>

  <div class="widget mt-8" style="border-radius:var(--radius-lg)">
    <h3 class="widget__title">Find Shushant Singh online</h3>
    <div class="widget__list">
      <?php foreach ($profiles as $p): ?>
        <a href="<?= eattr($p['url']) ?>" rel="me noopener" target="_blank">
          <span class="dot" style="background:var(--accent)"></span>
          <strong><?= e($p['label']) ?></strong>&nbsp;<span class="muted">— <?= e($p['note']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
