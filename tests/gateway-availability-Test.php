<?php

class Test_Gateway_Availability extends WP_UnitTestCase {
    public function test_gateway_availability() {

        $product = $this->create_sample_product_and_add_to_cart();
        $order = wc_create_order();
        $order_id = $order->get_id();

        WC()->session = new WC_Session_Handler();
        WC()->session->set_customer_session_cookie( true );
        WC()->session->set( 'order_awaiting_payment', $order_id );

        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        $this->assertArrayHasKey( 'axytoswc', $available_gateways, 'Axytos gateway should be available initially.' );

        set_transient( 'disable_axitos_for_' . $order_id, true, 60 * 60 );

        $available_gateways = apply_filters( 'woocommerce_available_payment_gateways', WC()->payment_gateways()->get_available_payment_gateways() );

        $this->assertArrayNotHasKey( 'axytoswc', $available_gateways, 'Axytos gateway should not be available for this order after disabling.' );
    }

    private function create_sample_product_and_add_to_cart() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            $this->fail( 'WooCommerce is not installed or activated.' );
            return;
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
