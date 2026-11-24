/**
 * Block inserter: searchable grid of block types.
 */
import { useState } from '@wordpress/element';
import {
	Button,
	Dashicon,
	Dropdown,
	SearchControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { plus } from '@wordpress/icons';

function InserterContent( { blockTypes, used, onInsert, onClose } ) {
	const [ query, setQuery ] = useState( '' );
	const q = query.trim().toLowerCase();
	const items = Object.entries( blockTypes ).filter(
		( [ type, def ] ) =>
			! q || def.label.toLowerCase().includes( q ) || type.includes( q )
	);
	const pick = ( type ) => {
		onInsert( type );
		onClose();
	};
	return (
		<div className="odocs-inserter">
			<SearchControl
				__nextHasNoMarginBottom
				// eslint-disable-next-line jsx-a11y/no-autofocus
				autoFocus
				value={ query }
				onChange={ setQuery }
				placeholder={ __( 'Search blocks', 'order-documents' ) }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' && items.length ) {
						e.preventDefault();
						pick( items[ 0 ][ 0 ] );
					}
				} }
			/>
			<div
				className="odocs-inserter-grid"
				role="listbox"
				aria-label={ __( 'Blocks', 'order-documents' ) }
			>
				{ items.map( ( [ type, def ] ) => (
					<button
						key={ type }
						type="button"
						role="option"
						aria-selected="false"
						className="odocs-inserter-item"
						onClick={ () => pick( type ) }
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
						{ __( 'No blocks found.', 'order-documents' ) }
					</p>
				) }
			</div>
		</div>
	);
}

export default function Inserter( {
	blockTypes,
	layout,
	onInsert,
	renderToggle,
	placement = 'right-start',
} ) {
	const used = new Set(
		layout.rows.flatMap( ( row ) =>
			row.columns.flatMap( ( col ) => col.blocks.map( ( b ) => b.type ) )
		)
	);
	return (
		<Dropdown
			popoverProps={ { placement, offset: 8 } }
			contentClassName="odocs-popover"
			renderToggle={
				renderToggle ||
				( ( { isOpen, onToggle } ) => (
					<Button
						size="small"
						icon={ plus }
						label={ __( 'Add block', 'order-documents' ) }
						onClick={ onToggle }
						aria-expanded={ isOpen }
					/>
				) )
			}
			renderContent={ ( { onClose } ) => (
				<InserterContent
					blockTypes={ blockTypes }
					used={ used }
					onInsert={ onInsert }
					onClose={ onClose }
				/>
			) }
		/>
	);
}
