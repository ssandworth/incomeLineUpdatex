-- Budget Management System Schema
-- This schema creates tables for budget management and officer target tracking

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
    annual_budget DECIMAL(15,2) GENERATED ALWAYS AS (
        january_budget + february_budget + march_budget + april_budget + 
        may_budget + june_budget + july_budget + august_budget + 
        september_budget + october_budget + november_budget + december_budget
    ) STORED,
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

-- 2. Officer Monthly Targets Table
CREATE TABLE IF NOT EXISTS officer_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    officer_name VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    target_month TINYINT NOT NULL CHECK (target_month BETWEEN 1 AND 12),
    target_year YEAR NOT NULL,
    acct_id VARCHAR(50) NOT NULL,
    acct_desc VARCHAR(255) NOT NULL,
    monthly_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    daily_target DECIMAL(15,2) GENERATED ALWAYS AS (
        CASE 
            WHEN target_month IN (1,3,5,7,8,10,12) THEN monthly_target / 26  -- 31 days - ~5 Sundays
            WHEN target_month IN (4,6,9,11) THEN monthly_target / 26          -- 30 days - ~4 Sundays  
            WHEN target_month = 2 THEN 
                CASE 
                    WHEN (target_year % 4 = 0 AND target_year % 100 != 0) OR (target_year % 400 = 0) 
                    THEN monthly_target / 25  -- Leap year February - 29 days - ~4 Sundays
                    ELSE monthly_target / 24  -- Regular February - 28 days - 4 Sundays
                END
        END
    ) STORED,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_officer_target (officer_id, target_month, target_year, acct_id),
    INDEX idx_officer_month_year (officer_id, target_month, target_year),
    INDEX idx_target_period (target_month, target_year),
    INDEX idx_acct_id (acct_id),
    INDEX idx_department (department)
);

-- 3. Budget Performance Tracking Table
CREATE TABLE IF NOT EXISTS budget_performance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    acct_id VARCHAR(50) NOT NULL,
    performance_month TINYINT NOT NULL CHECK (performance_month BETWEEN 1 AND 12),
    performance_year YEAR NOT NULL,
    budgeted_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    actual_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    variance_amount DECIMAL(15,2) GENERATED ALWAYS AS (actual_amount - budgeted_amount) STORED,
    variance_percentage DECIMAL(8,2) GENERATED ALWAYS AS (
        CASE 
            WHEN budgeted_amount > 0 THEN ((actual_amount - budgeted_amount) / budgeted_amount) * 100
            ELSE 0
        END
    ) STORED,
    performance_status ENUM('Above Budget', 'On Budget', 'Below Budget') GENERATED ALWAYS AS (
        CASE 
            WHEN actual_amount > budgeted_amount * 1.05 THEN 'Above Budget'
            WHEN actual_amount >= budgeted_amount * 0.95 THEN 'On Budget'
            ELSE 'Below Budget'
        END
    ) STORED,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_performance (acct_id, performance_month, performance_year),
    INDEX idx_performance_period (performance_month, performance_year),
    INDEX idx_performance_status (performance_status)
);

