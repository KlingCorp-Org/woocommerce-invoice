<?php
/**
 * Compact template styles.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;
?>
.odocs-tpl-compact { font-size: <?php echo max( 6, (float) $s['font_size'] - 1 ); ?>pt; line-height: 1.25; }
.odocs-tpl-compact .odocs-items th, .odocs-tpl-compact .odocs-items td { padding: 0.9mm 1mm; }
.odocs-tpl-compact .odocs-totals td { padding: 0.5mm 1mm; }
.odocs-tpl-compact .odocs-heading { margin-bottom: 0.6mm; }
.odocs-tpl-compact .odocs-shop-name { font-size: 1.05em; }
