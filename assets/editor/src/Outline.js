/**
 * Layout outline: rows, columns and blocks with native drag and drop.
 */
import { useState } from '@wordpress/element';
import { Button, Dropdown, MenuGroup, MenuItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { moveBlock, setColumnCount, newRow, clone, uid, blockDefaults } from './utils';

function AddBlockMenu( { blockTypes, onAdd } ) {
	return (
		<Dropdown
			popoverProps={ { placement: 'bottom-start' } }
			renderToggle={ ( { isOpen, onToggle } ) => (
				<Button
					className="odocs-add-block"
					size="small"
					variant="tertiary"
					icon="plus"
					onClick={ onToggle }
					aria-expanded={ isOpen }
				>
					{ __( 'Add block', 'order-documents' ) }
				</Button>
			) }
			renderContent={ ( { onClose } ) => (
				<MenuGroup className="odocs-block-menu">
					{ Object.entries( blockTypes ).map( ( [ type, def ] ) => (
						<MenuItem
							key={ type }
							icon={ def.icon }
							onClick={ () => {
								onAdd( type );
								onClose();
							} }
						>
							{ def.label }
						</MenuItem>
					) ) }
				</MenuGroup>
			) }
		/>
	);
}

export default function Outline( { layout, setLayout, config, selected, onSelect } ) {
	const [ drag, setDrag ] = useState( null );
	const [ over, setOver ] = useState( null );
	const { blockTypes, columnTypes } = config;

	const updateRow = ( r, fn ) => {
		const next = clone( layout );
		fn( next.rows[ r ], next );
		setLayout( next );
	};

	const onDropBlock = ( e, r, c, index ) => {
		e.preventDefault();
		e.stopPropagation();
		if ( drag?.kind === 'block' ) {
			setLayout( moveBlock( layout, drag.id, r, c, index ) );
		}
		setDrag( null );
		setOver( null );
	};

	const onDropRow = ( e, index ) => {
		if ( drag?.kind !== 'row' ) {
			return;
		}
		e.preventDefault();
		const next = clone( layout );
		const [ row ] = next.rows.splice( drag.index, 1 );
		next.rows.splice( drag.index < index ? index - 1 : index, 0, row );
		setLayout( next );
		setDrag( null );
		setOver( null );
	};

	const addBlock = ( r, c, type ) => {
		const block = { id: uid( 'b' ), type, settings: blockDefaults( blockTypes, columnTypes, type ) };
		updateRow( r, ( row ) => row.columns[ c ].blocks.push( block ) );
		onSelect( { kind: 'block', id: block.id } );
	};

	return (
		<div className="odocs-outline">
			{ layout.rows.map( ( row, r ) => {
				const rowSelected = selected?.kind === 'row' && selected.id === row.id;
				return (
					<div
						key={ row.id }
						className={ 'odocs-outline-row' + ( rowSelected ? ' is-selected' : '' ) + ( over === 'row-' + r ? ' is-drop-target' : '' ) }
						onDragOver={ ( e ) => {
							if ( drag?.kind === 'row' ) {
								e.preventDefault();
								setOver( 'row-' + r );
							}
						} }
						onDrop={ ( e ) => onDropRow( e, r ) }
					>
						<div className="odocs-row-head">
							<span
								className="odocs-drag-handle dashicons dashicons-move"
								draggable
								title={ __( 'Drag to move the row', 'order-documents' ) }
								onDragStart={ ( e ) => {
									e.dataTransfer.effectAllowed = 'move';
									e.dataTransfer.setData( 'text/plain', row.id );
									setDrag( { kind: 'row', index: r } );
								} }
								onDragEnd={ () => {
									setDrag( null );
									setOver( null );
								} }
							/>
							<button type="button" className="odocs-row-title" onClick={ () => onSelect( { kind: 'row', id: row.id } ) }>
								{ sprintf( /* translators: %d: row number */ __( 'Row %d', 'order-documents' ), r + 1 ) }
							</button>
							<span className="odocs-col-count">
								{ [ 1, 2, 3 ].map( ( n ) => (
									<Button
										key={ n }
										size="small"
										isPressed={ row.columns.length === n }
										onClick={ () => setLayout( setColumnCount( layout, r, n ) ) }
										label={ sprintf( /* translators: %d: number of columns */ __( '%d columns', 'order-documents' ), n ) }
									>
										{ n }
									</Button>
								) ) }
							</span>
							<Button size="small" icon="arrow-up-alt2" disabled={ r === 0 } label={ __( 'Move up', 'order-documents' ) } onClick={ () => {
								const next = clone( layout );
								next.rows.splice( r - 1, 0, next.rows.splice( r, 1 )[ 0 ] );
								setLayout( next );
							} } />
							<Button size="small" icon="arrow-down-alt2" disabled={ r === layout.rows.length - 1 } label={ __( 'Move down', 'order-documents' ) } onClick={ () => {
								const next = clone( layout );
								next.rows.splice( r + 1, 0, next.rows.splice( r, 1 )[ 0 ] );
								setLayout( next );
							} } />
							<Button size="small" icon="trash" isDestructive label={ __( 'Delete row', 'order-documents' ) } onClick={ () => {
								// eslint-disable-next-line no-alert
								if ( row.columns.some( ( col ) => col.blocks.length ) && ! window.confirm( __( 'Delete this row and its blocks?', 'order-documents' ) ) ) {
									return;
								}
								const next = clone( layout );
								next.rows.splice( r, 1 );
								setLayout( next );
							} } />
						</div>
						<div className="odocs-row-columns">
							{ row.columns.map( ( column, c ) => (
								<div
									key={ c }
									className={ 'odocs-outline-col' + ( over === `col-${ r }-${ c }` ? ' is-drop-target' : '' ) }
									style={ { flexBasis: column.width + '%' } }
									onDragOver={ ( e ) => {
										if ( drag?.kind === 'block' ) {
											e.preventDefault();
											setOver( `col-${ r }-${ c }` );
										}
									} }
									onDrop={ ( e ) => onDropBlock( e, r, c, column.blocks.length ) }
								>
									{ column.blocks.map( ( block, b ) => {
										const def = blockTypes[ block.type ] || { label: block.type, icon: 'block-default' };
										const isSel = selected?.kind === 'block' && selected.id === block.id;
										return (
											<div
												key={ block.id }
												className={ 'odocs-chip' + ( isSel ? ' is-selected' : '' ) + ( over === block.id ? ' is-drop-before' : '' ) + ( drag?.id === block.id ? ' is-dragging' : '' ) }
												draggable
												onDragStart={ ( e ) => {
													e.stopPropagation();
													e.dataTransfer.effectAllowed = 'move';
													e.dataTransfer.setData( 'text/plain', block.id );
													setDrag( { kind: 'block', id: block.id } );
												} }
												onDragEnd={ () => {
													setDrag( null );
													setOver( null );
												} }
												onDragOver={ ( e ) => {
													if ( drag?.kind === 'block' ) {
														e.preventDefault();
														e.stopPropagation();
														setOver( block.id );
													}
												} }
												onDrop={ ( e ) => onDropBlock( e, r, c, b ) }
												onClick={ () => onSelect( { kind: 'block', id: block.id } ) }
												onKeyDown={ ( e ) => {
													if ( e.key === 'Enter' || e.key === ' ' ) {
														e.preventDefault();
														onSelect( { kind: 'block', id: block.id } );
													}
												} }
												role="button"
												tabIndex={ 0 }
												data-block-id={ block.id }
											>
												<span className={ 'dashicons dashicons-' + def.icon } />
												<span className="odocs-chip-label">{ def.label }</span>
											</div>
										);
									} ) }
									<AddBlockMenu blockTypes={ blockTypes } onAdd={ ( type ) => addBlock( r, c, type ) } />
								</div>
							) ) }
						</div>
					</div>
				);
			} ) }
			<div className="odocs-add-row">
				{ [ 1, 2, 3 ].map( ( n ) => (
					<Button key={ n } variant="secondary" size="small" icon="plus" onClick={ () => setLayout( { ...layout, rows: [ ...layout.rows, newRow( n ) ] } ) }>
						{ sprintf( /* translators: %d: number of columns */ __( 'Row with %d columns', 'order-documents' ), n ) }
					</Button>
				) ) }
			</div>
		</div>
	);
}
