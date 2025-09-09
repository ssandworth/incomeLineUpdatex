<?php
require_once 'BudgetManager.php';
require_once 'OfficerTargetManager.php';
require_once 'OfficerPerformanceAnalyzer.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

class MonthlyBudgetPerformanceAnalyzer {
    private $db;
    private $budget_manager;
    private $target_manager;
    private $performance_analyzer;
    
    public function __construct() {
        $this->db = new Database();
        $this->budget_manager = new BudgetManager();
        $this->target_manager = new OfficerTargetManager();
        $this->performance_analyzer = new OfficerPerformanceAnalyzer();
    }
    
    /**
     * Get cumulative budget performance up to selected month
     */
    public function getCumulativeBudgetPerformance($year, $month) {
        $this->db->query("
            SELECT 
                bl.acct_id,
                bl.acct_desc,
                bl.budget_year,
                -- Calculate cumulative budget up to selected month
                CASE 
                    WHEN :month >= 1 THEN bl.january_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 2 THEN bl.february_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 3 THEN bl.march_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 4 THEN bl.april_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 5 THEN bl.may_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 6 THEN bl.june_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 7 THEN bl.july_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 8 THEN bl.august_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 9 THEN bl.september_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 10 THEN bl.october_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 11 THEN bl.november_budget ELSE 0 END +
                CASE 
                    WHEN :month >= 12 THEN bl.december_budget ELSE 0 END as cumulative_budget,
                -- Get actual collections up to selected month
                COALESCE(SUM(CASE 
                    WHEN MONTH(t.date_of_payment) <= :month 
                    AND YEAR(t.date_of_payment) = :year 
                    AND (t.approval_status = 'Approved' OR t.approval_status = '')
                    THEN t.amount_paid ELSE 0 END), 0) as cumulative_actual
            FROM budget_lines bl
            LEFT JOIN account_general_transaction_new t ON bl.acct_id = t.credit_account
            WHERE bl.budget_year = :year
            AND bl.status = 'Active'
            GROUP BY bl.acct_id, bl.acct_desc, bl.budget_year
            ORDER BY bl.acct_desc ASC
        ");
        
        $this->db->bind(':year', $year);
        $this->db->bind(':month', $month);
        
        $results = $this->db->resultSet();
        
        // Calculate variance for each income line
        foreach ($results as &$result) {
            $result['variance_amount'] = $result['cumulative_actual'] - $result['cumulative_budget'];
            $result['variance_percentage'] = $result['cumulative_budget'] > 0 ? 
                (($result['cumulative_actual'] - $result['cumulative_budget']) / $result['cumulative_budget']) * 100 : 0;
            
            // Determine performance status
            if ($result['cumulative_actual'] > $result['cumulative_budget'] * 1.05) {
                $result['performance_status'] = 'Above Budget';
                $result['status_class'] = 'bg-green-100 text-green-800';
            } elseif ($result['cumulative_actual'] >= $result['cumulative_budget'] * 0.95) {
                $result['performance_status'] = 'On Budget';
                $result['status_class'] = 'bg-blue-100 text-blue-800';
            } else {
                $result['performance_status'] = 'Below Budget';
                $result['status_class'] = 'bg-red-100 text-red-800';
            }
        }
        
        return $results;
    }
    
    /**
     * Get cumulative officer performance up to selected month
     */
    public function getCumulativeOfficerPerformance($year, $month) {
        $this->db->query("
            SELECT 
                opt.officer_id,
                opt.officer_name,
                opt.department,
                -- Calculate cumulative targets up to selected month
                SUM(CASE 
                    WHEN opt.target_month <= :month 
                    AND opt.target_year = :year 
                    THEN opt.monthly_target ELSE 0 END) as cumulative_target,
                -- Get actual collections up to selected month
                COALESCE(SUM(CASE 
                    WHEN MONTH(t.date_of_payment) <= :month 
                    AND YEAR(t.date_of_payment) = :year 
                    AND (t.approval_status = 'Approved' OR t.approval_status = '')
                    THEN t.amount_paid ELSE 0 END), 0) as cumulative_achieved,
                COUNT(DISTINCT opt.acct_id) as assigned_lines,
                COUNT(DISTINCT CASE 
                    WHEN MONTH(t.date_of_payment) <= :month 
                    AND YEAR(t.date_of_payment) = :year 
                    THEN t.date_of_payment END) as working_days,
                COUNT(CASE 
                    WHEN MONTH(t.date_of_payment) <= :month 
                    AND YEAR(t.date_of_payment) = :year 
                    AND (t.approval_status = 'Approved' OR t.approval_status = '')
                    THEN t.id END) as total_transactions
            FROM officer_monthly_targets opt
            LEFT JOIN account_general_transaction_new t ON opt.officer_id = t.remitting_id
                AND opt.acct_id = t.credit_account
            WHERE opt.target_year = :year
            AND opt.status = 'Active'
            GROUP BY opt.officer_id, opt.officer_name, opt.department
            HAVING cumulative_target > 0 OR cumulative_achieved > 0
            ORDER BY cumulative_achieved DESC
        ");
        
        $this->db->bind(':year', $year);
        $this->db->bind(':month', $month);
        
        $results = $this->db->resultSet();
        
        // Calculate performance metrics for each officer
        foreach ($results as &$result) {
            $result['achievement_percentage'] = $result['cumulative_target'] > 0 ? 
                ($result['cumulative_achieved'] / $result['cumulative_target']) * 100 : 0;
            
            $result['daily_average'] = $result['working_days'] > 0 ? 
                $result['cumulative_achieved'] / $result['working_days'] : 0;
            
            // Calculate performance grade
            if ($result['cumulative_target'] == 0) {
                $result['performance_grade'] = 'F';
                $result['grade_class'] = 'bg-gray-100 text-gray-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 1.5) {
                $result['performance_grade'] = 'A+';
                $result['grade_class'] = 'bg-green-100 text-green-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 1.2) {
                $result['performance_grade'] = 'A';
                $result['grade_class'] = 'bg-green-100 text-green-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target']) {
                $result['performance_grade'] = 'B+';
                $result['grade_class'] = 'bg-blue-100 text-blue-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 0.8) {
                $result['performance_grade'] = 'B';
                $result['grade_class'] = 'bg-blue-100 text-blue-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 0.6) {
                $result['performance_grade'] = 'C+';
                $result['grade_class'] = 'bg-yellow-100 text-yellow-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 0.4) {
                $result['performance_grade'] = 'C';
                $result['grade_class'] = 'bg-yellow-100 text-yellow-800';
            } elseif ($result['cumulative_achieved'] >= $result['cumulative_target'] * 0.2) {
                $result['performance_grade'] = 'D';
                $result['grade_class'] = 'bg-orange-100 text-orange-800';
            } else {
                $result['performance_grade'] = 'F';
                $result['grade_class'] = 'bg-red-100 text-red-800';
            }
        }
        
        return $results;
    }
    
    /**
     * Get monthly breakdown for selected period
     */
    public function getMonthlyBreakdown($year, $selected_month) {
        $breakdown = [];
        
        for ($month = 1; $month <= $selected_month; $month++) {
            $this->db->query("
                SELECT 
                    SUM(CASE 
                        WHEN bl.budget_year = :year THEN
                            CASE :month
                                WHEN 1 THEN bl.january_budget
                                WHEN 2 THEN bl.february_budget
                                WHEN 3 THEN bl.march_budget
                                WHEN 4 THEN bl.april_budget
                                WHEN 5 THEN bl.may_budget
                                WHEN 6 THEN bl.june_budget
                                WHEN 7 THEN bl.july_budget
                                WHEN 8 THEN bl.august_budget
                                WHEN 9 THEN bl.september_budget
                                WHEN 10 THEN bl.october_budget
                                WHEN 11 THEN bl.november_budget
                                WHEN 12 THEN bl.december_budget
                            END
                        ELSE 0
                    END) as monthly_budget,
                    COALESCE(SUM(CASE 
                        WHEN MONTH(t.date_of_payment) = :month 
                        AND YEAR(t.date_of_payment) = :year 
                        AND (t.approval_status = 'Approved' OR t.approval_status = '')
                        THEN t.amount_paid ELSE 0 END), 0) as monthly_actual
                FROM budget_lines bl
                LEFT JOIN account_general_transaction_new t ON bl.acct_id = t.credit_account
                WHERE bl.budget_year = :year
                AND bl.status = 'Active'
            ");
            
            $this->db->bind(':year', $year);
            $this->db->bind(':month', $month);
            
            $result = $this->db->single();
            
            $breakdown[] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'monthly_budget' => $result['monthly_budget'] ?? 0,
                'monthly_actual' => $result['monthly_actual'] ?? 0,
                'variance' => ($result['monthly_actual'] ?? 0) - ($result['monthly_budget'] ?? 0),
                'variance_percentage' => ($result['monthly_budget'] ?? 0) > 0 ? 
                    ((($result['monthly_actual'] ?? 0) - ($result['monthly_budget'] ?? 0)) / ($result['monthly_budget'] ?? 0)) * 100 : 0
            ];
        }
        
        return $breakdown;
    }
    
    /**
     * Get performance insights for the selected period
     */
    public function getPerformanceInsights($year, $month, $budget_performance, $officer_performance) {
        $insights = [];
        
        // Budget performance insights
        $above_budget = count(array_filter($budget_performance, function($item) {
            return $item['performance_status'] === 'Above Budget';
        }));
        
        $below_budget = count(array_filter($budget_performance, function($item) {
            return $item['performance_status'] === 'Below Budget';
        }));
        
        if ($above_budget > $below_budget) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Strong Budget Performance',
                'message' => "{$above_budget} income lines are performing above budget expectations",
                'recommendation' => 'Analyze success factors and replicate across underperforming lines'
            ];
        } elseif ($below_budget > $above_budget) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Budget Performance Concerns',
                'message' => "{$below_budget} income lines are underperforming against budget",
                'recommendation' => 'Immediate review of operational efficiency and market conditions required'
            ];
        }
        
        // Officer performance insights
        $high_performers = count(array_filter($officer_performance, function($officer) {
            return $officer['achievement_percentage'] >= 120;
        }));
        
        $low_performers = count(array_filter($officer_performance, function($officer) {
            return $officer['achievement_percentage'] < 80;
        }));
        
        if ($high_performers > 0) {
            $insights[] = [
                'type' => 'success',
                'title' => 'Exceptional Officer Performance',
                'message' => "{$high_performers} officers are exceeding targets significantly",
                'recommendation' => 'Consider recognition programs and sharing best practices'
            ];
        }
        
        if ($low_performers > 0) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Officer Performance Issues',
                'message' => "{$low_performers} officers are underperforming against targets",
                'recommendation' => 'Provide additional training and support to improve performance'
            ];
        }
        
        return $insights;
    }
}

