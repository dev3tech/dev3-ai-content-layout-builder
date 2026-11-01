import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton, PanelBody, Button } from '@wordpress/components';
import { useSelect, dispatch, select } from '@wordpress/data';
import { rawHandler, serialize } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { whenReady } from '../bridges/fields';

const TEXT_BLOCKS = [ 'core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/verse', 'core/preformatted' ];

const Wand = () => (
	<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
		<path fill="currentColor" d="M12 2l1.9 5.1L19 9l-5.1 1.9L12 16l-1.9-5.1L5 9l5.1-1.9L12 2zm7 11l.95 2.05L22 16l-2.05.95L19 19l-.95-2.05L16 16l2.05-.95L19 13zM6 15l.7 1.3L8 17l-1.3.7L6 19l-.7-1.3L4 17l1.3-.7L6 15z" />
	</svg>
);

const be = () => dispatch( 'core/block-editor' );

function blockHtml( block ) {
	return serialize( [ block ] ).replace( /<!--[\s\S]*?-->/g, '' ).trim();
}

function insertHtml( html, afterClientId ) {
	const blocks = rawHandler( { HTML: html } );
	if ( ! blocks.length ) {
		return;
	}
	if ( afterClientId ) {
		const store = select( 'core/block-editor' );
		const root = store.getBlockRootClientId( afterClientId );
		be().insertBlocks( blocks, store.getBlockIndex( afterClientId ) + 1, root || undefined );
	} else {
		be().insertBlocks( blocks );
	}
}

function openForBlock( block ) {
	if ( block.name === 'core/image' ) {
		window.dev3cl.open( {
			context: 'gutenberg',
			tab: 'image',
			tabs: [ 'image', 'history' ],
			attachmentId: block.attributes.id || 0,
			imageUrl: block.attributes.url || '',
			visionFields: [ 'alt', 'caption' ],
			onApplyMedia: ( data ) => {
				be().updateBlockAttributes( block.clientId, { ...( data.alt ? { alt: data.alt } : {} ), ...( data.caption ? { caption: data.caption } : {} ) } );
			},
			onInsert: ( value ) => {
				insertHtml( value.startsWith( '<' ) ? value : `<p>${ value }</p>`, block.clientId );
				window.dev3cl.close();
			},
		} );
		return;
	}
	const isHeading = block.name === 'core/heading';
	window.dev3cl.open( {
		context: 'gutenberg',
		builder: 'gutenberg',
		tabs: [ 'write', 'history' ],
		text: blockHtml( block ),
		field: isHeading ? 'heading' : 'wysiwyg',
		onInsert: ( value, { mode } ) => {
			if ( isHeading && mode !== 'append' ) {
				be().updateBlockAttributes( block.clientId, { content: value } );
			} else if ( mode === 'append' ) {
				insertHtml( value, block.clientId );
			} else {
				const blocks = rawHandler( { HTML: value } );
				if ( blocks.length ) {
					be().replaceBlocks( block.clientId, blocks );
				}
			}
			window.dev3cl.close();
		},
	} );
}

const withToolbar = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if ( ! window.dev3cl || ( ! TEXT_BLOCKS.includes( props.name ) && props.name !== 'core/image' ) || ! props.isSelected ) {
			return <BlockEdit { ...props } />;
		}
		return (
			<>
				<BlockControls group="other">
					<ToolbarGroup>
						<ToolbarButton icon={ <Wand /> } label={ __( 'Write with DEV3 AI', 'dev3-ai-content-layout-builder' ) } onClick={ () => openForBlock( select( 'core/block-editor' ).getBlock( props.clientId ) ) } />
					</ToolbarGroup>
				</BlockControls>
				<BlockEdit { ...props } />
			</>
		);
	},
	'withGeminiToolbar'
);

function postText() {
	const blocks = select( 'core/block-editor' ).getBlocks();
	return serialize( blocks )
		.replace( /<!--[\s\S]*?-->/g, '' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim()
		.slice( 0, 12000 );
}

function Sidebar() {
	const selected = useSelect( ( s ) => s( 'core/block-editor' ).getSelectedBlock(), [] );
	const PluginSidebar = ( window.wp.editor && window.wp.editor.PluginSidebar ) || ( window.wp.editPost && window.wp.editPost.PluginSidebar );
	const MoreMenuItem = ( window.wp.editor && window.wp.editor.PluginSidebarMoreMenuItem ) || ( window.wp.editPost && window.wp.editPost.PluginSidebarMoreMenuItem );
	if ( ! PluginSidebar ) {
		return null;
	}
	const canImprove = selected && ( TEXT_BLOCKS.includes( selected.name ) || selected.name === 'core/image' );
	return (
		<>
			{ MoreMenuItem && (
				<MoreMenuItem target="dev3cl-sidebar" icon={ <Wand /> }>
					{ __( 'DEV3 AI Assistant', 'dev3-ai-content-layout-builder' ) }
				</MoreMenuItem>
			) }
			<PluginSidebar name="dev3cl-sidebar" title={ __( 'DEV3 AI Assistant', 'dev3-ai-content-layout-builder' ) } icon={ <Wand /> }>
				<PanelBody>
					<p>{ __( 'Write, rewrite and translate content, generate SEO meta and describe images with AI.', 'dev3-ai-content-layout-builder' ) }</p>
					<div style={ { display: 'grid', gap: 8 } }>
						<Button
							variant="primary"
							onClick={ () =>
								window.dev3cl.open( {
									context: 'gutenberg',
									tabs: [ 'write', 'image', 'history' ],
									field: 'wysiwyg',
									onInsert: ( value ) => {
										insertHtml( value, selected ? selected.clientId : null );
										window.dev3cl.close();
									},
								} )
							}
						>
							{ __( 'Write new content', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button variant="secondary" disabled={ ! canImprove } onClick={ () => openForBlock( selected ) }>
							{ selected && selected.name === 'core/image' ? __( 'Describe selected image', 'dev3-ai-content-layout-builder' ) : __( 'Improve selected block', 'dev3-ai-content-layout-builder' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ () =>
								window.dev3cl.open( {
									context: 'gutenberg',
									tabs: [ 'write', 'history' ],
									task: 'seo_meta',
									text: postText(),
									prompt: select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '',
									onInsertSeo: ( seo ) => {
										dispatch( 'core/editor' ).editPost( { excerpt: seo.description, ...( seo.slug ? { slug: seo.slug } : {} ) } );
										dispatch( 'core/notices' ).createNotice( 'success', __( 'Excerpt and slug updated from AI SEO meta.', 'dev3-ai-content-layout-builder' ), { type: 'snackbar' } );
										window.dev3cl.close();
									},
								} )
							}
						>
							{ __( 'SEO title & description', 'dev3-ai-content-layout-builder' ) }
						</Button>
					</div>
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

addFilter( 'editor.BlockEdit', 'dev3-ai-content-layout-builder/toolbar', withToolbar );
whenReady( () => registerPlugin( 'dev3-ai-content-layout-builder', { render: Sidebar, icon: <Wand /> } ) );
