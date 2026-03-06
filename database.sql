-- دروستکردنی داتابەیس
CREATE DATABASE IF NOT EXISTS ranj_grup;
USE ranj_grup;

-- خشتەی بەکارهێنەران
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
    api_key VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- خشتەی کڕیاران
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    address TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- خشتەی قیاسەکان
CREATE TABLE measurements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    measurement_date DATE NOT NULL,
    chest_cm DECIMAL(5,2),
    waist_cm DECIMAL(5,2),
    hips_cm DECIMAL(5,2),
    shoulder_cm DECIMAL(5,2),
    sleeve_length_cm DECIMAL(5,2),
    dress_length_cm DECIMAL(5,2),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- خشتەی قەرزەکان
CREATE TABLE debts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    fabric_type VARCHAR(100),
    fabric_quantity DECIMAL(10,2),
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) DEFAULT 0,
    remaining_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    debt_date DATE NOT NULL,
    expected_payment_date DATE,
    notes TEXT,
    status ENUM('active', 'paid', 'overdue') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- خشتەی پارەدانەکان
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    debt_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- خشتەی تۆمارەکان (Logs)
CREATE TABLE logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    user_name VARCHAR(100),
    action_type VARCHAR(50),
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- زیادکردنی بەکارهێنەرەکان (کلیلەکان)
INSERT INTO users (username, full_name, role, api_key) VALUES
('admin', 'بەڕێوەبەری سەرەکی', 'admin', 'key_admin_001'),
('employee1', 'مووچەخۆر ١', 'employee', 'key_emp_001'),
('employee2', 'مووچەخۆر ٢', 'employee', 'key_emp_002'),
('employee3', 'مووچەخۆر ٣', 'employee', 'key_emp_003'),
('employee4', 'مووچەخۆر ٤', 'employee', 'key_emp_004');