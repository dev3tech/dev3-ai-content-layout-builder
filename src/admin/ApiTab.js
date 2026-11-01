import { Button, Notice, RangeControl, SelectControl, TextControl, ExternalLink } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../shared/api';

const GROUPS = [
	{ id: 'copy', label: __( 'Copywriting & rewriting', 'dev3-ai-content-layout-builder' ) },
	{ id: 'seo', label: __( 'SEO meta', 'dev3-ai-content-layout-builder' ) },
	{ id: 'translate', label: __( 'Translation', 'dev3-ai-content-layout-builder' ) },
	{ id: 'layout', label: __( 'Layout generator', 'dev3-ai-content-layout-builder' ) },
	{ id: 'vision', label: __( 'Image analysis', 'dev3-ai-content-layout-builder' ) },
];

const CUSTOM = '__custom';

function ModelPicker( { label, help, value, models, onChange } ) {
	const known = models.some( ( m ) => m.id === value );
	const [ custom, setCustom ] = useState( ! known && models.length > 0 );
	const options = [ ...models.map( ( m ) => ( { value: m.id, label: `${ m.name } (${ m.id })` } ) ), { value: CUSTOM, label: __( 'Custom model ID…', 'dev3-ai-content-layout-builder' ) } ];
	if ( ! models.length || custom ) {
		return (
			<div>
				<TextControl label={ label } help={ help } value={ value } onChange={ onChange } placeholder="gemini-2.5-flash" />
				{ models.length > 0 && (
					<Button variant="link" onClick={ () => setCustom( false ) }>
						{ __( 'Choose from list', 'dev3-ai-content-layout-builder' ) }
					</Button>
				) }
			</div>
		);
	}
	return (
		<SelectControl
			label={ label }
			help={ help }
			value={ known ? value : CUSTOM }
			options={ options }
			onChange={ ( v ) => {
				if ( v === CUSTOM ) {
					setCustom( true );
				} else {
					onChange( v );
				}
			} }
		/>
	);
}

