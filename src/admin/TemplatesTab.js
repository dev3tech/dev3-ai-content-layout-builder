import { Button, SelectControl, TextControl, TextareaControl, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../shared/api';
import { invalidateTemplates } from '../modal/useTemplates';

const TASKS = [
	{ value: 'generate', label: __( 'Write copy', 'dev3-ai-content-layout-builder' ) },
	{ value: 'layout', label: __( 'Section layout', 'dev3-ai-content-layout-builder' ) },
	{ value: 'seo_meta', label: __( 'SEO meta', 'dev3-ai-content-layout-builder' ) },
	{ value: 'headline_variations', label: __( 'Headline ideas', 'dev3-ai-content-layout-builder' ) },
];

const EMPTY = { id: '', name: '', task: 'generate', format: 'html', prompt: '' };

export default function TemplatesTab() {
	const [ list, setList ] = useState( [] );
	const [ form, setForm ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = () => request( 'templates' ).then( setList ).catch( setError );
	useEffect( () => {
		load();
	}, [] );

	const submit = () => {
		request( 'templates', { method: 'POST', data: form } )
			.then( () => {
				invalidateTemplates();
				setForm( null );
				load();
			} )
			.catch( setError );
	};

	const remove = ( id ) => {
		// eslint-disable-next-line no-alert
		if ( window.confirm( __( 'Delete this template?', 'dev3-ai-content-layout-builder' ) ) ) {
			request( `templates/${ id }`, { method: 'DELETE' } )
				.then( () => {
					invalidateTemplates();
					load();
				} )
				.catch( setError );
		}
	};

	const tryIt = ( tpl ) => {
		if ( ! window.dev3cl ) {
			return;
		}
		window.dev3cl.open( { context: 'admin', tab: tpl.task === 'layout' ? 'layout' : 'write', field: 'wysiwyg', title: tpl.name } );
	};

	return (
		<>
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>
					{ error.message }
				</Notice>
			) }
			{ form ? (
				<div className="dev3cl-card">
					<h2>{ form.id ? __( 'Edit template', 'dev3-ai-content-layout-builder' ) : __( 'New template', 'dev3-ai-content-layout-builder' ) }</h2>
					<p className="description">{ __( 'Use {placeholders} for values the editor fills in, e.g. "Write a product description for {product} aimed at {audience}."', 'dev3-ai-content-layout-builder' ) }</p>
					<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: '2fr 1fr 1fr' } }>
						<TextControl label={ __( 'Name', 'dev3-ai-content-layout-builder' ) } value={ form.name } onChange={ ( v ) => setForm( { ...form, name: v } ) } />
						<SelectControl label={ __( 'Type', 'dev3-ai-content-layout-builder' ) } value={ form.task } options={ TASKS } onChange={ ( v ) => setForm( { ...form, task: v } ) } />
						<SelectControl
							label={ __( 'Output', 'dev3-ai-content-layout-builder' ) }
							value={ form.format }
							disabled={ form.task !== 'generate' }
							options={ [
								{ value: 'html', label: __( 'Rich text (HTML)', 'dev3-ai-content-layout-builder' ) },
								{ value: 'plain', label: __( 'Plain text', 'dev3-ai-content-layout-builder' ) },
							] }
							onChange={ ( v ) => setForm( { ...form, format: v } ) }
						/>
					</div>
					<TextareaControl label={ __( 'Prompt', 'dev3-ai-content-layout-builder' ) } value={ form.prompt } onChange={ ( v ) => setForm( { ...form, prompt: v } ) } rows={ 6 } />
					<div className="dev3cl-flex dev3cl-gap-2">
						<Button variant="primary" onClick={ submit } disabled={ ! form.name || ! form.prompt }>
							{ __( 'Save template', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button variant="tertiary" onClick={ () => setForm( null ) }>
							{ __( 'Cancel', 'dev3-ai-content-layout-builder' ) }
						</Button>
					</div>
				</div>
			) : (
				<Button variant="primary" className="dev3cl-mb-4" onClick={ () => setForm( EMPTY ) }>
					{ __( 'Add template', 'dev3-ai-content-layout-builder' ) }
				</Button>
			) }
			<table className="widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Template', 'dev3-ai-content-layout-builder' ) }</th>
						<th>{ __( 'Type', 'dev3-ai-content-layout-builder' ) }</th>
						<th>{ __( 'Fields', 'dev3-ai-content-layout-builder' ) }</th>
						<th style={ { width: 220 } } />
					</tr>
				</thead>
				<tbody>
					{ list.map( ( tpl ) => (
						<tr key={ tpl.id }>
							<td>
								<strong>{ tpl.name }</strong>
								{ tpl.builtin && <span className="dev3cl-badge is-ok" style={ { marginLeft: 8 } }>{ __( 'Built-in', 'dev3-ai-content-layout-builder' ) }</span> }
								<div className="dev3cl-text-xs dev3cl-text-gray-500" style={ { maxWidth: 520 } }>{ tpl.prompt.slice( 0, 140 ) }{ tpl.prompt.length > 140 ? '…' : '' }</div>
							</td>
							<td>{ ( TASKS.find( ( t ) => t.value === tpl.task ) || {} ).label || tpl.task }</td>
							<td>{ tpl.fields.map( ( f ) => f.label ).join( ', ' ) || '—' }</td>
							<td style={ { textAlign: 'right' } }>
								<Button size="small" variant="secondary" onClick={ () => tryIt( tpl ) }>
									{ __( 'Try', 'dev3-ai-content-layout-builder' ) }
								</Button>{ ' ' }
								{ ! tpl.builtin && (
									<>
										<Button size="small" variant="secondary" onClick={ () => setForm( tpl ) }>
											{ __( 'Edit', 'dev3-ai-content-layout-builder' ) }
										</Button>{ ' ' }
										<Button size="small" variant="tertiary" isDestructive onClick={ () => remove( tpl.id ) }>
											{ __( 'Delete', 'dev3-ai-content-layout-builder' ) }
										</Button>
									</>
								) }
								{ tpl.builtin && (
									<Button size="small" variant="tertiary" onClick={ () => setForm( { ...tpl, id: '', name: `${ tpl.name } (copy)` } ) }>
										{ __( 'Duplicate', 'dev3-ai-content-layout-builder' ) }
									</Button>
								) }
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</>
	);
}
