/**
 * Pure helpers for manipulating layouts.
 */

export const uid = ( prefix ) =>
	prefix +
	Date.now().toString( 36 ) +
	Math.random().toString( 36 ).slice( 2, 6 );

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
			...Object.keys( columnTypes ).filter(
				( k ) => ! enabled.includes( k )
			),
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
		settings: {
			background: '',
			text_color: '',
			padding: 0,
			margin_bottom: 5,
			border: 0,
			bleed: 0,
		},
		columns: Array.from( { length: count }, () => ( {
			width,
			valign: 'top',
			blocks: [],
		} ) ),
	};
}

/** Finds a block by id: returns { r, c, b } indexes or null. */
export function findBlock( layout, id ) {
	for ( let r = 0; r < layout.rows.length; r++ ) {
		const cols = layout.rows[ r ].columns;
		for ( let c = 0; c < cols.length; c++ ) {
			const b = cols[ c ].blocks.findIndex(
				( block ) => block.id === id
			);
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
	const [ block ] = next.rows[ from.r ].columns[ from.c ].blocks.splice(
		from.b,
		1
	);
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

/** Column width presets per column count (percentages). */
export const ROW_PRESETS = {
	1: [ [ 100 ] ],
	2: [
		[ 50, 50 ],
		[ 33.33, 66.67 ],
		[ 66.67, 33.33 ],
		[ 25, 75 ],
		[ 75, 25 ],
	],
	3: [
		[ 33.33, 33.33, 33.34 ],
		[ 25, 50, 25 ],
		[ 50, 25, 25 ],
		[ 25, 25, 50 ],
	],
};

/** A new row with the given column widths. */
export function rowWithWidths( widths ) {
	const row = newRow( widths.length );
	row.columns.forEach( ( col, i ) => ( col.width = widths[ i ] ) );
	return row;
}

/** Sets the widths (and so the count) of a row's columns. Returns a new layout. */
export function setColumnWidths( layout, r, widths ) {
	const next = setColumnCount( layout, r, widths.length );
	next.rows[ r ].columns.forEach( ( col, i ) => ( col.width = widths[ i ] ) );
	return next;
}

export function sameWidths( a, b ) {
	return (
		a.length === b.length &&
		a.every( ( w, i ) => Math.abs( Number( w ) - Number( b[ i ] ) ) < 0.6 )
	);
}

export function findRow( layout, id ) {
	return layout.rows.findIndex( ( row ) => row.id === id );
}

/** Gives every block and the row itself fresh ids. */
function reId( row ) {
	row.id = uid( 'r' );
	row.columns.forEach( ( col ) =>
		col.blocks.forEach( ( block ) => ( block.id = uid( 'b' ) ) )
	);
	return row;
}

export function insertBlock( layout, r, c, index, block ) {
	const next = clone( layout );
	next.rows[ r ].columns[ c ].blocks.splice( index, 0, block );
	return next;
}

export function removeBlock( layout, id ) {
	const pos = findBlock( layout, id );
	if ( ! pos ) {
		return layout;
	}
	const next = clone( layout );
	next.rows[ pos.r ].columns[ pos.c ].blocks.splice( pos.b, 1 );
	return next;
}

/** Returns [ layout, newId ]. */
export function duplicateBlock( layout, id ) {
	const pos = findBlock( layout, id );
	if ( ! pos ) {
		return [ layout, null ];
	}
	const next = clone( layout );
	const copy = clone( next.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ] );
	copy.id = uid( 'b' );
	next.rows[ pos.r ].columns[ pos.c ].blocks.splice( pos.b + 1, 0, copy );
	return [ next, copy.id ];
}

/**
 * Moves a block one step up (-1) or down (+1). At the edge of a column it
 * continues into the neighbouring column/row.
 */
export function moveBlockBy( layout, id, dir ) {
	const pos = findBlock( layout, id );
	if ( ! pos ) {
		return layout;
	}
	const column = layout.rows[ pos.r ].columns[ pos.c ];
	const target = pos.b + dir;
	if ( target >= 0 && target < column.blocks.length ) {
		return moveBlock(
			layout,
			id,
			pos.r,
			pos.c,
			dir > 0 ? target + 1 : target
		);
	}
	const slots = [];
	layout.rows.forEach( ( row, r ) =>
		row.columns.forEach( ( col, c ) =>
			slots.push( { r, c, length: col.blocks.length } )
		)
	);
	const here = slots.findIndex( ( s ) => s.r === pos.r && s.c === pos.c );
	const to = slots[ here + dir ];
	if ( ! to ) {
		return layout;
	}
	return moveBlock( layout, id, to.r, to.c, dir > 0 ? 0 : to.length );
}

export function moveRow( layout, from, to ) {
	if ( to < 0 || to >= layout.rows.length || from === to ) {
		return layout;
	}
	const next = clone( layout );
	next.rows.splice( to, 0, next.rows.splice( from, 1 )[ 0 ] );
	return next;
}

/** Returns [ layout, newId ]. */
export function duplicateRow( layout, r ) {
	const next = clone( layout );
	const copy = reId( clone( next.rows[ r ] ) );
	next.rows.splice( r + 1, 0, copy );
	return [ next, copy.id ];
}

export function insertRow( layout, index, row ) {
	const next = clone( layout );
	next.rows.splice( index, 0, row );
	return next;
}

/** A short, human hint of what a block shows (heading, text…). */
export function blockSummary( block ) {
	const s = block.settings || {};
	const text = s.heading || s.text || s.title || s.content || s.label || '';
	return String( text )
		.replace( /<[^>]*>/g, '' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, 40 );
}

/** Where a new block goes: after the selection, else at the end of the last column. */
export function insertionPoint( layout, selected ) {
	if ( selected?.kind === 'block' ) {
		const pos = findBlock( layout, selected.id );
		if ( pos ) {
			return { r: pos.r, c: pos.c, index: pos.b + 1 };
		}
	}
	let r = layout.rows.length - 1;
	if ( selected?.kind === 'row' ) {
		const found = findRow( layout, selected.id );
		r = found === -1 ? r : found;
	}
	if ( r < 0 ) {
		return null;
	}
	const c = layout.rows[ r ].columns.length - 1;
	return { r, c, index: layout.rows[ r ].columns[ c ].blocks.length };
}
