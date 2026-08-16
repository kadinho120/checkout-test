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
    theme TEXT DEFAULT 'dark',
    request_email INTEGER DEFAULT 1,
    request_phone INTEGER DEFAULT 1,
    request_name INTEGER DEFAULT 1,
    track_initiate_checkout INTEGER DEFAULT 1,
    track_add_payment_info INTEGER DEFAULT 1,
    checkout_style TEXT DEFAULT 'default',
    product_type TEXT DEFAULT 'digital', -- 'digital' or 'physical'
    compare_at_price DECIMAL(10, 2), -- Stripped price (preço âncora)
    checkout_cta_text TEXT, -- Custom CTA Button Text
    top_bar_timer TEXT, -- Top bar timer setting (format mm:ss)
    show_close_button INTEGER DEFAULT 1,
    payment_gateway TEXT DEFAULT 'woovi', -- 'woovi' or 'appmax'
    evolution_instance TEXT,
    evolution_token TEXT,
    evolution_url TEXT,
    deliverable_type TEXT,
    deliverable_text TEXT,
    deliverable_file TEXT,
    deliverable_email_subject TEXT,
    deliverable_email_body TEXT,
    twilio_account_sid TEXT,
    twilio_auth_token TEXT,
    twilio_from TEXT,
    twilio_content_sid TEXT,
    twilio_content_variables TEXT,
    twilio_message TEXT,
    twilio_media_url TEXT,
    sms_token TEXT,
    sms_message TEXT,
    pix_key TEXT,
    pix_receiver_name TEXT,
    pix_receiver_city TEXT,
    pix_whatsapp_number TEXT,
    pix_whatsapp_message TEXT,
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
    deliverable_type TEXT,
    deliverable_text TEXT,
    deliverable_file TEXT,
    deliverable_email_subject TEXT,
    deliverable_email_body TEXT,
    twilio_content_sid TEXT,
    twilio_content_variables TEXT,
    twilio_message TEXT,
    twilio_media_url TEXT,
    sms_message TEXT,
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
    gateway TEXT DEFAULT 'woovi', -- 'woovi' or 'appmax'
    transaction_id TEXT,
    external_id TEXT,
    cep TEXT,
    address TEXT,
    address_number TEXT,
    complement TEXT,
    neighborhood TEXT,
    city TEXT,
    state TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME,
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
    client_ip TEXT,
    json_payload TEXT, -- Stores extra data like value, currency, product strings
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Pix Key Rotations History (Woovi)
CREATE TABLE IF NOT EXISTS pix_key_rotations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    pix_key TEXT NOT NULL,
    type TEXT DEFAULT 'EVP',
    is_default INTEGER DEFAULT 1,
    status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE' or 'DELETED'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME
);

