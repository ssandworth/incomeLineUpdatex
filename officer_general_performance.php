<?php
require_once 'Database.php';
require_once 'config.php';
require_once 'functions.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class GeneralPerformanceAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get officer overall performance across all months
     */
    public function getOfficerOverallPerformance($year = null) {
        $year = $year ?? date('Y');
        
        $this->db->query("
            SELECT 
                s.user_id,
                s.full_name,
                s.department,
                s.phone,
                COUNT(DISTINCT CONCAT(YEAR(t.date_of_payment), '-', MONTH(t.date_of_payment))) as active_months,
                COUNT(DISTINCT t.date_of_payment) as total_working_days,
                COUNT(t.id) as total_transactions,
                SUM(t.amount_paid) as total_collections,
                AVG(t.amount_paid) as avg_transaction_amount,
                MIN(t.date_of_payment) as first_transaction_date,
                MAX(t.date_of_payment) as last_transaction_date
            FROM staffs s
            LEFT JOIN account_general_transaction_new t ON s.user_id = t.remitting_id
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE s.department = 'Wealth Creation'
            GROUP BY s.user_id, s.full_name, s.department, s.phone
            ORDER BY total_collections DESC
        ");
        
        $this->db->bind(':year', $year);
        $officers = $this->db->resultSet();
        
        // Calculate additional metrics
        foreach ($officers as &$officer) {
            $officer['months_in_year'] = 12;
            $officer['activity_rate'] = ($officer['active_months'] / 12) * 100;
            $officer['daily_average'] = $officer['total_working_days'] > 0 ? 
                $officer['total_collections'] / $officer['total_working_days'] : 0;
            $officer['monthly_average'] = $officer['active_months'] > 0 ? 
                $officer['total_collections'] / $officer['active_months'] : 0;
        }
        
        return $officers;
    }
    
    /**
     * Get officer performance by income line across all months
     */
    public function getOfficerIncomeLinePerformance($officer_id, $year = null) {
        $year = $year ?? date('Y');
        
        $this->db->query("
            SELECT 
                a.acct_id,
                a.acct_desc as income_line,
                COUNT(DISTINCT CONCAT(YEAR(t.date_of_payment), '-', MONTH(t.date_of_payment))) as active_months,
                COUNT(t.id) as total_transactions,
                SUM(t.amount_paid) as total_amount,
                AVG(t.amount_paid) as avg_transaction_amount,
                MIN(t.date_of_payment) as first_collection,
                MAX(t.date_of_payment) as last_collection,
                COUNT(DISTINCT t.date_of_payment) as active_days
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND t.remitting_id = :officer_id
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE a.active = 'Yes' AND a.income_line = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            ORDER BY total_amount DESC
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':year', $year);
        $income_lines = $this->db->resultSet();
        
        // Calculate completion percentages
        foreach ($income_lines as &$line) {
            $line['completion_rate'] = ($line['active_months'] / 12) * 100;
            $line['consistency_score'] = $line['active_days'] > 0 ? 
                ($line['total_transactions'] / $line['active_days']) * 10 : 0; // Arbitrary scoring
            $line['performance_level'] = $this->getPerformanceLevel($line['completion_rate']);
        }
        
        return $income_lines;
    }
    
    /**
     * Get monthly breakdown for officer
     */
    public function getOfficerMonthlyBreakdown($officer_id, $year = null) {
        $year = $year ?? date('Y');
        $monthly_data = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $this->db->query("
                SELECT 
                    COUNT(t.id) as transactions,
                    SUM(t.amount_paid) as total_amount,
                    COUNT(DISTINCT t.date_of_payment) as working_days,
                    COUNT(DISTINCT t.credit_account) as income_lines_used
                FROM account_general_transaction_new t
                WHERE t.remitting_id = :officer_id
                AND YEAR(t.date_of_payment) = :year
                AND MONTH(t.date_of_payment) = :month
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            ");
            
            $this->db->bind(':officer_id', $officer_id);
            $this->db->bind(':year', $year);
            $this->db->bind(':month', $month);
            
            $result = $this->db->single();
            
            $monthly_data[] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'transactions' => $result['transactions'] ?? 0,
                'total_amount' => $result['total_amount'] ?? 0,
                'working_days' => $result['working_days'] ?? 0,
                'income_lines_used' => $result['income_lines_used'] ?? 0,
                'daily_average' => ($result['working_days'] ?? 0) > 0 ? 
                    ($result['total_amount'] ?? 0) / $result['working_days'] : 0
            ];
        }
        
        return $monthly_data;
    }
    
    /**
     * Get performance level based on completion rate
     */
    private function getPerformanceLevel($completion_rate) {
        if ($completion_rate >= 90) {
            return ['level' => 'Excellent', 'class' => 'bg-green-100 text-green-800'];
        } elseif ($completion_rate >= 75) {
            return ['level' => 'Very Good', 'class' => 'bg-blue-100 text-blue-800'];
        } elseif ($completion_rate >= 60) {
            return ['level' => 'Good', 'class' => 'bg-yellow-100 text-yellow-800'];
        } elseif ($completion_rate >= 40) {
            return ['level' => 'Fair', 'class' => 'bg-orange-100 text-orange-800'];
        } else {
            return ['level' => 'Poor', 'class' => 'bg-red-100 text-red-800'];
        }
    }
    
    /**
     * Get officer ranking and percentile
     */
    public function getOfficerRanking($year = null) {
        $officers = $this->getOfficerOverallPerformance($year);
        
        // Calculate percentiles
        $collections = array_column($officers, 'total_collections');
        $collections = array_filter($collections); // Remove zeros
        sort($collections);
        
        if (empty($collections)) return $officers;
        
        $count = count($collections);
        $percentiles = [
            'p90' => $collections[floor($count * 0.9)] ?? 0,
            'p75' => $collections[floor($count * 0.75)] ?? 0,
            'p50' => $collections[floor($count * 0.5)] ?? 0,
            'p25' => $collections[floor($count * 0.25)] ?? 0
        ];
        
        // Add ranking and percentile to each officer
        foreach ($officers as $index => &$officer) {
            $officer['rank'] = $index + 1;
            
            if ($officer['total_collections'] >= $percentiles['p90']) {
                $officer['percentile'] = 'Top 10%';
                $officer['percentile_class'] = 'bg-green-100 text-green-800';
            } elseif ($officer['total_collections'] >= $percentiles['p75']) {
                $officer['percentile'] = 'Top 25%';
                $officer['percentile_class'] = 'bg-blue-100 text-blue-800';
            } elseif ($officer['total_collections'] >= $percentiles['p50']) {
                $officer['percentile'] = 'Top 50%';
                $officer['percentile_class'] = 'bg-yellow-100 text-yellow-800';
            } elseif ($officer['total_collections'] >= $percentiles['p25']) {
                $officer['percentile'] = 'Top 75%';
                $officer['percentile_class'] = 'bg-orange-100 text-orange-800';
            } else {
                $officer['percentile'] = 'Bottom 25%';
                $officer['percentile_class'] = 'bg-red-100 text-red-800';
            }
        }
        
        return $officers;
    }
    
    /**
     * Get department statistics
     */
    public function getDepartmentStatistics($year = null) {
        $year = $year ?? date('Y');
        
        $this->db->query("
            SELECT 
                COUNT(DISTINCT s.user_id) as total_officers,
                COUNT(DISTINCT CASE WHEN t.id IS NOT NULL THEN s.user_id END) as active_officers,
                SUM(t.amount_paid) as total_department_collections,
                COUNT(t.id) as total_department_transactions,
                AVG(t.amount_paid) as avg_transaction_amount
            FROM staffs s
            LEFT JOIN account_general_transaction_new t ON s.user_id = t.remitting_id
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE s.department = 'Wealth Creation'
        ");
        
        $this->db->bind(':year', $year);
        $stats = $this->db->single();
        
        $stats['avg_per_officer'] = $stats['active_officers'] > 0 ? 
            $stats['total_department_collections'] / $stats['active_officers'] : 0;
        $stats['officer_utilization'] = $stats['total_officers'] > 0 ? 
            ($stats['active_officers'] / $stats['total_officers']) * 100 : 0;
        
        return $stats;
    }
}

