<?php
/**
 * Long-form page content for the PDF tool collection.
 *
 * Kept out of includes/tools.php on purpose: that registry is deliberately
 * one-readable-line-per-tool so it scales to 1000+ entries. This file supplies
 * the `extra` fields (intro, faqs, processing) which omnitools_tools() merges
 * into each tool, so get_tool('merge-pdf')['intro'] works exactly as the
 * registry docblock describes.
 *
 * Every claim here must match what assets/js/pdf-tools.js and api/pdf.php
 * actually do — this content is rendered into FAQPage JSON-LD, so an
 * inaccuracy is a structured-data inaccuracy served to search engines.
 *
 * `processing` drives the privacy messaging and must be exact:
 *   'client' → runs fully in-browser (pdf-lib / pdf.js / qpdf.wasm), no upload
 *   'server' → posts to api/pdf.php, needs Ghostscript/qpdf/Imagick/LibreOffice
 *
 * @package Toolzy
 */

declare(strict_types=1);

return [

/* ------------------------------------------------------------------ merge */
'merge-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Merging is the most common PDF job in professional work, and it is almost always about assembling one clean deliverable from parts that arrived separately. A closing packet gathers the contract, the disclosures and the signed addenda. A court bundle gathers the pleading and its exhibits. A month-end submission gathers a dozen supplier invoices that each downloaded as their own file.',
        'This merger keeps the underlying page objects intact rather than flattening them to pictures, so text stays selectable and searchable in the merged file, links and fonts survive, and page dimensions are preserved even when you mix A4 and US Letter originals in the same document. You control the order before you commit, which matters when a bundle index has to match the pages behind it.',
    ],
    'faqs' => [
        ['Does merging reduce the quality of my PDFs?', 'No. Pages are copied across as they are, not re-rendered or re-compressed, so text remains selectable, images keep their original resolution and the merged file looks identical to its sources.'],
        ['Can I merge A4 and US Letter pages in one document?', 'Yes. Each page keeps its own dimensions, so a merged file can legitimately contain both A4 and Letter pages. If a recipient needs a single uniform size, set that when printing or exporting rather than at the merge step.'],
        ['Is this suitable for assembling a court bundle or exhibit set?', 'It handles the assembly and ordering reliably, and the output stays text-searchable, which courts generally prefer. Pagination, indexing and bookmarking requirements vary by jurisdiction and by court, so check the local rules or practice direction that applies to your filing before you submit.'],
        ['Are there limits on how many files I can merge?', 'There is no imposed file-count limit. Because merging runs in your browser, the practical ceiling is your device’s available memory, so very large scanned bundles will merge more slowly on a phone than on a laptop.'],
    ],
],

/* ------------------------------------------------------------------ split */
'split-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Splitting is what you reach for when a recipient should see part of a document but not all of it. Pulling a single exhibit out of a filed bundle, sending a lender the three statement pages they asked for instead of a full year, or separating a scanned batch where several distinct documents ended up in one file.',
        'You choose pages by range, so 1-3, 7, 12-15 is a valid selection, and the extracted pages come out as a proper PDF rather than images. Because the original is never modified, you can run several different extractions from the same source file without re-uploading or re-scanning anything.',
    ],
    'faqs' => [
        ['How do I specify which pages to extract?', 'Enter page numbers and ranges separated by commas, for example 1-3, 7, 12-15. Pages are extracted in the order the document holds them.'],
        ['Does splitting remove the pages from my original file?', 'No. The original file on your device is untouched. Splitting produces a new PDF containing only the pages you selected, so you can extract several different sets from the same source.'],
        ['Will the extracted pages still be searchable?', 'Yes. Pages are copied rather than rasterised, so any text layer in the original carries over and the extract remains selectable and searchable.'],
        ['Can I use this to send only part of a document to a third party?', 'Yes, and that is a common data-minimisation step under UK GDPR and similar US state privacy laws. Be aware that extracting pages does not remove personal data that appears on the pages you kept — for that, use the Redact PDF tool, which permanently removes the underlying content.'],
    ],
],

/* ----------------------------------------------------------------- rotate */
'rotate-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Rotation problems almost always come from scanning hardware. A sheet-fed scanner takes a landscape page in the wrong orientation, or a batch scan flips alternate pages when a duplex feeder mis-reads the paper. The pages are fine; the metadata telling a viewer which way is up is not.',
        'This tool corrects that by writing the correct rotation into the page, so the fix is permanent and travels with the file instead of being a temporary view setting in your PDF reader. You can rotate every page at once or only the pages that are wrong, in 90-degree increments.',
    ],
    'faqs' => [
        ['Why does my PDF look correct on screen but print sideways?', 'Your viewer is applying a temporary on-screen rotation that was never saved into the file. Rotating here writes the orientation into the document itself, so it prints and uploads the right way up everywhere.'],
        ['Can I rotate only some pages?', 'Yes. You can apply rotation to the whole document or to a specific selection, which is what you usually need after a duplex scan flipped alternate pages.'],
        ['Does rotating re-compress or degrade the pages?', 'No. Rotation changes a page attribute only. No pixels are re-encoded, so image quality and any text layer are completely unaffected.'],
        ['Will the rotation survive when the file is uploaded to a portal?', 'Yes. Because the rotation is stored in the document structure rather than in your viewer’s settings, any compliant PDF reader on the receiving end will display and print the corrected orientation.'],
    ],
],

