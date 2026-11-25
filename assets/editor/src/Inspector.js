/**
 * Right-hand settings pane: document style, a section, one component or several
 * components, depending on the selection.
 */
import { useRef, useState } from '@wordpress/element';
import {
	Button,
	PanelBody,
	TextControl,
	ToggleControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOptionIcon as ToggleGroupControlOptionIcon,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	Dashicon,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	Icon,
	chevronRight,
	chevronDown,
	copy,
	trash,
	dragHandle,
	alignLeft,
	alignCenter,
	alignRight,
	justifyLeft,
	justifyCenter,
	justifyRight,
	justifyTop,
	justifyCenterVertical,
	justifyBottom,
	justifySpaceBetween,
	arrowUp,
	arrowDown,
	lock,
	unlock,
	styles as stylesIcon,
} from '@wordpress/icons';
import Field, { NumberField } from './fields';
import StylePanel from './StylePanel';
import {
	clone,
	contentBottom,
	findItem,
	findSection,
	isFree,
	sectionKindLabel,
	sectionLabel,
	tidy,
} from './utils';

const COMMON = [ 'align', 'font_size', 'margin_bottom', 'text_color', 'box' ];

function Breadcrumb( { layout, selection, onSelect, blockTypes } ) {
	const crumbs = [ { label: __( 'Document', 'order-documents' ), sel: null } ];
	if ( selection?.kind === 'section' ) {
		const s = findSection( layout, selection.id );
		if ( s !== -1 ) {
			crumbs.push( { label: sectionLabel( layout.sections[ s ], s ), sel: selection } );
		}
	} else if ( selection?.kind === 'items' ) {
		const pos = findItem( layout, selection.ids[ 0 ] );
		if ( pos ) {
			crumbs.push( {
				label: sectionLabel( pos.section, pos.s ),
				sel: { kind: 'section', id: pos.section.id },
			} );
			crumbs.push( {
				label:
					selection.ids.length > 1
						? sprintf(
								/* translators: %d: number of components */ __( '%d components', 'order-documents' ),
								selection.ids.length
						  )
						: blockTypes[ pos.item.type ]?.label || pos.item.type,
				sel: selection,
			} );
		}
	}
	return (
		<nav className="odocs-breadcrumb" aria-label={ __( 'Selection', 'order-documents' ) }>
			{ crumbs.map( ( c, i ) => {
				const last = i === crumbs.length - 1;
				return (
					<span key={ i } className="odocs-crumb">
						{ last ? (
							<span aria-current="location">{ c.label }</span>
						) : (
							<button type="button" onClick={ () => onSelect( c.sel ) }>
								{ c.label }
							</button>
						) }
						{ ! last && <Icon icon={ chevronRight } size={ 16 } /> }
					</span>
				);
			} ) }
		</nav>
	);
}

function PaneHeader( { icon, title, subtitle } ) {
	return (
		<div className="odocs-card-head">
			<span className="odocs-card-icon">
				{ typeof icon === 'string' ? (
					<Dashicon icon={ icon } />
				) : (
					<Icon icon={ icon } />
				) }
			</span>
			<div>
				<h2>{ title }</h2>
				{ subtitle && <p>{ subtitle }</p> }
			</div>
		</div>
	);
}

const COLUMN_ALIGN = {
	left: alignLeft,
	center: alignCenter,
	right: alignRight,
};

