<?php
/**
 * Business template styles.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$accent = $s['accent_color'];
?>
.odocs-tpl-business .odocs-title { color: <?php echo $s['text_color']; ?>; }
.odocs-tpl-business .odocs-heading { color: <?php echo $accent; ?>; border-bottom: 0.5pt solid #cbd5e1; padding-bottom: 0.8mm; }
.odocs-tpl-business .odocs-data-boxed { background-color: #f8fafc; }
.odocs-tpl-business .odocs-row-border { border-color: #cbd5e1; }
.odocs-tpl-business .odocs-totals td { border: 0.6pt solid #cbd5e1; }
.odocs-tpl-business .odocs-totals tr.odocs-total-total td { background-color: #f3f4f6; border-top: 1pt solid <?php echo $accent; ?>; }
