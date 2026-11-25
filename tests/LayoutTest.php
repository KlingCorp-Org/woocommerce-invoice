<?php
namespace OrderDocs\Tests;

use OrderDocs\Rendering\Layout;
use OrderDocs\Rendering\Renderer;
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

	private function free_layout(): array {
		return array(
			'version'  => 2,
			'sections' => array(
				array(
					'id'     => 'top',
					'kind'   => 'canvas',
					'height' => 60,
					'items'  => array(
						array( 'id' => 'bg', 'type' => 'shape', 'x' => -14, 'y' => 0, 'w' => 210, 'h' => 30, 'settings' => array( 'fill' => 'accent' ) ),
						array( 'id' => 'title', 'type' => 'document_title', 'x' => 120.5, 'y' => 8, 'w' => 60, 'h' => 12, 'settings' => array( 'text_color' => '#ffffff' ) ),
						array( 'id' => 'note', 'type' => 'custom_text', 'x' => 0, 'y' => 35, 'w' => 60, 'h' => 20, 'overflow' => 'clip', 'settings' => array( 'content' => 'Hello' ) ),
					),
				),
				array(
					'id'    => 'products',
					'kind'  => 'flow',
					'items' => array( array( 'id' => 'table', 'type' => 'items_table', 'x' => 5, 'w' => 170, 'settings' => array( 'columns' => Layout::columns( array( 'description', 'quantity', 'total' ) ) ) ) ),
				),
				array(
					'id'     => 'foot',
					'kind'   => 'footer',
					'height' => 18,
					'items'  => array( array( 'id' => 'f', 'type' => 'footer', 'x' => 0, 'y' => 4, 'w' => 182, 'h' => 10, 'settings' => array( 'content' => 'Thanks' ) ) ),
				),
			),
		);
	}

	public function test_free_form_layout_is_sanitized(): void {
		$layout                                   = $this->free_layout();
		$layout['sections'][0]['items'][1]['x']   = 9999;
		$layout['sections'][0]['items'][1]['w']   = 'abc';
		$layout['sections'][0]['items'][2]['type'] = 'evil';
		$layout['sections'][1]['kind']            = 'weird';
		$layout['sections'][]                     = array( 'kind' => 'footer', 'items' => array() );
		$clean = Layout::sanitize( $layout, 'invoice' );

		$this->assertSame( 2, $clean['version'] );
		$this->assertTrue( Layout::is_v2( $clean ) );
		$this->assertCount( 4, $clean['sections'] );
		$this->assertCount( 2, $clean['sections'][0]['items'], 'unknown component dropped' );
		$this->assertEquals( 400, $clean['sections'][0]['items'][1]['x'], 'x clamped' );
		$this->assertEquals( 2, $clean['sections'][0]['items'][1]['w'], 'bad width falls back to the minimum' );
		$this->assertSame( 'canvas', $clean['sections'][1]['kind'], 'unknown kind becomes free-form' );
		$this->assertSame( 'footer', $clean['sections'][2]['kind'] );
		$this->assertSame( 'canvas', $clean['sections'][3]['kind'], 'only one page footer' );
		$this->assertSame( 1, $clean['sections'][0]['items'][0]['snap'] );
		$this->assertSame( 'grow', $clean['sections'][0]['items'][0]['overflow'] );
	}

	public function test_rows_layouts_are_still_sanitized_as_rows(): void {
		$clean = Layout::sanitize( Layout::default_layout( 'classic', 'invoice' ), 'invoice' );
		$this->assertSame( 1, $clean['version'] );
		$this->assertArrayHasKey( 'rows', $clean );
		$this->assertFalse( Layout::is_v2( $clean ) );
	}

	public function test_free_form_layout_renders_positions_and_layers(): void {
		$order    = $this->order();
		$document = DocumentRegistry::make( 'invoice', $order );
		$layout   = $this->free_layout();

		$html = ( new Renderer( 'pdf', array( 'template' => 'classic', 'layout' => $layout ) ) )->render( array( $document ) );
		$this->assertStringContainsString( 'position:relative;height:60mm;page-break-inside:avoid', $html );
		$this->assertStringContainsString( 'position:absolute;left:120.5mm;top:8mm;width:60mm;z-index:2;min-height:12mm', $html );
		$this->assertStringContainsString( 'z-index:3;height:20mm;overflow:hidden', $html, 'clipped component' );
		$this->assertStringContainsString( 'margin-left:5mm;width:170mm', $html, 'flowing component' );
		$this->assertMatchesRegularExpression( '/<body[^>]*><div class="odocs-section odocs-section-footer" style="position:fixed;left:0;right:0;bottom:-[0-9.]+mm;height:18mm"/', $html, 'page footer repeats on every page and comes first' );
		$this->assertStringContainsString( 'odocs-colored', $html );
		$this->assertStringNotContainsString( 'data-odocs-item', $html );

		$preview = ( new Renderer( 'preview', array( 'template' => 'classic', 'layout' => $layout ) ) )->render( array( $document ) );
		$this->assertStringContainsString( 'data-odocs-item="title"', $preview );
		$this->assertStringContainsString( 'data-odocs-section="foot"', $preview );
		$this->assertStringContainsString( 'position:absolute;left:', $preview, 'footer at the bottom of the sheet in the preview' );

		$bulk = ( new Renderer( 'pdf', array( 'template' => 'classic', 'layout' => $layout ) ) )->render( array( $document, DocumentRegistry::make( 'invoice', $this->order() ) ) );
		$this->assertStringNotContainsString( 'position:fixed', $bulk, 'several documents: no repeating footer' );
		$this->assertSame( 2, substr_count( $bulk, 'class="odocs-section odocs-section-footer"' ) );
	}

	public function test_footer_section_reserves_the_bottom_margin(): void {
		$document = DocumentRegistry::make( 'invoice', $this->order() );
		$layout   = $this->free_layout();
		$layout['sections'][2]['height'] = 55;
		$renderer = new Renderer( 'pdf', array( 'template' => 'classic', 'layout' => $layout ) );
		$this->assertEquals( 55, $renderer->margins( $document )[2] );
	}

	public function test_free_form_pdf_renders(): void {
		$document = DocumentRegistry::make( 'invoice', $this->order() );
		$pdf      = \OrderDocs\Rendering\PdfGenerator::documents( array( $document ), array( 'template' => 'fresh', 'layout' => $this->free_layout() ) );
		$this->assertStringStartsWith( '%PDF', $pdf );
	}
}
