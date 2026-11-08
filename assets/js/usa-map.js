/**
 * ClickMap USA — front-end behaviour.
 *
 * - Hover/focus a state → show its name in the tooltip (positioned to the cursor).
 * - Click/Enter/Space on a state → navigate (if it's a link) or fire a
 *   `clickmap:stateClick` event you can hook into with your own JS.
 * - Auto-places optional abbreviation labels at each state's visual centre.
 *
 * Works in the Elementor editor and on the live site. No dependencies.
 */
( function () {
	'use strict';

	function initMap( map ) {
		if ( ! map || map.dataset.clickmapInit === '1' ) {
			return;
		}
		map.dataset.clickmapInit = '1';

		var svg       = map.querySelector( '.clickmap-usa-map-svg' );
		var tooltip   = map.querySelector( '.clickmap-tooltip' );
		var useToolt  = map.getAttribute( 'data-tooltip' ) === '1' && tooltip;
		var states    = map.querySelectorAll( '.clickmap-state' );

		if ( ! svg ) {
			return;
		}

		/* ----- Tooltip ----- */
		function showTooltip( name, evt ) {
			if ( ! useToolt || ! name ) {
				return;
			}
			tooltip.textContent = name;
			tooltip.classList.add( 'is-visible' );
			tooltip.setAttribute( 'aria-hidden', 'false' );
			moveTooltip( evt );
		}

		function moveTooltip( evt ) {
			if ( ! useToolt ) {
				return;
			}
			var rect = map.getBoundingClientRect();
			var x, y;
			if ( evt && typeof evt.clientX === 'number' ) {
				x = evt.clientX - rect.left;
				y = evt.clientY - rect.top;
			} else if ( evt && evt.target && evt.target.getBoundingClientRect ) {
				// Keyboard focus: anchor to the shape centre.
				var b = evt.target.getBoundingClientRect();
				x = b.left + b.width / 2 - rect.left;
				y = b.top - rect.top;
			} else {
				return;
			}
			tooltip.style.left = x + 'px';
			tooltip.style.top  = y + 'px';
		}

		function hideTooltip() {
			if ( ! useToolt ) {
				return;
			}
			tooltip.classList.remove( 'is-visible' );
			tooltip.setAttribute( 'aria-hidden', 'true' );
		}

		/* ----- Per-state wiring ----- */
		Array.prototype.forEach.call( states, function ( shape ) {
			var name  = shape.getAttribute( 'data-name' ) || '';
			var abbr  = shape.getAttribute( 'data-state' ) || '';
			var group = shape.closest( '.clickmap-state-link' );

			shape.addEventListener( 'mouseenter', function ( e ) {
				showTooltip( name, e );
			} );
			shape.addEventListener( 'mousemove', moveTooltip );
			shape.addEventListener( 'mouseleave', hideTooltip );

			if ( group ) {
				group.addEventListener( 'focus', function ( e ) {
					showTooltip( name, { target: shape } );
				} );
				group.addEventListener( 'blur', hideTooltip );

				// Keyboard activation for non-link (group) states.
				if ( group.classList.contains( 'clickmap-state-nolink' ) ) {
					group.addEventListener( 'keydown', function ( e ) {
						if ( e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar' ) {
							e.preventDefault();
							fireClick( abbr, name, shape );
						}
					} );
					group.addEventListener( 'click', function () {
						fireClick( abbr, name, shape );
					} );
				}
			}
		} );

		/* Dispatch a custom event others can listen for. */
		function fireClick( abbr, name, shape ) {
			var detail = { state: abbr, name: name, element: shape };
			map.dispatchEvent(
				new CustomEvent( 'clickmap:stateClick', { detail: detail, bubbles: true } )
			);
		}

		/* ----- Auto-place abbreviation labels at centroids ----- */
		placeLabels( map, svg );
	}

	/**
	 * Position each .clickmap-state-label at the bounding-box centre of its
	 * matching state shape, in SVG user units.
	 */
	function placeLabels( map, svg ) {
		var labels = map.querySelectorAll( '.clickmap-state-label' );
		if ( ! labels.length ) {
			return;
		}
		Array.prototype.forEach.call( labels, function ( label ) {
			// Labels rendered with explicit coordinates (the bundled map) are
			// already positioned precisely server-side — leave them alone.
			if ( label.hasAttribute( 'x' ) && label.hasAttribute( 'y' ) ) {
				return;
			}
			var abbr  = label.getAttribute( 'data-label-for' );
			var shape = map.querySelector( '.clickmap-state[data-state="' + abbr + '"]' );
			if ( ! shape || typeof shape.getBBox !== 'function' ) {
				return;
			}
			try {
				var box = shape.getBBox();
				label.setAttribute( 'x', box.x + box.width / 2 );
				label.setAttribute( 'y', box.y + box.height / 2 );
			} catch ( err ) {
				/* getBBox can throw if not yet rendered; ignore. */
			}
		} );
	}

	function initAll( root ) {
		var scope = root || document;
		var maps  = scope.querySelectorAll( '.clickmap-usa-map' );
		Array.prototype.forEach.call( maps, initMap );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll();
		} );
	} else {
		initAll();
	}

	// Elementor editor preview support.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', function () {
			if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/global',
					function ( $scope ) {
						initAll( $scope[ 0 ] );
					}
				);
			}
		} );
	}
} )();
