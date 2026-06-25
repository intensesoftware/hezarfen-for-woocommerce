/**
 * Province (il) + district (ilçe) + neighborhood (mahalle) cascading, searchable
 * comboboxes for the block checkout. Mounted once per address type
 * (billing / shipping).
 *
 * The selections are written to the core WooCommerce fields so downstream order
 * meta stays identical to the classic checkout: province → `state`, district →
 * `city`, neighborhood → `address_1`. The redundant core State / City /
 * Address line 1 inputs are hidden via CSS while Turkey is selected.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { CART_STORE_KEY, VALIDATION_STORE_KEY } from '@woocommerce/block-data';
import { settings, getDistrictsForProvince, fetchNeighborhoods } from '../settings';
import Combobox from './combobox';

const AddressFields = ( { addressType } ) => {
	const [ neighborhoods, setNeighborhoods ] = useState( [] );
	const [ loadingNeighborhoods, setLoadingNeighborhoods ] = useState( false );
	const rootRef = useRef( null );

	const address = useSelect(
		( select ) => {
			const customerData = select( CART_STORE_KEY ).getCustomerData();
			return addressType === 'billing'
				? customerData.billingAddress
				: customerData.shippingAddress;
		},
		[ addressType ]
	);

	const { setBillingAddress, setShippingAddress } = useDispatch( CART_STORE_KEY );
	const { setValidationErrors, clearValidationError } = useDispatch(
		VALIDATION_STORE_KEY
	);

	const setAddress = addressType === 'billing' ? setBillingAddress : setShippingAddress;

	const country = address?.country || '';
	const province = address?.state || '';
	const district = address?.city || '';
	const neighborhood = address?.address_1 || '';
	const isTR = country === 'TR';

	const districtOptions = useMemo(
		() => getDistrictsForProvince( province ),
		[ province ]
	);

	// Hide the redundant core State / City / Address line 1 inputs while TR is
	// active; our searchable comboboxes replace them.
	useEffect( () => {
		document.body.classList.toggle( 'hezarfen-tr-checkout', isTR );
	}, [ isTR ] );

	// Relocate our comboboxes so the visual order matches the classic checkout:
	// İl → İlçe → Mahalle → Açık adres → Posta kodu. We anchor right before the
	// "Açık adres" (address_2) field — a stable, always-visible field for TR —
	// which keeps the order correct regardless of where the hidden core fields
	// land in the DOM. A MutationObserver re-applies it if WooCommerce rebuilds
	// the form (e.g. on a country change).
	useEffect( () => {
		const root = rootRef.current;

		if ( ! isTR || ! root ) {
			return;
		}

		const form = root
			.closest( 'fieldset' )
			?.querySelector( '.wc-block-components-address-form' );

		if ( ! form ) {
			return;
		}

		const reposition = () => {
			const anchor =
				form.querySelector(
					'.wc-block-components-address-form__address_2'
				) ||
				form.querySelector(
					'.wc-block-components-address-form__postcode'
				);

			if ( anchor && anchor.previousElementSibling !== root ) {
				anchor.insertAdjacentElement( 'beforebegin', root );
			}
		};

		reposition();

		const observer = new window.MutationObserver( reposition );
		observer.observe( form, { childList: true } );

		return () => observer.disconnect();
	}, [ isTR, addressType ] );

	// Load neighborhoods whenever the province or district changes.
	useEffect( () => {
		if ( ! isTR || ! province || ! district ) {
			setNeighborhoods( [] );
			return;
		}

		let cancelled = false;
		setLoadingNeighborhoods( true );

		fetchNeighborhoods( province, district ).then( ( options ) => {
			if ( ! cancelled ) {
				setNeighborhoods( options );
				setLoadingNeighborhoods( false );
			}
		} );

		return () => {
			cancelled = true;
		};
	}, [ isTR, province, district ] );

	// Surface validation errors for the required comboboxes.
	useEffect( () => {
		const ids = {
			province: `hezarfen-${ addressType }-province`,
			district: `hezarfen-${ addressType }-district`,
			neighborhood: `hezarfen-${ addressType }-neighborhood`,
		};

		if ( ! isTR ) {
			Object.values( ids ).forEach( clearValidationError );
			return;
		}

		const apply = ( id, isEmpty, message ) => {
			if ( isEmpty ) {
				setValidationErrors( { [ id ]: { message, hidden: true } } );
			} else {
				clearValidationError( id );
			}
		};

		apply( ids.province, ! province, settings.labels.province );
		apply( ids.district, ! district, settings.labels.district );
		apply( ids.neighborhood, ! neighborhood, settings.labels.neighborhood );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ isTR, province, district, neighborhood, addressType ] );

	if ( ! settings.neighborhoodEnabled || ! isTR ) {
		return null;
	}

	// Changing a level resets the dependent levels below it.
	const onProvinceChange = ( value ) =>
		setAddress( { state: value, city: '', address_1: '' } );

	const onDistrictChange = ( value ) =>
		setAddress( { city: value, address_1: '' } );

	const onNeighborhoodChange = ( value ) =>
		setAddress( { address_1: value } );

	return (
		<div
			ref={ rootRef }
			className="hezarfen-checkout-fields hezarfen-checkout-fields--address"
		>
			<Combobox
				id={ `hezarfen-${ addressType }-province` }
				className="wc-block-components-address-form__hez-province"
				label={ settings.labels.province }
				value={ province }
				onChange={ onProvinceChange }
				options={ settings.provinces }
				placeholder={ settings.labels.selectOption }
				noResultsText={ settings.labels.noResults }
			/>

			<Combobox
				id={ `hezarfen-${ addressType }-district` }
				className="wc-block-components-address-form__hez-district"
				label={ settings.labels.district }
				value={ district }
				onChange={ onDistrictChange }
				options={ districtOptions }
				disabled={ ! province }
				placeholder={ settings.labels.selectOption }
				noResultsText={ settings.labels.noResults }
			/>

			<Combobox
				id={ `hezarfen-${ addressType }-neighborhood` }
				className="wc-block-components-address-form__hez-neighborhood"
				label={ settings.labels.neighborhood }
				value={ neighborhood }
				onChange={ onNeighborhoodChange }
				options={ neighborhoods }
				disabled={ ! district || loadingNeighborhoods }
				placeholder={
					loadingNeighborhoods ? '…' : settings.labels.selectOption
				}
				noResultsText={ settings.labels.noResults }
			/>
		</div>
	);
};

export default AddressFields;
