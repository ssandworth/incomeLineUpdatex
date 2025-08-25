<?php
require_once 'Database.php';

class OfficerTargetManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all officers eligible for targets
     */
    public function getEligibleOfficers() {
        $this->db->query("
            SELECT user_id, full_name, department 
            FROM staffs 
            WHERE department IN ('Wealth Creation', 'Leasing')
            ORDER BY department ASC, full_name ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get officer targets for specific period
     */
    public function getOfficerTargets($officer_id, $month, $year) {
        $this->db->query("
            SELECT omt.*, a.acct_desc as income_line_desc
            FROM officer_monthly_targets omt
            LEFT JOIN accounts a ON omt.acct_id = a.acct_id
            WHERE omt.officer_id = :officer_id 
            AND omt.target_month = :month 
            AND omt.target_year = :year
            AND omt.status = 'Active'
            ORDER BY omt.acct_desc ASC
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get all officer targets for management view
     */
    public function getAllOfficerTargets($month, $year) {
        $this->db->query("
            SELECT 
                omt.officer_id,
                omt.officer_name,
                omt.department,
                COUNT(omt.id) as assigned_lines,
                SUM(omt.monthly_target) as total_target,
                AVG(omt.daily_target) as avg_daily_target
            FROM officer_monthly_targets omt
            WHERE omt.target_month = :month 
            AND omt.target_year = :year
            AND omt.status = 'Active'
            GROUP BY omt.officer_id, omt.officer_name, omt.department
            ORDER BY total_target DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Save officer target
     */
    public function saveOfficerTarget($data) {
        $this->db->beginTransaction();
        
        try {
            // Calculate daily target (excluding Sundays)
            $working_days = $this->getWorkingDaysInMonth($data['target_month'], $data['target_year']);
            $daily_target = $working_days > 0 ? $data['monthly_target'] / $working_days : 0;
            
            // Get officer information
            $this->db->query("SELECT full_name, department FROM staffs WHERE user_id = :officer_id");
            $this->db->bind(':officer_id', $data['officer_id']);
            $officer = $this->db->single();
            
            // Get account description
            $this->db->query("SELECT acct_desc FROM accounts WHERE acct_id = :acct_id");
            $this->db->bind(':acct_id', $data['acct_id']);
            $account = $this->db->single();
            
            if (isset($data['target_id']) && $data['target_id']) {
                // Update existing target
                $this->db->query("
                    UPDATE officer_monthly_targets SET
                        monthly_target = :monthly_target,
                        daily_target = :daily_target,
                        status = :status,
                        updated_by = :updated_by
                    WHERE id = :target_id
                ");
                $this->db->bind(':target_id', $data['target_id']);
                $this->db->bind(':updated_by', $data['user_id']);
            } else {
                // Create new target
                $this->db->query("
                    INSERT INTO officer_monthly_targets (
                        officer_id, officer_name, department, target_month, target_year,
                        acct_id, acct_desc, monthly_target, daily_target, status, created_by
                    ) VALUES (
                        :officer_id, :officer_name, :department, :target_month, :target_year,
                        :acct_id, :acct_desc, :monthly_target, :daily_target, :status, :created_by
                    ) ON DUPLICATE KEY UPDATE
                        monthly_target = VALUES(monthly_target),
                        daily_target = VALUES(daily_target),
                        status = VALUES(status),
                        updated_by = :updated_by
                ");
                $this->db->bind(':officer_name', $officer['full_name']);
                $this->db->bind(':department', $officer['department']);
                $this->db->bind(':created_by', $data['user_id']);
                $this->db->bind(':updated_by', $data['user_id']);
            }
            
            $this->db->bind(':officer_id', $data['officer_id']);
            $this->db->bind(':target_month', $data['target_month']);
            $this->db->bind(':target_year', $data['target_year']);
            $this->db->bind(':acct_id', $data['acct_id']);
            $this->db->bind(':acct_desc', $account['acct_desc']);
            $this->db->bind(':monthly_target', $data['monthly_target']);
            $this->db->bind(':daily_target', $daily_target);
            $this->db->bind(':status', $data['status']);
            
            $this->db->execute();
            $this->db->endTransaction();
            
            return ['success' => true, 'message' => 'Officer target saved successfully!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error saving target: ' . $e->getMessage()];
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
     * Delete officer target
     */
    public function deleteOfficerTarget($target_id) {
        $this->db->query("DELETE FROM officer_monthly_targets WHERE id = :target_id");
        $this->db->bind(':target_id', $target_id);
        return $this->db->execute();
    }
    
    /**
     * Update officer performance tracking
     */
    public function updateOfficerPerformance($month, $year) {
        $this->db->beginTransaction();
        
        try {
            // Get all active targets for the period
            $this->db->query("
                SELECT * FROM officer_monthly_targets 
                WHERE target_month = :month 
                AND target_year = :year 
                AND status = 'Active'
            ");
            
            $this->db->bind(':month', $month);
            $this->db->bind(':year', $year);
            $targets = $this->db->resultSet();
            
            foreach ($targets as $target) {
                // Get actual performance
                $this->db->query("
                    SELECT 
                        COALESCE(SUM(amount_paid), 0) as achieved_amount,
                        COUNT(DISTINCT date_of_payment) as working_days,
                        COUNT(id) as total_transactions
                    FROM account_general_transaction_new 
                    WHERE remitting_id = :officer_id
                    AND credit_account = :acct_id
                    AND MONTH(date_of_payment) = :month 
                    AND YEAR(date_of_payment) = :year
                    AND (approval_status = 'Approved' OR approval_status = '')
                ");
                
                $this->db->bind(':officer_id', $target['officer_id']);
                $this->db->bind(':acct_id', $target['acct_id']);
                $this->db->bind(':month', $month);
                $this->db->bind(':year', $year);
                
                $performance = $this->db->single();
                
                // Insert or update performance tracking
                $this->db->query("
                    INSERT INTO officer_performance_tracking (
                        officer_id, performance_month, performance_year, acct_id,
                        target_amount, achieved_amount, working_days, total_transactions
                    ) VALUES (
                        :officer_id, :month, :year, :acct_id,
                        :target_amount, :achieved_amount, :working_days, :total_transactions
                    ) ON DUPLICATE KEY UPDATE
                        target_amount = VALUES(target_amount),
                        achieved_amount = VALUES(achieved_amount),
                        working_days = VALUES(working_days),
                        total_transactions = VALUES(total_transactions)
                ");
                
                $this->db->bind(':officer_id', $target['officer_id']);
                $this->db->bind(':month', $month);
                $this->db->bind(':year', $year);
                $this->db->bind(':acct_id', $target['acct_id']);
                $this->db->bind(':target_amount', $target['monthly_target']);
                $this->db->bind(':achieved_amount', $performance['achieved_amount']);
                $this->db->bind(':working_days', $performance['working_days']);
                $this->db->bind(':total_transactions', $performance['total_transactions']);
                
                $this->db->execute();
            }
            
            $this->db->endTransaction();
            return ['success' => true, 'message' => 'Officer performance updated successfully!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error updating performance: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get officer performance summary
     */
    public function getOfficerPerformanceSummary($officer_id, $month, $year) {
        $this->db->query("
            SELECT 
                opt.officer_name,
                opt.department,
                COUNT(opt.id) as assigned_lines,
                SUM(opt.monthly_target) as total_target,
                SUM(COALESCE(opt_track.achieved_amount, 0)) as total_achieved,
                AVG(COALESCE(opt_track.achievement_percentage, 0)) as avg_achievement,
                AVG(COALESCE(opt_track.performance_score, 0)) as avg_score,
                COUNT(CASE WHEN opt_track.performance_grade IN ('A+', 'A') THEN 1 END) as excellent_count,
                COUNT(CASE WHEN opt_track.performance_grade IN ('B+', 'B') THEN 1 END) as good_count,
                COUNT(CASE WHEN opt_track.performance_grade IN ('C+', 'C', 'D', 'F') THEN 1 END) as poor_count
            FROM officer_monthly_targets opt
            LEFT JOIN officer_performance_tracking opt_track ON 
                opt.officer_id = opt_track.officer_id 
                AND opt.target_month = opt_track.performance_month 
                AND opt.target_year = opt_track.performance_year
                AND opt.acct_id = opt_track.acct_id
            WHERE opt.officer_id = :officer_id
            AND opt.target_month = :month 
            AND opt.target_year = :year
            AND opt.status = 'Active'
            GROUP BY opt.officer_id, opt.officer_name, opt.department
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->single();
    }
    
    /**
     * Get detailed officer performance by income line
     */
    public function getOfficerDetailedPerformance($officer_id, $month, $year) {
        $this->db->query("
            SELECT 
                opt.acct_id,
                opt.acct_desc,
                opt.monthly_target,
                opt.daily_target,
                COALESCE(opt_track.achieved_amount, 0) as achieved_amount,
                COALESCE(opt_track.achievement_percentage, 0) as achievement_percentage,
                COALESCE(opt_track.performance_score, 0) as performance_score,
                COALESCE(opt_track.performance_grade, 'F') as performance_grade,
                COALESCE(opt_track.working_days, 0) as working_days,
                COALESCE(opt_track.total_transactions, 0) as total_transactions
            FROM officer_monthly_targets opt
            LEFT JOIN officer_performance_tracking opt_track ON 
                opt.officer_id = opt_track.officer_id 
                AND opt.target_month = opt_track.performance_month 
                AND opt.target_year = opt_track.performance_year
                AND opt.acct_id = opt_track.acct_id
            WHERE opt.officer_id = :officer_id
            AND opt.target_month = :month 
            AND opt.target_year = :year
            AND opt.status = 'Active'
            ORDER BY opt_track.performance_score DESC, opt.acct_desc ASC
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get department performance comparison
     */
    public function getDepartmentPerformanceComparison($month, $year) {
        $this->db->query("
            SELECT 
                opt.department,
                COUNT(DISTINCT opt.officer_id) as officer_count,
                COUNT(opt.id) as total_targets,
                SUM(opt.monthly_target) as total_department_target,
                SUM(COALESCE(opt_track.achieved_amount, 0)) as total_department_achieved,
                AVG(COALESCE(opt_track.achievement_percentage, 0)) as avg_achievement_percentage,
                AVG(COALESCE(opt_track.performance_score, 0)) as avg_performance_score
            FROM officer_monthly_targets opt
            LEFT JOIN officer_performance_tracking opt_track ON 
                opt.officer_id = opt_track.officer_id 
                AND opt.target_month = opt_track.performance_month 
                AND opt.target_year = opt_track.performance_year
                AND opt.acct_id = opt_track.acct_id
            WHERE opt.target_month = :month 
            AND opt.target_year = :year
            AND opt.status = 'Active'
            GROUP BY opt.department
            ORDER BY avg_performance_score DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Bulk assign targets to officer
     */
    public function bulkAssignTargets($officer_id, $month, $year, $income_lines, $user_id) {
        $this->db->beginTransaction();
        
        try {
            // Get officer information
            $this->db->query("SELECT full_name, department FROM staffs WHERE user_id = :officer_id");
            $this->db->bind(':officer_id', $officer_id);
            $officer = $this->db->single();
            
            foreach ($income_lines as $line) {
                $data = [
                    'officer_id' => $officer_id,
                    'target_month' => $month,
                    'target_year' => $year,
                    'acct_id' => $line['acct_id'],
                    'monthly_target' => $line['target_amount'],
                    'status' => 'Active',
                    'user_id' => $user_id
                ];
                
                $result = $this->saveOfficerTarget($data);
                if (!$result['success']) {
                    throw new Exception($result['message']);
                }
            }
            
            $this->db->endTransaction();
            return ['success' => true, 'message' => 'Targets assigned successfully!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error assigning targets: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get officer ranking based on performance scores
     */
    public function getOfficerRanking($month, $year) {
        $this->db->query("
            SELECT 
                opt.officer_id,
                opt.officer_name,
                opt.department,
                SUM(opt.monthly_target) as total_target,
                SUM(COALESCE(opt_track.achieved_amount, 0)) as total_achieved,
                AVG(COALESCE(opt_track.achievement_percentage, 0)) as avg_achievement,
                AVG(COALESCE(opt_track.performance_score, 0)) as overall_score,
                COUNT(CASE WHEN opt_track.performance_grade IN ('A+', 'A') THEN 1 END) as excellent_lines,
                COUNT(CASE WHEN opt_track.performance_grade IN ('B+', 'B') THEN 1 END) as good_lines,
                COUNT(CASE WHEN opt_track.performance_grade IN ('C+', 'C', 'D', 'F') THEN 1 END) as poor_lines,
                COUNT(opt.id) as total_assigned_lines
            FROM officer_monthly_targets opt
            LEFT JOIN officer_performance_tracking opt_track ON 
                opt.officer_id = opt_track.officer_id 
                AND opt.target_month = opt_track.performance_month 
                AND opt.target_year = opt_track.performance_year
                AND opt.acct_id = opt_track.acct_id
            WHERE opt.target_month = :month 
            AND opt.target_year = :year
            AND opt.status = 'Active'
            GROUP BY opt.officer_id, opt.officer_name, opt.department
            ORDER BY overall_score DESC, avg_achievement DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
}
?>