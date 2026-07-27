<?php
/**
 * Tool & Category Registry.
 *
 * The registry is the single source of truth for every tool on the platform.
 * Pages, search, sitemaps and JSON-LD all read from here. Adding a new tool
 * is as simple as adding one line here + one engine in assets/js/tools.js.
 *
 * This design scales cleanly to 1000+ tools without touching page templates.
 *
 * @package Toolzy
 */

declare(strict_types=1);

/**
 * All categories, keyed by slug.
 *
 * @return array<string,array<string,string>>
 */
function omnitools_categories(): array
{
    return [
        'pdf'         => ['name' => 'PDF',         'icon' => 'file',      'color' => '#ef4444', 'desc' => 'Merge, split, convert and optimise PDF documents in your browser.'],
        'image'       => ['name' => 'Image',       'icon' => 'image',     'color' => '#f59e0b', 'desc' => 'Compress, resize, crop and convert images privately, on device.'],
        'video'       => ['name' => 'Video',       'icon' => 'video',     'color' => '#8b5cf6', 'desc' => 'Inspect, capture and work with video files right in your browser.'],
        'audio'       => ['name' => 'Audio',       'icon' => 'audio',     'color' => '#ec4899', 'desc' => 'Cut, convert, normalise and boost audio without uploads.'],
        'text'        => ['name' => 'Text',        'icon' => 'text',      'color' => '#10b981', 'desc' => 'Count, sort, clean, transform and generate text instantly.'],
        'developer'   => ['name' => 'Developer',   'icon' => 'code',      'color' => '#3b82f6', 'desc' => 'Formatters, encoders, validators and generators for developers.'],
        'seo'         => ['name' => 'SEO',         'icon' => 'search',    'color' => '#06b6d4', 'desc' => 'Generate meta tags, schema, sitemaps and everything SEO.'],
        'finance'     => ['name' => 'Finance',     'icon' => 'money',     'color' => '#22c55e', 'desc' => 'Loan, EMI, tax, investment and percentage calculators.'],
        'utilities'   => ['name' => 'Utilities',   'icon' => 'grid',      'color' => '#6366f1', 'desc' => 'QR codes, passwords, colors and everyday utilities.'],
        'calculators' => ['name' => 'Calculators', 'icon' => 'calc',      'color' => '#14b8a6', 'desc' => 'Fast, accurate calculators for daily life and work.'],
        'documents'   => ['name' => 'Documents',   'icon' => 'doc',       'color' => '#0ea5e9', 'desc' => 'Convert between CSV, JSON, Markdown and other document formats.'],
        'ai'          => ['name' => 'AI',          'icon' => 'sparkles',  'color' => '#a855f7', 'desc' => 'Smart, on-device text intelligence: summarise, extract, analyse.'],
        'converters'  => ['name' => 'Converters',  'icon' => 'swap',      'color' => '#f43f5e', 'desc' => 'Convert units of length, weight, temperature, data and more.'],
    ];
}

/**
 * The full tool registry, keyed by slug.
 *
 * Each tool: name, category, desc, keywords, flags (popular|trending|new),
 * engine (JS engine key, defaults to slug), and optional intro/faqs.
 *
 * @return array<string,array<string,mixed>>
 */
