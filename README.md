# Order Documents for WooCommerce

PDF invoices, packing slips, delivery notes, credit notes, proforma invoices, receipts and picking lists for WooCommerce. It includes six design templates, a visual layout editor, and a migration path from *WooCommerce PDF Invoices & Packing Slips*.

- **Requirements:** WordPress 6.4+, WooCommerce 8.5+, PHP 7.4+.
- **Order storage:** works with both HPOS and legacy order storage.
- **Languages:** English, Norwegian Bokmål, Swedish and Danish.

## Installing

1. Go to **Plugins → Add New → Upload Plugin** and choose `order-documents.zip`. Then activate it.
2. Go to **WooCommerce → Order Documents**:
   - **General:** shop name, address, org.nr., logo, bank details, footer.
   - **Documents:** turn on the document types you want and set numbering, e-mail attachments and My Account buttons.
   - **Design:** pick a template, colours and font, or open the **visual editor**.

## Where the buttons are

| Place | What you get |
|---|---|
| Order edit screen, **Documents** box (sidebar) | One button per document type. Clicking a button creates the document (and its number) and opens the PDF. Each refund gets its own credit note button. |
| Order edit screen, **Document data** box | Number, date and notes of every document. **Edit** (saved with the order), **Regenerate PDF** and **Delete**. |
| Orders list, **Documents** column | Invoice number and date, plus an icon per document. |
| Orders list, **Bulk actions** | "PDF: Invoices", "PDF: Packing slips", "PDF: Picking lists" and so on. Opens one merged PDF. |
| Order actions dropdown | "E-mail invoice to customer" (one entry per type), and "Regenerate archived document PDFs". |
| My Account (orders list and order page) | Download buttons, per the "My Account" setting of each type. |

## Document types

| Type | Notes |
|---|---|
| Invoice | Always numbered. Due date, delivery date, customer number, KID (MOD10/MOD11), payment information text. |
| Packing slip | No prices. Shipping address first. Optional number. |
| Delivery note | Packing slip with prices. |
| Credit note | One per WooCommerce refund. Own number sequence, or shared with invoices. Shows the original invoice and the reason. Attached to the refund e-mail. |
| Proforma invoice | Optional number (`PF-` prefix). Disclaimer text. |
| Receipt | Paid orders only. Paid stamp and transaction ID. Can reuse the invoice number. |
| Picking list | One or many orders. Same products are added up, sorted by SKU, name, category or stock location. |

## Numbering

- Numbers come from a database sequence that is claimed atomically. Parallel orders never get the same number, and no number is skipped.
- Creating a document is locked per order, so a double click doesn't use two numbers.
- Sequences can restart every year or month. The prefix and suffix accept `{{year}}`, `{{month}}`, `{{day}}`, `{{order_year}}`, `{{order_month}}` and `{{order_number}}`.
- **Tools → Number sequences** shows every sequence and lets you change the next number.

## Archive

With **Keep an archived copy** on (the default for invoices and credit notes), the first PDF is stored in `wp-content/uploads/order-documents/archive/`. That copy is served from then on, so a document never changes after it is issued. **Regenerate PDF** discards the copy.

The storage folder contains an `.htaccess` that blocks web access on Apache. **On nginx**, add:

```nginx
location ^~ /wp-content/uploads/order-documents/ { deny all; }
```

## Switching from WooCommerce PDF Invoices & Packing Slips

1. Install and activate this plugin. Orders that already have a WPO invoice or credit note keep its number and date right away; the old data is read directly.
2. Go to **WooCommerce → Order Documents → Tools** and run the three steps:
   1. **Continue the number sequences.** The proposed next number is the highest of WPO's own counter and the highest number in use. With a yearly reset, only this year's documents count.
   2. **Import settings:** shop details, logo, footer, number format (`[invoice_year]` becomes `{{year}}`), e-mail attachments, My Account rules and due days. Untick anything you don't want.
   3. **Copy document data.** This runs in the background, through WooCommerce's Action Scheduler.
3. **Deactivate WPO right after step 1.** While both plugins are active, both attach PDFs to e-mails and both hand out numbers. The WPO data stays in the database untouched, so you can switch back if needed.

Try the import on a staging copy of the shop first.

## Templates and the editor

- **Templates:** Classic, Modern, Minimal, Business, Compact, Fresh and Receipt 80 mm. Each document type can use its own template (**Documents → [type] → Template**).
- **Fresh** is based on the Goggo Grossist designs. It has Nunito text with Fredoka headings, rounded and tinted boxes, product images, a large total, a payment band on invoices, and *Ordered / Delivered / Done* columns with signature fields on packing slips and delivery notes.
  - Its green palette and fonts apply while the Design settings are left at their defaults; set your own accent colour or font there to override them.
  - It brings new blocks that every template can use: *Shipping details*, *Quantity summary*, *Payment band* and *Signature fields*. It also adds a *Box* option on every block, a column layout for *Document data*, compact addresses, a three-column footer and a *Delivered* column.
- **Visual editor:** **Order Documents → Design → Open the visual editor**.
  - Build the page from rows of 1–3 columns and drag blocks between them.
  - Click a block in the preview to edit it.
  - The preview is rendered by the same code that makes the PDF.
  - Layouts are saved per template and document type. **Reset to default** brings the template's layout back.
- **Overriding files in a theme:** copy a file to `yourtheme/order-documents/{template}/…` or `yourtheme/order-documents/shared/…`. Examples are `blocks/totals.php`, `style.css.php` or `custom.css`.

## Developer hooks (selection)

- **Types and data:** `odocs_document_types`, `odocs_items`, `odocs_item_row`, `odocs_totals`, `odocs_vat_summary`, `odocs_data_rows`.
- **Output:** `odocs_layout`, `odocs_default_layout`, `odocs_block_types`, `odocs_css`, `odocs_html`, `odocs_filename`, `odocs_formatted_number`, `odocs_placeholder_value`.
- **Rules:** `odocs_attach_document`, `odocs_document_allowed`, `odocs_document_visible_to_customer`, `odocs_document_locale`, `odocs_templates`, `odocs_fonts`.
- **Actions:** `odocs_document_created`, `odocs_document_saved`, `odocs_document_deleted`, `odocs_before_document`, `odocs_after_document`.
- **Helper:** `odocs_get_document( 'invoice', $order )` returns a document object.

## Development

```bash
composer install            # Dompdf + PHPUnit
php /path/to/strauss-run.php && php bin/patch-vendor.php   # prefix Dompdf into vendor-prefixed/
npm install && npm run build                               # visual editor
WP_ROOT=/path/to/test-wordpress vendor/bin/phpunit         # tests against a WP+WooCommerce test site
bin/build.sh                                               # dist/order-documents.zip
```

- **Dompdf is renamespaced** to `OrderDocs\Vendor\Dompdf` with Strauss, so it never conflicts with another plugin's copy.
- **Translations** live in `languages/`. Regenerate the template with `wp i18n make-pot . languages/order-documents.pot --exclude=node_modules,vendor,vendor-prefixed,assets/editor/build,tests,bin`.
