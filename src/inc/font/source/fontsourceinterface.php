<?php
/**
 * @package Make
 */


interface MAKE_Font_Source_FontSourceInterface {
	public function get_label();

	public function get_priority();

	public function get_font_data( $font = null );

	public function get_font_choices();

	public function get_font_stack( $font, $default_stack = 'sans-serif' );
}