/* --------------------------------------------------------------- compress */
'compress-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Upload size caps are the usual reason people compress a PDF. Court e-filing systems, HMRC and Companies House submission portals, lender document uploads and ordinary corporate mail gateways all enforce a maximum, and a scanned document is very often several times over it. The limits differ by system and are revised periodically, so check the figure published for the portal you are filing into.',
        'This compressor works by rasterising each page at a resolution and JPEG quality you choose. That is genuinely effective on scans and image-heavy documents, where it typically produces the largest reductions. It also has an important consequence you should understand before using it: selectable text becomes part of the page image.',
    ],
    'faqs' => [
        ['Will my text still be selectable after compressing?', 'No. This tool rasterises each page, which converts selectable text into part of the page image. The document will look the same but will no longer be searchable or copy-pasteable. If the recipient requires searchable text, do not compress this way.'],
        ['Should I compress a document before filing it with a court?', 'Be careful. Many courts and regulators require filings to be text-searchable, and rasterising removes the text layer. If you are only trying to meet a size limit, first try re-exporting from the source application, and treat rasterising as a last resort — or run OCR afterwards to restore a text layer.'],
        ['Which quality setting should I choose?', 'Screen resolution gives the smallest file and suits documents that will only be read on a display. eBook is a good general-purpose balance. Print keeps the most detail and is the right choice when the document will be physically printed or contains fine detail such as plans or signatures.'],
        ['Why did my file barely shrink?', 'Documents that are already mostly text with few images are efficiently encoded to begin with, so there is little to reclaim, and the tool will tell you when there was no meaningful reduction. Large savings come mainly from scans and photo-heavy pages.'],
    ],
],

/* ----------------------------------------------------------------- unlock */
'unlock-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Password-protected PDFs are routine in finance and payroll: banks issue statements locked with a customer identifier, payroll providers protect payslips, and insurers encrypt policy documents. Once a document reaches its intended recipient, that protection often just gets in the way of filing it, printing it or passing it to an accountant.',
        'This tool removes the encryption using qpdf compiled to WebAssembly, running inside your browser. That detail matters here more than for any other tool on the site: your document and the password you type are both processed locally and are never transmitted anywhere. Use it only on documents you own or are authorised to unlock.',
    ],
    'faqs' => [
        ['Do I need to know the password?', 'Yes, if the PDF has an open password. This tool removes protection from documents you can already open; it does not recover, guess or bypass unknown passwords. If the PDF only carries permission restrictions and no open password, leave the field blank.'],
        ['Is my password sent to your servers?', 'No. The unlocking engine is WebAssembly running inside your browser tab. Neither the document nor the password leaves your device, and nothing about the operation is transmitted, stored or logged by us.'],
        ['Is it legal to remove a PDF password?', 'Removing protection from documents you own or are authorised to handle — your own bank statements or payslips, for example — is ordinary practice. Circumventing protection on documents you have no right to access may breach the US DMCA, the UK Computer Misuse Act 1990 or your contractual obligations. You are responsible for having the authority to unlock the file.'],
        ['Why would I unlock a bank statement?', 'Accountants, mortgage brokers and bookkeeping software frequently cannot open encrypted PDFs. Removing the password from a statement you already have access to lets you file or share it through systems that would otherwise reject it.'],
    ],
],

/* ---------------------------------------------------------------- protect */
'protect-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Encrypting a document before it leaves your control is one of the clearest technical safeguards you can apply to it. Both UK GDPR and the EU GDPR name encryption explicitly in Article 32 as an example of an appropriate technical measure, and US frameworks including HIPAA treat it as a recognised way to reduce the impact of a document going astray.',
        'This tool applies AES-256 encryption with an open password using qpdf compiled to WebAssembly. The encryption runs inside your browser, so the document and the password you choose are both handled locally. The resulting file requires the password in any standards-compliant PDF reader.',
    ],
    'faqs' => [
        ['What encryption strength is used?', 'AES-256, the strongest encryption defined by the PDF specification and the standard expected for documents containing personal or financial data.'],
        ['Does encrypting a PDF satisfy GDPR requirements?', 'Encryption is specifically cited in Article 32 of the UK and EU GDPR as an example of an appropriate technical measure, and encrypting attachments before emailing them is widely treated as good practice. It is one control among several, not blanket compliance, so it should sit within your organisation’s wider data-protection approach.'],
        ['Is my password sent anywhere?', 'No. Encryption happens in your browser through WebAssembly. The document and the password never leave your device, so we could not recover the password for you even if asked.'],
        ['What happens if I forget the password?', 'The file cannot be opened. AES-256 has no backdoor and there is no reset. Record the password somewhere safe before you send the document, and share it with the recipient through a different channel than the file itself.'],
    ],
],

/* ------------------------------------------------------------- pdf-to-jpg */
'pdf-to-jpg' => [
    'processing' => 'client',
    'intro' => [
        'Turning PDF pages into images is what you do when the destination will not take a PDF at all. Content management systems, listing platforms, marketplace product pages, slide decks and messaging apps frequently accept only images, and a page that renders perfectly as a PDF simply cannot be posted.',
        'Each page is rendered to a JPG at the resolution and quality you select, and every page is offered as its own download so you can take only the ones you need. Because rendering happens in your browser, the document is never uploaded — which matters when the pages contain client or contract detail.',
    ],
    'faqs' => [
        ['What resolution should I choose?', 'Standard suits on-screen use and web publishing. High is the right choice when the image will be printed or zoomed into, such as a plan or a detailed table. Low produces the smallest files for quick previews and thumbnails.'],
        ['Can I download all the pages at once?', 'Each page is presented separately with its own download button, so you can take a single page or work through them all. This is deliberate — most people converting a PDF to images want specific pages, not every page in a long document.'],
        ['Is my document uploaded to convert it?', 'No. Rendering runs entirely in your browser, so a contract, statement or client document never leaves your device during conversion.'],
        ['Will the resulting images be searchable?', 'No. A JPG is a picture, so any text becomes part of the image and cannot be selected or searched. Keep the original PDF if you need a searchable copy for your records.'],
    ],
],

