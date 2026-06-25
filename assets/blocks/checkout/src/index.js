/**
 * Entry point for the Hezarfen block-checkout integration.
 *
 * Registers three checkout inner blocks (frontend components only — they are
 * force-injected server-side, so no editor UI is needed):
 *   - billing district / neighborhood cascade
 *   - shipping district / neighborhood cascade
 *   - invoice / tax fields (billing)
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';

import billingMetadata from './block-billing.json';
import shippingMetadata from './block-shipping.json';
import invoiceMetadata from './block-invoice.json';

import AddressFields from './components/address-fields';
import InvoiceFields from './components/invoice-fields';

import './style.scss';

registerCheckoutBlock( {
	metadata: billingMetadata,
	component: () => <AddressFields addressType="billing" />,
} );

registerCheckoutBlock( {
	metadata: shippingMetadata,
	component: () => <AddressFields addressType="shipping" />,
} );

registerCheckoutBlock( {
	metadata: invoiceMetadata,
	component: InvoiceFields,
} );
