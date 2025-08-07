<?php
if (!defined('ABSPATH')) exit;

class WSSC_Mobile_Selector {
    public function __construct() {
        // Frontend functionality
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('mobile_selector', [$this, 'render_selector']);
        
        // AJAX handlers
        add_action('wp_ajax_wssc_get_models', [$this, 'get_models']);
        add_action('wp_ajax_nopriv_wssc_get_models', [$this, 'get_models']);
        
        // Cart integration
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 3);
        
        // Display in orders
        add_action('woocommerce_before_order_itemmeta', [$this, 'display_order_item_meta'], 10, 3);
        add_action('woocommerce_order_item_meta_end', [$this, 'display_order_item_meta_frontend'], 10, 4);
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $brand_table = $wpdb->prefix . 'wssc_mobile_brands';
        $model_table = $wpdb->prefix . 'wssc_mobile_models';

        $sql = "
        CREATE TABLE $brand_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_name VARCHAR(255) NOT NULL UNIQUE
        ) $charset_collate;

        CREATE TABLE $model_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_id INT NOT NULL,
            model_name VARCHAR(255) NOT NULL,
            INDEX idx_brand_id (brand_id),
            FOREIGN KEY (brand_id) REFERENCES $brand_table(id) ON DELETE CASCADE
        ) $charset_collate;
        ";

        dbDelta($sql);
    }

    public function enqueue_scripts() {
        if (is_woocommerce() || is_cart() || is_checkout() || is_shop() || is_product_category() || is_product_tag() || is_product()) {
            wp_enqueue_script('wssc-mobile-selector', WSSC_PLUGIN_URL . 'assets/js/wssc-mobile-selector.js', ['jquery'], '1.0.0', true);
            wp_localize_script('wssc-mobile-selector', 'wsscMobile', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wssc_mobile_nonce')
            ]);
        }
    }

    public function render_selector($atts = []) {
        global $wpdb;
        
        $atts = shortcode_atts([
            'required' => 'true',
            'class' => 'wssc-mobile-selector'
        ], $atts);

        // Get all brands
        $brands = $wpdb->get_results("SELECT brand_name FROM {$wpdb->prefix}wssc_mobile_brands ORDER BY brand_name");

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <div class="wssc-mobile-field">
                <label for="mobile_brand">📱 Select Mobile Brand <?php echo $atts['required'] === 'true' ? '*' : ''; ?></label>
                <select id="mobile_brand" name="mobile_brand" <?php echo $atts['required'] === 'true' ? 'required' : ''; ?>>
                    <option value="">Choose Brand</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo esc_attr($brand->brand_name); ?>">
                            <?php echo esc_html($brand->brand_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="wssc-mobile-field">
                <label for="mobile_model">📱 Select Mobile Model <?php echo $atts['required'] === 'true' ? '*' : ''; ?></label>
                <select id="mobile_model" name="mobile_model" <?php echo $atts['required'] === 'true' ? 'required' : ''; ?> disabled>
                    <option value="">First select brand</option>
                </select>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_models() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wssc_mobile_nonce')) {
            wp_die('Security check failed');
        }

        global $wpdb;
        $brand_name = sanitize_text_field($_POST['brand_name']);
        
        // Get brand ID
        $brand_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wssc_mobile_brands WHERE brand_name = %s", 
            $brand_name
        ));

        if (!$brand_id) {
            wp_send_json([]);
        }
        
        // Get models for this brand
        $models = $wpdb->get_results($wpdb->prepare(
            "SELECT model_name FROM {$wpdb->prefix}wssc_mobile_models WHERE brand_id = %d ORDER BY model_name", 
            $brand_id
        ));
        
        wp_send_json($models);
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Check both $_POST (regular form) and existing cart_item_data (AJAX)
        $mobile_brand = '';
        $mobile_model = '';
        
        // Priority 1: Check if already set in cart_item_data (from AJAX)
        if (!empty($cart_item_data['mobile_brand']) && !empty($cart_item_data['mobile_model'])) {
            return $cart_item_data;
        }
        
        // Priority 2: Check $_POST for regular form submissions
        if (isset($_POST['mobile_brand']) && !empty($_POST['mobile_brand']) && 
            isset($_POST['mobile_model']) && !empty($_POST['mobile_model'])) {
            
            $mobile_brand = sanitize_text_field($_POST['mobile_brand']);
            $mobile_model = sanitize_text_field($_POST['mobile_model']);
        }
        
        // If we have brand and model, add them to cart item data
        if (!empty($mobile_brand) && !empty($mobile_model)) {
            $cart_item_data['mobile_brand'] = $mobile_brand;
            $cart_item_data['mobile_model'] = $mobile_model;
            
            // Make each cart item unique
            $cart_item_data['unique_key'] = md5(microtime().rand());
        }
        
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['mobile_brand'])) {
            $item_data[] = [
                'key'     => __('Mobile Brand', 'wssc'),
                'value'   => wc_clean($cart_item['mobile_brand']),
                'display' => '',
            ];
        }
        
        if (!empty($cart_item['mobile_model'])) {
            $item_data[] = [
                'key'     => __('Mobile Model', 'wssc'),
                'value'   => wc_clean($cart_item['mobile_model']),
                'display' => '',
            ];
        }
        
        return $item_data;
    }

    public function save_order_item_meta($item, $cart_item_key, $values, $order) {
        if (!empty($values['mobile_brand'])) {
            $item->add_meta_data(__('Mobile Brand', 'wssc'), $values['mobile_brand']);
        }
        
        if (!empty($values['mobile_model'])) {
            $item->add_meta_data(__('Mobile Model', 'wssc'), $values['mobile_model']);
        }
    }

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        // Only validate if mobile selector fields are present in the form
        if (isset($_POST['mobile_brand']) || isset($_POST['mobile_model'])) {
            if (empty($_POST['mobile_brand']) || empty($_POST['mobile_model'])) {
                wc_add_notice(__('Please select both mobile brand and model.', 'wssc'), 'error');
                $passed = false;
            }
        }
        return $passed;
    }

    public function display_order_item_meta($item_id, $item, $order) {
        $brand = $item->get_meta('Mobile Brand');
        $model = $item->get_meta('Mobile Model');
        
        if ($brand) {
            echo '<div><strong>' . __('Mobile Brand', 'wssc') . ':</strong> ' . esc_html($brand) . '</div>';
        }
        
        if ($model) {
            echo '<div><strong>' . __('Mobile Model', 'wssc') . ':</strong> ' . esc_html($model) . '</div>';
        }
    }

    public function display_order_item_meta_frontend($item_id, $item, $order, $plain_text) {
        $brand = $item->get_meta('Mobile Brand');
        $model = $item->get_meta('Mobile Model');
        
        if ($brand || $model) {
            if ($plain_text) {
                echo "\n";
                if ($brand) echo 'Mobile Brand: ' . $brand . "\n";
                if ($model) echo 'Mobile Model: ' . $model . "\n";
            } else {
                echo '<div class="wssc-mobile-info" style="margin-top: 5px; font-size: 0.9em; color: #666;">';
                if ($brand) echo '<div><strong>Mobile Brand:</strong> ' . esc_html($brand) . '</div>';
                if ($model) echo '<div><strong>Mobile Model:</strong> ' . esc_html($model) . '</div>';
                echo '</div>';
            }
        }
    }

    // Get brands for admin
    public function get_all_brands() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wssc_mobile_brands ORDER BY brand_name");
    }

    // Get models for admin
    public function get_models_by_brand($brand_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wssc_mobile_models WHERE brand_id = %d ORDER BY model_name", 
            $brand_id
        ));
    }

    // Add brand
    public function add_brand($brand_name) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wssc_mobile_brands',
            ['brand_name' => sanitize_text_field($brand_name)]
        );
    }

    // Add model
    public function add_model($brand_id, $model_name) {
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix . 'wssc_mobile_models',
            [
                'brand_id' => intval($brand_id),
                'model_name' => sanitize_text_field($model_name)
            ]
        );
    }

    // Delete brand (and its models)
    public function delete_brand($brand_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wssc_mobile_brands', ['id' => intval($brand_id)]);
    }

    // Delete model
    public function delete_model($model_id) {
        global $wpdb;
        return $wpdb->delete($wpdb->prefix . 'wssc_mobile_models', ['id' => intval($model_id)]);
    }
}