import { wp } from './wp-cli';

/**
 * Snapshot a set of wp_options, run a callback, then restore them.
 * Useful at describe-scope to flip Hezarfen feature flags (TC field,
 * contracts, etc.) for one test group without leaking state across
 * the rest of the suite. wp-cli is synchronous so we don't need to
 * await anything here.
 */
export function snapshotOptions( keys: string[] ): Record< string, string > {
	const snapshot: Record< string, string > = {};
	for ( const key of keys ) {
		snapshot[ key ] = wp( [ 'option', 'get', key ], {
			allowFailure: true,
		} );
	}
	return snapshot;
}

export function applyOptions( options: Record< string, string > ): void {
	for ( const [ key, value ] of Object.entries( options ) ) {
		wp( [ 'option', 'update', key, value ] );
	}
}

export function restoreOptions(
	snapshot: Record< string, string >
): void {
	for ( const [ key, value ] of Object.entries( snapshot ) ) {
		if ( value === '' ) {
			wp( [ 'option', 'delete', key ], { allowFailure: true } );
		} else {
			wp( [ 'option', 'update', key, value ] );
		}
	}
}
