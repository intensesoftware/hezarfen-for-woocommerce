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
					// selecting it lets the customer copy it by hand. This must
					// not throw — getSelection() can be null, and a throw here
					// would skip the label-reset below and leave the button
					// stuck on "copy failed".
					button.textContent = i18n.copyFailed || original;

					try {
						var selection = window.getSelection();

						if ( selection ) {
							var range = document.createRange();
							range.selectNodeContents( source );
							selection.removeAllRanges();
							selection.addRange( range );
						}
					} catch ( error ) {
						// Selecting the code is a courtesy; the code is still on
						// screen to copy by hand if the browser refuses.
					}
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

	/**
	 * Marks a select as loading.
	 *
	 * Deliberately not `disabled`: a disabled control is skipped by the
	 * browser's `required` check and is left out of the submission entirely,
	 * so a submit landing mid-fetch would post an empty district and fail
	 * server side with a field that looks filled in on screen. The submit is
	 * held back instead — see the guard below.
	 */
	function setBusy( select, busy ) {
		select.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		select.classList.toggle( 'is-loading', busy );

		// selectWoo hides the native <select> and renders a container in its
		// place, so styling and announcing the state on the native control
		// alone is invisible on the sites where the enhancement is active —
		// which is most of them, and exactly the ones that hit the round trip.
		// Mirror the state onto the visible widget too.
		if ( select.hezEnhanced && window.jQuery ) {
			window.jQuery( select )
				.next( '.select2' )
				.attr( 'aria-busy', busy ? 'true' : 'false' )
				.toggleClass( 'is-loading', busy );
		}
	}

	function initAddressFields( fields ) {
		var city = fields.querySelector( '[data-hez-address-city]' );
		var district = fields.querySelector( '[data-hez-address-district]' );
		var neighborhood = fields.querySelector( '[data-hez-address-neighborhood]' );

		if ( ! city || ! district || ! neighborhood ) {
			return;
		}

		var selects = [ city, district, neighborhood ];
		var pending = 0;
		// One generation counter per list: a fast city→city switch leaves two
		// fetches in flight, and without this the slower (older) response could
		// land last and leave a district list that belongs to the wrong city.
		var districtSeq = 0;
		var neighborhoodSeq = 0;
		var form = fields.closest( 'form' );

		if ( form ) {
			form.addEventListener(
				'submit',
				function ( event ) {
					if ( pending < 1 ) {
						return;
					}

					// The lists the customer is about to be judged on are
					// still in flight; sending now would submit an address
					// they never got to finish.
					event.preventDefault();
					showError( form, i18n.addressLoading || '' );
				},
				// Captured, so it runs before the form's own submit handlers.
				true
			);
		}

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
			// Emptied before the round trip, not after: the options on screen
			// belong to the city that was just replaced, and leaving them
			// selectable is how a district from another province gets
			// submitted.
			fillSelect( district, [], '', i18n.selectDistrict );
			fillSelect( neighborhood, [], '', i18n.selectNeighborhood );

			if ( ! city.value ) {
				return;
			}

			var seq = ++districtSeq;

			pending++;
			setBusy( district, true );

			fetchOptions( { city_code: city.value } )
				.then( function ( data ) {
					// A newer city change has already fired; that request owns
					// the list now, so this stale response is dropped.
					if ( seq !== districtSeq ) {
						return;
					}

					fillSelect( district, data.districts || [], selected || '', i18n.selectDistrict );

					// A district that survived the city change keeps its
					// neighbourhoods; otherwise the list stays empty.
					if ( district.value ) {
						loadNeighborhoods( neighborhood.value );
					}
				} )
				.catch( function () {
					// Nonce aged out or the network failed: the lists were
					// already cleared, so tell the customer instead of leaving
					// them staring at an empty district with no reason.
					if ( seq === districtSeq && form ) {
						showError( form, i18n.addressRefreshFailed || '' );
					}
				} )
				.then( function () {
					// pending is always decremented — it gates the submit and
					// must return to zero — but only the latest request owns the
					// busy indicator.
					pending--;

					if ( seq === districtSeq ) {
						setBusy( district, false );
					}
				} );
		}

		function loadNeighborhoods( selected ) {
			fillSelect( neighborhood, [], '', i18n.selectNeighborhood );

			if ( ! city.value || ! district.value ) {
				return;
			}

			var seq = ++neighborhoodSeq;

			pending++;
			setBusy( neighborhood, true );

			fetchOptions( { city_code: city.value, district: district.value } )
				.then( function ( data ) {
					if ( seq !== neighborhoodSeq ) {
						return;
					}

					fillSelect( neighborhood, data.neighborhoods || [], selected || '', i18n.selectNeighborhood );
				} )
				.catch( function () {
					if ( seq === neighborhoodSeq && form ) {
						showError( form, i18n.addressRefreshFailed || '' );
					}
				} )
				.then( function () {
					pending--;

					if ( seq === neighborhoodSeq ) {
						setBusy( neighborhood, false );
					}
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
