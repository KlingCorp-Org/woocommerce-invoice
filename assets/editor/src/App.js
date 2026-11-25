/**
 * Visual document editor: full-screen, three panes (structure, canvas, settings).
 */
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import {
	Button,
	Modal,
	Notice,
	SnackbarList,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import TopBar, { SAMPLE } from './TopBar';
import Layers from './Layers';
import Canvas from './Canvas';
import Inspector from './Inspector';
import useHistory from './useHistory';
import {
	alignItems,
	clone,
	convertV1,
	distributeItems,
	duplicateItems,
	findItem,
	findSection,
	fitSections,
	insertItem,
	isFree,
	isV2,
	newItem,
	newSection,
	removeItems,
	reorderLayer,
	setFrame,
	tidy,
	uid,
	updateItems,
} from './utils';

const boot = window.odocsEditor || {};
const MENU_KEY = 'odocs-editor-wp-menu';
const GRID_KEY = 'odocs-editor-grid';
const GRID_DEFAULT = { show: false, snap: true, size: 2, guides: true };

const storage = {
	get( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	},
	set( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) {}
	},
};

function loadGrid() {
	try {
		return {
			...GRID_DEFAULT,
			...JSON.parse( storage.get( GRID_KEY ) || '{}' ),
		};
	} catch ( e ) {
		return GRID_DEFAULT;
	}
}

const isMac =
	typeof navigator !== 'undefined' &&
	/Mac|iPhone|iPad/.test( navigator.platform || '' );
const MOD = isMac ? '⌘' : 'Ctrl';

const SHORTCUTS = [
	[ `${ MOD }+S`, __( 'Save', 'order-documents' ) ],
	[ `${ MOD }+Z`, __( 'Undo', 'order-documents' ) ],
	[ `${ MOD }+Shift+Z`, __( 'Redo', 'order-documents' ) ],
	[ '← ↑ → ↓', __( 'Nudge the selection one grid step', 'order-documents' ) ],
	[ 'Shift+← ↑ → ↓', __( 'Nudge the selection five grid steps', 'order-documents' ) ],
	[ __( 'Alt while dragging', 'order-documents' ), __( 'Place freely, without snapping', 'order-documents' ) ],
	[ __( 'Shift while resizing', 'order-documents' ), __( 'Keep the proportions', 'order-documents' ) ],
	[ __( 'Shift+click', 'order-documents' ), __( 'Add to or remove from the selection', 'order-documents' ) ],
	[ `${ MOD }+A`, __( 'Select everything in the section', 'order-documents' ) ],
	[ `${ MOD }+D`, __( 'Duplicate the selection', 'order-documents' ) ],
	[ 'Delete', __( 'Delete the selection', 'order-documents' ) ],
	[ `${ MOD }+] / ${ MOD }+[`, __( 'Bring forward / send backward', 'order-documents' ) ],
	[ `${ MOD }+Shift+] / ${ MOD }+Shift+[`, __( 'Bring to front / send to back', 'order-documents' ) ],
	[ 'Esc', __( 'Select the section, then nothing', 'order-documents' ) ],
	[ '?', __( 'Show these shortcuts', 'order-documents' ) ],
];

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

function ConfirmModal( { title, message, buttons, onClose } ) {
	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			size="small"
			className="odocs-confirm"
		>
			<p>{ message }</p>
			<div className="odocs-confirm-actions">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'order-documents' ) }
				</Button>
				{ buttons.map( ( b ) => (
					<Button
						key={ b.label }
						variant={ b.variant || 'secondary' }
						isDestructive={ b.destructive }
						onClick={ () => {
							onClose();
							b.onClick();
						} }
						__next40pxDefaultSize
					>
						{ b.label }
					</Button>
				) ) }
			</div>
		</Modal>
	);
}