$analyzer = new MonthlyBudgetPerformanceAnalyzer();

// Check access permissions
$can_view = $analyzer->budget_manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get parameters
$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? date('n');
$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get performance data
$budget_performance = $analyzer->getCumulativeBudgetPerformance($selected_year, $selected_month);
$officer_performance = $analyzer->getCumulativeOfficerPerformance($selected_year, $selected_month);
$monthly_breakdown = $analyzer->getMonthlyBreakdown($selected_year, $selected_month);
$insights = $analyzer->getPerformanceInsights($selected_year, $selected_month, $budget_performance, $officer_performance);

// Calculate summary statistics
$total_budget = array_sum(array_column($budget_performance, 'cumulative_budget'));
$total_actual = array_sum(array_column($budget_performance, 'cumulative_actual'));
$overall_variance = $total_budget > 0 ? (($total_actual - $total_budget) / $total_budget) * 100 : 0;

$total_officer_targets = array_sum(array_column($officer_performance, 'cumulative_target'));
$total_officer_achieved = array_sum(array_column($officer_performance, 'cumulative_achieved'));
$overall_officer_achievement = $total_officer_targets > 0 ? ($total_officer_achieved / $total_officer_targets) * 100 : 0;

// Performance distribution
$above_budget_count = count(array_filter($budget_performance, function($item) {
    return $item['performance_status'] === 'Above Budget';
}));

