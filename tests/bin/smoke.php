<?php
// REST smoke test: wp eval-file tests/bin/smoke.php (exits 1 if any element fails to render).
wp_set_current_user( 1 );
define( 'REST_REQUEST', true );
$kwt_tee  = get_page_by_path( 'classic-tee', OBJECT, 'product' )->ID;
$kwt_sale = get_page_by_path( 'vintage-tee', OBJECT, 'product' )->ID;
function call( $m, $r, $body = null ) {
	$req = new WP_REST_Request( $m, '/kirki-woo/v1' . $r );
	if ( $body ) { $req->set_header( 'content-type', 'application/json' ); $req->set_body( wp_json_encode( $body ) ); }
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
}
[$s,$d] = call( 'GET', '/status' ); echo "status $s ", wp_json_encode( $d ), "\n";
[$s,$d] = call( 'GET', '/elements' ); echo "elements $s ", count( $d['elements'] ), "\n";
$bad = 0;
foreach ( $d['elements'] as $el ) {
	[$s2,$r] = call( 'POST', '/render', array( 'name' => $el['name'], 'product_id' => $kwt_tee ) );
	$len = isset( $r['html'] ) ? strlen( $r['html'] ) : -1;
	$ph  = isset( $r['html'] ) && false !== strpos( $r['html'], 'kwi-placeholder' );
	if ( 200 !== $s2 || $len < 40 ) { $bad++; }
	printf( "%-36s %d %6d %s\n", $el['name'], $s2, $len, $ph ? 'PLACEHOLDER' : ( 200 !== $s2 ? wp_json_encode( $r ) : '' ) );
}
echo "bad=$bad\n";
if ( $bad ) { exit( 1 ); }
foreach ( array( array('GET','/dynamic-data/fields'), array('GET','/settings'), array('GET','/templates/contexts'), array('GET','/templates'), array('GET','/designs') ) as $c ) {
	[$s,$d] = call( $c[0], $c[1] ); echo $c[1], " $s ", strlen( wp_json_encode( $d ) ), "\n";
}
[$s,$d] = call( 'POST', '/dynamic-data/resolve', array( 'field' => 'price_html', 'product_id' => $kwt_sale ) ); echo "resolve $s ", wp_json_encode( $d ), "\n";
[$s,$d] = call( 'POST', '/dynamic-data/resolve', array( 'field' => 'cart_count', 'scope' => 'store' ) ); echo "resolve store $s ", wp_json_encode( $d ), "\n";
[$s,$d] = call( 'POST', '/blocks', array( 'name' => 'products', 'settings' => array( 'columns' => 4 ) ) ); echo "block $s ", wp_json_encode( $d ), "\n";
[$s,$d] = call( 'POST', '/blocks', array( 'name' => 'products', 'settings' => array( 'columns' => 99, 'nope' => 1 ) ) ); echo "block bad $s ", wp_json_encode( $d ), "\n";
[$s,$d] = call( 'PUT', '/settings', array( 'settings' => array( 'bogus' => 1 ) ) ); echo "settings bad $s ", wp_json_encode( $d ), "\n";
if ( function_exists( 'wp_get_abilities' ) ) { $a = array_filter( array_keys( wp_get_abilities() ), fn( $n ) => 0 === strpos( $n, 'kirki-woo/' ) ); echo 'abilities ', count( $a ), ' ', implode( ',', $a ), "\n"; }
else { echo "no wp_get_abilities\n"; }
wp_set_current_user( 0 );
[$s,$d] = call( 'GET', '/elements' ); echo "anon elements $s\n";
