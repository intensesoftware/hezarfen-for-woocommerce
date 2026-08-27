/**
 * Hezarfen returns — front-end enhancements.
 *
 * Progressive enhancement only: without this file the form still submits
 * and the server performs the exact same validation. The script collapses
 * the per-item detail fields until a line is picked, reveals the note
 * field for reasons that need one, keeps a live summary, stops an
 * obviously incomplete form before it costs the customer a round trip, and
 * chains the pickup address selects. Without it the address selects still
 * carry the values the server rendered, so an address that was already
 * correct can be submitted untouched.
 */
( function () {
	'use strict';

	var settings = window.hezarfenReturns || {};
	var reasonsRequiringNote = settings.reasonsRequiringNote || [];
	var i18n = settings.i18n || {};

	function requiresNote( reason ) {
		return reasonsRequiringNote.indexOf( reason ) !== -1;
	}

	function selectedItems( form ) {
		return Array.prototype.filter.call(
			form.querySelectorAll( '[data-hez-item-toggle]' ),
			function ( checkbox ) {
				return checkbox.checked;
			}
		);
	}

	function syncItem( item ) {
		var checkbox = item.querySelector( '[data-hez-item-toggle]' );
		var details = item.querySelector( '[data-hez-item-details]' );
		var reason = item.querySelector( '[data-hez-reason]' );
		var noteField = item.querySelector( '[data-hez-note-field]' );

		if ( ! checkbox || ! details ) {
			return;
		}

		details.hidden = ! checkbox.checked;
		item.classList.toggle( 'is-selected', checkbox.checked );

		if ( noteField && reason ) {
			noteField.hidden = ! checkbox.checked || ! requiresNote( reason.value );
		}
	}

	function updateSummary( form ) {
		var summary = form.querySelector( '[data-hez-summary]' );

		if ( ! summary ) {
			return;
		}

		var count = selectedItems( form ).reduce( function ( total, checkbox ) {
			var item = checkbox.closest( '[data-hez-item]' );
			var qty = item && item.querySelector( '.hez-input--qty' );

			return total + ( qty ? parseInt( qty.value, 10 ) || 0 : 1 );
		}, 0 );

		summary.textContent = count
			? ( i18n.summary || '%d ürün seçildi' ).replace( '%d', count )
			: '';
	}

	/**
	 * Client-side mirror of the server rules. It exists to save the
	 * customer a page load, never to be the only check.
	 */
	function validate( form ) {
		var selected = selectedItems( form );

		if ( ! selected.length ) {
			return i18n.selectAtLeastOne || '';
		}

		for ( var i = 0; i < selected.length; i++ ) {
			var item = selected[ i ].closest( '[data-hez-item]' );
			var reason = item && item.querySelector( '[data-hez-reason]' );

			if ( ! reason || ! reason.value ) {
				return i18n.reasonRequired || '';
			}

			if ( requiresNote( reason.value ) ) {
				var note = item.querySelector( '[data-hez-note-field] textarea' );

				if ( ! note || ! note.value.trim() ) {
					return i18n.noteRequired || '';
				}
			}
		}

		return '';
	}

	function showError( form, message ) {
		var summary = form.querySelector( '[data-hez-summary]' );

		if ( summary ) {
			summary.textContent = message;
			summary.classList.add( 'is-error' );
		} else {
			window.alert( message );
		}
	}

	function initForm( form ) {
		var items = form.querySelectorAll( '[data-hez-item]' );

		Array.prototype.forEach.call( items, function ( item ) {
			syncItem( item );

			item.addEventListener( 'change', function ( event ) {
				if (
					event.target.matches(
						'[data-hez-item-toggle], [data-hez-reason]'
					)
				) {
					syncItem( item );
				}

				updateSummary( form );
			} );

			item.addEventListener( 'input', function () {
				updateSummary( form );
			} );
		} );

		updateSummary( form );

		form.addEventListener( 'submit', function ( event ) {
			var error = validate( form );

			if ( error ) {
				event.preventDefault();
				showError( form, error );
			}
		} );
	}

	function initCancelConfirm( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if (
				i18n.confirmCancel &&
				! window.confirm( i18n.confirmCancel )
			) {
				event.preventDefault();
			}
		} );
	}


	function initUnbookConfirm( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if (
				typeof window.confirm === 'function' &&
				i18n.confirmUnbook &&
				! window.confirm( i18n.confirmUnbook )
			) {
				event.preventDefault();
			}
		} );
	}

	/* --------------------------------------------------------- copy code */

	/**
	 * Copies the return code to the clipboard.
	 *
	 * navigator.clipboard only exists in a secure context, and plenty of
	 * shops still run their account pages over plain http, so the old
	 * execCommand path is not a legacy nicety here — it is the one that
	 * actually runs on those sites.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var helper = document.createElement( 'textarea' );

			helper.value = text;
			helper.setAttribute( 'readonly', 'readonly' );
			helper.style.position = 'fixed';
			helper.style.top = '-1000px';
			document.body.appendChild( helper );
			helper.select();

			try {
				document.execCommand( 'copy' ) ? resolve() : reject();
			} catch ( error ) {
				reject( error );
			}

			document.body.removeChild( helper );
		} );
	}

	function initCopy( button ) {
		var block = button.closest( '.hez-code' );
		var source = block && block.querySelector( '[data-hez-copy-source]' );

		if ( ! source ) {
			return;
		}

		var original = button.textContent;
		var timer = null;

		button.addEventListener( 'click', function () {
			copyText( source.textContent.trim() )
				.then( function () {
					button.textContent = i18n.copied || original;
					button.classList.add( 'is-copied' );
				} )
				.catch( function () {
					// Nothing was copied, so the code has to stay reachable:
					// selecting it lets the customer copy it by hand.
					button.textContent = i18n.copyFailed || original;

					var range = document.createRange();
					range.selectNodeContents( source );
					window.getSelection().removeAllRanges();
					window.getSelection().addRange( range );
				} )
				.then( function () {
					window.clearTimeout( timer );
					timer = window.setTimeout( function () {
						button.textContent = original;
						button.classList.remove( 'is-copied' );
					}, 2500 );
				} );
		} );
	}

	/* ----------------------------------------------------- pickup address */

	/**
	 * Binds a change handler.
	 *
	 * Through jQuery when it is there, because selectWoo announces a pick
	 * with a jQuery event that a plain addEventListener never hears. jQuery
	 * still receives ordinary native changes, so this one path covers both
	 * the enhanced and the bare select.
	 */
	function onChange( element, handler ) {
		if ( window.jQuery ) {
			window.jQuery( element ).on( 'change', handler );

			return;
		}

		element.addEventListener( 'change', handler );
	}

	function selectWooAvailable() {
		return !! ( window.jQuery && window.jQuery.fn && window.jQuery.fn.selectWoo );
	}

	function enhanceSelect( select ) {
		if ( ! selectWooAvailable() || select.hezEnhanced ) {
			return;
		}

		// A select inside a closed <details> has no width yet, and selectWoo
		// would freeze that zero into the replacement box. Enhance it when
		// it is first revealed instead.
		if ( ! select.offsetParent ) {
			return;
		}

		var placeholder = select.options.length ? select.options[ 0 ].text : '';

		window.jQuery( select ).selectWoo( {
			width: '100%',
			placeholder: placeholder,
			dropdownCssClass: 'hez-select2-dropdown',
			language: {
				noResults: function () {
					return i18n.searchNoResults || '';
				},
				searching: function () {
					return i18n.searching || '';
				}
			}
		} );

		select.hezEnhanced = true;
	}

	/** Tells selectWoo its options changed, without re-firing our handlers. */
	function refreshSelect( select ) {
		if ( select.hezEnhanced ) {
			window.jQuery( select ).trigger( 'change.select2' );
		}
	}

	function fillSelect( select, values, selected, placeholder ) {
		select.innerHTML = '';

		var blank = document.createElement( 'option' );
		blank.value = '';
		blank.textContent = placeholder;
		select.appendChild( blank );

		values.forEach( function ( value ) {
			var option = document.createElement( 'option' );
			option.value = value;
			option.textContent = value;

			if ( value === selected ) {
				option.selected = true;
			}

			select.appendChild( option );
		} );

		refreshSelect( select );
	}

	function fetchOptions( params ) {
		if ( ! settings.addressEndpoint || ! settings.addressNonce ) {
			return Promise.reject();
		}

		var url = new URL( settings.addressEndpoint, window.location.origin );
		url.searchParams.set( 'action', 'hezarfen_returns_address_options' );
		url.searchParams.set( 'security', settings.addressNonce );

		Object.keys( params ).forEach( function ( key ) {
			url.searchParams.set( key, params[ key ] );
		} );

		return fetch( url.toString(), { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				return payload && payload.success ? payload.data : {};
			} );
	}

	function initAddressFields( fields ) {
		var city = fields.querySelector( '[data-hez-address-city]' );
		var district = fields.querySelector( '[data-hez-address-district]' );
		var neighborhood = fields.querySelector( '[data-hez-address-neighborhood]' );

		if ( ! city || ! district || ! neighborhood ) {
			return;
		}

		var selects = [ city, district, neighborhood ];

		selects.forEach( enhanceSelect );

		// The address block on an approved request lives in a <details>;
		// its selects are only measurable once it opens.
		var container = fields.closest( 'details' );

		if ( container ) {
			container.addEventListener( 'toggle', function () {
				selects.forEach( enhanceSelect );
			} );
		}

		function loadDistricts( selected ) {
			if ( ! city.value ) {
				fillSelect( district, [], '', i18n.selectDistrict );
				fillSelect( neighborhood, [], '', i18n.selectNeighborhood );

				return;
			}

			district.disabled = true;

			fetchOptions( { city_code: city.value } )
				.then( function ( data ) {
					fillSelect( district, data.districts || [], selected || '', i18n.selectDistrict );
					district.disabled = false;

					// A district that survived the city change keeps its
					// neighbourhoods; otherwise the list has to be emptied
					// so a stale neighbourhood cannot be submitted.
					loadNeighborhoods( district.value ? neighborhood.value : '' );
				} )
				.catch( function () {
					district.disabled = false;
				} );
		}

		function loadNeighborhoods( selected ) {
			if ( ! city.value || ! district.value ) {
				fillSelect( neighborhood, [], '', i18n.selectNeighborhood );

				return;
			}

			neighborhood.disabled = true;

			fetchOptions( { city_code: city.value, district: district.value } )
				.then( function ( data ) {
					fillSelect( neighborhood, data.neighborhoods || [], selected || '', i18n.selectNeighborhood );
					neighborhood.disabled = false;
				} )
				.catch( function () {
					neighborhood.disabled = false;
				} );
		}

		onChange( city, function () {
			loadDistricts( '' );
		} );

		onChange( district, function () {
			loadNeighborhoods( '' );
		} );
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-return-form]' ),
			initForm
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-confirm-cancel]' ),
			initCancelConfirm
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-address-fields]' ),
			initAddressFields
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-copy]' ),
			initCopy
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-confirm-unbook]' ),
			initUnbookConfirm
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