export default function ApiTab( { settings, save } ) {
	const [ key, setKey ] = useState( settings.api_key_masked || '' );
	const [ form, setForm ] = useState( settings );
	const [ models, setModels ] = useState( [] );
	const [ status, setStatus ] = useState( null );
	const [ busy, setBusy ] = useState( '' );

	useEffect( () => {
		if ( settings.api_key_set ) {
			request( 'models' ).then( setModels ).catch( () => {} );
		}
	}, [ settings.api_key_set ] );

	const set = ( k ) => ( v ) => setForm( { ...form, [ k ]: v } );

	const validateAndSave = () => {
		setBusy( 'validate' );
		setStatus( null );
		request( 'settings/validate', { method: 'POST', data: { api_key: key } } )
			.then( ( res ) => {
				setModels( res.models );
				setStatus( { ok: true, message: sprintf( /* translators: %d: number of models */ __( 'Connected — %d models available to this key.', 'dev3-ai-content-layout-builder' ), res.models.length ) } );
				return save( { api_key: key } ).then( ( next ) => setKey( next.api_key_masked ) );
			} )
			.catch( ( err ) => setStatus( { ok: false, message: err.message, hint: err.hint } ) )
			.finally( () => setBusy( '' ) );
	};

	const saveEngine = () => {
		setBusy( 'engine' );
		save( {
			default_model: form.default_model,
			pro_model: form.pro_model,
			task_models: form.task_models,
			temperature: form.temperature,
			max_tokens: parseInt( form.max_tokens, 10 ) || 4096,
			reasoning: form.reasoning,
		} ).finally( () => setBusy( '' ) );
	};

	const refresh = () => {
		setBusy( 'models' );
		request( 'models?refresh=1' )
			.then( setModels )
			.catch( ( err ) => setStatus( { ok: false, message: err.message, hint: err.hint } ) )
			.finally( () => setBusy( '' ) );
	};

	return (
		<>
			<div className="dev3cl-card">
				<h2>{ __( 'Google Gemini API key', 'dev3-ai-content-layout-builder' ) }</h2>
				<p className="description">
					{ __( 'Create a free key in Google AI Studio, paste it here and click “Validate & save”. The key is encrypted in your database and never sent to the browser.', 'dev3-ai-content-layout-builder' ) }{ ' ' }
					<ExternalLink href="https://aistudio.google.com/app/apikey">{ __( 'Get an API key', 'dev3-ai-content-layout-builder' ) }</ExternalLink>
				</p>
				{ settings.api_key_constant ? (
					<Notice status="info" isDismissible={ false }>
						{ __( 'The key is defined by the DEV3CL_GEMINI_API_KEY constant in wp-config.php.', 'dev3-ai-content-layout-builder' ) }
					</Notice>
				) : (
					<div className="dev3cl-flex dev3cl-items-end dev3cl-gap-2 dev3cl-flex-wrap">
						<div style={ { flex: '1 1 360px' } }>
							<TextControl
								label={ __( 'API key', 'dev3-ai-content-layout-builder' ) }
								value={ key }
								onChange={ setKey }
								onFocus={ () => key.includes( '•' ) && setKey( '' ) }
								autoComplete="off"
								spellCheck={ false }
								placeholder="AIza…"
							/>
						</div>
						<div className="dev3cl-mb-2 dev3cl-flex dev3cl-gap-2">
							<Button variant="primary" onClick={ validateAndSave } isBusy={ busy === 'validate' } disabled={ !! busy || ! key }>
								{ __( 'Validate & save', 'dev3-ai-content-layout-builder' ) }
							</Button>
							{ settings.api_key_set && (
								<Button
									variant="tertiary"
									isDestructive
									onClick={ () => save( { api_key: '' } ).then( () => {
										setKey( '' );
										setModels( [] );
									} ) }
								>
									{ __( 'Remove key', 'dev3-ai-content-layout-builder' ) }
								</Button>
							) }
						</div>
					</div>
				) }
				{ ! settings.encryption_available && (
					<Notice status="warning" isDismissible={ false }>
						{ __( 'OpenSSL is not available on this server, so the key is only obfuscated. Consider defining DEV3CL_GEMINI_API_KEY in wp-config.php instead.', 'dev3-ai-content-layout-builder' ) }
					</Notice>
				) }
				{ status && (
					<Notice status={ status.ok ? 'success' : 'error' } isDismissible={ false }>
						<strong>{ status.message }</strong> { status.hint }
					</Notice>
				) }
			</div>

			<div className="dev3cl-card">
				<div className="dev3cl-flex dev3cl-justify-between dev3cl-items-start">
					<div>
						<h2>{ __( 'Models', 'dev3-ai-content-layout-builder' ) }</h2>
						<p className="description">{ __( 'Fast is used for most editing tasks; Pro for complex, long-form or structured work.', 'dev3-ai-content-layout-builder' ) }</p>
					</div>
					<Button variant="secondary" onClick={ refresh } isBusy={ busy === 'models' } disabled={ ! settings.api_key_set || !! busy }>
						{ __( 'Refresh models', 'dev3-ai-content-layout-builder' ) }
					</Button>
				</div>
				<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))' } }>
					<ModelPicker label={ __( 'Fast model', 'dev3-ai-content-layout-builder' ) } help={ __( 'Recommended: gemini-2.5-flash', 'dev3-ai-content-layout-builder' ) } value={ form.default_model } models={ models } onChange={ set( 'default_model' ) } />
					<ModelPicker label={ __( 'Pro model', 'dev3-ai-content-layout-builder' ) } help={ __( 'Recommended: gemini-2.5-pro', 'dev3-ai-content-layout-builder' ) } value={ form.pro_model } models={ models } onChange={ set( 'pro_model' ) } />
				</div>
				<h3 className="dev3cl-mt-4 dev3cl-mb-2" style={ { fontSize: 14 } }>{ __( 'Model per task', 'dev3-ai-content-layout-builder' ) }</h3>
				<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))' } }>
					{ GROUPS.map( ( g ) => (
						<SelectControl
							key={ g.id }
							label={ g.label }
							value={ [ 'default', 'pro' ].includes( form.task_models[ g.id ] ) ? form.task_models[ g.id ] : 'default' }
							options={ [
								{ value: 'default', label: __( 'Fast', 'dev3-ai-content-layout-builder' ) },
								{ value: 'pro', label: __( 'Pro', 'dev3-ai-content-layout-builder' ) },
							] }
							onChange={ ( v ) => setForm( { ...form, task_models: { ...form.task_models, [ g.id ]: v } } ) }
						/>
					) ) }
				</div>
			</div>

			<div className="dev3cl-card">
				<h2>{ __( 'Generation parameters', 'dev3-ai-content-layout-builder' ) }</h2>
				<div className="dev3cl-grid dev3cl-gap-6" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))' } }>
					<RangeControl
						label={ __( 'Temperature', 'dev3-ai-content-layout-builder' ) }
						help={ __( 'Lower = focused and consistent, higher = more creative.', 'dev3-ai-content-layout-builder' ) }
						value={ form.temperature }
						onChange={ set( 'temperature' ) }
						min={ 0 }
						max={ 1 }
						step={ 0.05 }
					/>
					<RangeControl
						label={ __( 'Max output tokens', 'dev3-ai-content-layout-builder' ) }
						help={ __( 'Upper limit per answer. Layouts always get at least 12288.', 'dev3-ai-content-layout-builder' ) }
						value={ parseInt( form.max_tokens, 10 ) }
						onChange={ set( 'max_tokens' ) }
						min={ 256 }
						max={ 32768 }
						step={ 256 }
					/>
					<SelectControl
						label={ __( 'Reasoning', 'dev3-ai-content-layout-builder' ) }
						help={ __( '“Low” keeps editor generations fast and cheap; “Auto” lets the model think longer.', 'dev3-ai-content-layout-builder' ) }
						value={ form.reasoning }
						options={ [
							{ value: 'low', label: __( 'Low (fast)', 'dev3-ai-content-layout-builder' ) },
							{ value: 'auto', label: __( 'Auto (model decides)', 'dev3-ai-content-layout-builder' ) },
						] }
						onChange={ set( 'reasoning' ) }
					/>
				</div>
				<Button variant="primary" onClick={ saveEngine } isBusy={ busy === 'engine' } disabled={ !! busy }>
					{ __( 'Save engine settings', 'dev3-ai-content-layout-builder' ) }
				</Button>
			</div>
		</>
	);
}
