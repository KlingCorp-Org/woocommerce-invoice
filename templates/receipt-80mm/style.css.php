<?php
/**
 * Receipt 80 mm template styles.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;
?>
.odocs-tpl-receipt-80mm { font-size: <?php echo max( 6.5, min( 8.5, (float) $s['font_size'] - 1 ) ); ?>pt; line-height: 1.3; }
.odocs-tpl-receipt-80mm .odocs-title { color: <?php echo $s['text_color']; ?>; }
.odocs-tpl-receipt-80mm .odocs-items th { border-bottom: 0.5pt dashed <?php echo $s['text_color']; ?>; padding: 0.8mm 0.6mm; }
.odocs-tpl-receipt-80mm .odocs-items td { padding: 0.8mm 0.6mm; }
.odocs-tpl-receipt-80mm .odocs-totals td { padding: 0.4mm 0.6mm; }
.odocs-tpl-receipt-80mm .odocs-totals tr.odocs-total-total td { border-top: 0.75pt dashed <?php echo $s['text_color']; ?>; }
.odocs-tpl-receipt-80mm .odocs-data-table { width: 100%; }
.odocs-tpl-receipt-80mm .odocs-data-table td.odocs-value { text-align: right; }
.odocs-tpl-receipt-80mm .odocs-stamp { font-size: 13pt; }