/* ------------------------------------------------------------- jpg-to-pdf */
'jpg-to-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Almost every organisation that accepts documents wants a single PDF, and almost every phone produces individual JPGs. That mismatch is why this conversion is so common: photographs of receipts for an expense claim, images of an ID document for a compliance check, or pictures of damage for an insurance submission all need to arrive as one file.',
        'You can add several images, arrange the order, and export them as a single multi-page PDF. Conversion runs in your browser, which is the right property for this category of file — identity documents and receipts are exactly the material you should avoid uploading to an unfamiliar service.',
    ],
    'faqs' => [
        ['Can I combine several photos into one PDF?', 'Yes. Add as many images as you need, put them in the order you want, and they become sequential pages of a single PDF.'],
        ['Which image formats can I use?', 'Common web and camera formats including JPG, PNG and WebP. The exact accepted list is shown in the upload area of the tool.'],
        ['Are my images uploaded to a server?', 'No. The whole conversion runs inside your browser, so photographs of ID documents, receipts or personal records never leave your device.'],
        ['Will the PDF be accepted for an ID or compliance check?', 'Most portals accept image-based PDFs for identity and receipt uploads. Where a checker requires text to be machine-readable, the pages will need OCR first, since a photographed page contains no text layer.'],
    ],
],

/* ------------------------------------------------------------ word-to-pdf */
'word-to-pdf' => [
    'processing' => 'server',
    'intro' => [
        'PDF is the format organisations ask for when a document must look the same for everyone who opens it. A Word file re-flows depending on the fonts and version installed on the reader’s machine, which is why contracts, tenders, statements of work and formal correspondence are almost always requested as PDF.',
        'This conversion runs on the server because faithful Office rendering needs a full office engine — LibreOffice — rather than anything a browser can do alone. Your document is sent over HTTPS, written to a temporary file with a randomly generated name, converted, and deleted immediately once the finished PDF has been sent back to you.',
    ],
    'faqs' => [
        ['Why is this conversion done on a server when your other PDF tools are not?', 'Rendering .doc and .docx with correct fonts, styles, tables and pagination requires a complete office engine. That cannot run in a browser tab, so this specific conversion is handled server-side while the in-browser PDF tools continue to work with no upload at all.'],
        ['What happens to my document after conversion?', 'It is stored under a randomly generated filename in a temporary directory, used only for the conversion, and deleted as soon as your PDF has been streamed back. Neither the source file nor the output is retained.'],
        ['Will my formatting be preserved?', 'Standard formatting — headings, tables, lists, page breaks and common fonts — converts faithfully. Documents relying on unusual embedded fonts or complex third-party add-ins may shift slightly, so check the output before sending it to a client or filing it.'],
        ['What if the conversion is unavailable?', 'The tool depends on LibreOffice being installed on the host. If it is not available you will get a clear message rather than a silent failure, and the in-browser PDF tools on this site continue to work without any server involvement.'],
    ],
],

/* ------------------------------------------------------------ pdf-to-word */
'pdf-to-word' => [
    'processing' => 'client',
    'intro' => [
        'Recovering editable text from a PDF is a recurring need when the original document is gone: an agreement you received but never had in source form, a policy you need to update, or a template that has been passed around as a PDF for years.',
        'This tool reads the PDF’s embedded text layer in your browser and builds a Word document from it. It is a text recovery tool rather than a layout clone — the words come across reliably, but complex multi-column layouts, tables and precise positioning will not be reproduced exactly, and a scanned page with no text layer will produce nothing until it has been through OCR.',
    ],
    'faqs' => [
        ['Will the Word file look exactly like the PDF?', 'No. This extracts the text into an editable Word document rather than reconstructing the layout. Paragraphs and page breaks carry across, but multi-column layouts, tables and exact positioning will not be reproduced. It is built for recovering editable content, not for cloning a design.'],
        ['Why did my scanned PDF produce an empty document?', 'A scanned page is an image and holds no text layer to extract. Run it through the OCR PDF tool first to add a text layer, then convert.'],
        ['Is my document uploaded anywhere?', 'No. The text is extracted in your browser and the Word file is generated on your device, so a contract or client document is never transmitted.'],
        ['What file format do I get?', 'A .doc file that opens in Microsoft Word, Google Docs, LibreOffice and Pages. You can save it to .docx from within Word if your workflow requires that specific extension.'],
    ],
],

/* ------------------------------------------------------ pdf-to-powerpoint */
'pdf-to-powerpoint' => [
    'processing' => 'server',
    'intro' => [
        'Decks have a habit of circulating as PDFs long after the source file has been lost. When a client asks for changes to a pitch, or a template needs updating for a new quarter, getting back to editable slides is the difference between an edit and a rebuild.',
        'This conversion runs on the server because rebuilding slide structure requires an office engine rather than browser JavaScript. Your file is uploaded over HTTPS, given a random temporary filename, converted, and deleted the moment the result has been returned to you.',
    ],
    'faqs' => [
        ['How editable will the resulting slides be?', 'You get slides you can open and work with in PowerPoint, but a PDF does not carry PowerPoint’s object model, so animations, speaker notes, slide masters and precise groupings cannot be recovered. Expect a solid starting point rather than the original deck.'],
        ['Why does this need a server when other tools here do not?', 'Reconstructing Office file structure requires a full office engine, which cannot run inside a browser tab. Most of the PDF tools on this site avoid uploads entirely; this one cannot.'],
        ['Is my deck kept after conversion?', 'No. It is written to a temporary file with a randomly generated name and deleted immediately after the converted file is sent back. Nothing is retained or logged.'],
        ['What if the tool reports it is unavailable?', 'The conversion depends on an office engine being installed on the host. If it is missing you will see a clear message explaining that, rather than a failed download.'],
    ],
],

