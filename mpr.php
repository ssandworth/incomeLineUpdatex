<?php
require_once 'Database.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class MPRAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get income lines performance data
     */
    public function getIncomeLinePerformance($month, $year) {
        $this->db->query("
            SELECT a.acct_id, a.acct_desc, a.acct_table_name 
            FROM accounts a 
            WHERE a.active = 'Yes' 
            AND a.acct_desc IS NOT NULL 
            ORDER BY a.acct_desc ASC
        ");
        
        return $this->db->resultSet();
    }
    
    /**
     * Get daily collections for a specific account and month
     */
    public function getDailyCollections($table_name, $account_id, $month, $year) {
        $daily_data = [];
        
        for ($day = 1; $day <= 31; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            
            $this->db->query("
                SELECT COALESCE(SUM(credit_amount), 0) as daily_total 
                FROM {$table_name} 
                WHERE acct_id = :account_id 
                AND date = :date 
                AND (approval_status = 'Approved' OR approval_status = '')
            ");
            
            $this->db->bind(':account_id', $account_id);
            $this->db->bind(':date', $date);
            
            $result = $this->db->single();
            $daily_data[$day] = $result['daily_total'] ?? 0;
        }
        
        return $daily_data;
    }
    
    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics($month, $year) {
        // Get current month total
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as current_total 
            FROM account_general_transaction_new 
            WHERE MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
        ");
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $current = $this->db->single();
        
        // Get previous month total
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as previous_total 
            FROM account_general_transaction_new 
            WHERE MONTH(date_of_payment) = :prev_month 
            AND YEAR(date_of_payment) = :prev_year
        ");
        $this->db->bind(':prev_month', $prev_month);
        $this->db->bind(':prev_year', $prev_year);
        $previous = $this->db->single();
        
        // Get year to date
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as ytd_total 
            FROM account_general_transaction_new 
            WHERE YEAR(date_of_payment) = :year 
            AND MONTH(date_of_payment) <= :month
        ");
        $this->db->bind(':year', $year);
        $this->db->bind(':month', $month);
        $ytd = $this->db->single();
        
        // Calculate growth
        $growth = 0;
        if ($previous['previous_total'] > 0) {
            $growth = (($current['current_total'] - $previous['previous_total']) / $previous['previous_total']) * 100;
        }
        
        return [
            'current_total' => $current['current_total'],
            'previous_total' => $previous['previous_total'],
            'ytd_total' => $ytd['ytd_total'],
            'growth_percentage' => $growth
        ];
    }
    
    /**
     * Get top performing income lines
     */
    public function getTopPerformers($month, $year, $limit = 5) {
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                COALESCE(SUM(t.amount_paid), 0) as total_amount,
                COUNT(t.id) as transaction_count
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
            WHERE a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            ORDER BY total_amount DESC
            LIMIT :limit
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $this->db->bind(':limit', $limit);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get weekly performance breakdown
     */
    public function getWeeklyBreakdown($month, $year) {
        $weeks = [];
        $start_date = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $end_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        for ($week = 1; $week <= 5; $week++) {
            $week_start = date('Y-m-d', strtotime($start_date . " + " . (($week - 1) * 7) . " days"));
            $week_end = date('Y-m-d', strtotime($week_start . " + 6 days"));
            
            if ($week_start > $end_date) break;
            if ($week_end > $end_date) $week_end = $end_date;
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as week_total 
                FROM account_general_transaction_new 
                WHERE date_of_payment BETWEEN :week_start AND :week_end
            ");
            
            $this->db->bind(':week_start', $week_start);
            $this->db->bind(':week_end', $week_end);
            
            $result = $this->db->single();
            $weeks[] = [
                'week' => $week,
                'start_date' => $week_start,
                'end_date' => $week_end,
                'total' => $result['week_total']
            ];
        }
        
        return $weeks;
    }
}

$analyzer = new MPRAnalyzer();

// Get current date info
$current_date = date('Y-m-d');
$selected_month = $_GET['smonth'] ?? date('n');
$selected_year = $_GET['syear'] ?? date('Y');
$selected_month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get data
$income_lines = $analyzer->getIncomeLinePerformance($selected_month, $selected_year);
$analytics = $analyzer->getPerformanceAnalytics($selected_month, $selected_year);
$top_performers = $analyzer->getTopPerformers($selected_month, $selected_year);
$weekly_breakdown = $analyzer->getWeeklyBreakdown($selected_month, $selected_year);

// Calculate Sunday positions for the month
$sundays = [];
for ($week = 1; $week <= 5; $week++) {
    $sunday = date('j', strtotime("$selected_year-$selected_month-01 +" . (($week - 1) * 7) . " days Sunday"));
    if ($sunday <= date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year))) {
        $sundays[] = $sunday;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Monthly Performance Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - MPR Dashboard</h1>
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
                        <h2 class="text-2xl font-bold text-gray-900">Monthly Performance Report</h2>
                        <p class="text-gray-600"><?php echo $selected_month_name . ' ' . $selected_year; ?> Collection Summary</p>
                    </div>
                    
                    <!-- Period Selection Form -->
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                            <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                            <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Officer (Optional)</label>
                            <select name="officer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Officers</option>
                                <?php foreach ($officers as $officer): ?>
                                    <option value="<?php echo $officer['user_id']; ?>" 
                                            <?php echo $selected_officer == $officer['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-search mr-2"></i>Load Report
                            </button>
                        </div>
                    </form>
                    
                    <!-- Legacy form for backward compatibility -->
                    <form method="GET" class="hidden" id="legacyForm">
                        <select name="smonth" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="syear" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Year</option>
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Load Report
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Current Month</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['current_total']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Growth Rate</p>
                        <p class="text-2xl font-bold <?php echo $analytics['growth_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($analytics['growth_percentage'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Year to Date</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['ytd_total']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Previous Month</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['previous_total']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Weekly Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Weekly Performance</h3>
                <div class="relative h-48">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Income Lines</h3>
                <div class="space-y-4">
                    <?php foreach ($top_performers as $index => $performer): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900"><?php echo $performer['income_line']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $performer['transaction_count']; ?> transactions</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-gray-900">₦<?php echo number_format($performer['total_amount']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Decision Making Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="mpr_detailed_analysis.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Detailed Analysis
                </a>
                
                <a href="mpr_trends.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Trend Analysis
                </a>
                
                <a href="mpr_forecasting.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Forecasting
                </a>
                
                <a href="mpr_recommendations.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Recommendations
                </a>
                
                <a href="officer_general_performance.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Officer Performance
                </a>
            </div>
        </div>

        <!-- Daily Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Daily Collection Summary</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <?php for ($day = 1; $day <= 31; $day++): ?>
                                <?php 
                                $is_sunday = in_array($day, $sundays);
                                $header_class = $is_sunday ? 'bg-red-100 text-red-800' : 'text-gray-500';
                                ?>
                                <th class="px-2 py-3 text-right text-xs font-medium <?php echo $header_class; ?> uppercase tracking-wider">
                                    <?php if ($is_sunday): ?>Sun<br><?php endif; ?>
                                    <?php echo sprintf('%02d', $day); ?>
                                </th>
                            <?php endfor; ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $daily_totals = array_fill(1, 31, 0);
                        $grand_total = 0;
                        
                        foreach ($income_lines as $line): 
                            $daily_collections = $analyzer->getDailyCollections($line['acct_table_name'], $line['acct_id'], $selected_month, $selected_year);
                            $line_total = array_sum($daily_collections);
                            $grand_total += $line_total;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo ucwords(strtolower($line['acct_desc'])); ?>
                            </td>
                            <?php for ($day = 1; $day <= 31; $day++): ?>
                                <?php 
                                $amount = $daily_collections[$day];
                                $daily_totals[$day] += $amount;
                                $is_sunday = in_array($day, $sundays);
                                $cell_class = $is_sunday ? 'bg-red-50 text-red-800' : 'text-gray-900';
                                ?>
                                <td class="px-2 py-4 whitespace-nowrap text-sm <?php echo $cell_class; ?> text-right">
                                    <?php if ($amount > 0): ?>
                                        <a href="mpr_daily_detail.php?account=<?php echo $line['acct_id']; ?>&date=<?php echo $selected_year . '-' . sprintf('%02d-%02d', $selected_month, $day); ?>" 
                                           class="hover:underline">
                                            <?php echo number_format($amount); ?>
                                        </a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                <?php echo number_format($line_total); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">Total</th>
                            <?php for ($day = 1; $day <= 31; $day++): ?>
                                <?php 
                                $is_sunday = in_array($day, $sundays);
                                $cell_class = $is_sunday ? 'bg-red-100 text-red-800' : 'text-gray-900';
                                ?>
                                <th class="px-2 py-3 text-right text-sm font-bold <?php echo $cell_class; ?>">
                                    <?php echo number_format($daily_totals[$day]); ?>
                                </th>
                            <?php endfor; ?>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                <?php echo number_format($grand_total); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Weekly Performance Chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyData = <?php echo json_encode($weekly_breakdown); ?>;
        
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: weeklyData.map(week => `Week ${week.week}`),
                datasets: [{
                    label: 'Weekly Collections (₦)',
                    data: weeklyData.map(week => week.total),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
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
    </script>
</body>
</html>