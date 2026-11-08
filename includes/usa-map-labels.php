<?php
/**
 * Label placement for the bundled US map (assets/us-states.svg).
 *
 * 'inside'  : states whose abbreviation fits within the shape. Coordinates
 *             are the pole of inaccessibility (the point furthest from any
 *             edge), so the label always sits visually centred and inside -
 *             including concave/multi-part states such as Michigan.
 * 'outside' : small states that cannot hold text. Rendered beside the map
 *             with a leader line back to the state (lx/ly = label,
 *             ax/ay = anchor on the state).
 *
 * Only used when the bundled map is in place ('source_viewbox' matches);
 * a swapped-in SVG falls back to automatic JS placement.
 *
 * @package ClickMap_USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'clickmap_usa_labels' ) ) {
	/**
	 * @return array
	 */
	function clickmap_usa_labels() {
		return array(
			'source_viewbox' => '0 0 650 421',
			'viewbox'        => '0 0 680 421',
			'inside'         => array(
				'AL' => array( 'x' => 451, 'y' => 276 ),
				'AR' => array( 'x' => 393, 'y' => 256 ),
				'AZ' => array( 'x' => 139, 'y' => 284 ),
				'CA' => array( 'x' => 26, 'y' => 137 ),
				'CO' => array( 'x' => 237, 'y' => 211 ),
				'FL' => array( 'x' => 543, 'y' => 378 ),
				'GA' => array( 'x' => 487, 'y' => 273 ),
				'IA' => array( 'x' => 381, 'y' => 153 ),
				'ID' => array( 'x' => 117, 'y' => 93 ),
				'IL' => array( 'x' => 414, 'y' => 162 ),
				'IN' => array( 'x' => 448, 'y' => 195 ),
				'KS' => array( 'x' => 328, 'y' => 213 ),
				'KY' => array( 'x' => 460, 'y' => 222 ),
				'LA' => array( 'x' => 386, 'y' => 308 ),
				'ME' => array( 'x' => 625, 'y' => 49 ),
				'MI' => array( 'x' => 456, 'y' => 115 ),
				'MN' => array( 'x' => 347, 'y' => 110 ),
				'MO' => array( 'x' => 393, 'y' => 226 ),
				'MS' => array( 'x' => 414, 'y' => 311 ),
				'MT' => array( 'x' => 218, 'y' => 72 ),
				'NC' => array( 'x' => 561, 'y' => 239 ),
				'ND' => array( 'x' => 306, 'y' => 75 ),
				'NE' => array( 'x' => 313, 'y' => 166 ),
				'NM' => array( 'x' => 223, 'y' => 251 ),
				'NV' => array( 'x' => 88, 'y' => 190 ),
				'NY' => array( 'x' => 574, 'y' => 96 ),
				'OH' => array( 'x' => 480, 'y' => 186 ),
				'OK' => array( 'x' => 337, 'y' => 270 ),
				'OR' => array( 'x' => 79, 'y' => 81 ),
				'PA' => array( 'x' => 529, 'y' => 157 ),
				'SC' => array( 'x' => 541, 'y' => 264 ),
				'SD' => array( 'x' => 308, 'y' => 120 ),
				'TN' => array( 'x' => 474, 'y' => 244 ),
				'TX' => array( 'x' => 328, 'y' => 321 ),
				'UT' => array( 'x' => 154, 'y' => 202 ),
				'VA' => array( 'x' => 536, 'y' => 214 ),
				'WA' => array( 'x' => 94, 'y' => 35 ),
				'WI' => array( 'x' => 406, 'y' => 126 ),
				'WV' => array( 'x' => 520, 'y' => 185 ),
				'WY' => array( 'x' => 219, 'y' => 146 ),
			),
			'outside'        => array(
				'CT' => array( 'lx' => 648, 'ly' => 156, 'ax' => 602, 'ay' => 127 ),
				'DE' => array( 'lx' => 648, 'ly' => 216, 'ax' => 581, 'ay' => 181 ),
				'MA' => array( 'lx' => 648, 'ly' => 116, 'ax' => 610, 'ay' => 113 ),
				'MD' => array( 'lx' => 648, 'ly' => 196, 'ax' => 563, 'ay' => 173 ),
				'NH' => array( 'lx' => 648, 'ly' => 96, 'ax' => 608, 'ay' => 98 ),
				'NJ' => array( 'lx' => 648, 'ly' => 176, 'ax' => 584, 'ay' => 144 ),
				'RI' => array( 'lx' => 648, 'ly' => 136, 'ax' => 615, 'ay' => 123 ),
				'VT' => array( 'lx' => 586, 'ly' => 46, 'ax' => 593, 'ay' => 86 ),
			),
		);
	}
}
