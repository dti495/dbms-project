-- ====================================================================
-- HaatLink: Online Marketplace Database Schema (Phase 1 / 50% Milestone)
-- Project: DBMS Final Project
-- DBMS: MySQL / MariaDB (Compatible with XAMPP)
-- Description: Complete schema for Users, Categories, Products, and
--              foundation tables for Orders and Reviews. Includes dummy data.
-- ====================================================================

-- Step 1: Create Database if not exists
CREATE DATABASE IF NOT EXISTS `haatlink_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `haatlink_db`;

-- --------------------------------------------------------------------
-- Table 1: USERS
-- Stores credentials, roles (admin, producer, customer), and status
-- Status logic:
--   - 'customer': defaults to 'verified'
--   - 'producer': defaults to 'pending' (requires admin verification)
--   - 'admin': pre-seeded and verified
--   - 'suspended': for rejected/blocked accounts
-- --------------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'producer', 'customer') NOT NULL DEFAULT 'customer',
    `status` ENUM('pending', 'verified', 'suspended', 'rejected') NOT NULL DEFAULT 'verified',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- Table 2: CATEGORIES
-- Organizes products into distinct marketplace sections
-- --------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- Table 3: PRODUCTS
-- Stores products listed by verified producers
-- Relational integrity:
--   - producer_id references users(id)
--   - category_id references categories(id)
-- --------------------------------------------------------------------
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `producer_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10, 2) NOT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'active', 'out_of_stock', 'rejected') NOT NULL DEFAULT 'pending',
    `image_url` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_products_producer` 
        FOREIGN KEY (`producer_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_products_category` 
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- Table 4: ORDERS (Order Processing & Checkout)
-- --------------------------------------------------------------------
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `delivery_fee` DECIMAL(10, 2) NOT NULL DEFAULT 50.00,
    `platform_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'processing', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    `shipping_name` VARCHAR(100) NOT NULL,
    `shipping_phone` VARCHAR(30) NOT NULL,
    `shipping_address` TEXT NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_customer` 
        FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- Table 5: ORDER_ITEMS (Relational Bridge & Price Transparency)
-- --------------------------------------------------------------------
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `producer_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `platform_fee` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `producer_earnings` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    CONSTRAINT `fk_order_items_order` 
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_producer` 
        FOREIGN KEY (`producer_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------------------
-- Table 6: REVIEWS (Foundation for Phase 2 - Feedback system)
-- --------------------------------------------------------------------
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `customer_id` INT NOT NULL,
    `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_reviews_product` 
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reviews_customer` 
        FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ====================================================================
-- DUMMY SEED DATA FOR TESTING AND PRESENTATION
-- ====================================================================

-- 1. Insert Users:
--    - 1 Admin: admin@haatlink.com (Password: admin123)
--    - 2 Producers:
--         * rohim.farm@haatlink.com (Password: password123, Status: verified)
--         * karim.agro@haatlink.com (Password: password123, Status: pending -> for Admin verification demo!)
--    - 2 Customers:
--         * sakib@haatlink.com (Password: password123, Status: verified)
--         * tamim@haatlink.com (Password: password123, Status: verified)
-- Note: Passwords stored below will match via password_verify() or the fallback handler
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@haatlink.com', 'admin123', 'admin', 'verified'),
(2, 'Rohim Uddin (Farmer)', 'rohim.farm@haatlink.com', 'password123', 'producer', 'verified'),
(3, 'Karim Agro Crafts', 'karim.agro@haatlink.com', 'password123', 'producer', 'pending'),
(4, 'Sakib Al Hasan', 'sakib@haatlink.com', 'password123', 'customer', 'verified'),
(5, 'Tamim Iqbal', 'tamim@haatlink.com', 'password123', 'customer', 'verified');

-- 2. Insert Categories (3 Categories as required):
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Fresh Vegetables & Greens'),
(2, 'Dairy & Farm Poultry'),
(3, 'Handmade Crafts & Pottery');

-- 3. Insert Products (5 Products linked to verified Producer Rohim Uddin):
INSERT INTO `products` (`id`, `producer_id`, `category_id`, `name`, `description`, `price`, `stock_quantity`, `status`, `image_url`) VALUES
(1, 2, 1, 'Organic Red Tomatoes (1kg)', 'Farm-fresh, pesticide-free red ripe tomatoes harvested directly from rural Bogura farm.', 65.00, 45, 'active', 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=500&auto=format&fit=crop&q=80'),
(2, 2, 1, 'Fresh Green Spinach (Palong Shak)', 'Crisp, organically grown leafy greens packed with iron and vitamins. Freshly picked today.', 30.00, 30, 'active', 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?w=500&auto=format&fit=crop&q=80'),
(3, 2, 2, 'Pure Village Cow Milk (1 Litre)', '100% natural, unadulterated whole cow milk sourced from grass-fed local dairy cows.', 90.00, 20, 'active', 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=500&auto=format&fit=crop&q=80'),
(4, 2, 2, 'Free-Range Country Eggs (Dozen)', 'Nutritious brown country eggs from free-range hens raised in natural open farm environment.', 160.00, 0, 'out_of_stock', 'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=500&auto=format&fit=crop&q=80'),
(5, 2, 3, 'Handwoven Jute Shopping Bag', 'Eco-friendly, durable traditional jute tote bag handcrafted by rural village artisans.', 250.00, 15, 'active', 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=500&auto=format&fit=crop&q=80'),
(6, 2, 3, 'Handcrafted Traditional Clay Pot', 'Authentic handmade clay cooking pot crafted by local village artisans. Awaiting admin approval.', 180.00, 12, 'pending', 'https://images.unsplash.com/photo-1565193566173-7a0ee3dbe261?w=500&auto=format&fit=crop&q=80');

-- Reset Auto-Increment pointers for future inserts
ALTER TABLE `users` AUTO_INCREMENT = 6;
ALTER TABLE `categories` AUTO_INCREMENT = 4;
ALTER TABLE `products` AUTO_INCREMENT = 7;
