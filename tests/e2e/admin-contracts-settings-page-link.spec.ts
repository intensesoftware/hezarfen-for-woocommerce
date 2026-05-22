import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth';
import { wp } from './helpers/wp-cli';

/**
 * The Hezarfen "Contracts & Agreements" settings screen
 * ([class-contracts-settings.php](../../includes/contracts/admin/class-contracts-settings.php))
 * renders one row per configured contract. Each row has a `<select>` of
 * eligible WordPress pages (`.page-selector`) plus an "Edit Page" link
 * (`.page-link`) that the inline jQuery (`updatePageLink`) wires up by
 * concatenating an `admin.php?action=edit&post=` prefix with the
 * currently selected page id.
 *
 * Two failure modes have hit this in the past:
 *   - The prefix gets HTML-entity-escaped (`&amp;` / `&#038;`), which
 *     turns the click target into `…?action=edit&#038;post=<id>`. The
 *     URL parser then treats `#038;post=<id>` as a fragment, drops the
 *     `post` query param, and WordPress lands the user on the post
 *     listing screen instead of the edit screen for the chosen page.
 *   - The link stays hidden because the JS branch that calls `.show()`
 *     never finds a selected value.
 *
 * Guard both by reading the actual `href` after page load and by
 * following the link to confirm we end up on the post edit screen.
 */
let templatePageId: string;

test.describe( 'Hezarfen sözleşme ayarları — page-selector edit linki', () => {
	test.beforeAll( () => {
		// Reuse whichever published page the contracts seeder picked, or
		// fall back to creating a dedicated one if the option is empty.
		const seededId = wp(
			[
				'eval',
				`
					$opts = get_option( 'hezarfen_mss_settings', array() );
					if ( ! empty( $opts['contracts'][0]['template_id'] ) ) {
						echo (int) $opts['contracts'][0]['template_id'];
					} else {
						echo '';
					}
				`,
			],
			{ allowFailure: true }
		).trim();

		if ( seededId && /^\d+$/.test( seededId ) ) {
			templatePageId = seededId;
			return;
		}

		templatePageId = wp( [
			'post',
			'create',
			'--post_type=page',
			'--post_status=publish',
			'--post_title=Hezarfen E2E Sözleşme Sayfası',
			'--porcelain',
		] ).trim();
	} );

	test( 'kayıtlı sayfa için .page-link doğru ?action=edit&post=<id> href üretiyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=contracts_settings'
		);

		// The first contract row's page-link should be visible (init JS
		// ran on a populated <select>) and carry an href that points at
		// the seeded page's edit screen.
		const link = page.locator( '.page-link' ).first();
		await expect( link ).toBeVisible();

		const href = ( await link.getAttribute( 'href' ) ) || '';

		// Authoritative shape check: the `&` between `action=edit` and
		// `post=…` must remain a literal ampersand. If the upstream PHP
		// helper percent-encodes the URL for display context, the JS
		// sees `&#038;` / `&amp;` inside a <script> raw-text block,
		// concatenation lands an invalid query string in `href`, and
		// the browser parses everything after `#038;` as the fragment.
		expect( href ).toMatch(
			new RegExp(
				`post\\.php\\?action=edit&post=${ templatePageId }$`
			)
		);
		expect( href ).not.toContain( '&amp;' );
		expect( href ).not.toContain( '&#038;' );
	} );

	test( 'edit linkine tıklamak gerçekten o sayfanın düzenleme ekranını açıyor', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=hezarfen&section=contracts_settings',
			{ waitUntil: 'domcontentloaded' }
		);

		const link = page.locator( '.page-link' ).first();
		await expect( link ).toBeVisible();

		// The inline `updatePageLink()` jQuery handler writes the href
		// asynchronously after `.page-selector`'s change event fires on
		// page load. Wait for the href to settle before clicking so we
		// don't race the JS that wires it up.
		await expect
			.poll( () => link.getAttribute( 'href' ), { timeout: 10_000 } )
			.toMatch( /post\.php\?action=edit&post=\d+/ );

		// `target="_blank"` is on the markup; remove it so we can assert
		// the same-tab navigation directly without juggling popup events.
		await link.evaluate( ( el ) => el.removeAttribute( 'target' ) );
		await Promise.all( [
			page.waitForURL( /post\.php\?action=edit&post=\d+/, {
				timeout: 30_000,
				waitUntil: 'domcontentloaded',
			} ),
			link.click(),
		] );

		// The regression we care about is "did the post id reach the
		// server" — i.e. WordPress did NOT bounce us to the post
		// listing or to "Sorry, you are not allowed to edit this
		// item." We assert on the post-edit form's hidden `post_ID`
		// input (present in the initial HTML response of `post.php`)
		// rather than the block editor's `#title` / `.editor-post-title`
		// — the editor bundle can take 20-30s to render on a cold
		// worker and push past the suite-wide 90s timeout. Using
		// `#wpadminbar` here would also be unreliable because the
		// block editor opens in fullscreen mode by default and hides
		// the admin bar via CSS.
		await expect(
			page.locator( `input[name="post_ID"][value="${ templatePageId }"]` )
		).toHaveCount( 1, { timeout: 20_000 } );
		expect( page.url() ).toContain( `post=${ templatePageId }` );
		expect( page.url() ).toContain( 'action=edit' );
	} );
} );
