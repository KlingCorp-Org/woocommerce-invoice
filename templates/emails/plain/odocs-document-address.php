<?php
/**
 * Order document to a fixed address (plain text).
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
echo esc_html(
	sprintf(
		/* translators: 1: document title and number, 2: order number, 3: shop name */
		__( '%1$s for order #%2$s from %3$s is attached to this e-mail.', 'order-documents' ),
		trim( $document->get_title() . ' ' . $document->get_number() ),
		$order->get_order_number(),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	)
) . "\n\n";

do_action( 'woocommerce_email_order_details', $order, false, true, $email );
do_action( 'woocommerce_email_customer_details', $order, false, true, $email );

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