function ColumnsEditor( { columns, columnTypes, onChange } ) {
	const [ open, setOpen ] = useState( null );
	const [ drag, setDragState ] = useState( null );
	const [ drop, setDropState ] = useState( null );
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
	const update = ( i, changes ) => {
		const next = clone( columns );
		next[ i ] = { ...next[ i ], ...changes };
		onChange( next );
	};
	const finishDrop = () => {
		const from = dragRef.current;
		const to = dropRef.current;
		if ( from !== null && to !== null && to !== from && to !== from + 1 ) {
			const next = clone( columns );
			const [ item ] = next.splice( from, 1 );
			next.splice( to > from ? to - 1 : to, 0, item );
			onChange( next );
		}
		setDrag( null );
		setDrop( null );
	};
	return (
		<div className="odocs-columns-editor">
			<p className="odocs-help">
				{ __(
					'Turn columns on or off and drag to reorder them.',
					'order-documents'
				) }
			</p>
			{ columns.map( ( col, i ) => {
				const def = columnTypes[ col.key ] || { label: col.key };
				const isOpen = open === col.key && !! col.enabled;
				return (
					<div
						key={ col.key }
						className={
							'odocs-colitem' +
							( col.enabled ? ' is-enabled' : '' ) +
							( drag === i ? ' is-dragging' : '' ) +
							( drop === i ? ' is-drop-before' : '' ) +
							( drop === i + 1 && i === columns.length - 1
								? ' is-drop-after'
								: '' )
						}
						onDragOver={ ( e ) => {
							if ( dragRef.current === null ) {
								return;
							}
							e.preventDefault();
							const rect =
								e.currentTarget.getBoundingClientRect();
							const at =
								e.clientY < rect.top + rect.height / 2
									? i
									: i + 1;
							if ( at !== dropRef.current ) {
								setDrop( at );
							}
						} }
						onDrop={ ( e ) => {
							e.preventDefault();
							finishDrop();
						} }
					>
						<div className="odocs-colitem-head">
							<span
								className="odocs-handle"
								draggable
								onDragStart={ ( e ) => {
									e.dataTransfer.effectAllowed = 'move';
									e.dataTransfer.setData(
										'text/plain',
										col.key
									);
									setDrag( i );
								} }
								onDragEnd={ () => {
									setDrag( null );
									setDrop( null );
								} }
								title={ __(
									'Drag to reorder',
									'order-documents'
								) }
							>
								<Icon icon={ dragHandle } size={ 18 } />
							</span>
							<ToggleControl
								__nextHasNoMarginBottom
								label={ col.label || def.label }
								checked={ !! col.enabled }
								onChange={ ( checked ) =>
									update( i, { enabled: checked ? 1 : 0 } )
								}
							/>
							{ !! col.enabled && (
								<Button
									size="small"
									className="odocs-colitem-more"
									icon={ isOpen ? chevronDown : chevronRight }
									label={ __(
										'Column options',
										'order-documents'
									) }
									aria-expanded={ isOpen }
									onClick={ () =>
										setOpen( isOpen ? null : col.key )
									}
								/>
							) }
						</div>
						{ isOpen && (
							<div className="odocs-colitem-props">
								<TextControl
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ __( 'Heading', 'order-documents' ) }
									placeholder={ def.label }
									value={ col.label }
									onChange={ ( v ) =>
										update( i, { label: v } )
									}
								/>
								<div className="odocs-two">
									<NumberField
										label={ __(
											'Width %',
											'order-documents'
										) }
										help={ __(
											'0 = automatic',
											'order-documents'
										) }
										value={ col.width }
										min={ 0 }
										max={ 80 }
										onChange={ ( v ) =>
											update( i, { width: v } )
										}
									/>
									<ToggleGroupControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										isDeselectable
										label={ __(
											'Align',
											'order-documents'
										) }
										value={ col.align || undefined }
										onChange={ ( v ) =>
											update( i, { align: v || '' } )
										}
									>
										{ Object.entries( COLUMN_ALIGN ).map(
											( [ k, icon ] ) => (
												<ToggleGroupControlOptionIcon
													key={ k }
													value={ k }
													icon={ icon }
													label={ k }
												/>
											)
										) }
									</ToggleGroupControl>
								</div>
							</div>
						) }
					</div>
				);
			} ) }
		</div>
	);
}


const ALIGN_BUTTONS = [
	[ 'left', justifyLeft, __( 'Align left', 'order-documents' ) ],
	[ 'center', justifyCenter, __( 'Align centre', 'order-documents' ) ],
	[ 'right', justifyRight, __( 'Align right', 'order-documents' ) ],
	[ 'top', justifyTop, __( 'Align top', 'order-documents' ) ],
	[ 'middle', justifyCenterVertical, __( 'Align middle', 'order-documents' ) ],
	[ 'bottom', justifyBottom, __( 'Align bottom', 'order-documents' ) ],
];

