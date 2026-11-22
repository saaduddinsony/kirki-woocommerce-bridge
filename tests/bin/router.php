<?php
// Router for `php -S`: KWT_ROOT=/path/to/wordpress php -S 127.0.0.1:8895 -t /path/to/wordpress tests/bin/router.php
$root = getenv( 'KWT_ROOT' ) ? getenv( 'KWT_ROOT' ) : __DIR__ . '/site';
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( $path !== '/' && file_exists( $root . $path ) && ! is_dir( $root . $path ) ) {
	return false;
}
if ( is_dir( $root . $path ) && file_exists( $root . rtrim( $path, '/' ) . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	require $root . rtrim( $path, '/' ) . '/index.php';
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
