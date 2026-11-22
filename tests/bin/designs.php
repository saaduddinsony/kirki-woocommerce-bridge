<?php
// Create a starter design + rule for every WooCommerce context: wp eval-file tests/bin/designs.php
wp_set_current_user( 1 );
foreach ( array( 'single_product', 'shop', 'product_archive', 'cart', 'cart_empty', 'checkout', 'my_account', 'my_account_login' ) as $ctx ) {
	$r = KirkiWoo\Api::create_design( array( 'context' => $ctx ) );
	if ( is_wp_error( $r ) ) { echo "$ctx ERROR ", $r->get_error_message(), "\n"; continue; }
	echo "$ctx design ", $r['design']['id'], " rule ", $r['rule']['id'], " ", $r['design']['editor_url'], "\n";
}
