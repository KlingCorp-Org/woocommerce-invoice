<?php
namespace OrderDocs\Tests;

use OrderDocs\Rendering\Layout;
use OrderDocs\Rendering\TemplateRegistry;
use OrderDocs\Documents\DocumentRegistry;

class LayoutTest extends TestCase {

	public function test_sanitize_drops_unknown_blocks_and_limits_columns(): void {
		$layout = Layout::sanitize(
			array(
				'rows' => array(
					array(
						'columns' => array(
							array( 'width' => 10, 'blocks' => array( array( 'type' => 'logo' ), array( 'type' => 'evil' ) ) ),
							array( 'width' => 10, 'blocks' => array() ),
							array( 'width' => 20, 'blocks' => array() ),
							array( 'width' => 20, 'blocks' => array() ),
						),
					),
				),
			)
		);
		$row = $layout['rows'][0];
		$this->assertCount( 3, $row['columns'] );
		$this->assertCount( 1, $row['columns'][0]['blocks'] );
		$this->assertEqualsWithDelta( 100, array_sum( wp_list_pluck( $row['columns'], 'width' ) ), 0.1 );
	}

	public function test_sanitize_strips_scripts_and_bad_colours(): void {
		$layout = Layout::sanitize(
			array(
				'rows' => array(
					array(
						'settings' => array( 'background' => 'javascript:alert(1)', 'text_color' => 'accent' ),
						'columns'  => array( array( 'width' => 100, 'blocks' => array( array( 'type' => 'custom_text', 'settings' => array( 'content' => '<b>Hi</b><script>x()</script>', 'font_size' => 999 ) ) ) ) ),
					),
				),
			)
		);
		$row   = $layout['rows'][0];
		$block = $row['columns'][0]['blocks'][0];
		$this->assertSame( '', $row['settings']['background'] );
		$this->assertSame( 'accent', $row['settings']['text_color'] );
		$this->assertStringNotContainsString( '<script', $block['settings']['content'] );
		$this->assertStringContainsString( '<b>Hi</b>', $block['settings']['content'] );
		$this->assertEquals( 40, $block['settings']['font_size'] );
	}

	public function test_every_template_has_a_valid_default_layout_for_every_type(): void {
		foreach ( array_keys( TemplateRegistry::all() ) as $template ) {
			foreach ( array_keys( DocumentRegistry::types() ) as $type ) {
				$layout = Layout::default_layout( $template, $type );
				$this->assertNotEmpty( $layout['rows'], "$template/$type" );
				$types = array();
				foreach ( $layout['rows'] as $row ) {
					foreach ( $row['columns'] as $column ) {
						foreach ( $column['blocks'] as $block ) {
							$types[] = $block['type'];
						}
					}
				}
				$this->assertContains( 'items_table', $types, "$template/$type has a products table" );
			}
		}
	}
}
