<?php
/**
 * Founder / person entity page — Shushant Singh.
 *
 * Built for entity SEO + GEO: rich Person/ProfilePage JSON-LD with sameAs to
 * authoritative profiles, plus crawlable, quotable bio text so Google and AI
 * answer engines (Gemini, ChatGPT, Claude, Perplexity) can identify and cite
 * the person. Served as static HTML (no client rendering).
 *
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$name  = 'Shushant Singh';
$legalName = 'Shushant Kumar Singh';
$title = 'Founder & Product Architect';
$pageUrl = url('shushant-singh');

// Authoritative, param-free profile links (sameAs).
$profiles = [
    ['label' => 'LinkedIn',              'url' => 'https://www.linkedin.com/in/shushant-kumar-singh/', 'note' => 'Professional profile'],
    ['label' => 'BriefNepal',            'url' => 'https://briefnepal.com',      'note' => 'News platform he founded'],
    ['label' => 'Toolzy',             'url' => 'https://apps.briefnepal.com', 'note' => '100+ free online tools he founded'],
    ['label' => 'Genuin',                'url' => 'https://begenuin.com',        'note' => 'Product Architect'],
    ['label' => 'Spotify, Artist',      'url' => 'https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz', 'note' => 'Singer / recording artist'],
    ['label' => 'Apple Music, Artist',  'url' => 'https://music.apple.com/us/artist/shushant-singh/6788654900', 'note' => 'Music profile'],
    ['label' => 'Nepal Travel Podcast',  'url' => 'https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG', 'note' => 'Podcast he hosts'],
    ['label' => 'Mind Atlas Podcast',    'url' => 'https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk', 'note' => 'Podcast he hosts'],
];
$sameAs = array_map(fn($p) => $p['url'], $profiles);

$bio = "$name (Shushant Kumar Singh) is a Nepali product architect, entrepreneur, podcaster and recording "
     . "artist who builds digital products that make everyday life simpler. He is the founder and owner of "
     . "BriefNepal (briefnepal.com), a modern Nepali news, jobs and travel platform, and of Toolzy "
     . "(apps.briefnepal.com), a growing library of 100+ free, privacy-first online tools. Professionally he "
     . "works as a Product Architect at Genuin and is based in Ahmedabad, India. He holds a degree from "
     . "Charotar University of Science and Technology (CHARUSAT). Beyond software, he hosts two podcasts, the "
     . "Nepal Travel Podcast and Mind Atlas, and releases music as a singer and recording artist on Spotify "
     . "and Apple Music.";

$person = [
    '@type'         => 'Person',
    '@id'           => $pageUrl . '#person',
    'name'          => $name,
    'alternateName' => $legalName,
    'givenName'     => 'Shushant',
    'familyName'    => 'Singh',
    'url'           => $pageUrl,
    'jobTitle'      => $title,
    'description'   => $bio,
    'nationality'   => ['@type' => 'Country', 'name' => 'Nepal'],
    'homeLocation'  => ['@type' => 'Place', 'name' => 'Ahmedabad, India'],
    'workLocation'  => ['@type' => 'Place', 'name' => 'Ahmedabad, Gujarat, India'],
    'hasOccupation' => [
        ['@type' => 'Occupation', 'name' => 'Product Architect'],
        ['@type' => 'Occupation', 'name' => 'Entrepreneur'],
        ['@type' => 'Occupation', 'name' => 'Podcast Host'],
        ['@type' => 'Occupation', 'name' => 'Recording Artist'],
    ],
    'worksFor'      => ['@type' => 'Organization', 'name' => 'Genuin', 'url' => 'https://begenuin.com'],
    'founder'       => [
        ['@type' => 'Organization', 'name' => 'BriefNepal', 'url' => 'https://briefnepal.com'],
        ['@type' => 'Organization', 'name' => 'Toolzy', 'url' => 'https://apps.briefnepal.com'],
    ],
    'alumniOf'      => ['@type' => 'CollegeOrUniversity', 'name' => 'Charotar University of Science and Technology (CHARUSAT)', 'url' => 'https://www.charusat.ac.in'],
    'knowsAbout'    => ['Product architecture', 'Software engineering', 'SaaS', 'Artificial intelligence', 'Android development', 'Web development', 'Digital products', 'SEO', 'Generative engine optimization', 'Nepal travel', 'Travel technology', 'Podcasting', 'Music', 'Entrepreneurship'],
    'sameAs'        => $sameAs,
];

$page = [
    'title'       => $name . ' (Shushant Kumar Singh), Founder of BriefNepal & Toolzy',
    'description' => "$name (Shushant Kumar Singh) is a Nepali Product Architect at Genuin, founder of BriefNepal and Toolzy, CHARUSAT alumnus, host of the Nepal Travel Podcast and Mind Atlas, and a singer/recording artist based in Ahmedabad.",
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
            // Full ISO 8601 datetime (with time + offset) — Google rejects a
            // date-only value here ("Invalid datetime value for dateModified").
            'datePublished' => '2026-07-20T00:00:00+00:00',
            'dateModified'  => date('c'),
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
                ['What companies and products is Shushant Singh behind?', 'He founded and owns BriefNepal (briefnepal.com), a Nepali news, jobs and travel platform, and Toolzy (apps.briefnepal.com), a platform of 100+ free online tools. He also built Cardly, a free digital business card product. Professionally he is a Product Architect at Genuin.'],
                ['What does Shushant Singh do as a Product Architect?', 'As a Product Architect at Genuin, he designs product experiences, user flows and the technical architecture behind digital products, bridging design, engineering and business needs.'],
                ['What podcasts does Shushant Singh host?', 'He hosts two podcasts: the Nepal Travel Podcast, about travel and stories from Nepal, and Mind Atlas, exploring ideas, the mind and meaningful conversations. Both are on Spotify.'],
                ['Is Shushant Singh a musician?', 'Yes, he is a singer and recording artist with music available on Spotify and Apple Music.'],
                ['Where is Shushant Singh based?', 'He is Nepali and is currently based in Ahmedabad, India, where he works as a Product Architect at Genuin.'],
                ['Where did Shushant Singh study?', 'He holds a degree from Charotar University of Science and Technology (CHARUSAT).'],
                ['How can I follow or contact Shushant Singh?', 'You can find him on LinkedIn (linkedin.com/in/shushant-kumar-singh), and through BriefNepal, Toolzy, Genuin, his podcasts on Spotify, and his music on Spotify and Apple Music, all linked on this page.'],
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
    <p><strong><?= e($name) ?></strong> (<?= e($legalName) ?>) is a <strong>Nepali</strong> product architect, entrepreneur, podcaster and recording artist, currently based in <strong>Ahmedabad, India</strong>. He builds digital products that make everyday life simpler, from a national news platform to a suite of free online tools. He is the <strong>founder and owner of <a href="https://briefnepal.com" rel="me noopener">BriefNepal</a></strong> and <strong><a href="https://apps.briefnepal.com" rel="me noopener">Toolzy</a></strong>, and works professionally as a <strong>Product Architect at <a href="https://begenuin.com" rel="me noopener">Genuin</a></strong>. Connect with him on <a href="https://www.linkedin.com/in/shushant-kumar-singh/" rel="me noopener">LinkedIn</a>.</p>
    <p>His work spans product architecture and design, software engineering, SaaS, AI, Android and web development, and modern SEO/GEO, and, outside of software, storytelling through podcasts and music. The common thread is a focus on clean, honest, genuinely useful products.</p>

    <h2>Ventures &amp; work</h2>
    <ul>
      <li><strong>Founder &amp; Owner, BriefNepal</strong> (<a href="https://briefnepal.com" rel="me noopener">briefnepal.com</a>): a modern Nepali news, jobs and travel platform.</li>
      <li><strong>Founder &amp; Owner, Toolzy</strong> (<a href="https://apps.briefnepal.com" rel="me noopener">apps.briefnepal.com</a>): 100+ free, privacy-first PDF, image, developer and utility tools, plus <strong>Cardly</strong>, a free digital business card.</li>
      <li><strong>Product Architect, Genuin</strong> (<a href="https://begenuin.com" rel="me noopener">begenuin.com</a>): designing product experiences, user flows and technical architecture.</li>
    </ul>

    <h2>Podcasts</h2>
    <p>Shushant hosts two podcasts:</p>
    <ul>
      <li><strong><a href="https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG" rel="me noopener">Nepal Travel Podcast</a></strong>: travel, places and stories from across Nepal.</li>
      <li><strong><a href="https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk" rel="me noopener">Mind Atlas</a></strong>: ideas, the mind and meaningful conversations.</li>
    </ul>

    <h2>Music</h2>
    <p>As a singer and recording artist, Shushant releases original music on <a href="https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz" rel="me noopener">Spotify</a> and <a href="https://music.apple.com/us/artist/shushant-singh/6788654900" rel="me noopener">Apple Music</a>.</p>

    <h2>Education</h2>
    <p>Shushant holds a degree from <strong><a href="https://www.charusat.ac.in" rel="noopener">Charotar University of Science and Technology (CHARUSAT)</a></strong>.</p>

    <h2>Areas of focus</h2>
    <p>Product architecture &amp; design · Software engineering · SaaS · Artificial intelligence · Android &amp; web development · SEO &amp; generative-engine optimization · Digital tools &amp; automation · Podcasting &amp; storytelling · Music · Entrepreneurship.</p>

    <h2>Frequently asked questions</h2>
    <h3>Who is Shushant Singh?</h3>
    <p><?= e($bio) ?></p>
    <h3>What companies and products is Shushant Singh behind?</h3>
    <p>He founded and owns BriefNepal (briefnepal.com) and Toolzy (apps.briefnepal.com), built the Cardly digital business card, and is a Product Architect at Genuin.</p>
    <h3>What does a Product Architect do?</h3>
    <p>At Genuin, Shushant designs product experiences, user flows and the technical architecture behind digital products, bridging design, engineering and business needs.</p>
    <h3>What podcasts does Shushant Singh host?</h3>
    <p>He hosts the Nepal Travel Podcast (travel and stories from Nepal) and Mind Atlas (ideas and the mind), both available on Spotify.</p>
    <h3>Is Shushant Singh a musician?</h3>
    <p>Yes, he is a singer and recording artist with music on Spotify and Apple Music.</p>
    <h3>Where is Shushant Singh based?</h3>
    <p>He is Nepali and is currently based in Ahmedabad, India, where he works at Genuin.</p>
    <h3>Where did Shushant Singh study?</h3>
    <p>He holds a degree from Charotar University of Science and Technology (CHARUSAT).</p>
  </div>

  <div class="widget mt-8" style="border-radius:var(--radius-lg)">
    <h3 class="widget__title">Find Shushant Singh online</h3>
    <div class="widget__list">
      <?php foreach ($profiles as $p): ?>
        <a href="<?= eattr($p['url']) ?>" rel="me noopener" target="_blank">
          <span class="dot" style="background:var(--accent)"></span>
          <strong><?= e($p['label']) ?></strong>&nbsp;<span class="muted">- <?= e($p['note']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
