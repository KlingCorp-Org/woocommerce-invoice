/**
 * Left pane: the layer list (sections with their components, top layer first)
 * and the component library.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	Dashicon,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	Icon,
	chevronDown,
	chevronRight,
	dragHandle,
	moreVertical,
	plus,
	lock,
	unlock,
	copy,
	trash,
	arrowUp,
	arrowDown,
} from '@wordpress/icons';
import Library from './Inserter';
import {
	blockSummary,
	isFree,
	moveItemTo,
	sectionKindLabel,
	sectionLabel,
	findItem,
} from './utils';

const SECTION_ICONS = {
	canvas: 'layout',
	flow: 'list-view',
	footer: 'editor-insertmore',
};

function ItemMenu( { free, locked, actions, id } ) {
	return (
		<DropdownMenu
			icon={ moreVertical }
			label={ __( 'Options', 'order-documents' ) }
			className="odocs-item-menu"
			toggleProps={ { size: 'small' } }
			popoverProps={ { placement: 'bottom-end' } }
		>
			{ ( { onClose } ) => (
				<>
					{ free && (
						<MenuGroup>
							<MenuItem
								icon={ arrowUp }
								onClick={ () => ( actions.layer( [ id ], 'front' ), onClose() ) }
							>
								{ __( 'Bring to front', 'order-documents' ) }
							</MenuItem>
							<MenuItem
								icon={ arrowDown }
								onClick={ () => ( actions.layer( [ id ], 'back' ), onClose() ) }
							>
								{ __( 'Send to back', 'order-documents' ) }
							</MenuItem>
						</MenuGroup>
					) }
					<MenuGroup>
						<MenuItem
							icon={ locked ? unlock : lock }
							onClick={ () => ( actions.setLocked( [ id ], ! locked ), onClose() ) }
						>
							{ locked
								? __( 'Unlock position', 'order-documents' )
								: __( 'Lock position', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ copy }
							onClick={ () => ( actions.duplicate( [ id ] ), onClose() ) }
						>
							{ __( 'Duplicate', 'order-documents' ) }
						</MenuItem>
						<MenuItem
							icon={ trash }
							isDestructive
							onClick={ () => ( actions.remove( [ id ] ), onClose() ) }
						>
							{ __( 'Delete', 'order-documents' ) }
						</MenuItem>
					</MenuGroup>
				</>
			) }
		</DropdownMenu>
	);
}

export default function Layers( { layout, config, selection, actions } ) {
	const { blockTypes } = config;
	const [ tab, setTab ] = useState( 'layers' );
	const [ collapsed, setCollapsed ] = useState( {} );
	// State drives rendering; refs give event handlers the latest value before a re-render.
	const [ dragId, setDragIdState ] = useState( null );
	const [ drop, setDropState ] = useState( null ); // { s, index } model index
	const dragRef = useRef( null );
	const dropRef = useRef( null );
	const setDragId = ( v ) => {
		dragRef.current = v;
		setDragIdState( v );
	};
	const setDrop = ( v ) => {
		dropRef.current = v;
		setDropState( v );
	};
	const tree = useRef();
	const selectedIds = selection?.kind === 'items' ? selection.ids : [];

	useEffect( () => {
		if ( ! tree.current || ! selectedIds.length ) {
			return;
		}
		const el = tree.current.querySelector( `[data-item="${ selectedIds[ 0 ] }"]` );
		if ( el ) {
			el.scrollIntoView( { block: 'nearest' } );
		}
	}, [ selection ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const endDrag = () => {
		setDragId( null );
		setDrop( null );
	};

	const commitDrop = ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		const id = dragRef.current;
		const target = dropRef.current;
		endDrag();
		if ( ! id || ! target ) {
			return;
		}
		const pos = findItem( layout, id );
		if ( ! pos ) {
			return;
		}
		const section = layout.sections[ target.s ];
		let frame = null;
		if ( pos.s !== target.s && isFree( section ) ) {
			frame = {
				y: isFree( pos.section ) ? Math.min( pos.item.y, Math.max( 0, section.height - pos.item.h ) ) : 0,
			};
		}
		actions.change( moveItemTo( layout, id, target.s, target.index, frame ) );
		actions.select( { kind: 'items', ids: [ id ] } );
	};

	const focusSibling = ( current, dir ) => {
		const items = Array.from( tree.current.querySelectorAll( '[data-item]' ) );
		const next = items[ items.indexOf( current ) + dir ];
		if ( next ) {
			next.focus();
			next.click();
		}
	};
	const itemKeys = ( e ) => {
		if ( e.altKey || e.target !== e.currentTarget ) {
			return;
		}
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			e.stopPropagation();
			focusSibling( e.currentTarget, e.key === 'ArrowDown' ? 1 : -1 );
		} else if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			e.currentTarget.click();
		}
	};

	const renderItem = ( item, s, section, displayIndex, count ) => {
		const def = blockTypes[ item.type ] || { label: item.type, icon: 'block-default' };
		const free = isFree( section );
		const modelIndex = section.items.indexOf( item );
		const isSel = selectedIds.includes( item.id );
		const summary = blockSummary( item );
		// In free-form sections the list shows the top layer first.
		const beforeIndex = free ? modelIndex + 1 : modelIndex;
		const afterIndex = free ? modelIndex : modelIndex + 1;
		const isDropBefore = drop && drop.s === s && drop.index === beforeIndex && drop.at === item.id + ':b';
		const isDropAfter = drop && drop.s === s && drop.index === afterIndex && drop.at === item.id + ':a';
		return (
			<div
				key={ item.id }
				data-item={ item.id }
				role="treeitem"
				aria-selected={ isSel }
				tabIndex={ isSel || ( ! selectedIds.length && s === 0 && displayIndex === 0 ) ? 0 : -1 }
				className={
					'odocs-item odocs-item-block' +
					( isSel ? ' is-selected' : '' ) +
					( dragId === item.id ? ' is-dragging' : '' ) +
					( isDropBefore ? ' is-drop-before' : '' ) +
					( isDropAfter ? ' is-drop-after' : '' )
				}
				draggable
				onDragStart={ ( e ) => {
					e.stopPropagation();
					e.dataTransfer.effectAllowed = 'move';
					e.dataTransfer.setData( 'text/plain', item.id );
					setDragId( item.id );
				} }
				onDragEnd={ endDrag }
				onDragOver={ ( e ) => {
					if ( ! dragRef.current ) {
						return;
					}
					e.preventDefault();
					e.stopPropagation();
					const rect = e.currentTarget.getBoundingClientRect();
					const top = e.clientY < rect.top + rect.height / 2;
					const next = {
						s,
						index: top ? beforeIndex : afterIndex,
						at: item.id + ( top ? ':b' : ':a' ),
					};
					if ( dropRef.current?.at !== next.at ) {
						setDrop( next );
					}
				} }
				onDrop={ commitDrop }
				onClick={ ( e ) => {
					if ( e.shiftKey || e.metaKey || e.ctrlKey ) {
						const ids = isSel
							? selectedIds.filter( ( x ) => x !== item.id )
							: [ ...selectedIds, item.id ];
						actions.select( ids.length ? { kind: 'items', ids } : null );
					} else {
						actions.select( { kind: 'items', ids: [ item.id ] } );
					}
				} }
				onKeyDown={ itemKeys }
			>
				<span className="odocs-handle" aria-hidden="true">
					<Icon icon={ dragHandle } size={ 16 } />
				</span>
				<Dashicon icon={ def.icon } className="odocs-item-icon" />
				<span className="odocs-item-label">
					{ def.label }
					{ summary && <span className="odocs-item-meta">{ summary }</span> }
				</span>
				{ free && displayIndex === 0 && count > 1 && (
					<span className="odocs-layer-top" title={ __( 'Top layer', 'order-documents' ) } />
				) }
				<Button
					size="small"
					className={ 'odocs-lock' + ( item.locked ? ' is-locked' : '' ) }
					icon={ item.locked ? lock : unlock }
					label={
						item.locked
							? __( 'Unlock position', 'order-documents' )
							: __( 'Lock position', 'order-documents' )
					}
					onClick={ ( e ) => {
						e.stopPropagation();
						actions.setLocked( [ item.id ], ! item.locked );
					} }
				/>
				<ItemMenu free={ free } locked={ !! item.locked } actions={ actions } id={ item.id } />
			</div>
		);
	};

	const hasFooter = layout.sections.some( ( x ) => x.kind === 'footer' );

	const renderSection = ( section, s ) => {
		const open = ! collapsed[ section.id ];
		const free = isFree( section );
		const list = free ? [ ...section.items ].reverse() : section.items;
		const isSel = selection?.kind === 'section' && selection.id === section.id;
		return (
			<div
				key={ section.id }
				className={ 'odocs-layer-section' + ( drop?.s === s && drop.at === 'section' ? ' is-drop-target' : '' ) }
				role="group"
				aria-label={ sectionLabel( section, s ) }
				onDragOver={ ( e ) => {
					if ( ! dragRef.current ) {
						return;
					}
					e.preventDefault();
					if ( ! section.items.length && dropRef.current?.at !== 'section' ) {
						setDrop( { s, index: 0, at: 'section' } );
					}
				} }
				onDrop={ commitDrop }
			>
				<div
					className={ 'odocs-item odocs-item-row' + ( isSel ? ' is-selected' : '' ) }
					role="treeitem"
					aria-expanded={ open }
					aria-selected={ isSel }
					tabIndex={ -1 }
					data-item={ section.id }
					onClick={ () => actions.select( { kind: 'section', id: section.id } ) }
					onDragOver={ ( e ) => {
						if ( ! dragRef.current ) {
							return;
						}
						e.preventDefault();
						e.stopPropagation();
						const index = free ? section.items.length : 0;
						if ( dropRef.current?.at !== 'head' + s ) {
							setDrop( { s, index, at: 'head' + s } );
						}
					} }
					onDrop={ commitDrop }
					onKeyDown={ itemKeys }
				>
					<button
						type="button"
						className="odocs-chevron"
						aria-label={ open ? __( 'Collapse', 'order-documents' ) : __( 'Expand', 'order-documents' ) }
						onClick={ ( e ) => {
							e.stopPropagation();
							setCollapsed( { ...collapsed, [ section.id ]: open } );
						} }
					>
						<Icon icon={ open ? chevronDown : chevronRight } size={ 18 } />
					</button>
					<Dashicon icon={ SECTION_ICONS[ section.kind ] } className="odocs-item-icon" />
					<span className="odocs-item-label">
						{ sectionLabel( section, s ) }
						<span className="odocs-item-meta">{ sectionKindLabel( section.kind ) }</span>
					</span>
					<DropdownMenu
						icon={ moreVertical }
						label={ __( 'Section options', 'order-documents' ) }
						className="odocs-item-menu"
						toggleProps={ { size: 'small' } }
						popoverProps={ { placement: 'bottom-end' } }
						controls={ [
							{
								title: __( 'Move up', 'order-documents' ),
								icon: arrowUp,
								isDisabled: s === 0,
								onClick: () => actions.moveSection( s, s - 1 ),
							},
							{
								title: __( 'Move down', 'order-documents' ),
								icon: arrowDown,
								isDisabled: s === layout.sections.length - 1,
								onClick: () => actions.moveSection( s, s + 1 ),
							},
							{
								title: __( 'Delete section', 'order-documents' ),
								icon: trash,
								onClick: () => actions.removeSection( s ),
							},
						] }
					/>
				</div>
				{ open && (
					<div className="odocs-layer-items" role="group">
						{ list.map( ( item, i ) => renderItem( item, s, section, i, list.length ) ) }
						{ ! list.length && (
							<div className={ 'odocs-col-empty' + ( drop?.s === s && ( drop.at === 'section' || drop.at === 'head' + s ) ? ' is-drop-target' : '' ) }>
								{ __( 'Empty – drag a component here', 'order-documents' ) }
							</div>
						) }
					</div>
				) }
			</div>
		);
	};

	return (
		<div className="odocs-structure">
			<div className="odocs-pane-head odocs-tabs" role="tablist">
				<button
					type="button"
					role="tab"
					aria-selected={ tab === 'layers' }
					className={ 'odocs-tab' + ( tab === 'layers' ? ' is-active' : '' ) }
					onClick={ () => setTab( 'layers' ) }
				>
					{ __( 'Layers', 'order-documents' ) }
				</button>
				<button
					type="button"
					role="tab"
					aria-selected={ tab === 'add' }
					className={ 'odocs-tab' + ( tab === 'add' ? ' is-active' : '' ) }
					onClick={ () => setTab( 'add' ) }
				>
					<Icon icon={ plus } size={ 18 } />
					{ __( 'Add', 'order-documents' ) }
				</button>
			</div>
			<div className="odocs-pane-scroll">
				{ tab === 'add' ? (
					<Library blockTypes={ blockTypes } layout={ layout } onInsert={ actions.add } />
				) : (
					<div
						className="odocs-tree"
						role="tree"
						aria-label={ __( 'Layers', 'order-documents' ) }
						ref={ tree }
					>
						{ layout.sections.map( renderSection ) }
						<div className="odocs-tree-foot">
							<DropdownMenu
								icon={ plus }
								text={ __( 'Add section', 'order-documents' ) }
								label={ __( 'Add section', 'order-documents' ) }
								toggleProps={ { variant: 'secondary', size: 'compact' } }
								controls={ [
									{
										title: __( 'Free-form section', 'order-documents' ),
										icon: 'layout',
										onClick: () => actions.addSection( 'canvas' ),
									},
									{
										title: __( 'Flowing section (grows with its content)', 'order-documents' ),
										icon: 'list-view',
										onClick: () => actions.addSection( 'flow' ),
									},
									...( hasFooter
										? []
										: [
												{
													title: __( 'Page footer', 'order-documents' ),
													icon: 'editor-insertmore',
													onClick: () => actions.addSection( 'footer' ),
												},
										  ] ),
								] }
							/>
						</div>
						<p className="odocs-help odocs-pad">
							{ sprintf(
								/* translators: %s: name of the Add tab */
								__( 'Tip: open %s to drag new components onto the page.', 'order-documents' ),
								__( 'Add', 'order-documents' )
							) }
						</p>
					</div>
				) }
			</div>
		</div>
	);
}
