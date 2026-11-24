/**
 * Right-hand settings pane: document style, row settings or block settings,
 * depending on the selection.
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
	justifyTop,
	justifyCenterVertical,
	justifyBottom,
	alignLeft,
	alignCenter,
	alignRight,
	styles as stylesIcon,
} from '@wordpress/icons';
import Field, { ColorField, NumberField } from './fields';
import { ColumnGlyph } from './RowLayoutPicker';
import StylePanel from './StylePanel';
import {
	clone,
	findBlock,
	findRow,
	ROW_PRESETS,
	sameWidths,
	setColumnWidths,
} from './utils';

const COMMON = [ 'align', 'font_size', 'margin_bottom', 'text_color', 'box' ];

function Breadcrumb( { layout, selected, onSelect, blockTypes } ) {
	const crumbs = [
		{ label: __( 'Document', 'order-documents' ), sel: null },
	];
	if ( selected?.kind === 'row' ) {
		const r = findRow( layout, selected.id );
		crumbs.push( {
			label: sprintf(
				/* translators: %d: row number */ __(
					'Row %d',
					'order-documents'
				),
				r + 1
			),
			sel: selected,
		} );
	} else if ( selected?.kind === 'block' ) {
		const pos = findBlock( layout, selected.id );
		if ( pos ) {
			const row = layout.rows[ pos.r ];
			const block = row.columns[ pos.c ].blocks[ pos.b ];
			crumbs.push( {
				label: sprintf(
					/* translators: %d: row number */ __(
						'Row %d',
						'order-documents'
					),
					pos.r + 1
				),
				sel: { kind: 'row', id: row.id },
			} );
			crumbs.push( {
				label: blockTypes[ block.type ]?.label || block.type,
				sel: selected,
			} );
		}
	}
	return (
		<nav
			className="odocs-breadcrumb"
			aria-label={ __( 'Selection', 'order-documents' ) }
		>
			{ crumbs.map( ( c, i ) => {
				const last = i === crumbs.length - 1;
				return (
					<span key={ i } className="odocs-crumb">
						{ last ? (
							<span aria-current="location">{ c.label }</span>
						) : (
							<button
								type="button"
								onClick={ () => onSelect( c.sel ) }
							>
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

function RowSettings( {
	layout,
	setLayout,
	rowIndex,
	palette,
	onDuplicate,
	onDelete,
} ) {
	const row = layout.rows[ rowIndex ];
	const set = ( fn, key ) => {
		const next = clone( layout );
		fn( next.rows[ rowIndex ] );
		setLayout( next, key && row.id + key );
	};
	const widths = row.columns.map( ( c ) => Number( c.width ) );
	const VALIGN = [
		{
			value: 'top',
			icon: justifyTop,
			label: __( 'Top', 'order-documents' ),
		},
		{
			value: 'middle',
			icon: justifyCenterVertical,
			label: __( 'Middle', 'order-documents' ),
		},
		{
			value: 'bottom',
			icon: justifyBottom,
			label: __( 'Bottom', 'order-documents' ),
		},
	];
	return (
		<>
			<PaneHeader
				icon="columns"
				title={ sprintf(
					/* translators: %d: row number */ __(
						'Row %d',
						'order-documents'
					),
					rowIndex + 1
				) }
				subtitle={
					row.columns.length > 1
						? sprintf(
								/* translators: %d: number of columns */ __(
									'%d columns side by side.',
									'order-documents'
								),
								row.columns.length
						  )
						: __( 'One full-width column.', 'order-documents' )
				}
			/>
			<PanelBody title={ __( 'Columns', 'order-documents' ) }>
				<ToggleGroupControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					isBlock
					label={ __( 'Number of columns', 'order-documents' ) }
					value={ String( row.columns.length ) }
					onChange={ ( v ) =>
						setLayout(
							setColumnWidths(
								layout,
								rowIndex,
								ROW_PRESETS[ v ][ 0 ]
							)
						)
					}
				>
					{ [ 1, 2, 3 ].map( ( n ) => (
						<ToggleGroupControlOption
							key={ n }
							value={ String( n ) }
							label={ String( n ) }
						/>
					) ) }
				</ToggleGroupControl>
				{ row.columns.length > 1 && (
					<div
						className="odocs-presets"
						role="group"
						aria-label={ __( 'Column widths', 'order-documents' ) }
					>
						{ ROW_PRESETS[ row.columns.length ].map( ( preset ) => (
							<button
								key={ preset.join( '-' ) }
								type="button"
								className={
									'odocs-preset' +
									( sameWidths( preset, widths )
										? ' is-active'
										: '' )
								}
								onClick={ () =>
									setLayout(
										setColumnWidths(
											layout,
											rowIndex,
											preset
										)
									)
								}
								title={ preset.map( Math.round ).join( ' / ' ) }
							>
								<ColumnGlyph widths={ preset } />
								<span>
									{ preset.map( Math.round ).join( '/' ) }
								</span>
							</button>
						) ) }
					</div>
				) }
				{ row.columns.map( ( col, c ) => (
					<div key={ c } className="odocs-colsettings">
						<span className="odocs-colsettings-title">
							{ sprintf(
								/* translators: %d: column number */ __(
									'Column %d',
									'order-documents'
								),
								c + 1
							) }
						</span>
						<div className="odocs-two">
							{ row.columns.length > 1 ? (
								<NumberField
									label={ __( 'Width %', 'order-documents' ) }
									value={ col.width }
									min={ 5 }
									max={ 100 }
									onChange={ ( v ) =>
										set(
											( r ) =>
												( r.columns[ c ].width =
													Math.max( 5, v ) ),
											'w' + c
										)
									}
								/>
							) : (
								<span />
							) }
							<ToggleGroupControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __( 'Vertical', 'order-documents' ) }
								value={ col.valign || 'top' }
								onChange={ ( v ) =>
									set(
										( r ) => ( r.columns[ c ].valign = v )
									)
								}
							>
								{ VALIGN.map( ( o ) => (
									<ToggleGroupControlOptionIcon
										key={ o.value }
										value={ o.value }
										icon={ o.icon }
										label={ o.label }
									/>
								) ) }
							</ToggleGroupControl>
						</div>
					</div>
				) ) }
			</PanelBody>
			<PanelBody title={ __( 'Appearance', 'order-documents' ) }>
				<div className="odocs-fields">
					<ColorField
						label={ __( 'Background', 'order-documents' ) }
						palette={ palette }
						value={ row.settings.background }
						onChange={ ( v ) =>
							set( ( r ) => ( r.settings.background = v ) )
						}
					/>
					<ColorField
						label={ __( 'Text colour', 'order-documents' ) }
						palette={ palette }
						value={ row.settings.text_color }
						onChange={ ( v ) =>
							set( ( r ) => ( r.settings.text_color = v ) )
						}
					/>
					<div className="odocs-two">
						<NumberField
							label={ __( 'Padding (mm)', 'order-documents' ) }
							min={ 0 }
							max={ 30 }
							step={ 0.5 }
							value={ row.settings.padding }
							onChange={ ( v ) =>
								set(
									( r ) => ( r.settings.padding = v ),
									'pad'
								)
							}
						/>
						<NumberField
							label={ __(
								'Space below (mm)',
								'order-documents'
							) }
							min={ 0 }
							max={ 60 }
							step={ 0.5 }
							value={ row.settings.margin_bottom }
							onChange={ ( v ) =>
								set(
									( r ) => ( r.settings.margin_bottom = v ),
									'mb'
								)
							}
						/>
					</div>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Border', 'order-documents' ) }
						checked={ !! Number( row.settings.border ) }
						onChange={ ( v ) =>
							set( ( r ) => ( r.settings.border = v ? 1 : 0 ) )
						}
					/>
				</div>
			</PanelBody>
			<div className="odocs-card-actions">
				<Button
					variant="secondary"
					icon={ copy }
					onClick={ onDuplicate }
				>
					{ __( 'Duplicate row', 'order-documents' ) }
				</Button>
				<Button
					variant="secondary"
					icon={ trash }
					isDestructive
					onClick={ onDelete }
				>
					{ __( 'Delete row', 'order-documents' ) }
				</Button>
			</div>
		</>
	);
}

function BlockSettings( {
	layout,
	setLayout,
	pos,
	config,
	palette,
	onDuplicate,
	onDelete,
} ) {
	const block = layout.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ];
	const def = config.blockTypes[ block.type ];
	if ( ! def ) {
		return (
			<p className="odocs-help odocs-pad">
				{ __( 'Unknown block type.', 'order-documents' ) }
			</p>
		);
	}
	const setSetting = ( key, value ) => {
		const next = clone( layout );
		next.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ].settings[ key ] =
			value;
		setLayout( next, block.id + ':' + key );
	};
	const keys = Object.keys( def.settings );
	const own = keys.filter(
		( k ) => ! COMMON.includes( k ) && def.settings[ k ].type !== 'columns'
	);
	const columnKeys = keys.filter(
		( k ) => def.settings[ k ].type === 'columns'
	);
	const common = COMMON.filter( ( k ) => def.settings[ k ] );
	const where =
		layout.rows[ pos.r ].columns.length > 1
			? sprintf(
					/* translators: 1: row number, 2: column number */ __(
						'Row %1$d, column %2$d',
						'order-documents'
					),
					pos.r + 1,
					pos.c + 1
			  )
			: sprintf(
					/* translators: %d: row number */ __(
						'Row %d',
						'order-documents'
					),
					pos.r + 1
			  );
	const renderField = ( key ) => (
		<Field
			key={ key }
			field={ def.settings[ key ] }
			value={ block.settings[ key ] }
			palette={ palette }
			onChange={ ( v ) => setSetting( key, v ) }
		/>
	);
	return (
		<>
			<PaneHeader
				icon={ def.icon }
				title={ def.label }
				subtitle={ where }
			/>
			{ own.length > 0 && (
				<PanelBody title={ __( 'Content', 'order-documents' ) }>
					<div className="odocs-fields">
						{ own.map( renderField ) }
					</div>
				</PanelBody>
			) }
			{ columnKeys.map( ( key ) => (
				<PanelBody
					key={ key }
					title={
						def.settings[ key ].label ||
						__( 'Columns', 'order-documents' )
					}
				>
					<ColumnsEditor
						columns={ block.settings[ key ] || [] }
						columnTypes={ config.columnTypes }
						onChange={ ( v ) => setSetting( key, v ) }
					/>
				</PanelBody>
			) ) }
			{ common.length > 0 && (
				<PanelBody
					title={ __( 'Appearance', 'order-documents' ) }
					initialOpen={ own.length === 0 }
				>
					<div className="odocs-fields">
						{ common.includes( 'align' ) && renderField( 'align' ) }
						<div className="odocs-two">
							{ common.includes( 'font_size' ) &&
								renderField( 'font_size' ) }
							{ common.includes( 'margin_bottom' ) &&
								renderField( 'margin_bottom' ) }
						</div>
						{ common.includes( 'text_color' ) &&
							renderField( 'text_color' ) }
						{ common.includes( 'box' ) && renderField( 'box' ) }
					</div>
				</PanelBody>
			) }
			<div className="odocs-card-actions">
				<Button
					variant="secondary"
					icon={ copy }
					onClick={ onDuplicate }
				>
					{ __( 'Duplicate', 'order-documents' ) }
				</Button>
				<Button
					variant="secondary"
					icon={ trash }
					isDestructive
					onClick={ onDelete }
				>
					{ __( 'Delete', 'order-documents' ) }
				</Button>
			</div>
		</>
	);
}

export default function Inspector( props ) {
	const {
		layout,
		selected,
		onSelect,
		config,
		style,
		setStyle,
		effectiveStyle,
	} = props;
	let body = null;
	if ( selected?.kind === 'row' ) {
		const r = findRow( layout, selected.id );
		if ( r !== -1 ) {
			body = <RowSettings { ...props } rowIndex={ r } />;
		}
	} else if ( selected?.kind === 'block' ) {
		const pos = findBlock( layout, selected.id );
		if ( pos ) {
			body = (
				<BlockSettings key={ selected.id } { ...props } pos={ pos } />
			);
		}
	}
	if ( ! body ) {
		body = (
			<>
				<PaneHeader
					icon={ stylesIcon }
					title={ __( 'Document style', 'order-documents' ) }
					subtitle={ __(
						'Colours, fonts and margins for all documents.',
						'order-documents'
					) }
				/>
				<StylePanel
					fields={ config.designFields }
					style={ style }
					setStyle={ setStyle }
					effective={ effectiveStyle }
				/>
				<p className="odocs-help odocs-pad">
					{ __(
						'Tip: click any part of the page to change it.',
						'order-documents'
					) }
				</p>
			</>
		);
	}
	return (
		<div className="odocs-settings">
			<div className="odocs-pane-head">
				<Breadcrumb
					layout={ layout }
					selected={ selected }
					onSelect={ onSelect }
					blockTypes={ config.blockTypes }
				/>
			</div>
			<div className="odocs-pane-scroll">{ body }</div>
		</div>
	);
}
