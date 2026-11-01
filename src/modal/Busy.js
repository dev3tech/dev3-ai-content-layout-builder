import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cancel } from '../shared/api';

export default function Busy( { label, onCancel } ) {
	return (
		<div className="dev3cl-output" aria-live="polite" aria-busy="true">
			<div className="dev3cl-flex dev3cl-items-center dev3cl-gap-2 dev3cl-mb-3 dev3cl-text-sm dev3cl-text-gray-600">
				<Spinner />
				<span>{ label || __( 'AI is writing…', 'dev3-ai-content-layout-builder' ) }</span>
				<Button
					variant="link"
					className="dev3cl-ml-auto"
					onClick={ () => {
						cancel();
						if ( onCancel ) {
							onCancel();
						}
					} }
				>
					{ __( 'Stop', 'dev3-ai-content-layout-builder' ) }
				</Button>
			</div>
			<div className="dev3cl-skeleton dev3cl-mb-2" style={ { width: '92%' } } />
			<div className="dev3cl-skeleton dev3cl-mb-2" style={ { width: '80%' } } />
			<div className="dev3cl-skeleton" style={ { width: '64%' } } />
		</div>
	);
}
