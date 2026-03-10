<?php
/**
 * Migration script for Online Shopping Module
 */
require_once '../config/db.php';
$db = getDB();

try {
    echo "Updating customers table...\n";
    // Add password, username, status and image to customers if they don't exist
    $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS `username` varchar(100) AFTER `name` ");
    $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS `password` varchar(255) AFTER `email` ");
    $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS `status` enum('active','inactive') NOT NULL DEFAULT 'active' AFTER `loyalty_points` ");
    $db->exec("ALTER TABLE customers ADD COLUMN IF NOT EXISTS `image` varchar(255) AFTER `address` ");

    echo "Creating online_orders table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `online_orders` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_number` varchar(50) NOT NULL UNIQUE,
      `customer_id` int(11) NOT NULL,
      `total` decimal(12,2) NOT NULL DEFAULT 0.00,
      `status` enum('pending','preparing','completed','delivered','cancelled') NOT NULL DEFAULT 'pending',
      `delivery_type` enum('pickup','delivery') NOT NULL DEFAULT 'pickup',
      `notes` text,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Creating online_order_items table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `online_order_items` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) NOT NULL,
      `product_id` int(11) NOT NULL,
      `quantity` decimal(10,2) NOT NULL,
      `unit_price` decimal(10,2) NOT NULL,
      `total` decimal(12,2) NOT NULL,
      PRIMARY KEY (`id`),
      FOREIGN KEY (`order_id`) REFERENCES `online_orders`(`id`) ON DELETE CASCADE,
      FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Add image column to products if not exists for shop display
    echo "Adding image column to products...\n";
    $db->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS `image` varchar(255) AFTER `description` ");

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
