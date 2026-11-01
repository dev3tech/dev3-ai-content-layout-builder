import { Button, SelectControl, TextareaControl, TextControl, RangeControl, Dropdown, MenuGroup, MenuItem, RadioControl } from '@wordpress/components';
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import config from '../shared/config';
import { generate } from '../shared/api';
import { addLocal } from '../shared/history-local';
import { adaptToField, htmlToText, isRich } from '../shared/text';
import ErrorNotice from './ErrorNotice';
import Busy from './Busy';
import useTemplates from './useTemplates';

const LANGUAGES = [ 'English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Dutch', 'Polish', 'Turkish', 'Russian', 'Arabic', 'Hindi', 'Bengali', 'Urdu', 'Indonesian', 'Japanese', 'Korean', 'Chinese (Simplified)', 'Chinese (Traditional)', 'Vietnamese', 'Thai', 'Swedish', 'Greek', 'Hebrew' ];

const TEXT_ACTIONS = [
	{ task: 'rewrite', label: __( 'Rewrite', 'dev3-ai-content-layout-builder' ) },
	{ task: 'expand', label: __( 'Expand', 'dev3-ai-content-layout-builder' ) },
	{ task: 'shorten', label: __( 'Shorten', 'dev3-ai-content-layout-builder' ) },
	{ task: 'summarize', label: __( 'Summarize', 'dev3-ai-content-layout-builder' ) },
	{ task: 'fix_grammar', label: __( 'Fix Grammar', 'dev3-ai-content-layout-builder' ) },
];