-- 4. Officer Performance Tracking Table
CREATE TABLE IF NOT EXISTS officer_performance_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    performance_month TINYINT NOT NULL CHECK (performance_month BETWEEN 1 AND 12),
    performance_year YEAR NOT NULL,
    acct_id VARCHAR(50) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    achieved_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    achievement_percentage DECIMAL(8,2) GENERATED ALWAYS AS (
        CASE 
            WHEN target_amount > 0 THEN (achieved_amount / target_amount) * 100
            ELSE 0
        END
    ) STORED,
    performance_score DECIMAL(5,2) GENERATED ALWAYS AS (
        CASE 
            WHEN target_amount = 0 THEN 0
            WHEN achieved_amount >= target_amount * 1.5 THEN 100.00
            WHEN achieved_amount >= target_amount * 1.2 THEN 90.00
            WHEN achieved_amount >= target_amount THEN 80.00
            WHEN achieved_amount >= target_amount * 0.8 THEN 70.00
            WHEN achieved_amount >= target_amount * 0.6 THEN 60.00
            WHEN achieved_amount >= target_amount * 0.4 THEN 50.00
            WHEN achieved_amount >= target_amount * 0.2 THEN 40.00
            ELSE 30.00
        END
    ) STORED,
    performance_grade ENUM('A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F') GENERATED ALWAYS AS (
        CASE 
            WHEN target_amount = 0 THEN 'F'
            WHEN achieved_amount >= target_amount * 1.5 THEN 'A+'
            WHEN achieved_amount >= target_amount * 1.2 THEN 'A'
            WHEN achieved_amount >= target_amount THEN 'B+'
            WHEN achieved_amount >= target_amount * 0.8 THEN 'B'
            WHEN achieved_amount >= target_amount * 0.6 THEN 'C+'
            WHEN achieved_amount >= target_amount * 0.4 THEN 'C'
            WHEN achieved_amount >= target_amount * 0.2 THEN 'D'
            ELSE 'F'
        END
    ) STORED,
    working_days INT DEFAULT 0,
    total_transactions INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_officer_performance (officer_id, performance_month, performance_year, acct_id),
    INDEX idx_officer_period (officer_id, performance_month, performance_year),
    INDEX idx_performance_grade (performance_grade),
    INDEX idx_achievement_percentage (achievement_percentage)
);

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

-- Create views for easier reporting
CREATE OR REPLACE VIEW budget_summary_view AS
SELECT 
    bl.id,
    bl.acct_id,
    bl.acct_desc,
    bl.budget_year,
    bl.annual_budget,
    bl.status,
    COALESCE(SUM(bp.actual_amount), 0) as ytd_actual,
    COALESCE(SUM(bp.budgeted_amount), 0) as ytd_budget,
    CASE 
        WHEN SUM(bp.budgeted_amount) > 0 THEN 
            ((SUM(bp.actual_amount) - SUM(bp.budgeted_amount)) / SUM(bp.budgeted_amount)) * 100
        ELSE 0
    END as ytd_variance_percentage
FROM budget_lines bl
LEFT JOIN budget_performance bp ON bl.acct_id = bp.acct_id AND bl.budget_year = bp.performance_year
GROUP BY bl.id, bl.acct_id, bl.acct_desc, bl.budget_year, bl.annual_budget, bl.status;

CREATE OR REPLACE VIEW officer_performance_summary_view AS
SELECT 
    opt.officer_id,
    opt.officer_name,
    opt.department,
    opt.target_month,
    opt.target_year,
    COUNT(opt.id) as assigned_income_lines,
    SUM(opt.monthly_target) as total_monthly_target,
    SUM(COALESCE(opt_track.achieved_amount, 0)) as total_achieved,
    AVG(COALESCE(opt_track.achievement_percentage, 0)) as avg_achievement_percentage,
    AVG(COALESCE(opt_track.performance_score, 0)) as avg_performance_score,
    COUNT(CASE WHEN opt_track.performance_grade IN ('A+', 'A') THEN 1 END) as excellent_lines,
    COUNT(CASE WHEN opt_track.performance_grade IN ('B+', 'B') THEN 1 END) as good_lines,
    COUNT(CASE WHEN opt_track.performance_grade IN ('C+', 'C', 'D', 'F') THEN 1 END) as poor_lines
FROM officer_monthly_targets opt
LEFT JOIN officer_performance_tracking opt_track ON 
    opt.officer_id = opt_track.officer_id 
    AND opt.target_month = opt_track.performance_month 
    AND opt.target_year = opt_track.performance_year
    AND opt.acct_id = opt_track.acct_id
WHERE opt.status = 'Active'
GROUP BY opt.officer_id, opt.officer_name, opt.department, opt.target_month, opt.target_year;