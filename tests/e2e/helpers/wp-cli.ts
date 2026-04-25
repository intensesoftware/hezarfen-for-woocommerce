import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import path from 'node:path';

/**
 * Locate the LocalWP ssh-entry script that matches the WordPress public root,
 * so we can run wp-cli with the same PATH/env that LocalWP would use in its
 * shell. Mirrors the matching done by ~/.localwp-shell.zsh.
 */
function findSshEntry( wpPublicRoot: string ): string | null {
	const sshEntryDir = path.join(
		homedir(),
		'Library',
		'Application Support',
		'Local',
		'ssh-entry'
	);
	if ( ! existsSync( sshEntryDir ) ) return null;

	const siteRoot = path.resolve( wpPublicRoot, '..', '..' );
	const candidates = readdirSync( sshEntryDir ).filter( ( f ) =>
		f.endsWith( '.sh' )
	);
	for ( const file of candidates ) {
		const full = path.join( sshEntryDir, file );
		const content = readFileSync( full, 'utf8' );
		const match = content.match( /^cd\s+["']?(.+?)["']?\s*$/m );
		if ( ! match ) continue;
		const cdPath = match[ 1 ].replace( /\/+$/, '' );
		if (
			cdPath === wpPublicRoot ||
			cdPath.startsWith( siteRoot + '/' ) ||
			cdPath === siteRoot
		) {
			return full;
		}
	}
	return null;
}

/**
 * Pull the `export FOO=...` lines out of an ssh-entry script and turn them
 * into an env dictionary we can inject when we spawn wp-cli.
 */
function envFromSshEntry( sshEntryPath: string ): Record< string, string > {
	const content = readFileSync( sshEntryPath, 'utf8' );
	const env: Record< string, string > = {
		PATH: process.env.PATH || '',
		HOME: process.env.HOME || homedir(),
	};
	const lines = content.split( '\n' );
	for ( const raw of lines ) {
		const line = raw.trim();
		if ( ! line.startsWith( 'export ' ) ) continue;
		const stripped = line.slice( 'export '.length );
		const eq = stripped.indexOf( '=' );
		if ( eq < 0 ) continue;
		const key = stripped.slice( 0, eq ).trim();
		let value = stripped.slice( eq + 1 ).trim();
		if (
			( value.startsWith( '"' ) && value.endsWith( '"' ) ) ||
			( value.startsWith( "'" ) && value.endsWith( "'" ) )
		) {
			value = value.slice( 1, -1 );
		}
		// Expand variables against the env we're building up — each
		// `export PATH=".../bin:$PATH"` line cumulatively prepends.
		value = value.replace( /\$\{?([A-Z_][A-Z0-9_]*)\}?/gi, ( _, name ) =>
			env[ name ] !== undefined ? env[ name ] : process.env[ name ] || ''
		);
		env[ key ] = value;
	}
	return env;
}

const WP_PUBLIC_ROOT =
	process.env.HEZARFEN_E2E_WP_ROOT ||
	path.resolve( __dirname, '..', '..', '..', '..', '..', '..' );

let cachedEnv: Record< string, string > | null = null;
function wpEnv(): Record< string, string > {
	if ( cachedEnv ) return cachedEnv;
	const sshEntry = findSshEntry( WP_PUBLIC_ROOT );
	if ( ! sshEntry ) {
		throw new Error(
			`Could not find LocalWP ssh-entry script for ${ WP_PUBLIC_ROOT }. ` +
				'Set HEZARFEN_E2E_WP_ROOT to the WordPress public root, ' +
				'or run the tests inside a LocalWP site.'
		);
	}
	cachedEnv = envFromSshEntry( sshEntry );
	return cachedEnv;
}

export interface WpCliOptions {
	allowFailure?: boolean;
	stdin?: string;
}

/**
 * Run a wp-cli command for the LocalWP site. Returns trimmed stdout.
 */
export function wp( args: string[], opts: WpCliOptions = {} ): string {
	const env = {
		...process.env,
		...wpEnv(),
	};
	try {
		const out = execFileSync( 'wp', args, {
			cwd: WP_PUBLIC_ROOT,
			env,
			encoding: 'utf8',
			input: opts.stdin,
			stdio: opts.stdin
				? [ 'pipe', 'pipe', 'pipe' ]
				: [ 'ignore', 'pipe', 'pipe' ],
		} );
		return out.trim();
	} catch ( e: any ) {
		if ( opts.allowFailure ) {
			return ( e.stdout?.toString?.() || '' ).trim();
		}
		const stderr = e.stderr?.toString?.() || '';
		throw new Error(
			`wp ${ args.join( ' ' ) } failed:\n${ stderr || e.message }`
		);
	}
}

export function wpJson< T = unknown >(
	args: string[],
	opts: WpCliOptions = {}
): T {
	const out = wp( [ ...args, '--format=json' ], opts );
	if ( ! out ) return null as unknown as T;
	return JSON.parse( out ) as T;
}
