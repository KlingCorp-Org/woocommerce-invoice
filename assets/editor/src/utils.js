/**
 * Pure helpers for free-form (version 2) layouts.
 *
 * Layout: { version: 2, sections: [ { id, kind: 'canvas'|'flow'|'footer', name, height,
 * keep_together, items: [ { id, type, settings, x, y, w, h, snap, locked, overflow } ] } ] }
 * All sizes in mm. Item order inside a section is the layer order (last = on top).
 */
import { __, sprintf } from '@wordpress/i18n';

/** CSS pixels per millimetre (96 dpi). */
export const PX = 96 / 25.4;

export const uid = ( prefix ) =>
	prefix +
	Date.now().toString( 36 ) +
	Math.random().toString( 36 ).slice( 2, 6 );

export const clone = ( value ) => JSON.parse( JSON.stringify( value ) );

export const round = ( v, step = 0.1 ) =>
	Math.round( Number( v ) / step ) * step;

/** Rounds to 0.01 mm to keep stored numbers tidy. */
export const tidy = ( v ) => Math.round( Number( v ) * 100 ) / 100;

export const isV2 = ( layout ) => !! layout && Array.isArray( layout.sections );

export const isFree = ( section ) => section && section.kind !== 'flow';

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
	if ( 'margin_bottom' in settings ) {
		settings.margin_bottom = 0;
	}
	return settings;
}

export function newItem( blockTypes, columnTypes, type, frame = {} ) {
	const [ w, h ] = blockTypes[ type ]?.size || [ 70, 15 ];
	return {
		id: uid( 'i' ),
		type,
		settings: blockDefaults( blockTypes, columnTypes, type ),
		x: 0,
		y: 0,
		w,
		h,
		snap: 1,
		locked: 0,
		overflow: 'grow',
		...frame,
	};
}

export function newSection( kind = 'canvas', name = '' ) {
	return {
		id: uid( 's' ),
		kind,
		name,
		height: kind === 'flow' ? 0 : 40,
		keep_together: 1,
		items: [],
	};
}

export function sectionLabel( section, index ) {
	if ( section.name ) {
		return section.name;
	}
	if ( section.kind === 'footer' ) {
		return __( 'Page footer', 'order-documents' );
	}
	return section.kind === 'flow'
		? __( 'Flowing section', 'order-documents' )
		: sprintf(
				/* translators: %d: section number */
				__( 'Section %d', 'order-documents' ),
				index + 1
		  );
}

export function sectionKindLabel( kind ) {
	if ( kind === 'flow' ) {
		return __( 'Grows with its content', 'order-documents' );
	}
	if ( kind === 'footer' ) {
		return __( 'Bottom of every page', 'order-documents' );
	}
	return __( 'Free-form', 'order-documents' );
}

/** Finds an item: { s, i, section, item } or null. */
export function findItem( layout, id ) {
	if ( ! isV2( layout ) ) {
		return null;
	}
	for ( let s = 0; s < layout.sections.length; s++ ) {
		const items = layout.sections[ s ].items;
		for ( let i = 0; i < items.length; i++ ) {
			if ( items[ i ].id === id ) {
				return {
					s,
					i,
					section: layout.sections[ s ],
					item: items[ i ],
				};
			}
		}
	}
	return null;
}

export const findSection = ( layout, id ) =>
	isV2( layout ) ? layout.sections.findIndex( ( s ) => s.id === id ) : -1;

export const allItems = ( layout ) =>
	isV2( layout ) ? layout.sections.flatMap( ( s ) => s.items ) : [];

/** Lowest item bottom in a section (mm). */
export const contentBottom = ( section ) =>
	section.items.reduce( ( m, it ) => Math.max( m, it.y + it.h ), 0 );

/** Applies `fn( item, section )` to the given items; returns a new layout. */
export function updateItems( layout, ids, fn ) {
	const set = new Set( ids );
	const next = clone( layout );
	next.sections.forEach( ( section ) =>
		section.items.forEach( ( item ) => set.has( item.id ) && fn( item, section ) )
	);
	return next;
}

/** Grows free-form sections so every item fits. */
export function fitSections( layout ) {
	layout.sections.forEach( ( section ) => {
		if ( isFree( section ) ) {
			section.height = tidy(
				Math.max( section.height, contentBottom( section ) )
			);
		}
	} );
	return layout;
}

export function setFrame( layout, id, frame ) {
	return fitSections(
		updateItems( layout, [ id ], ( item ) => {
			Object.keys( frame ).forEach(
				( k ) => ( item[ k ] = tidy( frame[ k ] ) )
			);
		} )
	);
}

