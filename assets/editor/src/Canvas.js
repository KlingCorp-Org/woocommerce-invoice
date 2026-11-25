/**
 * The page canvas: a same-origin preview iframe that is updated in place (DOM morph),
 * with an interaction layer on top for selecting, moving, resizing and inserting
 * components. Positions come from the layout, so moves show instantly; the server
 * preview only refreshes the content.
 */
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { Button, Dashicon, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	copy,
	trash,
	lock,
	unlock,
	arrowUp,
	arrowDown,
	grid as gridIcon,
} from '@wordpress/icons';
import {
	PX,
	bounds,
	contentBottom,
	findItem,
	guideLines,
	isFree,
	moveItemTo,
	sectionKindLabel,
	sectionLabel,
	snapToLines,
	snapValue,
	tidy,
	clone,
	fitSections,
} from './utils';

export const DRAG_TYPE = 'application/x-odocs-block';

/** Minimal DOM morph: updates `from` to match `to`, reusing equal nodes. */
function morph( from, to ) {
	if ( from.nodeType === 1 ) {
		Array.from( from.attributes ).forEach(
			( a ) =>
				! to.hasAttribute( a.name ) && from.removeAttribute( a.name )
		);
		Array.from( to.attributes ).forEach(
			( a ) =>
				from.getAttribute( a.name ) !== a.value &&
				from.setAttribute( a.name, a.value )
		);
	}
	const doc = from.ownerDocument;
	const oldKids = Array.from( from.childNodes );
	const newKids = Array.from( to.childNodes );
	newKids.forEach( ( n, i ) => {
		const o = oldKids[ i ];
		if ( ! o ) {
			from.appendChild( doc.importNode( n, true ) );
		} else if ( o.nodeType !== n.nodeType || o.nodeName !== n.nodeName ) {
			from.replaceChild( doc.importNode( n, true ), o );
		} else if ( o.nodeType === 3 || o.nodeType === 8 ) {
			if ( o.nodeValue !== n.nodeValue ) {
				o.nodeValue = n.nodeValue;
			}
		} else if ( o.nodeType === 1 ) {
			morph( o, n );
		}
	} );
	for ( let i = oldKids.length - 1; i >= newKids.length; i-- ) {
		from.removeChild( oldKids[ i ] );
	}
}

const box = ( el ) => {
	const r = el.getBoundingClientRect();
	return { left: r.left, top: r.top, width: r.width, height: r.height };
};

const attrSel = ( name, id ) =>
	`[${ name }="${ String( id ).replace( /"/g, '\\"' ) }"]`;

const HANDLES = [ 'nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w' ];

