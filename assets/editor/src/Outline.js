/**
 * Structure pane: a tree of rows, columns and blocks with drag and drop.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Dashicon,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf, _n } from '@wordpress/i18n';
import {
	Icon,
	chevronDown,
	chevronRight,
	moreVertical,
	dragHandle,
	plus,
	arrowUp,
	arrowDown,
	copy,
	trash,
} from '@wordpress/icons';
import Inserter from './Inserter';
import RowLayoutPicker, { ColumnGlyph } from './RowLayoutPicker';
import {
	blockSummary,
	moveBlock,
	moveRow,
	ROW_PRESETS,
	setColumnWidths,
} from './utils';

function BlockMenu( { onMove, onDuplicate, onDelete, canUp, canDown } ) {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Options', 'order-documents' ) }
			className="odocs-item-menu"
			toggleProps={ {
				size: 'small',
				onClick: ( e ) => e.stopPropagation(),
			} }
			popoverProps={ { placement: 'bottom-end' } }
		>
			{ ( { onClose } ) => (
				<>
					<MenuGroup>
						<MenuItem
							icon={ arrowUp }
							disabled={ ! canUp }
							onClick={ () => ( onMove( -1 ), onClose() ) }
							shortcut="Alt+↑"
						>
							{ __( 'Move up', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ arrowDown }
							disabled={ ! canDown }
							onClick={ () => ( onMove( 1 ), onClose() ) }
							shortcut="Alt+↓"
						>
							{ __( 'Move down', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ copy }
							onClick={ () => ( onDuplicate(), onClose() ) }
							shortcut="Ctrl+D"
						>
							{ __( 'Duplicate', 'order-documents' ) }
						</MenuItem>
					</MenuGroup>
					<MenuGroup>
						<MenuItem
							icon={ trash }
							isDestructive
							onClick={ () => ( onDelete(), onClose() ) }
							shortcut="Del"
						>
							{ __( 'Delete', 'order-documents' ) }
						</MenuItem>
					</MenuGroup>
				</>
			) }
		</DropdownMenu>
	);
}

function RowMenu( { r, count, actions, layout, setLayout } ) {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Row options', 'order-documents' ) }
			className="odocs-item-menu"
			toggleProps={ {
				size: 'small',
				onClick: ( e ) => e.stopPropagation(),
			} }
			popoverProps={ { placement: 'bottom-end' } }
		>
			{ ( { onClose } ) => (
				<>
					<MenuGroup label={ __( 'Columns', 'order-documents' ) }>
						<div className="odocs-menu-presets">
							{ [ 1, 2, 3 ].map( ( n ) => (
								<Button
									key={ n }
									size="small"
									isPressed={
										layout.rows[ r ].columns.length === n
									}
									onClick={ () => {
										setLayout(
											setColumnWidths(
												layout,
												r,
												ROW_PRESETS[ n ][ 0 ]
											)
										);
										onClose();
									} }
									label={ sprintf(
										/* translators: %d: number of columns */ _n(
											'%d column',
											'%d columns',
											n,
											'order-documents'
										),
										n
									) }
								>
									<ColumnGlyph
										widths={ ROW_PRESETS[ n ][ 0 ] }
										small
									/>
								</Button>
							) ) }
						</div>
					</MenuGroup>
					<MenuGroup>
						<MenuItem
							icon={ arrowUp }
							disabled={ r === 0 }
							onClick={ () => (
								actions.moveRow( r, r - 1 ), onClose()
							) }
							shortcut="Alt+↑"
						>
							{ __( 'Move up', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ arrowDown }
							disabled={ r === count - 1 }
							onClick={ () => (
								actions.moveRow( r, r + 1 ), onClose()
							) }
							shortcut="Alt+↓"
						>
							{ __( 'Move down', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ copy }
							onClick={ () => (
								actions.duplicateRow( r ), onClose()
							) }
							shortcut="Ctrl+D"
						>
							{ __( 'Duplicate row', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ plus }
							onClick={ () => (
								actions.addRow( [ 100 ], r + 1 ), onClose()
							) }
						>
							{ __( 'Insert row below', 'order-documents' ) }
						</MenuItem>
					</MenuGroup>
					<MenuGroup>
						<MenuItem
							icon={ trash }
							isDestructive
							onClick={ () => (
								actions.removeRow( r ), onClose()
							) }
							shortcut="Del"
						>
							{ __( 'Delete row', 'order-documents' ) }
						</MenuItem>
					</MenuGroup>
				</>
			) }
		</DropdownMenu>
	);
}