function omnitools_tools(): array
{
    static $tools = null;
    if ($tools !== null) {
        return $tools;
    }

    $raw = [];

    /**
     * Local builder to keep each definition to a single readable line.
     */
    $t = function (string $slug, string $cat, string $name, string $desc, array $flags = [], string $kw = '', array $extra = []) use (&$raw): void {
        $raw[$slug] = array_merge([
            'slug'     => $slug,
            'category' => $cat,
            'name'     => $name,
            'desc'     => $desc,
            'keywords' => $kw,
            'flags'    => $flags,
            'engine'   => $slug,
        ], $extra);
    };

    /* ---------------------------------------------------------------- PDF */
    $t('merge-pdf', 'pdf', 'Merge PDF', 'Combine multiple PDF files into one document, in the order you choose.', ['popular'], 'combine pdf join pdf');
    $t('split-pdf', 'pdf', 'Split PDF', 'Extract selected pages or split a PDF into separate files.', [], 'separate pdf extract pages');
    $t('rotate-pdf', 'pdf', 'Rotate PDF', 'Rotate all or selected pages of a PDF and download the result.', [], 'turn pdf orientation');
    $t('compress-pdf', 'pdf', 'Compress PDF', 'Reduce PDF file size while keeping quality high.', ['popular'], 'shrink pdf reduce size');
    $t('unlock-pdf', 'pdf', 'Unlock PDF', 'Remove password protection from a PDF you own.', [], 'remove pdf password decrypt');
    $t('protect-pdf', 'pdf', 'Protect PDF', 'Add a password and encryption to secure your PDF.', [], 'encrypt pdf password lock');
    $t('pdf-to-jpg', 'pdf', 'PDF to JPG', 'Convert each page of a PDF into a high-quality JPG image.', ['trending'], 'pdf image export pages');
    $t('jpg-to-pdf', 'pdf', 'JPG to PDF', 'Turn one or many images into a single PDF document.', ['popular'], 'image to pdf photo pdf');
    $t('word-to-pdf', 'pdf', 'Word to PDF', 'Convert Word / text documents into PDF files.', [], 'doc docx pdf convert');
    $t('pdf-to-word', 'pdf', 'PDF to Word', 'Extract a PDF into an editable Word document.', ['popular'], 'pdf doc docx convert export');
    $t('pdf-to-powerpoint', 'pdf', 'PDF to PowerPoint', 'Convert a PDF into an editable PowerPoint presentation.', [], 'pdf ppt pptx slides convert');
    $t('pdf-to-excel', 'pdf', 'PDF to Excel', 'Pull data from a PDF into an Excel spreadsheet.', [], 'pdf xls xlsx sheet convert');
    $t('powerpoint-to-pdf', 'pdf', 'PowerPoint to PDF', 'Convert PPT and PPTX slideshows into PDF.', [], 'ppt pptx slides pdf convert');
    $t('excel-to-pdf', 'pdf', 'Excel to PDF', 'Convert Excel spreadsheets into PDF documents.', [], 'xls xlsx sheet pdf convert');
    $t('edit-pdf', 'pdf', 'Edit PDF', 'Add text and notes anywhere on your PDF.', ['trending'], 'annotate add text pdf editor');
    $t('sign-pdf', 'pdf', 'Sign PDF', 'Draw and place your signature on a PDF.', ['popular'], 'signature esign sign document');
    $t('watermark-pdf', 'pdf', 'Watermark PDF', 'Stamp text watermarks across every page.', [], 'stamp watermark brand overlay');
    $t('html-to-pdf', 'pdf', 'HTML to PDF', 'Convert HTML content into a PDF document.', [], 'webpage html pdf convert');
    $t('organize-pdf', 'pdf', 'Organize PDF', 'Reorder and delete pages visually.', [], 'reorder delete pages arrange');
    $t('pdf-to-pdfa', 'pdf', 'PDF to PDF/A', 'Convert to the ISO PDF/A archival format.', [], 'pdfa archive iso long term');
    $t('repair-pdf', 'pdf', 'Repair PDF', 'Recover data from a damaged or corrupt PDF.', [], 'fix damaged corrupt recover');
    $t('page-numbers-pdf', 'pdf', 'Add Page Numbers', 'Insert page numbers anywhere on your PDF.', [], 'page numbers pagination');
    $t('scan-to-pdf', 'pdf', 'Scan to PDF', 'Capture pages with your camera into a PDF.', ['new'], 'camera scan document pdf');
    $t('ocr-pdf', 'pdf', 'OCR PDF', 'Make scanned PDFs searchable and selectable.', [], 'ocr searchable scan text recognize');
    $t('compare-pdf', 'pdf', 'Compare PDF', 'View two PDFs side by side to spot changes.', [], 'diff compare versions changes');
    $t('redact-pdf', 'pdf', 'Redact PDF', 'Permanently black out sensitive content.', [], 'redact hide censor remove sensitive');
    $t('crop-pdf', 'pdf', 'Crop PDF', 'Trim margins and crop PDF pages.', [], 'trim margins crop pages');
    $t('pdf-forms', 'pdf', 'Fill PDF Forms', 'Detect and fill interactive PDF form fields.', [], 'forms acroform fill fields');
    $t('pdf-summarizer', 'pdf', 'PDF Summarizer', 'Summarise a PDF into key points, on device.', ['new'], 'summary tldr ai pdf');
    $t('translate-pdf', 'pdf', 'Translate PDF', 'Translate the text content of a PDF.', [], 'translate language pdf convert');
    $t('pdf-to-markdown', 'pdf', 'PDF to Markdown', 'Extract PDF text as clean Markdown.', ['new'], 'md markdown extract text pdf');

    /* -------------------------------------------------------------- Image */
    $t('compress-image', 'image', 'Compress Image', 'Reduce JPG, PNG and WebP file size with adjustable quality, on device.', ['popular'], 'shrink photo reduce jpg png');
    $t('resize-image', 'image', 'Resize Image', 'Resize images to exact pixel dimensions or by percentage.', ['popular'], 'scale photo dimensions px');
    $t('crop-image', 'image', 'Crop Image', 'Crop an image to any region with a live preview.', [], 'cut photo trim');
    $t('rotate-image', 'image', 'Rotate Image', 'Rotate and flip images to any angle.', [], 'turn flip photo');
    $t('flip-image', 'image', 'Flip Image', 'Flip images horizontally or vertically.', [], 'mirror photo');
    $t('grayscale-image', 'image', 'Grayscale Image', 'Convert any image to black & white instantly.', [], 'black white monochrome');
    $t('convert-png', 'image', 'Convert to PNG', 'Convert any image to lossless PNG format.', [], 'to png image convert');
    $t('convert-jpg', 'image', 'Convert to JPG', 'Convert any image to compressed JPG format.', [], 'to jpg jpeg convert');
    $t('convert-webp', 'image', 'Convert to WebP', 'Convert images to modern, lightweight WebP.', ['trending'], 'to webp modern convert');
    $t('image-converter', 'image', 'Image Converter', 'Convert between PNG, JPG, WebP and BMP in one place.', ['popular'], 'change format image');
    $t('image-to-gif', 'image', 'Image to GIF', 'Animate a single image (zoom, pan, pulse, fade) or combine multiple images (JPG, PNG, WebP) into an animated GIF, on device.', ['new'], 'jpg png webp to gif animated gif maker ken burns zoom pan photos slideshow');
    $t('image-to-base64', 'image', 'Image to Base64', 'Encode an image into a Base64 data URI for CSS/HTML.', [], 'data uri encode inline');
    $t('gif-animation-studio', 'image', 'GIF Animation Studio', 'Start from a ready-made template, compose images, icons, shapes and text on a canvas, animate each element independently (slide, fade, pop, spin, float, pulse) and export an animated GIF.', ['new'], 'motion graphics animation studio animated gif maker layers shapes templates logo intro title lower third youtube sale');

    /* -------------------------------------------------------------- Video */
    $t('video-metadata', 'video', 'Video Metadata Viewer', 'View resolution, duration, aspect ratio and more from a video file.', [], 'inspect video info duration');
    $t('video-thumbnail', 'video', 'Video Thumbnail Grabber', 'Capture a frame from any video and save it as an image.', ['new'], 'frame snapshot poster video');
    $t('video-to-gif', 'video', 'Video to GIF', 'Turn a video clip (MP4, WebM, MOV) into an animated GIF, trim the range, pick the frame rate and size. Runs entirely in your browser.', ['new', 'popular'], 'mp4 webm mov to gif convert video animated gif maker trim clip fps');

    /* -------------------------------------------------------------- Audio */
    $t('mp3-cutter', 'audio', 'Audio Cutter', 'Trim and cut any audio file to a selected range (exports WAV).', ['popular'], 'trim mp3 cut clip');
    $t('audio-converter', 'audio', 'Audio Converter', 'Decode and export audio as a clean WAV file.', [], 'convert wav format audio');
    $t('volume-booster', 'audio', 'Volume Booster', 'Increase the loudness of an audio file with gain control.', [], 'louder gain amplify');
    $t('normalize-audio', 'audio', 'Normalize Audio', 'Automatically normalise audio to a consistent peak level.', [], 'level peak loudness');
    $t('audio-metadata', 'audio', 'Audio Metadata Viewer', 'Inspect duration, channels and sample rate of audio files.', [], 'inspect audio info');

    /* --------------------------------------------------------------- Text */
    $t('word-counter', 'text', 'Word Counter', 'Count words, characters, sentences, paragraphs and reading time.', ['popular'], 'count words characters reading time');
    $t('character-counter', 'text', 'Character Counter', 'Count characters with and without spaces in real time.', [], 'count letters length');
    $t('case-converter', 'text', 'Case Converter', 'Convert text to UPPER, lower, Title, Sentence and more.', ['popular'], 'uppercase lowercase title case');
    $t('remove-duplicate-lines', 'text', 'Remove Duplicate Lines', 'Delete duplicate lines and optionally sort the result.', [], 'dedupe unique lines');
    $t('text-sorter', 'text', 'Text Sorter', 'Sort lines alphabetically, numerically, or reverse.', [], 'sort lines order');
    $t('reverse-text', 'text', 'Reverse Text', 'Reverse text, words or lines instantly.', [], 'backwards flip mirror text');
    $t('random-text-generator', 'text', 'Random Text Generator', 'Generate random words, sentences or strings.', [], 'placeholder random string');
    $t('lorem-ipsum', 'text', 'Lorem Ipsum Generator', 'Generate classic Lorem Ipsum placeholder text.', ['popular'], 'placeholder dummy text');
    $t('markdown-preview', 'text', 'Markdown Preview', 'Write Markdown and preview the rendered HTML live.', ['trending'], 'md render preview html');
    $t('find-replace', 'text', 'Find & Replace', 'Find and replace text with regex and case options.', [], 'substitute swap regex');
    $t('text-diff', 'text', 'Text Diff Checker', 'Compare two blocks of text and highlight differences.', [], 'compare diff changes');
    $t('whitespace-remover', 'text', 'Whitespace Remover', 'Trim extra spaces, tabs and blank lines from text.', [], 'trim spaces clean');

    /* ---------------------------------------------------------- Developer */
    $t('json-formatter', 'developer', 'JSON Formatter', 'Beautify, minify and validate JSON with syntax feedback.', ['popular'], 'pretty json beautify format');
    $t('json-validator', 'developer', 'JSON Validator', 'Validate JSON and pinpoint syntax errors instantly.', [], 'lint json check valid');
    $t('base64-encode', 'developer', 'Base64 Encode', 'Encode text or files to Base64.', ['popular'], 'encode base64 convert');
    $t('base64-decode', 'developer', 'Base64 Decode', 'Decode Base64 back to readable text.', [], 'decode base64 convert');
    $t('jwt-decoder', 'developer', 'JWT Decoder', 'Decode and inspect JSON Web Token header and payload.', ['trending'], 'token jwt decode inspect');
    $t('uuid-generator', 'developer', 'UUID Generator', 'Generate v4 UUIDs, single or in bulk.', ['popular'], 'guid unique id generate');
    $t('hash-generator', 'developer', 'Hash Generator', 'Generate MD5, SHA-1, SHA-256 and SHA-512 hashes.', [], 'md5 sha checksum digest');
    $t('regex-tester', 'developer', 'Regex Tester', 'Test regular expressions with live match highlighting.', [], 'regexp pattern match test');
    $t('html-formatter', 'developer', 'HTML Formatter', 'Beautify and indent messy HTML markup.', [], 'pretty html indent beautify');
    $t('css-minifier', 'developer', 'CSS Minifier', 'Minify CSS to reduce file size.', [], 'compress css shrink');
    $t('js-minifier', 'developer', 'JS Minifier', 'Minify JavaScript to reduce file size.', [], 'compress javascript shrink');
    $t('sql-formatter', 'developer', 'SQL Formatter', 'Format and indent SQL queries for readability.', [], 'pretty sql beautify query');
    $t('html-beautifier', 'developer', 'Code Beautifier', 'Beautify HTML, CSS and JS in one place.', [], 'pretty format indent code');
    $t('url-encode', 'developer', 'URL Encode', 'Percent-encode text for safe use in URLs.', [], 'encode uri percent');
    $t('url-decode', 'developer', 'URL Decode', 'Decode percent-encoded URL strings.', [], 'decode uri percent');
    $t('html-entities', 'developer', 'HTML Entity Encoder', 'Encode and decode HTML entities.', [], 'escape entities special chars');
    $t('color-contrast', 'developer', 'Color Contrast Checker', 'Check WCAG contrast ratio between two colors.', [], 'wcag accessibility ratio');
    $t('cron-parser', 'developer', 'Cron Expression Parser', 'Explain a cron expression in plain English.', [], 'crontab schedule explain');

    /* --------------------------------------------------------------- SEO */
    $t('meta-generator', 'seo', 'Meta Tag Generator', 'Generate complete SEO meta tags for any page.', ['popular'], 'meta tags seo head');
    $t('meta-description-generator', 'seo', 'Meta Description Generator', 'Craft optimised meta descriptions within the ideal length.', [], 'description snippet seo');
    $t('title-generator', 'seo', 'Title Tag Generator', 'Build SEO-friendly title tags with length feedback.', [], 'title tag seo headline');
    $t('robots-txt-generator', 'seo', 'Robots.txt Generator', 'Create a valid robots.txt to control crawlers.', [], 'robots crawler disallow');
    $t('sitemap-generator', 'seo', 'Sitemap Generator', 'Generate an XML sitemap from a list of URLs.', [], 'xml sitemap urls');
    $t('schema-generator', 'seo', 'Schema Markup Generator', 'Generate JSON-LD structured data for rich results.', ['trending'], 'json-ld structured data rich');
    $t('canonical-generator', 'seo', 'Canonical Tag Generator', 'Generate canonical link tags to avoid duplicate content.', [], 'canonical duplicate url');
    $t('opengraph-generator', 'seo', 'Open Graph Generator', 'Generate Open Graph tags for rich social sharing.', [], 'og facebook social share');
    $t('twitter-card-generator', 'seo', 'Twitter Card Generator', 'Generate Twitter/X card meta tags.', [], 'twitter x card social');
    $t('slug-generator', 'seo', 'Slug Generator', 'Turn any title into a clean, SEO-friendly URL slug.', [], 'url slug permalink');

    /* ------------------------------------------------------------ Finance */
    $t('emi-calculator', 'finance', 'EMI Calculator', 'Calculate loan EMI, total interest and amortisation.', ['popular'], 'loan emi installment');
    $t('gst-calculator', 'finance', 'GST Calculator', 'Add or remove GST/VAT from any amount.', [], 'tax vat gst');
    $t('loan-calculator', 'finance', 'Loan Calculator', 'Estimate monthly payments and total cost of a loan.', [], 'mortgage payment interest');
    $t('age-calculator', 'finance', 'Age Calculator', 'Calculate exact age in years, months and days.', ['popular'], 'birthday age years');
    $t('percentage-calculator', 'finance', 'Percentage Calculator', 'Solve every kind of percentage problem quickly.', [], 'percent increase decrease');
    $t('discount-calculator', 'finance', 'Discount Calculator', 'Calculate sale price and savings from a discount.', [], 'sale off savings price');
    $t('sip-calculator', 'finance', 'SIP Calculator', 'Estimate returns on a Systematic Investment Plan.', ['trending'], 'mutual fund invest returns');
    $t('currency-converter', 'finance', 'Currency Converter', 'Convert between currencies with editable exchange rates.', [], 'money exchange rate forex');

    /* ---------------------------------------------------------- Utilities */
    $t('qr-code-generator', 'utilities', 'QR Code Generator', 'Create QR codes for URLs, text, Wi-Fi and more.', ['popular'], 'qr barcode scan generate');
    $t('barcode-generator', 'utilities', 'Barcode Generator', 'Generate CODE128 barcodes from any text.', [], 'barcode code128 generate');
    $t('password-generator', 'utilities', 'Password Generator', 'Create strong, random passwords with custom rules.', ['popular'], 'random secure password');
    $t('password-strength', 'utilities', 'Password Strength Checker', 'Measure how strong a password really is.', [], 'strength entropy secure');
    $t('timestamp-converter', 'utilities', 'Timestamp Converter', 'Convert between Unix timestamps and human dates.', ['trending'], 'unix epoch time date');
    $t('color-picker', 'utilities', 'Color Picker', 'Pick colors and get HEX, RGB, HSL values.', [], 'color hex rgb hsl');
    $t('gradient-generator', 'utilities', 'CSS Gradient Generator', 'Design CSS gradients with a live preview.', ['trending'], 'css gradient background');
    $t('hex-to-rgb', 'utilities', 'HEX to RGB', 'Convert HEX color codes to RGB values.', [], 'color hex rgb convert');
    $t('rgb-to-hex', 'utilities', 'RGB to HEX', 'Convert RGB values to HEX color codes.', [], 'color rgb hex convert');
    $t('random-number', 'utilities', 'Random Number Generator', 'Generate random numbers in any range.', [], 'random number range dice');
    $t('coin-flip', 'utilities', 'Coin Flip', 'Flip a virtual coin, heads or tails.', [], 'heads tails random');
    $t('stopwatch', 'utilities', 'Stopwatch & Timer', 'A precise stopwatch and countdown timer.', [], 'timer countdown clock');

    /* -------------------------------------------------------- Calculators */
    $t('bmi-calculator', 'calculators', 'BMI Calculator', 'Calculate Body Mass Index and category.', ['popular'], 'body mass weight health');
    $t('tip-calculator', 'calculators', 'Tip Calculator', 'Split the bill and calculate the tip per person.', [], 'gratuity bill split');
    $t('average-calculator', 'calculators', 'Average Calculator', 'Find mean, median, mode and sum of numbers.', [], 'mean median mode sum');
    $t('date-difference', 'calculators', 'Date Difference', 'Calculate the number of days between two dates.', [], 'days between dates duration');
    $t('scientific-calculator', 'calculators', 'Scientific Calculator', 'A full scientific calculator in your browser.', [], 'math trig log calculator');

    /* ---------------------------------------------------------- Documents */
    $t('csv-to-json', 'documents', 'CSV to JSON', 'Convert CSV data into structured JSON.', ['trending'], 'csv json convert data');
    $t('json-to-csv', 'documents', 'JSON to CSV', 'Convert a JSON array into CSV.', [], 'json csv convert data');
    $t('markdown-to-html', 'documents', 'Markdown to HTML', 'Convert Markdown into clean HTML.', [], 'md html convert');
    $t('text-to-pdf', 'documents', 'Text to PDF', 'Turn plain text into a downloadable PDF.', [], 'txt pdf document convert');

    /* ---------------------------------------------------------------- AI */
    $t('text-summarizer', 'ai', 'Text Summarizer', 'Summarise long text into key sentences, on device.', ['popular', 'new'], 'summary tldr shorten');
    $t('keyword-extractor', 'ai', 'Keyword Extractor', 'Extract the most important keywords from any text.', ['new'], 'keywords tags extract');
    $t('readability-checker', 'ai', 'Readability Checker', 'Score the readability of your writing (Flesch).', ['new'], 'flesch grade reading level');

    /* --------------------------------------------------------- Converters */
    $t('bytes-converter', 'converters', 'Data Size Converter', 'Convert between bytes, KB, MB, GB and TB.', [], 'bytes kb mb gb data');
    $t('length-converter', 'converters', 'Length Converter', 'Convert metres, feet, miles, inches and more.', ['popular'], 'metre feet mile length');
    $t('weight-converter', 'converters', 'Weight Converter', 'Convert kilograms, pounds, ounces and grams.', [], 'kg lb weight mass');
    $t('temperature-converter', 'converters', 'Temperature Converter', 'Convert Celsius, Fahrenheit and Kelvin.', ['popular'], 'celsius fahrenheit kelvin');
    $t('speed-converter', 'converters', 'Speed Converter', 'Convert km/h, mph, m/s and knots.', [], 'kmh mph speed velocity');
    $t('area-converter', 'converters', 'Area Converter', 'Convert square metres, acres, hectares and more.', [], 'square metre acre area');
    $t('volume-converter', 'converters', 'Volume Converter', 'Convert litres, gallons, cups and millilitres.', [], 'litre gallon volume');
    $t('time-converter', 'converters', 'Time Converter', 'Convert seconds, minutes, hours, days and weeks.', [], 'seconds minutes hours time');
    $t('number-to-words', 'converters', 'Number to Words', 'Convert numbers into written words.', [], 'spell number words');
    $t('roman-numerals', 'converters', 'Roman Numeral Converter', 'Convert between numbers and Roman numerals.', [], 'roman numeral convert');

    /*
     * Merge long-form page content (intro, faqs, processing) from includes/content/.
     * Keeping it in separate files preserves the one-line-per-tool registry above
     * while still exposing everything through get_tool(). Unknown slugs are
     * ignored so a content file can never resurrect a retired tool.
     */
    foreach (['pdf'] as $pack) {
        $file = __DIR__ . '/content/' . $pack . '.php';
        if (!is_file($file)) {
            continue;
        }
        foreach ((array) require $file as $slug => $extra) {
            if (isset($raw[$slug])) {
                $raw[$slug] = array_merge($raw[$slug], $extra);
            }
        }
    }

    $tools = $raw;
    return $tools;
}

