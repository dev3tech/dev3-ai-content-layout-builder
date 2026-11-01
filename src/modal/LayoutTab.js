import { Button, SelectControl, TextareaControl, TextControl } from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import config from '../shared/config';
import { generate, request } from '../shared/api';
import { addLocal } from '../shared/history-local';
import ErrorNotice from './ErrorNotice';
import Busy from './Busy';
import LayoutPreview from './LayoutPreview';
import useTemplates from './useTemplates';

const EXAMPLES = [
	__( '3-column pricing table with monthly/yearly toggle for a project-management SaaS', 'dev3-ai-content-layout-builder' ),
	__( 'Hero section for a boutique yoga studio with headline, intro, two buttons and an image', 'dev3-ai-content-layout-builder' ),
	__( 'Feature grid of 6 benefits for a home-cleaning service, then a call to action', 'dev3-ai-content-layout-builder' ),
	__( 'FAQ accordion with 6 questions for an online course platform', 'dev3-ai-content-layout-builder' ),
	__( 'Stats band with 4 counters and three testimonials for a digital agency', 'dev3-ai-content-layout-builder' ),
];

function download( filename, text ) {
	const blob = new window.Blob( [ text ], { type: 'application/json' } );
	const url = window.URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.appendChild( a );
	a.click();
	a.remove();
	setTimeout( () => window.URL.revokeObjectURL( url ), 1000 );
}

export default function LayoutTab( props ) {
	const { builder = 'elementor', onInsertLayout, context = 'admin', elementorMode = '', restored } = props;
	const [ prompt, setPrompt ] = useState( '' );
	const [ templateId, setTemplateId ] = useState( '' );
	const [ values, setValues ] = useState( {} );
	const [ tone, setTone ] = useState( config.defaultTone || 'professional' );
	const [ language, setLanguage ] = useState( config.defaultLanguage || 'English' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ copied, setCopied ] = useState( '' );
	const lastRequest = useRef( null );
	const templates = useTemplates();
	const layoutTemplates = useMemo( () => templates.filter( ( t ) => t.task === 'layout' ), [ templates ] );
	const template = layoutTemplates.find( ( t ) => t.id === templateId );

	useEffect( () => {
		if ( ! restored ) {
			return;
		}
		let tree = null;
		try {
			tree = JSON.parse( restored.output );
		} catch ( e ) {
			return;
		}
		setBusy( true );
		request( 'layout/convert', { method: 'POST', data: { tree, elementor_mode: elementorMode } } )
			.then( setResult )
			.catch( setError )
			.finally( () => setBusy( false ) );
	}, [ restored && restored.nonce ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const run = ( body ) => {
		const data = body || {
			task: 'layout',
			prompt,
			tone,
			language,
			builder,
			context,
			elementor_mode: elementorMode,
			...( templateId ? { template_id: templateId, values } : {} ),
		};
		lastRequest.current = data;
		setBusy( true );
		setError( null );
		generate( data )
			.then( ( res ) => {
				setResult( res );
				if ( res.history_id ) {
					addLocal( { id: res.history_id, task: 'layout', prompt: data.prompt, output: JSON.stringify( res.tree ), model: res.model, meta: { format: 'layout' } } );
				}
			} )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};

	const copy = ( label, text ) => {
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard.writeText( text ).then( () => {
				setCopied( label );
				setTimeout( () => setCopied( '' ), 1500 );
			} );
		}
	};

	return (
		<div>
			<ErrorNotice error={ error } onRetry={ () => lastRequest.current && run( lastRequest.current ) } onDismiss={ () => setError( null ) } />
			<div className="dev3cl-grid dev3cl-gap-3" style={ { gridTemplateColumns: layoutTemplates.length ? '1fr 220px' : '1fr' } }>
				<TextareaControl
					label={ __( 'Describe the section', 'dev3-ai-content-layout-builder' ) }
					value={ prompt }
					onChange={ setPrompt }
					rows={ 3 }
					placeholder={ EXAMPLES[ 0 ] }
					onKeyDown={ ( e ) => {
						if ( ( e.metaKey || e.ctrlKey ) && e.key === 'Enter' && prompt.trim() ) {
							run();
						}
					} }
				/>
				{ layoutTemplates.length > 0 && (
					<SelectControl
						label={ __( 'Template', 'dev3-ai-content-layout-builder' ) }
						value={ templateId }
						options={ [ { value: '', label: __( '— None —', 'dev3-ai-content-layout-builder' ) }, ...layoutTemplates.map( ( t ) => ( { value: t.id, label: t.name } ) ) ] }
						onChange={ ( id ) => {
							setTemplateId( id );
							setValues( {} );
						} }
					/>
				) }
			</div>
			{ template && (
				<div className="dev3cl-grid dev3cl-gap-3" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' } }>
					{ template.fields.map( ( f ) => (
						<TextControl key={ f.key } label={ f.label } value={ values[ f.key ] ?? '' } placeholder={ f.default || '' } onChange={ ( v ) => setValues( { ...values, [ f.key ]: v } ) } />
					) ) }
				</div>
			) }
			{ ! prompt && ! templateId && (
				<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-gap-2 dev3cl-mb-3">
					{ EXAMPLES.map( ( ex ) => (
						<button key={ ex } type="button" className="dev3cl-chip" onClick={ () => setPrompt( ex ) }>
							{ ex.length > 48 ? ex.slice( 0, 46 ) + '…' : ex }
						</button>
					) ) }
				</div>
			) }
			<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-items-end dev3cl-gap-3 dev3cl-mb-3">
				<div style={ { width: 160 } }>
					<SelectControl label={ __( 'Tone', 'dev3-ai-content-layout-builder' ) } value={ tone } options={ config.tones || [] } onChange={ setTone } />
				</div>
				<div style={ { width: 160 } }>
					<TextControl label={ __( 'Language', 'dev3-ai-content-layout-builder' ) } value={ language } onChange={ setLanguage } />
				</div>
				<div className="dev3cl-mb-2">
					<Button variant="primary" onClick={ () => run() } disabled={ busy || ( ! prompt.trim() && ! templateId ) } isBusy={ busy }>
						{ __( 'Generate layout', 'dev3-ai-content-layout-builder' ) }
					</Button>
				</div>
			</div>

			{ busy && <Busy label={ __( 'AI is designing your section… this can take 20–40 seconds.', 'dev3-ai-content-layout-builder' ) } /> }

			{ ! busy && result && (
				<div>
					<LayoutPreview tree={ result.tree } />
					<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-gap-2 dev3cl-mt-3">
						{ onInsertLayout && (
							<Button variant="primary" onClick={ () => onInsertLayout( result ) }>
								{ __( 'Insert into page', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						<Button variant="secondary" onClick={ () => lastRequest.current && run( lastRequest.current ) } disabled={ ! lastRequest.current }>
							{ __( 'Regenerate', 'dev3-ai-content-layout-builder' ) }
						</Button>
						{ ( builder === 'wpbakery' || context === 'admin' ) && (
							<Button variant="secondary" onClick={ () => copy( 'vc', result.wpbakery ) }>
								{ copied === 'vc' ? __( 'Copied!', 'dev3-ai-content-layout-builder' ) : __( 'Copy WPBakery shortcode', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						{ ( builder === 'elementor' || context === 'admin' ) && (
							<Button variant="secondary" onClick={ () => download( 'gemini-layout.json', JSON.stringify( result.elementor_template, null, 2 ) ) }>
								{ __( 'Download Elementor template', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						<Button variant="tertiary" isDestructive onClick={ () => setResult( null ) }>
							{ __( 'Discard', 'dev3-ai-content-layout-builder' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}