function ArrangePanel( { ids, actions, multi } ) {
	return (
		<PanelBody title={ __( 'Arrange', 'order-documents' ) }>
			<div className="odocs-fields">
				<div>
					<span className="odocs-field-label">
						{ multi
							? __( 'Align to each other', 'order-documents' )
							: __( 'Align in the section', 'order-documents' ) }
					</span>
					<div className="odocs-iconrow">
						{ ALIGN_BUTTONS.map( ( [ how, icon, label ] ) => (
							<Button
								key={ how }
								icon={ icon }
								label={ label }
								size="compact"
								onClick={ () => actions.align( ids, how ) }
							/>
						) ) }
					</div>
				</div>
				{ multi && ids.length > 2 && (
					<div>
						<span className="odocs-field-label">
							{ __( 'Spread evenly', 'order-documents' ) }
						</span>
						<div className="odocs-iconrow">
							<Button
								icon={ justifySpaceBetween }
								label={ __( 'Spread horizontally', 'order-documents' ) }
								size="compact"
								onClick={ () => actions.distribute( ids, 'x' ) }
							>
								{ __( 'Horizontally', 'order-documents' ) }
							</Button>
							<Button
								icon={ justifySpaceBetween }
								className="odocs-rotate"
								label={ __( 'Spread vertically', 'order-documents' ) }
								size="compact"
								onClick={ () => actions.distribute( ids, 'y' ) }
							>
								{ __( 'Vertically', 'order-documents' ) }
							</Button>
						</div>
					</div>
				) }
				<div>
					<span className="odocs-field-label">{ __( 'Layer', 'order-documents' ) }</span>
					<div className="odocs-iconrow odocs-layer-buttons">
						<Button size="compact" variant="secondary" onClick={ () => actions.layer( ids, 'front' ) }>
							{ __( 'To front', 'order-documents' ) }
						</Button>
						<Button size="compact" icon={ arrowUp } label={ __( 'Bring forward', 'order-documents' ) } shortcut="Ctrl+]" onClick={ () => actions.layer( ids, 'forward' ) } />
						<Button size="compact" icon={ arrowDown } label={ __( 'Send backward', 'order-documents' ) } shortcut="Ctrl+[" onClick={ () => actions.layer( ids, 'backward' ) } />
						<Button size="compact" variant="secondary" onClick={ () => actions.layer( ids, 'back' ) }>
							{ __( 'To back', 'order-documents' ) }
						</Button>
					</div>
				</div>
			</div>
		</PanelBody>
	);
}

function PlacementFields( { items, actions } ) {
	const ids = items.map( ( i ) => i.id );
	const snap = items.every( ( i ) => Number( i.snap ) );
	const locked = items.every( ( i ) => Number( i.locked ) );
	return (
		<>
			<ToggleGroupControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				isBlock
				label={ __( 'Placement', 'order-documents' ) }
				help={
					snap
						? __( 'Moves and resizes in steps of the grid.', 'order-documents' )
						: __( 'Moves freely, to a tenth of a millimetre. Smart guides still help you line it up.', 'order-documents' )
				}
				value={ snap ? 'grid' : 'free' }
				onChange={ ( v ) => actions.setSnap( ids, v === 'grid' ) }
			>
				<ToggleGroupControlOption value="grid" label={ __( 'Snap to grid', 'order-documents' ) } />
				<ToggleGroupControlOption value="free" label={ __( 'Free', 'order-documents' ) } />
			</ToggleGroupControl>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Lock position', 'order-documents' ) }
				help={ __( 'A locked component cannot be moved or resized by accident.', 'order-documents' ) }
				checked={ locked }
				onChange={ ( v ) => actions.setLocked( ids, v ) }
			/>
		</>
	);
}

