/**
 * District (ilçe) + neighborhood (mahalle) cascading selects for the block
 * checkout. Mounted once per address type (billing / shipping).
 *
 * The selected district is written to the core `city` field and the selected
 * neighborhood to the core `address_1` field, mirroring the classic checkout
 * so downstream order meta (`_billing_city`, `_billing_address_1`, …) is
 * identical. The redundant core City / Address line 1 inputs are hidden via
 * CSS while Turkey is selected.
 */
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { CART_STORE_KEY, VALIDATION_STORE_KEY } from '@woocommerce/block-data';
import { settings, getDistrictsForProvince, fetchNeighborhoods } from '../settings';
import SelectField from './select-field';

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

	// Hide the redundant core City / Address line 1 inputs while TR is active.
	useEffect( () => {
		document.body.classList.toggle( 'hezarfen-tr-checkout', isTR );
	}, [ isTR ] );

	// Relocate the district/neighborhood selects to sit right after the core
	// State (İl) field, so the visual order matches the classic checkout
	// (İl → İlçe → Mahalle → Açık adres → Posta kodu). Our block is rendered
	// outside the core address form, so we move it in and keep it in place with
	// a MutationObserver in case WooCommerce rebuilds the form (e.g. on country
	// change).
	useEffect( () => {
		const root = rootRef.current;

		if ( ! isTR || ! root ) {
			return;
		}

		const reposition = () => {
			const block = root.closest( 'fieldset' );
			const form = block?.querySelector(
				'.wc-block-components-address-form'
			);
			const stateField = form?.querySelector(
				'.wc-block-components-address-form__state'
			);

			if (
				stateField &&
				stateField.nextElementSibling !== root
			) {
				stateField.insertAdjacentElement( 'afterend', root );
			}
		};

		reposition();

		const form = root
			.closest( 'fieldset' )
			?.querySelector( '.wc-block-components-address-form' );

		if ( ! form ) {
			return;
		}

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

	// Surface validation errors for the required selects.
	useEffect( () => {
		const districtErrorId = `hezarfen-${ addressType }-district`;
		const neighborhoodErrorId = `hezarfen-${ addressType }-neighborhood`;

		if ( ! isTR ) {
			clearValidationError( districtErrorId );
			clearValidationError( neighborhoodErrorId );
			return;
		}

		if ( ! district ) {
			setValidationErrors( {
				[ districtErrorId ]: {
					message: settings.labels.district,
					hidden: true,
				},
			} );
		} else {
			clearValidationError( districtErrorId );
		}

		if ( ! neighborhood ) {
			setValidationErrors( {
				[ neighborhoodErrorId ]: {
					message: settings.labels.neighborhood,
					hidden: true,
				},
			} );
		} else {
			clearValidationError( neighborhoodErrorId );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ isTR, district, neighborhood, addressType ] );

	if ( ! settings.neighborhoodEnabled || ! isTR ) {
		return null;
	}

	const onDistrictChange = ( event ) => {
		// Changing the district resets the dependent neighborhood.
		setAddress( { city: event.target.value, address_1: '' } );
	};

	const onNeighborhoodChange = ( event ) => {
		setAddress( { address_1: event.target.value } );
	};

	return (
		<div
			ref={ rootRef }
			className="hezarfen-checkout-fields hezarfen-checkout-fields--address"
		>
			<SelectField
				id={ `hezarfen-${ addressType }-district` }
				className="wc-block-components-address-form__hez-district"
				label={ settings.labels.district }
				value={ district }
				onChange={ onDistrictChange }
				options={ districtOptions }
				placeholder={ settings.labels.selectOption }
			/>

			<SelectField
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
			/>
		</div>
	);
};

export default AddressFields;
