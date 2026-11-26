# Order Documents for WooCommerce

PDF invoices, packing slips, delivery notes, credit notes, proforma invoices, receipts and picking lists for WooCommerce. It includes six design templates, a visual layout editor, and a migration path from *WooCommerce PDF Invoices & Packing Slips*.

- **Requirements:** WordPress 6.4+, WooCommerce 8.5+, PHP 7.4+.
- **Order storage:** works with both HPOS and legacy order storage.
- **Languages:** English, Swedish and Danish.

## Installing

1. Go to **Plugins → Add New → Upload Plugin** and choose `order-documents-<version>.zip` (for example `order-documents-1.2.0.zip`). Then activate it.
2. Go to **WooCommerce → Order Documents**:
   - **General:** shop name, address, org.nr., logo, bank details, footer.
   - **Documents:** turn on the document types you want and set numbering, automatic e-mails and My Account buttons.
   - **Design:** pick a template, colours and font, or open the **visual editor**.

## Where the buttons are

| Place | What you get |
|---|---|
| Order edit screen, **Documents** box (sidebar) | One button per document type. Clicking a button creates the document (and its number) and opens the PDF. Each refund gets its own credit note button. |
| Order edit screen, **Document data** box | Number, date and notes of every document. **Edit** (saved with the order), **Regenerate PDF** and **Delete**. |
| Orders list, **Documents** column | Invoice number and date, plus an icon per document. |
| Orders list, **Bulk actions** | "PDF: Invoices", "PDF: Packing slips", "PDF: Picking lists" and so on. Opens one merged PDF. |
| Order actions dropdown | "E-mail invoice to customer" (one entry per type), "E-mail invoice to *address*" (when a fixed address is set), and "Regenerate archived document PDFs". |
| My Account (orders list and order page) | Download buttons, per the "My Account" setting of each type. |

## Document types

| Type | Notes |
|---|---|
| Invoice | Always numbered. Due date, delivery date, customer number, payment information text. |
| Packing slip | No prices. Shipping address first. Optional number. |
| Delivery note | Packing slip with prices. |
| Credit note | One per WooCommerce refund. Own number sequence, or shared with invoices. Shows the original invoice and the reason. Attached to the refund e-mail. |
| Proforma invoice | Optional number (`PF-` prefix). Disclaimer text. |
| Receipt | Paid orders only. Paid stamp and transaction ID. Can reuse the invoice number. |
| Picking list | One or many orders. Same products are added up, sorted by SKU, name, category or stock location. |

## Automatic e-mails

Nothing is e-mailed automatically until you turn it on. Each document type has three options under **Documents → [type] → Automation**:

- **Attach to e-mails:** adds the PDF to WooCommerce's own e-mails, such as *Completed order*.
- **E-mail to the customer when the order status becomes:** sends the PDF to the customer in its own e-mail (*Order document*). For example, send the delivery note when the order is completed.
- **E-mail to a fixed address when the order status becomes**, plus **Fixed e-mail address:** sends the PDF to the same address for every order, for example the company that handles your invoice payments (*Order document to a fixed address*).