export default function App() {
	const [ config, setConfig ] = useState( null );
	const [ type, setType ] = useState( boot.type || 'invoice' );
	const [ template, setTemplate ] = useState( boot.template || 'classic' );
	const [ orderId, setOrderId ] = useState(
		String( boot.orderId || SAMPLE )
	);
	const [ orders, setOrders ] = useState( [] );
	const history = useHistory( null );
	const [ saved, setSaved ] = useState( { layout: null, style: null } );
	const [ customized, setCustomized ] = useState( false );
	const [ selection, setSelection ] = useState( null );
	const [ converting, setConverting ] = useState( false );
	const [ grid, setGridState ] = useState( loadGrid );
	const [ contentWidth, setContentWidth ] = useState( 182 );
	const [ html, setHtml ] = useState( '' );
	const [ palette, setPalette ] = useState( {} );
	const [ effectiveStyle, setEffectiveStyle ] = useState( {} );
	const [ loading, setLoading ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ snacks, setSnacks ] = useState( [] );
	const [ confirm, setConfirm ] = useState( null );
	const [ showShortcuts, setShowShortcuts ] = useState( false );
	const [ zoom, setZoom ] = useState( 'fit' );
	const [ scale, setScale ] = useState( 1 );
	const [ wpMenu, setWpMenu ] = useState( storage.get( MENU_KEY ) === '1' );
	const setGrid = ( next ) => {
		setGridState( next );
		storage.set( GRID_KEY, JSON.stringify( next ) );
	};
	const [ panes, setPanes ] = useState( {
		structure: window.innerWidth >= 1100,
		settings: true,
	} );

	const layout = history.present?.layout || null;
	const style = history.present?.style || null;
	const styleRef = useRef( null );
	styleRef.current = style || config?.design || null;

	const dirtyLayout = useMemo(
		() =>
			! converting &&
			!! layout &&
			!! saved.layout &&
			! same( layout, saved.layout ),
		[ layout, saved.layout, converting ]
	);
	const layoutRef = useRef( null );
	layoutRef.current = layout;
	const convertMode = useRef( null );
	const dirtyStyle = useMemo(
		() => !! style && !! saved.style && ! same( style, saved.style ),
		[ style, saved.style ]
	);
	const dirty = dirtyLayout || dirtyStyle;

	const setLayout = useCallback(
		( next, key ) =>
			history.set(
				( d ) => ( { ...d, layout: next } ),
				key ? 'L:' + key : null
			),
		[ history.set ]
	); // eslint-disable-line react-hooks/exhaustive-deps
	const setStyle = useCallback(
		( next, key ) =>
			history.set(
				( d ) => ( { ...d, style: next } ),
				key ? 'S:' + key : null
			),
		[ history.set ]
	); // eslint-disable-line react-hooks/exhaustive-deps

	const snack = useCallback(
		( content, withUndo = false ) => {
			const id = uid( 'n' );
			setSnacks( ( list ) => [
				...list.slice( -2 ),
				{
					id,
					content,
					spokenMessage: content,
					actions: withUndo
						? [
								{
									label: __( 'Undo', 'order-documents' ),
									onClick: history.undo,
								},
						  ]
						: [],
				},
			] );
		},
		[ history.undo ]
	);

	// Full screen (WordPress menu hidden) unless the user prefers the menu.
	useEffect( () => {
		document.body.classList.toggle( 'odocs-editor-fullscreen', ! wpMenu );
		storage.set( MENU_KEY, wpMenu ? '1' : '0' );
	}, [ wpMenu ] );

	// Config + recent orders.
	useEffect( () => {
		apiFetch( { path: '/odocs/v1/editor-config' } )
			.then( ( cfg ) => {
				setConfig( cfg );
				setSaved( ( s ) => ( { ...s, style: cfg.design } ) );
			} )
			.catch( ( e ) => setError( e.message ) );
		apiFetch( { path: '/odocs/v1/orders' } )
			.then( setOrders )
			.catch( () => {} );
	}, [] );

	// Layout for the chosen template + type.
	useEffect( () => {
		if ( ! config ) {
			return;
		}
		let cancelled = false;
		setSelection( null );
		apiFetch( {
			path: `/odocs/v1/layout?template=${ template }&type=${ type }`,
		} )
			.then( ( res ) => {
				if ( cancelled ) {
					return;
				}
				history.reset( {
					layout: res.layout,
					style: styleRef.current,
				} );
				setCustomized( res.customized );
				if ( isV2( res.layout ) ) {
					setConverting( false );
					setSaved( ( s ) => ( { ...s, layout: res.layout } ) );
				} else {
					// Rows layout: shown once, measured and turned into free-form sections.
					convertMode.current = 'load';
					setConverting( true );
					setSaved( ( s ) => ( { ...s, layout: null } ) );
				}
			} )
			.catch( ( e ) => setError( e.message ) );
		const url = new URL( window.location.href );
		url.searchParams.set( 'type', type );
		url.searchParams.set( 'template', template );
		window.history.replaceState( null, '', url );
		return () => {
			cancelled = true;
		};
	}, [ config, template, type ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Debounced live preview; stale requests are aborted.
	const firstPreview = useRef( true );
	useEffect( () => {
		if ( ! layout || ! style ) {
			return;
		}
		const controller = new AbortController();
		const timer = setTimeout(
			() => {
				setLoading( true );
				apiFetch( {
					path: '/odocs/v1/preview',
					method: 'POST',
					data: {
						template,
						type,
						layout,
						style,
						order_id: Number( orderId ),
					},
					signal: controller.signal,
				} )
					.then( ( res ) => {
						setHtml( res.html );
						setPalette( res.palette || {} );
						setEffectiveStyle( res.style || {} );
						setError( null );
						setLoading( false );
					} )
					.catch( ( e ) => {
						if ( e?.name !== 'AbortError' ) {
							setError( e.message );
							setLoading( false );
						}
					} );
			},
			firstPreview.current ? 0 : 250
		);
		firstPreview.current = false;
		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ layout, style, template, type, orderId ] );

	// Warn about unsaved changes when leaving.
	useEffect( () => {
		const handler = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );

	const save = async () => {
		if ( saving || ! dirty ) {
			return true;
		}
		setSaving( true );
		try {
			let nextLayout = layout;
			let nextStyle = style;
			if ( dirtyLayout ) {
				const res = await apiFetch( {
					path: '/odocs/v1/layout',
					method: 'POST',
					data: { template, type, layout },
				} );
				nextLayout = res.layout;
				setCustomized( true );
			}
			if ( dirtyStyle ) {
				const res = await apiFetch( {
					path: '/odocs/v1/design',
					method: 'POST',
					data: { style },
				} );
				nextStyle = res.design;
			}
			setSaved( { layout: nextLayout, style: nextStyle } );
			if ( ! same( nextLayout, layout ) || ! same( nextStyle, style ) ) {
				history.set( { layout: nextLayout, style: nextStyle } );
			}
			snack( __( 'Saved.', 'order-documents' ) );
			setSaving( false );
			return true;
		} catch ( e ) {
			setError( e.message );
			setSaving( false );
			return false;
		}
	};

	const guard = ( apply ) => {
		if ( ! dirtyLayout ) {
			apply();
			return;
		}
		setConfirm( {
			title: __( 'Unsaved layout changes', 'order-documents' ),
			message: __(
				'This layout has changes that are not saved yet. What do you want to do with them?',
				'order-documents'
			),
			buttons: [
				{
					label: __( 'Discard', 'order-documents' ),
					destructive: true,
					onClick: apply,
				},
				{
					label: __( 'Save and continue', 'order-documents' ),
					variant: 'primary',
					onClick: async () => ( await save() ) && apply(),
				},
			],
		} );
	};

	const changeType = ( v ) =>
		v !== type &&
		guard( () => {
			setType( v );
			const t = config.documentTypes.find( ( d ) => d.id === v );
			if ( t?.template ) {
				setTemplate( t.template );
			}
		} );
	const changeTemplate = ( v ) =>
		v !== template && guard( () => setTemplate( v ) );

	const reset = () =>
		setConfirm( {
			title: __( 'Reset layout?', 'order-documents' ),
			message: __(
				'This restores the template’s original layout for this document type. You can still undo it until you leave the editor.',
				'order-documents'
			),
			buttons: [
				{
					label: __( 'Reset layout', 'order-documents' ),
					destructive: true,
					variant: 'primary',
					onClick: async () => {
						try {
							const res = await apiFetch( {
								path: `/odocs/v1/layout?template=${ template }&type=${ type }`,
								method: 'DELETE',
							} );
							setCustomized( false );
							setSelection( null );
							if ( isV2( res.layout ) ) {
								setLayout( res.layout );
								setSaved( ( s ) => ( { ...s, layout: res.layout } ) );
							} else {
								convertMode.current = 'reset';
								setConverting( true );
								setLayout( res.layout );
							}
							snack(
								__(
									'Layout reset to the template default.',
									'order-documents'
								),
								true
							);
						} catch ( e ) {
							setError( e.message );
						}
					},
				},
			],
		} );

	const previewPdf = () => {
		const form = document.createElement( 'form' );
		form.method = 'POST';
		form.action = boot.ajaxUrl;
		form.target = '_blank';
		const add = ( name, value ) => {
			const input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = name;
			input.value = value;
			form.appendChild( input );
		};
		add( 'action', 'odocs_preview_pdf' );
		add( '_wpnonce', boot.pdfNonce );
		add(
			'payload',
			JSON.stringify( {
				template,
				type,
				layout,
				style,
				order_id: Number( orderId ),
			} )
		);
		document.body.appendChild( form );
		form.submit();
		form.remove();
	};

	const label = ( item ) =>
		config.blockTypes[ item.type ]?.label || item.type;

	// A rows layout is measured in the PDF and turned into free-form sections that print the same.
	useEffect( () => {
		if ( ! converting || ! layout || isV2( layout ) || ! style ) {
			return;
		}
		let cancelled = false;
		apiFetch( {
			path: '/odocs/v1/measure',
			method: 'POST',
			data: { template, type, layout, style },
		} )
			.then( ( m ) => {
				if ( cancelled ) {
					return;
				}
				const next = convertV1( layout, m, config.blockTypes );
				history.replace( ( d ) => ( { ...d, layout: next } ) );
				setSaved( ( s ) => ( { ...s, layout: next } ) );
				convertMode.current = null;
				setConverting( false );
			} )
			.catch( ( e ) => ! cancelled && setError( e.message ) );
		return () => {
			cancelled = true;
		};
	}, [ converting, layout ] ); // eslint-disable-line react-hooks/exhaustive-deps

	/** Section that new components go to: the selected one, else the first free-form one. */
	const targetSection = () => {
		if ( selection?.kind === 'section' ) {
			return findSection( layout, selection.id );
		}
		if ( selection?.kind === 'items' ) {
			const pos = findItem( layout, selection.ids[ 0 ] );
			if ( pos ) {
				return pos.s;
			}
		}
		return layout.sections.findIndex( ( x ) => x.kind === 'canvas' );
	};

	const FULL_WIDTH = [
		'items_table',
		'divider',
		'payment_summary',
		'signature_fields',
		'footer',
	];

	const actions = {
		select: setSelection,
		change: ( next, key ) => setLayout( next, key ),
		setFrame( id, frame, key ) {
			setLayout( setFrame( layout, id, frame ), key );
		},
		changeItems( ids, fn, key ) {
			setLayout( fitSections( updateItems( layout, ids, fn ) ), key );
		},
		setSnap( ids, on ) {
			actions.changeItems( ids, ( it ) => ( it.snap = on ? 1 : 0 ) );
		},
		setLocked( ids, on ) {
			actions.changeItems( ids, ( it ) => ( it.locked = on ? 1 : 0 ) );
		},
		layer( ids, how ) {
			setLayout( reorderLayer( layout, ids, how ) );
		},
		align( ids, how ) {
			setLayout( alignItems( layout, ids, how, contentWidth ) );
		},
		distribute( ids, axis ) {
			setLayout( distributeItems( layout, ids, axis ) );
		},
		duplicate( ids ) {
			const [ next, newIds ] = duplicateItems(
				layout,
				ids,
				Math.max( 2, grid.size * 2 )
			);
			if ( newIds.length ) {
				setLayout( next );
				setSelection( { kind: 'items', ids: newIds } );
			}
		},
		remove( ids ) {
			const list = ids
				.map( ( id ) => findItem( layout, id ) )
				.filter( Boolean );
			if ( ! list.length ) {
				return;
			}
			setLayout( removeItems( layout, ids ) );
			setSelection( null );
			snack(
				list.length > 1
					? sprintf(
							/* translators: %d: number of components */ __(
								'%d components deleted.',
								'order-documents'
							),
							list.length
					  )
					: sprintf(
							/* translators: %s: component name */ __(
								'%s deleted.',
								'order-documents'
							),
							label( list[ 0 ].item )
					  ),
				true
			);
		},
		add( type ) {
			let next = layout;
			let s = targetSection();
			if ( s === -1 ) {
				next = clone( layout );
				next.sections.unshift(
					newSection( 'canvas', __( 'Top', 'order-documents' ) )
				);
				s = 0;
			}
			const section = next.sections[ s ];
			const item = newItem( config.blockTypes, config.columnTypes, type );
			item.w = tidy(
				FULL_WIDTH.includes( type )
					? contentWidth
					: Math.min( item.w, contentWidth )
			);
			if ( isFree( section ) ) {
				// Cascade from the top left so a new component never hides exactly under another.
				let k = 0;
				while (
					k < 20 &&
					section.items.some(
						( it ) =>
							Math.abs( it.x - k * 5 ) < 0.5 &&
							Math.abs( it.y - k * 5 ) < 0.5
					)
				) {
					k++;
				}
				item.x = k * 5;
				item.y = k * 5;
			}
			setLayout( insertItem( next, s, item ) );
			setSelection( { kind: 'items', ids: [ item.id ] } );
		},
		insertAt( type, t ) {
			const item = newItem( config.blockTypes, config.columnTypes, type, {
				x: t.x,
				y: t.y || 0,
				w: tidy(
					FULL_WIDTH.includes( type ) && t.index !== undefined
						? contentWidth
						: t.w
				),
				h: t.h,
			} );
			setLayout( insertItem( layout, t.s, item, t.index ?? null ) );
			setSelection( { kind: 'items', ids: [ item.id ] } );
		},
		addSection( kind ) {
			const next = clone( layout );
			const names = {
				canvas: __( 'Free-form section', 'order-documents' ),
				flow: __( 'Flowing section', 'order-documents' ),
				footer: __( 'Page footer', 'order-documents' ),
			};
			const section = newSection( kind, names[ kind ] );
			if ( kind === 'footer' ) {
				section.height = 20;
				next.sections.push( section );
			} else {
				const footerAt = next.sections.findIndex(
					( x ) => x.kind === 'footer'
				);
				next.sections.splice(
					footerAt === -1 ? next.sections.length : footerAt,
					0,
					section
				);
			}
			setLayout( next );
			setSelection( { kind: 'section', id: section.id } );
		},
		removeSection( s ) {
			const section = layout.sections[ s ];
			const next = clone( layout );
			next.sections.splice( s, 1 );
			setLayout( next );
			setSelection( null );
			snack(
				section.items.length
					? sprintf(
							/* translators: %d: number of components */ __(
								'Section deleted, with %d components.',
								'order-documents'
							),
							section.items.length
					  )
					: __( 'Section deleted.', 'order-documents' ),
				true
			);
		},
		moveSection( from, to ) {
			if ( to < 0 || to >= layout.sections.length ) {
				return;
			}
			const next = clone( layout );
			const [ section ] = next.sections.splice( from, 1 );
			next.sections.splice( to, 0, section );
			setLayout( next );
		},
		setSectionKind( s, kind ) {
			const next = clone( layout );
			const section = next.sections[ s ];
			if ( section.kind === kind ) {
				return;
			}
			if ( kind === 'flow' ) {
				section.items.sort( ( a, b ) => a.y - b.y || a.x - b.x );
			} else {
				let y = 0;
				section.items.forEach( ( it ) => {
					it.y = tidy( y );
					y += it.h + 2;
				} );
				section.height = tidy( Math.max( section.height, y ) );
			}
			section.kind = kind;
			setLayout( fitSections( next ) );
		},
	};

	// Keyboard shortcuts (also forwarded from the preview frame).
	const keyHandler = useRef();
	keyHandler.current = ( e ) => {
		if ( ! isV2( layout ) || converting || confirm || showShortcuts ) {
			return;
		}
		const mod = e.ctrlKey || e.metaKey;
		const key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
		const t = e.target && e.target.closest ? e.target : null;
		const typing =
			t &&
			t.closest( 'input, textarea, select, [contenteditable="true"]' );
		const overlay =
			t && t.closest( '.components-popover, .components-modal__frame' );
		if ( mod && key === 's' ) {
			e.preventDefault();
			save();
			return;
		}
		if ( typing ) {
			return;
		}
		if ( mod && key === 'z' ) {
			e.preventDefault();
			return e.shiftKey ? history.redo() : history.undo();
		}
		if ( mod && key === 'y' ) {
			e.preventDefault();
			return history.redo();
		}
		if ( overlay ) {
			return;
		}
		if ( key === '?' ) {
			e.preventDefault();
			return setShowShortcuts( true );
		}
		const ids = selection?.kind === 'items' ? selection.ids : [];
		if ( mod && key === 'a' ) {
			e.preventDefault();
			let s =
				selection?.kind === 'section'
					? findSection( layout, selection.id )
					: -1;
			if ( ids.length ) {
				s = findItem( layout, ids[ 0 ] )?.s ?? -1;
			}
			const pool =
				s === -1
					? layout.sections.flatMap( ( x ) => x.items )
					: layout.sections[ s ].items;
			return setSelection(
				pool.length
					? { kind: 'items', ids: pool.map( ( it ) => it.id ) }
					: null
			);
		}
		if ( key === 'Escape' ) {
			e.preventDefault();
			if ( ids.length ) {
				const pos = findItem( layout, ids[ 0 ] );
				return setSelection(
					pos ? { kind: 'section', id: pos.section.id } : null
				);
			}
			return setSelection( null );
		}
		if ( ! ids.length ) {
			return;
		}
		if ( key === 'Delete' || key === 'Backspace' ) {
			e.preventDefault();
			return actions.remove( ids );
		}
		if ( mod && key === 'd' ) {
			e.preventDefault();
			return actions.duplicate( ids );
		}
		let bracket = null;
		if ( e.code === 'BracketRight' || key === ']' || key === '}' ) {
			bracket = 'up';
		} else if ( e.code === 'BracketLeft' || key === '[' || key === '{' ) {
			bracket = 'down';
		}
		if ( mod && bracket ) {
			e.preventDefault();
			let how = e.shiftKey ? 'back' : 'backward';
			if ( bracket === 'up' ) {
				how = e.shiftKey ? 'front' : 'forward';
			}
			return actions.layer( ids, how );
		}
		const arrows = {
			ArrowLeft: [ -1, 0 ],
			ArrowRight: [ 1, 0 ],
			ArrowUp: [ 0, -1 ],
			ArrowDown: [ 0, 1 ],
		};
		const inPane = t && t.closest( '.odocs-pane, .odocs-topbar' );
		if ( arrows[ key ] && ! mod && ! e.altKey && ! inPane ) {
			e.preventDefault();
			const step =
				( grid.snap ? grid.size : 0.5 ) * ( e.shiftKey ? 5 : 1 );
			const [ dx, dy ] = arrows[ key ];
			return actions.changeItems(
				ids,
				( it, section ) => {
					if ( it.locked ) {
						return;
					}
					it.x = tidy( it.x + dx * step );
					if ( isFree( section ) ) {
						it.y = tidy( Math.max( 0, it.y + dy * step ) );
					}
				},
				'nudge:' + ids.join( ',' )
			);
		}
	};
	const onKeyDown = useCallback( ( e ) => keyHandler.current( e ), [] );
	useEffect( () => {
		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ onKeyDown ] );

	if ( ! config ) {
		return (
			<div className="odocs-editor is-loading">
				{ error ? (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) : (
					<Spinner />
				) }
			</div>
		);
	}

	return (
		<div
			className={
				'odocs-editor' +
				( panes.structure ? ' has-structure' : '' ) +
				( panes.settings ? ' has-settings' : '' )
			}
		>
			<TopBar
				config={ config }
				type={ type }
				template={ template }
				orderId={ orderId }
				orders={ orders }
				onType={ changeType }
				onTemplate={ changeTemplate }
				onOrder={ setOrderId }
				history={ history }
				zoom={ zoom }
				setZoom={ setZoom }
				scale={ scale }
				panes={ panes }
				togglePane={ ( p ) =>
					setPanes( { ...panes, [ p ]: ! panes[ p ] } )
				}
				dirty={ dirty }
				saving={ saving }
				onSave={ save }
				onPreviewPdf={ previewPdf }
				onReset={ reset }
				canReset={ customized || dirtyLayout }
				wpMenu={ wpMenu }
				toggleWpMenu={ () => setWpMenu( ! wpMenu ) }
				grid={ grid }
				setGrid={ setGrid }
				openShortcuts={ () => setShowShortcuts( true ) }
				settingsUrl={ boot.settingsUrl }
			/>
			<div className="odocs-body">
				{ panes.structure && (
					<aside
						className="odocs-pane odocs-pane-structure"
						aria-label={ __( 'Layers', 'order-documents' ) }
					>
						{ isV2( layout ) ? (
							<Layers
								layout={ layout }
								config={ config }
								selection={ selection }
								actions={ actions }
							/>
						) : (
							<Spinner />
						) }
					</aside>
				) }
				<main className="odocs-main">
					{ error && (
						<Notice
							status="error"
							className="odocs-error"
							onRemove={ () => setError( null ) }
						>
							{ error }
						</Notice>
					) }
						{ layout && (
							<Canvas
								html={ html }
								loading={ loading }
								zoom={ zoom }
								onScale={ setScale }
								selection={ selection }
								layout={ layout }
								blockTypes={ config.blockTypes }
								actions={ actions }
								grid={ grid }
								converting={ converting }
								onContentWidth={ setContentWidth }
							/>
						) }
					<SnackbarList
						className="odocs-snackbars"
						notices={ snacks }
						onRemove={ ( id ) =>
							setSnacks( ( list ) =>
								list.filter( ( n ) => n.id !== id )
							)
						}
					/>
				</main>
				{ panes.settings && (
					<aside
						className="odocs-pane odocs-pane-settings"
						aria-label={ __( 'Settings', 'order-documents' ) }
					>
						{ isV2( layout ) && style ? (
							<Inspector
								layout={ layout }
								style={ style }
								setStyle={ setStyle }
								selection={ selection }
								onSelect={ setSelection }
								config={ config }
								palette={ palette }
								effectiveStyle={ effectiveStyle }
								actions={ actions }
							/>
						) : (
							<Spinner />
						) }
					</aside>
				) }
			</div>
			{ confirm && (
				<ConfirmModal
					{ ...confirm }
					onClose={ () => setConfirm( null ) }
				/>
			) }
			{ showShortcuts && (
				<Modal
					title={ __( 'Keyboard shortcuts', 'order-documents' ) }
					onRequestClose={ () => setShowShortcuts( false ) }
					className="odocs-shortcuts"
				>
					<dl>
						{ SHORTCUTS.map( ( [ keys, what ] ) => (
							<div key={ keys + what }>
								<dt>{ what }</dt>
								<dd>
									{ keys.split( ' / ' ).map( ( k, i ) => (
										<kbd key={ i }>{ k }</kbd>
									) ) }
								</dd>
							</div>
						) ) }
					</dl>
				</Modal>
			) }
		</div>
	);
}
