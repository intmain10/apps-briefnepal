<?php
/**
 * Cardly — About page (served on the Cardly domain).
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

// Maker profile links (kept in sync with the founder page).
$makerProfiles = [
    ['label' => 'LinkedIn',             'url' => 'https://www.linkedin.com/in/shushant-kumar-singh/', 'note' => 'Professional profile'],
    ['label' => 'BriefNepal',           'url' => 'https://briefnepal.com',      'note' => 'News platform he founded'],
    ['label' => 'OmniTools',            'url' => 'https://apps.briefnepal.com', 'note' => '100+ free online tools he founded'],
    ['label' => 'Genuin',               'url' => 'https://begenuin.com',        'note' => 'Product Architect'],
    ['label' => 'Spotify, Artist',     'url' => 'https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz', 'note' => 'Singer / recording artist'],
    ['label' => 'Apple Music, Artist', 'url' => 'https://music.apple.com/us/artist/shushant-singh/6788654900', 'note' => 'Music profile'],
    ['label' => 'Nepal Travel Podcast', 'url' => 'https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG', 'note' => 'Podcast he hosts'],
    ['label' => 'Mind Atlas Podcast',   'url' => 'https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk', 'note' => 'Podcast he hosts'],
];

$page = [
    'title'       => 'About Cardly, Free Digital Business Card',
    'description' => 'Cardly is a free digital business card, one smart link for your contact, socials, portfolio and QR code. Built by Shushant Singh, part of OmniTools.',
    'canonical'   => cardly_link('about'),
    'is_cardly'   => true,
    'breadcrumb'  => [
        ['name' => 'Cardly', 'url' => cardly_link()],
        ['name' => 'About', 'url' => cardly_link('about')],
    ],
    'jsonld' => [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'AboutPage',
            'name'     => 'About Cardly',
            'url'      => cardly_link('about'),
            'mainEntity' => [
                '@type'           => 'WebApplication',
                'name'            => 'Cardly',
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Any',
                'url'             => cardly_link(),
                'description'     => 'Free digital business card builder, one shareable link with contact, socials, portfolio and QR code.',
                'offers'          => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'creator'         => [
                    '@type' => 'Person',
                    'name'  => 'Shushant Singh',
                    'url'   => 'https://apps.briefnepal.com/shushant-singh',
                ],
                'publisher'       => [
                    '@type' => 'Organization',
                    'name'  => 'OmniTools',
                    'url'   => 'https://apps.briefnepal.com',
                ],
            ],
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($qa) => [
                '@type' => 'Question', 'name' => $qa[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
            ], [
                ['What is Cardly?', 'Cardly is a free digital business card. You build one page with your name, contact details, social links, portfolio and a scannable QR code, and share it with a single link.'],
                ['Is Cardly free?', 'Yes, Cardly is completely free. There are no watermarks and no limits.'],
                ['Do I need an app?', 'No. Your Cardly card opens in any web browser, and the people you share it with need no app either.'],
                ['Who made Cardly?', 'Cardly was built by Shushant Singh, a product architect and founder of OmniTools and BriefNepal.'],
            ]),
        ],
    ],
];

require __DIR__ . '/includes/header.php';
?>
<section class="cardly-hero">
  <div class="container">
    <div class="cardly-brand">
      <img class="cardly-logo cardly-logo--light" src="<?= eattr(url('assets/images/cardly-wordmark-light.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="200" height="48">
      <img class="cardly-logo cardly-logo--dark" src="<?= eattr(url('assets/images/cardly-wordmark-dark.png?v=' . OMNITOOLS_VERSION)) ?>" alt="Cardly" width="200" height="48">
    </div>
    <h1>About <span class="grad">Cardly</span></h1>
    <p class="cardly-hero__sub">Your whole world, one smart link, a free digital business card you can share anywhere.</p>
  </div>
</section>

<div class="container" style="padding-bottom:56px">
  <div class="prose" style="max-width:760px;margin:0 auto">
    <h2>What is Cardly?</h2>
    <p><strong>Cardly</strong> is a free digital business card. Instead of paper cards that get lost or a dozen links scattered across your bio, Cardly gives you <strong>one smart link</strong> that holds everything, your name, role, contact details, social profiles, portfolio and a scannable QR code. Share it on Instagram, LinkedIn, X, WhatsApp, your résumé or email signature.</p>

    <h2>Why people use Cardly</h2>
    <ul>
      <li><strong>One link for everything</strong>: contact, socials, work and more in a single place.</li>
      <li><strong>Save to contacts (VCF)</strong>: one tap adds you to someone’s phone.</li>
      <li><strong>Dynamic QR code</strong>: show your card in person; instant scan.</li>
      <li><strong>Beautiful templates</strong>: pick a theme, make it yours.</li>
      <li><strong>Share as an Instagram Story</strong>: export a premium story image of your card.</li>
      <li><strong>Free &amp; private</strong>: no watermarks, no limits, and an unguessable link.</li>
    </ul>

    <h2>About the maker, Shushant Singh</h2>
    <p>Cardly was designed and built by <strong><a href="https://apps.briefnepal.com/shushant-singh" rel="noopener">Shushant Singh</a></strong> (Shushant Kumar Singh), a <strong>Nepali</strong> product architect, entrepreneur, podcaster and recording artist based in <strong>Ahmedabad, India</strong>. He builds digital products that make everyday life simpler, Cardly is part of the OmniTools family, built with the same focus on clean, honest, genuinely useful products. <a href="https://apps.briefnepal.com/shushant-singh" rel="noopener">Read his full profile →</a></p>

    <h3>Ventures &amp; work</h3>
    <ul>
      <li><strong>Founder &amp; Owner, BriefNepal</strong> (<a href="https://briefnepal.com" rel="noopener">briefnepal.com</a>): a modern Nepali news, jobs and travel platform.</li>
      <li><strong>Founder &amp; Owner, OmniTools</strong> (<a href="https://apps.briefnepal.com" rel="noopener">apps.briefnepal.com</a>): 100+ free, privacy-first PDF, image, developer and utility tools, plus Cardly.</li>
      <li><strong>Product Architect, Genuin</strong> (<a href="https://begenuin.com" rel="noopener">begenuin.com</a>): designing product experiences, user flows and technical architecture.</li>
    </ul>

    <h3>Podcasts</h3>
    <ul>
      <li><strong><a href="https://open.spotify.com/show/033Ka2C5wynL6xq41YAsbG" rel="noopener">Nepal Travel Podcast</a></strong>: travel, places and stories from across Nepal.</li>
      <li><strong><a href="https://open.spotify.com/show/033K9Ye7NX5mRELkuCt7Nk" rel="noopener">Mind Atlas</a></strong>: ideas, the mind and meaningful conversations.</li>
    </ul>

    <h3>Music</h3>
    <p>As a singer and recording artist, Shushant releases original music on <a href="https://open.spotify.com/artist/5b03eorWX5RxJqXCsTUgFz" rel="noopener">Spotify</a> and <a href="https://music.apple.com/us/artist/shushant-singh/6788654900" rel="noopener">Apple Music</a>.</p>

    <h3>Education</h3>
    <p>Shushant holds a degree from <a href="https://www.charusat.ac.in" rel="noopener">Charotar University of Science and Technology (CHARUSAT)</a>.</p>

    <h3>Areas of focus</h3>
    <p>Product architecture &amp; design · Software engineering · SaaS · Artificial intelligence · Android &amp; web development · SEO &amp; generative-engine optimization · Digital tools &amp; automation · Podcasting &amp; storytelling · Music · Entrepreneurship.</p>

    <h3>Find Shushant Singh online</h3>
    <ul>
      <?php foreach ($makerProfiles as $mp): ?>
        <li><a href="<?= eattr($mp['url']) ?>" rel="noopener" target="_blank"><strong><?= e($mp['label']) ?></strong></a>, <?= e($mp['note']) ?></li>
      <?php endforeach; ?>
    </ul>

    <h2>Your privacy</h2>
    <p>Your card is yours. Links are unguessable, images are stored securely, and there are no ads or trackers selling your data. You’re always in control of what your card shows.</p>

    <h2>Frequently asked questions</h2>
    <h3>Is Cardly free?</h3>
    <p>Yes, completely free, with no watermarks and no limits.</p>
    <h3>Do I (or the people I share with) need an app?</h3>
    <p>No. Your card opens in any web browser, no app required for you or your contacts.</p>
    <h3>Can I edit my card later?</h3>
    <p>Yes. You can update your card any time, and your link stays the same.</p>
  </div>

  <div class="text-center mt-8">
    <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px">Create your free card</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
