import { Modal, Notice, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import config from '../shared/config';
import WriteTab from './WriteTab';
import LayoutTab from './LayoutTab';
import ImageTab from './ImageTab';
import HistoryTab from './HistoryTab';

const ALL_TABS = [
	{ id: 'write', label: __( 'Write', 'dev3-ai-content-layout-builder' ) },
	{ id: 'layout', label: __( 'Layout', 'dev3-ai-content-layout-builder' ) },
	{ id: 'image', label: __( 'Image', 'dev3-ai-content-layout-builder' ) },
	{ id: 'history', label: __( 'History', 'dev3-ai-content-layout-builder' ) },
];

export default function AIModal( props ) {
	const { onClose, title } = props;
	const allowed = props.tabs && props.tabs.length ? props.tabs : [ 'write', 'layout', 'image', 'history' ];
	const tabs = ALL_TABS.filter( ( t ) => allowed.includes( t.id ) );
	const [ tab, setTab ] = useState( allowed.includes( props.tab ) ? props.tab : tabs[ 0 ].id );
	// History restores hand a result to the tab that produced it.
	const [ restored, setRestored ] = useState( null );

	const restore = ( entry ) => {
		const target = entry.task === 'layout' ? 'layout' : entry.task === 'vision' ? 'image' : 'write';
		if ( ! allowed.includes( target ) ) {
			return;
		}
		setRestored( { ...entry, nonce: Date.now() } );
		setTab( target );
	};

	return (
		<Modal
			title={
				<>
					<span className="dev3cl-wand-icon" style={ { color: '#4f46e5' } } />
					{ title || __( 'DEV3 AI Assistant', 'dev3-ai-content-layout-builder' ) }
				</>
			}
			onRequestClose={ onClose }
			className="dev3cl-modal dev3cl-root"
			overlayClassName="dev3cl-modal-overlay"
			shouldCloseOnClickOutside={ false }
		>
			{ tabs.length > 1 && (
				<div className="dev3cl-tabs" role="tablist">
					{ tabs.map( ( t ) => (
						<button
							key={ t.id }
							type="button"
							role="tab"
							aria-selected={ tab === t.id }
							className={ 'dev3cl-tab' + ( tab === t.id ? ' is-active' : '' ) }
							onClick={ () => setTab( t.id ) }
						>
							{ t.label }
						</button>
					) ) }
				</div>
			) }

			{ ! config.hasKey && (
				<Notice status="warning" isDismissible={ false } className="dev3cl-mb-4">
					{ __( 'No Gemini API key is configured yet.', 'dev3-ai-content-layout-builder' ) }{ ' ' }
					{ config.canManage ? (
						<a href={ config.settingsUrl + '#/api' } target="_blank" rel="noreferrer">
							{ __( 'Add your key', 'dev3-ai-content-layout-builder' ) }
						</a>
					) : (
						__( 'Ask an administrator to add one.', 'dev3-ai-content-layout-builder' )
					) }
				</Notice>
			) }

			<div hidden={ tab !== 'write' }>
				{ allowed.includes( 'write' ) && <WriteTab { ...props } restored={ restored && restored.task !== 'layout' && restored.task !== 'vision' ? restored : null } /> }
			</div>
			<div hidden={ tab !== 'layout' }>
				{ allowed.includes( 'layout' ) && <LayoutTab { ...props } restored={ restored && restored.task === 'layout' ? restored : null } /> }
			</div>
			<div hidden={ tab !== 'image' }>
				{ allowed.includes( 'image' ) && <ImageTab { ...props } restored={ restored && restored.task === 'vision' ? restored : null } /> }
			</div>
			{ tab === 'history' && <HistoryTab onRestore={ restore } /> }

			<div className="dev3cl-mt-4 dev3cl-flex dev3cl-justify-end">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Close', 'dev3-ai-content-layout-builder' ) }
				</Button>
			</div>
		</Modal>
	);
}
