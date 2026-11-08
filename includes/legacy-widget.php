<?php
/**
 * Backwards-compatibility widget alias.
 *
 * Elementor stores a widget's type name inside the page data. This plugin was
 * previously registered as `medcards_usa_map`, so any page built with that
 * build would render "widget not found" after the rename.
 *
 * This alias re-registers the same widget under the old name and hides it from
 * the widget panel, so existing pages keep working while new pages use
 * `clickmap_usa_map`. Safe to delete if no page uses the old widget.
 *
 * @package ClickMap_USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy alias of ClickMap_USA_Widget.
 */
class ClickMap_USA_Widget_Legacy extends ClickMap_USA_Widget {

	/**
	 * Previous widget name.
	 */
	public function get_name() {
		return 'medcards_usa_map';
	}

	/**
	 * Hide from the widget panel: new pages should use the renamed widget.
	 */
	public function show_in_panel() {
		return false;
	}
}
