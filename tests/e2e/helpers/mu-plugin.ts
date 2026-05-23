import { wp } from './wp-cli';

/**
 * Drop a must-use plugin file into `wp-content/mu-plugins/` at runtime.
 * Resolved through `WPMU_PLUGIN_DIR` inside `wp eval`, so the same code
 * path lands the file in the right place whether the spec runs against
 * a LocalWP install or a wp-env container.
 *
 * Each fixture mu-plugin should gate its behavior on an option (or
 * constant) so the file can be left in place between tests without
 * leaking side effects; flip the option to activate it.
 */
export function writeMuPlugin( slug: string, php: string ): void {
	if ( ! /^[a-z0-9-]+$/.test( slug ) ) {
		throw new Error(
			`writeMuPlugin: invalid slug "${ slug }" — use kebab-case ASCII.`
		);
	}
	// PHP heredoc dodges every layer of TS → argv → wp-cli string
	// escaping. We base64 the payload, decode it server-side, then
	// `file_put_contents` to the resolved mu-plugins directory.
	const encoded = Buffer.from( php, 'utf8' ).toString( 'base64' );
	wp( [
		'eval',
		`
			if ( ! file_exists( WPMU_PLUGIN_DIR ) ) {
				mkdir( WPMU_PLUGIN_DIR, 0755, true );
			}
			$path = WPMU_PLUGIN_DIR . '/${ slug }.php';
			file_put_contents( $path, base64_decode( '${ encoded }' ) );
			echo $path;
		`,
	] );
}

export function deleteMuPlugin( slug: string ): void {
	wp(
		[
			'eval',
			`
				$path = WPMU_PLUGIN_DIR . '/${ slug }.php';
				if ( file_exists( $path ) ) {
					unlink( $path );
				}
			`,
		],
		{ allowFailure: true }
	);
}
