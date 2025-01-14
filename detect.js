const consoleLogPrefix = '[OD Debug Helper]';

/**
 * Logs a message.
 *
 * @since 0.3.0
 *
 * @param {...*} message
 */
function log( ...message ) {
	// eslint-disable-next-line no-console
	console.log( consoleLogPrefix, ...message );
}

const inpData = [];

/**
 * Initializes extension.
 *
 * @since 0.1.0
 */
export async function initialize( { onINP } ) {
	onINP(
		/**
		 * @param {INPMetric|INPMetricWithAttribution} metric
		 */
		( metric ) => {
			if ( 'attribution' in metric ) {
				inpData.push( {
					value: metric.value,
					rating: metric.rating,
					interactionTarget: metric.attribution.interactionTarget,
					xpath: createXPathSelector(
						metric.attribution.interactionTargetElement
					),
				} );
			}
		}
	);
}

// TODO: The resulting selector needs to be compatible with the one in PHP.
function createXPathSelector( element ) {
	if ( ! element || element.nodeType !== Node.ELEMENT_NODE ) {
		throw new Error( 'Invalid element provided.' );
	}

	let path = '';
	let currentElement = element;

	while ( currentElement ) {
		// The `document` global doesn't have a tagName.
		if ( ! currentElement.tagName ) {
			break;
		}

		let elementName = currentElement.tagName;
		let index = 1;
		let siblings = currentElement.parentNode
			? currentElement.parentNode.childNodes
			: [];

		for ( let i = 0; i < siblings.length; i++ ) {
			const sibling = siblings[ i ];
			if ( sibling === currentElement ) {
				break;
			}
			if (
				sibling.nodeType === Node.ELEMENT_NODE &&
				sibling.tagName === elementName
			) {
				index++;
			}
		}

		path = `/${ elementName }[${ index }]${ path }`;
		currentElement = currentElement.parentNode;
	}

	return path;
}

/**
 * Finalizes extension.
 *
 * @since 0.1.0
 */
export async function finalize( { extendRootData, isDebug } ) {
	if ( Object.keys( inpData ).length === 0 ) {
		return;
	}

	if ( isDebug ) {
		log( 'Sending INP data:', inpData );
	}

	extendRootData( { inpData } );
}
