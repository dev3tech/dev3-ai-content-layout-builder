import { Button, SelectControl, TextControl, TextareaControl, ToggleControl, __experimentalNumberControl as NumberControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import config from '../shared/config';

const Num = NumberControl || TextControl;

export default function GeneralTab( { settings, save } ) {
	const [ form, setForm ] = useState( settings );
	const [ busy, setBusy ] = useState( false );
	const set = ( key ) => ( value ) => setForm( { ...form, [ key ]: value } );
	const setIntegration = ( key ) => ( value ) => setForm( { ...form, integrations: { ...form.integrations, [ key ]: value } } );

	const submit = () => {
		setBusy( true );
		save( {
			default_tone: form.default_tone,
			default_language: form.default_language,
			brand_voice: form.brand_voice,
			integrations: form.integrations,
			history_enabled: form.history_enabled,
			history_days: parseInt( form.history_days, 10 ) || 90,
			usage_logging: form.usage_logging,
			rate_limit: parseInt( form.rate_limit, 10 ) || 0,
			delete_on_uninstall: form.delete_on_uninstall,
		} ).finally( () => setBusy( false ) );
	};

	return (
		<>
			<div className="dev3cl-card">
				<h2>{ __( 'Writing defaults', 'dev3-ai-content-layout-builder' ) }</h2>
				<p className="description">{ __( 'Used whenever an editor does not pick something else in the AI modal.', 'dev3-ai-content-layout-builder' ) }</p>
				<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))' } }>
					<SelectControl label={ __( 'Default tone', 'dev3-ai-content-layout-builder' ) } value={ form.default_tone } options={ config.tones || [] } onChange={ set( 'default_tone' ) } />
					<TextControl label={ __( 'Default language', 'dev3-ai-content-layout-builder' ) } value={ form.default_language } onChange={ set( 'default_language' ) } />
				</div>
				<TextareaControl
					label={ __( 'Brand voice & guidelines', 'dev3-ai-content-layout-builder' ) }
					help={ __( 'Optional. Added to every copywriting request — e.g. "We are a friendly Berlin bakery. Use British spelling. Never use exclamation marks."', 'dev3-ai-content-layout-builder' ) }
					value={ form.brand_voice }
					onChange={ set( 'brand_voice' ) }
					rows={ 4 }
				/>
			</div>
			<div className="dev3cl-card">
				<h2>{ __( 'Integrations', 'dev3-ai-content-layout-builder' ) }</h2>
				<p className="description">{ __( 'Turn off any editor you do not use. Disabled integrations load no scripts.', 'dev3-ai-content-layout-builder' ) }</p>
				<ToggleControl label={ __( 'Elementor', 'dev3-ai-content-layout-builder' ) } checked={ !! form.integrations.elementor } onChange={ setIntegration( 'elementor' ) } />
				<ToggleControl label={ __( 'WPBakery Page Builder', 'dev3-ai-content-layout-builder' ) } checked={ !! form.integrations.wpbakery } onChange={ setIntegration( 'wpbakery' ) } />
				<ToggleControl label={ __( 'Block editor (Gutenberg)', 'dev3-ai-content-layout-builder' ) } checked={ !! form.integrations.gutenberg } onChange={ setIntegration( 'gutenberg' ) } />
				<ToggleControl label={ __( 'Media Library (alt text, captions, titles)', 'dev3-ai-content-layout-builder' ) } checked={ !! form.integrations.media } onChange={ setIntegration( 'media' ) } />
			</div>
			<div className="dev3cl-card">
				<h2>{ __( 'History, logging & limits', 'dev3-ai-content-layout-builder' ) }</h2>
				<ToggleControl label={ __( 'Keep a generation history for each user', 'dev3-ai-content-layout-builder' ) } checked={ !! form.history_enabled } onChange={ set( 'history_enabled' ) } />
				<ToggleControl label={ __( 'Log usage (tokens, model, latency)', 'dev3-ai-content-layout-builder' ) } checked={ !! form.usage_logging } onChange={ set( 'usage_logging' ) } />
				<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))' } }>
					<Num label={ __( 'Keep history for (days)', 'dev3-ai-content-layout-builder' ) } value={ form.history_days } min={ 1 } max={ 3650 } onChange={ set( 'history_days' ) } />
					<Num
						label={ __( 'Requests per user per minute', 'dev3-ai-content-layout-builder' ) }
						help={ __( '0 = unlimited. Protects your Gemini quota.', 'dev3-ai-content-layout-builder' ) }
						value={ form.rate_limit }
						min={ 0 }
						max={ 600 }
						onChange={ set( 'rate_limit' ) }
					/>
				</div>
				<ToggleControl
					label={ __( 'Delete all plugin data when the plugin is uninstalled', 'dev3-ai-content-layout-builder' ) }
					checked={ !! form.delete_on_uninstall }
					onChange={ set( 'delete_on_uninstall' ) }
				/>
			</div>
			<Button variant="primary" onClick={ submit } isBusy={ busy } disabled={ busy }>
				{ __( 'Save settings', 'dev3-ai-content-layout-builder' ) }
			</Button>
		</>
	);
}
