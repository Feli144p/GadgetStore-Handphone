-- Database schema for GadgetStore
-- Import this file into MySQL to create the required tables.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  name VARCHAR(100) NOT NULL,
  role ENUM('buyer','admin') NOT NULL DEFAULT 'buyer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  type VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  brand VARCHAR(100) NOT NULL,
  category VARCHAR(100) NOT NULL,
  price INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  spec_ram VARCHAR(100) DEFAULT NULL,
  spec_storage VARCHAR(100) DEFAULT NULL,
  spec_camera VARCHAR(100) DEFAULT NULL,
  spec_chipset VARCHAR(100) DEFAULT NULL,
  spec_battery VARCHAR(100) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS carts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cart_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(50) NOT NULL UNIQUE,
  user_id INT NOT NULL,
  total INT NOT NULL,
  payment_method VARCHAR(50) DEFAULT 'Transfer Bank',
  recipient_name VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(50) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  postal_code VARCHAR(20) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'Paid',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transaction_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transaction_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price INT NOT NULL,
  subtotal INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

DELIMITER $$
CREATE TRIGGER before_cart_items_insert
BEFORE INSERT ON cart_items
FOR EACH ROW
BEGIN
  DECLARE available_stock INT;
  SELECT stock INTO available_stock FROM products WHERE id = NEW.product_id;
  IF available_stock IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produk tidak ditemukan.';
  END IF;
  IF NEW.quantity > available_stock THEN
    SET NEW.quantity = available_stock;
  END IF;
END$$

CREATE TRIGGER before_cart_items_update
BEFORE UPDATE ON cart_items
FOR EACH ROW
BEGIN
  DECLARE available_stock INT;
  SELECT stock INTO available_stock FROM products WHERE id = NEW.product_id;
  IF available_stock IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produk tidak ditemukan.';
  END IF;
  IF NEW.quantity > available_stock THEN
    SET NEW.quantity = available_stock;
  END IF;
END$$

CREATE TRIGGER before_transaction_items_insert
BEFORE INSERT ON transaction_items
FOR EACH ROW
BEGIN
  DECLARE available_stock INT;
  SELECT stock INTO available_stock FROM products WHERE id = NEW.product_id;
  IF available_stock IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Produk tidak ditemukan.';
  ELSEIF NEW.quantity > available_stock THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok tidak mencukupi untuk transaksi.';
  END IF;
END$$

CREATE TRIGGER after_transaction_items_insert
AFTER INSERT ON transaction_items
FOR EACH ROW
BEGIN
  UPDATE products SET stock = stock - NEW.quantity WHERE id = NEW.product_id;
END$$
DELIMITER ;
