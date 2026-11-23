/* global jQuery, odocsAdmin, wp */
( function ( $ ) {
	'use strict';
	var cfg = window.odocsAdmin || {};

	function replaceBoxes( data ) {
		if ( ! data ) {
			return;
		}
		$( '#odocs-documents .inside' ).html( data.buttons );
		var $dataBox = $( '#odocs-document-data .inside' );
		$dataBox.find( '.odocs-data, .odocs-data-empty' ).remove();
		$dataBox.append( data.data );
	}

	function refresh( orderId ) {
		$.post( cfg.ajaxUrl, { action: 'odocs_metabox', nonce: cfg.nonce, order_id: orderId }, function ( res ) {
			if ( res && res.success ) {
				replaceBoxes( res.data );
			}
		} );
	}

	// Order screen: refresh the boxes after a document was opened (it may have been created).
	$( document ).on( 'click', '.odocs-metabox a.odocs-open', function () {
		var orderId = $( this ).closest( '.odocs-metabox' ).data( 'order-id' );
		setTimeout( function () {
			refresh( orderId );
		}, 2500 );
	} );

	$( document ).on( 'click', '.odocs-edit-document', function () {
		var $doc = $( this ).closest( '.odocs-data-doc' );
		$doc.find( '.odocs-data-edit' ).prop( 'hidden', false );
		$doc.find( '.odocs-data-view' ).hide();
		$doc.find( '.odocs-edited' ).val( '1' );
		$( this ).hide();
	} );

	function docAction( button, action, confirmText ) {
		if ( confirmText && ! window.confirm( confirmText ) ) {
			return;
		}
		var $doc = $( button ).closest( '.odocs-data-doc' );
		$doc.css( 'opacity', 0.5 );
		$.post(
			cfg.ajaxUrl,
			{
				action: action,
				nonce: cfg.nonce,
				order_id: $doc.closest( '.odocs-data' ).data( 'order-id' ),
				type: $doc.data( 'type' ),
				refund_id: $doc.data( 'refund-id' ) || 0
			},
			function ( res ) {
				if ( res && res.success ) {
					replaceBoxes( res.data );
				} else {
					$doc.css( 'opacity', 1 );
					window.alert( ( res && res.data && res.data.message ) || 'Error' );
				}
			}
		);
	}

	$( document ).on( 'click', '.odocs-delete-document', function () {
		docAction( this, 'odocs_delete_document', cfg.i18n.confirmDelete );
	} );
	$( document ).on( 'click', '.odocs-regenerate-document', function () {
		docAction( this, 'odocs_regenerate_document' );
	} );

	// Orders list: bulk print opens one merged PDF in a new tab.
	$( document ).on( 'click', '#doaction, #doaction2', function ( e ) {
		var which = this.id === 'doaction2' ? 'action2' : 'action';
		var action = $( 'select[name="' + which + '"]' ).val() || '';
		if ( action.indexOf( 'odocs_bulk_' ) !== 0 ) {
			return;
		}
		e.preventDefault();
		var ids = $( 'input[name="post[]"]:checked, input[name="id[]"]:checked' )
			.map( function () {
				return this.value;
			} )
			.get();
		if ( ! ids.length ) {
			window.alert( cfg.i18n.noOrders );
			return;
		}
		var url =
			cfg.ajaxUrl +
			'?action=odocs_document&type=' +
			encodeURIComponent( action.substring( 11 ) ) +
			'&order_ids=' +
			ids.join( ',' ) +
			'&_wpnonce=' +
			cfg.documentNonce;
		window.open( url, '_blank' );
	} );

	// Settings page.
	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.odocs-color' ).wpColorPicker();
		}
		$( document ).on( 'change', '.odocs-template-card input', function () {
			$( '.odocs-template-card' ).removeClass( 'is-selected' );
			$( this ).closest( '.odocs-template-card' ).addClass( 'is-selected' );
		} );
		$( document ).on( 'click', '.odocs-media-select', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.odocs-media' );
			var frame = wp.media( {
				title: cfg.i18n.chooseLogo,
				button: { text: cfg.i18n.useImage },
				library: { type: 'image' },
				multiple: false
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				$wrap.find( 'input[type=hidden]' ).val( att.id );
				$wrap.find( '.odocs-media-preview' ).html( '<img src="' + url + '" alt="">' );
				$wrap.find( '.odocs-media-remove' ).prop( 'hidden', false );
			} );
			frame.open();
		} );
		$( document ).on( 'click', '.odocs-media-remove', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.odocs-media' );
			$wrap.find( 'input[type=hidden]' ).val( '' );
			$wrap.find( '.odocs-media-preview' ).empty();
			$( this ).prop( 'hidden', true );
		} );
	} );
} )( jQuery );
