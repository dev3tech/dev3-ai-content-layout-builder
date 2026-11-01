import { restUrl } from '../../src/shared/config';
import { adaptToField, htmlToText, textToHtml } from '../../src/shared/text';

// WordPress packages are webpack externals (window.wp.*), so mock them virtually.
jest.mock( '@wordpress/api-fetch', () => jest.fn( () => new Promise( () => {} ) ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( { __: ( s ) => s, sprintf: ( s ) => s } ), { virtual: true } );

describe( 'restUrl', () => {
	it( 'joins routes for pretty permalinks', () => {
		expect( restUrl( 'usage?days=30', '/wp-json/dev3cl/v1/' ) ).toBe( '/wp-json/dev3cl/v1/usage?days=30' );
		expect( restUrl( '/templates', '/wp-json/dev3cl/v1/' ) ).toBe( '/wp-json/dev3cl/v1/templates' );
	} );
	it( 'uses & for plain permalinks', () => {
		expect( restUrl( 'usage?days=30', '/index.php?rest_route=/dev3cl/v1/' ) ).toBe( '/index.php?rest_route=/dev3cl/v1/usage&days=30' );
		expect( restUrl( 'history?per_page=50&search=a?b', '/index.php?rest_route=/dev3cl/v1/' ) ).toBe( '/index.php?rest_route=/dev3cl/v1/history&per_page=50&search=a?b' );
	} );
} );

describe( 'text helpers', () => {
	it( 'converts html to text and back', () => {
		expect( htmlToText( '<h2>Title</h2><p>One<br>two</p><ul><li>a</li></ul>' ) ).toBe( 'Title\nOne\ntwo\n• a' );
		expect( textToHtml( 'a <b>\n\nsecond\nline' ) ).toBe( '<p>a &lt;b&gt;</p>\n<p>second<br>line</p>' );
	} );
	it( 'adapts output to the target field', () => {
		expect( adaptToField( '<p>Hi</p>', 'html', 'heading' ) ).toBe( 'Hi' );
		expect( adaptToField( 'Hi', 'plain', 'wysiwyg' ) ).toBe( '<p>Hi</p>' );
		expect( adaptToField( '<p>Hi</p>', 'html', 'wysiwyg' ) ).toBe( '<p>Hi</p>' );
	} );
} );

describe( 'generate throttling', () => {
	it( 'refuses a second request while one is in flight', async () => {
		const api = require( '../../src/shared/api' );
		api.__resetThrottle();
		api.generate( { task: 'generate' } );
		expect( api.isBusy() ).toBe( true );
		await expect( api.generate( { task: 'generate' } ) ).rejects.toMatchObject( { code: 'dev3cl_busy', silent: true } );
		api.cancel();
		expect( api.isBusy() ).toBe( false );
		await expect( api.generate( { task: 'generate' } ) ).rejects.toMatchObject( { code: 'dev3cl_throttled' } );
	} );
} );
