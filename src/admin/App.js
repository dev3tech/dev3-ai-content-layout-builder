import { useEffect, useState } from '@wordpress/element';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { request } from '../shared/api';
import config from '../shared/config';
import GeneralTab from './GeneralTab';
import ApiTab from './ApiTab';
import TemplatesTab from './TemplatesTab';
import UsageTab from './UsageTab';

const TABS = [
	{ id: 'general', label: __( 'General Settings', 'dev3-ai-content-layout-builder' ) },
	{ id: 'api', label: __( 'API Config', 'dev3-ai-content-layout-builder' ) },
	{ id: 'templates', label: __( 'Prompt Templates', 'dev3-ai-content-layout-builder' ) },
	{ id: 'usage', label: __( 'Usage Logs', 'dev3-ai-content-layout-builder' ) },
];

const fromHash = () => {
	const id = window.location.hash.replace( /^#\/?/, '' );
	return TABS.some( ( t ) => t.id === id ) ? id : config.hasKey ? 'general' : 'api';
};

export default function App() {
	const [ tab, setTab ] = useState( fromHash() );
	const [ settings, setSettings ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ saved, setSaved ] = useState( false );

	useEffect( () => {
		const onHash = () => setTab( fromHash() );
		window.addEventListener( 'hashchange', onHash );
		request( 'settings' ).then( setSettings ).catch( setError );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	const save = ( patch ) =>
		request( 'settings', { method: 'POST', data: patch } )
			.then( ( next ) => {
				setSettings( next );
				setSaved( true );
				setError( null );
				setTimeout( () => setSaved( false ), 2500 );
				return next;
			} )
			.catch( ( err ) => {
				setError( err );
				throw err;
			} );

	return (
		<div className="dev3cl-root">
			<h1 className="wp-heading-inline" style={ { display: 'flex', alignItems: 'center', gap: 10 } }>
				<img src={ config.assetsUrl + 'img/icon.svg' } width="32" height="32" alt="" />
				{ __( 'DEV3 AI Content & Layout Builder', 'dev3-ai-content-layout-builder' ) }
				<span className="dev3cl-text-xs dev3cl-text-gray-500" style={ { fontWeight: 400 } }>v{ config.version }</span>
			</h1>
			<nav className="dev3cl-admin-nav" aria-label={ __( 'Settings sections', 'dev3-ai-content-layout-builder' ) }>
				{ TABS.map( ( t ) => (
					<a key={ t.id } href={ `#/${ t.id }` } className={ tab === t.id ? 'is-active' : '' } aria-current={ tab === t.id ? 'page' : undefined }>
						{ t.label }
					</a>
				) ) }
			</nav>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error.message } { error.hint }
				</Notice>
			) }
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ __( 'Settings saved.', 'dev3-ai-content-layout-builder' ) }
				</Notice>
			) }
			{ ! settings && ! error && <Spinner /> }
			{ settings && tab === 'general' && <GeneralTab settings={ settings } save={ save } /> }
			{ settings && tab === 'api' && <ApiTab settings={ settings } save={ save } /> }
			{ tab === 'templates' && <TemplatesTab /> }
			{ tab === 'usage' && <UsageTab /> }
		</div>
	);
}
