/**
 * Next Rocket Launch Tracker front-end behaviour.
 *
 * Two jobs: tick the countdowns, and rewrite launch times into the visitor's
 * own timezone where the shortcode asked for it. No dependencies.
 */
( function () {
	'use strict';

	var TICK = 1000;

	function pad( value ) {
		return value < 10 ? '0' + value : String( value );
	}

	function formatRemaining( seconds ) {
		var sign = seconds < 0 ? 'T+' : 'T-';
		var abs = Math.abs( seconds );

		var days = Math.floor( abs / 86400 );
		var hours = Math.floor( ( abs % 86400 ) / 3600 );
		var minutes = Math.floor( ( abs % 3600 ) / 60 );
		var secs = Math.floor( abs % 60 );

		var clock = pad( hours ) + ':' + pad( minutes ) + ':' + pad( secs );

		if ( days > 0 ) {
			return sign + ' ' + days + 'd ' + clock;
		}

		return sign + ' ' + clock;
	}

	function collectCountdowns() {
		var nodes = document.querySelectorAll( '[data-sdnl-countdown]' );
		var items = [];

		Array.prototype.forEach.call( nodes, function ( node ) {
			var raw = node.getAttribute( 'data-net' );

			if ( ! raw ) {
				return;
			}

			var target = Date.parse( raw );

			if ( isNaN( target ) ) {
				return;
			}

			var output = node.querySelector( '.sdnl__countdown-value' ) || node;

			items.push( { target: target, output: output } );
		} );

		return items;
	}

	function localizeTimes() {
		var nodes = document.querySelectorAll( '[data-sdnl-localtime]' );

		Array.prototype.forEach.call( nodes, function ( node ) {
			var raw = node.getAttribute( 'datetime' );

			if ( ! raw ) {
				return;
			}

			var when = new Date( raw );

			if ( isNaN( when.getTime() ) ) {
				return;
			}

			try {
				node.textContent = when.toLocaleString( undefined, {
					weekday: 'short',
					year: 'numeric',
					month: 'short',
					day: 'numeric',
					hour: 'numeric',
					minute: '2-digit',
					timeZoneName: 'short'
				} );
			} catch ( error ) {
				// Older browser without full Intl support. Leave the
				// server-rendered time in place.
			}
		} );
	}

	function initSliders() {
		var widgets = document.querySelectorAll( '.sdnl--slider' );

		Array.prototype.forEach.call( widgets, function ( widget ) {
			var list = widget.querySelector( '.sdnl__list' );
			var prev = widget.querySelector( '.sdnl__arrow--prev' );
			var next = widget.querySelector( '.sdnl__arrow--next' );

			if ( ! list || ! prev || ! next || ! list.children.length ) {
				return;
			}

			var index = 0;

			function show( target ) {
				index = ( target + list.children.length ) % list.children.length;
				list.style.transform = 'translateX(-' + ( index * 100 ) + '%)';
			}

			prev.addEventListener( 'click', function () {
				show( index - 1 );
			} );

			next.addEventListener( 'click', function () {
				show( index + 1 );
			} );
		} );
	}

	function start() {
		localizeTimes();
		initSliders();

		var items = collectCountdowns();

		if ( ! items.length ) {
			return;
		}

		function tick() {
			var now = Date.now();

			items.forEach( function ( item ) {
				item.output.textContent = formatRemaining( Math.round( ( item.target - now ) / 1000 ) );
			} );
		}

		tick();
		window.setInterval( tick, TICK );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
