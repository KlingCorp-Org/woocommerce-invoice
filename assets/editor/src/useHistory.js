/**
 * Undo/redo history for the editor document ({ layout, style }).
 *
 * Consecutive changes with the same merge key (e.g. typing in one field)
 * within a short time collapse into one undo step.
 */
import { useCallback, useRef, useState } from '@wordpress/element';

const LIMIT = 100;
const MERGE_MS = 800;

export default function useHistory( initial = null ) {
	const [ state, setState ] = useState( {
		past: [],
		present: initial,
		future: [],
	} );
	const last = useRef( { key: null, time: 0 } );

	const set = useCallback( ( updater, mergeKey = null ) => {
		const now = Date.now();
		const merge =
			mergeKey &&
			mergeKey === last.current.key &&
			now - last.current.time < MERGE_MS;
		last.current = { key: mergeKey, time: now };
		setState( ( s ) => {
			const present =
				typeof updater === 'function' ? updater( s.present ) : updater;
			if ( present === s.present ) {
				return s;
			}
			if ( merge ) {
				return { ...s, present, future: [] };
			}
			return {
				past: [ ...s.past, s.present ].slice( -LIMIT ),
				present,
				future: [],
			};
		} );
	}, [] );

	const reset = useCallback( ( present ) => {
		last.current = { key: null, time: 0 };
		setState( { past: [], present, future: [] } );
	}, [] );

	const undo = useCallback( () => {
		last.current = { key: null, time: 0 };
		setState( ( s ) =>
			s.past.length
				? {
						past: s.past.slice( 0, -1 ),
						present: s.past[ s.past.length - 1 ],
						future: [ s.present, ...s.future ],
				  }
				: s
		);
	}, [] );

	const redo = useCallback( () => {
		last.current = { key: null, time: 0 };
		setState( ( s ) =>
			s.future.length
				? {
						past: [ ...s.past, s.present ],
						present: s.future[ 0 ],
						future: s.future.slice( 1 ),
				  }
				: s
		);
	}, [] );

	return {
		present: state.present,
		set,
		reset,
		undo,
		redo,
		canUndo: state.past.length > 0,
		canRedo: state.future.length > 0,
	};
}
