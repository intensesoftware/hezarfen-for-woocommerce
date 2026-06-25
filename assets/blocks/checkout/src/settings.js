/**
 * Server-provided settings for the Hezarfen checkout block.
 *
 * Exposed by Hezarfen_Blocks_Integration::get_script_data() and read here via
 * the WooCommerce settings global.
 */
import { getSetting } from '@woocommerce/settings';

const defaults = {
	restUrl: '',
	nonce: '',
	districts: {},
	neighborhoodEnabled: true,
	taxFieldsEnabled: false,
	showIdentityField: false,
	identityRequired: false,
	labels: {},
};

export const settings = {
	...defaults,
	...getSetting( 'hezarfen-checkout_data', {} ),
};

/**
 * Returns the district options for a given TR plate number (e.g. "TR34").
 *
 * @param {string} plate Province plate code.
 * @return {Array<{value: string, label: string}>} District options.
 */
export const getDistrictsForProvince = ( plate ) => {
	if ( ! plate || ! settings.districts[ plate ] ) {
		return [];
	}
	return settings.districts[ plate ];
};

/**
 * Fetches neighborhoods for a province + district from the REST endpoint.
 *
 * @param {string} plate    Province plate code (e.g. "TR34").
 * @param {string} district District name.
 * @return {Promise<Array<{value: string, label: string}>>} Neighborhood options.
 */
export const fetchNeighborhoods = ( plate, district ) => {
	if ( ! plate || ! district || ! settings.restUrl ) {
		return Promise.resolve( [] );
	}

	const url = `${ settings.restUrl }/neighborhoods?city=${ encodeURIComponent(
		plate
	) }&district=${ encodeURIComponent( district ) }`;

	return fetch( url, {
		headers: { 'X-WP-Nonce': settings.nonce },
	} )
		.then( ( response ) => ( response.ok ? response.json() : [] ) )
		.catch( () => [] );
};