$on_budget_count = count(array_filter($budget_performance, function($item) {
    return $item['performance_status'] === 'On Budget';
}));

$below_budget_count = count(array_filter($budget_performance, function($item) {
    return $item['performance_status'] === 'Below Budget';
}));

$excellent_officers = count(array_filter($officer_performance, function($officer) {
    return in_array($officer['performance_grade'], ['A+', 'A']);
}));

$good_officers = count(array_filter($officer_performance, function($officer) {
    return in_array($officer['performance_grade'], ['B+', 'B']);
}));

$poor_officers = count(array_filter($officer_performance, function($officer) {
    return in_array($officer['performance_grade'], ['C+', 'C', 'D', 'F']);
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Monthly Budget Performance Analysis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#059669',
                        accent: '#dc2626',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="budget_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Budget Management</a>
                    <h1 class="text-xl font-bold text-gray-900">Monthly Budget Performance Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Welcome, <?php echo $staff['full_name']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $staff['department']; ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Period Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h2 class="text-2xl font-bold text-gray-900">Cumulative Performance Analysis</h2>
                    <p class="text-gray-600">View budget and officer performance up to selected month</p>
                </div>
                
                <form method="GET" class="flex flex-col sm:flex-row gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Month</label>
                        <select name="month" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Year</label>
                        <select name="year" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 2; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-chart-line mr-2"></i>Analyze Period
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-700 rounded-xl shadow-lg p-8 mb-8 text-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-6 lg:mb-0">
                    <h1 class="text-3xl font-bold mb-2">Performance Analysis</h1>
                    <p class="text-blue-100 text-lg">
                        Cumulative performance from January to <?php echo $month_name . ' ' . $selected_year; ?>
                    </p>
                    <div class="mt-4 flex items-center space-x-6">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <span class="text-sm">Period: Jan - <?php echo $month_name . ' ' . $selected_year; ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-chart-pie mr-2"></i>
                            <span class="text-sm"><?php echo count($budget_performance); ?> Income Lines Tracked</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-bold mb-2">₦<?php echo number_format($total_actual); ?></div>
                    <div class="text-blue-100">Total Collections (YTD)</div>
                    <div class="mt-2 flex items-center justify-end">
                        <span class="text-sm <?php echo $overall_variance >= 0 ? 'text-green-200' : 'text-red-200'; ?>">
                            <?php echo $overall_variance >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance, 1); ?>% vs Budget
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-chart-pie text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Cumulative Budget</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_budget); ?></p>
                        <p class="text-xs text-gray-500">Jan - <?php echo $month_name; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-coins text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Actual Collections</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_actual); ?></p>
                        <p class="text-xs text-gray-500">Jan - <?php echo $month_name; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $overall_variance >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Budget Variance</p>
                        <p class="text-2xl font-bold <?php echo $overall_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $overall_variance >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance, 1); ?>%
                        </p>
                        <p class="text-xs text-gray-500">vs cumulative budget</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $overall_officer_achievement >= 100 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Officer Achievement</p>
                        <p class="text-2xl font-bold <?php echo $overall_officer_achievement >= 100 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($overall_officer_achievement, 1); ?>%
                        </p>
                        <p class="text-xs text-gray-500">vs cumulative targets</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Insights -->
        <?php if (!empty($insights)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($insights as $insight): ?>
                <div class="border-l-4 <?php echo $insight['type'] === 'success' ? 'border-green-500 bg-green-50' : 'border-yellow-500 bg-yellow-50'; ?> p-4 rounded-r-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <?php if ($insight['type'] === 'success'): ?>
                                <i class="fas fa-check-circle text-green-600"></i>
                            <?php else: ?>
                                <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-gray-900"><?php echo $insight['title']; ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?php echo $insight['message']; ?></p>
                            <p class="text-xs text-gray-500 mt-2 italic"><?php echo $insight['recommendation']; ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Monthly Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Performance Trend</h3>
                <div class="relative h-48">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>

            <!-- Performance Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Distribution</h3>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="text-center">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Budget Performance</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                    Above Budget
                                </span>
                                <span class="font-bold text-green-600"><?php echo $above_budget_count; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                    On Budget
                                </span>
                                <span class="font-bold text-blue-600"><?php echo $on_budget_count; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                    Below Budget
                                </span>
                                <span class="font-bold text-red-600"><?php echo $below_budget_count; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Officer Performance</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                    Excellent (A+, A)
                                </span>
                                <span class="font-bold text-green-600"><?php echo $excellent_officers; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                    Good (B+, B)
                                </span>
                                <span class="font-bold text-blue-600"><?php echo $good_officers; ?></span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                    Needs Improvement
                                </span>
                                <span class="font-bold text-red-600"><?php echo $poor_officers; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="relative h-32">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Income Lines Performance -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Income Lines Cumulative Performance (Jan - <?php echo $month_name . ' ' . $selected_year; ?>)
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cumulative Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual Collections</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (₦)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (%)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($budget_performance)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No budget data available for the selected period.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($budget_performance as $performance): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $performance['acct_desc']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($performance['cumulative_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($performance['cumulative_actual']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                    <?php echo $performance['variance_amount'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $performance['variance_amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format($performance['variance_amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                                    <?php echo $performance['variance_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $performance['variance_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($performance['variance_percentage'], 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $performance['status_class']; ?>">
                                        <?php echo $performance['performance_status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <a href="ledger.php?acct_id=<?php echo $performance['acct_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Ledger">
                                        <i class="fas fa-book"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTAL</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_budget); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_actual); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold <?php echo $overall_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $overall_variance >= 0 ? '+' : ''; ?>₦<?php echo number_format($total_actual - $total_budget); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold <?php echo $overall_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $overall_variance >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance, 1); ?>%
                            </th>
                            <th colspan="2" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Officer Performance -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Officer Cumulative Performance (Jan - <?php echo $month_name . ' ' . $selected_year; ?>)
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cumulative Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual Achievement</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Working Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($officer_performance)): ?>
                        <tr>
                            <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                                No officer targets set for the selected period.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($officer_performance as $index => $officer): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold
                                        <?php echo $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-500' : ($index === 2 ? 'bg-orange-500' : 'bg-blue-500')); ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                            <?php echo strtoupper(substr($officer['officer_name'], 0, 2)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $officer['officer_name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $officer['assigned_lines']; ?> assigned lines</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $officer['department'] === 'Wealth Creation' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo $officer['department']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($officer['cumulative_target']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($officer['cumulative_achieved']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="<?php echo $officer['achievement_percentage'] >= 100 ? 'bg-green-500' : ($officer['achievement_percentage'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                                 style="width: <?php echo min(100, $officer['achievement_percentage']); ?>%"></div>
                                        </div>
                                        <span class="ml-2 text-xs text-gray-500">
                                            <?php echo number_format($officer['achievement_percentage'], 1); ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $officer['grade_class']; ?>">
                                        <?php echo $officer['performance_grade']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                    <?php echo $officer['working_days']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($officer['daily_average']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="officer_performance_report.php?officer_id=<?php echo $officer['officer_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="mpr_income_lines_officers.php?sstaff=<?php echo $officer['officer_id']; ?>&smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                                           class="text-green-600 hover:text-green-800" title="View MPR">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="3" class="px-6 py-3 text-left text-sm font-bold text-gray-900">OVERALL TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_officer_targets); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_officer_achieved); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php echo number_format($overall_officer_achievement, 1); ?>%
                            </th>
                            <th colspan="4" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Monthly Breakdown Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Monthly Breakdown (<?php echo $selected_year; ?>)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($monthly_breakdown as $month_data): ?>
                        <tr class="hover:bg-gray-50 <?php echo $month_data['month'] == $selected_month ? 'bg-blue-50' : ''; ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $month_data['month_name']; ?> <?php echo $selected_year; ?>
                                <?php if ($month_data['month'] == $selected_month): ?>
                                    <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Selected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($month_data['monthly_budget']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($month_data['monthly_actual']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $month_data['variance'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $month_data['variance'] >= 0 ? '+' : ''; ?><?php echo number_format($month_data['variance_percentage'], 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mx-auto">
                                    <div class="<?php echo $month_data['variance_percentage'] >= 0 ? 'bg-green-500' : 'bg-red-500'; ?> h-2 rounded-full" 
                                         style="width: <?php echo min(100, abs($month_data['variance_percentage']) > 100 ? 100 : (100 + $month_data['variance_percentage'])); ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analysis Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="budget_performance_analysis.php?year=<?php echo $selected_year; ?>&month=<?php echo $selected_month; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Detailed Analysis
                </a>
                
                <a href="budget_variance_report.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Variance Report
                </a>
                
                <a href="officer_performance_dashboard.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-users mr-2"></i>
                    Officer Dashboard
                </a>
                
                <a href="budget_forecasting.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-crystal-ball mr-2"></i>
                    Forecasting
                </a>
            </div>
        </div>
    </div>

    <script>
        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_breakdown); ?>;
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month_name),
                datasets: [{
                    label: 'Budget (₦)',
                    data: monthlyData.map(item => item.monthly_budget),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }, {
                    label: 'Actual (₦)',
                    data: monthlyData.map(item => item.monthly_actual),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Performance Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Above Budget', 'On Budget', 'Below Budget'],
                datasets: [{
                    data: [<?php echo $above_budget_count; ?>, <?php echo $on_budget_count; ?>, <?php echo $below_budget_count; ?>],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' lines';
                            }
                        }
                    }
                }
            }
        });

        // Add smooth hover effects
        document.querySelectorAll('.hover\\:shadow-lg').forEach(element => {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.transition = 'all 0.3s ease';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>