Notes on the two status options:
- The document is created when needed, and each document is sent at most once per order to each recipient. Reaching the status again sends nothing; use the order action to send it again by hand.
- Sending runs in the background through Action Scheduler, usually within a minute, and each send is logged as an order note.
- Edit the subject, heading and text of both e-mails in **WooCommerce → Settings → E-mails**.
- Credit notes and picking lists don't have the status options.

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
- **Visual editor:** **Order Documents → Design → Open the visual editor**. It opens full screen (turn this off in the ⋯ menu). Pages are free-form: drag components anywhere on the page, resize them and let them overlap.
  - **Sections:** the page is a stack of sections.
    - **Free-form sections**, such as *Top* and *Below products*, have a fixed height. Every component in them keeps its exact position and size, so a long address never pushes anything down.
    - The **flowing section** holds the products table. It grows with the order and continues on the next page; the sections below it move down with it.
    - The **page footer** sits in the bottom margin of every page.
    - Add, rename, reorder or resize sections. Drag a section's bottom edge on the page to change its height.
  - **Page** (centre): the live preview, laid out like the PDF.
    - Click a component to select it, drag it to move it (also into another section), and drag its handles to resize it. Shift-click or drag across an empty area to select several.
    - Components snap to a grid (1–10 mm, in the grid menu) and to smart guides that line them up with each other and the section edges. Hold **Alt** while dragging to place freely, or set a component to *Free* placement. **Lock** stops accidental moves.
    - The preview uses the same fonts and line spacing as the PDF. An orange striped area shows where a component's content is taller than its box for the order you preview with (*Preview with* in the top bar); make the box taller, or choose *Cut off*.
  - **Layers** (left): the sections with their components, top layer first. Drag to change the layer order or move components between sections. The **Add** tab lists all components: drag one onto the page, or click it.
  - **Settings** (right): position and size in mm, layer order and alignment (one component or several), the component's own settings, or the section's settings. With nothing selected it shows the document style (colours, fonts, margins).
  - Undo/redo and keyboard shortcuts (press **?** in the editor for the list): arrow keys nudge, Ctrl+] / Ctrl+[ change the layer, Ctrl+D duplicates.
  - Layouts are saved per template and document type. **⋯ → Reset layout** brings the template's layout back.
  - **Layouts from 1.0.x** (rows and columns) keep printing exactly as before. When you open one in the editor, it is measured in the PDF and turned into free-form sections that print the same; nothing changes until you save.
- **Overriding files in a theme:** copy a file to `yourtheme/order-documents/{template}/…` or `yourtheme/order-documents/shared/…`. Examples are `blocks/totals.php`, `style.css.php` or `custom.css`.

## Developer hooks (selection)

- **Types and data:** `odocs_document_types`, `odocs_items`, `odocs_item_row`, `odocs_totals`, `odocs_vat_summary`, `odocs_data_rows`.
- **Output:** `odocs_layout`, `odocs_default_layout`, `odocs_block_types`, `odocs_css`, `odocs_html`, `odocs_preview_script`, `odocs_filename`, `odocs_formatted_number`, `odocs_placeholder_value`.
- **Layout format:** saved layouts are `{ version: 2, sections: [ { kind: canvas|flow|footer, height, items: [ { type, settings, x, y, w, h } ] } ] }` in mm (see `src/Rendering/Layout.php`). Version 1 (`rows`) layouts are still rendered.
- **Rules:** `odocs_attach_document`, `odocs_document_allowed`, `odocs_document_visible_to_customer`, `odocs_document_locale`, `odocs_templates`, `odocs_fonts`.
- **Actions:** `odocs_document_created`, `odocs_document_saved`, `odocs_document_deleted`, `odocs_before_document`, `odocs_after_document`.
- **Helper:** `odocs_get_document( 'invoice', $order )` returns a document object.

## Development

```bash
composer install            # Dompdf + PHPUnit
php /path/to/strauss-run.php && php bin/patch-vendor.php   # prefix Dompdf into vendor-prefixed/
npm install && npm run build                               # visual editor
WP_ROOT=/path/to/test-wordpress vendor/bin/phpunit         # tests against a WP+WooCommerce test site
bin/build.sh                                               # dist/order-documents-<version>.zip
```

- **Dompdf is renamespaced** to `OrderDocs\Vendor\Dompdf` with Strauss, so it never conflicts with another plugin's copy.
- **Translations** live in `languages/`. Regenerate the template with `wp i18n make-pot . languages/order-documents.pot --exclude=node_modules,vendor,vendor-prefixed,assets/editor/build,tests,bin`.

## Changelog

### 1.2.0
- Nothing is e-mailed automatically by default any more. New installs no longer attach the invoice and credit note to WooCommerce e-mails. Choices already saved on a store are kept.
- New: e-mail a document to the customer automatically when the order reaches a chosen status, for example the delivery note when the order is completed.
- New: e-mail a document to a fixed address when the order reaches a chosen status, for example every invoice to the company that handles your invoice payments. There is also an order action to send it by hand.
- Each automatic e-mail is sent once per document and logged as an order note. A document e-mail is no longer sent without its PDF.
- Fixed: the plugin's own e-mail no longer appears in the "Attach to e-mails" list.

### 1.1.3
- Removed the Norwegian KID payment reference (the setting, the `{{kid}}` placeholder and the KID row). The payment band uses the document number as the payment reference.
- The business register text example is now Swedish ("Godkänd för F-skatt").

### 1.1.2
- The zip file is named after the plugin version, for example `order-documents-1.1.2.zip`.
- Norwegian translation removed; the plugin ships in English, Swedish and Danish.

### 1.1.1
- Free-form visual editor: drag and drop components directly on the page, resize them with handles or exact sizes in mm, overlap them in layers, and snap them to a grid and smart guides (or place them freely). Components never push each other, so the layout stays the same whatever the length of an address.
- Pages are built from sections: free-form sections, a flowing section for the products table and a page footer on every page.
- New *Shape* component for coloured bands, boxes and frames behind other components.
- Multi-select with alignment and even spacing, locking, a layer list, a component library you can drag from, page-break guides and a warning when content is taller than its box.
- The preview now uses the PDF's fonts and line spacing, so what you see in the editor is what prints.
- Rows-and-columns layouts are converted automatically when opened in the editor and print the same as before.
- Fixed: the fixed page footer is repeated on every page of a long document again.
- A component's text colour now also applies to its title and headings.

### 1.0.2
- Redesigned the visual editor: full screen with structure, page and settings panes; the preview updates in place; on-page toolbar; searchable block inserter; row layout presets; undo/redo and keyboard shortcuts.
- New *Fresh* template with the *Shipping details*, *Quantity summary*, *Payment band* and *Signature fields* blocks.
- Fresh always shows a delivery address (falls back to the billing address).

### 1.0.0
- First release.
