CREATE DATABASE IF NOT EXISTS klps CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE klps;

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

CREATE TABLE knowledge_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE knowledge (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    problem TEXT NOT NULL,
    cause TEXT NULL,
    solution TEXT NOT NULL,
    procedure_steps TEXT NULL,
    best_practice TEXT NULL,
    status ENUM('draft','active','archived') NOT NULL DEFAULT 'active',
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES knowledge_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FULLTEXT KEY ft_knowledge (title, problem, cause, solution, procedure_steps, best_practice)
);

CREATE TABLE knowledge_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    knowledge_id INT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    problem TEXT NOT NULL,
    cause TEXT NULL,
    solution TEXT NOT NULL,
    procedure_steps TEXT NULL,
    best_practice TEXT NULL,
    changed_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_knowledge_version (knowledge_id, version_no),
    FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ai_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE ai_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender ENUM('user','ai','system') NOT NULL,
    message LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_time (conversation_id, created_at)
);

CREATE TABLE knowledge_bookmarks (
    user_id INT UNSIGNED NOT NULL,
    knowledge_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, knowledge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE
);

CREATE TABLE knowledge_ratings (
    user_id INT UNSIGNED NOT NULL,
    knowledge_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, knowledge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE
);

CREATE TABLE knowledge_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    knowledge_id INT UNSIGNED NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (knowledge_id) REFERENCES knowledge(id) ON DELETE CASCADE
);

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO departments (name) VALUES
('IT Support'),
('Network Administration'),
('Systems Administration'),
('Hardware Support'),
('IT Security');

INSERT INTO knowledge_categories (name) VALUES
('Hardware'),
('Network'),
('Wi-Fi'),
('Software'),
('Server'),
('Equipment');

-- Demo accounts. Replace these passwords after first login.
-- Password for both accounts: Password123!
INSERT INTO users (department_id, full_name, email, password_hash, role) VALUES
(1, 'System Administrator', 'admin@klps.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaA4z0N8jJ4Q/9E1K1m0Z6JpS1K', 'admin'),
(1, 'Demo Employee', 'employee@klps.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEaA4z0N8jJ4Q/9E1K1m0Z6JpS1K', 'employee');

INSERT INTO knowledge (category_id, department_id, created_by, title, problem, cause, solution, procedure_steps, best_practice)
VALUES
(3, 1, 1,
 'Laptop Connected to Wi-Fi but No Internet',
 'A laptop connects to office Wi-Fi but cannot access websites.',
 'Possible DNS, DHCP, adapter, or network configuration issue.',
 'Check the Wi-Fi connection, renew the network configuration, and test connectivity.',
 '1. Confirm Wi-Fi is connected. 2. Restart the network adapter. 3. Renew the IP configuration. 4. Test the gateway. 5. Test a website again.',
 'Document recurring network issues and record changes made during troubleshooting.');
