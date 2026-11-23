/**
 * Visual document editor.
 */
import { useEffect, useRef, useState, useCallback } from '@wordpress/element';
import { Button, SelectControl, Spinner, Notice, TabPanel } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import Outline from './Outline';
import Inspector from './Inspector';
import StylePanel from './StylePanel';

const boot = window.odocsEditor || {};
const SAMPLE = '0';

export default function App() {
	const [ config, setConfig ] = useState( null );
	const [ type, setType ] = useState( boot.type || 'invoice' );
	const [ template, setTemplate ] = useState( boot.template || 'classic' );
	const [ orderId, setOrderId ] = useState( String( boot.orderId || SAMPLE ) );
	const [ orders, setOrders ] = useState( [] );
	const [ layout, setLayoutState ] = useState( null );
	const [ savedLayout, setSavedLayout ] = useState( null );
	const [ customized, setCustomized ] = useState( false );
	const [ style, setStyle ] = useState( null );
	const [ savedStyle, setSavedStyle ] = useState( null );
	const [ selected, setSelected ] = useState( null );
	const [ tab, setTab ] = useState( 'layout' );
	const [ html, setHtml ] = useState( '' );
	const [ previewing, setPreviewing ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const iframe = useRef();
	const tabRef = useRef();

	const dirtyLayout = layout && savedLayout && JSON.stringify( layout ) !== JSON.stringify( savedLayout );
	const dirtyStyle = style && savedStyle && JSON.stringify( style ) !== JSON.stringify( savedStyle );
	const dirty = dirtyLayout || dirtyStyle;

	const setLayout = useCallback( ( next ) => setLayoutState( next ), [] );

	// Config + recent orders.
	useEffect( () => {
		apiFetch( { path: '/odocs/v1/editor-config' } )
			.then( ( cfg ) => {
				setConfig( cfg );
				setStyle( cfg.design );
				setSavedStyle( cfg.design );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		apiFetch( { path: '/odocs/v1/orders' } ).then( setOrders ).catch( () => {} );
	}, [] );

	// Layout for the chosen template + type.
	useEffect( () => {
		setLayoutState( null );
		setSelected( null );
		apiFetch( { path: `/odocs/v1/layout?template=${ template }&type=${ type }` } )
			.then( ( res ) => {
				setLayoutState( res.layout );
				setSavedLayout( res.layout );
				setCustomized( res.customized );
			} )
			.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) );
		const url = new URL( window.location.href );
		url.searchParams.set( 'type', type );
		url.searchParams.set( 'template', template );
		window.history.replaceState( null, '', url );
	}, [ template, type ] );

	// Debounced live preview.
	useEffect( () => {
		if ( ! layout || ! style ) {
			return;
		}
		setPreviewing( true );
		const timer = setTimeout( () => {
			apiFetch( {
				path: '/odocs/v1/preview',
				method: 'POST',
				data: { template, type, layout, style, order_id: Number( orderId ) },
			} )
				.then( ( res ) => setHtml( res.html ) )
				.catch( ( e ) => setNotice( { status: 'error', text: e.message } ) )
				.finally( () => setPreviewing( false ) );
		}, 400 );
		return () => clearTimeout( timer );
	}, [ layout, style, template, type, orderId ] );

	// Clicks in the preview select blocks.
	useEffect( () => {
		const onMessage = ( e ) => {
			if ( e.data?.source === 'odocs-preview' && e.data.block ) {
				setSelected( { kind: 'block', id: e.data.block } );
				setTab( 'block' );
			}
		};
		window.addEventListener( 'message', onMessage );
		return () => window.removeEventListener( 'message', onMessage );
	}, [] );

	// Highlight the selected block in the preview.
	const syncSelection = useCallback( () => {
		iframe.current?.contentWindow?.postMessage(
			{ source: 'odocs-editor', block: selected?.kind === 'block' ? selected.id : null },
			'*'
		);
	}, [ selected ] );
	useEffect( syncSelection, [ syncSelection, html ] );

	// Warn about unsaved changes.
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

	const confirmDiscard = () =>
		// eslint-disable-next-line no-alert
		! dirtyLayout || window.confirm( __( 'You have unsaved layout changes. Discard them?', 'order-documents' ) );

	const save = async () => {
		setSaving( true );
		try {
			if ( dirtyLayout ) {
				const res = await apiFetch( { path: '/odocs/v1/layout', method: 'POST', data: { template, type, layout } } );
				setLayoutState( res.layout );
				setSavedLayout( res.layout );
				setCustomized( true );
			}
			if ( dirtyStyle ) {
				const res = await apiFetch( { path: '/odocs/v1/design', method: 'POST', data: { style } } );
				setStyle( res.design );
				setSavedStyle( res.design );
			}
			setNotice( { status: 'success', text: __( 'Saved.', 'order-documents' ) } );
		} catch ( e ) {
			setNotice( { status: 'error', text: e.message } );
		}
		setSaving( false );
	};

	const reset = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Reset this layout to the template default? Your changes to it will be lost.', 'order-documents' ) ) ) {
			return;
		}
		const res = await apiFetch( { path: `/odocs/v1/layout?template=${ template }&type=${ type }`, method: 'DELETE' } );
		setLayoutState( res.layout );
		setSavedLayout( res.layout );
		setCustomized( false );
		setSelected( null );
		setNotice( { status: 'success', text: __( 'Layout reset to the template default.', 'order-documents' ) } );
	};

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
		add( 'payload', JSON.stringify( { template, type, layout, style, order_id: Number( orderId ) } ) );
		document.body.appendChild( form );
		form.submit();
		form.remove();
	};

	const onSelect = ( sel ) => {
		setSelected( sel );
		if ( sel ) {
			setTab( 'block' );
		}
	};

	if ( ! config || ! style ) {
		return notice ? <Notice status="error" isDismissible={ false }>{ notice.text }</Notice> : <Spinner />;
	}

	const docType = config.documentTypes.find( ( t ) => t.id === type );

	return (
		<div className="odocs-editor">
			<div className="odocs-toolbar">
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Document', 'order-documents' ) }
					value={ type }
					options={ config.documentTypes.map( ( t ) => ( { value: t.id, label: t.label + ( t.enabled ? '' : ' (' + __( 'off', 'order-documents' ) + ')' ) } ) ) }
					onChange={ ( v ) => {
						if ( confirmDiscard() ) {
							setType( v );
							const t = config.documentTypes.find( ( d ) => d.id === v );
							if ( t?.template ) {
								setTemplate( t.template );
							}
						}
					} }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Template', 'order-documents' ) }
					value={ template }
					options={ config.templates.map( ( t ) => ( { value: t.id, label: t.name + ( t.id === docType?.template ? ' ✓' : '' ) } ) ) }
					onChange={ ( v ) => confirmDiscard() && setTemplate( v ) }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Preview with', 'order-documents' ) }
					value={ orderId }
					options={ [
						{ value: SAMPLE, label: __( 'Sample data', 'order-documents' ) },
						...orders.map( ( o ) => ( { value: String( o.id ), label: o.label } ) ),
					] }
					onChange={ setOrderId }
				/>
				<div className="odocs-toolbar-actions">
					{ previewing && <Spinner /> }
					<Button variant="tertiary" onClick={ reset } disabled={ ! customized && ! dirtyLayout }>
						{ __( 'Reset to default', 'order-documents' ) }
					</Button>
					<Button variant="secondary" icon="pdf" onClick={ previewPdf }>
						{ __( 'Preview PDF', 'order-documents' ) }
					</Button>
					<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ ! dirty || saving }>
						{ dirty ? __( 'Save', 'order-documents' ) : __( 'Saved', 'order-documents' ) }
					</Button>
				</div>
			</div>
			{ docType?.template && docType.template !== template && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'You are editing a template this document type does not use. Choose the template under Order Documents → Documents or Design.', 'order-documents' ) }
				</Notice>
			) }
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.text }
				</Notice>
			) }
			<div className="odocs-editor-body">
				<div className="odocs-sidebar" ref={ tabRef }>
					<TabPanel
						key={ tab }
						initialTabName={ tab }
						onSelect={ ( name ) => name !== tab && setTab( name ) }
						tabs={ [
							{ name: 'layout', title: __( 'Layout', 'order-documents' ) },
							{ name: 'block', title: __( 'Block', 'order-documents' ) },
							{ name: 'style', title: __( 'Style', 'order-documents' ) },
						] }
					>
						{ ( t ) => {
							if ( ! layout ) {
								return <Spinner />;
							}
							if ( t.name === 'layout' ) {
								return <Outline layout={ layout } setLayout={ setLayout } config={ config } selected={ selected } onSelect={ onSelect } />;
							}
							if ( t.name === 'block' ) {
								return <Inspector layout={ layout } setLayout={ setLayout } selected={ selected } config={ config } onSelect={ setSelected } />;
							}
							return <StylePanel fields={ config.designFields } style={ style } setStyle={ setStyle } />;
						} }
					</TabPanel>
				</div>
				<div className="odocs-preview">
					<iframe ref={ iframe } title={ __( 'Preview', 'order-documents' ) } srcDoc={ html } onLoad={ syncSelection } />
				</div>
			</div>
		</div>
	);
}
