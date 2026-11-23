/**
 * Settings of the selected block or row.
 */
import { useState } from '@wordpress/element';
import { Button, CheckboxControl, SelectControl, TextControl, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Field, { ColorField } from './fields';
import { clone, findBlock } from './utils';

function ColumnsEditor( { columns, columnTypes, onChange } ) {
	const [ dragIndex, setDragIndex ] = useState( null );
	const update = ( i, changes ) => {
		const next = clone( columns );
		next[ i ] = { ...next[ i ], ...changes };
		onChange( next );
	};
	const move = ( from, to ) => {
		const next = clone( columns );
		next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
		onChange( next );
	};
	return (
		<div className="odocs-columns-editor">
			<p className="odocs-help">{ __( 'Drag to reorder. Width 0 = automatic.', 'order-documents' ) }</p>
			{ columns.map( ( col, i ) => {
				const def = columnTypes[ col.key ] || { label: col.key };
				return (
					<div
						key={ col.key }
						className={ 'odocs-column-item' + ( col.enabled ? ' is-enabled' : '' ) }
						draggable
						onDragStart={ () => setDragIndex( i ) }
						onDragOver={ ( e ) => e.preventDefault() }
						onDrop={ () => {
							if ( dragIndex !== null && dragIndex !== i ) {
								move( dragIndex, i );
							}
							setDragIndex( null );
						} }
					>
						<div className="odocs-column-head">
							<span className="odocs-drag-handle dashicons dashicons-menu" />
							<CheckboxControl
								__nextHasNoMarginBottom
								label={ def.label }
								checked={ !! col.enabled }
								onChange={ ( checked ) => update( i, { enabled: checked ? 1 : 0 } ) }
							/>
						</div>
						{ !! col.enabled && (
							<div className="odocs-column-props">
								<TextControl __nextHasNoMarginBottom label={ __( 'Heading', 'order-documents' ) } placeholder={ def.label } value={ col.label } onChange={ ( v ) => update( i, { label: v } ) } />
								<TextControl __nextHasNoMarginBottom type="number" min={ 0 } max={ 80 } label={ __( 'Width %', 'order-documents' ) } value={ col.width } onChange={ ( v ) => update( i, { width: Number( v ) || 0 } ) } />
								<SelectControl
									__nextHasNoMarginBottom
									label={ __( 'Align', 'order-documents' ) }
									value={ col.align }
									options={ [
										{ value: '', label: __( 'Default', 'order-documents' ) },
										{ value: 'left', label: __( 'Left', 'order-documents' ) },
										{ value: 'center', label: __( 'Center', 'order-documents' ) },
										{ value: 'right', label: __( 'Right', 'order-documents' ) },
									] }
									onChange={ ( v ) => update( i, { align: v } ) }
								/>
							</div>
						) }
					</div>
				);
			} ) }
		</div>
	);
}

function RowInspector( { layout, setLayout, rowIndex } ) {
	const row = layout.rows[ rowIndex ];
	const set = ( fn ) => {
		const next = clone( layout );
		fn( next.rows[ rowIndex ] );
		setLayout( next );
	};
	return (
		<div className="odocs-inspector">
			<h3>{ sprintf( /* translators: %d: row number */ __( 'Row %d', 'order-documents' ), rowIndex + 1 ) }</h3>
			<ColorField label={ __( 'Background', 'order-documents' ) } value={ row.settings.background } onChange={ ( v ) => set( ( r ) => ( r.settings.background = v ) ) } />
			<ColorField label={ __( 'Text colour', 'order-documents' ) } value={ row.settings.text_color } onChange={ ( v ) => set( ( r ) => ( r.settings.text_color = v ) ) } />
			<Field field={ { type: 'number', label: __( 'Padding (mm)', 'order-documents' ), min: 0, max: 30, step: 0.5 } } value={ row.settings.padding } onChange={ ( v ) => set( ( r ) => ( r.settings.padding = v ) ) } />
			<Field field={ { type: 'number', label: __( 'Space below (mm)', 'order-documents' ), min: 0, max: 60, step: 0.5 } } value={ row.settings.margin_bottom } onChange={ ( v ) => set( ( r ) => ( r.settings.margin_bottom = v ) ) } />
			<Field field={ { type: 'checkbox', label: __( 'Border', 'order-documents' ) } } value={ row.settings.border } onChange={ ( v ) => set( ( r ) => ( r.settings.border = v ) ) } />
			<h4>{ __( 'Columns', 'order-documents' ) }</h4>
			{ row.columns.map( ( col, c ) => (
				<div key={ c } className="odocs-col-settings">
					<TextControl
						__nextHasNoMarginBottom
						type="number"
						min={ 5 }
						max={ 100 }
						label={ sprintf( /* translators: %d: column number */ __( 'Column %d width %%', 'order-documents' ), c + 1 ) }
						value={ col.width }
						onChange={ ( v ) => set( ( r ) => ( r.columns[ c ].width = Number( v ) || 5 ) ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Vertical alignment', 'order-documents' ) }
						value={ col.valign || 'top' }
						options={ [
							{ value: 'top', label: __( 'Top', 'order-documents' ) },
							{ value: 'middle', label: __( 'Middle', 'order-documents' ) },
							{ value: 'bottom', label: __( 'Bottom', 'order-documents' ) },
						] }
						onChange={ ( v ) => set( ( r ) => ( r.columns[ c ].valign = v ) ) }
					/>
				</div>
			) ) }
		</div>
	);
}

export default function Inspector( { layout, setLayout, selected, config, onSelect } ) {
	if ( ! selected ) {
		return <p className="odocs-help">{ __( 'Select a block in the outline or click it in the preview to change its settings.', 'order-documents' ) }</p>;
	}
	if ( selected.kind === 'row' ) {
		const r = layout.rows.findIndex( ( row ) => row.id === selected.id );
		return r === -1 ? null : <RowInspector layout={ layout } setLayout={ setLayout } rowIndex={ r } />;
	}
	const pos = findBlock( layout, selected.id );
	if ( ! pos ) {
		return null;
	}
	const block = layout.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ];
	const def = config.blockTypes[ block.type ];
	if ( ! def ) {
		return <Notice status="warning" isDismissible={ false }>{ __( 'Unknown block type.', 'order-documents' ) }</Notice>;
	}
	const setSetting = ( key, value ) => {
		const next = clone( layout );
		next.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ].settings[ key ] = value;
		setLayout( next );
	};
	const remove = () => {
		const next = clone( layout );
		next.rows[ pos.r ].columns[ pos.c ].blocks.splice( pos.b, 1 );
		setLayout( next );
		onSelect( null );
	};
	const duplicate = () => {
		const next = clone( layout );
		const copy = clone( block );
		copy.id = 'b' + Date.now().toString( 36 );
		next.rows[ pos.r ].columns[ pos.c ].blocks.splice( pos.b + 1, 0, copy );
		setLayout( next );
		onSelect( { kind: 'block', id: copy.id } );
	};
	const common = [ 'align', 'font_size', 'margin_bottom', 'text_color' ];
	const own = Object.keys( def.settings ).filter( ( k ) => ! common.includes( k ) );
	return (
		<div className="odocs-inspector">
			<h3>
				<span className={ 'dashicons dashicons-' + def.icon } /> { def.label }
			</h3>
			{ own.map( ( key ) =>
				def.settings[ key ].type === 'columns' ? (
					<ColumnsEditor key={ key } columns={ block.settings[ key ] || [] } columnTypes={ config.columnTypes } onChange={ ( v ) => setSetting( key, v ) } />
				) : (
					<Field key={ key } field={ def.settings[ key ] } value={ block.settings[ key ] } onChange={ ( v ) => setSetting( key, v ) } />
				)
			) }
			<h4>{ __( 'Appearance', 'order-documents' ) }</h4>
			{ common.map( ( key ) => (
				<Field key={ key } field={ def.settings[ key ] } value={ block.settings[ key ] } onChange={ ( v ) => setSetting( key, v ) } />
			) ) }
			<div className="odocs-inspector-actions">
				<Button variant="secondary" size="small" icon="admin-page" onClick={ duplicate }>{ __( 'Duplicate', 'order-documents' ) }</Button>
				<Button variant="secondary" size="small" icon="trash" isDestructive onClick={ remove }>{ __( 'Remove block', 'order-documents' ) }</Button>
			</div>
		</div>
	);
}