export function insertItem( layout, s, item, index = null ) {
	const next = clone( layout );
	const items = next.sections[ s ].items;
	items.splice( index === null ? items.length : index, 0, item );
	return fitSections( next );
}

export function removeItems( layout, ids ) {
	const set = new Set( ids );
	const next = clone( layout );
	next.sections.forEach(
		( section ) =>
			( section.items = section.items.filter( ( it ) => ! set.has( it.id ) ) )
	);
	return next;
}

/** Duplicates items (placed one step lower right); returns [ layout, newIds ]. */
export function duplicateItems( layout, ids, offset = 4 ) {
	const next = clone( layout );
	const newIds = [];
	next.sections.forEach( ( section ) => {
		const copies = [];
		section.items.forEach( ( item ) => {
			if ( ids.includes( item.id ) ) {
				const copy = clone( item );
				copy.id = uid( 'i' );
				if ( isFree( section ) ) {
					copy.x = tidy( item.x + offset );
					copy.y = tidy( item.y + offset );
				}
				copies.push( [ item.id, copy ] );
				newIds.push( copy.id );
			}
		} );
		copies.forEach( ( [ afterId, copy ] ) => {
			const at = section.items.findIndex( ( it ) => it.id === afterId );
			section.items.splice(
				isFree( section ) ? section.items.length : at + 1,
				0,
				copy
			);
		} );
	} );
	return [ fitSections( next ), newIds ];
}

/**
 * Moves an item to a section at an index (layer position or flow position),
 * optionally with a new frame. Returns a new layout.
 */
export function moveItemTo( layout, id, s, index = null, frame = null ) {
	const pos = findItem( layout, id );
	if ( ! pos ) {
		return layout;
	}
	const next = clone( layout );
	const [ item ] = next.sections[ pos.s ].items.splice( pos.i, 1 );
	if ( frame ) {
		Object.keys( frame ).forEach( ( k ) => ( item[ k ] = tidy( frame[ k ] ) ) );
	}
	const items = next.sections[ s ].items;
	let at = index === null ? items.length : index;
	if ( pos.s === s && index !== null && index > pos.i ) {
		at -= 1;
	}
	items.splice( Math.max( 0, Math.min( items.length, at ) ), 0, item );
	return fitSections( next );
}

/**
 * Changes the layer of items: 'front', 'back', 'forward', 'backward'.
 */
export function reorderLayer( layout, ids, how ) {
	const set = new Set( ids );
	const next = clone( layout );
	next.sections.forEach( ( section ) => {
		if ( ! isFree( section ) ) {
			return;
		}
		const items = section.items;
		if ( ! items.some( ( it ) => set.has( it.id ) ) ) {
			return;
		}
		if ( how === 'front' || how === 'back' ) {
			const moving = items.filter( ( it ) => set.has( it.id ) );
			const rest = items.filter( ( it ) => ! set.has( it.id ) );
			section.items =
				how === 'front' ? [ ...rest, ...moving ] : [ ...moving, ...rest ];
			return;
		}
		if ( how === 'forward' ) {
			for ( let i = items.length - 2; i >= 0; i-- ) {
				if ( set.has( items[ i ].id ) && ! set.has( items[ i + 1 ].id ) ) {
					[ items[ i ], items[ i + 1 ] ] = [ items[ i + 1 ], items[ i ] ];
				}
			}
		} else {
			for ( let i = 1; i < items.length; i++ ) {
				if ( set.has( items[ i ].id ) && ! set.has( items[ i - 1 ].id ) ) {
					[ items[ i ], items[ i - 1 ] ] = [ items[ i - 1 ], items[ i ] ];
				}
			}
		}
	} );
	return next;
}

