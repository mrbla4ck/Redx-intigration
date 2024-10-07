<?php

/**
 * Plugin Name: RedX Integration
 * Description: Sends WooCommerce order details to RedX for processing.
 * Version: 1.6.8
 * Author: Shahriar Rahman
 */

 if (!defined('WPINC')) {
    die;
}

function redx_integration_init() {
    if (class_exists('WooCommerce')) {
        // Hook AJAX actions for logged-in and not logged-in users
        add_action('wp_ajax_fetch_redx_zones', 'fetch_redx_delivery_zones'); // For logged-in users
        add_action('wp_ajax_nopriv_fetch_redx_zones', 'fetch_redx_delivery_zones'); // For not logged-in users
        add_action('admin_menu', 'redx_add_admin_menu_page');
        add_action('admin_enqueue_scripts', 'redx_enqueue_admin_scripts');
        add_action('wp_ajax_send_redx_order', 'redx_handle_send_order');
        add_action('wp_ajax_nopriv_fetch_redx_zones', 'fetch_redx_delivery_zones');
        add_action('wp_ajax_fetch_redx_zones', 'fetch_redx_delivery_zones');
        add_action('wp_enqueue_scripts', 'redx_enqueue_checkout_script');
        add_filter('woocommerce_checkout_fields', 'custom_add_delivery_area_dropdown');
    } else {
        add_action('admin_notices', 'redx_woocommerce_missing_notice');
    }
}
add_action('plugins_loaded', 'redx_integration_init');

function redx_add_admin_menu_page() {
    add_menu_page(
        'RedX Orders',
        'RedX Orders',
        'manage_options',
        'redx-orders',
        'redx_orders_page_html',
        'dashicons-clipboard',
        6
    );
}

function redx_orders_page_html() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    wp_nonce_field('redx_send_order_nonce_action', 'redx_send_order_nonce');

    $args = array(
        'status' => 'processing',
        'limit' => -1,
    );
    $orders = wc_get_orders($args);
    
    // Start building the HTML
    echo '<div class="wrap"><h1>' . esc_html__('RedX Orders', 'text-domain') . '</h1>';
    echo '<table class="wp-list-table widefat fixed striped">';
    // Define table headers
    echo '<thead><tr><th>' . esc_html__('Order ID', 'text-domain') . '</th><th>' . esc_html__('Customer Name', 'text-domain') . '</th><th>' . esc_html__('Create Parcel', 'text-domain') . '</th><th>' . esc_html__('Tracking ID', 'text-domain') . '</th><th>' . esc_html__('Category', 'text-domain') . '</th></tr></thead>';
    echo '<tbody>';

    foreach ($orders as $order) {
        echo '<tr>';
        echo '<td>' . esc_html($order->get_id()) . '</td>';
        echo '<td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>';
        // Create Parcel Button
        echo '<td><button type="button" class="button button-primary redx-send-order" data-order-id="' . esc_attr($order->get_id()) . '" data-nonce="' . wp_create_nonce('redx-ajax-nonce') . '">' . esc_html__('Send to RedX', 'text-domain') . '</button></td>';
        // Tracking ID Input - Initially empty, to be filled in by the admin
        echo '<td><input type="text" class="small-text tracking-id-input" style="width:70%;" name="tracking_id_' . esc_attr($order->get_id()) . '" value=""/></td>';
        // Category Update Action - Assuming a button for action, not an input for category ID
        echo '<td><button type="button" class="button button-secondary update-categories" data-order-id="' . esc_attr($order->get_id()) . '" data-nonce="' . wp_create_nonce('redx-update-categories-nonce') . '">' . esc_html__('Update Categories', 'text-domain') . '</button></td>';
        echo '</tr>';
    }
    

    echo '</tbody></table></div>';
}

function redx_enqueue_admin_scripts($hook) {
    if ('toplevel_page_redx-orders' !== $hook) {
        return;
    }

    wp_enqueue_script(
        'redx-admin-script',
        plugin_dir_url(__FILE__) . 'js/admin-script.js',
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script(
        'redx-admin-script',
        'redx_ajax_object',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('redx-ajax-nonce'),
        )
    );

        // Enqueue the script - adjust the handle and path as necessary
        wp_enqueue_script('redx-category-script', plugin_dir_url(__FILE__) . 'js/redx-category.js', array('jquery'), '1.0.1', true);

        // Localize the script with necessary data
        wp_localize_script('redx-category-script', 'redx_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('redx-update-categories-action'), // Action should match the one used in check_ajax_referer
    ));
}




