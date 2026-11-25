<?php
namespace OrderDocs\Tests;

use OrderDocs\Documents\Number\NumberFormatter;
use OrderDocs\Documents\Number\Sequence;

class NumberingTest extends TestCase {

	public function test_format_with_prefix_suffix_padding_and_placeholders(): void {
		$date = new \DateTimeImmutable( '2026-03-05 12:00:00', wp_timezone() );
		$this->assertSame( 'F-2026-0042/A', NumberFormatter::format( 42, array( 'prefix' => 'F-{{year}}-', 'suffix' => '/A', 'padding' => 4 ), $date ) );
		$this->assertSame( '26030542', NumberFormatter::format( 42, array( 'prefix' => '{{y}}{{month}}{{day}}' ), $date ) );
		$this->assertSame( '7', NumberFormatter::format( 7, array() ) );
	}

	public function test_sequence_names_follow_reset_period(): void {
		$date = new \DateTimeImmutable( '2026-11-23 10:00:00', wp_timezone() );
		$this->assertSame( 'invoice', Sequence::name( 'invoice', 'never', $date ) );
		$this->assertSame( 'invoice_2026', Sequence::name( 'invoice', 'yearly', $date ) );
		$this->assertSame( 'invoice_2026_11', Sequence::name( 'invoice', 'monthly', $date ) );
	}

	public function test_claim_is_sequential_and_set_next_works(): void {
		$name = 'test_' . wp_generate_password( 6, false );
		$this->assertSame( 1, Sequence::claim( $name ) );
		$this->assertSame( 2, Sequence::claim( $name ) );
		Sequence::set_next( $name, 500 );
		$this->assertSame( 500, Sequence::peek( $name ) );
		$this->assertSame( 500, Sequence::claim( $name ) );
		$this->assertSame( 501, Sequence::peek( $name ) );
	}
}
