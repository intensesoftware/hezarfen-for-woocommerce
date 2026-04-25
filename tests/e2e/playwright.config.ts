import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.HEZARFEN_E2E_BASE_URL || 'http://hezarfen-dev.local';

export default defineConfig({
	testDir: __dirname,
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 90_000,
	expect: { timeout: 15_000 },
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'playwright-report', open: 'never' } ],
	],
	outputDir: 'test-results',
	globalSetup: require.resolve( './global-setup' ),
	use: {
		baseURL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		locale: 'tr-TR',
		ignoreHTTPSErrors: true,
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
