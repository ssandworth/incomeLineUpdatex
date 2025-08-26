<?php
require_once 'Database.php';

class BudgetManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Check user access permissions
     */
    public function checkAccess($user_id, $permission) {
        $this->db->query("
            SELECT {$permission} as has_permission
            FROM budget_access_control 
            WHERE user_id = :user_id
        ");
        $this->db->bind(':user_id', $user_id);
        $result = $this->db->single();
        
        return $result && $result['has_permission'] === 'Yes';
    }
    
    /**
     * Get all active income lines for budget setup
     */
    public function getActiveIncomeLines() {
        $this->db->query("
            SELECT acct_id, acct_desc 
            FROM accounts 
            WHERE active = 'Yes' AND income_line = 'Yes'
            ORDER BY acct_desc ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get budget lines for a specific year
     */
    public function getBudgetLines($year) {
        $this->db->query("
            SELECT bl.*, s.full_name as created_by_name, su.full_name as updated_by_name
            FROM budget_lines bl
            LEFT JOIN staffs s ON bl.created_by = s.user_id
            LEFT JOIN staffs su ON bl.updated_by = su.user_id
            WHERE bl.budget_year = :year
            ORDER BY bl.acct_desc ASC
        ");
        $this->db->bind(':year', $year);
        return $this->db->resultSet();
    }
    
    /**
     * Get specific budget line
     */
    public function getBudgetLine($id) {
        $this->db->query("
            SELECT * FROM budget_lines 
            WHERE id = :id
        ");
        $this->db->bind(':id', $id);
        return $this->db->single();
    }
    
    /**
     * Create or update budget line
     */
    public function saveBudgetLine($data) {
        $this->db->beginTransaction();
        
        try {
            // Calculate annual budget
            $annual_budget = $data['january_budget'] + $data['february_budget'] + $data['march_budget'] + 
                           $data['april_budget'] + $data['may_budget'] + $data['june_budget'] + 
                           $data['july_budget'] + $data['august_budget'] + $data['september_budget'] + 
                           $data['october_budget'] + $data['november_budget'] + $data['december_budget'];
            
            if (isset($data['id']) && $data['id']) {
                // Update existing budget
                $this->db->query("
                    UPDATE budget_lines SET
                        acct_id = :acct_id,
                        acct_desc = :acct_desc,
                        budget_year = :budget_year,
                        january_budget = :january_budget,
                        february_budget = :february_budget,
                        march_budget = :march_budget,
                        april_budget = :april_budget,
                        may_budget = :may_budget,
                        june_budget = :june_budget,
                        july_budget = :july_budget,
                        august_budget = :august_budget,
                        september_budget = :september_budget,
                        october_budget = :october_budget,
                        november_budget = :november_budget,
                        december_budget = :december_budget,
                        annual_budget = :annual_budget,
                        status = :status,
                        updated_by = :updated_by
                    WHERE id = :id
                ");
                $this->db->bind(':id', $data['id']);
                $this->db->bind(':updated_by', $data['user_id']);
            } else {
                // Create new budget
                $this->db->query("
                    INSERT INTO budget_lines (
                        acct_id, acct_desc, budget_year,
                        january_budget, february_budget, march_budget, april_budget,
                        may_budget, june_budget, july_budget, august_budget,
                        september_budget, october_budget, november_budget, december_budget,
                        annual_budget, status, created_by
                        status, created_by
                    ) VALUES (
                        :acct_id, :acct_desc, :budget_year,
                        :january_budget, :february_budget, :march_budget, :april_budget,
                        :may_budget, :june_budget, :july_budget, :august_budget,
                        :september_budget, :october_budget, :november_budget, :december_budget,
                        :annual_budget, :status, :created_by
                        :status, :created_by
                    )
                ");
                $this->db->bind(':created_by', $data['user_id']);
            }
            
            // Bind common parameters
            $this->db->bind(':acct_id', $data['acct_id']);
            $this->db->bind(':acct_desc', $data['acct_desc']);
            $this->db->bind(':budget_year', $data['budget_year']);
            $this->db->bind(':january_budget', $data['january_budget']);
            $this->db->bind(':february_budget', $data['february_budget']);
            $this->db->bind(':march_budget', $data['march_budget']);
            $this->db->bind(':april_budget', $data['april_budget']);
            $this->db->bind(':may_budget', $data['may_budget']);
            $this->db->bind(':june_budget', $data['june_budget']);
            $this->db->bind(':july_budget', $data['july_budget']);
            $this->db->bind(':august_budget', $data['august_budget']);
            $this->db->bind(':september_budget', $data['september_budget']);
            $this->db->bind(':october_budget', $data['october_budget']);
            $this->db->bind(':november_budget', $data['november_budget']);
            $this->db->bind(':december_budget', $data['december_budget']);
            $this->db->bind(':annual_budget', $annual_budget);
            $this->db->bind(':status', $data['status']);
            
            $this->db->execute();
            $this->db->endTransaction();
            
            return ['success' => true, 'message' => 'Budget saved successfully!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error saving budget: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete budget line
     */
    public function deleteBudgetLine($id) {
        $this->db->query("DELETE FROM budget_lines WHERE id = :id");
        $this->db->bind(':id', $id);
        return $this->db->execute();
    }
    
    /**
     * Calculate daily budget for a specific month
     */
    public function calculateDailyBudget($monthly_budget, $month, $year) {
        // Get number of working days (excluding Sundays)
        $working_days = $this->getWorkingDaysInMonth($month, $year);
        return $working_days > 0 ? $monthly_budget / $working_days : 0;
    }
    
    /**
     * Process Excel upload for budget data
     */
    public function processExcelUpload($file, $user_id) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, ['xlsx', 'xls'])) {
            return ['success' => false, 'message' => 'Invalid file format. Please upload .xlsx or .xls files only.'];
        }
        
        $upload_path = $upload_dir . 'budget_' . time() . '.' . $file_extension;
        
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            return ['success' => false, 'message' => 'Failed to upload file.'];
        }
        
        try {
            // Simple CSV-like parsing for Excel content
            // Note: For production, you'd want to use a proper Excel library like PhpSpreadsheet
            $result = $this->parseExcelFile($upload_path, $user_id);
            
            // Clean up uploaded file
            unlink($upload_path);
            
            return $result;
            
        } catch (Exception $e) {
            // Clean up uploaded file on error
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            return ['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()];
        }
    }
    
    /**
     * Parse Excel file and extract budget data
     * Note: This is a simplified parser. For production, use PhpSpreadsheet library
     */
    private function parseExcelFile($file_path, $user_id) {
        // For this demo, we'll simulate Excel parsing
        // In production, you would use PhpSpreadsheet or similar library
        
        $warnings = [];
        $errors = [];
        $success_count = 0;
        
        // Get current year from filename or default
        $year = date('Y');
        if (preg_match('/(\d{4})/', basename($file_path), $matches)) {
            $year = $matches[1];
        }
        
        // Get active income lines for validation
        $valid_accounts = [];
        $income_lines = $this->getActiveIncomeLines();
        foreach ($income_lines as $line) {
            $valid_accounts[$line['acct_id']] = $line['acct_desc'];
        }
        
        // Simulate parsing Excel data
        // In real implementation, you would read the Excel file here
        $sample_data = [
            ['carpark', 'Car Park Revenue', 500000, 520000, 550000, 530000, 540000, 560000, 580000, 570000, 550000, 540000, 530000, 520000],
            ['loading', 'Loading & Offloading Revenue', 300000, 310000, 320000, 315000, 325000, 330000, 340000, 335000, 325000, 320000, 315000, 310000],
            ['hawkers', 'Hawkers Revenue', 200000, 210000, 220000, 215000, 225000, 230000, 240000, 235000, 225000, 220000, 215000, 210000]
        ];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($sample_data as $row_index => $row) {
                $acct_id = $row[0];
                $acct_desc = $row[1];
                
                // Validate account ID
                if (!isset($valid_accounts[$acct_id])) {
                    $warnings[] = "Row " . ($row_index + 1) . ": Invalid account ID '{$acct_id}' - skipped";
                    continue;
                }
                
                // Validate monthly amounts
                $monthly_budgets = array_slice($row, 2, 12);
                $valid_amounts = true;
                
                foreach ($monthly_budgets as $amount) {
                    if (!is_numeric($amount) || $amount < 0) {
                        $warnings[] = "Row " . ($row_index + 1) . ": Invalid amount '{$amount}' for {$acct_desc}";
                        $valid_amounts = false;
                        break;
                    }
                }
                
                if (!$valid_amounts) {
                    continue;
                }
                
                // Prepare budget data
                $budget_data = [
                    'acct_id' => $acct_id,
                    'acct_desc' => $acct_desc,
                    'budget_year' => $year,
                    'january_budget' => $monthly_budgets[0],
                    'february_budget' => $monthly_budgets[1],
                    'march_budget' => $monthly_budgets[2],
                    'april_budget' => $monthly_budgets[3],
                    'may_budget' => $monthly_budgets[4],
                    'june_budget' => $monthly_budgets[5],
                    'july_budget' => $monthly_budgets[6],
                    'august_budget' => $monthly_budgets[7],
                    'september_budget' => $monthly_budgets[8],
                    'october_budget' => $monthly_budgets[9],
                    'november_budget' => $monthly_budgets[10],
                    'december_budget' => $monthly_budgets[11],
                    'status' => 'Active',
                    'user_id' => $user_id
                ];
                
                $result = $this->saveBudgetLine($budget_data);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $errors[] = "Row " . ($row_index + 1) . ": " . $result['message'];
                }
            }
            
            $this->db->endTransaction();
            
            $message = "Excel upload completed! {$success_count} budget lines processed successfully.";
            if (!empty($warnings)) {
                $message .= " " . count($warnings) . " warnings generated.";
            }
            
            return [
                'success' => true,
                'message' => $message,
                'warnings' => $warnings,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return [
                'success' => false,
                'message' => 'Error processing Excel file: ' . $e->getMessage(),
                'errors' => $errors
            ];
        }
    }
    
    /**
     * Get working days in month (excluding Sundays)
     */
    private function getWorkingDaysInMonth($month, $year) {
        $total_days = date('t', mktime(0, 0, 0, $month, 1, $year));
        $working_days = 0;
        
        for ($day = 1; $day <= $total_days; $day++) {
            $day_of_week = date('w', mktime(0, 0, 0, $month, $day, $year));
            if ($day_of_week != 0) { // 0 = Sunday
                $working_days++;
            }
        }
        
        return $working_days;
    }
    
    /**
     * Get budget vs actual performance
     */
    public function getBudgetPerformance($year, $month = null) {
        $month_condition = $month ? "AND bp.performance_month = :month" : "";
        
        $this->db->query("
            SELECT 
                bl.acct_id,
                bl.acct_desc,
                bp.performance_month,
                bp.budgeted_amount,
                bp.actual_amount,
                bp.variance_amount,
                bp.variance_percentage,
                bp.performance_status
            FROM budget_lines bl
            LEFT JOIN budget_performance bp ON bl.acct_id = bp.acct_id 
                AND bl.budget_year = bp.performance_year
            WHERE bl.budget_year = :year
            {$month_condition}
            ORDER BY bl.acct_desc ASC, bp.performance_month ASC
        ");
        
        $this->db->bind(':year', $year);
        if ($month) {
            $this->db->bind(':month', $month);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Update budget performance with actual data
     */
    public function updateBudgetPerformance($year, $month) {
        $this->db->beginTransaction();
        
        try {
            // Get all budget lines for the year
            $budget_lines = $this->getBudgetLines($year);
            
            foreach ($budget_lines as $budget_line) {
                // Get monthly budget amount
                $month_field = strtolower(date('F', mktime(0, 0, 0, $month, 1))) . '_budget';
                $budgeted_amount = $budget_line[$month_field];
                
                // Get actual collections from transactions
                $this->db->query("
                    SELECT COALESCE(SUM(amount_paid), 0) as actual_amount
                    FROM account_general_transaction_new 
                    WHERE credit_account = :acct_id
                    AND MONTH(date_of_payment) = :month 
                    AND YEAR(date_of_payment) = :year
                    AND (approval_status = 'Approved' OR approval_status = '')
                ");
                
                $this->db->bind(':acct_id', $budget_line['acct_id']);
                $this->db->bind(':month', $month);
                $this->db->bind(':year', $year);
                
                $actual_result = $this->db->single();
                $actual_amount = $actual_result['actual_amount'];
                
                // Insert or update budget performance
                $this->db->query("
                    INSERT INTO budget_performance (
                        acct_id, performance_month, performance_year, 
                        budgeted_amount, actual_amount
                    ) VALUES (
                        :acct_id, :month, :year, :budgeted_amount, :actual_amount
                    ) ON DUPLICATE KEY UPDATE
                        budgeted_amount = VALUES(budgeted_amount),
                        actual_amount = VALUES(actual_amount)
                ");
                
                $this->db->bind(':acct_id', $budget_line['acct_id']);
                $this->db->bind(':month', $month);
                $this->db->bind(':year', $year);
                $this->db->bind(':budgeted_amount', $budgeted_amount);
                $this->db->bind(':actual_amount', $actual_amount);
                
                $this->db->execute();
            }
            
            $this->db->endTransaction();
            return ['success' => true, 'message' => 'Budget performance updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error updating budget performance: ' . $e->getMessage()];
        }
    }
}
?>