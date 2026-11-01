import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function ErrorNotice( { error, onRetry, onDismiss } ) {
	if ( ! error || error.silent || error.aborted ) {
		return null;
	}
	const actions = [];
	if ( error.retryable && onRetry ) {
		actions.push( { label: __( 'Try again', 'dev3-ai-content-layout-builder' ), onClick: onRetry } );
	}
	return (
		<Notice status="error" onRemove={ onDismiss } actions={ actions } className="dev3cl-mb-3">
			<strong>{ error.message }</strong>
			{ error.hint && <div className="dev3cl-mt-1">{ error.hint }</div> }
		</Notice>
	);
}
