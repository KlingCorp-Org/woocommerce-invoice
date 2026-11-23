<?php
/**
 * Minimal template styles.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;
?>
.odocs-tpl-minimal .odocs-title { color: <?php echo $s['text_color']; ?>; font-weight: normal; letter-spacing: -0.01em; }
.odocs-tpl-minimal .odocs-heading { text-transform: none; letter-spacing: 0; font-weight: normal; }
.odocs-tpl-minimal .odocs-items th { font-weight: normal; color: <?php echo $s['muted_color']; ?>; border-bottom: 0.4pt solid <?php echo $s['muted_color']; ?>; }
.odocs-tpl-minimal .odocs-items td { padding-top: 2.2mm; padding-bottom: 2.2mm; }
.odocs-tpl-minimal .odocs-item-name { font-weight: normal; }
.odocs-tpl-minimal .odocs-totals tr.odocs-total-total td { border-top: 0.4pt solid <?php echo $s['text_color']; ?>; font-weight: normal; font-size: 1.25em; }
