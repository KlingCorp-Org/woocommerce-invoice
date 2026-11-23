<?php
/**
 * Order document e-mail (plain text).
 *
 * @var WC_Order                      $order
 * @var \OrderDocs\Documents\Document $document
 * @var string                        $email_heading
 * @var string                        $additional_content
 * @var WC_Email                      $email
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";
/* translators: %s: customer first name */
echo esc_html( sprintf( __( 'Hi %s,', 'order-documents' ), $order->get_billing_first_name() ) ) . "\n\n";
/* translators: 1: document title, 2: order number */
echo esc_html( sprintf( __( 'Please find your %1$s for order #%2$s attached to this e-mail.', 'order-documents' ), strtolower( $document->get_title() ), $order->get_order_number() ) ) . "\n\n";
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
