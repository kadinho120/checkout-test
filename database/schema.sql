-- Users for Admin Panel
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Products (The main offers)
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE, -- used in URL: checkout.php?slug=my-product
    name TEXT NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url TEXT, -- Hero image/gif
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Order Bumps (Upsells/Cross-sells available on checkout)
CREATE TABLE IF NOT EXISTS order_bumps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL, -- Parent product
    title TEXT NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    image_url TEXT, -- Icon or product image
    active BOOLEAN DEFAULT 1,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Tracking Pixels (Per product)
CREATE TABLE IF NOT EXISTS pixels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    type TEXT NOT NULL, -- 'facebook', 'tiktok', 'google', 'custom'
    pixel_id TEXT, -- e.g. '123456789'
    token TEXT, -- API Access Token (for CAPI)
    active BOOLEAN DEFAULT 1,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    customer_name TEXT,
    customer_email TEXT,
    customer_phone TEXT,
    customer_cpf TEXT,
    total_amount DECIMAL(10, 2) NOT NULL,
    status TEXT DEFAULT 'pending', -- pending, paid, failed
    payment_method TEXT,
    transaction_id TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    json_data TEXT -- Store full payload for safety
);

-- Initial Admin User (Default: admin/admin123 - Change immediately in prod)
-- Hash is for 'admin123' using PASSWORD_DEFAULT
INSERT OR IGNORE INTO users (username, password_hash) VALUES ('admin', '$2y$10$8.w.L5.h5.h5.h5.h5.h5.h5.h5.h5.h5');

-- Tracking Logs (Meta API / S2S)
CREATE TABLE IF NOT EXISTS tracking_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    correlation_id TEXT NOT NULL UNIQUE,
    fbc TEXT,
    fbp TEXT,
    user_agent TEXT,
    event_url TEXT,
    pixel_id TEXT,
    json_payload TEXT, -- Stores extra data like value, currency, product strings
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
