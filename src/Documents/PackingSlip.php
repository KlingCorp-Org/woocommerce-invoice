<?php
/**
 * Packing slip (no prices).
 *
 * @package OrderDocs
 */

namespace OrderDocs\Documents;

defined( 'ABSPATH' ) || exit;

class PackingSlip extends Document {

	public static function type(): string {
		return 'packing_slip';
	}

	public static function label(): string {
		return __( 'Packing slip', 'order-documents' );
	}

	public static function plural_label(): string {
		return __( 'Packing slips', 'order-documents' );
	}

	public static function icon(): string {
		return 'clipboard';
	}

	public static function default_enabled(): bool {
		return true;
	}

	public static function shows_prices(): bool {
		return false;
	}

	public static function legacy_slug(): ?string {
		return 'packing_slip';
	}

	protected static function field_defaults(): array {
		return array(
			'billing_address'  => 'never',
			'shipping_address' => 'always',
			'show_weight'      => 1,
		);
	}

}
