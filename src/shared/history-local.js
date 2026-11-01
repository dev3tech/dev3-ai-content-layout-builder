import config from './config';

const MAX = 50;
const key = () => `dev3cl_history_${ config.userId || 0 }`;

/**
 * Client-side mirror of recent generations, for instant restore without a request.
 * Storage can be unavailable (private mode, blocked site data), so every access is guarded.
 *
 * @return {Array} Entries, newest first.
 */
export function readLocal() {
	try {
		const raw = window.localStorage.getItem( key() );
		const list = raw ? JSON.parse( raw ) : [];
		return Array.isArray( list ) ? list : [];
	} catch ( e ) {
		return [];
	}
}

export function addLocal( entry ) {
	if ( config.historyEnabled === false ) {
		return;
	}
	try {
		const list = readLocal().filter( ( e ) => e.id !== entry.id );
		list.unshift( { ...entry, created_at: entry.created_at || new Date().toISOString() } );
		window.localStorage.setItem( key(), JSON.stringify( list.slice( 0, MAX ) ) );
	} catch ( e ) {
		// Quota exceeded or storage blocked: history still lives on the server.
	}
}

export function clearLocal() {
	try {
		window.localStorage.removeItem( key() );
	} catch ( e ) {}
}
