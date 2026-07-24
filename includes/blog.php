<?php
/**
 * Blog content store.
 *
 * Articles live here as structured data so the blog works instantly on any
 * host (no DB required). When the MySQL `blogs` table has rows, those take
 * precedence and are merged in by get_posts().
 *
 * @package Toolzy
 */
declare(strict_types=1);

/** @return array<int,array<string,mixed>> */
function omnitools_static_posts(): array
{
    return [
        [
            'slug' => 'how-to-compress-images-without-losing-quality',
            'title' => 'How to Compress Images Without Losing Quality',
            'tag' => 'Image',
            'icon' => 'image',
            'date' => '2026-06-28',
            'author' => 'Toolzy Team',
            'excerpt' => 'Learn the difference between lossy and lossless compression and how to shrink images by up to 80% while keeping them crisp.',
            'related' => 'compress-image',
            'body' => "## Why image size matters\n\nLarge images are the number one cause of slow web pages. Every kilobyte you save improves load time, Core Web Vitals and SEO.\n\n## Lossy vs lossless\n\n**Lossy** compression (JPG, WebP) discards data the human eye barely notices, perfect for photos. **Lossless** compression (PNG) keeps every pixel but compresses the file structure, ideal for logos and screenshots.\n\n## Practical steps\n\n1. Choose the right format: photos → JPG/WebP, graphics → PNG.\n2. Resize to the actual display dimensions before compressing.\n3. Aim for 60–80% quality, the sweet spot for JPG.\n4. Convert to WebP for ~30% smaller files than JPG.\n\n> Use the **Compress Image** tool, everything happens in your browser, so your photos never leave your device.\n\n## The bottom line\n\nCompression is free performance. Combine resizing, the right format and 70% quality and most images shrink dramatically with no visible loss.",
        ],
        [
            'slug' => 'png-vs-jpg-which-format-should-you-use',
            'title' => 'PNG vs JPG: Which Format Should You Use?',
            'tag' => 'Image',
            'icon' => 'image',
            'date' => '2026-06-20',
            'author' => 'Toolzy Team',
            'excerpt' => 'A clear, practical guide to choosing between PNG and JPG for the web, print and everything in between.',
            'related' => 'image-converter',
            'body' => "## The short answer\n\n- **Photographs** → JPG (or WebP)\n- **Logos, icons, screenshots, transparency** → PNG\n\n## Why\n\nJPG uses lossy compression tuned for continuous-tone images like photos. PNG is lossless and supports transparency, making it perfect for graphics with sharp edges and flat colour.\n\n## File size\n\nFor a typical photo, JPG can be 5–10× smaller than PNG. For a flat logo, PNG is often smaller *and* sharper.\n\n## When in doubt\n\nTry WebP, it supports both lossy and lossless modes plus transparency, and is now supported everywhere. Convert instantly with our **Image Converter**.",
        ],
        [
            'slug' => 'what-is-json-a-beginners-guide',
            'title' => 'What is JSON? A Beginner\'s Guide',
            'tag' => 'Developer',
            'icon' => 'code',
            'date' => '2026-06-12',
            'author' => 'Toolzy Team',
            'excerpt' => 'JSON is the language of modern APIs. Here is what it is, why it matters, and how to read and validate it.',
            'related' => 'json-formatter',
            'body' => "## JSON in one sentence\n\nJSON (JavaScript Object Notation) is a lightweight, human-readable format for storing and exchanging data.\n\n## The building blocks\n\n- **Objects** `{ }` hold key–value pairs\n- **Arrays** `[ ]` hold ordered lists\n- **Values** can be strings, numbers, booleans, null, objects or arrays\n\n```\n{\n  \"name\": \"Toolzy\",\n  \"tools\": 100,\n  \"free\": true\n}\n```\n\n## Common mistakes\n\n- Trailing commas are not allowed\n- Keys must use double quotes\n- No comments permitted\n\nPaste your JSON into the **JSON Formatter** to beautify it and catch errors instantly.",
        ],
        [
            'slug' => 'what-is-base64-encoding',
            'title' => 'What is Base64 Encoding (and When to Use It)',
            'tag' => 'Developer',
            'icon' => 'code',
            'date' => '2026-06-05',
            'author' => 'Toolzy Team',
            'excerpt' => 'Base64 turns binary data into text. Learn how it works and where it is genuinely useful.',
            'related' => 'base64-encode',
            'body' => "## What it does\n\nBase64 encodes binary data (like images) into a set of 64 safe ASCII characters, so it can travel through systems built for text, email, JSON, data URIs.\n\n## It is not encryption\n\nBase64 is fully reversible and offers **zero** security. Never use it to hide passwords.\n\n## Good use cases\n\n- Embedding small images directly in CSS/HTML with data URIs\n- Encoding binary in JSON payloads\n- Basic auth headers\n\n## The trade-off\n\nBase64 increases size by ~33%. Use it only for small assets. Try our **Base64 Encode/Decode** tools to experiment.",
        ],
        [
            'slug' => 'best-free-pdf-tools-online',
            'title' => 'The Best Free PDF Tools Online in 2026',
            'tag' => 'PDF',
            'icon' => 'file',
            'date' => '2026-05-28',
            'author' => 'Toolzy Team',
            'excerpt' => 'Merge, split, compress and convert PDFs without installing software or paying a cent.',
            'related' => 'merge-pdf',
            'body' => "## Everything you need for PDFs\n\nModern browsers and lightweight servers can handle almost every PDF task for free.\n\n## The essentials\n\n- **Merge PDF**, combine reports, invoices and scans\n- **Split PDF**, extract only the pages you need\n- **Compress PDF**, shrink big files for email\n- **JPG to PDF**, turn photos into a document\n\n## Privacy first\n\nWhere possible, choose tools that process files locally. Our image-to-PDF and text-to-PDF tools run entirely in your browser.",
        ],
        [
            'slug' => 'benefits-of-qr-codes-for-business',
            'title' => 'The Benefits of QR Codes for Your Business',
            'tag' => 'Utilities',
            'icon' => 'grid',
            'date' => '2026-05-18',
            'author' => 'Toolzy Team',
            'excerpt' => 'QR codes bridge print and digital. Here is how to use them well, and generate them for free.',
            'related' => 'qr-code-generator',
            'body' => "## Why QR codes are back\n\nEvery smartphone camera now scans QR codes natively, making them a frictionless bridge from the physical world to your website, menu or payment page.\n\n## Great uses\n\n- Restaurant menus and Wi-Fi passwords\n- Product packaging linking to guides\n- Business cards and event check-ins\n\n## Best practices\n\n1. Use high error correction for printed codes\n2. Keep a generous quiet zone (margin)\n3. Test on multiple phones before printing\n\nGenerate unlimited codes with our free **QR Code Generator**.",
        ],
        [
            'slug' => 'developer-productivity-tips',
            'title' => '10 Developer Productivity Tips Using Free Web Tools',
            'tag' => 'Developer',
            'icon' => 'code',
            'date' => '2026-05-08',
            'author' => 'Toolzy Team',
            'excerpt' => 'Small utilities save big time. Here are ten browser tools every developer should bookmark.',
            'related' => 'jwt-decoder',
            'body' => "## Work smarter, not harder\n\n1. **Format JSON** before debugging APIs\n2. **Decode JWTs** to inspect auth tokens\n3. **Test regex** with live highlighting\n4. **Generate UUIDs** in bulk\n5. **Hash strings** to verify integrity\n6. **Encode/decode Base64** for data URIs\n7. **Minify CSS/JS** before shipping\n8. **Convert timestamps** without maths\n9. **Format SQL** for readable queries\n10. **Pick colours** and copy HEX/RGB instantly\n\nEvery one of these lives in Toolzy, no installs, no logins.",
        ],
    ];
}

/** Merge DB posts (if any) with static posts, DB first. */
function get_posts(): array
{
    $posts = [];
    $db = Database::getInstance();
    if ($db->isConnected()) {
        foreach ($db->select('SELECT * FROM blogs WHERE status = "published" ORDER BY created_at DESC') as $r) {
            $posts[$r['slug']] = [
                'slug' => $r['slug'], 'title' => $r['title'], 'tag' => $r['category'] ?? 'Blog',
                'icon' => 'doc', 'date' => substr((string)$r['created_at'], 0, 10), 'author' => $r['author'] ?? SITE_AUTHOR,
                'excerpt' => $r['excerpt'] ?? '', 'body' => $r['body'] ?? '', 'related' => $r['related_tool'] ?? '',
            ];
        }
    }
    foreach (omnitools_static_posts() as $p) {
        if (!isset($posts[$p['slug']])) {
            $posts[$p['slug']] = $p;
        }
    }
    return array_values($posts);
}

function get_post(string $slug): ?array
{
    foreach (get_posts() as $p) {
        if ($p['slug'] === $slug) {
            return $p;
        }
    }
    return null;
}