export default function Outline( {
	layout,
	setLayout,
	config,
	selected,
	actions,
} ) {
	const { blockTypes } = config;
	const [ collapsed, setCollapsed ] = useState( {} );
	// State drives rendering; refs give event handlers the latest value even before a re-render.
	const [ dragState, setDragState ] = useState( null ); // { kind: 'block', id } | { kind: 'row', index }
	const [ dropState, setDropState ] = useState( null ); // { kind: 'block', r, c, index } | { kind: 'row', index }
	const dragRef = useRef( null );
	const dropRef = useRef( null );
	const setDrag = ( v ) => {
		dragRef.current = v;
		setDragState( v );
	};
	const setDrop = ( v ) => {
		dropRef.current = v;
		setDropState( v );
	};
	const tree = useRef();

	// Keep the selected item visible and focusable.
	useEffect( () => {
		if ( ! selected || ! tree.current ) {
			return;
		}
		const el = tree.current.querySelector(
			`[data-item="${ selected.id }"]`
		);
		if ( el ) {
			el.scrollIntoView( { block: 'nearest' } );
		}
	}, [ selected ] );

	const endDrag = () => {
		setDrag( null );
		setDrop( null );
	};

	const commitDrop = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		const drag = dragRef.current;
		const drop = dropRef.current;
		if ( drag?.kind === 'block' && drop?.kind === 'block' ) {
			setLayout(
				moveBlock( layout, drag.id, drop.r, drop.c, drop.index )
			);
		} else if ( drag?.kind === 'row' && drop?.kind === 'row' ) {
			const to = drop.index > drag.index ? drop.index - 1 : drop.index;
			setLayout( moveRow( layout, drag.index, to ) );
		}
		endDrag();
	};

	const half = ( e ) => {
		const rect = e.currentTarget.getBoundingClientRect();
		return e.clientY < rect.top + rect.height / 2 ? 0 : 1;
	};

	const isDropIn = ( drop, kind, a, b, c ) =>
		drop &&
		drop.kind === kind &&
		( kind === 'row'
			? drop.index === a
			: drop.r === a && drop.c === b && drop.index === c );
	// For rendering (state) and for event handlers (ref).
	const isDrop = ( ...args ) => isDropIn( dropState, ...args );
	const isDropNow = ( ...args ) => isDropIn( dropRef.current, ...args );
	const drag = dragState;

	const focusSibling = ( current, dir ) => {
		const items = Array.from(
			tree.current.querySelectorAll( '[data-item]' )
		);
		const next = items[ items.indexOf( current ) + dir ];
		if ( next ) {
			next.focus();
			next.click();
		}
	};

	const itemKeys = ( e ) => {
		// Ignore keys from inner controls and popovers (React events bubble through portals).
		if ( e.altKey || e.target !== e.currentTarget ) {
			return; // Alt+arrows are global "move" shortcuts.
		}
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			focusSibling( e.currentTarget, e.key === 'ArrowDown' ? 1 : -1 );
		} else if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			e.currentTarget.click();
		}
	};

	const renderBlock = ( block, r, c, b, column ) => {
		const def = blockTypes[ block.type ] || {
			label: block.type,
			icon: 'block-default',
		};
		const isSel = selected?.kind === 'block' && selected.id === block.id;
		const summary = blockSummary( block );
		const first = r === 0 && c === 0 && b === 0;
		const last =
			r === layout.rows.length - 1 &&
			c === layout.rows[ r ].columns.length - 1 &&
			b === column.blocks.length - 1;
		return (
			<div
				key={ block.id }
				data-item={ block.id }
				role="treeitem"
				aria-selected={ isSel }
				tabIndex={ isSel || ( ! selected && first ) ? 0 : -1 }
				className={
					'odocs-item odocs-item-block' +
					( isSel ? ' is-selected' : '' ) +
					( drag?.id === block.id ? ' is-dragging' : '' ) +
					( isDrop( 'block', r, c, b ) ? ' is-drop-before' : '' ) +
					( isDrop( 'block', r, c, b + 1 ) &&
					b === column.blocks.length - 1
						? ' is-drop-after'
						: '' )
				}
				draggable
				onDragStart={ ( e ) => {
					e.stopPropagation();
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData( 'text/plain', block.id );
					setDrag( { kind: 'block', id: block.id } );
				} }
				onDragEnd={ endDrag }
				onDragOver={ ( e ) => {
					if ( dragRef.current?.kind !== 'block' ) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					const index = b + half( e );
					if ( ! isDropNow( 'block', r, c, index ) ) {
						setDrop( { kind: 'block', r, c, index } );
					}
				} }
				onDrop={ commitDrop }
				onClick={ () =>
					actions.select( { kind: 'block', id: block.id } )
				}
				onKeyDown={ itemKeys }
			>
				<span className="odocs-handle" aria-hidden="true">
					<Icon icon={ dragHandle } size={ 16 } />
				</span>
				<Dashicon icon={ def.icon } className="odocs-item-icon" />
				<span className="odocs-item-label">
					{ def.label }
					{ summary && (
						<span className="odocs-item-meta">{ summary }</span>
					) }
				</span>
				<BlockMenu
					canUp={ ! first }
					canDown={ ! last }
					onMove={ ( dir ) => actions.moveBlockBy( block.id, dir ) }
					onDuplicate={ () => actions.duplicateBlock( block.id ) }
					onDelete={ () => actions.removeBlock( block.id ) }
				/>
			</div>
		);
	};

	const renderColumn = ( column, r, c, multi ) => {
		const empty = ! column.blocks.length;
		return (
			<div
				key={ c }
				className={ 'odocs-col' + ( multi ? ' is-multi' : '' ) }
				role="group"
				onDragOver={ ( e ) => {
					if ( dragRef.current?.kind === 'block' && empty ) {
						e.preventDefault();
						if ( ! isDropNow( 'block', r, c, 0 ) ) {
							setDrop( { kind: 'block', r, c, index: 0 } );
						}
					}
				} }
				onDrop={ commitDrop }
			>
				{ multi && (
					<div className="odocs-col-head">
						<span>
							{ sprintf(
								/* translators: 1: column number, 2: width in percent */ __(
									'Column %1$d · %2$s%%',
									'order-documents'
								),
								c + 1,
								Math.round( column.width )
							) }
						</span>
						<Inserter
							blockTypes={ blockTypes }
							layout={ layout }
							onInsert={ ( type ) =>
								actions.addBlock( type, {
									r,
									c,
									index: column.blocks.length,
								} )
							}
						/>
					</div>
				) }
				{ column.blocks.map( ( block, b ) =>
					renderBlock( block, r, c, b, column )
				) }
				{ empty && (
					<div
						className={
							'odocs-col-empty' +
							( isDrop( 'block', r, c, 0 )
								? ' is-drop-target'
								: '' )
						}
					>
						{ __( 'Empty – drag a block here', 'order-documents' ) }
					</div>
				) }
			</div>
		);
	};

	return (
		<div className="odocs-structure">
			<div className="odocs-pane-head">
				<h2 className="odocs-pane-title">
					{ __( 'Structure', 'order-documents' ) }
				</h2>
				<Inserter
					blockTypes={ blockTypes }
					layout={ layout }
					onInsert={ ( type ) => actions.addBlock( type ) }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="primary"
							size="compact"
							icon={ plus }
							onClick={ onToggle }
							aria-expanded={ isOpen }
							label={ __( 'Add block', 'order-documents' ) }
							showTooltip
						>
							{ __( 'Block', 'order-documents' ) }
						</Button>
					) }
				/>
			</div>
			<div className="odocs-pane-scroll">
				<div
					className="odocs-tree"
					role="tree"
					aria-label={ __( 'Document structure', 'order-documents' ) }
					ref={ tree }
					onDragLeave={ ( e ) =>
						! e.currentTarget.contains( e.relatedTarget ) &&
						setDrop( null )
					}
				>
					{ layout.rows.map( ( row, r ) => {
						const isSel =
							selected?.kind === 'row' && selected.id === row.id;
						const isCollapsed = !! collapsed[ row.id ];
						const multi = row.columns.length > 1;
						const blockCount = row.columns.reduce(
							( n, col ) => n + col.blocks.length,
							0
						);
						return (
							<div
								key={ row.id }
								className={
									'odocs-row' +
									( isSel ? ' is-selected' : '' ) +
									( drag?.kind === 'row' && drag.index === r
										? ' is-dragging'
										: '' ) +
									( isDrop( 'row', r )
										? ' is-drop-before'
										: '' ) +
									( isDrop( 'row', r + 1 ) &&
									r === layout.rows.length - 1
										? ' is-drop-after'
										: '' )
								}
								onDragOver={ ( e ) => {
									if ( dragRef.current?.kind !== 'row' ) {
										return;
									}
									e.preventDefault();
									const index = r + half( e );
									if ( ! isDropNow( 'row', index ) ) {
										setDrop( { kind: 'row', index } );
									}
								} }
								onDrop={ commitDrop }
							>
								<div
									data-item={ row.id }
									role="treeitem"
									aria-selected={ isSel }
									aria-expanded={ ! isCollapsed }
									tabIndex={ isSel ? 0 : -1 }
									className="odocs-item odocs-item-row"
									draggable
									onDragStart={ ( e ) => {
										e.dataTransfer.effectAllowed = 'move';
										e.dataTransfer.setData(
											'text/plain',
											row.id
										);
										setDrag( { kind: 'row', index: r } );
									} }
									onDragEnd={ endDrag }
									onDragOver={ ( e ) => {
										// Dropping a block on a collapsed row opens it.
										if (
											dragRef.current?.kind === 'block' &&
											isCollapsed
										) {
											setCollapsed( {
												...collapsed,
												[ row.id ]: false,
											} );
										}
									} }
									onClick={ () =>
										actions.select( {
											kind: 'row',
											id: row.id,
										} )
									}
									onKeyDown={ ( e ) => {
										if (
											e.target === e.currentTarget &&
											( e.key === 'ArrowRight' ||
												e.key === 'ArrowLeft' )
										) {
											setCollapsed( {
												...collapsed,
												[ row.id ]:
													e.key === 'ArrowLeft',
											} );
											return;
										}
										itemKeys( e );
									} }
								>
									<button
										type="button"
										className="odocs-chevron"
										tabIndex={ -1 }
										aria-label={
											isCollapsed
												? __(
														'Expand',
														'order-documents'
												  )
												: __(
														'Collapse',
														'order-documents'
												  )
										}
										onClick={ ( e ) => {
											e.stopPropagation();
											setCollapsed( {
												...collapsed,
												[ row.id ]: ! isCollapsed,
											} );
										} }
									>
										<Icon
											icon={
												isCollapsed
													? chevronRight
													: chevronDown
											}
											size={ 18 }
										/>
									</button>
									<ColumnGlyph
										widths={ row.columns.map(
											( col ) => col.width
										) }
										small
									/>
									<span className="odocs-item-label">
										{ sprintf(
											/* translators: %d: row number */ __(
												'Row %d',
												'order-documents'
											),
											r + 1
										) }
										<span className="odocs-item-meta">
											{ isCollapsed
												? sprintf(
														/* translators: %d: number of blocks */ _n(
															'%d block',
															'%d blocks',
															blockCount,
															'order-documents'
														),
														blockCount
												  )
												: multi
												? sprintf(
														/* translators: %d: number of columns */ _n(
															'%d column',
															'%d columns',
															row.columns.length,
															'order-documents'
														),
														row.columns.length
												  )
												: '' }
										</span>
									</span>
									{ ! multi && (
										<Inserter
											blockTypes={ blockTypes }
											layout={ layout }
											onInsert={ ( type ) =>
												actions.addBlock( type, {
													r,
													c: 0,
													index: row.columns[ 0 ]
														.blocks.length,
												} )
											}
										/>
									) }
									<RowMenu
										r={ r }
										count={ layout.rows.length }
										actions={ actions }
										layout={ layout }
										setLayout={ setLayout }
									/>
								</div>
								{ ! isCollapsed && (
									<div
										className={
											'odocs-row-body' +
											( multi ? ' is-multi' : '' )
										}
									>
										{ row.columns.map( ( column, c ) =>
											renderColumn( column, r, c, multi )
										) }
									</div>
								) }
							</div>
						);
					} ) }
					{ ! layout.rows.length && (
						<p className="odocs-help">
							{ __(
								'This layout is empty. Add a row to start.',
								'order-documents'
							) }
						</p>
					) }
				</div>
			</div>
			<div className="odocs-pane-foot">
				<RowLayoutPicker
					onPick={ ( widths ) => actions.addRow( widths ) }
				/>
			</div>
		</div>
	);
}
