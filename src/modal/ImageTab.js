import { Button, CheckboxControl, TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import config from '../shared/config';
import { generate, request } from '../shared/api';
import { addLocal } from '../shared/history-local';
import ErrorNotice from './ErrorNotice';
import Busy from './Busy';

const FIELDS = [
	{ id: 'alt', label: __( 'Alt text', 'dev3-ai-content-layout-builder' ) },
	{ id: 'caption', label: __( 'Caption', 'dev3-ai-content-layout-builder' ) },
	{ id: 'title', label: __( 'Title attribute', 'dev3-ai-content-layout-builder' ) },
	{ id: 'description', label: __( 'Description', 'dev3-ai-content-layout-builder' ) },
	{ id: 'promo', label: __( 'Promotional copy', 'dev3-ai-content-layout-builder' ) },
];

export default function ImageTab( props ) {
	const { onInsert, onApplyMedia, field = 'text', restored } = props;
	const [ attachmentId, setAttachmentId ] = useState( props.attachmentId || 0 );
	const [ preview, setPreview ] = useState( props.imageUrl || '' );
	const [ url, setUrl ] = useState( props.attachmentId ? '' : props.imageUrl || '' );
	const [ fields, setFields ] = useState( props.visionFields || [ 'alt', 'caption', 'title' ] );
	const [ extra, setExtra ] = useState( '' );
	const [ language, setLanguage ] = useState( config.defaultLanguage || 'English' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ data, setData ] = useState( null );
	const [ applied, setApplied ] = useState( false );
	const [ insertField, setInsertField ] = useState( 'promo' );
	const lastRequest = useRef( null );

	useEffect( () => {
		if ( restored ) {
			try {
				setData( JSON.parse( restored.output ) );
			} catch ( e ) {}
		}
	}, [ restored && restored.nonce ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const pick = () => {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		const frame = window.wp.media( { title: __( 'Choose an image for the AI', 'dev3-ai-content-layout-builder' ), library: { type: 'image' }, multiple: false } );
		frame.on( 'select', () => {
			const att = frame.state().get( 'selection' ).first().toJSON();
			setAttachmentId( att.id );
			setPreview( ( att.sizes && att.sizes.medium && att.sizes.medium.url ) || att.url );
			setUrl( '' );
			setData( null );
		} );
		frame.open();
	};

	const run = ( body ) => {
		const req = body || {
			task: 'vision',
			attachment_id: attachmentId || 0,
			image_url: attachmentId ? '' : url,
			vision_fields: fields,
			prompt: extra,
			language,
			context: props.context || 'admin',
		};
		lastRequest.current = req;
		setBusy( true );
		setError( null );
		setApplied( false );
		generate( req )
			.then( ( res ) => {
				setData( res.output );
				const firstAvailable = [ 'promo', 'caption', 'description', 'alt', 'title' ].find( ( k ) => res.output[ k ] );
				setInsertField( firstAvailable || 'alt' );
				if ( res.history_id ) {
					addLocal( { id: res.history_id, task: 'vision', prompt: extra || url || `#${ attachmentId }`, output: JSON.stringify( res.output ), model: res.model, meta: { format: 'vision' } } );
				}
			} )
			.catch( setError )
			.finally( () => setBusy( false ) );
	};

	const apply = () => {
		request( `media/${ attachmentId }`, { method: 'POST', data } )
			.then( () => {
				setApplied( true );
				if ( onApplyMedia ) {
					onApplyMedia( data );
				}
			} )
			.catch( setError );
	};

	const hasImage = attachmentId > 0 || /^https?:\/\//.test( url );
	const available = data ? FIELDS.filter( ( f ) => data[ f.id ] !== undefined ) : [];

	return (
		<div>
			<ErrorNotice error={ error } onRetry={ () => lastRequest.current && run( lastRequest.current ) } onDismiss={ () => setError( null ) } />
			<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: '160px 1fr' } }>
				<div>
					<div className="dev3cl-lp-img" style={ { height: 140, overflow: 'hidden', borderRadius: 8 } }>
						{ preview || ( url && hasImage ) ? <img src={ preview || url } alt="" style={ { maxWidth: '100%', maxHeight: '100%', objectFit: 'cover' } } /> : __( 'No image', 'dev3-ai-content-layout-builder' ) }
					</div>
					{ window.wp && window.wp.media && (
						<Button variant="secondary" className="dev3cl-mt-2 dev3cl-w-full dev3cl-justify-center" onClick={ pick }>
							{ attachmentId ? __( 'Change image', 'dev3-ai-content-layout-builder' ) : __( 'Media Library', 'dev3-ai-content-layout-builder' ) }
						</Button>
					) }
				</div>
				<div>
					<TextControl
						label={ __( 'Or image URL', 'dev3-ai-content-layout-builder' ) }
						type="url"
						value={ url }
						placeholder="https://"
						onChange={ ( v ) => {
							setUrl( v );
							setAttachmentId( 0 );
							setPreview( '' );
						} }
					/>
					<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-gap-x-4">
						{ FIELDS.map( ( f ) => (
							<CheckboxControl
								key={ f.id }
								label={ f.label }
								checked={ fields.includes( f.id ) }
								onChange={ ( on ) => setFields( on ? [ ...fields, f.id ] : fields.filter( ( x ) => x !== f.id ) ) }
							/>
						) ) }
					</div>
					<div className="dev3cl-grid dev3cl-gap-3" style={ { gridTemplateColumns: '1fr 160px' } }>
						<TextControl label={ __( 'Context (optional)', 'dev3-ai-content-layout-builder' ) } value={ extra } placeholder={ __( 'e.g. Product: Aurora wireless earbuds', 'dev3-ai-content-layout-builder' ) } onChange={ setExtra } />
						<TextControl label={ __( 'Language', 'dev3-ai-content-layout-builder' ) } value={ language } onChange={ setLanguage } />
					</div>
					<Button variant="primary" onClick={ () => run() } disabled={ busy || ! hasImage || ! fields.length } isBusy={ busy }>
						{ __( 'Analyze image', 'dev3-ai-content-layout-builder' ) }
					</Button>
				</div>
			</div>

			{ busy && (
				<div className="dev3cl-mt-3">
					<Busy label={ __( 'AI is looking at your image…', 'dev3-ai-content-layout-builder' ) } />
				</div>
			) }

			{ ! busy && data && (
				<div className="dev3cl-mt-3">
					{ available.map( ( f ) =>
						f.id === 'alt' || f.id === 'title' ? (
							<TextControl key={ f.id } label={ f.label } value={ data[ f.id ] } onChange={ ( v ) => setData( { ...data, [ f.id ]: v } ) } />
						) : (
							<TextareaControl key={ f.id } label={ f.label } value={ data[ f.id ] } rows={ f.id === 'caption' ? 2 : 3 } onChange={ ( v ) => setData( { ...data, [ f.id ]: v } ) } />
						)
					) }
					<div className="dev3cl-flex dev3cl-flex-wrap dev3cl-items-end dev3cl-gap-2">
						{ attachmentId > 0 && (
							<Button variant="primary" onClick={ apply }>
								{ applied ? __( 'Saved to image ✓', 'dev3-ai-content-layout-builder' ) : __( 'Save to image', 'dev3-ai-content-layout-builder' ) }
							</Button>
						) }
						{ onInsert && available.length > 0 && (
							<>
								<div style={ { width: 170 } }>
									<SelectControl label={ __( 'Insert', 'dev3-ai-content-layout-builder' ) } value={ insertField } options={ available.map( ( f ) => ( { value: f.id, label: f.label } ) ) } onChange={ setInsertField } />
								</div>
								<div className="dev3cl-mb-2">
									<Button variant="secondary" onClick={ () => onInsert( field === 'wysiwyg' || field === 'html' ? `<p>${ data[ insertField ] }</p>` : data[ insertField ], { mode: 'replace' } ) }>
										{ __( 'Insert into field', 'dev3-ai-content-layout-builder' ) }
									</Button>
								</div>
							</>
						) }
						<div className="dev3cl-mb-2">
							<Button variant="secondary" onClick={ () => lastRequest.current && run( lastRequest.current ) } disabled={ ! lastRequest.current }>
								{ __( 'Regenerate', 'dev3-ai-content-layout-builder' ) }
							</Button>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
