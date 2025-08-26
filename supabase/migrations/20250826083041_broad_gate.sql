/*
  # Budget Management System Schema for MySQL 5.6/XAMPP 5.6.40
  
  This migration creates the budget management system tables compatible with MySQL 5.6.
  Since MySQL 5.6 doesn't support generated columns, we'll use triggers and manual calculations.
  
  1. New Tables
    - `budget_lines` - Annual budget planning by income line
    - `officer_monthly_targets` - Monthly collection targets for officers  
    - `budget_performance` - Budget vs actual performance tracking
    - `officer_performance_tracking` - Officer target achievement tracking
    - `budget_access_control` - User permissions for budget features
  
  2. Security
    - Proper indexing for performance
    - Unique constraints to prevent duplicates
    - Foreign key relationships where applicable
  
  3. Compatibility
    - No generated columns (MySQL 5.6 limitation)
    - Manual calculation of derived fields
    - Triggers for automatic calculations where needed
*/

-- 1. Budget Lines Table
CREATE TABLE IF NOT EXISTS budget_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acct_id VARCHAR(50) NOT NULL,
    acct_desc VARCHAR(255) NOT NULL,
    budget_year YEAR NOT NULL,
    january_budget DECIMAL(15,2) DEFAULT 0.00,
    february_budget DECIMAL(15,2) DEFAULT 0.00,
    march_budget DECIMAL(15,2) DEFAULT 0.00,
    april_budget DECIMAL(15,2) DEFAULT 0.00,
    may_budget DECIMAL(15,2) DEFAULT 0.00,
    june_budget DECIMAL(15,2) DEFAULT 0.00,
    july_budget DECIMAL(15,2) DEFAULT 0.00,
    august_budget DECIMAL(15,2) DEFAULT 0.00,
    september_budget DECIMAL(15,2) DEFAULT 0.00,
    october_budget DECIMAL(15,2) DEFAULT 0.00,
    november_budget DECIMAL(15,2) DEFAULT 0.00,
    december_budget DECIMAL(15,2) DEFAULT 0.00,
    annual_budget DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_budget_line (acct_id, budget_year),
    INDEX idx_budget_year (budget_year),
    INDEX idx_acct_id (acct_id),
    INDEX idx_status (status)
);

-- Trigger to calculate annual_budget automatically
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS budget_lines_annual_calculation 
BEFORE INSERT ON budget_lines
FOR EACH ROW
BEGIN
    SET NEW.annual_budget = NEW.january_budget + NEW.february_budget + NEW.march_budget + NEW.april_budget + 
                           NEW.may_budget + NEW.june_budget + NEW.july_budget + NEW.august_budget + 
                           NEW.september_budget + NEW.october_budget + NEW.november_budget + NEW.december_budget;
END$$

CREATE TRIGGER IF NOT EXISTS budget_lines_annual_calculation_update 
BEFORE UPDATE ON budget_lines
FOR EACH ROW
BEGIN
    SET NEW.annual_budget = NEW.january_budget + NEW.february_budget + NEW.march_budget + NEW.april_budget + 
                           NEW.may_budget + NEW.june_budget + NEW.july_budget + NEW.august_budget + 
                           NEW.september_budget + NEW.october_budget + NEW.november_budget + NEW.december_budget;
END$$
DELIMITER ;

-- 2. Officer Monthly Targets Table
CREATE TABLE IF NOT EXISTS officer_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    officer_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    target_month TINYINT NOT NULL,
    target_year YEAR NOT NULL,
    acct_id VARCHAR(50) NOT NULL,
    acct_desc VARCHAR(255) NOT NULL,
    monthly_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    daily_target DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_officer_target (officer_id, target_month, target_year, acct_id),
    INDEX idx_officer_month_year (officer_id, target_month, target_year),
    INDEX idx_target_period (target_month, target_year),
    INDEX idx_acct_id (acct_id),
    INDEX idx_department (department),
    CHECK (target_month BETWEEN 1 AND 12)
);