export default function Canvas( {
	html,
	loading,
	zoom,
	onScale,
	layout,
	blockTypes,
	selection,
	actions,
	grid,
	converting,
	onContentWidth,
} ) {
	const frame = useRef();
	const scroller = useRef();
	const inner = useRef();
	const overlay = useRef();
	const ready = useRef( false );
	const applied = useRef( '' );
	const fromCanvas = useRef( false );
	const [ srcDoc, setSrcDoc ] = useState( '' );
	const [ size, setSize ] = useState( { w: 794, h: 1123 } );
	const [ width, setWidth ] = useState( 900 );
	const [ geo, setGeo ] = useState( null );
	const [ version, setVersion ] = useState( 0 );
	const [ hover, setHover ] = useState( null );
	const [ hoverSec, setHoverSec ] = useState( null );
	const [ live, setLive ] = useState( null );
	const drag = useRef( null );
	const raf = useRef( 0 );

	// Latest values for event handlers.
	const ref = useRef( {} );
	ref.current = { layout, selection, actions, grid, geo, live, onContentWidth };

	const scale =
		zoom === 'fit'
			? Math.min( 1.25, Math.max( 0.3, ( width - 72 ) / size.w ) )
			: zoom;
	useEffect( () => onScale?.( scale ), [ scale, onScale ] );

	const doc = () => ( ready.current ? frame.current?.contentDocument : null );

	/* ---------------- Geometry ---------------- */

	const measure = useCallback( () => {
		const d = doc();
		if ( ! d?.body ) {
			return;
		}
		const sheet = d.querySelector( '.odocs-sheet' );
		const w = Math.ceil( sheet ? sheet.offsetWidth : 794 );
		const h = Math.ceil( d.body.getBoundingClientRect().height );
		setSize( ( s ) => ( s.w === w && s.h === h ? s : { w, h } ) );
		if ( ! sheet ) {
			setGeo( null );
			return;
		}
		const cs = d.defaultView.getComputedStyle( sheet );
		const sr = box( sheet );
		const pad = {
			top: parseFloat( cs.paddingTop ),
			right: parseFloat( cs.paddingRight ),
			bottom: parseFloat( cs.paddingBottom ),
			left: parseFloat( cs.paddingLeft ),
		};
		const next = {
			sheet: sr,
			pad,
			paperHeight: parseFloat( cs.minHeight ) || sr.height,
			content: {
				left: sr.left + pad.left,
				top: sr.top + pad.top,
				width: sr.width - pad.left - pad.right,
			},
			sections: {},
			items: {},
		};
		d.querySelectorAll( '[data-odocs-section]' ).forEach( ( el ) => {
			next.sections[ el.getAttribute( 'data-odocs-section' ) ] = box( el );
		} );
		d.querySelectorAll( '[data-odocs-item]' ).forEach( ( el ) => {
			if ( el.style.display === 'none' ) {
				return;
			}
			const r = box( el );
			const content = el.firstElementChild;
			r.content = content ? content.scrollHeight : r.height;
			next.items[ el.getAttribute( 'data-odocs-item' ) ] = r;
		} );
		setGeo( next );
		ref.current.onContentWidth?.( Math.round( ( next.content.width / PX ) * 10 ) / 10 );
	}, [] );

	/** Writes positions from the layout (or a live drag) straight into the frame. */
	const applyFrames = useCallback( () => {
		const d = doc();
		const { layout: l, live: lv } = ref.current;
		if ( ! d || ! l?.sections ) {
			return;
		}
		const known = new Set();
		l.sections.forEach( ( section ) => {
			const sel = d.querySelector(
				attrSel( 'data-odocs-section', section.id )
			);
			if ( ! sel ) {
				return;
			}
			if ( isFree( section ) ) {
				const h = lv?.heights?.[ section.id ] ?? section.height;
				sel.style.height = h + 'mm';
			}
			section.items.forEach( ( item, z ) => {
				known.add( item.id );
				const el = d.querySelector(
					attrSel( 'data-odocs-item', item.id )
				);
				if ( ! el ) {
					return;
				}
				const f = lv?.frames?.[ item.id ];
				const homeSection =
					f && f.s !== undefined ? l.sections[ f.s ] : section;
				const home =
					homeSection === section
						? sel
						: d.querySelector(
								attrSel( 'data-odocs-section', homeSection.id )
						  );
				const fits =
					isFree( homeSection ) ===
					el.classList.contains( 'odocs-item' );
				if ( ! home ) {
					return;
				}
				if ( ! fits && ! f ) {
					// Changed kind of section: hidden until the server render arrives.
					el.style.display = 'none';
					return;
				}
				const place = fits ? home : sel;
				const placeSection = fits ? homeSection : section;
				if ( el.parentElement !== place ) {
					place.appendChild( el );
				}
				el.style.display = '';
				// Dragging into a section of another kind: the original stays put, faded.
				const p = f && ! f.ghost && fits ? f : item;
				if ( ! fits ) {
					el.style.opacity = '0.35';
				}
				if ( isFree( placeSection ) ) {
					el.style.left = p.x + 'mm';
					el.style.top = p.y + 'mm';
					el.style.width = p.w + 'mm';
					el.style.zIndex = String( z + 1 );
					if ( item.overflow === 'clip' ) {
						el.style.height = p.h + 'mm';
						el.style.minHeight = '';
					} else {
						el.style.minHeight = p.h + 'mm';
						el.style.height = '';
					}
				} else {
					el.style.marginLeft = p.x + 'mm';
					el.style.width = p.w + 'mm';
				}
				if ( fits ) {
					el.style.opacity = f?.ghost ? '0.35' : '';
				}
			} );
		} );
		d.querySelectorAll( '[data-odocs-item]' ).forEach( ( el ) => {
			if ( ! known.has( el.getAttribute( 'data-odocs-item' ) ) ) {
				el.style.display = 'none';
			}
		} );
	}, [] );

	const apply = useCallback( () => {
		const d = doc();
		if ( ! d || ! html || applied.current === html ) {
			return;
		}
		const next = new DOMParser().parseFromString( html, 'text/html' );
		next.querySelectorAll( 'script' ).forEach( ( s ) => s.remove() );
		morph( d.head, next.head );
		morph( d.body, next.body );
		applied.current = html;
		setVersion( ( v ) => v + 1 );
	}, [ html ] );

	// First HTML boots the frame; later HTML is morphed in.
	useEffect( () => {
		if ( ! html ) {
			return;
		}
		if ( ! srcDoc ) {
			setSrcDoc( html );
			applied.current = html;
			return;
		}
		apply();
	}, [ html, srcDoc, apply ] );

	// Positions follow the layout instantly, before the server preview arrives.
	useLayoutEffect( () => {
		applyFrames();
		if ( ! live ) {
			measure();
		}
	}, [ layout, live, version, applyFrames, measure ] );

	const onLoad = () => {
		const d = frame.current.contentDocument;
		ready.current = true;
		d.querySelectorAll( 'script' ).forEach( ( s ) => s.remove() );
		d.addEventListener( 'keydown', ( e ) =>
			window.dispatchEvent( new KeyboardEvent( 'keydown', e ) )
		);
		if ( 'ResizeObserver' in frame.current.contentWindow ) {
			new frame.current.contentWindow.ResizeObserver( () =>
				ref.current.live ? null : measure()
			).observe( d.body );
		}
		( d.fonts?.ready || Promise.resolve() ).then( measure );
		if ( applied.current !== html ) {
			apply();
		} else {
			setVersion( ( v ) => v + 1 );
		}
	};

	// Canvas width for "fit".
	useLayoutEffect( () => {
		const el = scroller.current;
		if ( ! el ) {
			return;
		}
		const ro = new ResizeObserver( () => setWidth( el.clientWidth ) );
		ro.observe( el );
		setWidth( el.clientWidth );
		return () => ro.disconnect();
	}, [] );

	/* ---------------- Frames in frame pixels ---------------- */

	const sectionBox = ( section ) => geo?.sections[ section.id ] || null;

	/** Rectangle of an item in frame px, or null. */
	const itemRect = ( item, s, useLive = true ) => {
		if ( ! geo ) {
			return null;
		}
		const f = useLive ? live?.frames?.[ item.id ] : null;
		const target = layout.sections[ f?.s ?? s ];
		const sb = target && sectionBox( target );
		if ( f && sb && ( isFree( target ) || f.ghost ) ) {
			return {
				left: sb.left + f.x * PX,
				top: ( f.abs ?? sb.top + f.y * PX ),
				width: f.w * PX,
				height: f.h * PX,
			};
		}
		const section = layout.sections[ s ];
		const own = sectionBox( section );
		if ( isFree( section ) && own ) {
			const rendered = geo.items[ item.id ];
			return {
				left: own.left + item.x * PX,
				top: own.top + item.y * PX,
				width: item.w * PX,
				height: item.h * PX,
				rendered: rendered ? rendered.content : 0,
			};
		}
		const r = geo.items[ item.id ];
		return r ? { ...r } : null;
	};

	const selectedIds = selection?.kind === 'items' ? selection.ids : [];

	/* ---------------- Pointer interactions ---------------- */

	const toFrame = ( e ) => {
		const r = overlay.current.getBoundingClientRect();
		return {
			x: ( e.clientX - r.left ) / scale,
			y: ( e.clientY - r.top ) / scale,
		};
	};

	/** Section index under a frame y (footer wins where it overlaps). */
	const sectionAt = ( y ) => {
		const { layout: l, geo: g } = ref.current;
		if ( ! g ) {
			return -1;
		}
		let best = -1;
		let bestD = Infinity;
		l.sections.forEach( ( section, s ) => {
			const b = g.sections[ section.id ];
			if ( ! b ) {
				return;
			}
			if ( y >= b.top && y <= b.top + Math.max( b.height, 6 ) ) {
				if ( bestD > 0 || section.kind === 'footer' ) {
					best = s;
					bestD = 0;
				}
				return;
			}
			const d = Math.min(
				Math.abs( y - b.top ),
				Math.abs( y - b.top - b.height )
			);
			if ( bestD > 0 && d < bestD ) {
				best = s;
				bestD = d;
			}
		} );
		return best;
	};

	const threshold = () => 5 / ( scale * PX );

	const onPointerDown = ( e ) => {
		if ( e.button !== 0 || ! geo ) {
			return;
		}
		const p = toFrame( e );
		// No text selection or native drag may start from the page: it would cancel the pointer.
		window.getSelection?.()?.removeAllRanges();
		const t = e.target.closest( '[data-hit], [data-handle], [data-sec-resize]' );
		const { layout: l, selection: sel } = ref.current;
		fromCanvas.current = true;
		overlay.current.focus( { preventScroll: true } );
		if ( t?.dataset.secResize ) {
			const s = l.sections.findIndex( ( x ) => x.id === t.dataset.secResize );
			drag.current = {
				kind: 'section',
				s,
				start: p,
				orig: l.sections[ s ].height,
				min: contentBottom( l.sections[ s ] ),
			};
		} else if ( t?.dataset.handle ) {
			const pos = findItem( l, selectedIds[ 0 ] );
			if ( ! pos || pos.item.locked ) {
				return;
			}
			drag.current = {
				kind: 'resize',
				dir: t.dataset.handle,
				id: pos.item.id,
				s: pos.s,
				start: p,
				orig: { ...pos.item },
				flow: ! isFree( pos.section ),
			};
		} else if ( t?.dataset.hit ) {
			const id = t.dataset.hit;
			const ids = sel?.kind === 'items' ? sel.ids : [];
			let nextIds = ids;
			if ( e.shiftKey ) {
				nextIds = ids.includes( id )
					? ids.filter( ( x ) => x !== id )
					: [ ...ids, id ];
				actions.select( nextIds.length ? { kind: 'items', ids: nextIds } : null );
			} else if ( ! ids.includes( id ) ) {
				nextIds = [ id ];
				actions.select( { kind: 'items', ids: nextIds } );
			}
			const moving = nextIds
				.map( ( x ) => findItem( l, x ) )
				.filter( ( q ) => q && ! q.item.locked );
			const primary = findItem( l, id );
			if ( ! primary || primary.item.locked || ! nextIds.includes( id ) ) {
				drag.current = { kind: 'click', id, start: p, shift: e.shiftKey };
			} else {
				const flowDrag = ! isFree( primary.section );
				drag.current = {
					kind: 'move',
					id,
					start: p,
					shift: e.shiftKey,
					flow: flowDrag,
					items: ( flowDrag ? [ primary ] : moving.filter( ( q ) => isFree( q.section ) ) ).map(
						( q ) => {
							const r = itemRect( q.item, q.s, false );
							return {
								id: q.item.id,
								s: q.s,
								x: q.item.x,
								y: isFree( q.section ) ? q.item.y : 0,
								w: q.item.w,
								h: isFree( q.section )
									? q.item.h
									: Math.max( 3, ( r?.height || 30 ) / PX ),
								absTop: r ? r.top : 0,
								snap: !! Number( q.item.snap ),
							};
						}
					),
				};
			}
		} else {
			drag.current = { kind: 'marquee', start: p, shift: e.shiftKey };
		}
		drag.current.started = false;
		overlay.current.setPointerCapture( e.pointerId );
	};

	const computeMove = ( d, p, alt ) => {
		const { layout: l, grid: g } = ref.current;
		const dxPx = p.x - d.start.x;
		const dyPx = p.y - d.start.y;
		let ts = sectionAt( p.y );
		if ( ts === -1 ) {
			ts = d.items[ 0 ].s;
		}
		const target = l.sections[ ts ];
		const tb = geo.sections[ target.id ];
		const width = geo.content.width / PX;
		const frames = {};
		const guides = { x: [], y: [] };
		if ( ! isFree( target ) ) {
			// Dropping into a flowing section: one item, shown as a ghost, inserted at a line.
			const it = d.items[ 0 ];
			const flowItems = target.items.filter( ( x ) => x.id !== it.id );
			let index = flowItems.length;
			let lineY = tb.top + tb.height;
			for ( let i = 0; i < flowItems.length; i++ ) {
				const r = geo.items[ flowItems[ i ].id ];
				if ( r && p.y < r.top + r.height / 2 ) {
					index = target.items.indexOf( flowItems[ i ] );
					lineY = r.top;
					break;
				}
			}
			if ( index === flowItems.length ) {
				index = target.items.length;
				const last = flowItems[ flowItems.length - 1 ];
				const r = last && geo.items[ last.id ];
				lineY = r ? r.top + r.height : tb.top;
			}
			frames[ it.id ] = {
				s: ts,
				x: it.x + dxPx / PX,
				y: 0,
				w: it.w,
				h: it.h,
				abs: it.absTop + dyPx,
				ghost: true,
			};
			return { frames, guides, drop: { s: ts, index, y: lineY }, target: ts };
		}
		const placed = d.items.map( ( it ) => ( {
			...it,
			nx: it.x + dxPx / PX,
			ny: ( it.absTop + dyPx - tb.top ) / PX,
		} ) );
		const bb = bounds( placed.map( ( q ) => ( { x: q.nx, y: q.ny, w: q.w, h: q.h } ) ) );
		let ox = 0;
		let oy = 0;
		if ( ! alt ) {
			const lines = guideLines(
				target,
				new Set( d.items.map( ( q ) => q.id ) ),
				width
			);
			const sx = g.guides ? snapToLines( bb.x, bb.w, lines.xs, threshold() ) : null;
			const sy = g.guides ? snapToLines( bb.y, bb.h, lines.ys, threshold() ) : null;
			const gridOn = g.snap && placed.some( ( q ) => q.snap );
			if ( sx ) {
				ox = sx.value - bb.x;
				guides.x.push( sx.line );
			} else if ( gridOn ) {
				ox = snapValue( bb.x, g.size ) - bb.x;
			}
			if ( sy ) {
				oy = sy.value - bb.y;
				guides.y.push( sy.line );
			} else if ( gridOn ) {
				oy = snapValue( bb.y, g.size ) - bb.y;
			}
		}
		if ( bb.y + oy < 0 ) {
			oy = -bb.y;
		}
		placed.forEach( ( q ) => {
			frames[ q.id ] = {
				s: ts,
				x: tidy( q.nx + ox ),
				y: tidy( q.ny + oy ),
				w: q.w,
				h: q.h,
			};
		} );
		return { frames, guides, target: ts, section: target.id };
	};

	const computeResize = ( d, p, alt, shift ) => {
		const { layout: l, grid: g } = ref.current;
		const section = l.sections[ d.s ];
		const o = d.orig;
		const dx = ( p.x - d.start.x ) / PX;
		const dy = ( p.y - d.start.y ) / PX;
		let x1 = o.x;
		let y1 = isFree( section ) ? o.y : 0;
		let x2 = o.x + o.w;
		let y2 = y1 + o.h;
		const dir = d.dir;
		if ( dir.includes( 'w' ) ) {
			x1 += dx;
		}
		if ( dir.includes( 'e' ) ) {
			x2 += dx;
		}
		if ( ! d.flow && dir.includes( 'n' ) ) {
			y1 += dy;
		}
		if ( ! d.flow && dir.includes( 's' ) ) {
			y2 += dy;
		}
		const guides = { x: [], y: [] };
		if ( ! alt ) {
			const width = geo.content.width / PX;
			const lines = guideLines( section, new Set( [ d.id ] ), width );
			const snapEdge = ( v, list, axis ) => {
				const s = g.guides ? snapToLines( v, 0, list, threshold(), [ 0 ] ) : null;
				if ( s ) {
					guides[ axis ].push( s.line );
					return s.value;
				}
				return g.snap && o.snap ? snapValue( v, g.size ) : v;
			};
			if ( dir.includes( 'w' ) ) {
				x1 = snapEdge( x1, lines.xs, 'x' );
			}
			if ( dir.includes( 'e' ) ) {
				x2 = snapEdge( x2, lines.xs, 'x' );
			}
			if ( ! d.flow && dir.includes( 'n' ) ) {
				y1 = snapEdge( y1, lines.ys, 'y' );
			}
			if ( ! d.flow && dir.includes( 's' ) ) {
				y2 = snapEdge( y2, lines.ys, 'y' );
			}
		}
		const MIN = 3;
		if ( x2 - x1 < MIN ) {
			if ( dir.includes( 'w' ) ) {
				x1 = x2 - MIN;
			} else {
				x2 = x1 + MIN;
			}
		}
		if ( y2 - y1 < MIN ) {
			if ( dir.includes( 'n' ) ) {
				y1 = y2 - MIN;
			} else {
				y2 = y1 + MIN;
			}
		}
		if ( y1 < 0 ) {
			y1 = 0;
		}
		let w = x2 - x1;
		let h = y2 - y1;
		if ( shift && dir.length === 2 && ! d.flow ) {
			const ratio = o.w / o.h;
			if ( w / ratio > h ) {
				h = w / ratio;
			} else {
				w = h * ratio;
			}
			if ( dir.includes( 'w' ) ) {
				x1 = o.x + o.w - w;
			}
			if ( dir.includes( 'n' ) ) {
				y1 = Math.max( 0, o.y + o.h - h );
			}
		}
		return {
			frames: {
				[ d.id ]: {
					s: d.s,
					x: tidy( x1 ),
					y: tidy( y1 ),
					w: tidy( w ),
					h: tidy( h ),
				},
			},
			guides,
			section: section.id,
		};
	};

	/** Scrolls the canvas while dragging near its top or bottom edge. */
	const autoScroll = () => {
		const d = drag.current;
		const el = scroller.current;
		if ( ! d || ! d.started || ! d.last || ! el ) {
			return;
		}
		const r = el.getBoundingClientRect();
		const edge = 48;
		let dy = 0;
		if ( d.last.clientY > r.bottom - edge ) {
			dy = Math.ceil( ( d.last.clientY - ( r.bottom - edge ) ) / 3 );
		} else if ( d.last.clientY < r.top + edge ) {
			dy = -Math.ceil( ( r.top + edge - d.last.clientY ) / 3 );
		}
		if ( dy ) {
			const before = el.scrollTop;
			el.scrollTop += Math.max( -24, Math.min( 24, dy ) );
			if ( el.scrollTop !== before ) {
				onPointerMove( d.last, true );
			}
		}
		d.scrollTimer = window.requestAnimationFrame( autoScroll );
	};

	const onPointerMove = ( e, fromScroll = false ) => {
		const d = drag.current;
		const p = toFrame( e );
		if ( d && ! fromScroll ) {
			d.last = {
				clientX: e.clientX,
				clientY: e.clientY,
				altKey: e.altKey,
				shiftKey: e.shiftKey,
				target: e.target,
			};
			if ( ! d.scrollTimer ) {
				d.scrollTimer = window.requestAnimationFrame( autoScroll );
			}
		}
		if ( ! d ) {
			// Hover.
			const hs = sectionAt( p.y );
			const hsId = hs === -1 ? null : ref.current.layout.sections[ hs ]?.id;
			if ( hsId !== hoverSec ) {
				setHoverSec( hsId );
			}
			const t = e.target.closest( '[data-hit]' );
			const id = t ? t.dataset.hit : null;
			if ( id !== hover ) {
				setHover( id );
			}
			return;
		}
		if (
			! d.started &&
			Math.hypot( p.x - d.start.x, p.y - d.start.y ) * scale < 3
		) {
			return;
		}
		if ( d.kind === 'click' ) {
			return;
		}
		d.started = true;
		const alt = e.altKey;
		const shift = e.shiftKey;
		cancelAnimationFrame( raf.current );
		raf.current = requestAnimationFrame( () => {
			if ( d.kind === 'move' ) {
				setLive( { kind: 'move', ...computeMove( d, p, alt ) } );
			} else if ( d.kind === 'resize' ) {
				setLive( { kind: 'resize', ...computeResize( d, p, alt, shift ) } );
			} else if ( d.kind === 'section' ) {
				const { grid: g, layout: l } = ref.current;
				let h = d.orig + ( p.y - d.start.y ) / PX;
				if ( ! alt && g.snap ) {
					h = snapValue( h, g.size );
				}
				h = tidy( Math.max( d.min, 0, h ) );
				setLive( {
					kind: 'section',
					heights: { [ l.sections[ d.s ].id ]: h },
					label: { s: d.s, h },
				} );
			} else if ( d.kind === 'marquee' ) {
				setLive( {
					kind: 'marquee',
					rect: {
						left: Math.min( d.start.x, p.x ),
						top: Math.min( d.start.y, p.y ),
						width: Math.abs( p.x - d.start.x ),
						height: Math.abs( p.y - d.start.y ),
					},
				} );
			}
		} );
	};

	const endDrag = ( e, cancelled = false ) => {
		const d = drag.current;
		drag.current = null;
		cancelAnimationFrame( raf.current );
		if ( d?.scrollTimer ) {
			cancelAnimationFrame( d.scrollTimer );
		}
		if ( overlay.current?.hasPointerCapture?.( e.pointerId ) ) {
			overlay.current.releasePointerCapture( e.pointerId );
		}
		if ( ! d ) {
			return;
		}
		const { layout: l } = ref.current;
		const p = toFrame( e );
		if ( cancelled ) {
			setLive( null );
			return;
		}
		if ( ! d.started ) {
			setLive( null );
			if ( d.kind === 'marquee' && ! d.shift ) {
				actions.select( null );
			} else if ( ( d.kind === 'move' || d.kind === 'click' ) && ! d.shift ) {
				actions.select( { kind: 'items', ids: [ d.id ] } );
			}
			return;
		}
		if ( d.kind === 'move' ) {
			const res = computeMove( d, p, e.altKey );
			let next = l;
			const ids = Object.keys( res.frames );
			if ( res.drop ) {
				const f = res.frames[ ids[ 0 ] ];
				next = moveItemTo( next, ids[ 0 ], res.drop.s, res.drop.index, {
					x: f.x,
					w: f.w,
					h: f.h,
				} );
			} else {
				ids.forEach( ( id ) => {
					const f = res.frames[ id ];
					const pos = findItem( next, id );
					if ( pos.s === f.s ) {
						next = clone( next );
						Object.assign( next.sections[ f.s ].items[ pos.i ], {
							x: f.x,
							y: f.y,
						} );
					} else {
						next = moveItemTo( next, id, f.s, null, {
							x: f.x,
							y: f.y,
							w: f.w,
							h: f.h,
						} );
					}
				} );
				next = fitSections( next );
			}
			actions.change( next );
			if ( d.flow || res.drop ) {
				actions.select( { kind: 'items', ids } );
			}
		} else if ( d.kind === 'resize' ) {
			const f = computeResize( d, p, e.altKey, e.shiftKey ).frames[ d.id ];
			const frameChange = d.flow
				? { x: f.x, w: f.w }
				: { x: f.x, y: f.y, w: f.w, h: f.h };
			actions.setFrame( d.id, frameChange );
		} else if ( d.kind === 'section' ) {
			const h = ref.current.live?.heights?.[ l.sections[ d.s ].id ];
			if ( h !== undefined ) {
				const next = clone( l );
				next.sections[ d.s ].height = h;
				actions.change( next );
			}
		} else if ( d.kind === 'marquee' ) {
			const r = ref.current.live?.rect;
			if ( r ) {
				const hits = [];
				l.sections.forEach( ( section, s ) =>
					section.items.forEach( ( item ) => {
						const ir = itemRect( item, s, false );
						if (
							ir &&
							ir.left < r.left + r.width &&
							ir.left + ir.width > r.left &&
							ir.top < r.top + r.height &&
							ir.top + ir.height > r.top
						) {
							hits.push( item.id );
						}
					} )
				);
				const base = d.shift && selectedIds.length ? selectedIds : [];
				const ids = Array.from( new Set( [ ...base, ...hits ] ) );
				actions.select( ids.length ? { kind: 'items', ids } : null );
			}
		}
		setLive( null );
	};

	/* ---------------- Inserting by drag from the library ---------------- */

	const insertTarget = ( e, type ) => {
		const p = toFrame( e );
		const s = sectionAt( p.y );
		if ( s === -1 ) {
			return null;
		}
		const section = layout.sections[ s ];
		const sb = geo.sections[ section.id ];
		const [ w0, h0 ] = blockTypes[ type ]?.size || [ 70, 15 ];
		const width = geo.content.width / PX;
		const w = Math.min( w0, width );
		if ( ! isFree( section ) ) {
			let index = section.items.length;
			for ( let i = 0; i < section.items.length; i++ ) {
				const r = geo.items[ section.items[ i ].id ];
				if ( r && p.y < r.top + r.height / 2 ) {
					index = i;
					break;
				}
			}
			return { s, index, x: 0, w: width, h: h0 };
		}
		let x = ( p.x - sb.left ) / PX - w / 2;
		let y = ( p.y - sb.top ) / PX - h0 / 2;
		if ( grid.snap && ! e.altKey ) {
			x = snapValue( x, grid.size );
			y = snapValue( y, grid.size );
		}
		return {
			s,
			x: tidy( Math.max( -10, Math.min( width - 3, x ) ) ),
			y: tidy( Math.max( 0, y ) ),
			w,
			h: h0,
		};
	};

	const onDragOver = ( e ) => {
		if ( ! Array.from( e.dataTransfer.types ).includes( DRAG_TYPE ) || ! geo ) {
			return;
		}
		e.preventDefault();
		e.dataTransfer.dropEffect = 'copy';
		const type = window.odocsDragType;
		const t = type && insertTarget( e, type );
		setLive( t ? { kind: 'insert', insert: { ...t, type } } : null );
	};
	const onDrop = ( e ) => {
		const type =
			e.dataTransfer.getData( DRAG_TYPE ) || window.odocsDragType || '';
		e.preventDefault();
		setLive( null );
		if ( ! type || ! geo ) {
			return;
		}
		const t = insertTarget( e, type );
		if ( t ) {
			actions.insertAt( type, t );
		}
	};

	/* ---------------- Reveal selections made elsewhere ---------------- */

	useEffect( () => {
		if ( fromCanvas.current ) {
			fromCanvas.current = false;
			return;
		}
		const ids = selection?.kind === 'items' ? selection.ids : [];
		const el = scroller.current;
		if ( ! ids.length || ! el || ! inner.current ) {
			return;
		}
		const pos = findItem( layout, ids[ 0 ] );
		const r = pos && itemRect( pos.item, pos.s, false );
		if ( ! r ) {
			return;
		}
		const top = inner.current.offsetTop + r.top * scale;
		const bottom = top + r.height * scale;
		if ( top < el.scrollTop + 48 || bottom > el.scrollTop + el.clientHeight - 24 ) {
			el.scrollTo( { top: Math.max( 0, top - 96 ), behavior: 'smooth' } );
		}
	}, [ selection ] ); // eslint-disable-line react-hooks/exhaustive-deps

	/* ---------------- Rendering ---------------- */

	const S = ( v ) => v * scale;
	const rectStyle = ( r ) => ( {
		left: S( r.left ),
		top: S( r.top ),
		width: S( r.width ),
		height: S( r.height ),
	} );

	const layers = [];
	const hits = [];
	const overflow = [];
	let selRects = [];
	if ( geo && layout?.sections ) {
		layout.sections.forEach( ( section, s ) => {
			const sb = sectionBox( section );
			if ( ! sb ) {
				return;
			}
			const isSel = selection?.kind === 'section' && selection.id === section.id;
			const liveH = live?.heights?.[ section.id ];
			const h = isFree( section )
				? ( liveH ?? section.height ) * PX
				: sb.height;
			const gridPx = grid.size * PX * scale;
			layers.push(
				<div
					key={ 'sec-' + section.id }
					className={
						'odocs-sec odocs-sec-' +
						section.kind +
						( isSel ? ' is-selected' : '' ) +
						( hoverSec === section.id ? ' is-hover' : '' ) +
						( live?.section === section.id || live?.drop?.s === s
							? ' is-target'
							: '' )
					}
					style={ {
						left: S( sb.left ),
						top: S( sb.top ),
						width: S( sb.width ),
						height: Math.max( S( h ), 2 ),
						...( grid.show && isFree( section )
							? {
									backgroundSize: `${ gridPx }px ${ gridPx }px`,
							  }
							: {} ),
					} }
				>
					<button
						type="button"
						className="odocs-sec-tag"
						data-sec-tag={ section.id }
						onPointerDown={ ( e ) => e.stopPropagation() }
						onClick={ () => {
							fromCanvas.current = true;
							actions.select( { kind: 'section', id: section.id } );
						} }
					>
						<span>{ sectionLabel( section, s ) }</span>
						<small>{ sectionKindLabel( section.kind ) }</small>
					</button>
					{ isFree( section ) && (
						<div
							className="odocs-sec-resize"
							data-sec-resize={ section.id }
							title={ __( 'Drag to change the section height', 'order-documents' ) }
						>
							<span />
						</div>
					) }
					{ live?.label?.s === s && (
						<span className="odocs-measure odocs-measure-sec">
							{ sprintf(
								/* translators: %s: height in mm */ __( 'Height %s mm', 'order-documents' ),
								live.label.h
							) }
						</span>
					) }
				</div>
			);
			section.items.forEach( ( item ) => {
				const r = itemRect( item, s );
				if ( ! r ) {
					return;
				}
				const lf = live?.frames?.[ item.id ];
				const rendered = ! lf && r.rendered ? r.rendered : 0;
				const visualH = Math.max( r.height, rendered );
				hits.push(
					<div
						key={ 'hit-' + item.id }
						data-hit={ item.id }
						className={
							'odocs-hit' +
							( hover === item.id ? ' is-hover' : '' ) +
							( item.locked ? ' is-locked' : '' ) +
							( lf?.ghost ? ' is-ghost' : '' ) +
							( ! isFree( section ) ? ' is-flow' : '' )
						}
						style={ {
							...rectStyle( { ...r, height: lf ? r.height : visualH } ),
							zIndex: 10 + s * 100 + section.items.indexOf( item ),
						} }
					>
						{ lf?.ghost && (
							<span className="odocs-ghost-label">
								{ blockTypes[ item.type ]?.label || item.type }
							</span>
						) }
					</div>
				);
				if ( item.overflow !== 'clip' && rendered > r.height + 2 && isFree( section ) ) {
					overflow.push(
						<span
							key={ 'of-' + item.id }
							className="odocs-overflow"
							style={ {
								left: S( r.left ),
								top: S( r.top + r.height ),
								width: S( r.width ),
								height: S( rendered - r.height ),
							} }
						>
							<i
								title={ __(
									'The content is taller than the box with this order. It grows over what is below it; nothing else moves.',
									'order-documents'
								) }
							>
								!
							</i>
						</span>
					);
				}
				if ( selectedIds.includes( item.id ) ) {
					selRects.push( { id: item.id, r: { ...r, height: lf ? r.height : r.height }, item, section } );
				}
			} );
		} );
	}

	// Guides.
	const guideEls = [];
	if ( live?.guides && geo && live.section ) {
		const sec = layout.sections.find( ( x ) => x.id === live.section );
		const sb = sec && sectionBox( sec );
		if ( sb ) {
			live.guides.x.forEach( ( gx, i ) =>
				guideEls.push(
					<span
						key={ 'gx' + i }
						className="odocs-guide is-v"
						style={ { left: S( sb.left + gx * PX ), top: S( sb.top - 8 ), height: S( sb.height + 16 ) } }
					/>
				)
			);
			live.guides.y.forEach( ( gy, i ) =>
				guideEls.push(
					<span
						key={ 'gy' + i }
						className="odocs-guide is-h"
						style={ { top: S( sb.top + gy * PX ), left: S( sb.left - 8 ), width: S( sb.width + 16 ) } }
					/>
				)
			);
		}
	}

	// Page breaks (estimated).
	const breaks = [];
	if ( geo && layout?.sections ) {
		const pageH = geo.paperHeight - geo.pad.top - geo.pad.bottom;
		let at = geo.content.top + pageH;
		const ordered = layout.sections
			.filter( ( x ) => x.kind !== 'footer' && geo.sections[ x.id ] )
			.map( ( x ) => ( { section: x, b: geo.sections[ x.id ] } ) );
		let page = 2;
		ordered.forEach( ( { section, b } ) => {
			let guard = 0;
			while ( at < b.top + b.height && guard++ < 50 ) {
				if ( isFree( section ) && section.keep_together && b.top < at && b.top > geo.content.top + 1 && b.height <= pageH ) {
					breaks.push( { y: b.top, page: page++ } );
					at = b.top + pageH;
				} else {
					breaks.push( { y: at, page: page++ } );
					at += pageH;
				}
			}
		} );
	}

	// Selection frames, handles and toolbar.
	let selection3 = null;
	if ( selRects.length && ! ( live && live.kind === 'marquee' ) ) {
		const bb = bounds(
			selRects.map( ( x ) => ( { x: x.r.left, y: x.r.top, w: x.r.width, h: x.r.height } ) )
		);
		const single = selRects.length === 1 ? selRects[ 0 ] : null;
		const flow = single && ! isFree( single.section );
		const showHandles = single && ! single.item.locked && live?.kind !== 'move';
		const lf = single && live?.frames?.[ single.id ];
		const sizeLabel = single
			? `${ tidy( lf?.w ?? single.item.w ) } × ${ flow ? '—' : tidy( lf?.h ?? single.item.h ) } mm`
			: '';
		const first = selRects[ 0 ];
		const label =
			selRects.length > 1
				? sprintf(
						/* translators: %d: number of components */ __( '%d components', 'order-documents' ),
						selRects.length
				  )
				: blockTypes[ first.item.type ]?.label || first.item.type;
		const anyFree = selRects.some( ( x ) => isFree( x.section ) );
		const allSnap = selRects.every( ( x ) => Number( x.item.snap ) );
		const allLocked = selRects.every( ( x ) => Number( x.item.locked ) );
		const tbTop = S( bb.y ) - 46 < 4 ? S( bb.y + bb.h ) + 10 : S( bb.y ) - 46;
		const tbLeft = Math.max( 0, Math.min( S( bb.x ), S( size.w ) - 330 ) );
		selection3 = (
			<>
				{ selRects.map( ( x ) => (
					<div
						key={ 'sel-' + x.id }
						className={ 'odocs-selframe' + ( x.item.locked ? ' is-locked' : '' ) }
						style={ rectStyle( x.r ) }
					/>
				) ) }
				{ selRects.length > 1 && (
					<div
						className="odocs-selbox"
						style={ { left: S( bb.x ), top: S( bb.y ), width: S( bb.w ), height: S( bb.h ) } }
					/>
				) }
				{ showHandles &&
					HANDLES.filter( ( h ) => ! flow || h === 'e' || h === 'w' ).map( ( h ) => {
						const r = single.r;
						const xs = { w: 0, e: 1 };
						const ys = { n: 0, s: 1 };
						const fx = h.includes( 'w' ) ? xs.w : h.includes( 'e' ) ? xs.e : 0.5;
						const fy = h.includes( 'n' ) ? ys.n : h.includes( 's' ) ? ys.s : 0.5;
						return (
							<span
								key={ h }
								data-handle={ h }
								className={ 'odocs-handle-r is-' + h }
								style={ {
									left: S( r.left + r.width * fx ),
									top: S(
										r.top +
											( flow && fy === 0.5
												? Math.min( r.height / 2, 28 / scale )
												: r.height * fy )
									),
								} }
							/>
						);
					} ) }
				{ single && ( live?.kind === 'resize' || live?.kind === 'move' ) && (
					<span
						className="odocs-measure"
						style={ { left: S( single.r.left ), top: S( single.r.top + single.r.height ) + 6 } }
					>
						{ live?.kind === 'move' && lf && ! lf.ghost
							? `X ${ tidy( lf.x ) } · Y ${ tidy( lf.y ) } mm`
							: sizeLabel }
					</span>
				) }
				{ ! live && (
					<div
						className="odocs-block-toolbar"
						style={ { top: tbTop, left: tbLeft } }
						onPointerDown={ ( e ) => e.stopPropagation() }
					>
						<span className="odocs-block-toolbar-label">
							<Dashicon
								icon={
									selRects.length > 1
										? 'screenoptions'
										: blockTypes[ first.item.type ]?.icon || 'block-default'
								}
							/>
							{ label }
						</span>
						{ anyFree && (
							<>
								<span className="odocs-sep" />
								<Button
									size="small"
									icon={ gridIcon }
									isPressed={ allSnap }
									label={
										allSnap
											? __( 'Snaps to the grid. Click for free placement.', 'order-documents' )
											: __( 'Free placement. Click to snap to the grid.', 'order-documents' )
									}
									onClick={ () => actions.setSnap( selectedIds, ! allSnap ) }
								/>
								<Button
									size="small"
									icon={ arrowUp }
									label={ __( 'Bring forward', 'order-documents' ) }
									shortcut={ 'Ctrl+]' }
									onClick={ () => actions.layer( selectedIds, 'forward' ) }
								/>
								<Button
									size="small"
									icon={ arrowDown }
									label={ __( 'Send backward', 'order-documents' ) }
									shortcut={ 'Ctrl+[' }
									onClick={ () => actions.layer( selectedIds, 'backward' ) }
								/>
							</>
						) }
						<span className="odocs-sep" />
						<Button
							size="small"
							icon={ allLocked ? lock : unlock }
							isPressed={ allLocked }
							label={
								allLocked
									? __( 'Unlock position', 'order-documents' )
									: __( 'Lock position', 'order-documents' )
							}
							onClick={ () => actions.setLocked( selectedIds, ! allLocked ) }
						/>
						<Button
							size="small"
							icon={ copy }
							label={ __( 'Duplicate', 'order-documents' ) }
							shortcut="Ctrl+D"
							onClick={ () => actions.duplicate( selectedIds ) }
						/>
						<Button
							size="small"
							icon={ trash }
							label={ __( 'Delete', 'order-documents' ) }
							shortcut="Del"
							isDestructive
							onClick={ () => actions.remove( selectedIds ) }
						/>
					</div>
				) }
			</>
		);
	}

	let hoverTag = null;
	if ( hover && ! live && ! selectedIds.includes( hover ) ) {
		const pos = findItem( layout, hover );
		const r = pos && itemRect( pos.item, pos.s );
		if ( r ) {
			hoverTag = (
				<span className="odocs-hover-tag" style={ { top: S( r.top ) - 20, left: S( r.left ) } }>
					{ blockTypes[ pos.item.type ]?.label || pos.item.type }
					{ !! pos.item.locked && <Dashicon icon="lock" /> }
				</span>
			);
		}
	}

	const ins = live?.kind === 'insert' ? live.insert : null;
	let insertGhost = null;
	if ( ins && geo ) {
		const section = layout.sections[ ins.s ];
		const sb = section && sectionBox( section );
		if ( sb && isFree( section ) ) {
			insertGhost = (
				<div
					className="odocs-insert-ghost"
					style={ {
						left: S( sb.left + ins.x * PX ),
						top: S( sb.top + ins.y * PX ),
						width: S( ins.w * PX ),
						height: S( ins.h * PX ),
					} }
				>
					<span>{ blockTypes[ ins.type ]?.label }</span>
				</div>
			);
		} else if ( sb ) {
			const items = section.items;
			const r = items[ ins.index ] ? geo.items[ items[ ins.index ].id ] : null;
			const last = items.length ? geo.items[ items[ items.length - 1 ].id ] : null;
			const y = r ? r.top : last ? last.top + last.height : sb.top;
			insertGhost = (
				<span className="odocs-dropline" style={ { left: S( sb.left ), top: S( y ) - 1, width: S( sb.width ) } } />
			);
		}
	}

	return (
		<div className="odocs-canvas-wrap">
			{ ( loading || converting ) && (
				<div className="odocs-progress" role="progressbar" aria-label={ __( 'Updating preview', 'order-documents' ) } />
			) }
			<div
				className="odocs-canvas"
				ref={ scroller }
				onPointerDown={ ( e ) => {
					if ( e.target === scroller.current || e.target === inner.current ) {
						actions.select( null );
					}
				} }
				role="presentation"
			>
				{ ! srcDoc && (
					<div className="odocs-canvas-loading">
						<Spinner />
					</div>
				) }
				<div
					className={ 'odocs-page' + ( live ? ' is-interacting is-' + live.kind : '' ) }
					ref={ inner }
					style={ { width: size.w * scale, height: size.h * scale } }
				>
					{ srcDoc && (
						<iframe
							ref={ frame }
							title={ __( 'Document preview', 'order-documents' ) }
							srcDoc={ srcDoc }
							onLoad={ onLoad }
							scrolling="no"
							tabIndex={ -1 }
							style={ {
								width: size.w,
								height: size.h,
								transform: `scale(${ scale })`,
							} }
						/>
					) }
					<div
						ref={ overlay }
						className={ 'odocs-overlay' + ( grid.show ? ' show-grid' : '' ) + ( converting ? ' is-converting' : '' ) }
						tabIndex={ -1 }
						role="application"
						aria-label={ __( 'Page. Click a component to select it, drag to move it.', 'order-documents' ) }
						onPointerDown={ onPointerDown }
						onPointerMove={ onPointerMove }
						onPointerUp={ endDrag }
						onPointerCancel={ ( e ) => endDrag( e, true ) }
						onPointerLeave={ () => {
							if ( ! drag.current ) {
								setHover( null );
								setHoverSec( null );
							}
						} }
						onDragOver={ onDragOver }
						onDragLeave={ ( e ) => {
							if ( e.target === overlay.current ) {
								setLive( null );
							}
						} }
						onDrop={ onDrop }
						onDragStart={ ( e ) => e.preventDefault() }
						onMouseDown={ ( e ) => e.preventDefault() }
					>
						{ layers }
						{ breaks.map( ( b ) => (
							<div key={ 'pb' + b.page } className="odocs-pagebreak" style={ { top: S( b.y ) } }>
								<span>
									{ sprintf(
										/* translators: %d: page number */ __( 'Page %d', 'order-documents' ),
										b.page
									) }
								</span>
							</div>
						) ) }
						{ hits }
						{ overflow }
						{ guideEls }
						{ selection3 }
						{ hoverTag }
						{ insertGhost }
						{ live?.kind === 'move' && live.drop && geo && (
							<span
								className="odocs-dropline"
								style={ {
									left: S( geo.sections[ layout.sections[ live.drop.s ].id ].left ),
									top: S( live.drop.y ) - 1,
									width: S( geo.sections[ layout.sections[ live.drop.s ].id ].width ),
								} }
							/>
						) }
						{ live?.kind === 'marquee' && (
							<div className="odocs-marquee" style={ rectStyle( live.rect ) } />
						) }
					</div>
					{ converting && (
						<div className="odocs-converting">
							<Spinner />
							<span>{ __( 'Preparing the free-form editor…', 'order-documents' ) }</span>
						</div>
					) }
				</div>
			</div>
		</div>
	);
}
