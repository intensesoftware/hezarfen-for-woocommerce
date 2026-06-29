/**
 * Invoice / tax (vergi) fields for the block checkout.
 *
 * Renders the invoice-type selector and, depending on the selection, either the
 * T.C. identity number field (personal) or the company title + tax number + tax
 * office fields (company). The company title is written to the core billing
 * `company` field; the remaining values travel to the server as `hezarfen`
 * Store API extension data and are persisted by Hezarfen_Store_API.
 */
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { CART_STORE_KEY, CHECKOUT_STORE_KEY } from '@woocommerce/block-data';
import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { settings } from '../settings';
import SelectField from './select-field';

const onlyDigits = ( value ) => ( value || '' ).replace( /[^0-9]/g, '' );

const InvoiceFields = () => {
	const initialInvoiceType = useSelect( ( select ) => {
		const data = select( CHECKOUT_STORE_KEY ).getExtensionData();
		return data?.hezarfen?.invoice_type || '';
	}, [] );

	const companyTitle = useSelect(
		( select ) =>
			select( CART_STORE_KEY ).getCustomerData()?.billingAddress
				?.company || '',
		[]
	);

	const [ invoiceType, setInvoiceType ] = useState( initialInvoiceType );
	const [ tcNumber, setTcNumber ] = useState( '' );
	const [ taxNumber, setTaxNumber ] = useState( '' );
	const [ taxOffice, setTaxOffice ] = useState( '' );

	const { setExtensionData } = useDispatch( CHECKOUT_STORE_KEY );
	const { setBillingAddress } = useDispatch( CART_STORE_KEY );

	// Keep the server-bound extension data in sync with the local state.
	useEffect( () => {
		setExtensionData( 'hezarfen', {
			invoice_type: invoiceType,
			tc_number: invoiceType === 'person' ? tcNumber : '',
			tax_number: invoiceType === 'company' ? taxNumber : '',
			tax_office: invoiceType === 'company' ? taxOffice : '',
		} );
	}, [ invoiceType, tcNumber, taxNumber, taxOffice, setExtensionData ] );

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
						id="hezarfen-company-title"
						className="hezarfen-field hezarfen-field--company-title"
						label={ labels.companyTitle }
						value={ companyTitle }
						required
						onChange={ ( value ) =>
							setBillingAddress( { company: value } )
						}
					/>
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
