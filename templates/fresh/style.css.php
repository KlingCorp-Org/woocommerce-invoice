<?php
/**
 * Fresh template styles. Variables: $s (design), $palette, $heading_font, $margins, $mode.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$accent = $palette['accent'];
$light  = $palette['accent_light'];
$tint   = $palette['tint'];
$line   = $palette['line'];
$text   = $s['text_color'];
$muted  = $s['muted_color'];
$strong = '#3a4a3e' === strtolower( $text ) ? '#16241A' : $text;
$size   = (float) $s['font_size'];
?>
.odocs-tpl-fresh .odocs-title { font-family: <?php echo $heading_font; // phpcs:ignore ?>; font-size: 30pt; font-weight: bold; letter-spacing: 0.02em; line-height: 1; color: <?php echo $accent; ?>; }
.odocs-tpl-fresh .odocs-title-sub { font-size: <?php echo $size + 1.2; ?>pt; color: <?php echo $strong; ?>; margin-top: 2mm; }
.odocs-tpl-fresh .odocs-heading, .odocs-tpl-fresh .odocs-grid-label { font-size: 8.5pt; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: <?php echo $muted; ?>; }
.odocs-tpl-fresh .odocs-grid-value { font-size: <?php echo $size + 1.2; ?>pt; font-weight: bold; color: <?php echo $strong; ?>; }
.odocs-tpl-fresh .odocs-box { border-radius: 3.7mm; padding: 2.8mm 5.3mm; }
.odocs-tpl-fresh .odocs-box-border { border-color: <?php echo $line; ?>; }
.odocs-tpl-fresh .odocs-box-filled .odocs-heading { color: <?php echo $accent; ?>; }
.odocs-tpl-fresh .odocs-address, .odocs-tpl-fresh .odocs-shipping-info { line-height: 1.4; }
.odocs-tpl-fresh .odocs-box-filled .odocs-address { font-size: <?php echo $size + 1.2; ?>pt; color: <?php echo $strong; ?>; }
.odocs-tpl-fresh .odocs-address-name { color: <?php echo $strong; ?>; font-size: <?php echo $size + 1; ?>pt; }
.odocs-tpl-fresh .odocs-shipping-info strong, .odocs-tpl-fresh .odocs-text strong { color: <?php echo $strong; ?>; }

.odocs-tpl-fresh .odocs-items th { background-color: <?php echo $tint; ?>; color: <?php echo $accent; ?>; font-size: 8.5pt; font-weight: bold; letter-spacing: 0.06em; text-transform: uppercase; padding: 2.2mm 3.2mm; border: 0; }
.odocs-tpl-fresh .odocs-items th:first-child { border-top-left-radius: 2mm; border-bottom-left-radius: 2mm; }
.odocs-tpl-fresh .odocs-items th:last-child { border-top-right-radius: 2mm; border-bottom-right-radius: 2mm; }
.odocs-tpl-fresh .odocs-items td { padding: 1.2mm 3.2mm; border-bottom: 0.75pt solid <?php echo $line; ?>; vertical-align: middle; }
.odocs-tpl-fresh .odocs-items td.odocs-col-image, .odocs-tpl-fresh .odocs-items th.odocs-col-image { padding-right: 0; }
.odocs-tpl-fresh .odocs-items td.odocs-col-image img { border: 0.75pt solid <?php echo $line; ?>; border-radius: 2mm; background-color: #ffffff; }
.odocs-tpl-fresh .odocs-item-name { font-weight: bold; color: <?php echo $strong; ?>; }
.odocs-tpl-fresh .odocs-item-details, .odocs-tpl-fresh .odocs-item-meta { font-size: 8.5pt; line-height: 1.25; color: <?php echo $muted; ?>; }
.odocs-tpl-fresh .odocs-items td.odocs-col-total, .odocs-tpl-fresh .odocs-items td.odocs-col-total_incl, .odocs-tpl-fresh .odocs-items td.odocs-col-total_excl { font-weight: bold; color: <?php echo $strong; ?>; }
.odocs-tpl-fresh .odocs-check-box, .odocs-tpl-fresh .odocs-fill-line { border-color: #A8B5AA; }

.odocs-tpl-fresh .odocs-totals td { padding: 0.7mm 0; line-height: 1.3; }
.odocs-tpl-fresh .odocs-totals tr.odocs-total-total td { border-top: 1.5pt solid <?php echo $light; ?>; padding-top: 2.2mm; font-size: <?php echo $size + 1.2; ?>pt; color: <?php echo $strong; ?>; vertical-align: bottom; }
.odocs-tpl-fresh .odocs-totals tr.odocs-total-total td.odocs-value { font-family: <?php echo $heading_font; // phpcs:ignore ?>; font-size: 21pt; color: <?php echo $accent; ?>; line-height: 1; }

.odocs-tpl-fresh .odocs-payment-band .odocs-grid-label { color: <?php echo $text; ?>; }
.odocs-tpl-fresh .odocs-payment-band td, .odocs-tpl-fresh .odocs-block-document-data .odocs-data-grid tr:last-child td { padding-bottom: 0; }
.odocs-tpl-fresh .odocs-pay-amount { color: <?php echo $accent; ?>; }
.odocs-tpl-fresh .odocs-sign-fields .odocs-sign-label { color: <?php echo $text; ?>; }
.odocs-tpl-fresh .odocs-block-signature-fields .odocs-heading { color: <?php echo $text; ?>; }

.odocs-tpl-fresh .odocs-block-footer { font-size: 8.5pt; color: <?php echo $muted; ?>; }
.odocs-tpl-fresh .odocs-footer-border { border-top: 0.75pt solid <?php echo $line; ?>; padding-top: 3.5mm; }
.odocs-tpl-fresh .odocs-footer-columns strong { color: <?php echo $strong; ?>; }
.odocs-tpl-fresh .odocs-stamp { font-family: <?php echo $heading_font; // phpcs:ignore ?>; }