function redx_handle_send_order() {
    // Check nonce for security
    
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'redx-ajax-nonce')) {
        wp_send_json_error(['message' => 'Nonce verification failed.']);
        return;
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
        return;
    }

    // Initialize an array to hold the parcel details
    $parcel_details_json = [];

    // Iterate through order items
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);

        // Fetch the custom fields for each product
        $parcel_weight = get_post_meta($product_id, 'parcel_weight', true);
        $is_closed_box_value = get_post_meta($product_id, 'is_closed_box', true);
        $is_closed_box = $is_closed_box_value === 'yes' ? true : false;

        // Append each item's details to the parcel details array
        $parcel_details_json[] = [
            'name' => $item->get_name(),
            'category' => 'category1', // This is a placeholder. You'll need to adjust it based on your actual data or logic.
            'value' => $product->get_price(),
            'weight' => $parcel_weight,
            'is_closed_box' => $is_closed_box,
        ];
    }
   // Capture delivery area details from the request
    $delivery_area_id = get_post_meta($order->get_id(), '_delivery_area_id', true);
    $delivery_area_name = get_post_meta($order->get_id(), '_delivery_area', true);



    // Constructing the payload for RedX API
    $payload = [
        "customer_name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        "customer_phone" => $order->get_billing_phone(),
        "delivery_area_id" => $delivery_area_id, // Use the captured delivery_area_id
        "delivery_area" => $delivery_area_name, // Use the captured delivery_area
        "customer_address" => $order->get_billing_address_1(),
        "merchant_invoice_id" => $order->get_order_number(),
        "cash_collection_amount" => strval($order->get_total()),
        "parcel_weight" => $parcel_weight, // Assuming all items have the same weight
        "instruction" => "Handle with care", // Example instruction
        "value" => strval($order->get_total()), // Example declared value
        "is_closed_box" => $is_closed_box, // Assuming all items have the same box type
        "parcel_details_json" => $parcel_details_json,
    ];

    $response = wp_remote_post('https://openapi.redx.com.bd/v1.0.0-beta/parcel', [
        'headers' => [
            'Content-Type' => 'application/json',
            'API-ACCESS-TOKEN' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI4OTgzMjEiLCJpYXQiOjE3MDY5NDMxMjksImlzcyI6InNHVEZiMG92THdPdDdFVTdtUkNiTFFMbURoemRjWmFFIiwic2hvcF9pZCI6ODk4MzIxLCJ1c2VyX2lkIjo4ODg2OTAzfQ.UUGtIG98LQpe5uHykLT5S2emvrcMhxcD5JwYd_JQcCA',
        ],
        'body' => json_encode($payload),
        'method' => 'POST',
        'data_format' => 'body',
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Failed to send request to RedX: ' . $response->get_error_message()]);
        return;
    }

    $api_response = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($api_response['tracking_id'])) {
        wp_send_json_success([
            'message' => 'Order successfully sent to RedX.',
            'tracking_id' => $api_response['tracking_id'],
            'response' => $api_response // Optionally include more response data for debugging
        ]);
    } else {
        $error_message = isset($api_response['message']) ? $api_response['message'] : 'Failed to send order to RedX.';
        wp_send_json_error(['message' => $error_message, 'response' => $api_response]);
    }
}
function redx_woocommerce_missing_notice() {
    echo '<div class="notice notice-warning is-dismissible">';
    echo '<p><strong>RedX Integration</strong> requires WooCommerce to be installed and active.</p>';
    echo '</div>';
}


// Display Fields
add_action('woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields');

// Save Fields
add_action('woocommerce_process_product_meta', 'woo_add_custom_general_fields_save');

function woo_add_custom_general_fields() {
    global $woocommerce, $post;
  
    echo '<div class="options_group">';

    // Parcel Weight Field
    woocommerce_wp_text_input(
        array(
            'id' => 'parcel_weight',
            'label' => __('Parcel Weight (g)', 'woocommerce'),
            'placeholder' => 'Weight in grams',
            'desc_tip' => 'true',
            'description' => __('Enter the weight of the parcel in grams.', 'woocommerce'),
            'type' => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min' => '0'
            )
        )
    );

    // Is Closed Box Checkbox
    woocommerce_wp_checkbox(
        array(
            'id' => 'is_closed_box',
            'label' => __('Is Closed Box?', 'woocommerce'),
            'description' => __('Check if the parcel is a closed box.', 'woocommerce')
        )
    );

    echo '</div>';
}

