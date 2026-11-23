/**
 * Pure helpers for manipulating layouts.
 */

export const uid = ( prefix ) =>
	prefix + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 6 );

export const clone = ( value ) => JSON.parse( JSON.stringify( value ) );

/** Default settings for a block type from its schema. */
export function blockDefaults( blockTypes, columnTypes, type ) {
	const settings = {};
	const schema = blockTypes[ type ]?.settings || {};
	Object.keys( schema ).forEach( ( key ) => {
		settings[ key ] = clone( schema[ key ].default ?? '' );
	} );
	if ( type === 'items_table' ) {
		const enabled = [ 'description', 'quantity', 'unit_price', 'total' ];
		settings.columns = [
			...enabled,
			...Object.keys( columnTypes ).filter( ( k ) => ! enabled.includes( k ) ),
		].map( ( key ) => ( {
			key,
			label: '',
			width: 0,
			align: '',
			enabled: enabled.includes( key ) ? 1 : 0,
		} ) );
	}
	return settings;
}

export function newRow( count = 1 ) {
	const width = Math.round( ( 100 / count ) * 100 ) / 100;
	return {
		id: uid( 'r' ),
		settings: { background: '', text_color: '', padding: 0, margin_bottom: 5, border: 0, bleed: 0 },
		columns: Array.from( { length: count }, () => ( { width, valign: 'top', blocks: [] } ) ),
	};
}

/** Finds a block by id: returns { r, c, b } indexes or null. */
export function findBlock( layout, id ) {
	for ( let r = 0; r < layout.rows.length; r++ ) {
		const cols = layout.rows[ r ].columns;
		for ( let c = 0; c < cols.length; c++ ) {
			const b = cols[ c ].blocks.findIndex( ( block ) => block.id === id );
			if ( b !== -1 ) {
				return { r, c, b };
			}
		}
	}
	return null;
}

/** Moves a block to row/column/index. Returns a new layout. */
export function moveBlock( layout, id, r, c, index ) {
	const next = clone( layout );
	const from = findBlock( next, id );
	if ( ! from ) {
		return layout;
	}
	const [ block ] = next.rows[ from.r ].columns[ from.c ].blocks.splice( from.b, 1 );
	if ( from.r === r && from.c === c && from.b < index ) {
		index--;
	}
	next.rows[ r ].columns[ c ].blocks.splice( index, 0, block );
	return next;
}

/** Changes the number of columns of a row; blocks of removed columns move to the last column. */
export function setColumnCount( layout, r, count ) {
	const next = clone( layout );
	const row = next.rows[ r ];
	const width = Math.round( ( 100 / count ) * 100 ) / 100;
	while ( row.columns.length > count ) {
		const removed = row.columns.pop();
		row.columns[ row.columns.length - 1 ].blocks.push( ...removed.blocks );
	}
	while ( row.columns.length < count ) {
		row.columns.push( { width, valign: 'top', blocks: [] } );
	}
	row.columns.forEach( ( col ) => ( col.width = width ) );
	return next;
}
