/**
 * External dependencies
 */
import { keyBy, merge, isEqual } from 'lodash';

/**
 * WordPress dependencies
 */
import { registerStore, select, dispatch } from '@wordpress/data';
import { controls, apiFetch } from '@wordpress/data-controls';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { createReducerFromActionMap } from '../shared/data/store-helpers';

/**
 * Extension statuses.
 */
const STATUS = {
	IN_PROGRESS: 'in-progress',
	IN_QUEUE: 'in-queue',
};

/**
 * Store name.
 */
export const EXTENSIONS_STORE = 'sensei/extensions';

/**
 * Default store state.
 */
const DEFAULT_STATE = {
	// Extensions list to be mapped using the entities.
	extensionSlugs: [],
	entities: { extensions: {} },
	layout: [],
	queue: [],
	wccom: {},
	error: null,
};

/**
 * Checks whether status is a loading status.
 *
 * @param {string} status Status to check.
 *
 * @return {string} Whether is a loading status.
 */
export const isLoadingStatus = ( status ) =>
	[ STATUS.IN_PROGRESS, STATUS.IN_QUEUE ].includes( status );

/**
 * Extension store actions.
 */
const actions = {
	/**
	 * Sets the extensions list.
	 *
	 * @param {Array} extensionSlugs The extensions slugs array.
	 */
	setExtensions( extensionSlugs ) {
		return {
			type: 'SET_EXTENSIONS',
			extensionSlugs,
		};
	},

	/**
	 * Sets entities.
	 *
	 * @param {Object} entities Entities to set.
	 */
	setEntities( entities ) {
		return {
			type: 'SET_ENTITIES',
			entities,
		};
	},

	/**
	 * Install extensions.
	 *
	 * @param {string} slug The extension slug to install.
	 */
	*installExtension( slug ) {
		yield actions.runProcess( { slugs: [ slug ], actionType: 'install' } );
	},

	/**
	 * Updates the provided extensions.
	 *
	 * @param {string[]} slugs The extension slugs to update.
	 */
	*updateExtensions( slugs ) {
		yield actions.runProcess( { slugs, actionType: 'update' } );
	},

	/**
	 * Run extension process (install or update).
	 *
	 * @param {Object}   process            The process.
	 * @param {string[]} process.slugs      Extension slugs.
	 * @param {string}   process.actionType Action type (`install` or `update`).
	 */
	*runProcess( process ) {
		const { slugs, actionType } = process;

		const inProgressExtensions = yield select(
			EXTENSIONS_STORE
		).getExtensionsByStatus( STATUS.IN_PROGRESS );

		// Add to process to queue and skip if a process is already running.
		if ( inProgressExtensions.length > 0 ) {
			yield actions.addToQueue( process );
			return;
		}

		yield actions.setExtensionsStatus( slugs, STATUS.IN_PROGRESS );

		let data;
		let successMessage;
		let errorMessage;

		if ( actionType === 'update' ) {
			data = { plugins: slugs };
			successMessage = __(
				'Update completed succesfully!',
				'sensei-lms'
			);
			// translators: Placeholder is the underlying error message.
			errorMessage = __(
				'There was an error while updating the plugin: %1$s.',
				'sensei-lms'
			);
		} else {
			data = { plugin: slugs[ 0 ] };
			successMessage = __(
				'Installation completed succesfully!',
				'sensei-lms'
			);
			// translators: Placeholder is the underlying error message.
			errorMessage = __(
				'There was an error while installing the plugin: %1$s.',
				'sensei-lms'
			);
		}

		try {
			const response = yield apiFetch( {
				path: `/sensei-internal/v1/sensei-extensions/${ actionType }`,
				method: 'POST',
				data,
			} );

			yield actions.setError( null );
			yield actions.setEntities( {
				extensions: keyBy( response.completed, 'product_slug' ),
			} );

			yield dispatch( 'core/notices' ).createNotice(
				'success',
				successMessage,
				{
					type: 'snackbar',
				}
			);
		} catch ( error ) {
			const serverError = Object.keys( error.errors )
				.map( ( key ) => error.errors[ key ].join( ' ' ) )
				.join( ' ' );

			yield actions.setError( sprintf( errorMessage, serverError ) );
		} finally {
			yield actions.setExtensionsStatus( slugs, '' );
			yield actions.removeFromQueue( process );

			const nextProcess = yield select(
				EXTENSIONS_STORE
			).getNextProcess();

			if ( nextProcess ) {
				yield actions.runProcess( nextProcess );
			}
		}
	},

	/**
	 * Set extensions in progress.
	 *
	 * @param {string} slugs  Extensions in progress.
	 * @param {string} status Status.
	 */
	setExtensionsStatus( slugs, status ) {
		return {
			type: 'SET_EXTENSIONS_STATUS',
			slugs,
			status,
		};
	},

	/**
	 * Set the extensions layout.
	 *
	 * @param {Array} layout Extensions layout.
	 */
	setLayout( layout = [] ) {
		return {
			type: 'SET_LAYOUT',
			layout,
		};
	},

	/**
	 * Set WooCommerce.com data.
	 *
	 * @param {Object} wccom WooCommerce.com data.
	 */
	setWccom( wccom ) {
		return {
			type: 'SET_WCCOM',
			wccom,
		};
	},

	/**
	 * Add process (update/install) to queue.
	 *
	 * @param {Object}   process            The process.
	 * @param {string}   process.actionType Action type.
	 * @param {string[]} process.slugs      Extension slugs.
	 */
	*addToQueue( process ) {
		yield actions.setExtensionsStatus( process.slugs, STATUS.IN_QUEUE );

		return {
			type: 'ADD_TO_QUEUE',
			process,
		};
	},

	/**
	 * Add process (update/install) to queue.
	 *
	 * @param {Object}   process       The process.
	 * @param {string}   process.type  Process type.
	 * @param {string[]} process.slugs Extension slugs.
	 */
	removeFromQueue( process ) {
		return {
			type: 'REMOVE_FROM_QUEUE',
			process,
		};
	},

	/**
	 * Set the error message.
	 *
	 * @param {string} error The error.
	 */
	setError( error ) {
		return {
			type: 'SET_ERROR',
			error,
		};
	},
};