$analyzer = new GeneralPerformanceAnalyzer();

// Get parameters
$selected_year = $_GET['year'] ?? date('Y');
$selected_officer = $_GET['officer_id'] ?? null;
$page = $_GET['page'] ?? 1;
$per_page = 20;

// Get data
$officers_ranking = $analyzer->getOfficerRanking($selected_year);
$department_stats = $analyzer->getDepartmentStatistics($selected_year);

// Pagination
$total_officers = count($officers_ranking);
$total_pages = ceil($total_officers / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_officers = array_slice($officers_ranking, $offset, $per_page);

// Get specific officer data if selected
$officer_income_lines = [];
$officer_monthly_breakdown = [];
$officer_info = null;

if ($selected_officer) {
    $officer_income_lines = $analyzer->getOfficerIncomeLinePerformance($selected_officer, $selected_year);
    $officer_monthly_breakdown = $analyzer->getOfficerMonthlyBreakdown($selected_officer, $selected_year);
    
    // Find officer info from ranking
    foreach ($officers_ranking as $officer) {
        if ($officer['user_id'] == $selected_officer) {
            $officer_info = $officer;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer General Performance</title>
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
                    <a href="mpr_income_lines_officers.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Officers</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer General Performance</h1>
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
        <!-- Header Section -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-4 lg:mb-0">
                        <h2 class="text-2xl font-bold text-gray-900">Annual Performance Overview</h2>
                        <p class="text-gray-600">Comprehensive performance analysis for <?php echo $selected_year; ?></p>
                    </div>
                    
                    <!-- Year Selection Form -->
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <input type="hidden" name="officer_id" value="<?php echo $selected_officer; ?>">
                        
                        <select name="year" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($y = date('Y') - 3; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i>
                            Load Year
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Department Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Officers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $department_stats['total_officers']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $department_stats['active_officers']; ?> active</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-coins text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Collections</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($department_stats['total_department_collections']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Avg per Officer</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($department_stats['avg_per_officer']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-percentage text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Officer Utilization</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($department_stats['officer_utilization'], 1); ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($selected_officer && $officer_info): ?>
        <!-- Selected Officer Details -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                            <?php echo strtoupper(substr($officer_info['full_name'], 0, 2)); ?>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-xl font-bold text-gray-900"><?php echo $officer_info['full_name']; ?></h3>
                            <p class="text-gray-600"><?php echo $officer_info['department']; ?></p>
                            <p class="text-sm text-gray-500">Rank #<?php echo $officer_info['rank']; ?> • <?php echo $officer_info['percentile']; ?></p>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-600">₦<?php echo number_format($officer_info['total_collections']); ?></div>
                        <div class="text-sm text-gray-500">Total <?php echo $selected_year; ?> Collections</div>
                        <div class="text-sm text-gray-500"><?php echo $officer_info['active_months']; ?>/12 months active</div>
                    </div>
                </div>

                <!-- Officer Performance Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?php echo number_format($officer_info['activity_rate'], 1); ?>%</div>
                        <div class="text-sm text-gray-600">Activity Rate</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">₦<?php echo number_format($officer_info['daily_average']); ?></div>
                        <div class="text-sm text-gray-600">Daily Average</div>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600"><?php echo number_format($officer_info['total_transactions']); ?></div>
                        <div class="text-sm text-gray-600">Total Transactions</div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600"><?php echo $officer_info['total_working_days']; ?></div>
                        <div class="text-sm text-gray-600">Working Days</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section for Selected Officer -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Monthly Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Performance Breakdown</h3>
                <div class="relative h-48">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Income Line Completion Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Income Line Completion Rates</h3>
                <div class="relative h-48">
                    <canvas id="completionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Income Line Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Income Line Performance Analysis</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active Months</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Completion Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance Level</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($officer_income_lines as $line): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="ledger.php?acct_id=<?php echo $line['acct_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <?php echo ucwords(strtolower($line['income_line'])); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $line['active_months']; ?>/12
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $line['completion_rate'] >= 75 ? 'bg-green-500' : ($line['completion_rate'] >= 50 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo $line['completion_rate']; ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($line['completion_rate'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($line['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format($line['total_transactions']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $line['active_days']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($line['avg_transaction_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $line['performance_level']['class']; ?>">
                                    <?php echo $line['performance_level']['level']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Officers Ranking Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Officer Performance Ranking - <?php echo $selected_year; ?></h3>
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_officers); ?> of <?php echo $total_officers; ?> officers
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Collections</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active Months</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Activity Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Average</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Percentile</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($paginated_officers as $officer): ?>
                        <tr class="hover:bg-gray-50 <?php echo $selected_officer == $officer['user_id'] ? 'bg-blue-50' : ''; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php if ($officer['rank'] <= 3): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold
                                            <?php echo $officer['rank'] === 1 ? 'bg-yellow-500' : ($officer['rank'] === 2 ? 'bg-gray-500' : 'bg-orange-500'); ?>">
                                            <i class="fas fa-<?php echo $officer['rank'] === 1 ? 'crown' : ($officer['rank'] === 2 ? 'medal' : 'award'); ?>"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center bg-blue-100 text-blue-800 text-sm font-bold">
                                            <?php echo $officer['rank']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                        <?php echo strtoupper(substr($officer['full_name'], 0, 2)); ?>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $officer['full_name']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $officer['department']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($officer['total_collections']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $officer['active_months']; ?>/12
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $officer['activity_rate'] >= 80 ? 'bg-green-500' : ($officer['activity_rate'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo $officer['activity_rate']; ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($officer['activity_rate'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($officer['monthly_average']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($officer['daily_average']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $officer['percentile_class']; ?>">
                                    <?php echo $officer['percentile']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="?officer_id=<?php echo $officer['user_id']; ?>&year=<?php echo $selected_year; ?>&page=<?php echo $page; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="officer_detailed_report.php?officer_id=<?php echo $officer['user_id']; ?>&month=<?php echo date('n'); ?>&year=<?php echo $selected_year; ?>" 
                                       class="text-green-600 hover:text-green-800" title="Detailed Report">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_officers); ?> of <?php echo $total_officers; ?> results
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="px-3 py-2 text-sm font-medium <?php echo $i === (int)$page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300'; ?> border rounded-md hover:bg-gray-50">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Performance Insights -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights & Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Top Performers</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php foreach (array_slice($officers_ranking, 0, 3) as $top): ?>
                            <li>• <?php echo $top['full_name']; ?> - ₦<?php echo number_format($top['total_collections']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Most Consistent</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $consistent = array_filter($officers_ranking, function($o) { return $o['activity_rate'] >= 90; });
                        usort($consistent, function($a, $b) { return $b['activity_rate'] <=> $a['activity_rate']; });
                        ?>
                        <?php foreach (array_slice($consistent, 0, 3) as $cons): ?>
                            <li>• <?php echo $cons['full_name']; ?> - <?php echo number_format($cons['activity_rate'], 1); ?>% activity</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Improvement Needed</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $needs_improvement = array_filter($officers_ranking, function($o) { return $o['activity_rate'] < 60; });
                        ?>
                        <?php if (!empty($needs_improvement)): ?>
                            <?php foreach (array_slice($needs_improvement, 0, 3) as $improve): ?>
                                <li>• <?php echo $improve['full_name']; ?> - <?php echo number_format($improve['activity_rate'], 1); ?>% activity</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>• All officers meeting minimum activity standards</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if ($selected_officer && !empty($officer_monthly_breakdown)): ?>
    <script>
        // Monthly Performance Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = <?php echo json_encode($officer_monthly_breakdown); ?>;
        
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => item.month_name),
                datasets: [{
                    label: 'Monthly Collections (₦)',
                    data: monthlyData.map(item => item.total_amount),
                    backgroundColor: monthlyData.map(item => 
                        item.total_amount > 0 ? 'rgba(59, 130, 246, 0.8)' : 'rgba(156, 163, 175, 0.3)'
                    ),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
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
                                return 'Amount: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Income Line Completion Chart
        const completionCtx = document.getElementById('completionChart').getContext('2d');
        const incomeLineData = <?php echo json_encode($officer_income_lines); ?>;
        
        new Chart(completionCtx, {
            type: 'horizontalBar',
            data: {
                labels: incomeLineData.map(item => 
                    item.income_line.length > 20 ? item.income_line.substring(0, 20) + '...' : item.income_line
                ),
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: incomeLineData.map(item => item.completion_rate),
                    backgroundColor: incomeLineData.map(item => 
                        item.completion_rate >= 75 ? 'rgba(16, 185, 129, 0.8)' : 
                        (item.completion_rate >= 50 ? 'rgba(245, 158, 11, 0.8)' : 'rgba(239, 68, 68, 0.8)')
                    ),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Completion: ' + context.parsed.x.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>