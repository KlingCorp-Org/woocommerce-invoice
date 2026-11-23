<?php
/**
 * Shared base styles. Variables: $s (design settings), $margins, $paper, $font, $table_style, $mode, $r.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$accent = $s['accent_color'];
$text   = $s['text_color'];
$muted  = $s['muted_color'];
$size   = (float) $s['font_size'];
list( $mt, $mr, $mb, $ml ) = $margins;
?>
@page { margin: <?php echo (float) $mt; ?>mm <?php echo (float) $mr; ?>mm <?php echo (float) $mb; ?>mm <?php echo (float) $ml; ?>mm; }
body { margin: 0; padding: 0; }
body { font-family: <?php echo $font; // phpcs:ignore ?>; font-size: <?php echo $size; ?>pt; color: <?php echo $text; ?>; line-height: 1.35; }
.odocs-page-break { page-break-before: always; }
table { border-collapse: collapse; }
.odocs-row { width: 100%; }
.odocs-row-table { width: 100%; border-collapse: collapse; }
.odocs-col { padding: 0; }
.odocs-row-border { border: 0.75pt solid #d1d5db; }
.odocs-block { page-break-inside: auto; }
.odocs-block p { margin: 0 0 1.5mm 0; }
.odocs-heading { font-size: 0.82em; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; color: <?php echo $muted; ?>; margin-bottom: 1.2mm; }
.odocs-row-band .odocs-heading, .odocs-row-band .odocs-muted, .odocs-row-band .odocs-title { color: inherit; }
.odocs-title { font-size: 18pt; font-weight: bold; color: <?php echo $accent; ?>; line-height: 1.15; }
.odocs-shop-name { font-weight: bold; font-size: 1.15em; }
.odocs-muted { color: <?php echo $muted; ?>; }
.odocs-logo-text { font-size: 16pt; font-weight: bold; color: <?php echo $accent; ?>; }
.odocs-align-right table.odocs-data-table, .odocs-align-right table.odocs-totals { margin-left: auto; }
.odocs-align-center table.odocs-data-table { margin-left: auto; margin-right: auto; }
.odocs-data-table td { padding: 0.35mm 0; vertical-align: top; }
.odocs-data-table td.odocs-label { color: <?php echo $muted; ?>; padding-right: 3mm; white-space: nowrap; }
.odocs-data-table td.odocs-value { white-space: nowrap; }
.odocs-data-boxed { border: 0.75pt solid #d1d5db; padding: 2mm; }
.odocs-items { width: 100%; border-collapse: collapse; }
.odocs-items th { font-weight: bold; padding: 1.6mm 1.4mm; text-align: left; font-size: 0.92em; }
.odocs-items td { padding: 1.6mm 1.4mm; vertical-align: top; }
.odocs-items .odocs-c-right { text-align: right; }
.odocs-items .odocs-c-center { text-align: center; }
.odocs-items .odocs-c-left { text-align: left; }
.odocs-items tr { page-break-inside: avoid; }
.odocs-item-name { font-weight: bold; }
.odocs-item-details, .odocs-item-meta { font-size: 0.85em; color: <?php echo $muted; ?>; }
.odocs-meta-key { font-weight: bold; }
.odocs-checkbox { font-family: "DejaVu Sans", sans-serif; font-size: 1.4em; line-height: 1; }
<?php if ( 'lined' === $table_style ) : ?>
.odocs-items th { border-bottom: 1.2pt solid <?php echo $accent; ?>; }
.odocs-items td { border-bottom: 0.5pt solid #e5e7eb; }
<?php elseif ( 'striped' === $table_style ) : ?>
.odocs-items th { background-color: <?php echo $accent; ?>; color: #ffffff; }
.odocs-items tr.odocs-even td { background-color: #f3f4f6; }
<?php elseif ( 'boxed' === $table_style ) : ?>
.odocs-items th { background-color: #f3f4f6; border: 0.6pt solid #cbd5e1; }
.odocs-items td { border: 0.6pt solid #cbd5e1; }
<?php else : ?>
.odocs-items th { border-bottom: 0.75pt solid <?php echo $text; ?>; }
<?php endif; ?>
.odocs-totals { width: 100%; border-collapse: collapse; }
.odocs-totals td { padding: 0.9mm 1.4mm; }
.odocs-totals td.odocs-value { text-align: right; white-space: nowrap; }
.odocs-totals tr.odocs-total-total td { font-weight: bold; font-size: 1.12em; border-top: 1pt solid <?php echo $text; ?>; padding-top: 1.6mm; }
.odocs-totals tr.odocs-total-tax-included td { font-size: 0.88em; color: <?php echo $muted; ?>; }
.odocs-vat { border-collapse: collapse; width: 100%; font-size: 0.9em; }
.odocs-vat th { text-align: right; font-weight: bold; padding: 0.6mm 1.2mm; border-bottom: 0.5pt solid #d1d5db; }
.odocs-vat td { text-align: right; padding: 0.6mm 1.2mm; }
.odocs-vat th:first-child, .odocs-vat td:first-child { text-align: left; }
.odocs-block-footer { font-size: 0.82em; color: <?php echo $muted; ?>; }
.odocs-footer-border { border-top: 0.5pt solid #d1d5db; padding-top: 2mm; }
.odocs-fixed-footer { position: fixed; left: 0; right: 0; bottom: -<?php echo max( 2, (float) $mb - 6 ); ?>mm; }
.odocs-fixed-footer .odocs-block { margin-bottom: 0 !important; }
.odocs-stamp { display: inline-block; font-weight: bold; font-size: 16pt; letter-spacing: 0.1em; padding: 1mm 4mm; border: 2pt solid; border-radius: 2mm; }
.odocs-signature td { padding-top: 10mm; }
.odocs-signature .odocs-sign-line { border-top: 0.75pt solid <?php echo $text; ?>; padding-top: 1mm; font-size: 0.85em; color: <?php echo $muted; ?>; }
.odocs-box { padding: 3mm 4.5mm; border-radius: 3.5mm; page-break-inside: avoid; }
.odocs-sign-fields, .odocs-payment-band, .odocs-footer-columns { page-break-inside: avoid; }
.odocs-box .odocs-heading { margin-bottom: 1mm; }
.odocs-title-sub { font-weight: bold; margin-top: 1.5mm; font-size: 1.1em; }
.odocs-data-grid { width: 100%; border-collapse: collapse; }
.odocs-data-grid td { vertical-align: top; padding: 0 3mm 1.5mm 0; }
.odocs-grid-label { font-size: 0.85em; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: <?php echo $muted; ?>; margin-bottom: 0.8mm; }
.odocs-grid-value { font-weight: bold; }
.odocs-check-box { display: inline-block; width: 4.2mm; height: 4.2mm; border: 1.4pt solid #9ca3af; border-radius: 1mm; }
.odocs-fill-line { display: inline-block; width: 13mm; height: 4mm; border-bottom: 0.75pt solid #9ca3af; }
.odocs-footer-columns { width: 100%; border-collapse: collapse; }
.odocs-footer-columns td { width: 33.33%; vertical-align: top; padding-right: 4mm; line-height: 1.5; }
.odocs-footer-extra { margin-top: 1.5mm; }
.odocs-sign-fields { width: 100%; border-collapse: collapse; }
.odocs-sign-fields td { vertical-align: top; }
.odocs-sign-space { height: 7mm; border-bottom: 0.75pt solid <?php echo $muted; ?>; }
.odocs-sign-label { font-size: 0.85em; font-weight: bold; margin: 1.2mm 0; }
.odocs-shipping-info { line-height: 1.55; }
<?php if ( 'pdf' !== $mode ) : ?>
body { background: #e5e7eb; padding: 8mm 0; }
.odocs-document { margin: 0 auto 8mm auto; }
.odocs-sheet { background: #ffffff; width: <?php echo (float) $paper['width_mm']; ?>mm; min-height: <?php echo (float) $paper['height_mm']; ?>mm; padding: <?php echo (float) $mt; ?>mm <?php echo (float) $mr; ?>mm <?php echo (float) $mb; ?>mm <?php echo (float) $ml; ?>mm; margin: 0 auto; box-sizing: border-box; box-shadow: 0 1px 6px rgba(0,0,0,0.18); }
<?php endif; ?>
<?php if ( 'preview' === $mode ) : ?>
[data-odocs-block] { cursor: pointer; }
[data-odocs-block]:hover { outline: 1px dashed #2271b1; outline-offset: 1px; }
.odocs-selected, .odocs-selected:hover { outline: 2px solid #2271b1; outline-offset: 1px; }
.odocs-block-empty { border: 1px dashed #c3c4c7; color: #8c8f94; padding: 1.5mm 2mm; font-size: 8pt; margin-bottom: 2mm; font-family: sans-serif; }
<?php endif; ?>