/* ----------------------------------------------------------- pdf-to-excel */
'pdf-to-excel' => [
    'processing' => 'server',
    'intro' => [
        'Financial data has a habit of arriving locked inside PDFs. Bank statements, HMRC and IRS output, supplier invoices, management accounts and audit schedules all get distributed as PDF, and every one of them tends to be needed as rows in a spreadsheet before anyone can reconcile, model or file against it.',
        'This conversion runs on the server because reliable table extraction requires processing that a browser cannot perform alone. The file is sent over HTTPS, handled under a random temporary filename, converted, and deleted as soon as the spreadsheet has been returned.',
    ],
    'faqs' => [
        ['How accurate is the table extraction?', 'Clean, ruled tables with consistent columns extract well. Statements with merged cells, multi-line entries, running balances or footnotes frequently need manual tidying afterwards. Always reconcile the totals against the source PDF before relying on the data.'],
        ['Can I use this for bank statements or tax documents?', 'Yes, and it is one of the most common uses. Because these files contain financial personal data, note that this particular tool does upload your document for processing — it is deleted immediately after conversion, but if you would rather nothing left your device, extract the text with PDF to Markdown instead, which runs entirely in your browser.'],
        ['Will scanned statements work?', 'No, not directly. A scan holds no text layer, so there is nothing to extract into cells. Run OCR first to add a text layer, then convert.'],
        ['How long is my file kept on the server?', 'It is not kept. The upload is written under a randomly generated filename, used for the conversion, and deleted the moment the spreadsheet has been streamed back to you.'],
    ],
],

/* ------------------------------------------------------ powerpoint-to-pdf */
'powerpoint-to-pdf' => [
    'processing' => 'server',
    'intro' => [
        'Sending a deck as a PowerPoint file gambles on the recipient having your fonts, your version and your willingness not to edit it. Sending it as a PDF removes all three risks, which is why board packs, investor materials, conference submissions and client proposals are almost always distributed as PDF.',
        'The conversion runs on the server using an office engine, since accurate slide rendering is beyond what a browser can do on its own. Your file is transmitted over HTTPS, converted under a random temporary filename, and deleted immediately after the PDF is returned.',
    ],
    'faqs' => [
        ['Will my fonts and layout be preserved?', 'Standard fonts and layouts render faithfully. Slides built on unusual embedded fonts or third-party add-ins can shift, so review the PDF before circulating it to a board or client.'],
        ['What happens to animations and transitions?', 'They are not carried over, because PDF has no concept of them. Animated builds are flattened to their final state, which is the standard outcome for any PowerPoint-to-PDF conversion.'],
        ['Is my presentation stored after conversion?', 'No. It is handled under a randomly generated temporary filename and deleted as soon as the finished PDF has been sent back to you.'],
        ['Can I convert both .ppt and .pptx?', 'Yes, both the legacy .ppt format and the modern .pptx format are accepted.'],
    ],
],

/* ----------------------------------------------------------- excel-to-pdf */
'excel-to-pdf' => [
    'processing' => 'server',
    'intro' => [
        'A spreadsheet sent as a spreadsheet is an invitation to change the numbers. Sent as a PDF, it is a record. That is why management accounts, invoices, quotations, budget submissions and audit schedules are converted to PDF before they go to a client, a lender or a regulator.',
        'Conversion runs on the server through an office engine, because page-break handling, print areas and column fitting need real spreadsheet rendering. Your workbook is sent over HTTPS, converted under a random temporary filename, and deleted immediately once the PDF has been returned.',
    ],
    'faqs' => [
        ['Why did my columns get cut off?', 'The PDF follows the print settings saved in the workbook. Set the print area, scaling and orientation in Excel first — Fit All Columns on One Page usually solves it — then convert.'],
        ['Are formulas preserved?', 'No, and that is the point of converting. The PDF captures calculated values as a fixed record, which is exactly what a recipient needs when the figures must not change after they are sent.'],
        ['Will every worksheet be included?', 'The conversion follows the workbook’s own print configuration. If you need specific sheets only, set that as the print selection before converting.'],
        ['Is my financial data retained on your server?', 'No. The workbook is written under a randomly generated temporary filename, converted, and deleted the moment the PDF has been streamed back. Nothing is stored or logged.'],
    ],
],

/* -------------------------------------------------------------------- edit */
'edit-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Most real-world PDF editing is small: adding a reference number a portal demands, writing in a date, marking a document with a note before passing it on, or completing a field on a form that was never made fillable. Very little of it justifies a subscription to a full PDF editor.',
        'This tool lets you click anywhere on a page preview to place text, choosing the size and colour, and then writes those additions into a real PDF text layer. Because the text is added as text rather than as a picture, the result stays selectable and searchable, and the rest of the document is untouched.',
    ],
    'faqs' => [
        ['Can I edit the text that is already in the PDF?', 'No. This tool adds new text on top of the existing pages rather than rewriting the original content. To change existing wording, recover the text with PDF to Word, edit it there, and convert back.'],
        ['Will the text I add be searchable?', 'Yes. Additions are written into the document as a real text layer, so they can be selected, copied and searched just like the original content.'],
        ['Can I add text to more than one page?', 'Yes. Select each page in turn and click to place text on it. Everything you have placed is written when you download the finished file.'],
        ['Is my document uploaded while I edit it?', 'No. The preview is rendered and the edits are applied entirely in your browser, so the document never leaves your device.'],
    ],
],

