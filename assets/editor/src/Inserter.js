/**
 * Component library: a searchable grid of component types. Drag a tile onto the
 * page to place it exactly, or click it to add it to the selected section.
 */
import { useState } from '@wordpress/element';
import { Dashicon, SearchControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { allItems } from './utils';
import { DRAG_TYPE } from './Canvas';

export default function Library( { blockTypes, layout, onInsert } ) {
	const [ query, setQuery ] = useState( '' );
	const q = query.trim().toLowerCase();
	const used = new Set( allItems( layout ).map( ( b ) => b.type ) );
	const items = Object.entries( blockTypes ).filter(
		( [ type, def ] ) =>
			! q || def.label.toLowerCase().includes( q ) || type.includes( q )
	);
	return (
		<div className="odocs-inserter">
			<SearchControl
				__nextHasNoMarginBottom
				value={ query }
				onChange={ setQuery }
				placeholder={ __( 'Search components', 'order-documents' ) }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' && items.length ) {
						e.preventDefault();
						onInsert( items[ 0 ][ 0 ] );
					}
				} }
			/>
			<p className="odocs-help">
				{ __(
					'Drag a component onto the page, or click it to add it to the selected section.',
					'order-documents'
				) }
			</p>
			<div
				className="odocs-inserter-grid"
				role="listbox"
				aria-label={ __( 'Components', 'order-documents' ) }
			>
				{ items.map( ( [ type, def ] ) => (
					<button
						key={ type }
						type="button"
						role="option"
						aria-selected="false"
						className="odocs-inserter-item"
						draggable
						onDragStart={ ( e ) => {
							e.dataTransfer.effectAllowed = 'copy';
							e.dataTransfer.setData( DRAG_TYPE, type );
							e.dataTransfer.setData( 'text/plain', def.label );
							window.odocsDragType = type;
						} }
						onDragEnd={ () => {
							window.odocsDragType = null;
						} }
						onClick={ () => onInsert( type ) }
					>
						<Dashicon icon={ def.icon || 'block-default' } />
						<span>{ def.label }</span>
						{ used.has( type ) && (
							<i
								className="odocs-used"
								title={ __(
									'Already in this layout',
									'order-documents'
								) }
							/>
						) }
					</button>
				) ) }
				{ ! items.length && (
					<p className="odocs-help">
						{ __( 'No components found.', 'order-documents' ) }
					</p>
				) }
			</div>
		</div>
	);
}
