<?php
if (!defined('ABSPATH')) exit;

class WSSC_Ajax {
    public function __construct() {
        add_action('wp_ajax_wssc_buy_bulk', [$this, 'save_request']);
        add_action('wp_ajax_nopriv_wssc_buy_bulk', [$this, 'save_request']);
        add_action('wp_ajax_wssc_add_to_cart', [$this, 'add_to_cart']);
        add_action('wp_ajax_nopriv_wssc_add_to_cart', [$this, 'add_to_cart']);
        add_action('wp_ajax_wssc_update_request_status', [$this, 'update_request_status']);
        add_action('wp_ajax_wssc_delete_request', [$this, 'delete_request']);
    }

    public function save_request() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wssc_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Validate required fields
        if (empty($_POST['name']) || empty($_POST['phone'])) {
            wp_send_json_error('Name and phone are required');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wssc_bulk_requests';
        
        $result = $wpdb->insert($table, [
            'product_id' => intval($_POST['product_id']),
            'name' => sanitize_text_field($_POST['name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'email' => sanitize_email($_POST['email']),
            'quantity' => intval($_POST['quantity']),
            'message' => sanitize_textarea_field($_POST['message']),
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);

        if ($result !== false) {
            wp_send_json_success(['message' => 'Request Submitted Successfully!']);
        } else {
            wp_send_json_error('Failed to save request');
        }
    }

   public function add_to_cart() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wssc_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Get mobile brand and model data if provided
    $mobile_brand = isset($_POST['mobile_brand']) ? sanitize_text_field($_POST['mobile_brand']) : '';
    $mobile_model = isset($_POST['mobile_model']) ? sanitize_text_field($_POST['mobile_model']) : '';

    if ($product_id <= 0) {
        wp_send_json_error('Invalid product ID');
    }

    // Check if product exists and is purchasable
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_purchasable()) {
        wp_send_json_error('Product is not available for purchase');
    }

    // Prepare cart item data with mobile info
    $cart_item_data = [];
    if (!empty($mobile_brand) && !empty($mobile_model)) {
        $cart_item_data['mobile_brand'] = $mobile_brand;
        $cart_item_data['mobile_model'] = $mobile_model;
        $cart_item_data['unique_key'] = md5(microtime().rand());
    }
    
    // Add to cart with mobile data
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

    if ($cart_item_key) {
        wp_send_json_success([
            'message' => 'Product added to cart',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_item_key' => $cart_item_key,
            'mobile_brand' => $mobile_brand,
            'mobile_model' => $mobile_model
        ]);
    } else {
        wp_send_json_error('Failed to add product to cart');
    }
}

    public function update_request_status() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'wssc_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wssc_bulk_requests';
        $id = intval($_POST['id']);
        $status = sanitize_text_field($_POST['status']);

        // Validate status
        if (!in_array($status, ['pending', 'done'])) {
            wp_send_json_error('Invalid status');
        }

        $result = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => 'Status updated successfully']);
        } else {
            wp_send_json_error('Failed to update status');
        }
    }

    public function delete_request() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'wssc_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wssc_bulk_requests';
        $id = intval($_POST['id']);

        $result = $wpdb->delete($table, ['id' => $id], ['%d']);

        if ($result !== false) {
            wp_send_json_success(['message' => 'Request deleted successfully']);
        } else {
            wp_send_json_error('Failed to delete request');
        }
    }
}
new WSSC_Ajax();