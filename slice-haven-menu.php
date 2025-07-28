<?php
/**
 * Plugin Name:       Slice Haven Menu Integration
 * Plugin URI:        (Your website URL, optional)
 * Description:       Integrates the Slice Haven React menu with WooCommerce.
 * Version:           1.0.2 // Incremented version
 * Author:            (Your Name)
 * Author URI:        (Your website URL, optional)
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       slice-haven-menu
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue React app static assets.
 */
function shm_enqueue_react_app_assets() {
    $plugin_url = plugin_dir_url( __FILE__ );
    // Assumes build files were copied into 'react-app' subfolder within the plugin
    $react_app_base = $plugin_url . 'react-app/';

    // --- !!! IMPORTANT: MANUALLY UPDATE THESE FILENAMES AFTER EACH REACT BUILD !!! ---
    // --- Look inside your wp-content/plugins/slice-haven-menu/react-app/ folder ---
    $main_js_file = 'index-DlayTtaA.js';     // Use the actual JS filename hash
    $main_css_file = 'index-BKjPj0-G.css';   // Use the actual CSS filename hash
    // Add lines for other chunk files if needed

    // Enqueue the main CSS file
    wp_enqueue_style(
        'slice-haven-react-styles',
        $react_app_base . $main_css_file,
        [],
        '1.0.2' // Use updated version
    );

    // Enqueue the main JS file (and its dependencies)
    wp_enqueue_script(
        'slice-haven-react-app-main',
        $react_app_base . $main_js_file,
        [],
        '1.0.2',
        true
    );

    // Pass data to React: AJAX URL, Nonce, and My Account URL
    wp_localize_script(
        'slice-haven-react-app-main', // Use the main script handle
        'sliceHavenData',
        [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'slice_haven_cart_nonce' ), // Security nonce
            // Add the My Account URL - Ensure WooCommerce is active
            'my_account_url' => function_exists('wc_get_page_id') ? get_permalink( wc_get_page_id( 'myaccount' ) ) : home_url(), 
        ]
    );
}

/**
 * Render the React app root div via shortcode.
 */
function shm_render_react_app_shortcode( $atts ) {
    shm_enqueue_react_app_assets();
    return '<div id="root">Loading Menu...</div>';
}
add_shortcode( 'slice_haven_menu', 'shm_render_react_app_shortcode' );


/**
 * Handle the AJAX request from React to add items to the WC cart.
 */
function shm_ajax_add_react_cart_to_wc_handler() {
    check_ajax_referer( 'slice_haven_cart_nonce', '_ajax_nonce' );

    if ( ! isset( $_POST['cart_data'] ) ) {
        wp_send_json_error( [ 'message' => 'Missing cart data.' ] );
        return;
    }

    $cart_data_json = stripslashes( $_POST['cart_data'] );
    $cart_items = json_decode( $cart_data_json, true );

    if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $cart_items ) ) {
        wp_send_json_error( [ 'message' => 'Invalid cart data format.' ] );
        return;
    }

    if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
        wp_send_json_error( [ 'message' => 'WooCommerce session/cart not available.' ] );
        return;
    }

    // Get and store order type from React
    $order_type = isset( $_POST['order_type'] ) ? sanitize_text_field( $_POST['order_type'] ) : 'delivery';
    WC()->session->set( 'chosen_order_type', $order_type );

    WC()->cart->empty_cart();

    try {
        foreach ( $cart_items as $item ) {
            $product_id = isset( $item['product_id'] ) ? intval( $item['product_id'] ) : 0;
            $quantity   = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
            $final_price = isset( $item['price'] ) ? floatval( $item['price'] ) : null;
            $customizations = isset( $item['customizations'] ) ? $item['customizations'] : [];

            if ( $product_id <= 0 || $quantity <= 0 ) continue;

            $cart_item_data = [
                'slice_haven_customizations' => $customizations,
                'slice_haven_final_price'    => $final_price,
            ];

             WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
        }
    } catch ( Exception $e ) {
        wp_send_json_error( [ 'message' => 'Error adding items to cart: ' . $e->getMessage() ] );
        return;
    }

    wp_send_json_success( [
        'message'      => 'Items added to cart successfully.',
        'redirect_url' => wc_get_checkout_url()
    ] );
}
add_action( 'wp_ajax_nopriv_add_react_cart_to_wc', 'shm_ajax_add_react_cart_to_wc_handler' );
add_action( 'wp_ajax_add_react_cart_to_wc', 'shm_ajax_add_react_cart_to_wc_handler' );


/**
 * Save custom data to the cart item meta.
 */
function shm_add_custom_data_to_cart_item( $cart_item_data, $product_id, $variation_id, $quantity ) {
    if ( isset( $cart_item_data['slice_haven_final_price'] ) ) {
        $cart_item_data['slice_haven_final_price'] = $cart_item_data['slice_haven_final_price'];
    }
    if ( isset( $cart_item_data['slice_haven_customizations'] ) ) {
        $cart_item_data['slice_haven_customizations'] = $cart_item_data['slice_haven_customizations'];
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'shm_add_custom_data_to_cart_item', 10, 4 );


/**
 * Set the price in the cart based on the custom price.
 */
function shm_set_custom_cart_item_price( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['slice_haven_final_price'] ) ) {
            $cart_item['data']->set_price( floatval( $cart_item['slice_haven_final_price'] ) );
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'shm_set_custom_cart_item_price', 99, 1 );


/**
 * Display custom item data in the cart and checkout.
 */