export default function WriteTab( props ) {
	const { field = 'text', onInsert, onInsertItems, onInsertSeo, context = 'admin', restored } = props;
	const isRepeater = field === 'repeater';
	const [ source, setSource ] = useState( props.text ? ( isRich( field ) ? props.text : htmlToText( props.text ) ) : '' );
	const [ prompt, setPrompt ] = useState( props.prompt || '' );
	const [ task, setTask ] = useState( props.task || ( isRepeater ? 'items' : 'generate' ) );
	const [ tone, setTone ] = useState( config.defaultTone || 'professional' );
	const [ language, setLanguage ] = useState( config.defaultLanguage || 'English' );
	const [ templateId, setTemplateId ] = useState( '' );
	const [ values, setValues ] = useState( {} );
	const [ model, setModel ] = useState( 'default' );
	const [ temperature, setTemperature ] = useState( config.temperature ?? 0.7 );
	const [ count, setCount ] = useState( 5 );
	const [ showAdvanced, setShowAdvanced ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ result, setResult ] = useState( null );
	const [ editing, setEditing ] = useState( false );
	const [ draft, setDraft ] = useState( '' );
	const [ picked, setPicked ] = useState( '' );
	const [ copied, setCopied ] = useState( false );
	const lastRequest = useRef( null );
	const templates = useTemplates();

	const format = isRich( field ) ? 'html' : 'plain';
	const writeTemplates = useMemo( () => templates.filter( ( t ) => t.task !== 'layout' ), [ templates ] );
	const template = writeTemplates.find( ( t ) => t.id === templateId );

	useEffect( () => {
		if ( ! restored ) {
			return;
		}
		let output = restored.output;
		const fmt = ( restored.meta && restored.meta.format ) || 'plain';
		if ( [ 'list', 'items', 'seo' ].includes( fmt ) ) {
			try {
				output = JSON.parse( output );
			} catch ( e ) {}
		}
		setResult( { format: fmt, output, task: restored.task, model: restored.model } );
		setPrompt( restored.prompt || '' );
		setEditing( false );
	}, [ restored && restored.nonce ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const run = ( overrides = {} ) => {
		const body = {
			task,
			prompt,
			text: source,
			tone,
			language,
			format,
			field,
			context,
			model,
			temperature,
			...( templateId ? { template_id: templateId, values } : {} ),
			...( [ 'headline_variations', 'items' ].includes( overrides.task || task ) ? { variations: count } : {} ),
			...overrides,
		};
		lastRequest.current = body;
		setBusy( true );
		setError( null );
		setEditing( false );
		setCopied( false );
		generate( body )
			.then( ( res ) => {
				setResult( res );
				setPicked( res.format === 'list' && res.output.length ? res.output[ 0 ] : '' );
				if ( res.history_id ) {
					addLocal( { id: res.history_id, task: res.task, prompt: body.prompt || body.text.slice( 0, 200 ), output: typeof res.output === 'string' ? res.output : JSON.stringify( res.output ), meta: { format: res.format }, model: res.model } );
				}
			} )
			.catch( ( err ) => setError( err ) )
			.finally( () => setBusy( false ) );
	};

	const quick = ( nextTask, extra = {} ) => {
		setTask( nextTask );
		setTemplateId( '' );
		run( { task: nextTask, template_id: undefined, values: undefined, ...extra } );
	};

	const outputString = () => {
		if ( ! result ) {
			return '';
		}
		if ( editing ) {
			return draft;
		}
		if ( result.format === 'list' ) {
			return picked;
		}
		return typeof result.output === 'string' ? result.output : '';
	};

	const outputFormat = () => ( editing || result.format === 'list' ? ( result.format === 'html' ? 'html' : 'plain' ) : result.format );

	const insert = ( mode ) => {
		if ( result.format === 'items' ) {
			onInsertItems( result.output );
			return;
		}
		const value = adaptToField( outputString(), outputFormat(), field );
		onInsert( value, { mode, result } );
	};

	const copy = () => {
		let text = outputString();
		if ( result.format === 'seo' ) {
			text = `${ result.output.title }\n${ result.output.description }\n${ result.output.slug }\n${ result.output.keywords.join( ', ' ) }`;
		} else if ( result.format === 'items' ) {
			text = result.output.map( ( i ) => `${ i.title }\n${ htmlToText( i.text ) }` ).join( '\n\n' );
		} else if ( outputFormat() === 'html' ) {
			text = htmlToText( text );
		}
		const done = () => {
			setCopied( true );
			setTimeout( () => setCopied( false ), 1500 );
		};
		if ( window.navigator.clipboard ) {
			window.navigator.clipboard.writeText( text ).then( done, () => {} );
		}
	};

	const hasText = source.trim().length > 0;
	const canInsert = result && ( result.format === 'items' ? !! onInsertItems : result.format === 'seo' ? false : !! onInsert );

	return (
		<div>
			<ErrorNotice error={ error } onRetry={ () => lastRequest.current && run( lastRequest.current ) } onDismiss={ () => setError( null ) } />

			{ ( hasText || props.text ) && ! isRepeater && (
				<details className="dev3cl-mb-3">
					<summary className="dev3cl-cursor-pointer dev3cl-text-sm dev3cl-text-gray-700">
						<span className="dev3cl-font-medium">{ __( 'Current content', 'dev3-ai-content-layout-builder' ) }</span>{ ' ' }
						<span className="dev3cl-text-gray-500">— { htmlToText( source ).slice( 0, 70 ) }{ htmlToText( source ).length > 70 ? '…' : '' }</span>
					</summary>
					<TextareaControl value={ source } onChange={ setSource } rows={ isRich( field ) ? 5 : 2 } className="dev3cl-mt-2" />
				</details>
			) }

			<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-gap-2 dev3cl-mb-3" role="group" aria-label={ __( 'Quick actions', 'dev3-ai-content-layout-builder' ) }>
				{ ! isRepeater &&
					TEXT_ACTIONS.map( ( a ) => (
						<button key={ a.task } type="button" className={ 'dev3cl-chip' + ( task === a.task ? ' is-active' : '' ) } disabled={ ! hasText || busy } onClick={ () => quick( a.task ) } title={ hasText ? '' : __( 'Needs existing content', 'dev3-ai-content-layout-builder' ) }>
							{ a.label }
						</button>
					) ) }
				{ ! isRepeater && (
					<Dropdown
						renderToggle={ ( { onToggle } ) => (
							<button type="button" className={ 'dev3cl-chip' + ( task === 'tone' ? ' is-active' : '' ) } disabled={ ! hasText || busy } onClick={ onToggle }>
								{ __( 'Change tone ▾', 'dev3-ai-content-layout-builder' ) }
							</button>
						) }
						renderContent={ ( { onClose } ) => (
							<MenuGroup>
								{ ( config.tones || [] ).map( ( t ) => (
									<MenuItem
										key={ t.value }
										onClick={ () => {
											setTone( t.value );
											onClose();
											quick( 'tone', { tone: t.value } );
										} }
									>
										{ t.label }
									</MenuItem>
								) ) }
							</MenuGroup>
						) }
					/>
				) }
				{ ! isRepeater && (
					<Dropdown
						renderToggle={ ( { onToggle } ) => (
							<button type="button" className={ 'dev3cl-chip' + ( task === 'translate' ? ' is-active' : '' ) } disabled={ ! hasText || busy } onClick={ onToggle }>
								{ __( 'Translate ▾', 'dev3-ai-content-layout-builder' ) }
							</button>
						) }
						renderContent={ ( { onClose } ) => (
							<div style={ { maxHeight: 280, overflow: 'auto' } }>
								<MenuGroup>
									{ LANGUAGES.map( ( l ) => (
										<MenuItem
											key={ l }
											onClick={ () => {
												setLanguage( l );
												onClose();
												quick( 'translate', { language: l } );
											} }
										>
											{ l }
										</MenuItem>
									) ) }
								</MenuGroup>
							</div>
						) }
					/>
				) }
				{ ( field === 'heading' || field === 'title' ) && (
					<button type="button" className={ 'dev3cl-chip' + ( task === 'headline_variations' ? ' is-active' : '' ) } disabled={ busy } onClick={ () => setTask( 'headline_variations' ) }>
						{ __( 'Headline ideas', 'dev3-ai-content-layout-builder' ) }
					</button>
				) }
				{ ( context === 'gutenberg' || context === 'admin' ) && (
					<button type="button" className={ 'dev3cl-chip' + ( task === 'seo_meta' ? ' is-active' : '' ) } disabled={ busy } onClick={ () => setTask( 'seo_meta' ) }>
						{ __( 'SEO meta', 'dev3-ai-content-layout-builder' ) }
					</button>
				) }
			</div>

			<div className="dev3cl-grid dev3cl-gap-3" style={ { gridTemplateColumns: writeTemplates.length ? '1fr 220px' : '1fr' } }>
				<TextareaControl
					label={ isRepeater ? __( 'What should the items be about?', 'dev3-ai-content-layout-builder' ) : __( 'What should the AI write?', 'dev3-ai-content-layout-builder' ) }
					value={ prompt }
					onChange={ setPrompt }
					rows={ 3 }
					placeholder={ isRepeater ? __( 'e.g. FAQs for a dental clinic about insurance, emergencies and first visits', 'dev3-ai-content-layout-builder' ) : __( 'e.g. A hero headline for an eco-friendly coffee subscription', 'dev3-ai-content-layout-builder' ) }
					onKeyDown={ ( e ) => {
						if ( ( e.metaKey || e.ctrlKey ) && e.key === 'Enter' ) {
							run();
						}
					} }
				/>
				{ writeTemplates.length > 0 && ! isRepeater && (
					<SelectControl
						label={ __( 'Template', 'dev3-ai-content-layout-builder' ) }
						value={ templateId }
						options={ [ { value: '', label: __( '— None —', 'dev3-ai-content-layout-builder' ) }, ...writeTemplates.map( ( t ) => ( { value: t.id, label: t.name } ) ) ] }
						onChange={ ( id ) => {
							setTemplateId( id );
							setValues( {} );
							const tpl = writeTemplates.find( ( t ) => t.id === id );
							if ( tpl ) {
								setTask( tpl.task );
							}
						} }
					/>
				) }
			</div>

			{ template && template.fields.length > 0 && (
				<div className="dev3cl-grid dev3cl-gap-3 dev3cl-mb-2" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' } }>
					{ template.fields.map( ( f ) => (
						<TextControl key={ f.key } label={ f.label } value={ values[ f.key ] ?? '' } placeholder={ f.default || '' } onChange={ ( v ) => setValues( { ...values, [ f.key ]: v } ) } />
					) ) }
				</div>
			) }

			{ ( task === 'headline_variations' || task === 'items' ) && (
				<RangeControl label={ task === 'items' ? __( 'Number of items', 'dev3-ai-content-layout-builder' ) : __( 'Number of headlines', 'dev3-ai-content-layout-builder' ) } value={ count } onChange={ setCount } min={ 2 } max={ task === 'items' ? 12 : 10 } />
			) }

			<div className="dev3cl-flex dev3cl-items-center dev3cl-gap-2 dev3cl-mb-3">
				<Button variant="primary" onClick={ () => run( { task: [ 'headline_variations', 'items', 'seo_meta' ].includes( task ) || templateId ? task : 'generate' } ) } disabled={ busy || ( ! prompt.trim() && ! templateId && ! ( task === 'seo_meta' && hasText ) ) } isBusy={ busy }>
					{ task === 'headline_variations' ? __( 'Suggest headlines', 'dev3-ai-content-layout-builder' ) : task === 'items' ? __( 'Generate items', 'dev3-ai-content-layout-builder' ) : task === 'seo_meta' ? __( 'Write SEO meta', 'dev3-ai-content-layout-builder' ) : __( 'Generate', 'dev3-ai-content-layout-builder' ) }
				</Button>
				<Button variant="link" onClick={ () => setShowAdvanced( ! showAdvanced ) }>
					{ showAdvanced ? __( 'Hide options', 'dev3-ai-content-layout-builder' ) : __( 'Options', 'dev3-ai-content-layout-builder' ) }
				</Button>
				<span className="dev3cl-text-xs dev3cl-text-gray-500 dev3cl-ml-auto">{ __( '⌘/Ctrl + Enter to generate', 'dev3-ai-content-layout-builder' ) }</span>
			</div>

			{ showAdvanced && (
				<div className="dev3cl-grid dev3cl-gap-3 dev3cl-mb-3 dev3cl-p-3 dev3cl-rounded-md" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', background: '#f9fafb' } }>
					<SelectControl
						label={ __( 'Model', 'dev3-ai-content-layout-builder' ) }
						value={ model }
						options={ [
							{ value: 'default', label: sprintf( /* translators: %s: model id */ __( 'Fast (%s)', 'dev3-ai-content-layout-builder' ), config.defaultModel ) },
							{ value: 'pro', label: sprintf( /* translators: %s: model id */ __( 'Pro (%s)', 'dev3-ai-content-layout-builder' ), config.proModel ) },
						] }
						onChange={ setModel }
					/>
					<SelectControl label={ __( 'Tone', 'dev3-ai-content-layout-builder' ) } value={ tone } options={ config.tones || [] } onChange={ setTone } />
					<SelectControl label={ __( 'Language', 'dev3-ai-content-layout-builder' ) } value={ language } options={ Array.from( new Set( [ language, ...LANGUAGES ] ) ).map( ( l ) => ( { value: l, label: l } ) ) } onChange={ setLanguage } />
					<RangeControl label={ __( 'Creativity (temperature)', 'dev3-ai-content-layout-builder' ) } value={ temperature } onChange={ setTemperature } min={ 0 } max={ 1 } step={ 0.05 } />
				</div>
			) }

			{ busy && <Busy /> }

			{ ! busy && result && (
				<div>
					<div className="dev3cl-flex dev3cl-items-center dev3cl-justify-between dev3cl-mb-1 dev3cl-text-xs dev3cl-text-gray-500">
						<span>{ result.model }</span>
						{ result.truncated && <span style={ { color: '#b45309' } }>{ __( 'Output was cut off — raise Max Output Tokens for longer answers.', 'dev3-ai-content-layout-builder' ) }</span> }
					</div>
					<Output result={ result } editing={ editing } draft={ draft } setDraft={ setDraft } picked={ picked } setPicked={ setPicked } />
					<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-gap-2 dev3cl-mt-3">
						{ canInsert && (
							<Button variant="primary" onClick={ () => insert( 'replace' ) }>
								{ result.format === 'items' ? __( 'Insert items', 'dev3-ai-content-layout-builder' ) : hasText ? __( 'Replace', 'dev3-ai-content-layout-builder' ) : __( 'Insert', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						{ canInsert && hasText && isRich( field ) && result.format !== 'items' && (
							<Button variant="secondary" onClick={ () => insert( 'append' ) }>
								{ __( 'Insert below', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						{ result.format === 'seo' && onInsertSeo && (
							<Button variant="primary" onClick={ () => onInsertSeo( result.output ) }>
								{ __( 'Apply to post', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						{ [ 'plain', 'html' ].includes( result.format ) && (
							<Button
								variant="secondary"
								onClick={ () => {
									setDraft( result.output );
									setEditing( ! editing );
								} }
							>
								{ editing ? __( 'Preview', 'dev3-ai-content-layout-builder' ) : __( 'Edit', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						<Button variant="secondary" onClick={ copy }>
							{ copied ? __( 'Copied!', 'dev3-ai-content-layout-builder' ) : __( 'Copy', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button variant="secondary" onClick={ () => lastRequest.current && run( lastRequest.current ) } disabled={ ! lastRequest.current }>
							{ __( 'Regenerate', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () => {
								setResult( null );
								setEditing( false );
							} }
						>
							{ __( 'Discard', 'dev3-ai-content-layout-builder' ) }
						</Button>
					</div>
				</div>
			) }
		</div>
	);
}

function Output( { result, editing, draft, setDraft, picked, setPicked } ) {
	if ( editing ) {
		return (
			<div className="dev3cl-output">
				<textarea value={ draft } onChange={ ( e ) => setDraft( e.target.value ) } aria-label={ __( 'Edit output', 'dev3-ai-content-layout-builder' ) } />
			</div>
		);
	}
	switch ( result.format ) {
		case 'html':
			// Sanitised server-side with wp_kses_post.
			return <div className="dev3cl-output" dangerouslySetInnerHTML={ { __html: result.output } } />;
		case 'list':
			return (
				<div className="dev3cl-output">
					<RadioControl selected={ picked } options={ result.output.map( ( h ) => ( { value: h, label: h } ) ) } onChange={ setPicked } />
				</div>
			);
		case 'items':
			return (
				<div className="dev3cl-output">
					{ result.output.map( ( item, i ) => (
						<div key={ i } className="dev3cl-mb-3">
							<strong>{ item.title }</strong>
							<div dangerouslySetInnerHTML={ { __html: item.text } } />
						</div>
					) ) }
				</div>
			);
		case 'seo':
			return (
				<div className="dev3cl-output">
					<SeoRow label={ __( 'SEO title', 'dev3-ai-content-layout-builder' ) } value={ result.output.title } max={ 60 } />
					<SeoRow label={ __( 'Meta description', 'dev3-ai-content-layout-builder' ) } value={ result.output.description } max={ 155 } />
					<SeoRow label={ __( 'Slug', 'dev3-ai-content-layout-builder' ) } value={ result.output.slug } />
					<SeoRow label={ __( 'Keywords', 'dev3-ai-content-layout-builder' ) } value={ ( result.output.keywords || [] ).join( ', ' ) } />
					{ result.output.og_title && <SeoRow label={ __( 'Open Graph title', 'dev3-ai-content-layout-builder' ) } value={ result.output.og_title } /> }
				</div>
			);
		default:
			return <div className="dev3cl-output" style={ { whiteSpace: 'pre-wrap' } }>{ result.output }</div>;
	}
}

function SeoRow( { label, value, max } ) {
	return (
		<div className="dev3cl-mb-3">
			<div className="dev3cl-flex dev3cl-justify-between dev3cl-text-xs dev3cl-text-gray-500">
				<span>{ label }</span>
				{ max && <span style={ { color: value.length > max ? '#b91c1c' : undefined } }>{ value.length }/{ max }</span> }
			</div>
			<div className="dev3cl-flex dev3cl-items-start dev3cl-gap-2">
				<div className="dev3cl-flex-1">{ value }</div>
				<Button size="small" variant="tertiary" onClick={ () => window.navigator.clipboard && window.navigator.clipboard.writeText( value ) }>
					{ __( 'Copy', 'dev3-ai-content-layout-builder' ) }
				</Button>
			</div>
		</div>
	);
}
