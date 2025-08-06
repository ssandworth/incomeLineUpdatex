<?php
require_once 'Database.php';

class OfficerPerformanceAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all wealth creation officers
     */
    public function getWealthCreationOfficers() {
        $this->db->query("
            SELECT user_id, full_name, department, phone, email 
            FROM staffs 
            WHERE department = 'Wealth Creation' 
            ORDER BY full_name ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get other staff officers
     */
    public function getOtherOfficers() {
        $this->db->query("
            SELECT id, full_name, department, phone 
            FROM staffs_others 
            ORDER BY full_name ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get officer performance for specific month/year
     */
    public function getOfficerPerformance($officer_id, $month, $year, $is_other_staff = false) {
        if ($is_other_staff) {
            $this->db->query("
                SELECT 
                    a.acct_id,
                    a.acct_desc as income_line,
                    a.acct_table_name,
                    COALESCE(SUM(t.amount_paid), 0) as total_amount,
                    COUNT(t.id) as transaction_count
                FROM accounts a
                LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                    AND t.remitting_id = :officer_id
                    AND MONTH(t.date_of_payment) = :month 
                    AND YEAR(t.date_of_payment) = :year
                    AND (t.approval_status = 'Approved' OR t.approval_status = '')
                WHERE a.income_line = 'Yes' AND a.active = 'Yes'
                GROUP BY a.acct_id, a.acct_desc, a.acct_table_name
                ORDER BY total_amount DESC
            ");
        } else {
            $this->db->query("
                SELECT 
                    a.acct_id,
                    a.acct_desc as income_line,
                    a.acct_table_name,
                    COALESCE(SUM(t.amount_paid), 0) as total_amount,
                    COUNT(t.id) as transaction_count
                FROM accounts a
                LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                    AND t.remitting_id = :officer_id
                    AND MONTH(t.date_of_payment) = :month 
                    AND YEAR(t.date_of_payment) = :year
                    AND (t.approval_status = 'Approved' OR t.approval_status = '')
                WHERE a.income_line = 'Yes' AND a.active = 'Yes'
                GROUP BY a.acct_id, a.acct_desc, a.acct_table_name
                ORDER BY total_amount DESC
            ");
        }
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get officer daily performance breakdown
     */
    public function getOfficerDailyPerformance($officer_id, $month, $year, $is_other_staff = false) {
        $daily_data = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as daily_total 
                FROM account_general_transaction_new 
                WHERE remitting_id = :officer_id 
                AND date_of_payment = :date 
                AND (approval_status = 'Approved' OR approval_status = '')
            ");
            
            $this->db->bind(':officer_id', $officer_id);
            $this->db->bind(':date', $date);
            
            $result = $this->db->single();
            $daily_data[$day] = $result['daily_total'] ?? 0;
        }
        
        return $daily_data;
    }
    
    /**
     * Get officer performance comparison
     */
    public function getOfficerComparison($month, $year) {
        // Get WC officers performance
        $this->db->query("
            SELECT 
                s.user_id,
                s.full_name,
                s.department,
                COALESCE(SUM(t.amount_paid), 0) as total_collections,
                COUNT(t.id) as total_transactions,
                COUNT(DISTINCT t.date_of_payment) as active_days
            FROM staffs s
            LEFT JOIN account_general_transaction_new t ON s.user_id = t.remitting_id
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE s.department = 'Wealth Creation'
            GROUP BY s.user_id, s.full_name, s.department
            ORDER BY total_collections DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $wc_officers = $this->db->resultSet();
        
        // Get other officers performance
        $this->db->query("
            SELECT 
                so.id as user_id,
                so.full_name,
                so.department,
                COALESCE(SUM(t.amount_paid), 0) as total_collections,
                COUNT(t.id) as total_transactions,
                COUNT(DISTINCT t.date_of_payment) as active_days
            FROM staffs_others so
            LEFT JOIN account_general_transaction_new t ON so.id = t.remitting_id
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            GROUP BY so.id, so.full_name, so.department
            ORDER BY total_collections DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $other_officers = $this->db->resultSet();
        
        // Combine and rank
        $all_officers = array_merge($wc_officers, $other_officers);
        
        // Calculate performance metrics
        foreach ($all_officers as &$officer) {
            $officer['avg_per_day'] = $officer['active_days'] > 0 ? 
                $officer['total_collections'] / $officer['active_days'] : 0;
            $officer['avg_per_transaction'] = $officer['total_transactions'] > 0 ? 
                $officer['total_collections'] / $officer['total_transactions'] : 0;
        }
        
        // Sort by total collections
        usort($all_officers, function($a, $b) {
            return $b['total_collections'] <=> $a['total_collections'];
        });
        
        return $all_officers;
    }
    
    /**
     * Get officer performance trends (last 6 months)
     */
    public function getOfficerTrends($officer_id, $current_month, $current_year, $is_other_staff = false) {
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $month = $current_month - $i;
            $year = $current_year;
            
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as monthly_total 
                FROM account_general_transaction_new 
                WHERE remitting_id = :officer_id 
                AND MONTH(date_of_payment) = :month 
                AND YEAR(date_of_payment) = :year
                AND (approval_status = 'Approved' OR approval_status = '')
            ");
            
            $this->db->bind(':officer_id', $officer_id);
            $this->db->bind(':month', $month);
            $this->db->bind(':year', $year);
            
            $result = $this->db->single();
            
            $trends[] = [
                'month' => $month,
                'year' => $year,
                'month_name' => date('M Y', mktime(0, 0, 0, $month, 1, $year)),
                'total' => $result['monthly_total']
            ];
        }
        
        return $trends;
    }
    
    /**
     * Get officer performance rating
     */
    public function getOfficerRating($officer_id, $month, $year, $is_other_staff = false) {
        // Get officer's total
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as officer_total 
            FROM account_general_transaction_new 
            WHERE remitting_id = :officer_id 
            AND MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
            AND (approval_status = 'Approved' OR approval_status = '')
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        $officer_result = $this->db->single();
        $officer_total = $officer_result['officer_total'];
        
        // Get department average
        if ($is_other_staff) {
            $this->db->query("
                SELECT AVG(monthly_totals.total) as dept_average
                FROM (
                    SELECT 
                        so.id,
                        COALESCE(SUM(t.amount_paid), 0) as total
                    FROM staffs_others so
                    LEFT JOIN account_general_transaction_new t ON so.id = t.remitting_id
                        AND MONTH(t.date_of_payment) = :month 
                        AND YEAR(t.date_of_payment) = :year
                        AND (t.approval_status = 'Approved' OR t.approval_status = '')
                    GROUP BY so.id
                ) as monthly_totals
            ");
        } else {
            $this->db->query("
                SELECT AVG(monthly_totals.total) as dept_average
                FROM (
                    SELECT 
                        s.user_id,
                        COALESCE(SUM(t.amount_paid), 0) as total
                    FROM staffs s
                    LEFT JOIN account_general_transaction_new t ON s.user_id = t.remitting_id
                        AND MONTH(t.date_of_payment) = :month 
                        AND YEAR(t.date_of_payment) = :year
                        AND (t.approval_status = 'Approved' OR t.approval_status = '')
                    WHERE s.department = 'Wealth Creation'
                    GROUP BY s.user_id
                ) as monthly_totals
            ");
        }
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        $avg_result = $this->db->single();
        $dept_average = $avg_result['dept_average'] ?? 0;
        
        // Calculate performance ratio
        $performance_ratio = $dept_average > 0 ? ($officer_total / $dept_average) * 100 : 0;
        
        // Determine rating
        if ($performance_ratio >= 150) {
            $rating = 'Exceptional';
            $rating_class = 'bg-green-100 text-green-800';
        } elseif ($performance_ratio >= 120) {
            $rating = 'Excellent';
            $rating_class = 'bg-blue-100 text-blue-800';
        } elseif ($performance_ratio >= 100) {
            $rating = 'Good';
            $rating_class = 'bg-yellow-100 text-yellow-800';
        } elseif ($performance_ratio >= 80) {
            $rating = 'Fair';
            $rating_class = 'bg-orange-100 text-orange-800';
        } else {
            $rating = 'Needs Improvement';
            $rating_class = 'bg-red-100 text-red-800';
        }
        
        return [
            'officer_total' => $officer_total,
            'dept_average' => $dept_average,
            'performance_ratio' => $performance_ratio,
            'rating' => $rating,
            'rating_class' => $rating_class
        ];
    }
    
    /**
     * Get income line performance for all officers
     */
    public function getIncomeLinePerformance($month, $year) {
        $this->db->query("
            SELECT 
                a.acct_id,
                a.acct_desc as income_line,
                a.acct_table_name,
                COALESCE(SUM(t.amount_paid), 0) as total_amount,
                COUNT(t.id) as transaction_count,
                COUNT(DISTINCT t.remitting_id) as unique_officers
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE a.income_line = 'Yes' AND a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc, a.acct_table_name
            ORDER BY total_amount DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get daily collections for specific income line
     */
    public function getDailyCollectionsForIncomeLine($income_line_id, $month, $year) {
        $daily_data = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as daily_total 
                FROM account_general_transaction_new 
                WHERE credit_account = :income_line_id 
                AND date_of_payment = :date 
                AND (approval_status = 'Approved' OR approval_status = '')
            ");
            
            $this->db->bind(':income_line_id', $income_line_id);
            $this->db->bind(':date', $date);
            
            $result = $this->db->single();
            $daily_data[$day] = $result['daily_total'] ?? 0;
        }
        
        return $daily_data;
    }
    
    /**
     * Get officer efficiency metrics
     */
    public function getOfficerEfficiencyMetrics($officer_id, $month, $year, $is_other_staff = false) {
        // Get basic performance data
        $this->db->query("
            SELECT 
                COUNT(DISTINCT date_of_payment) as working_days,
                COUNT(*) as total_transactions,
                SUM(amount_paid) as total_amount,
                AVG(amount_paid) as avg_transaction_amount,
                MIN(amount_paid) as min_transaction,
                MAX(amount_paid) as max_transaction
            FROM account_general_transaction_new 
            WHERE remitting_id = :officer_id 
            AND MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
            AND (approval_status = 'Approved' OR approval_status = '')
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        $metrics = $this->db->single();
        
        // Calculate efficiency indicators
        $total_days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
        $working_days = $metrics['working_days'] ?? 0;
        $attendance_rate = ($working_days / $total_days_in_month) * 100;
        
        $productivity_score = 0;
        if ($working_days > 0) {
            $daily_avg = $metrics['total_amount'] / $working_days;
            $transaction_efficiency = $metrics['total_transactions'] / $working_days;
            $productivity_score = ($daily_avg * 0.7) + ($transaction_efficiency * 0.3);
        }
        
        return [
            'working_days' => $working_days,
            'total_transactions' => $metrics['total_transactions'] ?? 0,
            'total_amount' => $metrics['total_amount'] ?? 0,
            'avg_transaction_amount' => $metrics['avg_transaction_amount'] ?? 0,
            'min_transaction' => $metrics['min_transaction'] ?? 0,
            'max_transaction' => $metrics['max_transaction'] ?? 0,
            'attendance_rate' => $attendance_rate,
            'productivity_score' => $productivity_score,
            'daily_average' => $working_days > 0 ? $metrics['total_amount'] / $working_days : 0
        ];
    }
    
    /**
     * Get top performers for the month
     */
    public function getTopPerformers($month, $year, $limit = 10) {
        $comparison_data = $this->getOfficerComparison($month, $year);
        
        // Filter out officers with zero collections and limit results
        $top_performers = array_filter($comparison_data, function($officer) {
            return $officer['total_collections'] > 0;
        });
        
        return array_slice($top_performers, 0, $limit);
    }
    
    /**
     * Get performance insights and recommendations
     */
    public function getPerformanceInsights($officer_id, $month, $year, $is_other_staff = false) {
        $insights = [];
        
        // Get officer metrics
        $metrics = $this->getOfficerEfficiencyMetrics($officer_id, $month, $year, $is_other_staff);
        $rating = $this->getOfficerRating($officer_id, $month, $year, $is_other_staff);
        $trends = $this->getOfficerTrends($officer_id, $month, $year, $is_other_staff);
        
        // Attendance insights
        if ($metrics['attendance_rate'] < 70) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Attendance',
                'title' => 'Low Attendance Rate',
                'message' => 'Officer worked only ' . $metrics['working_days'] . ' days this month (' . number_format($metrics['attendance_rate'], 1) . '%)',
                'recommendation' => 'Review attendance patterns and provide support if needed'
            ];
        } elseif ($metrics['attendance_rate'] >= 90) {
            $insights[] = [
                'type' => 'success',
                'category' => 'Attendance',
                'title' => 'Excellent Attendance',
                'message' => 'Consistent attendance with ' . number_format($metrics['attendance_rate'], 1) . '% rate',
                'recommendation' => 'Maintain current attendance standards'
            ];
        }
        
        // Performance insights
        if ($rating['performance_ratio'] >= 150) {
            $insights[] = [
                'type' => 'success',
                'category' => 'Performance',
                'title' => 'Exceptional Performance',
                'message' => 'Performance is ' . number_format($rating['performance_ratio'], 1) . '% of department average',
                'recommendation' => 'Consider for recognition and leadership opportunities'
            ];
        } elseif ($rating['performance_ratio'] < 80) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'Performance',
                'title' => 'Below Average Performance',
                'message' => 'Performance is ' . number_format($rating['performance_ratio'], 1) . '% of department average',
                'recommendation' => 'Provide additional training and support'
            ];
        }
        
        // Trend insights
        if (count($trends) >= 3) {
            $recent_avg = array_sum(array_slice(array_column($trends, 'total'), -3)) / 3;
            $earlier_avg = array_sum(array_slice(array_column($trends, 'total'), 0, 3)) / 3;
            
            if ($recent_avg > $earlier_avg * 1.2) {
                $insights[] = [
                    'type' => 'success',
                    'category' => 'Trend',
                    'title' => 'Improving Performance',
                    'message' => 'Performance has improved significantly over recent months',
                    'recommendation' => 'Continue current strategies and share best practices'
                ];
            } elseif ($recent_avg < $earlier_avg * 0.8) {
                $insights[] = [
                    'type' => 'warning',
                    'category' => 'Trend',
                    'title' => 'Declining Performance',
                    'message' => 'Performance has declined over recent months',
                    'recommendation' => 'Investigate causes and implement improvement plan'
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Calculate Sunday positions for a month
     */
    public function getSundayPositions($month, $year) {
        $sundays = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            if (checkdate($month, $day, $year) && date('w', strtotime($date)) == 0) {
                $sundays[] = $day;
            }
        }
        
        return $sundays;
    }
    
    /**
     * Get officer information
     */
    public function getOfficerInfo($officer_id, $is_other_staff = false) {
        if ($is_other_staff) {
            $this->db->query("
                SELECT id as user_id, full_name, department, phone 
                FROM staffs_others 
                WHERE id = :officer_id
            ");
        } else {
            $this->db->query("
                SELECT user_id, full_name, department, phone, email 
                FROM staffs 
                WHERE user_id = :officer_id
            ");
        }
        
        $this->db->bind(':officer_id', $officer_id);
        return $this->db->single();
    }
}
?>