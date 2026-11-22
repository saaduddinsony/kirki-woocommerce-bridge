<?php
// Seed sample store data for local testing: wp eval-file tests/bin/seed.php
// Never run this against a real store.
update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );
update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
update_option( 'woocommerce_currency', 'USD' );
update_option( 'woocommerce_default_country', 'US:CA' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_reviews', 'yes' );
foreach ( array( 'cod', 'cheque' ) as $gw ) {
	$opt = get_option( "woocommerce_{$gw}_settings", array() );
	$opt['enabled'] = 'yes';
	update_option( "woocommerce_{$gw}_settings", $opt );
}

function kwt_image( $name, $rgb ) {
	$upload = wp_upload_dir();
	$file   = $upload['path'] . '/' . sanitize_title( $name ) . '.png';
	$im = imagecreatetruecolor( 800, 800 );
	$bg = imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] );
	imagefill( $im, 0, 0, $bg );
	$fg = imagecolorallocate( $im, 255, 255, 255 );
	imagefilledellipse( $im, 400, 400, 420, 420, imagecolorallocatealpha( $im, 255, 255, 255, 90 ) );
	imagestring( $im, 5, 330, 390, strtoupper( substr( $name, 0, 14 ) ), $fg );
	imagepng( $im, $file );
	imagedestroy( $im );
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $name, 'post_status' => 'inherit' ), $file );
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return $id;
}
function kwt_term( $name, $tax, $parent = 0 ) {
	$t = term_exists( $name, $tax );
	if ( ! $t ) { $t = wp_insert_term( $name, $tax, array( 'parent' => $parent ) ); }
	return (int) $t['term_id'];
}
$clothing = kwt_term( 'Clothing', 'product_cat' );
$tshirts  = kwt_term( 'T-shirts', 'product_cat', $clothing );
$hoodies  = kwt_term( 'Hoodies', 'product_cat', $clothing );
$acc      = kwt_term( 'Accessories', 'product_cat' );
$tag_new  = kwt_term( 'New', 'product_tag' );

// Global attributes.
foreach ( array( 'color' => array( 'Red', 'Blue', 'Green' ), 'size' => array( 'Small', 'Medium', 'Large' ) ) as $slug => $terms ) {
	if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
		wc_create_attribute( array( 'name' => ucfirst( $slug ), 'slug' => $slug, 'type' => 'select', 'has_archives' => true ) );
	}
	register_taxonomy( 'pa_' . $slug, 'product' );
	foreach ( $terms as $t ) { kwt_term( $t, 'pa_' . $slug ); }
}

