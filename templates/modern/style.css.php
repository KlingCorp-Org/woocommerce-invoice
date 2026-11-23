<?php
/**
 * Modern template styles.
 *
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

$accent = $s['accent_color'];
?>
.odocs-tpl-modern .odocs-heading { color: <?php echo $accent; ?>; }
.odocs-tpl-modern .odocs-row-band { border-radius: 1.5mm; }
.odocs-tpl-modern .odocs-row-band .odocs-title { letter-spacing: 0.06em; }
.odocs-tpl-modern .odocs-items th { padding-top: 2mm; padding-bottom: 2mm; }
.odocs-tpl-modern .odocs-totals tr.odocs-total-total td { background-color: <?php echo $accent; ?>; color: #ffffff; border-top: 0; padding: 2mm 1.6mm; }
.odocs-tpl-modern .odocs-shop-name { color: <?php echo $accent; ?>; }