/* =========================================================================
 * Registry accessors
 * ====================================================================== */

/** Fetch one tool by slug (or null). */
function get_tool(string $slug): ?array
{
    $tools = omnitools_tools();
    return $tools[$slug] ?? null;
}

/** All tools within a category. */
function tools_in_category(string $cat): array
{
    return array_values(array_filter(omnitools_tools(), fn($t) => $t['category'] === $cat));
}

/** Tools carrying a given flag (popular|trending|new). */
function tools_with_flag(string $flag, int $limit = 8): array
{
    $out = array_values(array_filter(omnitools_tools(), fn($t) => in_array($flag, $t['flags'], true)));
    return array_slice($out, 0, $limit);
}

/** Total tool count. */
function tools_count(): int
{
    return count(omnitools_tools());
}

/** Related tools (same category, excluding self). */
function related_tools(string $slug, int $limit = 6): array
{
    $tool = get_tool($slug);
    if (!$tool) {
        return [];
    }
    $out = array_values(array_filter(
        tools_in_category($tool['category']),
        fn($t) => $t['slug'] !== $slug
    ));
    return array_slice($out, 0, $limit);
}

/** Category meta for a tool. */
function category_of(string $slug): ?array
{
    $tool = get_tool($slug);
    if (!$tool) {
        return null;
    }
    $cats = omnitools_categories();
    $meta = $cats[$tool['category']] ?? null;
    return $meta ? array_merge(['slug' => $tool['category']], $meta) : null;
}

/**
 * Inline SVG icon by name. Keeps the UI crisp and dependency-free.
 */
function icon_svg(string $name, string $class = 'icon'): string
{
    $paths = [
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'video'    => '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/>',
        'audio'    => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'text'     => '<path d="M17 6.1H3M21 12.1H3M15.1 18H3"/>',
        'code'     => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'money'    => '<circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v2m0 8v2"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'calc'     => '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="8" x2="8" y1="14" y2="14"/><line x1="12" x2="12" y1="14" y2="14"/><line x1="16" x2="16" y1="14" y2="14"/><line x1="8" x2="8" y1="18" y2="18"/><line x1="12" x2="12" y1="18" y2="18"/><line x1="16" x2="16" y1="18" y2="18"/>',
        'doc'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M9 13h6M9 17h4"/>',
        'sparkles' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15z"/>',
        'swap'     => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>',
        'moon'     => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'star'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'heart'    => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'flame'    => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'plus'     => '<path d="M5 12h14M12 5v14"/>',
        'arrow'    => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'menu'     => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check'    => '<polyline points="20 6 9 17 4 12"/>',
        'copy'     => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
    ];
    $inner = $paths[$name] ?? $paths['grid'];
    return '<svg class="' . eattr($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
