<?php
require_once 'OfficerPerformanceAnalyzer.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class OfficerDetailedReportAnalyzer {
    private $db;
    private $analyzer;
    
    public function __construct() {
        $this->db = new Database();
        $this->analyzer = new OfficerPerformanceAnalyzer();
    }
    
    /**
     * Get comprehensive officer report
     */
    public function getOfficerDetailedReport($officer_id, $month, $year, $is_other_staff = false) {
        // Basic officer info
        $officer_info = $this->analyzer->getOfficerInfo($officer_id, $is_other_staff);
        
        // Performance metrics
        $performance = $this->analyzer->getOfficerPerformance($officer_id, $month, $year, $is_other_staff);
        $efficiency = $this->analyzer->getOfficerEfficiencyMetrics($officer_id, $month, $year, $is_other_staff);
        $rating = $this->analyzer->getOfficerRating($officer_id, $month, $year, $is_other_staff);
        $trends = $this->analyzer->getOfficerTrends($officer_id, $month, $year, $is_other_staff);
        $daily_data = $this->analyzer->getOfficerDailyPerformance($officer_id, $month, $year, $is_other_staff);
        $insights = $this->analyzer->getPerformanceInsights($officer_id, $month, $year, $is_other_staff);
        
        // Additional analysis
        $weekly_breakdown = $this->getWeeklyBreakdown($officer_id, $month, $year, $is_other_staff);
        $income_line_analysis = $this->getIncomeLineAnalysis($officer_id, $month, $year, $is_other_staff);
        $comparative_analysis = $this->getComparativeAnalysis($officer_id, $month, $year, $is_other_staff);
        
        return [
            'officer_info' => $officer_info,
            'performance' => $performance,
            'efficiency' => $efficiency,
            'rating' => $rating,
            'trends' => $trends,
            'daily_data' => $daily_data,
            'insights' => $insights,
            'weekly_breakdown' => $weekly_breakdown,
            'income_line_analysis' => $income_line_analysis,
            'comparative_analysis' => $comparative_analysis
        ];
    }
    
    /**
     * Get weekly performance breakdown
     */
    private function getWeeklyBreakdown($officer_id, $month, $year, $is_other_staff = false) {
        $weeks = [];
        $start_date = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $end_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        for ($week = 1; $week <= 5; $week++) {
            $week_start = date('Y-m-d', strtotime($start_date . " + " . (($week - 1) * 7) . " days"));
            $week_end = date('Y-m-d', strtotime($week_start . " + 6 days"));
            
            if ($week_start > $end_date) break;
            if ($week_end > $end_date) $week_end = $end_date;
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as week_total,
                       COUNT(*) as week_transactions
                FROM account_general_transaction_new 
                WHERE remitting_id = :officer_id
                AND date_of_payment BETWEEN :week_start AND :week_end
                AND (approval_status = 'Approved' OR approval_status = '')
            ");
            
            $this->db->bind(':officer_id', $officer_id);
            $this->db->bind(':week_start', $week_start);
            $this->db->bind(':week_end', $week_end);
            
            $result = $this->db->single();
            $weeks[] = [
                'week' => $week,
                'start_date' => $week_start,
                'end_date' => $week_end,
                'total' => $result['week_total'],
                'transactions' => $result['week_transactions']
            ];
        }
        
        return $weeks;
    }
    
    /**
     * Get income line analysis for officer
     */
    private function getIncomeLineAnalysis($officer_id, $month, $year, $is_other_staff = false) {
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                COALESCE(SUM(t.amount_paid), 0) as total_amount,
                COUNT(t.id) as transaction_count,
                AVG(t.amount_paid) as avg_transaction_amount,
                MAX(t.amount_paid) as max_transaction,
                MIN(t.amount_paid) as min_transaction
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND t.remitting_id = :officer_id
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
                AND (t.approval_status = 'Approved' OR t.approval_status = '')
            WHERE a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            HAVING total_amount > 0
            ORDER BY total_amount DESC
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get comparative analysis with department peers
     */
    private function getComparativeAnalysis($officer_id, $month, $year, $is_other_staff = false) {
        // Get officer's performance
        $officer_performance = $this->analyzer->getOfficerEfficiencyMetrics($officer_id, $month, $year, $is_other_staff);
        
        // Get department average
        if ($is_other_staff) {
            $this->db->query("
                SELECT 
                    AVG(monthly_totals.total) as dept_avg_collections,
                    AVG(monthly_totals.transactions) as dept_avg_transactions,
                    AVG(monthly_totals.days) as dept_avg_days
                FROM (
                    SELECT 
                        so.id,
                        COALESCE(SUM(t.amount_paid), 0) as total,
                        COUNT(t.id) as transactions,
                        COUNT(DISTINCT t.date_of_payment) as days
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
                SELECT 
                    AVG(monthly_totals.total) as dept_avg_collections,
                    AVG(monthly_totals.transactions) as dept_avg_transactions,
                    AVG(monthly_totals.days) as dept_avg_days
                FROM (
                    SELECT 
                        s.user_id,
                        COALESCE(SUM(t.amount_paid), 0) as total,
                        COUNT(t.id) as transactions,
                        COUNT(DISTINCT t.date_of_payment) as days
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
        
        $dept_avg = $this->db->single();
        
        return [
            'officer' => $officer_performance,
            'department_average' => $dept_avg,
            'collections_vs_dept' => $dept_avg['dept_avg_collections'] > 0 ? 
                ($officer_performance['total_amount'] / $dept_avg['dept_avg_collections']) * 100 : 0,
            'transactions_vs_dept' => $dept_avg['dept_avg_transactions'] > 0 ? 
                ($officer_performance['total_transactions'] / $dept_avg['dept_avg_transactions']) * 100 : 0
        ];
    }
}

$detailed_analyzer = new OfficerDetailedReportAnalyzer();
$officer_id = $_GET['officer_id'] ?? null;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

if (!$officer_id) {
    header('Location: mpr_income_lines_officers.php');
    exit;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));
$is_other_staff = false; // Assuming Wealth Creation officers for now

$report_data = $detailed_analyzer->getOfficerDetailedReport($officer_id, $month, $year, $is_other_staff);
$sundays = $detailed_analyzer->analyzer->getSundayPositions($month, $year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Detailed Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="mpr_income_lines_officers.php?smonth=<?php echo $month; ?>&syear=<?php echo $year; ?>" 
                       class="text-blue-600 hover:text-blue-800 mr-4">← Back to Officers</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer Detailed Report</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Officer Profile Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        <?php echo strtoupper(substr($report_data['officer_info']['full_name'], 0, 2)); ?>
                    </div>
                    <div class="ml-6">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $report_data['officer_info']['full_name']; ?></h2>
                        <p class="text-gray-600"><?php echo $report_data['officer_info']['department']; ?></p>
                        <?php if ($report_data['officer_info']['phone']): ?>
                            <p class="text-sm text-gray-500"><?php echo $report_data['officer_info']['phone']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-right">
                    <span class="px-4 py-2 rounded-full text-lg font-medium <?php echo $report_data['rating']['rating_class']; ?>">
                        <?php echo $report_data['rating']['rating']; ?>
                    </span>
                    <p class="text-sm text-gray-500 mt-2">
                        Performance: <?php echo number_format($report_data['rating']['performance_ratio'], 1); ?>% of department average
                    </p>
                </div>
            </div>
        </div>

        <!-- Key Performance Metrics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-coins text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Collections</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($report_data['efficiency']['total_amount']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-calendar-check text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Attendance Rate</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($report_data['efficiency']['attendance_rate'], 1); ?>%</p>
                        <p class="text-xs text-gray-500"><?php echo $report_data['efficiency']['working_days']; ?> working days</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Daily Average</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($report_data['efficiency']['daily_average']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-receipt text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($report_data['efficiency']['total_transactions']); ?></p>
                        <p class="text-xs text-gray-500">₦<?php echo number_format($report_data['efficiency']['avg_transaction_amount']); ?> avg</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Insights -->
        <?php if (!empty($report_data['insights'])): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($report_data['insights'] as $insight): ?>
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
            <!-- Daily Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Performance Pattern</h3>
                <div class="relative h-48">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- 6-Month Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">6-Month Performance Trend</h3>
                <div class="relative h-48">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Weekly Breakdown -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Weekly Performance Breakdown</h3>
            <div class="relative h-48">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- Income Line Analysis -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Income Line Performance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Highest</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($report_data['income_line_analysis'] as $line): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $line['income_line']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($line['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format($line['transaction_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($line['avg_transaction_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($line['max_transaction']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Comparative Analysis -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Comparative Analysis</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?php echo number_format($report_data['comparative_analysis']['collections_vs_dept'], 1); ?>%
                    </div>
                    <div class="text-sm text-gray-500">vs Department Average (Collections)</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        <?php echo number_format($report_data['comparative_analysis']['transactions_vs_dept'], 1); ?>%
                    </div>
                    <div class="text-sm text-gray-500">vs Department Average (Transactions)</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">
                        ₦<?php echo number_format($report_data['comparative_analysis']['department_average']['dept_avg_collections']); ?>
                    </div>
                    <div class="text-sm text-gray-500">Department Average</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Performance Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_values($report_data['daily_data'])); ?>;
        const dailyLabels = <?php echo json_encode(array_keys($report_data['daily_data'])); ?>;
        const sundayPositions = <?php echo json_encode($sundays); ?>;
        
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Daily Collections (₦)',
                    data: dailyData,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: dailyLabels.map(day => 
                        sundayPositions.includes(parseInt(day)) ? 'rgba(239, 68, 68, 1)' : 'rgba(59, 130, 246, 1)'
                    )
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
                }
            }
        });

        // 6-Month Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php echo json_encode($report_data['trends']); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month_name),
                datasets: [{
                    label: 'Monthly Collections (₦)',
                    data: trendData.map(item => item.total),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
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
                }
            }
        });

        // Weekly Breakdown Chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        const weeklyData = <?php echo json_encode($report_data['weekly_breakdown']); ?>;
        
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: weeklyData.map(week => `Week ${week.week}`),
                datasets: [{
                    label: 'Weekly Collections (₦)',
                    data: weeklyData.map(week => week.total),
                    backgroundColor: 'rgba(139, 69, 19, 0.8)',
                    borderColor: 'rgba(139, 69, 19, 1)',
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
                }
            }
        });
    </script>
</body>
</html>