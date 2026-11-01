import { __ } from '@wordpress/i18n';

/**
 * Wireframe preview of a normalised layout tree.
 *
 * @param {Object} props      Props.
 * @param {Object} props.tree Tree.
 */
export default function LayoutPreview( { tree } ) {
	if ( ! tree || ! tree.sections ) {
		return null;
	}
	return (
		<div className="dev3cl-output" style={ { maxHeight: 420, background: '#fff' } }>
			{ tree.title && <div className="dev3cl-text-xs dev3cl-text-gray-500 dev3cl-mb-2">{ tree.title }</div> }
			{ tree.sections.map( ( section, si ) => (
				<div key={ si } className={ 'dev3cl-lp-section' + ( section.background !== 'none' ? ' is-' + section.background : '' ) }>
					{ section.name && <div className="dev3cl-text-xs dev3cl-mb-2" style={ { opacity: 0.6 } }>{ section.name }</div> }
					<div className="dev3cl-lp-cols">
						{ section.columns.map( ( col, ci ) => (
							<div key={ ci } className={ 'dev3cl-lp-col' + ( col.featured ? ' is-featured' : '' ) } style={ { flex: `1 1 calc(${ col.width }% - 10px)` } }>
								{ col.widgets.map( ( w, wi ) => (
									<Widget key={ wi } w={ w } />
								) ) }
							</div>
						) ) }
					</div>
				</div>
			) ) }
		</div>
	);
}

function Widget( { w } ) {
	const style = w.align ? { textAlign: w.align } : undefined;
	switch ( w.type ) {
		case 'heading':
			return <div className="dev3cl-lp-w" style={ { ...style, fontWeight: 700, fontSize: w.level === 'h1' ? 18 : w.level === 'h2' ? 16 : 14 } }>{ w.text }</div>;
		case 'text':
			return <div className="dev3cl-lp-w" style={ style } dangerouslySetInnerHTML={ { __html: w.html || w.text } } />;
		case 'button':
			return (
				<div className="dev3cl-lp-w" style={ style }>
					<span className={ 'dev3cl-lp-btn' + ( w.style === 'secondary' ? ' is-secondary' : '' ) }>{ w.text }</span>
				</div>
			);
		case 'image':
			return <div className="dev3cl-lp-w dev3cl-lp-img">🖼 { w.image_alt || __( 'Image', 'dev3-ai-content-layout-builder' ) }</div>;
		case 'icon_list':
			return (
				<ul className="dev3cl-lp-w" style={ { margin: 0, paddingLeft: 16 } }>
					{ w.items.map( ( i, k ) => (
						<li key={ k }>{ i.title ? <strong>{ i.title } </strong> : null }<span dangerouslySetInnerHTML={ { __html: i.text } } /></li>
					) ) }
				</ul>
			);
		case 'icon_box': {
			const item = w.items[ 0 ] || { title: w.text, text: w.html };
			return (
				<div className="dev3cl-lp-w" style={ { textAlign: w.align || 'center' } }>
					<div style={ { color: '#4f46e5' } }>●</div>
					<strong>{ item.title || w.text }</strong>
					<div dangerouslySetInnerHTML={ { __html: item.text || w.html } } />
				</div>
			);
		}
		case 'price': {
			const p = w.price;
			return (
				<div className="dev3cl-lp-w" style={ { textAlign: 'center' } }>
					{ p.featured && <div style={ { color: '#4f46e5', fontWeight: 700 } }>{ __( 'Most popular', 'dev3-ai-content-layout-builder' ) }</div> }
					<strong>{ p.plan }</strong>
					<div style={ { fontSize: 20, fontWeight: 700 } }>
						{ p.currency }
						{ p.amount } <span style={ { fontSize: 11, fontWeight: 400 } }>{ p.period }</span>
					</div>
					{ p.amount_alt && (
						<div style={ { fontSize: 11, opacity: 0.7 } }>
							{ p.currency }
							{ p.amount_alt } { p.period_alt }
						</div>
					) }
					<ul style={ { textAlign: 'left', margin: '6px 0', paddingLeft: 16 } }>
						{ p.features.map( ( f, k ) => (
							<li key={ k }>{ f }</li>
						) ) }
					</ul>
					{ p.button && <span className={ 'dev3cl-lp-btn' + ( p.featured ? '' : ' is-secondary' ) }>{ p.button }</span> }
				</div>
			);
		}
		case 'period_toggle':
			return (
				<div className="dev3cl-lp-w" style={ { textAlign: 'center' } }>
					{ ( w.labels.length ? w.labels : [ 'Monthly', 'Yearly' ] ).map( ( l, k ) => (
						<span key={ k } className={ 'dev3cl-lp-btn' + ( k ? ' is-secondary' : '' ) } style={ { margin: 2, borderRadius: 999 } }>
							{ l }
						</span>
					) ) }
				</div>
			);
		case 'accordion':
		case 'tabs':
			return (
				<div className="dev3cl-lp-w">
					{ w.items.map( ( i, k ) => (
						<div key={ k } style={ { borderBottom: '1px solid rgba(0,0,0,.08)', padding: '3px 0' } }>
							{ w.type === 'accordion' ? '▸ ' : '▭ ' }
							{ i.title }
						</div>
					) ) }
				</div>
			);
		case 'testimonial':
			return (
				<div className="dev3cl-lp-w" style={ { fontStyle: 'italic', textAlign: w.align || 'center' } }>
					“{ w.text }” <div style={ { fontStyle: 'normal', fontWeight: 600 } }>— { w.author }{ w.role ? `, ${ w.role }` : '' }</div>
				</div>
			);
		case 'counter':
			return (
				<div className="dev3cl-lp-w" style={ { textAlign: 'center' } }>
					<div style={ { fontSize: 20, fontWeight: 700 } }>
						{ w.number }
						{ w.suffix }
					</div>
					{ w.text }
				</div>
			);
		case 'divider':
			return <hr className="dev3cl-lp-w" />;
		case 'spacer':
			return <div style={ { height: 14 } } />;
		case 'html':
			return <div className="dev3cl-lp-w" style={ { fontFamily: 'monospace', opacity: 0.7 } }>&lt;html&gt;</div>;
	}
	return null;
}