// Add custom fields for RedX Category ID and Sub Category ID
add_action('woocommerce_product_options_general_product_data', 'add_redx_category_fields');

function add_redx_category_fields() {
    echo '<div class="options_group">';

    woocommerce_wp_text_input([
        'id' => 'redx_category_id',
        'label' => __('RedX Category ID', 'woocommerce'),
        'desc_tip' => 'true',
        'description' => __('Enter the RedX Category ID.', 'woocommerce'),
    ]);

    woocommerce_wp_text_input([
        'id' => 'redx_sub_category_id',
        'label' => __('RedX Sub Category ID', 'woocommerce'),
        'desc_tip' => 'true',
        'description' => __('Enter the RedX Sub Category ID.', 'woocommerce'),
    ]);

    echo '</div>';
}

// Save the custom fields
add_action('woocommerce_process_product_meta', 'save_redx_category_fields');

function save_redx_category_fields($post_id) {
    $redx_category_id = isset($_POST['redx_category_id']) ? $_POST['redx_category_id'] : '';
    update_post_meta($post_id, 'redx_category_id', sanitize_text_field($redx_category_id));

    $redx_sub_category_id = isset($_POST['redx_sub_category_id']) ? $_POST['redx_sub_category_id'] : '';
    update_post_meta($post_id, 'redx_sub_category_id', sanitize_text_field($redx_sub_category_id));
}


function woo_add_custom_general_fields_save($post_id) {
    // Parcel Weight
    $parcel_weight = isset($_POST['parcel_weight']) ? $_POST['parcel_weight'] : '';
    update_post_meta($post_id, 'parcel_weight', esc_attr($parcel_weight));
    
    // Is Closed Box
    $is_closed_box = isset($_POST['is_closed_box']) ? 'yes' : 'no';
    update_post_meta($post_id, 'is_closed_box', $is_closed_box);
}


function redx_enqueue_checkout_script() {
    if (is_checkout()) {
        wp_enqueue_script('redx-checkout-script', plugin_dir_url(__FILE__) . 'js/redx-checkout.js', array('jquery'), '1.0.0', true);

        // Pass correct nonce action to match with the AJAX request verification
        wp_localize_script('redx-checkout-script', 'redx_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('redx-fetch-zones'), // Ensure this nonce action matches in AJAX verification
        ));
    }
}

function fetch_redx_delivery_zones() {
    // Ensure nonce action matches what's passed in the localized script
    check_ajax_referer('redx-fetch-zones', 'security');

    $district_name = isset($_POST['district_name']) ? sanitize_text_field($_POST['district_name']) : '';

    $api_response = wp_remote_get("https://openapi.redx.com.bd/v1.0.0-beta/areas?district_name={$district_name}", array(
        'headers' => array(
            'API-ACCESS-TOKEN' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI4OTgzMjEiLCJpYXQiOjE3MDY5NDMxMjksImlzcyI6InNHVEZiMG92THdPdDdFVTdtUkNiTFFMbURoemRjWmFFIiwic2hvcF9pZCI6ODk4MzIxLCJ1c2VyX2lkIjo4ODg2OTAzfQ.UUGtIG98LQpe5uHykLT5S2emvrcMhxcD5JwYd_JQcCA', // Replace YOUR_API_TOKEN with the actual token
        ),
    ));

    if (is_wp_error($api_response)) {
        wp_send_json_error(['message' => 'Failed to fetch delivery zones.']);
    } else {
        $zones = json_decode(wp_remote_retrieve_body($api_response), true);
        wp_send_json_success($zones);
    }
}

function custom_add_delivery_area_dropdown($fields) {
    // Modify checkout fields to add dynamic delivery area dropdown
    $fields['billing']['billing_delivery_area'] = array(
        'type'          => 'select',
        'label'         => __('Delivery Area', 'your-text-domain'),
        'required'      => true,
        'class'         => array('form-row-wide'),
        'clear'         => true,
        'options'       => array('' => __('Select your delivery area', 'your-text-domain')), // Placeholder for dynamic content
    );

    return $fields;
}

 
add_action('woocommerce_checkout_update_order_meta', 'save_delivery_area_to_order_meta');

