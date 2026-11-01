import { Button, SearchControl, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../shared/api';
import { clearLocal, readLocal } from '../shared/history-local';

const TASKS = [
	{ value: '', label: __( 'All', 'dev3-ai-content-layout-builder' ) },
	{ value: 'generate', label: __( 'Generate', 'dev3-ai-content-layout-builder' ) },
	{ value: 'rewrite', label: __( 'Rewrite', 'dev3-ai-content-layout-builder' ) },
	{ value: 'tone', label: __( 'Tone', 'dev3-ai-content-layout-builder' ) },
	{ value: 'translate', label: __( 'Translate', 'dev3-ai-content-layout-builder' ) },
	{ value: 'seo_meta', label: __( 'SEO', 'dev3-ai-content-layout-builder' ) },
	{ value: 'layout', label: __( 'Layout', 'dev3-ai-content-layout-builder' ) },
	{ value: 'vision', label: __( 'Image', 'dev3-ai-content-layout-builder' ) },
];

function preview( entry ) {
	const out = String( entry.output || '' );
	if ( entry.task === 'layout' ) {
		try {
			const tree = JSON.parse( out );
			return `${ tree.title || __( 'Layout', 'dev3-ai-content-layout-builder' ) } · ${ tree.sections.length } ${ __( 'section(s)', 'dev3-ai-content-layout-builder' ) }`;
		} catch ( e ) {}
	}
	return out.replace( /<[^>]+>/g, ' ' ).replace( /[{}"[\]]/g, ' ' ).replace( /\s+/g, ' ' ).trim().slice( 0, 160 );
}

export default function HistoryTab( { onRestore } ) {
	// Local mirror renders instantly; the server list replaces it when it arrives.
	const [ items, setItems ] = useState( readLocal() );
	const [ loading, setLoading ] = useState( true );
	const [ search, setSearch ] = useState( '' );
	const [ task, setTask ] = useState( '' );
	const [ offline, setOffline ] = useState( false );

	useEffect( () => {
		setLoading( true );
		const t = setTimeout( () => {
			const query = new URLSearchParams( { per_page: '50', search, task } ).toString();
			request( `history?${ query }` )
				.then( ( res ) => {
					setItems( res.items );
					setOffline( false );
				} )
				.catch( () => setOffline( true ) )
				.finally( () => setLoading( false ) );
		}, 250 );
		return () => clearTimeout( t );
	}, [ search, task ] );

	const remove = ( id ) => {
		setItems( items.filter( ( i ) => i.id !== id ) );
		request( `history/${ id }`, { method: 'DELETE' } ).catch( () => {} );
	};

	const clearAll = () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete your whole generation history?', 'dev3-ai-content-layout-builder' ) ) ) {
			return;
		}
		clearLocal();
		setItems( [] );
		request( 'history', { method: 'DELETE' } ).catch( () => {} );
	};

	return (
		<div>
			<div className="dev3cl-grid dev3cl-gap-3 dev3cl-items-end" style={ { gridTemplateColumns: '1fr 160px auto' } }>
				<SearchControl value={ search } onChange={ setSearch } label={ __( 'Search history', 'dev3-ai-content-layout-builder' ) } />
				<SelectControl value={ task } options={ TASKS } onChange={ setTask } label={ __( 'Type', 'dev3-ai-content-layout-builder' ) } hideLabelFromVision />
				<Button variant="tertiary" isDestructive onClick={ clearAll } disabled={ ! items.length }>
					{ __( 'Clear all', 'dev3-ai-content-layout-builder' ) }
				</Button>
			</div>
			{ offline && <p className="dev3cl-text-xs dev3cl-text-gray-500">{ __( 'Showing history saved in this browser.', 'dev3-ai-content-layout-builder' ) }</p> }
			{ loading && ! items.length && <Spinner /> }
			{ ! loading && ! items.length && <p className="dev3cl-text-gray-500">{ __( 'Nothing here yet — your generations will appear here.', 'dev3-ai-content-layout-builder' ) }</p> }
			<ul className="dev3cl-m-0 dev3cl-p-0" style={ { listStyle: 'none', maxHeight: 420, overflow: 'auto' } }>
				{ items.map( ( entry ) => (
					<li key={ entry.id } className="dev3cl-flex dev3cl-gap-3 dev3cl-items-start dev3cl-py-2" style={ { borderBottom: '1px solid #f3f4f6' } }>
						<div className="dev3cl-flex-1 dev3cl-min-w-0">
							<div className="dev3cl-text-xs dev3cl-text-gray-500">
								{ entry.task } · { entry.created_at ? new Date( entry.created_at ).toLocaleString() : '' }
							</div>
							{ entry.prompt && <div className="dev3cl-text-sm dev3cl-font-medium dev3cl-truncate">{ entry.prompt }</div> }
							<div className="dev3cl-text-sm dev3cl-text-gray-700">{ preview( entry ) }</div>
						</div>
						<Button size="small" variant="secondary" onClick={ () => onRestore( entry ) }>
							{ __( 'Restore', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button size="small" variant="tertiary" onClick={ () => window.navigator.clipboard && window.navigator.clipboard.writeText( String( entry.output ).replace( /<[^>]+>/g, '' ) ) }>
							{ __( 'Copy', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button size="small" variant="tertiary" isDestructive onClick={ () => remove( entry.id ) } label={ __( 'Delete', 'dev3-ai-content-layout-builder' ) }>
							×
						</Button>
					</li>
				) ) }
			</ul>
		</div>
	);
}
