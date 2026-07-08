/**
 * Invoice / tax (vergi) fields for the block checkout.
 *
 * Renders the invoice-type selector and, depending on the selection, either the
 * T.C. identity number field (personal) or the tax number + tax office fields
 * (company). The company name/title is *not* a Hezarfen field — it uses
 * WooCommerce's own billing company field, exactly like the classic checkout —
 * so it persists natively (including the block checkout's "use same address"
 * shipping→billing sync). The tax values travel to the server as `hezarfen`
 * Store API extension data and are persisted by Hezarfen_Store_API.
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';
import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { settings } from '../settings';
import SelectField from './select-field';

const onlyDigits = ( value ) => ( value || '' ).replace( /[^0-9]/g, '' );

const InvoiceFields = () => {
	const initialInvoiceType = useSelect( ( select ) => {
		const data = select( CHECKOUT_STORE_KEY ).getExtensionData();
		return data?.hezarfen?.invoice_type || '';
	}, [] );

	const [ invoiceType, setInvoiceType ] = useState( initialInvoiceType );
	const [ tcNumber, setTcNumber ] = useState( '' );
	const [ taxNumber, setTaxNumber ] = useState( '' );
	const [ taxOffice, setTaxOffice ] = useState( '' );

	const checkoutDispatch = useDispatch( CHECKOUT_STORE_KEY );
	// `setExtensionData` is the public action on modern WooCommerce, but on
	// WooCommerce 8.3 (our minimum) it is only exposed as the internal
	// `__internalSetExtensionData`. Support both so the block doesn't crash with
	// "setExtensionData is not a function" on 8.3.x.
	const setExtensionData =
		checkoutDispatch.setExtensionData ||
		checkoutDispatch.__internalSetExtensionData;

	// Keep the server-bound extension data in sync with the local state.
	useEffect( () => {
		if ( ! setExtensionData ) {
			return;
		}
		setExtensionData( 'hezarfen', {
			invoice_type: invoiceType,
			tc_number: invoiceType === 'person' ? tcNumber : '',
			tax_number: invoiceType === 'company' ? taxNumber : '',
			tax_office: invoiceType === 'company' ? taxOffice : '',
		} );
	}, [ invoiceType, tcNumber, taxNumber, taxOffice, setExtensionData ] );

	// Mirror the classic checkout, where WooCommerce's own company field is shown
	// only for a company invoice. We surface the invoice type on the body so the
	// stylesheet can reveal/hide the native company field accordingly (scoped to
	// when Hezarfen's tax fields are active, so non-Hezarfen stores are untouched).
	useEffect( () => {
		const active = settings.taxFieldsEnabled;
		document.body.classList.toggle( 'hezarfen-tax-fields', active );
		document.body.classList.toggle(
			'hezarfen-invoice-company',
			active && invoiceType === 'company'
		);
		return () => {
			document.body.classList.remove(
				'hezarfen-tax-fields',
				'hezarfen-invoice-company'
			);
		};
	}, [ invoiceType ] );

	if ( ! settings.taxFieldsEnabled ) {
		return null;
	}

	const labels = settings.labels;

	return (
		<div className="hezarfen-checkout-fields hezarfen-checkout-fields--invoice">
			<SelectField
				id="hezarfen-invoice-type"
				label={ labels.invoiceType }
				value={ invoiceType }
				onChange={ ( event ) => setInvoiceType( event.target.value ) }
				options={ [
					{ value: 'person', label: labels.invoicePerson },
					{ value: 'company', label: labels.invoiceCompany },
				] }
				placeholder={ labels.selectOption }
			/>

			{ invoiceType === 'person' && settings.showIdentityField && (
				<ValidatedTextInput
					id="hezarfen-tc-number"
					className="hezarfen-field hezarfen-field--tc-number"
					label={ labels.tcNumber }
					value={ tcNumber }
					required={ settings.identityRequired }
					onChange={ ( value ) => setTcNumber( onlyDigits( value ) ) }
					customValidation={ ( inputObject ) => {
						const value = inputObject.value;
						if ( ! value && ! settings.identityRequired ) {
							return true;
						}
						if ( value.length !== 11 || ! /^[0-9]+$/.test( value ) ) {
							inputObject.setCustomValidity( labels.tcInvalid );
							return false;
						}
						return true;
					} }
				/>
			) }

			{ invoiceType === 'company' && (
				<>
					<ValidatedTextInput
						id="hezarfen-tax-number"
						className="hezarfen-field hezarfen-field--tax-number"
						label={ labels.taxNumber }
						value={ taxNumber }
						required
						onChange={ ( value ) => setTaxNumber( onlyDigits( value ) ) }
						customValidation={ ( inputObject ) => {
							const value = inputObject.value;
							if (
								! /^[0-9]+$/.test( value ) ||
								( value.length !== 10 && value.length !== 11 )
							) {
								inputObject.setCustomValidity(
									labels.taxNumberInvalid
								);
								return false;
							}
							return true;
						} }
					/>
					<ValidatedTextInput
						id="hezarfen-tax-office"
						className="hezarfen-field hezarfen-field--tax-office"
						label={ labels.taxOffice }
						value={ taxOffice }
						required
						onChange={ ( value ) => setTaxOffice( value ) }
					/>
				</>
			) }
		</div>
	);
};

export default InvoiceFields;
