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
            'slug' => 'how-to-redact-a-pdf-properly',
            'title' => 'How to Redact a PDF Properly (and Why Black Boxes Usually Fail)',
            'tag' => 'PDF',
            'icon' => 'file',
            'date' => '2026-07-27',
            'author' => 'Toolzy Team',
            'excerpt' => 'Drawing a black rectangle over text in a PDF hides nothing, the words underneath are still there and can be copied straight out. Here is how redaction actually works, what US and UK rules require, and how to remove the content for good.',
            'related' => 'redact-pdf',
            'body' => "Every few months another organisation discovers that the document it published was not as redacted as it thought. The pattern is almost always identical: someone opened the PDF, drew black rectangles over the sensitive parts, saved it, and sent it out. The document looked perfect. The text underneath was still sitting in the file, and anyone who selected the page and pressed copy could read it.\n\nThis is not a rare failure or an exotic attack. It is the default outcome of the most obvious way to redact a document, and it catches careful people in law firms, government departments and finance teams. If you are about to send a contract, a statement, a case file or a records request response to someone outside your organisation, it is worth ten minutes to understand why.\n\n## Why the obvious method does not work\n\nA PDF is not a picture of a page. It is a set of instructions describing what to draw: this text, in this font, at these coordinates; this image, scaled to this box. When you draw a black rectangle in most PDF editors, you are not deleting anything. You are adding one more instruction to the list, a filled black shape, positioned on top of the text.\n\nThe text instruction is still in the file, unchanged, underneath. Your PDF reader faithfully draws the text and then draws the black box over it, and your eyes see a redaction. But the words are still there in the document structure, and every one of these will bring them straight back:\n\n- Selecting the area and copying it\n- Running any text-extraction tool over the file\n- Opening the PDF in a different reader that renders layers differently\n- Simply moving or deleting the black shape in an editor\n\nThe redaction was never a redaction. It was a sticker.\n\n## What redaction actually has to mean\n\nProper redaction has one requirement, and it is absolute: **the underlying content must no longer exist in the file you send.** Not hidden, not covered, not moved off the visible page area. Gone.\n\nThere are two reliable ways to achieve that. The first is a dedicated redaction feature that deletes the text objects themselves and then draws the box, which professional PDF software offers and which requires care to apply correctly. The second is to flatten the page to an image and paint the boxes onto that image, so the only thing left in the file is a picture with black areas where the content used to be. There is no text layer left to leak, because there is no text layer at all.\n\nThe tool further down this page takes the second approach, and does so entirely inside your browser, which matters when the document is exactly the kind you should not be uploading to an unfamiliar website.\n\n## The four ways people leak data they thought they removed\n\n### 1. Black boxes over live text\n\nCovered above, and by far the most common. If your process was draw a rectangle, save, send, assume the content is readable.\n\n### 2. Cropping instead of deleting\n\nCropping a page changes the visible boundary. It does not remove what falls outside it. A cropped PDF still carries the full page content, and resetting the crop box restores everything. Cropping is a layout tool, not a security control.\n\n### 3. Metadata, comments and attachments\n\nThe visible page is only part of a PDF. Document properties can carry the author name, the originating file path and the software used. Review comments, tracked changes carried over from a Word export, form field values and embedded file attachments all live outside the page content you are looking at. A redaction that only addresses the visible text leaves all of it in place.\n\n### 4. Sending the wrong file\n\nMundane and constant. The redacted version is saved alongside the original with a similar name, and the original gets attached. Put redacted copies in a separate folder and name them unmistakably before you go anywhere near an email client.\n\n## What US and UK rules actually require\n\nRedaction is not only good practice, it is frequently a legal obligation, and the specifics differ by jurisdiction.\n\n**United States.** In federal court filings, Rule 5.2 of the Federal Rules of Civil Procedure requires personal identifiers to be redacted before a document is filed. In broad terms that means Social Security and taxpayer identification numbers reduced to the last four digits, a minor's name reduced to initials, dates of birth reduced to the year, and financial account numbers reduced to the last four digits. Individual districts add their own local rules on top, and the obligation generally sits with the filing party rather than the court.\n\n**United Kingdom.** Under UK GDPR and the Data Protection Act 2018, disclosing more personal data than necessary is a problem in itself. It comes up constantly when responding to a subject access request, where you must provide the requester's own data while removing information that identifies other people, and in Freedom of Information responses, where exempt material has to be removed before release. Data minimisation is a principle, not a formality, and an incomplete redaction is a personal data breach that may be reportable.\n\n> Whichever side of the Atlantic you are on, the standard is the same in practice: after redaction, the protected information must not be recoverable from the file. A black rectangle over live text does not meet that standard anywhere.\n\n## Before you redact: a short prep checklist\n\n1. **Work from a copy.** Redaction that genuinely removes content cannot be undone. Keep the unredacted original somewhere safe, because you will eventually need to prove what was removed.\n2. **Search the document first.** Use your reader's find function for the obvious patterns before you start marking anything, so you catch the account number that appears again on page 14.\n3. **Read the whole document, including the parts you think are boilerplate.** Personal data hides in signature blocks, letterheads, footers, appendix tables and email chains pasted into the body.\n4. **Decide what is actually required.** Over-redaction is its own failure, particularly in a subject access response, where removing the requester's own data defeats the point.\n5. **Check for a text layer.** If the document is a scan, the visible text may not be selectable at all, but it may still have been through OCR at some point. Assume there is a text layer until you have confirmed otherwise.\n\n## What to look for, by document type\n\nMost missed redactions are not subtle. They are the second occurrence of something you already removed once, sitting somewhere you did not think to read.\n\n### Contracts and agreements\n\nCommercial terms are usually the reason for redacting, but the personal data is elsewhere. Check signature blocks for home addresses and personal email accounts, schedules and annexes for named individuals and their rates, and any execution pages where a witness has given their address. Contracts assembled from earlier deals frequently retain the previous counterparty's name in a definition or a cross-reference.\n\n### Bank and financial statements\n\nAccount numbers, sort codes and routing numbers, full card numbers, and the account holder's address in the letterhead. Remember that individual transaction lines can be identifying on their own: a payee name, a standing order to a named person, or a reference field containing a case number. If you are disclosing statements to prove one specific payment, consider extracting only the relevant pages first rather than redacting a hundred lines.\n\n### Medical and HR records\n\nNames, dates of birth, patient and employee identifiers, and the names of third parties such as family members, colleagues who made complaints, or clinicians not relevant to the disclosure. Free-text notes are the highest-risk area, because identifying details appear in ordinary prose rather than in a labelled field, which means find-and-check will not catch them and you have to read.\n\n### Court exhibits and disclosure bundles\n\nApply the personal-identifier rules for your jurisdiction to every page, not only the pleading. Exhibits are where identifiers slip through, particularly scanned correspondence and screenshots. Where a bundle has been paginated, redact before numbering so the pagination on the disclosed version matches what the other side receives.\n\n### Documents that came from someone else\n\nIf you did not create the file, be more suspicious, not less. Anything exported from a template or a case management system may carry structured metadata describing matters and clients that has nothing to do with the visible page.\n\n## Redact your document now\n\nThe tool below runs entirely in your browser. Your file is never uploaded, which is the correct property for a document sensitive enough to need redacting in the first place. Drag a box over each area to remove, page by page, then apply.\n\n[tool:redact-pdf]\n\nWhen you apply the redaction, every page is flattened to an image and your black boxes are painted onto it. The output is a new document containing page images only, so the original text objects, and the metadata attached to them, do not travel with it.\n\n## Verify it worked, every time\n\nDo not skip this. Verification takes thirty seconds and is the difference between believing a document is safe and knowing it.\n\n1. **Open the redacted file** in a normal PDF reader.\n2. **Try to select the redacted area.** Drag across a black box. Nothing should highlight, because there is no text there to select.\n3. **Search for a term you removed.** Use find and type a name or number that was redacted. It must return no results.\n4. **Copy a whole page and paste it** into a plain text editor. Anything that was genuinely removed cannot appear, and this catches content you missed elsewhere on the page.\n5. **Check the document properties** for author, title and subject fields.\n\nIf any redacted content survives those five checks, do not send the file.\n\n## What this method costs you\n\nHonesty about the trade-off matters, because there are cases where this is the wrong approach.\n\nFlattening pages to images removes the text layer from the **entire document**, not only the redacted parts. The result is no longer searchable or copy-pasteable, and it will be larger than the original because photographs of pages compress less efficiently than text. Screen readers cannot read it, which is a real accessibility problem if the document is going to a public body or is intended for wide publication.\n\nIf the recipient requires a searchable document, the sequence is: redact first, then run OCR on the redacted file to build a fresh text layer from the flattened pages. That new layer is generated from what is visibly left, so it cannot reintroduce anything you removed. Do it in that order and never the reverse.\n\nFor a small number of pages inside a long report, consider extracting the affected pages, redacting only those, and merging them back so the rest of the document keeps its text layer.\n\n## Frequently asked questions\n\n**Can redacted content ever be recovered from a file redacted this way?**\nNot from the page content. Flattening replaces the text objects with an image, and the black areas are painted into that image, so there are no hidden characters left behind. This is why the method is more robust than drawing shapes over live text.\n\n**Is a black highlight in a word processor enough before exporting to PDF?**\nNo. Black highlighting changes appearance only, and the text exports into the PDF exactly as it was. Setting the font colour to white is worse still, since the text remains selectable and copies out perfectly.\n\n**Does this remove the document metadata?**\nThe output is built as a new document from page images, so the original page-level text objects and their properties do not carry over. Always check the document properties of the final file yourself before sending it, as part of the verification checklist above.\n\n**Do I need to redact a scanned document?**\nYes, if it contains information that must not be disclosed. A scan has no text layer to copy, which stops casual extraction, but the information is still legible to anyone looking at it, and that is disclosure. Redact it the same way.\n\n**Is redacting a document the same as marking it confidential?**\nNo. A confidentiality marking is a signal about how a document should be handled. Redaction removes content. If you need both, apply the redaction first and add a watermark to the redacted output.\n\n## Related tools and guides\n\n- **Redact PDF**, permanently remove content by flattening the page and painting over it\n- **OCR PDF**, restore a searchable text layer after redacting\n- **Split PDF**, pull out only the pages that need redacting\n- **Merge PDF**, put redacted pages back into the full document\n- **Watermark PDF**, mark the redacted copy so it is never confused with the original",
        ],
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
            'body' => "## Everything you need for PDFs\n\nModern browsers and lightweight servers can handle almost every PDF task for free.\n\n## The essentials\n\n- **Merge PDF**, combine reports, invoices and scans\n- **Split PDF**, extract only the pages you need\n- **Compress PDF**, shrink big files for email\n- **JPG to PDF**, turn photos into a document\n\n## Merge your PDFs without leaving this page\n\nThe merger below is the full tool, not a preview. Add your files, drag them into the order you want, and download the combined document. Nothing is uploaded.\n\n[tool:merge-pdf]\n\n## Privacy first\n\nWhere possible, choose tools that process files locally. Our image-to-PDF and text-to-PDF tools run entirely in your browser.",
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
