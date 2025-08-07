<?php
if (!defined('ABSPATH')) exit;

class WSSC_DB {
    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'wssc_bulk_requests';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT NOT NULL,
            name VARCHAR(255),
            phone VARCHAR(50),
            email VARCHAR(255),
            quantity INT,
            message TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at DATETIME
        ) $charset;";
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Add email and status columns if they don't exist (for existing installations)
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS email VARCHAR(255) AFTER phone");
        $wpdb->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' AFTER message");
    }
}