<?php
/**
 * Delivery note: a packing slip with prices.
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class DeliveryNote extends PackingSlip {

	public static function type(): string {
		return 'delivery_note';
	}

	public static function label(): string {
		return __( 'Delivery note', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Delivery notes', 'order-documents' );
	}

	public static function icon(): string {
		return 'car';
	}

	public static function default_enabled(): bool {
		return false;
	}

	public static function shows_prices(): bool {
		return true;
	}

	public static function legacy_slug(): ?string {
		return null;
	}

	protected static function field_defaults(): array {
		return array(
			'billing_address'  => 'always',
			'shipping_address' => 'always',
			'show_weight'      => 0,
		);
	}
}
