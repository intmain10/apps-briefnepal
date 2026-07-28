<?php
/**
 * Minimal, safe server-side Markdown → HTML for article bodies.
 *
 * Shared by the Toolzy blog (blog/post.php) and the Cardly blog
 * (cardly-blog.php). Everything is escaped first, so an article body can never
 * inject markup — the only HTML in the output is what this renderer emits.
 *
 * Supported: ##–#### headings, unordered/ordered lists, blockquotes, fenced
 * code, and inline `code`, **bold**, *italic*, [links](url). A `[tool:slug]`
 * line mounts an embedded tool, but only where that renderer exists (the Toolzy
 * blog); elsewhere the shortcode is dropped rather than shown as raw text.
 *
 * @package Toolzy
 */
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

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
            if (function_exists('tool_embed_html')) { $html .= tool_embed_html($m[1]); }
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
