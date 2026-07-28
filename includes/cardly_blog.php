<?php
/**
 * Cardly blog content store.
 *
 * Articles live here as structured data (same approach as includes/blog.php)
 * so the Cardly blog needs no database and no build step. Bodies are Markdown,
 * rendered by md_to_html() in includes/markdown.php.
 *
 * Keep this store Cardly-only — the Toolzy blog is separate and lives on
 * apps.briefnepal.com/blog.
 *
 * @package Toolzy\Cardly
 */
declare(strict_types=1);

/** @return array<int,array<string,mixed>> Newest first. */
function cardly_posts(): array
{
    return [
        [
            'slug'     => 'why-i-built-cardly-digital-business-card',
            'title'    => 'One Link Instead of Ten: Why I Built Cardly',
            'tag'      => 'Founder Story',
            'date'     => '2026-07-28',
            'author'   => 'Shushant Kumar Singh',
            // Cover = a real card's generated share image, so the article shows
            // an actual Cardly card (and follows it when the card is edited).
            // 'cover' is the fallback if that card or GD is unavailable.
            'cover_card' => 'shushant-singh-twt6',
            'cover'    => 'assets/images/cardly-hero-card.jpg',
            'excerpt'  => 'I lost a good conversation because I was busy typing my GitHub handle into a stranger’s notes app. This is the story of the problem behind Cardly, how it is built, and where AI fits next.',
            'body'     => <<<'MD'
## The 40 seconds that started this

I was at a developer meetup, standing in front of someone who wanted to see my work. Actual work, not a summary of it.

So I started typing. My GitHub handle into their notes app. Then my LinkedIn, because that is where the professional history lives. Then my portfolio URL, which I had to spell out twice. They asked for my resume; I said I would email it. They asked for my number; I said I would message them. By the time we finished, the conversation was over. The curiosity that started it had drained out through the copy-paste.

That is roughly 40 seconds of friction, and it happens millions of times a day. Nobody complains about it, because nobody sees it as a problem. It just feels like the cost of meeting people.

I went home and built the first version of Cardly that week.

> **The core idea:** your identity online is not one profile, it is fifteen. Cardly is one page that points at all of them, and one QR code that gets someone there in two seconds.

**Cardly** is a free digital business card. You build one page, your name, role, contact details, social links, portfolio and resume, and share it with a single link or a scannable QR code. The person on the other end can save your contact to their phone in one tap. No app to download. No account needed to view it. No paper.

## Why I built Cardly

The unglamorous reason: I was tired of being badly represented by my own links.

I do a lot of different things. I am a product architect at Genuin. I founded BriefNepal, a news platform. I built Toolzy, a set of 100+ free browser tools. I also release music and host two podcasts. On paper that sounds like range. In a networking conversation it sounds like a mess, because there is no single platform where all of it fits. LinkedIn wants my job title. GitHub wants my code. Spotify wants my music. Instagram wants a version of me I do not entirely recognise.

So every introduction became an editing exercise: which three links do I share with *this* person, and which parts of myself do I leave out?

The second reason is that I watched other people lose more than I did. Students applying for their first internship with a decent portfolio sitting behind a Drive link nobody clicks. Freelancers sending clients a wall of URLs in a chat message. A friend who printed 200 business cards, changed jobs six weeks later, and threw away 190 of them.

Tools for professional identity are built for people with a settled, single-track career. Most of us do not have one of those.

## The networking problem nobody names

Break it down and it is really four problems wearing one coat.

**Fragmentation.** Your credibility is spread across platforms that do not talk to each other. No single link shows a complete picture of you.

**Decay.** A paper business card is frozen at the moment of printing. Job title changes, phone changes, company changes, and the card starts lying on your behalf with no way for you to correct it. The same is true of a PDF resume sitting in someone’s downloads folder.

**Friction at the moment that matters.** The handoff happens in a hallway, at a booth, in a queue, between sessions. You have maybe ten seconds of shared attention, and typing anything is too slow.

**The follow-up gap.** Most exchanged contacts never turn into anything, because “I will send you my portfolio later” almost always means never. Whatever is not shared in the moment is usually lost.

> **What I kept coming back to:** the bottleneck is not information. Everyone already has plenty of that online. The bottleneck is transfer, getting a complete picture of you into someone else’s hands before the conversation ends.

## What already exists, and where it falls short

I did not start building because nothing existed. I started because everything that existed made a trade-off I was not willing to make.

**Paper business cards** still win on ritual, the handoff is physical and memorable. They lose on everything else: no portfolio, nothing clickable, no updates, and an environmental cost that is hard to justify in 2026.

**NFC smart cards** are genuinely clever, but they need hardware. That means cost, shipping and a supply chain. For a student in Pokhara or a freelancer in Manila, a thirty-dollar metal card is not a starting point.

**Link-in-bio tools** solved link fragmentation for creators, and solved it well. But they are built for audiences, not introductions. They optimise for followers and clicks rather than for “save this person’s number.” Most do not do vCard at all, and most look like a landing page instead of a person.

**LinkedIn** is the default, and it is a real answer, if your professional identity fits inside LinkedIn’s shape. Mine does not. Neither does most developers’, designers’ or creators’. And “connect with me on LinkedIn” still means they have to search for you, pick the right account, and send a request you might approve three days later.

The gap was specific: something as fast as a paper handoff, as complete as a portfolio site, as cheap as free, and updatable forever.

## Introducing Cardly

Cardly is one page that is you, at a link you control.

You fill in your name, what you do, and a short bio. You add whatever links matter, LinkedIn, GitHub, portfolio, resume, Instagram, Spotify, email, phone. You pick a theme. You get a link and a QR code.

Three decisions shaped the product more than any feature did.

**No account required to view a card.** The person receiving your card is not my user and should not have to become one. They scan, they see, they save, they leave. Any friction I put on the receiver kills the entire point.

**Free, with no watermark.** A digital business card carrying someone else’s branding is not a business card, it is an advert you are paying for with your own reputation.

**Save Contact has to work in one tap.** This is what makes Cardly better than paper rather than just more modern than paper. Tap Save Contact, and your name, role, phone, email and links land in the other person’s address book as a proper contact, with your card’s URL attached so it stays alive after the conversation.

> **The test I use for every feature:** does this help a stranger understand and keep you within ten seconds? If not, it does not ship.

## Core features

- **Digital business card**, one page with your identity, contact details and links.
- **QR code sharing**, every card generates a scannable code for phone screens, laptop stickers, slides and event booths.
- **One-tap Save Contact (vCard)**, your details go into their phone’s contacts, not their notes app.
- **A clean, shareable link**, readable enough to say out loud.
- **Portfolio showcase**, your actual work above your job title, where it belongs.
- **Resume link**, always the current version, never a stale attachment.
- **Social links**, every platform you are on, in one place, in the order you choose.
- **Curated templates**, professional-looking by default, because most people do not want to design anything.
- **Instagram Story export**, a premium story image of your card, generated for you.
- **Mobile-first UI**, cards get scanned on phones, so phones are the primary target rather than an afterthought.

## Technical architecture

Cardly runs on a deliberately unfashionable stack, and that was a decision rather than a compromise: **PHP 8 and vanilla JavaScript, with no framework and no build step.**

The cost of a stack is not day one, it is month eighteen. Cardly card pages are rendered server-side as plain HTML. There is no bundler to break, no dependency tree to audit, no CI step between a fix and production. A card opens almost instantly on a mid-range Android phone over 4G in South Asia, which is the condition I actually optimise for, not a laptop on office fibre.

**Storage is two layers.** MySQL is the source of truth when a database is configured: one row per card, an indexed metadata head plus a JSON blob for the card contents. When no database is available, the engine transparently falls back to one JSON file per card on disk, and database writes are mirrored to those files as a hot backup. That means the app never white-screens because of a database problem, and I could ship the entire product before provisioning MySQL.

**Editing without accounts.** Cardly originally had no login at all. When you create a card you get a secret edit link, backed by a hashed token stored server-side, the same model as an unlisted document. Accounts arrived later as an optional upgrade rather than a gate. The philosophy stuck: never make someone sign up before they have seen the value.

**Links.** A card link keeps your name visible but appends a short random suffix, so it looks like `shushant-a4f9` rather than `shushant`. That solves two problems at once, nobody has to fight over common names, and nobody can enumerate cards by guessing. The random alphabet deliberately excludes characters that look alike, so a link survives being read aloud. System words are reserved, so a card can never shadow a real page.

**Templates** are a curated set of gradient themes rather than a design tool. Constraint as a feature: every card looks professional because you cannot make it not.

**QR codes** are generated from the canonical card URL, so a printed or stickered code never goes stale. I can change what the page contains without invalidating a code already out in the world.

**Cloudflare** sits in front for TLS, caching and DDoS protection. Uploaded media is served from disk under a per-card path, and social preview images are generated per card so a shared link looks like a card rather than a blank rectangle.

> **A constraint that shaped everything:** the editor can be heavy. The card must not be. Anything that slows down a stranger’s first three seconds does not ship.

## Real-world use cases

The thing about building an identity tool is that people use it in ways you did not plan for.

**Students and job seekers** put a QR code on the last slide of a presentation and in their email signature, so recruiters get portfolio, resume and GitHub without a single attachment.

**Developers** replace the six-link block in their README and conference slides with one Cardly link.

**Freelancers and consultants** send Cardly instead of a “here is my stuff” paragraph, and update the portfolio behind it without re-sending anything.

**Founders** hand it out at demo days, where they meet fifty people in three hours and every handoff has to survive until the follow-up email.

**Creators, musicians and podcasters**, which is my own case. Music, podcasts, socials and press links in one place, without pretending to be only a musician or only a builder.

**Event organisers and teams** put cards on badges and booth signage, so a scan replaces a form.

## How AI fits into the future

Here is the honest state of things: the hardest part of Cardly is not the technology. It is the blank text box.

I have watched people build a card and stall on the bio. They type “Software developer” and stop, because writing about yourself is genuinely difficult, and it is harder still in a second or third language. A very large number of capable people are underrepresented by their own two-line bios.

That is where AI belongs in Cardly. Not as a feature list, but as the thing that closes the gap between what someone has done and what their profile says they have done.

- **AI-generated professional bios**, three drafts in different registers from a role and a few links, for the person to choose and edit.
- **AI-powered profile completion**, looking at a half-finished card and naming what is missing: no portfolio link, no role, a resume six months stale.
- **Resume optimisation**, plain feedback against the role someone is actually targeting.
- **Portfolio suggestions**, which three projects to feature, and in what order, for the audience they care about.
- **Multilingual profile translation**, one card viewable in Nepali, Hindi, Japanese, Bahasa or English. This matters enormously across Asia, where the person scanning your card often does not share your first language.
- **AI-generated profile summaries**, a two-line version of you for the recipient’s context.
- **Personalised networking recommendations**, surfacing the people at an event whose work genuinely overlaps with yours.
- **A smart introduction assistant**, helping draft the follow-up message that turns a scanned card into a real conversation.

> **My rule for AI in Cardly:** it drafts, the human decides. An identity platform that invents your credentials is worse than useless, it is dangerous. Every AI output is a suggestion in an editable field, never a published claim.

## Lessons learned while building

**Fewer features, better handoff.** My first version had more options and worse outcomes. Everything I removed made the card easier to hand to a stranger.

**The receiver is the real user.** I designed for card owners first and got it wrong. The person scanning has the least patience, the least context and the most power over whether any of this works.

**Small friction hides inside working features.** Tapping Save Contact used to download a `.vcf` file directly. On desktop and some Android browsers that produced a mystery file in the downloads folder and no saved contact. The feature technically worked and practically failed. I replaced it with a preview page that shows exactly what is about to be saved, and then saves it. A boring fix with a measurable difference, and the lesson generalises: watch a real handoff before trusting a green checkmark.

**Defaults are the product.** Most people never open the template picker. The default card *is* Cardly for the majority of users, so that is where the design effort goes.

**Trust is engineering, not a policy page.** Unguessable links, no login wall for viewers, no watermark, no data resale. People are putting their phone number on this, and that deserves code rather than reassurance.

**Ship while it is still slightly embarrassing.** Cardly went live before I was comfortable with it, and every meaningful improvement since came from watching someone use it badly.

## The roadmap

**Near term**, analytics for card owners (scans, taps, top links), richer portfolio blocks with images and case studies, custom domains, and a small set of team features so a company can issue consistent cards.

**Mid term**, the AI layer above, starting with bio generation and profile completion, with multilingual cards close behind. Plus deeper vCard fidelity, wallet passes, and an event mode for conferences and job fairs.

**Long term**, Cardly as a professional identity layer rather than a card app. One canonical, portable, always-current representation of a person, that they own, that works across languages and platforms, and that an AI can help them present well without ever speaking for them.

## Closing thoughts

I still think about that meetup. Not because the technology was hard, a page with links is not hard, but because of what was actually lost in those 40 seconds. Somebody was curious about my work and I could not get them to it in time.

Talent is distributed evenly. The ability to present it is not. A student in Nepal, a designer in Jakarta, a developer in Manila can be exactly as good as anyone in San Francisco and still lose the room, because the portfolio was a Drive link and the bio was an empty box.

That is the problem I am actually working on. Cardly is a digital business card, yes. But the real ambition is smaller and larger at the same time: make it take two seconds for someone to see the full, current, accurate version of who you are, and make that true for people who do not have a personal brand consultant.

One link. One scan. The whole person.
MD,
        ],
    ];
}

/**
 * Cover image URL for a post, or null if it has none.
 *
 * A post may point at a live card via 'cover_card': that card's generated
 * 1200×630 share image is used, so the article always shows the current version
 * of a real card rather than a stale export. 'cover' is a static fallback for
 * when the card is gone or GD/FreeType is unavailable.
 */
function cardly_post_cover(array $post): ?string
{
    $cardSlug = (string)($post['cover_card'] ?? '');
    if ($cardSlug !== '' && function_exists('cardly_load')) {
        $card = cardly_load($cardSlug);
        if ($card) {
            $og = cardly_og_ensure($cardSlug, $card);
            if ($og) {
                return $og;
            }
        }
    }
    return !empty($post['cover']) ? url($post['cover'] . '?v=' . OMNITOOLS_VERSION) : null;
}

/** @return array<string,mixed>|null */
function cardly_post(string $slug): ?array
{
    foreach (cardly_posts() as $p) {
        if ($p['slug'] === $slug) {
            return $p;
        }
    }
    return null;
}
