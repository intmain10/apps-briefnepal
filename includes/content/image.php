<?php
/**
 * Long-form page content for image tools.
 *
 * Same contract as content/pdf.php: these `extra` fields are merged into the
 * registry by omnitools_tools(), and the FAQs are rendered into FAQPage
 * JSON-LD. Every claim here must match what assets/js/tools.js actually does —
 * an overstatement is an overstatement served to search engines.
 *
 * @package Toolzy
 */

declare(strict_types=1);

return [

/* ------------------------------------------------------ remove background */
'remove-background' => [
    'processing' => 'client',
    'intro' => [
        'A JPG cannot be transparent. The format has no alpha channel at all, so the moment you need a logo on a coloured slide, a signature over a contract, a product shot on a marketplace listing or a headshot on a dark website, you need a PNG whose background has actually been removed rather than painted white.',
        'This tool does that by sampling the background colour and clearing every pixel close enough to it, then exporting a 32-bit PNG with real transparency. It reads the colour from the border of your image automatically, and you can click the preview to add any other colour that should also go, which is how a two-tone or gradient backdrop gets handled.',
        'Two controls decide the quality of the result. Tolerance sets how far a pixel may drift from the background colour and still be removed, which matters because a JPG never stores a flat colour flatly. Edge softness fades the boundary instead of cutting it hard, and that is what prevents the pale halo you normally see around a badly cut-out logo.',
        'By default it works inward from the edges of the image and only clears background that is connected to them, so white inside a letter O, the gap in a pair of scissors or a bright shirt in the middle of a photo is kept. Turn that off and every matching pixel is removed wherever it sits, which is what you want for a flat logo or icon set.',
        'It runs entirely in your browser on a canvas. Nothing is uploaded, which is the right property for a signature, an ID photo or unreleased product imagery.',
    ],
    'faqs' => [
        ['Does this give me a genuinely transparent PNG?', 'Yes. The output is a 32-bit PNG with a real alpha channel, so the cleared areas are transparent in Figma, Canva, PowerPoint, Photoshop, a website or anywhere else. It is not a white rectangle pretending to be transparent, which is what you get when a JPG is simply renamed or re-saved.'],
        ['Which backgrounds does it handle well?', 'Solid and near-solid backgrounds: white, grey, black, studio backdrops, screenshots, scans, flat-colour logos, product shots on seamless paper, and signatures on white. Gradients often work too, because you can click to add several background colours. A busy photographic background — a street, a room, foliage behind a person — is not what this method is for; that needs subject segmentation rather than colour matching.'],
        ['What do Tolerance and Edge softness actually do?', 'Tolerance is how far a pixel\'s colour may differ from the sampled background and still be removed. Raise it if patches of background survive; lower it if parts of your subject start disappearing. Edge softness fades pixels just beyond the tolerance instead of cutting them off sharply, which removes the faint outline JPG compression leaves around edges. Around 10 to 20 tolerance with a little softness suits most photos.'],
        ['Why is some background left in the middle of my image?', 'By default only background connected to the edges of the image is removed, which protects same-coloured areas inside your subject. If you want every matching pixel gone — the enclosed area of a letter, the hole in a doughnut, gaps in a logo — untick "Edges inward only".'],
        ['Parts of my subject are being erased. How do I fix that?', 'Your subject contains a colour close to the background. Lower the tolerance first. If the subject is genuinely white against white, keep "Edges inward only" ticked so interior areas are protected, and remove any picked colour that is too close to the subject by clicking its swatch.'],
        ['Can I remove more than one background colour?', 'Yes. Click anywhere on the preview to add the colour under your cursor to the list, as many times as you need, and click a colour swatch to drop it again. This is how you clear a gradient backdrop or a backdrop with a shadow falling across it.'],
        ['Does it work on a transparent PNG I already have?', 'Yes. Existing transparency is preserved, and the tool only clears additional pixels that match the colours you have picked. "Trim empty space" is useful here: it crops the fully transparent margin so the exported PNG is tight to the subject.'],
        ['Is my image uploaded anywhere?', 'No. The image is read from your device into a canvas and processed there. Nothing is sent to a server, so signatures, ID photos and unreleased product images never leave your machine.'],
        ['Is there a size limit?', 'There is no imposed limit. The preview is scaled down so the sliders stay responsive, and the full-resolution version is processed when you press Download, so a very large photo takes a moment on a phone. Output dimensions always match your original unless you trim the empty space.'],
    ],
],

];