/* -------------------------------------------------------------------- sign */
'sign-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Electronic signatures have clear legal standing on both sides of the Atlantic. In the United States the ESIGN Act of 2000 and state UETA adoptions give them the same effect as ink for most commercial agreements, and in the United Kingdom the Law Commission confirmed in 2019 that a simple electronic signature can execute most contracts.',
        'This tool lets you draw your signature, choose the page and the position, and stamp it into the document — all inside your browser, so an unsigned agreement never gets uploaded anywhere. What it produces is a simple electronic signature: an image of your mark placed in the file. That is sufficient for a great many everyday agreements but is not the same thing as a certificate-based digital signature.',
    ],
    'faqs' => [
        ['Is a signature made this way legally binding?', 'In many everyday commercial contexts, yes. US law under ESIGN and UETA, and UK law as confirmed by the Law Commission in 2019, generally recognise simple electronic signatures for contract execution. Certain documents — deeds, wills, some property and cross-border instruments — carry additional formalities such as witnessing. When the stakes are significant, take legal advice on the requirements for your specific document.'],
        ['Is this a certificate-based digital signature?', 'No. This places a visual signature image into the document. It does not attach a cryptographic identity certificate and will not show as a verified digital signature in Adobe Acrobat. Where a counterparty or regulator specifically requires a qualified or advanced electronic signature, you need a certificate-based service instead.'],
        ['Where can I place the signature?', 'On any page in the document, positioned bottom left, bottom centre or bottom right. You choose both before applying it.'],
        ['Is my unsigned contract uploaded to sign it?', 'No. The signature is drawn and embedded entirely in your browser. The agreement never leaves your device, which is exactly the property you want when handling an unexecuted contract.'],
    ],
],

/* --------------------------------------------------------------- watermark */
'watermark-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Watermarks exist to stop a document being mistaken for something it is not. A draft contract circulating without a DRAFT stamp can be relied on as final. An internal analysis without a CONFIDENTIAL mark loses the visible signal that governs how it should be handled if it is forwarded or printed.',
        'This tool stamps text across every page of the document, and applies it in your browser so nothing is uploaded. Because the mark is written into the file itself, it survives forwarding, printing and re-saving — unlike a note in an email body, which disappears the moment the attachment is detached.',
    ],
    'faqs' => [
        ['Will the watermark appear on every page?', 'Yes. The text is applied across all pages, which is what you want for a draft or confidentiality marking — a stamp on the first page alone is easily lost when pages are extracted or forwarded.'],
        ['Can the watermark be removed by the recipient?', 'A determined recipient with the right tools can remove or obscure a visual watermark. It is a clear signal of status and handling expectations, not a security control. For actual protection use Protect PDF to encrypt the file, and Redact PDF to permanently remove sensitive content.'],
        ['Does watermarking affect the text underneath?', 'No. The original content stays intact and searchable. The watermark is drawn as an additional layer over the existing pages.'],
        ['Is my document uploaded to be watermarked?', 'No. The watermark is applied entirely in your browser, so confidential drafts are never transmitted.'],
    ],
],

/* ------------------------------------------------------------- html-to-pdf */
'html-to-pdf' => [
    'processing' => 'client',
    'intro' => [
        'When you need a PDF that renders HTML properly — with real CSS layout, web fonts and correct page breaks — the best engine available is the one already in your browser. It is the same renderer that produced the page on screen, so what you see is genuinely what you get.',
        'This tool takes your HTML, opens it in a new tab and triggers the browser’s own print dialog, where you choose Save as PDF. Nothing is uploaded and nothing is even processed by us; the conversion is performed by your browser locally, which is why the output fidelity is as high as it is.',
    ],
    'faqs' => [
        ['Why does this open a print dialog instead of downloading directly?', 'Because your browser’s print-to-PDF engine produces better output than any JavaScript library could. Choosing Save as PDF in that dialog gives you real CSS layout, correct page breaks and proper font rendering.'],
        ['Nothing happened when I clicked the button.', 'Your browser blocked the pop-up. Allow pop-ups for this site and try again — the tool needs to open a new tab to render your content before printing.'],
        ['Can I control page size and margins?', 'Yes, in the print dialog itself. Paper size, margins, orientation and whether background graphics are included are all set there before you save.'],
        ['Is my HTML sent to a server?', 'No. Your content is rendered locally by your own browser. It is never transmitted to us, which makes this safe for markup containing internal data or client information.'],
    ],
],

/* --------------------------------------------------------------- organize */
'organize-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Scanned documents rarely come out in the right order. A page feeds twice, a blank separator sheet ends up in the middle of the batch, an appendix scans before the section it belongs to, or a duplex feeder produces the reverse sides in the wrong sequence.',
        'This tool shows you every page as a thumbnail so you can reorder and delete visually rather than guessing at page numbers. The work happens in your browser and the original file is never modified, so you can experiment freely and only commit when the sequence is right.',
    ],
    'faqs' => [
        ['How do I reorder pages?', 'Work directly with the page thumbnails to put them in the sequence you want, and remove any you do not need, before downloading the rearranged document.'],
        ['Can I delete blank separator pages from a scan?', 'Yes, and it is one of the most common uses. Blank sheets and duplicate feeds are easy to spot in thumbnail view and can be dropped before you export.'],
        ['Does reorganising affect quality?', 'No. Pages are moved as they are, without re-rendering, so text stays searchable and images keep their original resolution.'],
        ['Is my original file changed?', 'No. The file on your device is untouched; the tool produces a new PDF with your arrangement, so you can try several orderings from the same source.'],
    ],
],