-- Trigger to calculate daily_target automatically
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS officer_targets_daily_calculation 
BEFORE INSERT ON officer_monthly_targets
FOR EACH ROW
BEGIN
    DECLARE working_days INT DEFAULT 26;
    
    -- Calculate working days based on month (excluding Sundays)
    CASE NEW.target_month
        WHEN 1 THEN SET working_days = 26;  -- January: 31 days - ~5 Sundays
        WHEN 2 THEN 
            -- February: Check for leap year
            IF ((NEW.target_year % 4 = 0 AND NEW.target_year % 100 != 0) OR (NEW.target_year % 400 = 0)) THEN
                SET working_days = 25;  -- Leap year: 29 days - ~4 Sundays
            ELSE
                SET working_days = 24;  -- Regular year: 28 days - 4 Sundays
            END IF;
        WHEN 3 THEN SET working_days = 26;  -- March: 31 days - ~5 Sundays
        WHEN 4 THEN SET working_days = 26;  -- April: 30 days - ~4 Sundays
        WHEN 5 THEN SET working_days = 26;  -- May: 31 days - ~5 Sundays
        WHEN 6 THEN SET working_days = 26;  -- June: 30 days - ~4 Sundays
        WHEN 7 THEN SET working_days = 26;  -- July: 31 days - ~5 Sundays
        WHEN 8 THEN SET working_days = 26;  -- August: 31 days - ~5 Sundays
        WHEN 9 THEN SET working_days = 26;  -- September: 30 days - ~4 Sundays
        WHEN 10 THEN SET working_days = 26; -- October: 31 days - ~5 Sundays
        WHEN 11 THEN SET working_days = 26; -- November: 30 days - ~4 Sundays
        WHEN 12 THEN SET working_days = 26; -- December: 31 days - ~5 Sundays
    END CASE;
    
    SET NEW.daily_target = CASE WHEN working_days > 0 THEN NEW.monthly_target / working_days ELSE 0 END;
END$$

CREATE TRIGGER IF NOT EXISTS officer_targets_daily_calculation_update 
BEFORE UPDATE ON officer_monthly_targets
FOR EACH ROW
BEGIN
    DECLARE working_days INT DEFAULT 26;
    
    -- Calculate working days based on month (excluding Sundays)
    CASE NEW.target_month
        WHEN 1 THEN SET working_days = 26;
        WHEN 2 THEN 
            IF ((NEW.target_year % 4 = 0 AND NEW.target_year % 100 != 0) OR (NEW.target_year % 400 = 0)) THEN
                SET working_days = 25;
            ELSE
                SET working_days = 24;
            END IF;
        WHEN 3 THEN SET working_days = 26;
        WHEN 4 THEN SET working_days = 26;
        WHEN 5 THEN SET working_days = 26;
        WHEN 6 THEN SET working_days = 26;
        WHEN 7 THEN SET working_days = 26;
        WHEN 8 THEN SET working_days = 26;
        WHEN 9 THEN SET working_days = 26;
        WHEN 10 THEN SET working_days = 26;
        WHEN 11 THEN SET working_days = 26;
        WHEN 12 THEN SET working_days = 26;
    END CASE;
    
    SET NEW.daily_target = CASE WHEN working_days > 0 THEN NEW.monthly_target / working_days ELSE 0 END;
END$$
DELIMITER ;

-- 3. Budget Performance Tracking Table
CREATE TABLE IF NOT EXISTS budget_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acct_id VARCHAR(50) NOT NULL,
    performance_month TINYINT NOT NULL,
    performance_year YEAR NOT NULL,
    budgeted_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    actual_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    variance_amount DECIMAL(15,2) DEFAULT 0.00,
    variance_percentage DECIMAL(8,2) DEFAULT 0.00,
    performance_status VARCHAR(20) DEFAULT 'Below Budget',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_performance (acct_id, performance_month, performance_year),
    INDEX idx_performance_period (performance_month, performance_year),
    INDEX idx_performance_status (performance_status),
    CHECK (performance_month BETWEEN 1 AND 12)
);

-- Trigger to calculate variance and status automatically
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS budget_performance_calculation 
BEFORE INSERT ON budget_performance
FOR EACH ROW
BEGIN
    SET NEW.variance_amount = NEW.actual_amount - NEW.budgeted_amount;
    SET NEW.variance_percentage = CASE 
        WHEN NEW.budgeted_amount > 0 THEN ((NEW.actual_amount - NEW.budgeted_amount) / NEW.budgeted_amount) * 100
        ELSE 0
    END;
    SET NEW.performance_status = CASE 
        WHEN NEW.actual_amount > NEW.budgeted_amount * 1.05 THEN 'Above Budget'
        WHEN NEW.actual_amount >= NEW.budgeted_amount * 0.95 THEN 'On Budget'
        ELSE 'Below Budget'
    END;