function PositionPanel( { pos, actions, layout } ) {
	const { item, section } = pos;
	const free = isFree( section );
	const set = ( key, v ) => actions.setFrame( item.id, { [ key ]: v }, item.id + ':' + key );
	return (
		<PanelBody title={ __( 'Position and size', 'order-documents' ) }>
			<div className="odocs-fields">
				{ free ? (
					<div className="odocs-grid4">
						<NumberField label={ __( 'X (mm)', 'order-documents' ) } value={ item.x } step={ 0.5 } min={ -60 } max={ 400 } onChange={ ( v ) => set( 'x', v ) } />
						<NumberField label={ __( 'Y (mm)', 'order-documents' ) } value={ item.y } step={ 0.5 } min={ 0 } max={ 1500 } onChange={ ( v ) => set( 'y', v ) } />
						<NumberField label={ __( 'Width (mm)', 'order-documents' ) } value={ item.w } step={ 0.5 } min={ 2 } max={ 400 } onChange={ ( v ) => set( 'w', Math.max( 2, v ) ) } />
						<NumberField label={ __( 'Height (mm)', 'order-documents' ) } value={ item.h } step={ 0.5 } min={ 1 } max={ 1500 } onChange={ ( v ) => set( 'h', Math.max( 1, v ) ) } />
					</div>
				) : (
					<>
						<div className="odocs-two">
							<NumberField label={ __( 'Indent (mm)', 'order-documents' ) } value={ item.x } step={ 0.5 } min={ -60 } max={ 400 } onChange={ ( v ) => set( 'x', v ) } />
							<NumberField label={ __( 'Width (mm)', 'order-documents' ) } value={ item.w } step={ 0.5 } min={ 2 } max={ 400 } onChange={ ( v ) => set( 'w', Math.max( 2, v ) ) } />
						</div>
						<p className="odocs-help">
							{ __( 'This section grows with its content, so components are stacked under each other. Drag a component into a free-form section to place it freely.', 'order-documents' ) }
						</p>
					</>
				) }
				{ free && (
					<ToggleGroupControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						isBlock
						label={ __( 'When the content is taller than the box', 'order-documents' ) }
						help={
							item.overflow === 'clip'
								? __( 'Anything below the box edge is cut off.', 'order-documents' )
								: __( 'The box grows downwards over what is below. Nothing else moves.', 'order-documents' )
						}
						value={ item.overflow === 'clip' ? 'clip' : 'grow' }
						onChange={ ( v ) => actions.changeItems( [ item.id ], ( it ) => ( it.overflow = v ) ) }
					>
						<ToggleGroupControlOption value="grow" label={ __( 'Let it grow', 'order-documents' ) } />
						<ToggleGroupControlOption value="clip" label={ __( 'Cut off', 'order-documents' ) } />
					</ToggleGroupControl>
				) }
				{ free && <PlacementFields items={ [ item ] } actions={ actions } /> }
			</div>
		</PanelBody>
	);
}

function ItemSettings( { layout, pos, config, palette, actions } ) {
	const { item, section } = pos;
	const def = config.blockTypes[ item.type ];
	if ( ! def ) {
		return <p className="odocs-help odocs-pad">{ __( 'Unknown component type.', 'order-documents' ) }</p>;
	}
	const free = isFree( section );
	const setSetting = ( key, value ) =>
		actions.changeItems( [ item.id ], ( it ) => ( it.settings[ key ] = value ), item.id + ':' + key );
	const keys = Object.keys( def.settings );
	const own = keys.filter( ( k ) => ! COMMON.includes( k ) && def.settings[ k ].type !== 'columns' );
	const columnKeys = keys.filter( ( k ) => def.settings[ k ].type === 'columns' );
	const common = COMMON.filter( ( k ) => def.settings[ k ] && ! ( free && k === 'margin_bottom' ) );
	const renderField = ( key ) => (
		<Field
			key={ key }
			field={ def.settings[ key ] }
			value={ item.settings[ key ] }
			palette={ palette }
			onChange={ ( v ) => setSetting( key, v ) }
		/>
	);
	return (
		<>
			<PaneHeader
				icon={ def.icon }
				title={ def.label }
				subtitle={ sprintf(
					/* translators: %s: section name */ __( 'In %s', 'order-documents' ),
					sectionLabel( section, pos.s )
				) }
			/>
			<PositionPanel pos={ pos } actions={ actions } layout={ layout } />
			{ own.length > 0 && (
				<PanelBody title={ __( 'Content', 'order-documents' ) }>
					<div className="odocs-fields">{ own.map( renderField ) }</div>
				</PanelBody>
			) }
			{ columnKeys.map( ( key ) => (
				<PanelBody key={ key } title={ def.settings[ key ].label || __( 'Columns', 'order-documents' ) } initialOpen={ false }>
					<ColumnsEditor
						columns={ item.settings[ key ] || [] }
						columnTypes={ config.columnTypes }
						onChange={ ( v ) => setSetting( key, v ) }
					/>
				</PanelBody>
			) ) }
			{ common.length > 0 && (
				<PanelBody title={ __( 'Appearance', 'order-documents' ) } initialOpen={ own.length === 0 }>
					<div className="odocs-fields">
						{ common.includes( 'align' ) && renderField( 'align' ) }
						<div className="odocs-two">
							{ common.includes( 'font_size' ) && renderField( 'font_size' ) }
							{ common.includes( 'margin_bottom' ) && renderField( 'margin_bottom' ) }
						</div>
						{ common.includes( 'text_color' ) && renderField( 'text_color' ) }
						{ common.includes( 'box' ) && renderField( 'box' ) }
					</div>
				</PanelBody>
			) }
			{ free && <ArrangePanel ids={ [ item.id ] } actions={ actions } multi={ false } /> }
			<div className="odocs-card-actions">
				<Button variant="secondary" icon={ copy } onClick={ () => actions.duplicate( [ item.id ] ) }>
					{ __( 'Duplicate', 'order-documents' ) }
				</Button>
				<Button variant="secondary" icon={ trash } isDestructive onClick={ () => actions.remove( [ item.id ] ) }>
					{ __( 'Delete', 'order-documents' ) }
				</Button>
			</div>
		</>
	);
}

