/**
 * Next Rocket Launch Tracker settings screen.
 *
 * Wires up the location search box on Settings > Next Rocket Launch Tracker.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var input   = document.getElementById( 'nextrlt-loc-search' );
		var button  = document.getElementById( 'nextrlt-loc-go' );
		var results = document.getElementById( 'nextrlt-loc-results' );

		if ( ! input || ! button || ! results || ! window.nextrltAdmin ) {
			return;
		}

		var strings = window.nextrltAdmin.strings || {};

		function escapeHtml( value ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( String( value ) ) );
			return div.innerHTML;
		}

		function search() {
			var term = input.value.trim();

			if ( ! term ) {
				return;
			}

			results.textContent = strings.searching || 'Searching…';

			var body = new URLSearchParams();
			body.append( 'action', 'nextrlt_search_locations' );
			body.append( 'nonce', window.nextrltAdmin.nonce );
			body.append( 'term', term );

			fetch( window.nextrltAdmin.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			} ).then( function ( response ) {
				return response.json();
			} ).then( function ( payload ) {
				if ( ! payload || ! payload.success ) {
					results.textContent = ( payload && payload.data && payload.data.message )
						? payload.data.message
						: ( strings.failed || 'Search failed.' );
					return;
				}

				var list = payload.data.locations || [];

				if ( ! list.length ) {
					results.textContent = strings.noResults || 'No matching launch sites.';
					return;
				}

				var html = '<table class="widefat striped" style="max-width:640px"><thead><tr>' +
					'<th style="width:80px">' + escapeHtml( strings.id || 'ID' ) + '</th>' +
					'<th>' + escapeHtml( strings.launchSite || 'Launch site' ) + '</th>' +
					'<th style="width:90px">' + escapeHtml( strings.country || 'Country' ) + '</th>' +
					'</tr></thead><tbody>';

				list.forEach( function ( item ) {
					html += '<tr><td><code>' + escapeHtml( item.id ) + '</code></td><td>' +
						escapeHtml( item.name ) + '</td><td>' +
						escapeHtml( item.country ) + '</td></tr>';
				} );

				html += '</tbody></table>';
				results.innerHTML = html;
			} ).catch( function () {
				results.textContent = strings.failed || 'Search failed.';
			} );
		}

		button.addEventListener( 'click', search );

		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
				search();
			}
		} );
	} );
} )();
