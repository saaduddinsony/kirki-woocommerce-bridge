<?php
/**
 * Class autoloader for the KirkiWoo namespace.
 *
 * Maps KirkiWoo\Some_Class to includes/class-some-class.php.
 *
 * @package KirkiWoo
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'KirkiWoo\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