/* ------------------------------------------------------------- pdf-to-pdfa */
'pdf-to-pdfa' => [
    'processing' => 'server',
    'intro' => [
        'PDF/A is the ISO 19005 archival profile: a PDF that carries everything needed to reproduce itself, with fonts embedded and features that could break over time — external references, JavaScript, encryption — excluded. It exists so a document opened in twenty years still renders as it does today.',
        'It is the format national archives and records-management policies specify for long-term retention, and some regulatory and court filing systems require it explicitly. Conversion runs on the server because it needs Ghostscript; your file is sent over HTTPS, converted under a random temporary filename, and deleted immediately afterwards.',
    ],
    'faqs' => [
        ['What is PDF/A and when do I actually need it?', 'PDF/A is the ISO 19005 standard for long-term archiving. You need it when a records-retention policy, an archive, a regulator or a court filing system specifically asks for it. For ordinary sharing, a standard PDF is fine.'],
        ['What changes in my document when it is converted?', 'Fonts are embedded so the document no longer depends on what is installed on the reader’s machine, and features that are not archival-safe — such as embedded JavaScript and encryption — are removed. Visually the document should look the same.'],
        ['Can I password-protect a PDF/A file?', 'No, and this catches people out. PDF/A forbids encryption by design, because a document nobody can open in fifty years is not archived. If you need both, keep an encrypted working copy and a separate PDF/A copy for the archive.'],
        ['Why does this run on a server?', 'PDF/A conversion requires Ghostscript, which cannot run in a browser tab. If Ghostscript is not available on the host you will get a clear message explaining that, rather than a silent failure.'],
    ],
],

/* ----------------------------------------------------------------- repair */
'repair-pdf' => [
    'processing' => 'client',
    'intro' => [
        'A PDF that will not open is usually not destroyed. Far more often its cross-reference table — the index telling a reader where each object lives — has been damaged by an interrupted download, a failed email transfer, a crash during save or a faulty USB drive, while the page content sits intact behind it.',
        'This tool reads the document as tolerantly as it can, ignoring the broken index and invalid objects, then writes out a clean, correctly structured file. That recovers a large proportion of files that report as damaged. It runs in your browser, so a document you are anxious about is never uploaded anywhere.',
    ],
    'faqs' => [
        ['What kinds of damage can actually be repaired?', 'Broken cross-reference tables, invalid object references and malformed structure — the causes behind most "cannot open, file is damaged" errors. The tool rebuilds the document structure around the content that is still readable.'],
        ['What cannot be repaired?', 'Truncated downloads where page content is genuinely missing, files overwritten by other data, and documents encrypted with a password you do not have. If the bytes are gone, nothing can reconstruct them.'],
        ['Will I lose anything in the repair?', 'The tool reports how many pages it recovered so you can check against what you expected. Unrecoverable objects are dropped rather than allowed to break the file, so compare the output with the original before discarding your copy.'],
        ['Is my damaged file uploaded?', 'No. The repair runs entirely in your browser. A corrupted file that may hold your only copy of a document is never transmitted.'],
    ],
],

/* ------------------------------------------------------- page-numbers-pdf */
'page-numbers-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Pagination stops being cosmetic the moment a document is referred to in argument. Court bundles are expected to be paginated so that everyone in the room can be directed to the same page — English civil practice directions on bundles are explicit about it — and the same applies to tender submissions, board packs and any document with a cross-referenced index.',
        'This tool inserts page numbers at the position you choose across the document, in your browser, without uploading anything. Numbers are written as real text, so they remain selectable and searchable in the finished file.',
    ],
    'faqs' => [
        ['Where can I position the numbers?', 'You choose the placement, so numbering can avoid existing headers, footers or margin annotations already present in the document.'],
        ['Is this Bates numbering for litigation discovery?', 'No. This adds sequential page numbers. Bates numbering uses a fixed prefix with a zero-padded sequence continuing across a whole production set, and formal discovery productions in US litigation generally require dedicated Bates stamping software.'],
        ['Should I add page numbers before or after merging a bundle?', 'After. Number the assembled bundle so the sequence runs continuously from the first page to the last, which is what an index needs to point at.'],
        ['Are the numbers searchable?', 'Yes. They are added as real text rather than as an image, so they can be selected and searched in the finished document.'],
    ],
],

/* ------------------------------------------------------------ scan-to-pdf */
'scan-to-pdf' => [
    'processing' => 'client',
    'intro' => [
        'The scanner most people actually have is the camera in their phone. Capturing pages directly into a PDF removes the awkward middle step of photographing pages, transferring the images to a computer and assembling them into a document that a portal will accept.',
        'This tool uses your device camera to capture pages one after another and builds them into a single multi-page PDF. Everything happens in the browser, so pages of a contract, a passport or a medical record are never uploaded — which is precisely the property you want when scanning documents of that kind.',
    ],
    'faqs' => [
        ['Do I need to install an app?', 'No. The tool uses your browser’s camera access, so it works on iPhone and Android without installing anything. Your browser will ask for camera permission the first time.'],
        ['Can I capture several pages into one PDF?', 'Yes. Capture pages one after another and they are assembled into a single multi-page document.'],
        ['Are the captured pages searchable?', 'No. A camera capture is a photograph, so there is no text layer. Run the result through OCR PDF if you need the document to be searchable.'],
        ['Are my scanned pages uploaded anywhere?', 'No. Capture and assembly both happen on your device, so identity documents, medical records and contracts are never transmitted.'],
    ],
],