function MultiSettings( { layout, ids, config, actions } ) {
	const list = ids.map( ( id ) => findItem( layout, id ) ).filter( Boolean );
	const freeItems = list.filter( ( p ) => isFree( p.section ) ).map( ( p ) => p.item );
	return (
		<>
			<PaneHeader
				icon="screenoptions"
				title={ sprintf(
					/* translators: %d: number of components */ __( '%d components', 'order-documents' ),
					list.length
				) }
				subtitle={ list.map( ( p ) => config.blockTypes[ p.item.type ]?.label || p.item.type ).join( ', ' ) }
			/>
			{ freeItems.length > 1 && <ArrangePanel ids={ freeItems.map( ( i ) => i.id ) } actions={ actions } multi /> }
			{ freeItems.length > 0 && (
				<PanelBody title={ __( 'Placement', 'order-documents' ) }>
					<div className="odocs-fields">
						<PlacementFields items={ freeItems } actions={ actions } />
					</div>
				</PanelBody>
			) }
			<p className="odocs-help odocs-pad">
				{ __( 'Drag any of them on the page to move them together. Shift-click adds or removes a component.', 'order-documents' ) }
			</p>
			<div className="odocs-card-actions">
				<Button variant="secondary" icon={ copy } onClick={ () => actions.duplicate( ids ) }>
					{ __( 'Duplicate', 'order-documents' ) }
				</Button>
				<Button variant="secondary" icon={ trash } isDestructive onClick={ () => actions.remove( ids ) }>
					{ __( 'Delete', 'order-documents' ) }
				</Button>
			</div>
		</>
	);
}