END$$

CREATE TRIGGER IF NOT EXISTS budget_performance_calculation_update 
BEFORE UPDATE ON budget_performance
FOR EACH ROW
BEGIN
    SET NEW.variance_amount = NEW.actual_amount - NEW.budgeted_amount;
    SET NEW.variance_percentage = CASE 
        WHEN NEW.budgeted_amount > 0 THEN ((NEW.actual_amount - NEW.budgeted_amount) / NEW.budgeted_amount) * 100
        ELSE 0
    END;
    SET NEW.performance_status = CASE 
        WHEN NEW.actual_amount > NEW.budgeted_amount * 1.05 THEN 'Above Budget'
        WHEN NEW.actual_amount >= NEW.budgeted_amount * 0.95 THEN 'On Budget'
        ELSE 'Below Budget'
    END;
END$$
DELIMITER ;

-- 4. Officer Performance Tracking Table
CREATE TABLE IF NOT EXISTS officer_performance_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    performance_month TINYINT NOT NULL,
    performance_year YEAR NOT NULL,
    acct_id VARCHAR(50) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    achieved_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    achievement_percentage DECIMAL(8,2) DEFAULT 0.00,
    performance_score DECIMAL(5,2) DEFAULT 0.00,
    performance_grade VARCHAR(2) DEFAULT 'F',
    working_days INT DEFAULT 0,
    total_transactions INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_officer_performance (officer_id, performance_month, performance_year, acct_id),
    INDEX idx_officer_period (officer_id, performance_month, performance_year),
    INDEX idx_performance_grade (performance_grade),
    INDEX idx_achievement_percentage (achievement_percentage),
    CHECK (performance_month BETWEEN 1 AND 12)
);

-- Trigger to calculate performance metrics automatically
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS officer_performance_calculation 
BEFORE INSERT ON officer_performance_tracking
FOR EACH ROW
BEGIN
    -- Calculate achievement percentage
    SET NEW.achievement_percentage = CASE 
        WHEN NEW.target_amount > 0 THEN (NEW.achieved_amount / NEW.target_amount) * 100
        ELSE 0
    END;
    
    -- Calculate performance score
    SET NEW.performance_score = CASE 
        WHEN NEW.target_amount = 0 THEN 0
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.5 THEN 100.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.2 THEN 90.00
        WHEN NEW.achieved_amount >= NEW.target_amount THEN 80.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.8 THEN 70.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.6 THEN 60.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.4 THEN 50.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.2 THEN 40.00
        ELSE 30.00
    END;
    
    -- Calculate performance grade
    SET NEW.performance_grade = CASE 
        WHEN NEW.target_amount = 0 THEN 'F'
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.5 THEN 'A+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.2 THEN 'A'
        WHEN NEW.achieved_amount >= NEW.target_amount THEN 'B+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.8 THEN 'B'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.6 THEN 'C+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.4 THEN 'C'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.2 THEN 'D'
        ELSE 'F'
    END;
END$$

CREATE TRIGGER IF NOT EXISTS officer_performance_calculation_update 
BEFORE UPDATE ON officer_performance_tracking
FOR EACH ROW
BEGIN
    -- Calculate achievement percentage
    SET NEW.achievement_percentage = CASE 
        WHEN NEW.target_amount > 0 THEN (NEW.achieved_amount / NEW.target_amount) * 100
        ELSE 0
    END;
    
    -- Calculate performance score
    SET NEW.performance_score = CASE 
        WHEN NEW.target_amount = 0 THEN 0
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.5 THEN 100.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.2 THEN 90.00
        WHEN NEW.achieved_amount >= NEW.target_amount THEN 80.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.8 THEN 70.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.6 THEN 60.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.4 THEN 50.00
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.2 THEN 40.00
        ELSE 30.00
    END;
    
    -- Calculate performance grade
    SET NEW.performance_grade = CASE 
        WHEN NEW.target_amount = 0 THEN 'F'
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.5 THEN 'A+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 1.2 THEN 'A'
        WHEN NEW.achieved_amount >= NEW.target_amount THEN 'B+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.8 THEN 'B'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.6 THEN 'C+'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.4 THEN 'C'
        WHEN NEW.achieved_amount >= NEW.target_amount * 0.2 THEN 'D'
        ELSE 'F'
    END;
END$$
DELIMITER ;

