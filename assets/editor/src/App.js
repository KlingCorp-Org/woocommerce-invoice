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
import Outline from './Outline';
import Canvas from './Canvas';
import Inspector from './Inspector';
import useHistory from './useHistory';
import {
	blockDefaults,
	duplicateBlock,
	duplicateRow,
	findBlock,
	findRow,
	insertBlock,
	insertionPoint,
	insertRow,
	moveBlockBy,
	moveRow,
	removeBlock,
	rowWithWidths,
	uid,
} from './utils';

const boot = window.odocsEditor || {};
const MENU_KEY = 'odocs-editor-wp-menu';

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

const isMac =
	typeof navigator !== 'undefined' &&
	/Mac|iPhone|iPad/.test( navigator.platform || '' );
const MOD = isMac ? '⌘' : 'Ctrl';

const SHORTCUTS = [
	[ `${ MOD }+S`, __( 'Save', 'order-documents' ) ],
	[ `${ MOD }+Z`, __( 'Undo', 'order-documents' ) ],
	[ `${ MOD }+Shift+Z`, __( 'Redo', 'order-documents' ) ],
	[
		`${ MOD }+D`,
		__( 'Duplicate the selected block or row', 'order-documents' ),
	],
	[ 'Delete', __( 'Delete the selected block or row', 'order-documents' ) ],
	[
		'Alt+↑ / Alt+↓',
		__( 'Move the selected block or row', 'order-documents' ),
	],
	[ '↑ / ↓', __( 'Move through the structure list', 'order-documents' ) ],
	[
		'Esc',
		__( 'Select the parent (block → row → document)', 'order-documents' ),
	],
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
	const [ selected, setSelected ] = useState( null );
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
	const [ panes, setPanes ] = useState( {
		structure: window.innerWidth >= 1100,
		settings: true,
	} );

	const layout = history.present?.layout || null;
	const style = history.present?.style || null;
	const styleRef = useRef( null );
	styleRef.current = style || config?.design || null;

	const dirtyLayout = useMemo(
		() => !! layout && !! saved.layout && ! same( layout, saved.layout ),
		[ layout, saved.layout ]
	);
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
		setSelected( null );
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
				setSaved( ( s ) => ( { ...s, layout: res.layout } ) );
				setCustomized( res.customized );
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
							setLayout( res.layout );
							setSaved( ( s ) => ( {
								...s,
								layout: res.layout,
							} ) );
							setCustomized( false );
							setSelected( null );
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

	const label = ( block ) =>
		config.blockTypes[ block.type ]?.label || block.type;

	const actions = {
		select: setSelected,
		addBlock( blockType, point ) {
			const block = {
				id: uid( 'b' ),
				type: blockType,
				settings: blockDefaults(
					config.blockTypes,
					config.columnTypes,
					blockType
				),
			};
			const at = point || insertionPoint( layout, selected );
			if ( ! at ) {
				const row = rowWithWidths( [ 100 ] );
				row.columns[ 0 ].blocks.push( block );
				setLayout( insertRow( layout, layout.rows.length, row ) );
			} else {
				setLayout( insertBlock( layout, at.r, at.c, at.index, block ) );
			}
			setSelected( { kind: 'block', id: block.id } );
		},
		removeBlock( id ) {
			const pos = findBlock( layout, id );
			if ( ! pos ) {
				return;
			}
			const block = layout.rows[ pos.r ].columns[ pos.c ].blocks[ pos.b ];
			setLayout( removeBlock( layout, id ) );
			setSelected( { kind: 'row', id: layout.rows[ pos.r ].id } );
			snack(
				sprintf(
					/* translators: %s: block name */ __(
						'%s deleted.',
						'order-documents'
					),
					label( block )
				),
				true
			);
		},
		duplicateBlock( id ) {
			const [ next, newId ] = duplicateBlock( layout, id );
			if ( newId ) {
				setLayout( next );
				setSelected( { kind: 'block', id: newId } );
			}
		},
		moveBlockBy( id, dir ) {
			setLayout( moveBlockBy( layout, id, dir ) );
		},
		addRow( widths, index = layout.rows.length ) {
			const row = rowWithWidths( widths );
			setLayout( insertRow( layout, index, row ) );
			setSelected( { kind: 'row', id: row.id } );
		},
		duplicateRow( r ) {
			const [ next, newId ] = duplicateRow( layout, r );
			setLayout( next );
			setSelected( { kind: 'row', id: newId } );
		},
		removeRow( r ) {
			const next = {
				...layout,
				rows: layout.rows.filter( ( _, i ) => i !== r ),
			};
			setLayout( next );
			setSelected( null );
			snack(
				sprintf(
					/* translators: %d: row number */ __(
						'Row %d deleted.',
						'order-documents'
					),
					r + 1
				),
				true
			);
		},
		moveRow( from, to ) {
			setLayout( moveRow( layout, from, to ) );
		},
	};

	// Keyboard shortcuts (also forwarded from the preview frame).
	const keyHandler = useRef();
	keyHandler.current = ( e ) => {
		if ( ! layout || confirm || showShortcuts ) {
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
		if ( ! selected ) {
			return;
		}
		const isBlock = selected.kind === 'block';
		const r = isBlock
			? findBlock( layout, selected.id )?.r
			: findRow( layout, selected.id );
		if ( r === undefined || r === -1 ) {
			return;
		}
		if ( key === 'Escape' ) {
			e.preventDefault();
			return setSelected(
				isBlock ? { kind: 'row', id: layout.rows[ r ].id } : null
			);
		}
		if ( key === 'Delete' || key === 'Backspace' ) {
			e.preventDefault();
			return isBlock
				? actions.removeBlock( selected.id )
				: actions.removeRow( r );
		}
		if ( mod && key === 'd' ) {
			e.preventDefault();
			return isBlock
				? actions.duplicateBlock( selected.id )
				: actions.duplicateRow( r );
		}
		if ( e.altKey && ( key === 'ArrowUp' || key === 'ArrowDown' ) ) {
			e.preventDefault();
			const dir = key === 'ArrowUp' ? -1 : 1;
			return isBlock
				? actions.moveBlockBy( selected.id, dir )
				: actions.moveRow( r, r + dir );
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
				openShortcuts={ () => setShowShortcuts( true ) }
				settingsUrl={ boot.settingsUrl }
			/>
			<div className="odocs-body">
				{ panes.structure && (
					<aside
						className="odocs-pane odocs-pane-structure"
						aria-label={ __( 'Structure', 'order-documents' ) }
					>
						{ layout ? (
							<Outline
								layout={ layout }
								setLayout={ setLayout }
								config={ config }
								selected={ selected }
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
							selected={ selected }
							layout={ layout }
							blockTypes={ config.blockTypes }
							actions={ actions }
							onKeyDown={ onKeyDown }
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
						{ layout && style ? (
							<Inspector
								layout={ layout }
								setLayout={ setLayout }
								style={ style }
								setStyle={ setStyle }
								selected={ selected }
								onSelect={ setSelected }
								config={ config }
								palette={ palette }
								effectiveStyle={ effectiveStyle }
								onDuplicate={ () =>
									selected?.kind === 'block'
										? actions.duplicateBlock( selected.id )
										: actions.duplicateRow(
												findRow( layout, selected.id )
										  )
								}
								onDelete={ () =>
									selected?.kind === 'block'
										? actions.removeBlock( selected.id )
										: actions.removeRow(
												findRow( layout, selected.id )
										  )
								}
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
							<div key={ keys }>
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