function SectionSettings( { layout, s, actions } ) {
	const section = layout.sections[ s ];
	const free = isFree( section );
	const set = ( fn, key ) => {
		const next = clone( layout );
		fn( next.sections[ s ] );
		actions.change( next, key && section.id + key );
	};
	const minHeight = contentBottom( section );
	const KINDS = [
		[ 'canvas', __( 'Free-form', 'order-documents' ) ],
		[ 'flow', __( 'Flowing', 'order-documents' ) ],
	];
	return (
		<>
			<PaneHeader
				icon={ section.kind === 'flow' ? 'list-view' : section.kind === 'footer' ? 'editor-insertmore' : 'layout' }
				title={ sectionLabel( section, s ) }
				subtitle={ sectionKindLabel( section.kind ) }
			/>
			<PanelBody title={ __( 'Section', 'order-documents' ) }>
				<div className="odocs-fields">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Name', 'order-documents' ) }
						help={ __( 'Only shown in the editor.', 'order-documents' ) }
						value={ section.name }
						placeholder={ sectionLabel( { ...section, name: '' }, s ) }
						onChange={ ( v ) => set( ( x ) => ( x.name = v ), 'name' ) }
					/>
					{ section.kind !== 'footer' && (
						<ToggleGroupControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
							isBlock
							label={ __( 'Type', 'order-documents' ) }
							help={
								free
									? __( 'Components are placed freely and never push each other. The section has a fixed height.', 'order-documents' )
									: __( 'Components are stacked under each other and the section grows with them, across pages if needed. Use it for the products table.', 'order-documents' )
							}
							value={ section.kind }
							onChange={ ( v ) => actions.setSectionKind( s, v ) }
						>
							{ KINDS.map( ( [ v, l ] ) => (
								<ToggleGroupControlOption key={ v } value={ v } label={ l } />
							) ) }
						</ToggleGroupControl>
					) }
					{ section.kind === 'footer' && (
						<p className="odocs-help">
							{ __( 'Shown at the bottom of every page, inside the bottom margin. The bottom margin grows to fit it.', 'order-documents' ) }
						</p>
					) }
					{ free && (
						<NumberField
							label={ __( 'Height (mm)', 'order-documents' ) }
							help={
								minHeight > 0
									? sprintf(
											/* translators: %s: height in mm */ __( 'At least %s mm, to fit its components.', 'order-documents' ),
											tidy( minHeight )
									  )
									: undefined
							}
							value={ section.height }
							min={ 0 }
							max={ 1500 }
							step={ 1 }
							onChange={ ( v ) => set( ( x ) => ( x.height = Math.max( minHeight, v ) ), 'height' ) }
						/>
					) }
					{ section.kind === 'canvas' && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Keep on one page', 'order-documents' ) }
							help={ __( 'If the section does not fit at the bottom of a page, it moves to the next page as a whole.', 'order-documents' ) }
							checked={ !! Number( section.keep_together ) }
							onChange={ ( v ) => set( ( x ) => ( x.keep_together = v ? 1 : 0 ) ) }
						/>
					) }
				</div>
			</PanelBody>
			<div className="odocs-card-actions">
				<Button variant="secondary" icon={ arrowUp } disabled={ s === 0 } onClick={ () => actions.moveSection( s, s - 1 ) }>
					{ __( 'Move up', 'order-documents' ) }
				</Button>
				<Button variant="secondary" icon={ arrowDown } disabled={ s === layout.sections.length - 1 } onClick={ () => actions.moveSection( s, s + 1 ) }>
					{ __( 'Move down', 'order-documents' ) }
				</Button>
				<Button variant="secondary" icon={ trash } isDestructive onClick={ () => actions.removeSection( s ) }>
					{ __( 'Delete', 'order-documents' ) }
				</Button>
			</div>
		</>
	);
}

export default function Inspector( props ) {
	const { layout, selection, onSelect, config, style, setStyle, effectiveStyle } = props;
	let body = null;
	if ( selection?.kind === 'section' ) {
		const s = findSection( layout, selection.id );
		if ( s !== -1 ) {
			body = <SectionSettings key={ selection.id } { ...props } s={ s } />;
		}
	} else if ( selection?.kind === 'items' && selection.ids.length > 1 ) {
		body = <MultiSettings { ...props } ids={ selection.ids } />;
	} else if ( selection?.kind === 'items' ) {
		const pos = findItem( layout, selection.ids[ 0 ] );
		if ( pos ) {
			body = <ItemSettings key={ pos.item.id } { ...props } pos={ pos } />;
		}
	}
	if ( ! body ) {
		body = (
			<>
				<PaneHeader
					icon={ stylesIcon }
					title={ __( 'Document style', 'order-documents' ) }
					subtitle={ __( 'Colours, fonts and margins for all documents.', 'order-documents' ) }
				/>
				<StylePanel fields={ config.designFields } style={ style } setStyle={ setStyle } effective={ effectiveStyle } />
				<p className="odocs-help odocs-pad">
					{ __( 'Tip: click a component on the page to change it, or drag it to move it. Drag on an empty spot to select several.', 'order-documents' ) }
				</p>
			</>
		);
	}
	return (
		<div className="odocs-settings">
			<div className="odocs-pane-head">
				<Breadcrumb layout={ layout } selection={ selection } onSelect={ onSelect } blockTypes={ config.blockTypes } />
			</div>
			<div className="odocs-pane-scroll">{ body }</div>
		</div>
	);
}
