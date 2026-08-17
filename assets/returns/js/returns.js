/**
 * Hezarfen returns — front-end enhancements.
 *
 * Progressive enhancement only: without this file the form still submits
 * and the server performs the exact same validation. The script collapses
 * the per-item detail fields until a line is picked, reveals the note
 * field for reasons that need one, keeps a live summary, and stops an
 * obviously incomplete form before it costs the customer a round trip.
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

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-return-form]' ),
			initForm
		);

		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-hez-confirm-cancel]' ),
			initCancelConfirm
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