$simple = array(
	array( 'Classic Tee', 20, null, array( $tshirts ), array( 220, 38, 38 ), true, 'red' ),
	array( 'Vintage Tee', 25, 18, array( $tshirts ), array( 37, 99, 235 ), false, 'blue' ),
	array( 'Zip Hoodie', 55, null, array( $hoodies ), array( 22, 163, 74 ), true, 'green' ),
	array( 'Pullover Hoodie', 60, 45, array( $hoodies ), array( 124, 58, 237 ), false, 'blue' ),
	array( 'Canvas Cap', 18, null, array( $acc ), array( 234, 88, 12 ), false, 'red' ),
	array( 'Leather Belt', 35, null, array( $acc ), array( 120, 53, 15 ), false, 'green' ),
	array( 'Wool Beanie', 22, 15, array( $acc ), array( 15, 118, 110 ), true, 'blue' ),
	array( 'Sunglasses', 90, null, array( $acc ), array( 17, 24, 39 ), false, 'red' ),
);
$ids = array();
foreach ( $simple as $i => $s ) {
	$p = new WC_Product_Simple();
	$p->set_name( $s[0] );
	$p->set_status( 'publish' );
	$p->set_regular_price( (string) $s[1] );
	if ( $s[2] ) { $p->set_sale_price( (string) $s[2] ); }
	$p->set_category_ids( $s[3] );
	$p->set_featured( $s[5] );
	$p->set_sku( 'KWT-' . ( 100 + $i ) );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( 0 === $i % 4 ? 3 : 50 );
	$p->set_short_description( 'A short description of the ' . $s[0] . '.' );
	$p->set_description( '<p>Full description for the ' . $s[0] . '. Made with care.</p>' );
	$p->set_image_id( kwt_image( $s[0], $s[4] ) );
	$p->set_gallery_image_ids( array( kwt_image( $s[0] . ' alt', array_reverse( $s[4] ) ) ) );
	if ( 0 === $i % 2 ) { $p->set_tag_ids( array( $tag_new ) ); }
	$attr = new WC_Product_Attribute();
	$attr->set_id( wc_attribute_taxonomy_id_by_name( 'color' ) );
	$attr->set_name( 'pa_color' );
	$attr->set_options( array( get_term_by( 'slug', $s[6], 'pa_color' )->term_id ) );
	$attr->set_visible( true );
	$p->set_attributes( array( $attr ) );
	$ids[] = $p->save();
}
// Out of stock product.
$p = new WC_Product_Simple();
$p->set_name( 'Sold Out Scarf' ); $p->set_status( 'publish' ); $p->set_regular_price( '30' );
$p->set_category_ids( array( $acc ) ); $p->set_stock_status( 'outofstock' ); $p->set_image_id( kwt_image( 'Scarf', array( 100, 116, 139 ) ) );
$ids[] = $p->save();

// Variable product.
$v = new WC_Product_Variable();
$v->set_name( 'Logo Hoodie' ); $v->set_status( 'publish' ); $v->set_category_ids( array( $hoodies ) );
$v->set_sku( 'KWT-VAR' ); $v->set_short_description( 'Pick a colour and size.' ); $v->set_description( '<p>Our best-selling logo hoodie.</p>' );
$v->set_image_id( kwt_image( 'Logo Hoodie', array( 30, 64, 175 ) ) );
$attrs = array();
foreach ( array( 'color' => array( 'red', 'blue' ), 'size' => array( 'small', 'medium', 'large' ) ) as $slug => $terms ) {
	$a = new WC_Product_Attribute();
	$a->set_id( wc_attribute_taxonomy_id_by_name( $slug ) );
	$a->set_name( 'pa_' . $slug );
	$a->set_options( array_map( fn( $t ) => get_term_by( 'slug', $t, 'pa_' . $slug )->term_id, $terms ) );
	$a->set_visible( true ); $a->set_variation( true );
	$attrs[] = $a;
}
$v->set_attributes( $attrs );
$vid = $v->save();
foreach ( array( 'red', 'blue' ) as $c ) {
	foreach ( array( 'small', 'medium', 'large' ) as $sz ) {
		$var = new WC_Product_Variation();
		$var->set_parent_id( $vid );
		$var->set_attributes( array( 'pa_color' => $c, 'pa_size' => $sz ) );
		$var->set_regular_price( 'large' === $sz ? '70' : '65' );
		$var->set_stock_status( 'instock' );
		$var->save();
	}
}
WC_Product_Variable::sync( $vid );
$ids[] = $vid;

// Linked products.
$first = wc_get_product( $ids[0] );
$first->set_upsell_ids( array( $ids[1], $vid ) );
$first->set_cross_sell_ids( array( $ids[4], $ids[5] ) );
$first->save();

// A review.
$cid = wp_insert_comment( array( 'comment_post_ID' => $ids[0], 'comment_author' => 'Ada', 'comment_author_email' => 'ada@example.com', 'comment_content' => 'Great tee, fits well.', 'comment_type' => 'review', 'comment_approved' => 1 ) );
update_comment_meta( $cid, 'rating', 5 );
WC_Comments::clear_transients( $ids[0] );
wc_update_product_lookup_tables_column( 'average_rating' );

update_option( 'kwt_seed_ids', $ids );
echo 'Seeded ' . count( $ids ) . " products\n";
