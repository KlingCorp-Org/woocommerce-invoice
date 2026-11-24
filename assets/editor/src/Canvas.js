/**
 * The page canvas: a same-origin iframe that is updated in place (DOM morph)
 * instead of reloaded, so scroll position, images and fonts stay put.
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
import { arrowUp, arrowDown, copy, trash } from '@wordpress/icons';
import { findBlock, findRow } from './utils';

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

const rectOf = ( el ) => {
	const r = el.getBoundingClientRect();
	return { top: r.top, left: r.left, width: r.width, height: r.height };
};

const attrSel = ( name, id ) =>
	`[${ name }="${ String( id ).replace( /"/g, '\\"' ) }"]`;

export default function Canvas( {
	html,
	loading,
	zoom,
	onScale,
	selected,
	layout,
	blockTypes,
	actions,
	onKeyDown,
} ) {
	const frame = useRef();
	const scroller = useRef();
	const inner = useRef();
	const ready = useRef( false );
	const applied = useRef( '' );
	const clickedId = useRef( null );
	const [ srcDoc, setSrcDoc ] = useState( '' );
	const [ size, setSize ] = useState( { w: 794, h: 1123 } );
	const [ width, setWidth ] = useState( 900 );
	const [ selRect, setSelRect ] = useState( null );
	const [ hover, setHover ] = useState( null );

	// Latest values for listeners attached inside the frame.
	const live = useRef( {} );
	live.current = { selected, actions, onKeyDown };

	const scale =
		zoom === 'fit'
			? Math.min( 1.25, Math.max( 0.3, ( width - 64 ) / size.w ) )
			: zoom;
	useEffect( () => onScale?.( scale ), [ scale, onScale ] );

	const doc = () => ( ready.current ? frame.current?.contentDocument : null );

	const syncSelection = useCallback( () => {
		const d = doc();
		if ( ! d ) {
			return;
		}
		d.querySelectorAll( '.odocs-selected' ).forEach( ( n ) =>
			n.classList.remove( 'odocs-selected' )
		);
		const sel = live.current.selected;
		let el = null;
		if ( sel?.kind === 'block' ) {
			el = d.querySelector( attrSel( 'data-odocs-block', sel.id ) );
		} else if ( sel?.kind === 'row' ) {
			el = d.querySelector( attrSel( 'data-odocs-row', sel.id ) );
		}
		if ( el ) {
			el.classList.add( 'odocs-selected' );
		}
		setSelRect( el ? rectOf( el ) : null );
	}, [] );

	const measure = useCallback( () => {
		const d = doc();
		if ( ! d?.body ) {
			return;
		}
		const sheet = d.querySelector( '.odocs-sheet' );
		const w = Math.ceil( sheet ? sheet.offsetWidth : 794 );
		const h = Math.ceil( d.body.getBoundingClientRect().height );
		setSize( ( s ) => ( s.w === w && s.h === h ? s : { w, h } ) );
		syncSelection();
	}, [ syncSelection ] );

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
		setHover( null );
		measure();
	}, [ html, measure ] );

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

	const onLoad = () => {
		const d = frame.current.contentDocument;
		ready.current = true;
		d.querySelectorAll( 'script' ).forEach( ( s ) => s.remove() );
		d.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const block = e.target.closest( '[data-odocs-block]' );
			const row = e.target.closest( '[data-odocs-row]' );
			const sel = block
				? {
						kind: 'block',
						id: block.getAttribute( 'data-odocs-block' ),
				  }
				: row
				? { kind: 'row', id: row.getAttribute( 'data-odocs-row' ) }
				: null;
			clickedId.current = sel?.id || null;
			live.current.actions.select( sel );
		} );
		d.addEventListener( 'mouseover', ( e ) => {
			const el = e.target.closest( '[data-odocs-block]' );
			const id = el ? el.getAttribute( 'data-odocs-block' ) : null;
			setHover( ( h ) =>
				h?.id === id ? h : id ? { id, rect: rectOf( el ) } : null
			);
		} );
		d.documentElement.addEventListener( 'mouseleave', () =>
			setHover( null )
		);
		d.addEventListener( 'keydown', ( e ) => live.current.onKeyDown?.( e ) );
		if ( 'ResizeObserver' in frame.current.contentWindow ) {
			new frame.current.contentWindow.ResizeObserver( measure ).observe(
				d.body
			);
		}
		( d.fonts?.ready || Promise.resolve() ).then( measure );
		if ( applied.current !== html ) {
			apply();
		} else {
			measure();
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

	// Selection changes: highlight, and reveal it when it came from elsewhere.
	useEffect( () => {
		syncSelection();
		const sel = selected;
		if ( ! sel || clickedId.current === sel.id ) {
			clickedId.current = null;
			return;
		}
		const d = doc();
		const el = d?.querySelector(
			attrSel(
				sel.kind === 'row' ? 'data-odocs-row' : 'data-odocs-block',
				sel.id
			)
		);
		const box = scroller.current;
		if ( ! el || ! box || ! inner.current ) {
			return;
		}
		const r = rectOf( el );
		const top = inner.current.offsetTop + r.top * scale;
		const bottom = top + r.height * scale;
		if (
			top < box.scrollTop + 48 ||
			bottom > box.scrollTop + box.clientHeight - 24
		) {
			box.scrollTo( {
				top: Math.max( 0, top - 96 ),
				behavior: 'smooth',
			} );
		}
	}, [ selected, syncSelection ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Selection toolbar.
	let toolbar = null;
	if ( selRect && selected ) {
		const isBlock = selected.kind === 'block';
		let label = '';
		let icon = 'columns';
		let canUp = true;
		let canDown = true;
		if ( isBlock ) {
			const pos = findBlock( layout, selected.id );
			const block =
				pos && layout.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ];
			label = block ? blockTypes[ block.type ]?.label || block.type : '';
			icon = block
				? blockTypes[ block.type ]?.icon || 'block-default'
				: 'block-default';
		} else {
			const r = findRow( layout, selected.id );
			label = sprintf(
				/* translators: %d: row number */ __(
					'Row %d',
					'order-documents'
				),
				r + 1
			);
			canUp = r > 0;
			canDown = r < layout.rows.length - 1;
		}
		const above = selRect.top * scale - 46;
		const top =
			above < -28 ? ( selRect.top + selRect.height ) * scale + 8 : above;
		const left = Math.max(
			0,
			Math.min( selRect.left * scale, size.w * scale - 230 )
		);
		const r = isBlock ? null : findRow( layout, selected.id );
		toolbar = (
			<div
				className="odocs-block-toolbar"
				style={ { top, left } }
				onMouseDown={ ( e ) => e.stopPropagation() }
			>
				<span className="odocs-block-toolbar-label">
					<Dashicon icon={ icon } />
					{ label }
				</span>
				<span className="odocs-sep" />
				<Button
					size="small"
					icon={ arrowUp }
					disabled={ ! canUp }
					label={ __( 'Move up', 'order-documents' ) }
					shortcut="Alt+↑"
					onClick={ () =>
						isBlock
							? actions.moveBlockBy( selected.id, -1 )
							: actions.moveRow( r, r - 1 )
					}
				/>
				<Button
					size="small"
					icon={ arrowDown }
					disabled={ ! canDown }
					label={ __( 'Move down', 'order-documents' ) }
					shortcut="Alt+↓"
					onClick={ () =>
						isBlock
							? actions.moveBlockBy( selected.id, 1 )
							: actions.moveRow( r, r + 1 )
					}
				/>
				<span className="odocs-sep" />
				<Button
					size="small"
					icon={ copy }
					label={ __( 'Duplicate', 'order-documents' ) }
					shortcut="Ctrl+D"
					onClick={ () =>
						isBlock
							? actions.duplicateBlock( selected.id )
							: actions.duplicateRow( r )
					}
				/>
				<Button
					size="small"
					icon={ trash }
					label={ __( 'Delete', 'order-documents' ) }
					shortcut="Del"
					isDestructive
					onClick={ () =>
						isBlock
							? actions.removeBlock( selected.id )
							: actions.removeRow( r )
					}
				/>
			</div>
		);
	}

	let hoverTag = null;
	if ( hover && hover.id !== selected?.id ) {
		const pos = findBlock( layout, hover.id );
		const block =
			pos && layout.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ];
		if ( block ) {
			hoverTag = (
				<span
					className="odocs-hover-tag"
					style={ {
						top: hover.rect.top * scale - 20,
						left: hover.rect.left * scale,
					} }
				>
					{ blockTypes[ block.type ]?.label || block.type }
				</span>
			);
		}
	}

	return (
		<div className="odocs-canvas-wrap">
			{ loading && (
				<div
					className="odocs-progress"
					role="progressbar"
					aria-label={ __( 'Updating preview', 'order-documents' ) }
				/>
			) }
			<div
				className="odocs-canvas"
				ref={ scroller }
				onClick={ ( e ) =>
					( e.target === scroller.current ||
						e.target === inner.current ) &&
					actions.select( null )
				}
				role="presentation"
			>
				{ ! srcDoc && (
					<div className="odocs-canvas-loading">
						<Spinner />
					</div>
				) }
				<div
					className="odocs-page"
					ref={ inner }
					style={ { width: size.w * scale, height: size.h * scale } }
				>
					{ srcDoc && (
						<iframe
							ref={ frame }
							title={ __(
								'Document preview',
								'order-documents'
							) }
							srcDoc={ srcDoc }
							onLoad={ onLoad }
							scrolling="no"
							style={ {
								width: size.w,
								height: size.h,
								transform: `scale(${ scale })`,
							} }
						/>
					) }
					{ hoverTag }
					{ toolbar }
				</div>
			</div>
		</div>
	);
}
