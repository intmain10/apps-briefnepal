<?php
/**
 * Cardly — About page (served on the Cardly domain).
 * @package OmniTools\Cardly
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/cardly.php';

$page = [
    'title'       => 'About Cardly — Free Digital Business Card',
    'description' => 'Cardly is a free digital business card — one smart link for your contact, socials, portfolio and QR code. Built by Shushant Singh, part of OmniTools.',
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
                'description'     => 'Free digital business card builder — one shareable link with contact, socials, portfolio and QR code.',
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
                ['Is Cardly free?', 'Yes — Cardly is completely free. There are no watermarks and no limits.'],
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
    <p class="cardly-hero__sub">Your whole world, one smart link — a free digital business card you can share anywhere.</p>
  </div>
</section>

<div class="container" style="padding-bottom:56px">
  <div class="prose" style="max-width:760px;margin:0 auto">
    <h2>What is Cardly?</h2>
    <p><strong>Cardly</strong> is a free digital business card. Instead of paper cards that get lost or a dozen links scattered across your bio, Cardly gives you <strong>one smart link</strong> that holds everything — your name, role, contact details, social profiles, portfolio and a scannable QR code. Share it on Instagram, LinkedIn, X, WhatsApp, your résumé or email signature.</p>

    <h2>Why people use Cardly</h2>
    <ul>
      <li><strong>One link for everything</strong> — contact, socials, work and more in a single place.</li>
      <li><strong>Save to contacts (VCF)</strong> — one tap adds you to someone’s phone.</li>
      <li><strong>Dynamic QR code</strong> — show your card in person; instant scan.</li>
      <li><strong>Beautiful templates</strong> — pick a theme, make it yours.</li>
      <li><strong>Share as an Instagram Story</strong> — export a premium story image of your card.</li>
      <li><strong>Free &amp; private</strong> — no watermarks, no limits, and an unguessable link.</li>
    </ul>

    <h2>Who made Cardly</h2>
    <p>Cardly was designed and built by <strong><a href="https://apps.briefnepal.com/shushant-singh" rel="noopener">Shushant Singh</a></strong>, a product architect and the founder of <a href="https://apps.briefnepal.com" rel="noopener">OmniTools</a> (100+ free online tools) and <a href="https://briefnepal.com" rel="noopener">BriefNepal</a>. Cardly is part of the OmniTools family — built with the same focus on clean, honest, genuinely useful products.</p>

    <h2>Your privacy</h2>
    <p>Your card is yours. Links are unguessable, images are stored securely, and there are no ads or trackers selling your data. You’re always in control of what your card shows.</p>

    <h2>Frequently asked questions</h2>
    <h3>Is Cardly free?</h3>
    <p>Yes — completely free, with no watermarks and no limits.</p>
    <h3>Do I (or the people I share with) need an app?</h3>
    <p>No. Your card opens in any web browser — no app required for you or your contacts.</p>
    <h3>Can I edit my card later?</h3>
    <p>Yes. You can update your card any time, and your link stays the same.</p>
  </div>

  <div class="text-center mt-8">
    <a class="btn btn--primary" href="<?= eattr(cardly_link('new')) ?>" style="font-size:17px;padding:14px 28px">Create your free card</a>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