function shm_display_custom_item_data( $item_data, $cart_item ) {
    if ( isset( $cart_item['slice_haven_customizations'] ) ) {
        $customizations = $cart_item['slice_haven_customizations'];
        if ( ! empty( $customizations['size'] ) ) {
            $item_data[] = [ 'key' => 'Size', 'value' => sanitize_text_field( $customizations['size']['name'] ) ];
        }
        if ( ! empty( $customizations['crust'] ) ) {
            $item_data[] = [ 'key' => 'Crust', 'value' => sanitize_text_field( $customizations['crust']['name'] ) ];
        }
        if ( ! empty( $customizations['toppings'] ) && is_array($customizations['toppings']) ) {
            $topping_names = array_map( function( $topping ) { return sanitize_text_field( $topping['name'] ); }, $customizations['toppings'] );
            if ( ! empty( $topping_names ) ) {
                $item_data[] = [ 'key' => 'Toppings', 'value' => implode( ', ', $topping_names ) ];
            }
        }
    }
    return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'shm_display_custom_item_data', 10, 2 );


/**
 * Add custom meta data to the order line item.
 */
function shm_add_custom_data_to_order_items( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['slice_haven_customizations'] ) ) {
         $customizations = $values['slice_haven_customizations'];
        if ( ! empty( $customizations['size'] ) ) {
            $item->add_meta_data( 'Size', sanitize_text_field( $customizations['size']['name'] ) );
        }
        if ( ! empty( $customizations['crust'] ) ) {
            $item->add_meta_data( 'Crust', sanitize_text_field( $customizations['crust']['name'] ) );
        }
        if ( ! empty( $customizations['toppings'] ) && is_array($customizations['toppings']) ) {
             $topping_names = array_map( function( $topping ) { return sanitize_text_field( $topping['name'] ); }, $customizations['toppings'] );
             if ( ! empty( $topping_names ) ) {
                 $item->add_meta_data( 'Toppings', implode( ', ', $topping_names ) );
             }
        }
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'shm_add_custom_data_to_order_items', 10, 4 );


/**
 * Filter shipping rates based on the order type chosen in React.
 */
function shm_filter_shipping_methods( $rates, $package ) {
    if ( ! WC()->session ) return $rates;

    $chosen_order_type = WC()->session->get('chosen_order_type');

    // If pickup was chosen in React, remove all non-pickup rates
    if ( $chosen_order_type === 'pickup' ) {
        foreach ( $rates as $rate_id => $rate ) {
            // Keep 'local_pickup' or potentially variations like 'local_pickup_plus' if you use extensions
            if ( strpos($rate->get_method_id(), 'local_pickup') === false ) {
                unset( $rates[ $rate_id ] );
            }
        }
    }
    // Optional: If delivery, you could force removal of local_pickup here if desired
    // elseif ( $chosen_order_type === 'delivery' ) {
    //     foreach ( $rates as $rate_id => $rate ) {
    //         if ( strpos($rate->get_method_id(), 'local_pickup') !== false ) {
    //              unset( $rates[ $rate_id ] );
    //          }
    //      }
    // }

    return $rates;
}
add_filter( 'woocommerce_package_rates', 'shm_filter_shipping_methods', 10, 2 );


/**
 * Add a conditional delivery fee if the cart subtotal is below a threshold
 * and the specific delivery shipping method is selected.
 */
function shm_add_conditional_delivery_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( did_action( 'woocommerce_cart_calculate_fees' ) >= 2 ) return; // Prevent double fees

    $minimum_order_amount = 30.00;
    $delivery_fee = 1.99;
    $delivery_method_instance_id = 2; // !!! DOUBLE CHECK THIS ID IN WC SETTINGS !!!

    $subtotal = $cart->get_subtotal();

    // Check chosen shipping method AFTER rates are potentially filtered
    $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
    if ( empty( $chosen_methods ) || ! is_array( $chosen_methods ) ) return;
    $chosen_method_id = $chosen_methods[0]; // e.g., 'flat_rate:1'

    // Check if the chosen method is the specific delivery method instance
    $is_delivery_method_selected = false;
    if ( strpos( $chosen_method_id, ':' ) !== false ) {
         // Extract instance ID if present (e.g., from 'flat_rate:1')
         list( $method_name, $instance_id ) = explode( ':', $chosen_method_id );
         if ( (int)$instance_id === $delivery_method_instance_id ) {
             $is_delivery_method_selected = true;
         }
     } else {
         // Handle cases where method ID might not have an instance ID (less common for flat rate)
         // You might need to adapt this logic based on your exact shipping setup
         // Example: Check if it's *any* flat_rate if instance ID isn't reliable
         // if ($chosen_method_id === 'flat_rate') { $is_delivery_method_selected = true; }
     }

    if ( $subtotal < $minimum_order_amount && $is_delivery_method_selected ) {
        $cart->add_fee( __( 'Delivery Fee', 'slice-haven-menu' ), $delivery_fee );
    }
}
add_action( 'woocommerce_cart_calculate_fees', 'shm_add_conditional_delivery_fee', 20, 1 ); // Increased priority slightly


/**
 * Enqueue global styles needed for theme consistency and WC overrides.
 */
function shm_enqueue_global_styles() {
    // Use the corrected path based on user feedback
    $style_url = plugin_dir_url( __FILE__ ) . 'assets/css/woocommerce-styles.css';

    wp_enqueue_style(
        'slice-haven-global-styles',
        $style_url,
        [],
        '1.0.2' // Updated version
    );
}
add_action( 'wp_enqueue_scripts', 'shm_enqueue_global_styles', 99 ); // High priority to load early





?>