<?php
/**
 * Order document e-mail (HTML). Override in yourtheme/woocommerce/emails/odocs-document.php.
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

<p><?php printf( esc_html__( 'Hi %s,', 'order-documents' ), esc_html( $order->get_billing_first_name() ) ); // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment ?></p>
<p>
<?php
printf(
	/* translators: 1: document title, 2: order number */
	esc_html__( 'Please find your %1$s for order #%2$s attached to this e-mail.', 'order-documents' ),
	esc_html( strtolower( $document->get_title() ) ),
	esc_html( $order->get_order_number() )
);
?>
</p>

<?php
do_action( 'woocommerce_email_order_details', $order, false, false, $email );

if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