/** Bounding box of frames: { x, y, w, h }. */
export function bounds( frames ) {
	const x1 = Math.min( ...frames.map( ( f ) => f.x ) );
	const y1 = Math.min( ...frames.map( ( f ) => f.y ) );
	const x2 = Math.max( ...frames.map( ( f ) => f.x + f.w ) );
	const y2 = Math.max( ...frames.map( ( f ) => f.y + f.h ) );
	return { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
}

/**
 * Aligns items. With one item (or `toSection`) they align to the section;
 * otherwise to each other. how: left, center, right, top, middle, bottom.
 */
export function alignItems( layout, ids, how, width ) {
	const frames = ids
		.map( ( id ) => findItem( layout, id ) )
		.filter( ( p ) => p && isFree( p.section ) );
	if ( ! frames.length ) {
		return layout;
	}
	const single = frames.length === 1;
	const box = single
		? {
				x: 0,
				y: 0,
				w: width,
				h: frames[ 0 ].section.height,
		  }
		: bounds( frames.map( ( p ) => p.item ) );
	return updateItems( layout, ids, ( item, section ) => {
		if ( ! isFree( section ) ) {
			return;
		}
		if ( how === 'left' ) {
			item.x = box.x;
		} else if ( how === 'center' ) {
			item.x = box.x + ( box.w - item.w ) / 2;
		} else if ( how === 'right' ) {
			item.x = box.x + box.w - item.w;
		} else if ( how === 'top' ) {
			item.y = box.y;
		} else if ( how === 'middle' ) {
			item.y = box.y + ( box.h - item.h ) / 2;
		} else if ( how === 'bottom' ) {
			item.y = box.y + box.h - item.h;
		}
		item.x = tidy( item.x );
		item.y = tidy( Math.max( 0, item.y ) );
	} );
}

/** Spreads 3+ items evenly: axis 'x' or 'y'. */
export function distributeItems( layout, ids, axis ) {
	const list = ids
		.map( ( id ) => findItem( layout, id ) )
		.filter( ( p ) => p && isFree( p.section ) )
		.map( ( p ) => p.item );
	if ( list.length < 3 ) {
		return layout;
	}
	const pos = axis === 'x' ? 'x' : 'y';
	const size = axis === 'x' ? 'w' : 'h';
	const sorted = [ ...list ].sort( ( a, b ) => a[ pos ] - b[ pos ] );
	const start = sorted[ 0 ][ pos ];
	const end = Math.max( ...sorted.map( ( f ) => f[ pos ] + f[ size ] ) );
	const total = sorted.reduce( ( sum, f ) => sum + f[ size ], 0 );
	const gap = ( end - start - total ) / ( sorted.length - 1 );
	const target = {};
	let cursor = start;
	sorted.forEach( ( f ) => {
		target[ f.id ] = cursor;
		cursor += f[ size ] + gap;
	} );
	return updateItems( layout, ids, ( item ) => {
		if ( item.id in target ) {
			item[ pos ] = tidy( target[ item.id ] );
		}
	} );
}

export const snapValue = ( v, step ) =>
	step > 0 ? Math.round( v / step ) * step : v;

/**
 * Snaps a moving span [start, start+size] to the closest guide line, checking
 * its start, centre and end. Returns { value, line } or null.
 */
export function snapToLines( start, size, lines, threshold, edges = [ 0, 0.5, 1 ] ) {
	let best = null;
	edges.forEach( ( edge ) => {
		const at = start + size * edge;
		lines.forEach( ( line ) => {
			const d = Math.abs( line - at );
			if ( d <= threshold && ( ! best || d < best.d ) ) {
				best = { d, value: line - size * edge, line };
			}
		} );
	} );
	return best;
}

/** Guide lines (mm) for a section from its other items, edges and centre. */
export function guideLines( section, exclude, width ) {
	const xs = [ 0, width / 2, width ];
	const ys = [ 0 ];
	if ( section.kind !== 'flow' && section.height > 0 ) {
		ys.push( section.height / 2, section.height );
	}
	section.items.forEach( ( it ) => {
		if ( exclude.has( it.id ) || ! isFree( section ) ) {
			return;
		}
		xs.push( it.x, it.x + it.w / 2, it.x + it.w );
		ys.push( it.y, it.y + it.h / 2, it.y + it.h );
	} );
	return { xs, ys };
}

export function blockSummary( block ) {
	const s = block.settings || {};
	const text = s.heading || s.text || s.title || s.content || s.label || '';
	return String( text )
		.replace( /<[^>]*>/g, '' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, 40 );
}

/* ------------------------------------------------------------------
 * Converting a version 1 (rows and columns) layout
 * ------------------------------------------------------------------ */

/**
 * Builds a free-form layout from a rows layout and the measured positions of its rows and
 * blocks in the PDF (see the /measure endpoint), so it prints like before.
 *
 * @param {Object} layout Version 1 layout.
 * @param {Object} m      { content: { left, top }, contentWidth, marginBottom (mm),
 *                        rows: { [rowId]: { rect, radius } }, blocks: { [blockId]: rect } },
 *                        rects in CSS px with a page number.
 * @param {Object} types  Block types (for the size of components that were empty).
 * @return {Object} Version 2 layout.
 */
export function convertV1( layout, m, types = {} ) {
	const mm = ( px ) => round( px / PX );
	const GAP = 3 * PX; // Column padding of rows layouts.
	const rows = layout.rows || [];
	const isFixedFooter = ( b ) =>
		b.type === 'footer' && Number( b.settings?.fixed ) === 1;
	const tableRow = rows.findIndex( ( row ) =>
		row.columns.some( ( c ) =>
			c.blocks.some( ( b ) => b.type === 'items_table' )
		)
	);
	const contentWidth = m.contentWidth || 182 * PX;
	const before = tableRow === -1 ? rows : rows.slice( 0, tableRow );
	const after = tableRow === -1 ? [] : rows.slice( tableRow + 1 );
	let tableBlocks = tableRow === -1 ? [] : null;
	if ( tableRow !== -1 && rows[ tableRow ].columns.length === 1 ) {
		// One column holding the table and more: what is above the table goes to the top,
		// what is below it after the table.
		const row = rows[ tableRow ];
		const blocks = row.columns[ 0 ].blocks;
		const at = blocks.findIndex( ( b ) => b.type === 'items_table' );
		const part = ( list, suffix ) => ( {
			id: row.id + suffix,
			settings: { ...row.settings, background: '', border: 0 },
			columns: [ { width: 100, valign: 'top', blocks: list } ],
		} );
		if ( at > 0 ) {
			before.push( part( blocks.slice( 0, at ), '-above' ) );
		}
		if ( at < blocks.length - 1 ) {
			after.unshift( part( blocks.slice( at + 1 ), '-below' ) );
		}
		tableBlocks = [ blocks[ at ] ];
	}
	/** Top of the first measured row or block in a list of rows (px), or null. */
	const firstTop = ( list ) => {
		for ( const row of list ) {
			if ( m.rows[ row.id ]?.rect ) {
				return m.rows[ row.id ].rect.top;
			}
			for ( const col of row.columns ) {
				for ( const b of col.blocks ) {
					if ( m.blocks[ b.id ] ) {
						return m.blocks[ b.id ].top;
					}
				}
			}
		}
		return null;
	};

	const item = ( block, r, origin, extra = {} ) => {
		const settings = clone( block.settings || {} );
		if ( 'margin_bottom' in settings ) {
			settings.margin_bottom = 0;
		}
		// A logo fills its box, so the box is the image itself.
		const img =
			block.type === 'logo' && r.img && ! Number( settings.max_height )
				? r.img
				: null;
		return {
			id: block.id,
			type: block.type,
			settings,
			x: mm( r.left - m.content.left ),
			y: Math.max( 0, mm( ( img || r ).top - origin ) ),
			w: mm( r.width ),
			h: Math.max( 1, mm( ( img || r ).height ) ),
			snap: 1,
			locked: 0,
			overflow: 'grow',
			...extra,
		};
	};

	/**
	 * Places rows in a free-form section. Rows on a later page are stacked under the
	 * previous one; blocks that rendered nothing go where they would sit in their column.
	 */
	const place = ( section, list, start ) => {
		let origin = start;
		let page = null;
		let bottom = start;
		list.forEach( ( row ) => {
			const s = row.settings || {};
			const blocks = row.columns.flatMap( ( c ) =>
				c.blocks.filter( ( b ) => ! isFixedFooter( b ) )
			);
			if ( ! blocks.length ) {
				return;
			}
			let rect = m.rows[ row.id ]?.rect;
			if ( rect && page !== null && rect.page !== page ) {
				// Continued on the next page: keep it right under the previous row.
				origin = rect.top - ( bottom - origin );
			}
			if ( rect ) {
				page = rect.page;
			} else {
				rect = {
					left: m.content.left,
					top: bottom,
					width: contentWidth,
					height: 0,
				};
			}
			if ( s.background || Number( s.border ) ) {
				section.items.push( {
					id: uid( 'i' ),
					type: 'shape',
					settings: {
						fill: s.background || '',
						border_color: Number( s.border ) ? '#d1d5db' : '',
						border_width: Number( s.border ) ? 0.75 : 0,
						radius: mm( m.rows[ row.id ]?.radius || 0 ),
					},
					x: mm( rect.left - m.content.left ),
					y: Math.max( 0, mm( rect.top - origin ) ),
					w: mm( rect.width ),
					h: Math.max( 1, mm( rect.height ) ),
					snap: 1,
					locked: 0,
					overflow: 'clip',
				} );
			}
			let rowBottom = rect.top + rect.height;
			let colLeft = rect.left;
			row.columns.forEach( ( col, c ) => {
				const colWidth = ( Number( col.width ) / 100 ) * rect.width;
				const innerLeft = colLeft + ( c > 0 ? GAP : 0 );
				const innerWidth =
					colWidth -
					( c > 0 ? GAP : 0 ) -
					( c < row.columns.length - 1 ? GAP : 0 );
				let cursor = rect.top;
				col.blocks.forEach( ( block ) => {
					if ( isFixedFooter( block ) ) {
						return;
					}
					let r = m.blocks[ block.id ];
					if ( r && r.page !== page && page !== null ) {
						r = null;
					}
					if ( ! r ) {
						const h = ( types[ block.type ]?.size?.[ 1 ] || 10 ) * PX;
						r = {
							left: innerLeft,
							top: cursor,
							width: innerWidth,
							height: h,
						};
					}
					const extra = {};
					if ( s.text_color && ! block.settings?.text_color ) {
						extra.settings = {
							...clone( block.settings || {} ),
							text_color: s.text_color,
							...( 'margin_bottom' in ( block.settings || {} )
								? { margin_bottom: 0 }
								: {} ),
						};
					}
					section.items.push( item( block, r, origin, extra ) );
					cursor =
						r.top +
						r.height +
						( Number( block.settings?.margin_bottom ) || 0 ) * PX;
					rowBottom = Math.max( rowBottom, r.top + r.height );
				} );
				colLeft += colWidth;
			} );
			bottom = rowBottom + ( Number( s.margin_bottom ) || 0 ) * PX;
		} );
		section.height = Math.max( 0, mm( bottom - origin ) );
	};

	const top = newSection( 'canvas', __( 'Top', 'order-documents' ) );
	const flow = newSection( 'flow', __( 'Products', 'order-documents' ) );
	const below = newSection(
		'canvas',
		__( 'Below products', 'order-documents' )
	);
	const footer = newSection( 'footer', __( 'Page footer', 'order-documents' ) );
	const sections = [];

	place( top, before, m.content.top );
	if ( tableRow !== -1 ) {
		const tableBlock = rows[ tableRow ].columns
			.flatMap( ( c ) => c.blocks )
			.find( ( b ) => b.type === 'items_table' );
		const tableRect =
			( tableBlocks && m.blocks[ tableBlock.id ] ) ||
			m.rows[ rows[ tableRow ].id ]?.rect;
		if ( tableRect ) {
			top.height = Math.max(
				top.height,
				mm( tableRect.top - m.content.top )
			);
		}
	}
	if ( top.items.length || tableRow === -1 ) {
		sections.push( top );
	}
	if ( tableRow !== -1 ) {
		const row = rows[ tableRow ];
		const rect = m.rows[ row.id ]?.rect;
		const columns = tableBlocks
			? [ { blocks: tableBlocks } ]
			: row.columns;
		columns.forEach( ( col ) =>
			col.blocks.forEach( ( block, b ) => {
				if ( isFixedFooter( block ) ) {
					return;
				}
				const r = m.blocks[ block.id ] ||
					( rect && { ...rect, height: 20 * PX } ) || {
						left: m.content.left,
						top: 0,
						width: contentWidth,
						height: 20 * PX,
					};
				const settings = clone( block.settings || {} );
				const last = b === col.blocks.length - 1;
				settings.margin_bottom =
					( Number( settings.margin_bottom ) || 0 ) +
					( last ? Number( row.settings?.margin_bottom ) || 0 : 0 );
				flow.items.push( {
					...item( block, r, 0 ),
					settings,
					y: 0,
				} );
			} )
		);
		sections.push( flow );
		if ( after.length ) {
			const first = firstTop( after );
			place( below, after, first === null ? 0 : first );
			below.height = round( below.height + 2 );
			if ( below.items.length ) {
				sections.push( below );
			}
		}
	}

	// Fixed footers: at the bottom of every page, inside the bottom margin.
	const footers = rows.flatMap( ( row ) =>
		row.columns.flatMap( ( c ) => c.blocks.filter( isFixedFooter ) )
	);
	if ( footers.length ) {
		footer.height = round( Math.max( m.marginBottom, 10 ) );
		const gap = footer.height - Math.max( 2, footer.height - 6 );
		footers.forEach( ( block ) => {
			const r = m.blocks[ block.id ] || {
				left: m.content.left,
				top: 0,
				width: contentWidth,
				height: 12 * PX,
			};
			const it = item( block, r, 0 );
			it.y = round( Math.max( 0, footer.height - gap - it.h ) );
			footer.items.push( it );
		} );
		sections.push( footer );
	}
	return { version: 2, sections };
}
