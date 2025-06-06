<?php

class ApiWorkFlowTest extends WP_UnitTestCase {
    public function test_process_payment_success() {

        $gateway = new \Axytos\WooCommerce\AxytosPaymentGateway();

        $product = $this->create_sample_product_and_add_to_cart();

        $order = wc_create_order();
        $order_id = $order->get_id();

        $order->set_billing_first_name( 'John' );
        $order->set_billing_last_name( 'Doe' );
        $order->set_billing_address_1( '123 Main St' );
        $order->set_billing_address_2( 'Apt 4B' );
        $order->set_billing_city( 'Anytown' );
        $order->set_billing_postcode( '56789' );
        $order->set_billing_country( 'DE' );
        $order->set_billing_email( 'john.doe@example.com' );
        $order->set_billing_phone( '+491234567890' );

        $order->set_shipping_first_name( 'John' );
        $order->set_shipping_last_name( 'Doe' );
        $order->set_shipping_address_1( '123 Main St' );
        $order->set_shipping_address_2( 'Apt 4B' );
        $order->set_shipping_city( 'Anytown' );
        $order->set_shipping_postcode( '56789' );
        $order->set_shipping_country( 'DE' );

        $order->update_meta_data( 'unique_id', '12345' );
        $order->set_status( 'pending' );
        $order->set_total( 50 );
        //causing error
        // $order->calculate_totals();

        $items = WC()->cart->get_cart();
        foreach ( $items as $cart_item ) {
            $product_id = $cart_item[ 'product_id' ];
            $quantity = $cart_item[ 'quantity' ];
            $order->add_product( wc_get_product( $product_id ), $quantity );
        }
        // $order->calculate_totals();
        $order->save();

        WC()->session = new WC_Session_Handler();
        WC()->session->set_customer_session_cookie( true );
        WC()->session->set( 'order_awaiting_payment', $order_id );

        $response = $gateway->process_payment( $order_id );
        $this->assertStringContainsString( 'order-received=', $response[ 'redirect' ] );
    }

    private function create_sample_product_and_add_to_cart() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->fail( 'WooCommerce is not installed or activated.' );
            return;
        }

        if ( ! post_type_exists( 'shop_order' ) ) {
            register_post_type( 'shop_order', array(
                'labels'             => array(
                    'name'          => 'Orders',
                    'singular_name' => 'Order'
                ),
                'public'             => false,
                'show_ui'            => true,
                'capability_type'    => 'shop_order',
                'map_meta_cap'       => true,
                'supports'           => array( 'title', 'custom-fields' ),
                'has_archive'        => false,
                'exclude_from_search'=> true,
            ) );
        }

        if ( ! taxonomy_exists( 'product_tag' ) ) {
            register_taxonomy( 'product_tag', 'product', array(
                'hierarchical' => false,
                'labels' => array(
                    'name'                       => 'Product Tags',
                    'singular_name'              => 'Product Tag',
                    'search_items'               => 'Search Product Tags',
                    'all_items'                  => 'All Product Tags',
                    'edit_item'                  => 'Edit Product Tag',
                    'update_item'                => 'Update Product Tag',
                    'add_new_item'               => 'Add New Product Tag',
                    'new_item_name'              => 'New Product Tag Name',
                    'menu_name'                  => 'Product Tags',
                ),
                'show_ui'                    => true,
                'show_admin_column'          => true,
                'query_var'                  => true,
                'rewrite'                    => array( 'slug' => 'product-tag' ),
            ) );
        }

        if ( ! taxonomy_exists( 'product_visibility' ) ) {
            register_taxonomy( 'product_visibility', 'product', array(
                'hierarchical' => false,
                'labels' => array(
                    'name'                       => 'Product Visibility',
                    'singular_name'              => 'Product Visibility',
                    'search_items'               => 'Search Product Visibility',
                    'all_items'                  => 'All Product Visibility',
                    'edit_item'                  => 'Edit Product Visibility',
                    'update_item'                => 'Update Product Visibility',
                    'add_new_item'               => 'Add New Product Visibility',
                    'new_item_name'              => 'New Product Visibility Name',
                    'menu_name'                  => 'Product Visibility',
                ),
                'show_ui'                    => true,
                'show_admin_column'          => true,
                'query_var'                  => true,
                'rewrite'                    => array( 'slug' => 'product-visibility' ),
            ) );
        }

        if ( ! taxonomy_exists( 'product_cat' ) ) {
            register_taxonomy( 'product_cat', 'product', array(
                'hierarchical' => true,
                'labels' => array(
                    'name'                       => 'Product Categories',
                    'singular_name'              => 'Product Category',
                    'search_items'               => 'Search Product Categories',
                    'all_items'                  => 'All Product Categories',
                    'parent_item'                => 'Parent Product Category',
                    'parent_item_colon'          => 'Parent Product Category:',
                    'edit_item'                  => 'Edit Product Category',
                    'update_item'                => 'Update Product Category',
                    'add_new_item'               => 'Add New Product Category',
                    'new_item_name'              => 'New Product Category Name',
                    'menu_name'                  => 'Product Categories',
                ),
                'show_ui'                    => true,
                'show_admin_column'          => true,
                'query_var'                  => true,
                'rewrite'                    => array( 'slug' => 'product-category' ),
            ) );
        }

        $category_name = 'Custom Category';
        $category_slug = 'custom-category';

        $term = term_exists( $category_slug, 'product_cat' );

        if ( ! $term ) {
            $term = wp_insert_term(
                $category_name,
                'product_cat',
                array(
                    'slug' => $category_slug
                )
            );

            if ( is_wp_error( $term ) ) {
                $this->fail( 'Error creating term: ' . $term->get_error_message() );
                return;
            }
        }

        $category_id = is_array( $term ) && ! is_wp_error( $term ) ? $term[ 'term_id' ] : 0;

        if ( $category_id ) {
            $exclude_from_catalog = get_term_meta( $category_id, 'exclude-from-catalog', true );
            if ( empty( $exclude_from_catalog ) ) {
                // update_term_meta( $category_id, 'exclude-from-catalog', 'no' );
            }
        }

        $product = new WC_Product_Simple();
        $product->set_name( 'Test Product' );
        $product->set_regular_price( 49.99 );
        $product->set_stock_quantity( 5 );
        $product->set_manage_stock( true );
        $product->set_status( 'publish' );

        $product->set_category_ids( [ $category_id ] );

        $product_id = $product->save();

        if ( $product_id ) {
            WC()->cart->add_to_cart( $product_id, 1 );
        }
        return $product;
    }
}
