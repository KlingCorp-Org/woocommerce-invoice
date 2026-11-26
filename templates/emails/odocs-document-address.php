<?php
/**
 * Order document to a fixed address (HTML). Override in yourtheme/woocommerce/emails/odocs-document-address.php.
 *
 * @var WC_Order                      $order
 * @var \OrderDocs\Documents\Document $document
 * @var string                        $email_heading
 * @var string                        $additional_content
 * @var WC_Email                      $email
 * @package OrderDocs
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
<?php
printf(
	/* translators: 1: document title and number, 2: order number, 3: shop name */
	esc_html__( '%1$s for order #%2$s from %3$s is attached to this e-mail.', 'order-documents' ),
	esc_html( trim( $document->get_title() . ' ' . $document->get_number() ) ),
	esc_html( $order->get_order_number() ),
	esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) )
);
?>
</p>

<?php
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_customer_details', $order, false, false, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