function save_delivery_area_to_order_meta($order_id) {
    if (!empty($_POST['billing_delivery_area'])) {
        // Split the delivery area into ID and name
        $delivery_area_parts = explode(' ', $_POST['billing_delivery_area'], 2); // Limit to 2 parts
        if (count($delivery_area_parts) === 2) {
            $delivery_area_id = trim($delivery_area_parts[0]);
            $delivery_area_name = trim($delivery_area_parts[1]);

            // Save the delivery area ID
            update_post_meta($order_id, '_delivery_area_id', sanitize_text_field($delivery_area_id));
            // Save the delivery area name
            update_post_meta($order_id, '_delivery_area', sanitize_text_field($delivery_area_name));
        }
    }
}




add_action('woocommerce_admin_order_data_after_billing_address', 'display_delivery_area_in_admin_order', 10, 1);

function display_delivery_area_in_admin_order($order) {
    $delivery_area_id = get_post_meta($order->get_id(), '_delivery_area_id', true);
    $delivery_area_name = get_post_meta($order->get_id(), '_delivery_area', true);

    if (!empty($delivery_area_id) || !empty($delivery_area_name)) {
        echo '<p><strong>Delivery Area ID:</strong> ' . esc_html($delivery_area_id) . '</p>';
        echo '<p><strong>Delivery Area Name:</strong> ' . esc_html($delivery_area_name) . '</p>';
    }
}



function handle_update_redx_categories() {
    // Check for nonce for security
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    if (!wp_verify_nonce($nonce, 'redx-update-categories-action')) {
        wp_send_json_error(['message' => 'Nonce verification failed2.']);
        return;
    }
 
    check_ajax_referer('redx-update-categories-action', 'nonce');

    // Extract necessary data from the AJAX request
    $tracking_id = sanitize_text_field($_POST['tracking_id']);
    $order_id = intval($_POST['order_id']);

    // Fetch order details
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found.']);
        return;
    }

    // Assume we're getting the first product from the order for category ID
    $items = $order->get_items();
    $first_item = reset($items);
    $product_id = $first_item->get_product_id();

    // Fetch previously saved category and subcategory IDs from product meta
    $category_id = get_post_meta($product_id, 'redx_category_id', true);
    $sub_category_id = get_post_meta($product_id, 'redx_sub_category_id', true);

    // Construct the payload for the PUT request
    $payload = [
        "AREA" => get_post_meta($order_id, '_delivery_area', true),
        "AREA_ID" => get_post_meta($order_id, '_delivery_area_id', true),
        "CASH" => $order->get_total(),
        "CATEGORY_ID" => $category_id,
        "CUSTOMER_NAME" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        "CUSTOMER_PHONE" => $order->get_billing_phone(),
        "DELIVERY_ADDRESS" => $order->get_billing_address_1(),
        "INVOICE_NUMBER" => $order->get_order_number(),
        "SELLER_INSTRUCTION" => "", // Adjust based on your actual data or logic
        "VALUE" => $order->get_total(),
        "VARIANT_ID" => $sub_category_id,
        "WEIGHT" => $parcel_weight, // This might need to be dynamically calculated based on the order
    ];

    // Execute the PUT request to the RedX API
    $response = wp_remote_request('https://api.redx.com.bd/v1/admin/shop/898321/logistics/parcels/' . $tracking_id, [
        'method'    => 'PUT',
        'headers'   => [
            'X-Access-Token' => 'Bearer 2ade760aca7d3a81003107cf249dd5c52c864c2eed106b870af4c046ad3ba105874e0e733d0bc23c93d5baf798d42ea179322e20e75858d93b581a970a7a6dd6', // Replace with your actual token
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode($payload),
    ]);

    // Handle the response
    if (is_wp_error($response)) {
        wp_send_json_error(['isError' => true, 'message' => 'Failed to update category: ' . $response->get_error_message()]);
        return;
    }

    $api_response = json_decode(wp_remote_retrieve_body($response), true);

    // Adjust the condition based on your actual API response structure
    if (isset($api_response['isError']) && $api_response['isError'] === false) {
        // If the API responded with "isError": false, it's a success
        wp_send_json_success(['isError' => false, 'message' => 'Category updated successfully', 'response' => $api_response]);
    } else {
        // If the API responded with "isError": true or the key is missing, it's a failure
        $error_message = isset($api_response['message']) ? $api_response['message'] : 'Failed to update category.';
        wp_send_json_error(['isError' => true, 'message' => $error_message, 'response' => $api_response]);
    }

    wp_send_json_success(['message' => 'Categories updated successfully.']);
}

add_action('wp_ajax_update_redx_categories', 'handle_update_redx_categories');