/* ---------------------------------------------------------------- ocr-pdf */
'ocr-pdf' => [
    'processing' => 'server',
    'intro' => [
        'A scanned PDF is a stack of photographs. It cannot be searched, its text cannot be copied, and a screen reader cannot read it aloud — which is why scanned documents fail accessibility requirements such as Section 508 in the US public sector and the UK public sector accessibility regulations. In litigation, unsearchable scans are also a serious obstacle during disclosure.',
        'OCR analyses the page images, recognises the characters and adds an invisible text layer behind them, so the document looks unchanged but becomes searchable and selectable. This runs on the server because it needs a full OCR engine; your file is sent over HTTPS, processed under a random temporary filename, and deleted immediately after.',
    ],
    'faqs' => [
        ['How accurate is OCR?', 'Clean, straight scans of printed text at reasonable resolution are recognised very accurately. Low-resolution scans, skewed pages, unusual fonts and handwriting are considerably less reliable. Always proofread the result before relying on it for anything consequential.'],
        ['Does OCR change how my document looks?', 'No. The original page images are kept and a text layer is added behind them, so the document appears identical while becoming searchable and selectable.'],
        ['Do I need OCR for a document I generated digitally?', 'No. A PDF exported from Word, Excel or a web page already contains a text layer. OCR is only needed for scans and photographs of pages. Try searching the document first — if search finds words, you do not need this.'],
        ['Is my document kept after OCR?', 'No. It is written under a randomly generated temporary filename, processed, and deleted as soon as the result is sent back. If you would prefer nothing left your device at all, the in-browser tools on this site handle most other PDF tasks with no upload.'],
    ],
],

/* ------------------------------------------------------------ compare-pdf */
'compare-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Comparing versions is a routine control before anything gets signed. A contract comes back from the other side’s counsel, a supplier reissues terms, or a policy is recirculated after review, and somebody has to establish what actually changed between the copy you approved and the copy in front of you.',
        'This tool renders both documents side by side, page for page, and lets you step through them together — so a moved clause, a changed figure or an inserted paragraph is visible in context. Both files are rendered in your browser, so two confidential drafts can be compared without either one being uploaded.',
    ],
    'faqs' => [
        ['Does this automatically highlight the differences?', 'No. It presents the two documents side by side, page for page, for visual comparison. It does not compute a word-level diff or mark changes for you, so review each page yourself rather than assuming an unmarked page is identical.'],
        ['What if the two documents have different page counts?', 'That is handled — the comparison runs to the longer document and shows clearly where one version has no corresponding page, which is itself often the change you are looking for.'],
        ['Are both documents uploaded to compare them?', 'No. Both are rendered entirely in your browser, so two confidential contract drafts can be compared without either leaving your device.'],
        ['Can I compare a scanned copy against a digital original?', 'You can view them side by side, which is useful for spotting an altered figure or a missing page. Bear in mind a scan will differ visually from a digital original throughout, so judge content rather than appearance.'],
    ],
],

/* ------------------------------------------------------------- redact-pdf */
'redact-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Redaction is the one PDF task where doing it wrong has consequences that make the news. Drawing a black rectangle over text in most PDF editors covers it visually while leaving the words underneath fully intact — anyone can select, copy or extract them. Courts, government departments and law firms have all disclosed protected information exactly this way.',
        'This tool avoids that failure mode by flattening each page to an image and then applying your black boxes, so the underlying text is genuinely gone from the file rather than merely hidden. That matters when you are meeting redaction duties such as the personal-identifier rules in US federal court filings under FRCP 5.2, or removing third-party personal data before responding to a subject access request under UK GDPR.',
    ],
    'faqs' => [
        ['Is the redacted content actually removed, or just covered up?', 'Actually removed. Each page is flattened to an image before the black boxes are applied, so the original text no longer exists in the output file and cannot be selected, copied or recovered by extraction.'],
        ['Why do so many redactions fail in other tools?', 'Because drawing a black shape in a normal PDF editor adds a graphic on top of the text without deleting the text. It looks redacted on screen but the words are still in the file and come straight out with copy-paste or a text-extraction tool. That is the mechanism behind most publicised redaction failures.'],
        ['What happens to the rest of my document?', 'It is flattened to images along with the redacted pages, so the whole document becomes non-searchable. That is the necessary trade-off for genuine removal. Keep your unredacted original safely, since the redaction cannot be undone.'],
        ['Is this suitable for court filings or subject access requests?', 'The removal is genuine, which is the technical requirement behind rules like FRCP 5.2 in US federal courts and data-minimisation duties under UK GDPR. Always verify the output yourself — search it, and try to copy text from a redacted area — and follow whatever review process your organisation or jurisdiction requires before disclosing.'],
    ],
],

/* --------------------------------------------------------------- crop-pdf */
'crop-pdf' => [
    'processing' => 'client',
    'intro' => [
        'Cropping fixes documents whose page boundaries are wrong for their purpose. Scans carry black borders and skewed edges from the platen. Documents converted from other formats arrive with excessive white margins. A slide deck printed to PDF wastes half the page on unusable space when it needs to go into a bundle.',
        'This tool trims the page boundaries to the region you select, applying the change in your browser without any upload. The content itself is not re-rendered, so text stays searchable and images keep their original resolution.',
    ],
    'faqs' => [
        ['Does cropping delete the content outside the crop area?', 'Cropping adjusts the visible page boundary. Content outside it is no longer displayed or printed, but for genuinely removing sensitive material you should use Redact PDF, which flattens the page so the content cannot be recovered.'],
        ['Can I crop only some pages?', 'Yes. Apply the crop across the whole document or to a selection, which is what you need when only the scanned section of a mixed document has borders to trim.'],
        ['Will cropping reduce my file size?', 'Usually only slightly, because the underlying page data is retained rather than re-encoded. If size is the goal, use Compress PDF instead.'],
        ['Is quality affected?', 'No. Cropping changes page boundaries rather than re-rendering content, so text stays searchable and images keep their original resolution.'],
    ],
],

