import { useEffect, useState } from '@wordpress/element';
import { request } from '../shared/api';

let cache = null;

/**
 * Template list, cached for the page lifetime (the server caches it in a transient too).
 *
 * @return {Array} Templates.
 */
export default function useTemplates() {
	const [ templates, setTemplates ] = useState( cache || [] );
	useEffect( () => {
		if ( cache ) {
			return;
		}
		request( 'templates' )
			.then( ( list ) => {
				cache = list;
				setTemplates( list );
			} )
			.catch( () => {} );
	}, [] );
	return templates;
}

export function invalidateTemplates() {
	cache = null;
}