/**
 * Extension store selectors.
 */
const selectors = {
	getExtensions: ( { extensionSlugs, entities } ) =>
		extensionSlugs.map( ( slug ) => entities.extensions[ slug ] ),
	getExtensionsByStatus: ( args, status ) =>
		selectors
			.getExtensions( args )
			.filter( ( extension ) => status === extension.status ),
	getEntities: ( { entities }, entity ) => entities[ entity ],
	getLayout: ( { layout } ) => layout,
	getNextProcess: ( { queue } ) => queue[ 0 ] || null,
	getWccomData: ( { wccom } ) => wccom,
	getError: ( { error } ) => error,
};

/**
 * Extension store resolvers.
 */
const resolvers = {
	/**
	 * Loads the extensions during initialization.
	 */
	*getExtensions() {
		const response = yield apiFetch( {
			path: '/sensei-internal/v1/sensei-extensions?type=plugin',
		} );

		yield actions.setLayout( response.layout );
		yield actions.setWccom( response.wccom );
		yield actions.setEntities( {
			extensions: keyBy( response.extensions, 'product_slug' ),
		} );
		yield actions.setExtensions(
			response.extensions.map( ( extension ) => extension.product_slug )
		);
	},
};

/**
 * Store reducer.
 */
const reducer = {
	SET_EXTENSIONS: ( { extensionSlugs }, state ) => ( {
		...state,
		extensionSlugs,
	} ),
	SET_EXTENSIONS_STATUS: ( { slugs, status }, state ) => ( {
		...state,
		entities: {
			...state.entities,
			extensions: Object.keys( state.entities.extensions ).reduce(
				( acc, slug ) => ( {
					...acc,
					[ slug ]: {
						...state.entities.extensions[ slug ],
						status: slugs.includes( slug )
							? status
							: state.entities.extensions[ slug ].status,
					},
				} ),
				{}
			),
		},
	} ),
	SET_LAYOUT: ( { layout }, state ) => ( {
		...state,
		layout,
	} ),
	SET_ENTITIES: ( { entities }, state ) => ( {
		...state,
		entities: merge( {}, state.entities, entities ),
	} ),
	SET_WCCOM: ( { wccom }, state ) => ( {
		...state,
		wccom,
	} ),
	ADD_TO_QUEUE: ( { process }, state ) => ( {
		...state,
		queue: [ ...state.queue, process ],
	} ),
	REMOVE_FROM_QUEUE: ( { process }, state ) => ( {
		...state,
		queue: state.queue.filter( ( item ) => ! isEqual( item, process ) ),
	} ),
	SET_ERROR: ( { error }, state ) => ( {
		...state,
		error,
	} ),
	DEFAULT: ( action, state ) => state,
};

registerStore( EXTENSIONS_STORE, {
	reducer: createReducerFromActionMap( reducer, DEFAULT_STATE ),
	actions,
	selectors,
	resolvers,
	controls,
} );