/* -------------------------------------------------------------- pdf-forms */
'pdf-forms' => [
    'processing' => 'client',
    'intro' => [
        'A great many official forms are distributed as fillable PDFs — IRS forms such as the W-9 and W-8BEN, HMRC forms, immigration paperwork, HR onboarding packs and supplier onboarding documents. They are built on AcroForm fields, and filling them properly matters because a printed-and-handwritten copy is often rejected.',
        'This tool detects the fillable fields in your document, presents them as ordinary inputs, and writes your answers back into the PDF. You can optionally flatten the result, which converts the completed fields into fixed page content so the answers can no longer be edited by whoever receives it. Everything runs in your browser, so tax and identity forms are never uploaded.',
    ],
    'faqs' => [
        ['What does flattening do, and should I use it?', 'Flattening converts your filled-in answers into permanent page content so the fields are no longer editable. Use it when sending a completed form to a third party, so the values cannot be altered after you send them. Leave it off if you need to revise the form later.'],
        ['The tool says no fillable fields were found.', 'The document has no AcroForm fields — it is a flat PDF that only looks like a form. Use Edit PDF to place text on it manually, or Sign PDF if a signature is all that is needed.'],
        ['Are my tax or identity forms uploaded?', 'No. Field detection and filling both happen in your browser. A completed W-9, W-8BEN or HMRC form containing your tax identifiers never leaves your device.'],
        ['Can I fill checkboxes as well as text fields?', 'Yes. Text fields and checkboxes are both detected and presented as ordinary form inputs.'],
    ],
],

/* --------------------------------------------------------- pdf-summarizer */
'pdf-summarizer' => [
    'processing' => 'client',
    'intro' => [
        'Deciding whether a long document needs your full attention is a real task in itself. A sixty-page report, a supplier agreement or a policy consultation each need triage before they need reading, and the confidentiality of the material often rules out pasting it into an online AI service.',
        'This summariser is extractive: it reads the document’s text in your browser, scores each sentence by how much of the document’s significant vocabulary it carries, and returns the highest-scoring sentences in their original order. There is no AI service involved and nothing is transmitted — the sentences you get back are verbatim from your document rather than generated text, so nothing is invented.',
    ],
    'faqs' => [
        ['Does this send my document to an AI service?', 'No. The summary is computed entirely in your browser by statistical sentence scoring. No AI provider, no API key, and nothing transmitted — which is why it is safe for confidential material.'],
        ['How does the summarisation actually work?', 'It is extractive. Sentences are scored by how much of the document’s significant vocabulary they contain, and the strongest are returned in their original order. Nothing is rewritten or generated, so the summary cannot invent facts that are not in the source.'],
        ['Why does the summary read as disconnected sentences?', 'Because they are real sentences lifted from different parts of the document rather than newly written prose. That is the trade-off for a summary that is guaranteed faithful to the source and needs no server.'],
        ['Why did my scanned PDF produce nothing?', 'A scan holds no text layer to read. Run it through OCR PDF first to add one, then summarise.'],
    ],
],

/* ---------------------------------------------------------- translate-pdf */
'translate-pdf' => [
    'processing' => 'server',
    'intro' => [
        'Cross-border work generates a steady stream of documents in the wrong language: a supplier contract, a foreign tax certificate, a regulatory notice or an academic reference that has to be understood before it can be acted on.',
        'Machine translation requires a translation service, so this tool is server-backed rather than in-browser. If you would rather keep a sensitive document on your own device, a good alternative is to extract the text with PDF to Markdown — which runs entirely in your browser — and translate that text using whichever service you already trust.',
    ],
    'faqs' => [
        ['Can I translate a document without uploading it?', 'Yes, indirectly. Use PDF to Markdown to extract the text in your browser, then translate the extracted text with a service of your choosing. That keeps the original document on your device throughout.'],
        ['Will the layout be preserved?', 'Translated text rarely occupies the same space as the original — German and French commonly run longer than English — so layouts shift. Treat any machine-translated document as a working copy rather than a formatted replacement.'],
        ['Is machine translation acceptable for legal or official use?', 'Generally not. Courts, immigration authorities and many regulators require a certified human translation, often with a translator’s statement of accuracy. Machine translation is for understanding a document, not for filing one.'],
        ['What if the tool reports it is unavailable?', 'Translation depends on a translation service being configured on the host. If none is available you will see a clear message rather than a silent failure.'],
    ],
],

/* --------------------------------------------------------- pdf-to-markdown */
'pdf-to-markdown' => [
    'processing' => 'client',
    'intro' => [
        'Markdown is the format text goes into when it needs to be reusable: documentation, static sites, knowledge bases, issue trackers, note apps and, increasingly, as clean input for AI tools that work far better with plain text than with binary PDFs.',
        'This tool extracts the document’s text layer in your browser, preserves the line structure, and marks each page with a heading so you can trace any passage back to its source page. It is the most privacy-preserving way to get content out of a PDF on this site — nothing is uploaded, and the output is plain text you can inspect in full.',
    ],
    'faqs' => [
        ['What does the Markdown output look like?', 'Each page becomes a section under its own page heading, with the text below it and separators between pages, so you can always trace a passage back to the page it came from.'],
        ['Why is my scanned document empty?', 'Scanned pages are images and carry no text layer. Run OCR PDF first to add one, then extract.'],
        ['Are tables and columns preserved?', 'Not as structure. Extraction follows the text as the PDF stores it, so multi-column layouts and tables come out as flowing lines that usually need tidying. For tabular financial data, PDF to Excel is the better tool.'],
        ['Is this a good way to prepare a document for an AI tool?', 'Yes, and it is a common use. Extraction happens entirely in your browser, so you can see exactly what text you are about to share before any of it goes to a third-party service.'],
    ],
],

];
