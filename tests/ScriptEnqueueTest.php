<?php

class ScriptEnqueueTest extends WP_UnitTestCase {
    public function test_checkout_scripts_enqueued() {
        add_action( 'wp_enqueue_scripts', function() {
            wp_register_script(
                'wc-axytos-blocks-integration',
                plugin_dir_url( __FILE__ ) . '../../assets/block-support.js',
                [],
                '1.0',
                true
            );
            wp_enqueue_script( 'wc-axytos-blocks-integration' );
        }
    );

    do_action( 'wp_enqueue_scripts' );

    $this->assertTrue( wp_script_is( 'wc-axytos-blocks-integration', 'enqueued' ) );
}

}