-- 5. Budget Access Control Table
CREATE TABLE IF NOT EXISTS budget_access_control (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    department VARCHAR(100) NOT NULL,
    can_create_budget ENUM('Yes', 'No') DEFAULT 'No',
    can_edit_budget ENUM('Yes', 'No') DEFAULT 'No',
    can_delete_budget ENUM('Yes', 'No') DEFAULT 'No',
    can_view_budget ENUM('Yes', 'No') DEFAULT 'Yes',
    can_manage_targets ENUM('Yes', 'No') DEFAULT 'No',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_access (user_id),
    INDEX idx_department (department)
);

-- Insert default access permissions
INSERT IGNORE INTO budget_access_control (user_id, department, can_create_budget, can_edit_budget, can_delete_budget, can_view_budget, can_manage_targets) VALUES
(1, 'Accounts', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes'),
(2, 'Audit/Inspections', 'No', 'Yes', 'No', 'Yes', 'Yes'),
(3, 'IT/E-Business', 'Yes', 'Yes', 'Yes', 'Yes', 'Yes');

-- 6. Sample Accounts Table (if not exists)
CREATE TABLE IF NOT EXISTS accounts (
    acct_id VARCHAR(50) PRIMARY KEY,
    acct_desc VARCHAR(255) NOT NULL,
    acct_code VARCHAR(20),
    acct_table_name VARCHAR(100),
    acct_alias VARCHAR(50),
    income_line ENUM('Yes', 'No') DEFAULT 'No',
    active ENUM('Yes', 'No') DEFAULT 'Yes',
    gl_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_income_line (income_line),
    INDEX idx_active (active)
);

-- Insert sample income lines if accounts table is empty
INSERT IGNORE INTO accounts (acct_id, acct_desc, acct_code, acct_table_name, acct_alias, income_line, active) VALUES
('carpark', 'Car Park Revenue', 'CP001', 'car_park_ledger', 'carpark', 'Yes', 'Yes'),
('loading', 'Loading & Offloading Revenue', 'LD001', 'loading_ledger', 'loading', 'Yes', 'Yes'),
('hawkers', 'Hawkers Revenue', 'HW001', 'hawkers_ledger', 'hawkers', 'Yes', 'Yes'),
('wheelbarrow', 'WheelBarrow Revenue', 'WB001', 'wheelbarrow_ledger', 'wheelbarrow', 'Yes', 'Yes'),
('daily_trade', 'Daily Trade Revenue', 'DT001', 'daily_trade_ledger', 'daily_trade', 'Yes', 'Yes'),
('abattoir', 'Abattoir Revenue', 'AB001', 'abattoir_ledger', 'abattoir', 'Yes', 'Yes'),
('overnight_parking', 'Overnight Parking Revenue', 'OP001', 'overnight_parking_ledger', 'overnight_parking', 'Yes', 'Yes'),
('scroll_board', 'Scroll Board Revenue', 'SB001', 'scroll_board_ledger', 'scroll_board', 'Yes', 'Yes'),
('other_pos', 'Other POS Revenue', 'POS001', 'other_pos_ledger', 'other_pos', 'Yes', 'Yes'),
('car_sticker', 'Car Sticker Revenue', 'CS001', 'car_sticker_ledger', 'car_sticker', 'Yes', 'Yes');

-- 7. Sample Staffs Table (if not exists)
CREATE TABLE IF NOT EXISTS staffs (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    department VARCHAR(100) NOT NULL,
    level VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(255),
    active ENUM('Yes', 'No') DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_department (department),
    INDEX idx_active (active)
);

-- Insert sample staff if staffs table is empty
INSERT IGNORE INTO staffs (user_id, full_name, first_name, last_name, department, level, phone, email) VALUES
(1, 'John Doe', 'John', 'Doe', 'Accounts', 'manager', '08012345678', 'john.doe@example.com'),
(2, 'Jane Smith', 'Jane', 'Smith', 'Audit/Inspections', 'auditor', '08012345679', 'jane.smith@example.com'),
(3, 'Mike Johnson', 'Mike', 'Johnson', 'IT/E-Business', 'developer', '08012345680', 'mike.johnson@example.com'),
(4, 'Sarah Wilson', 'Sarah', 'Wilson', 'Wealth Creation', 'officer', '08012345681', 'sarah.wilson@example.com'),
(5, 'David Brown', 'David', 'Brown', 'Wealth Creation', 'officer', '08012345682', 'david.brown@example.com'),
(6, 'Lisa Davis', 'Lisa', 'Davis', 'Leasing', 'officer', '08012345683', 'lisa.davis@example.com');

-- 8. Sample Staffs Others Table (if not exists)
CREATE TABLE IF NOT EXISTS staffs_others (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    active ENUM('Yes', 'No') DEFAULT 'Yes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_department (department)
);

-- Insert sample other staff
INSERT IGNORE INTO staffs_others (id, full_name, department, phone) VALUES
(1, 'Robert Taylor', 'Security', '08012345684'),
(2, 'Emily Clark', 'Cleaning', '08012345685'),
(3, 'James Wilson', 'Maintenance', '08012345686');

-- 9. Sample Transaction Table (if not exists)
CREATE TABLE IF NOT EXISTS account_general_transaction_new (
    id VARCHAR(50) PRIMARY KEY,
    date_of_payment DATE NOT NULL,
    transaction_desc TEXT,
    receipt_no VARCHAR(20),
    amount_paid DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    remitting_id INT,
    remitting_staff VARCHAR(255),
    posting_officer_id INT,
    posting_officer_name VARCHAR(255),
    posting_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_status VARCHAR(20) DEFAULT '',
    verification_status VARCHAR(20) DEFAULT '',
    debit_account VARCHAR(50),
    credit_account VARCHAR(50),
    payment_category VARCHAR(100),
    income_line VARCHAR(100),
    INDEX idx_date_payment (date_of_payment),
    INDEX idx_credit_account (credit_account),
    INDEX idx_remitting_id (remitting_id),
    INDEX idx_approval_status (approval_status)
);

-- 10. Insert sample budget data for demonstration
INSERT IGNORE INTO budget_lines (acct_id, acct_desc, budget_year, january_budget, february_budget, march_budget, april_budget, may_budget, june_budget, july_budget, august_budget, september_budget, october_budget, november_budget, december_budget, status, created_by) VALUES
('carpark', 'Car Park Revenue', 2025, 500000, 520000, 550000, 530000, 540000, 560000, 580000, 570000, 550000, 540000, 530000, 520000, 'Active', 1),
('loading', 'Loading & Offloading Revenue', 2025, 300000, 310000, 320000, 315000, 325000, 330000, 340000, 335000, 325000, 320000, 315000, 310000, 'Active', 1),
('hawkers', 'Hawkers Revenue', 2025, 200000, 210000, 220000, 215000, 225000, 230000, 240000, 235000, 225000, 220000, 215000, 210000, 'Active', 1);

-- 11. Insert sample officer targets for current month
INSERT IGNORE INTO officer_monthly_targets (officer_id, officer_name, department, target_month, target_year, acct_id, acct_desc, monthly_target, status, created_by) VALUES
(4, 'Sarah Wilson', 'Wealth Creation', MONTH(NOW()), YEAR(NOW()), 'carpark', 'Car Park Revenue', 150000, 'Active', 1),
(4, 'Sarah Wilson', 'Wealth Creation', MONTH(NOW()), YEAR(NOW()), 'loading', 'Loading & Offloading Revenue', 100000, 'Active', 1),
(5, 'David Brown', 'Wealth Creation', MONTH(NOW()), YEAR(NOW()), 'carpark', 'Car Park Revenue', 120000, 'Active', 1),
(5, 'David Brown', 'Wealth Creation', MONTH(NOW()), YEAR(NOW()), 'hawkers', 'Hawkers Revenue', 80000, 'Active', 1);

-- 12. Insert sample performance data
INSERT IGNORE INTO budget_performance (acct_id, performance_month, performance_year, budgeted_amount, actual_amount) VALUES
('carpark', 1, 2025, 500000, 520000),
('carpark', 2, 2025, 520000, 510000),
('loading', 1, 2025, 300000, 285000),
('loading', 2, 2025, 310000, 320000),
('hawkers', 1, 2025, 200000, 195000),
('hawkers', 2, 2025, 210000, 215000);

-- 13. Create uploads directory table for file tracking
CREATE TABLE IF NOT EXISTS budget_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    uploaded_by INT NOT NULL,
    file_size INT DEFAULT 0,
    status ENUM('Processing', 'Completed', 'Failed') DEFAULT 'Processing',
    records_processed INT DEFAULT 0,
    errors_count INT DEFAULT 0,
    INDEX idx_upload_date (upload_date),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_status (status)
);