import { Button, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../shared/api';

const fmt = ( n ) => Number( n || 0 ).toLocaleString();

function Table( { title, rows, keyName, label } ) {
	return (
		<div className="dev3cl-card" style={ { padding: 0 } }>
			<h2 style={ { padding: '14px 16px 0' } }>{ title }</h2>
			<table className="widefat" style={ { border: 0 } }>
				<thead>
					<tr>
						<th>{ label }</th>
						<th style={ { textAlign: 'right' } }>{ __( 'Requests', 'dev3-ai-content-layout-builder' ) }</th>
						<th style={ { textAlign: 'right' } }>{ __( 'Tokens', 'dev3-ai-content-layout-builder' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ rows.length === 0 && (
						<tr>
							<td colSpan="3">—</td>
						</tr>
					) }
					{ rows.map( ( r, i ) => (
						<tr key={ i }>
							<td>{ r[ keyName ] || '—' }</td>
							<td style={ { textAlign: 'right' } }>{ fmt( r.requests ) }</td>
							<td style={ { textAlign: 'right' } }>{ fmt( r.tokens ) }</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

export default function UsageTab() {
	const [ days, setDays ] = useState( '30' );
	const [ summary, setSummary ] = useState( null );
	const [ recent, setRecent ] = useState( { items: [], total: 0 } );
	const [ page, setPage ] = useState( 1 );

	useEffect( () => {
		setSummary( null );
		request( `usage?days=${ days }` ).then( setSummary ).catch( () => setSummary( false ) );
	}, [ days ] );
	useEffect( () => {
		request( `usage/recent?page=${ page }` ).then( setRecent ).catch( () => {} );
	}, [ page ] );

	if ( summary === null ) {
		return <Spinner />;
	}
	if ( summary === false ) {
		return <p>{ __( 'Usage could not be loaded.', 'dev3-ai-content-layout-builder' ) }</p>;
	}
	const t = summary.totals;
	const max = Math.max( 1, ...summary.by_day.map( ( d ) => d.requests ) );

	return (
		<>
			<div className="dev3cl-flex dev3cl-justify-between dev3cl-items-center dev3cl-mb-3">
				<p className="description dev3cl-m-0">{ __( 'Token counts come from Gemini’s usage metadata. Check exact billing in Google AI Studio / Cloud Console.', 'dev3-ai-content-layout-builder' ) }</p>
				<div style={ { width: 160 } }>
					<SelectControl
						value={ days }
						hideLabelFromVision
						label={ __( 'Period', 'dev3-ai-content-layout-builder' ) }
						options={ [
							{ value: '7', label: __( 'Last 7 days', 'dev3-ai-content-layout-builder' ) },
							{ value: '30', label: __( 'Last 30 days', 'dev3-ai-content-layout-builder' ) },
							{ value: '90', label: __( 'Last 90 days', 'dev3-ai-content-layout-builder' ) },
						] }
						onChange={ setDays }
					/>
				</div>
			</div>
			<div className="dev3cl-grid dev3cl-gap-3 dev3cl-mb-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))' } }>
				<div className="dev3cl-stat"><strong>{ fmt( t.requests ) }</strong><span>{ __( 'Requests', 'dev3-ai-content-layout-builder' ) }</span></div>
				<div className="dev3cl-stat"><strong>{ fmt( t.total_tokens ) }</strong><span>{ __( 'Total tokens', 'dev3-ai-content-layout-builder' ) }</span></div>
				<div className="dev3cl-stat"><strong>{ fmt( t.prompt_tokens ) }</strong><span>{ __( 'Input tokens', 'dev3-ai-content-layout-builder' ) }</span></div>
				<div className="dev3cl-stat"><strong>{ fmt( t.output_tokens ) }</strong><span>{ __( 'Output tokens', 'dev3-ai-content-layout-builder' ) }</span></div>
				<div className="dev3cl-stat"><strong>{ ( t.avg_ms / 1000 ).toFixed( 1 ) }s</strong><span>{ __( 'Avg. response time', 'dev3-ai-content-layout-builder' ) }</span></div>
				<div className="dev3cl-stat"><strong>{ fmt( t.errors ) }</strong><span>{ __( 'Errors', 'dev3-ai-content-layout-builder' ) }</span></div>
			</div>
			<div className="dev3cl-card">
				<h2>{ __( 'Requests per day', 'dev3-ai-content-layout-builder' ) }</h2>
				{ summary.by_day.length ? (
					<div className="dev3cl-bars" role="img" aria-label={ __( 'Requests per day chart', 'dev3-ai-content-layout-builder' ) }>
						{ summary.by_day.map( ( d ) => (
							<div key={ d.day } style={ { height: `${ ( d.requests / max ) * 100 }%` } } title={ sprintf( /* translators: 1: date, 2: requests, 3: tokens */ __( '%1$s — %2$s requests, %3$s tokens', 'dev3-ai-content-layout-builder' ), d.day, fmt( d.requests ), fmt( d.tokens ) ) } />
						) ) }
					</div>
				) : (
					<p>{ __( 'No requests in this period yet.', 'dev3-ai-content-layout-builder' ) }</p>
				) }
			</div>
			<div className="dev3cl-grid dev3cl-gap-4" style={ { gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))' } }>
				<Table title={ __( 'By task', 'dev3-ai-content-layout-builder' ) } rows={ summary.by_task } keyName="task" label={ __( 'Task', 'dev3-ai-content-layout-builder' ) } />
				<Table title={ __( 'By model', 'dev3-ai-content-layout-builder' ) } rows={ summary.by_model } keyName="model" label={ __( 'Model', 'dev3-ai-content-layout-builder' ) } />
				<Table title={ __( 'By user', 'dev3-ai-content-layout-builder' ) } rows={ summary.by_user } keyName="name" label={ __( 'User', 'dev3-ai-content-layout-builder' ) } />
			</div>
			<div className="dev3cl-card" style={ { padding: 0 } }>
				<h2 style={ { padding: '14px 16px 0' } }>{ __( 'Recent requests', 'dev3-ai-content-layout-builder' ) }</h2>
				<table className="widefat striped" style={ { border: 0 } }>
					<thead>
						<tr>
							<th>{ __( 'Time', 'dev3-ai-content-layout-builder' ) }</th>
							<th>{ __( 'User', 'dev3-ai-content-layout-builder' ) }</th>
							<th>{ __( 'Task', 'dev3-ai-content-layout-builder' ) }</th>
							<th>{ __( 'Where', 'dev3-ai-content-layout-builder' ) }</th>
							<th>{ __( 'Model', 'dev3-ai-content-layout-builder' ) }</th>
							<th style={ { textAlign: 'right' } }>{ __( 'Tokens', 'dev3-ai-content-layout-builder' ) }</th>
							<th style={ { textAlign: 'right' } }>{ __( 'Time (s)', 'dev3-ai-content-layout-builder' ) }</th>
							<th>{ __( 'Status', 'dev3-ai-content-layout-builder' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ recent.items.map( ( r ) => (
							<tr key={ r.id }>
								<td>{ new Date( r.created_at ).toLocaleString() }</td>
								<td>{ r.user }</td>
								<td>{ r.task }</td>
								<td>{ r.context }</td>
								<td>{ r.model }</td>
								<td style={ { textAlign: 'right' } }>{ fmt( r.total_tokens ) }</td>
								<td style={ { textAlign: 'right' } }>{ ( r.duration_ms / 1000 ).toFixed( 1 ) }</td>
								<td>{ r.status === 'ok' ? <span className="dev3cl-badge is-ok">OK</span> : <span className="dev3cl-badge is-bad" title={ r.error_code }>{ r.error_code || r.status }</span> }</td>
							</tr>
						) ) }
					</tbody>
				</table>
				<div className="dev3cl-flex dev3cl-justify-end dev3cl-gap-2 dev3cl-p-3">
					<Button size="small" variant="secondary" disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>
						{ __( 'Newer', 'dev3-ai-content-layout-builder' ) }
					</Button>
					<Button size="small" variant="secondary" disabled={ page * 50 >= recent.total } onClick={ () => setPage( page + 1 ) }>
						{ __( 'Older', 'dev3-ai-content-layout-builder' ) }
					</Button>
				</div>
			</div>
		</>
	);
}
