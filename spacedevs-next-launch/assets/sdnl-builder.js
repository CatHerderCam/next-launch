/**
 * Next Launch shortcode builder.
 *
 * Reads the form fields on the builder screen, assembles a [next_launch]
 * shortcode string, and offers a one-click copy. Every field is optional;
 * an attribute is only written out when it differs from doing nothing
 * (kept blank/default), so the generated shortcode stays short.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var form   = document.querySelector( '.sdnl-builder__form' );
		var output = document.getElementById( 'sdnl-b-output' );

		if ( ! form || ! output ) {
			return;
		}

		var toggles = form.querySelectorAll( '[data-sdnl-toggle]' );
		var fields  = form.querySelectorAll( '[data-sdnl-field]' );

		// Selects start on a real option (e.g. "card"), unlike text inputs which
		// start blank, so a select needs its own initial value to compare against.
		var initialSelectValues = new WeakMap();

		fields.forEach( function ( field ) {
			if ( 'SELECT' === field.tagName ) {
				initialSelectValues.set( field, field.value );
			}
		} );

		function escapeAttr( value ) {
			return String( value ).replace( /"/g, '&quot;' );
		}

		function build() {
			var parts = [];

			fields.forEach( function ( field ) {
				var name  = field.getAttribute( 'data-sdnl-field' );
				var value;

				if ( field.multiple ) {
					value = Array.prototype.map.call( field.selectedOptions, function ( o ) {
						return o.value;
					} ).join( ',' );
				} else {
					value = field.value.trim();
				}

				if ( 'SELECT' === field.tagName && ! field.multiple && value === initialSelectValues.get( field ) ) {
					return;
				}

				if ( '' === value ) {
					return;
				}

				parts.push( name + '="' + escapeAttr( value ) + '"' );
			} );

			toggles.forEach( function ( toggle ) {
				var name    = toggle.getAttribute( 'data-sdnl-toggle' );
				var checked = toggle.checked;
				var isDefault = toggle.defaultChecked;

				if ( checked === isDefault ) {
					return;
				}

				parts.push( name + '="' + ( checked ? 'yes' : 'no' ) + '"' );
			} );

			output.value = parts.length ? '[next_launch ' + parts.join( ' ' ) + ']' : '[next_launch]';
		}

		fields.forEach( function ( field ) {
			field.addEventListener( 'input', build );
			field.addEventListener( 'change', build );
		} );

		toggles.forEach( function ( toggle ) {
			toggle.addEventListener( 'change', build );
		} );

		build();

		var copyButton = document.getElementById( 'sdnl-b-copy' );
		var copyStatus = document.getElementById( 'sdnl-b-copy-status' );
		var strings    = ( window.sdnlBuilder && window.sdnlBuilder.strings ) || {};

		if ( copyButton ) {
			copyButton.addEventListener( 'click', function () {
				output.select();

				var done = function () {
					if ( copyStatus ) {
						copyStatus.textContent = strings.copied || 'Copied!';
						window.setTimeout( function () {
							copyStatus.textContent = '';
						}, 2000 );
					}
				};

				var fallback = function () {
					document.execCommand( 'copy' );
					done();
				};

				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( output.value ).then( done, fallback );
				} else {
					fallback();
				}
			} );
		}

		// Location picker: a multi-select kept in sync with the launch-site API,
		// pre-populated with the most active sites and refined by the search box.
		var searchInput    = document.getElementById( 'sdnl-b-loc-search' );
		var locationSelect = document.getElementById( 'sdnl-b-location' );

		if ( ! searchInput || ! locationSelect || ! window.sdnlBuilder ) {
			return;
		}

		function selectedEntries() {
			return Array.prototype.filter.call( locationSelect.options, function ( o ) {
				return o.selected;
			} ).map( function ( o ) {
				return { id: o.value, label: o.textContent };
			} );
		}

		function setLoading( message ) {
			locationSelect.innerHTML = '';

			var opt = document.createElement( 'option' );
			opt.disabled = true;
			opt.textContent = message;
			locationSelect.appendChild( opt );
		}

		function renderOptions( list, kept, hint ) {
			var keptIds   = kept.map( function ( item ) { return item.id; } );
			var listIds   = list.map( function ( item ) { return String( item.id ); } );
			var stillKept = kept.filter( function ( item ) { return -1 === listIds.indexOf( item.id ); } );

			locationSelect.innerHTML = '';

			if ( hint && ! list.length ) {
				var hintOpt = document.createElement( 'option' );
				hintOpt.disabled = true;
				hintOpt.textContent = hint;
				locationSelect.appendChild( hintOpt );
			}

			stillKept.concat( list.map( function ( item ) {
				return {
					id: String( item.id ),
					label: item.name + ( item.country ? ' (' + item.country + ')' : '' )
				};
			} ) ).forEach( function ( item ) {
				var opt = document.createElement( 'option' );
				opt.value = item.id;
				opt.textContent = item.label;
				opt.selected = -1 !== keptIds.indexOf( item.id );
				locationSelect.appendChild( opt );
			} );

			build();
		}

		function fetchLocations( action, term ) {
			// Capture the current selection before setLoading() wipes the <select>,
			// so it can be restored once the new option list is ready.
			var kept = selectedEntries();

			setLoading( strings.loading || 'Loading launch sites…' );

			var body = new URLSearchParams();
			body.append( 'action', action );
			body.append( 'nonce', window.sdnlBuilder.nonce );

			if ( term ) {
				body.append( 'term', term );
			}

			fetch( window.sdnlBuilder.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					var message = ( payload && payload.data && payload.data.message ) || strings.failed || 'Could not load launch sites.';
					renderOptions( [], kept, message );
					return;
				}

				var list = payload.data.locations || [];

				renderOptions( list, kept, list.length ? '' : ( strings.noResults || 'No matching launch sites.' ) );
			} ).catch( function () {
				renderOptions( [], kept, strings.failed || 'Could not load launch sites.' );
			} );
		}

		var searchTimer = null;

		searchInput.addEventListener( 'input', function () {
			window.clearTimeout( searchTimer );

			searchTimer = window.setTimeout( function () {
				var term = searchInput.value.trim();

				fetchLocations( term ? 'sdnl_search_locations' : 'sdnl_popular_locations', term );
			}, 300 );
		} );

		fetchLocations( 'sdnl_popular_locations' );
	} );
} )();
