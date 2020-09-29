import { dispatch } from "@wordpress/data";

let editorData = {
	content: "",
	title: "",
	excerpt: "",
};

/**
 * Gets the post content.
 *
 * @returns {string} The post's content.
 */
function getContent() {
	const content = [];

	window.elementor.$preview.contents().find( "[data-elementor-type]" ).find( ".elementor-widget-container" ).each( ( index, element ) => {
		content.push( element.innerHTML.trim() );
	} );

	return content.join( "" );
}

/**
 * Checks whether the current data and the incoming data are the same.
 *
 * @param {Object} currentData The current data.
 * @param {Object} newData     The incoming data.
 *
 * @returns {boolean} Whether the current data and the incoming data is the same or not.
 */
function isShallowEqual( currentData, newData ) {
	if ( Object.keys( currentData ).length !== Object.keys( newData ).length ) {
		return false;
	}

	for ( const dataPoint in currentData ) {
		if ( currentData.hasOwnProperty( dataPoint ) ) {
			if ( ! ( dataPoint in newData ) || currentData[ dataPoint ] !== newData[ dataPoint ] ) {
				return false;
			}
		}
	}
	return true;
}

/**
 * Gets the data that is specific to this editor.
 *
 * @returns {Object} The editorData object.
 */
function getEditorData() {
	return {
		content: getContent(),
		title: window.elementor.settings.page.model.get( "post_title" ),
		excerpt: window.elementor.settings.page.model.get( "post_excerpt" ),
	};
}

/**
 * Dispatches new data when the editor is dirty.
 *
 * @returns {void}
 */
function handleEditorChange() {
	const data = getEditorData();

	// Set isDirty to true if the current data and Gutenberg data are unequal.
	const isDirty = ! isShallowEqual( editorData, data );

	if ( isDirty ) {
		editorData = data;
		dispatch( "yoast-seo/editor" ).updateEditorData( editorData );
	}
}

/**
 * Initializes the watcher by coupling the change handler to the change event.
 *
 * @returns {void}
 */
export default function initialize() {
	// This function relies on `window.elementor`. This should be available after the content is loaded.
	document.addEventListener( "DOMContentLoaded", () => {
		// Initialize Elementor data one time after the preview is available.
		window.elementor.once( "preview:loaded", () => {
			window.elementorFrontend.hooks.addAction( "frontend/element_ready/global", () => {
				handleEditorChange();
				window.elementorFrontend.hooks.removeAction( "frontend/element_ready/global" );
			} );
		} );

		// Subscribe to Elementor change.
		window.elementor.channels.editor.on( "status:change", handleEditorChange );
	} );
}