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

$analyzer = new OfficerPerformanceAnalyzer();

// Get parameters
$account_id = $_GET['acct_id'] ?? null;
$selected_month = $_GET['month'] ?? date('n');
$selected_year = $_GET['year'] ?? date('Y');
$selected_month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

if (!$account_id) {
    header('Location: mpr_income_lines.php');
    exit;
}

// Get account information
$analyzer->db->query("
    SELECT a.*, gl.gl_code_name, gl.gl_category 
    FROM accounts a
    LEFT JOIN account_gl_code gl ON a.gl_code = gl.gl_code
    WHERE a.acct_id = :account_id
");
$analyzer->db->bind(':account_id', $account_id);
$account_info = $analyzer->db->single();

if (!$account_info) {
    header('Location: mpr_income_lines.php');
    exit;
}

// Get officers performance for this income line
$analyzer->db->query("
    SELECT 
        CASE 
            WHEN s.user_id IS NOT NULL THEN s.user_id
            ELSE so.id
        END as officer_id,
        CASE 
            WHEN s.full_name IS NOT NULL THEN s.full_name
            ELSE so.full_name
        END as officer_name,
        CASE 
            WHEN s.department IS NOT NULL THEN s.department
            ELSE so.department
        END as department,
        CASE 
            WHEN s.user_id IS NOT NULL THEN 'wc'
            ELSE 'so'
        END as officer_type,
        COALESCE(SUM(t.amount_paid), 0) as total_amount,
        COUNT(t.id) as transaction_count,
        COUNT(DISTINCT t.date_of_payment) as active_days
    FROM account_general_transaction_new t
    LEFT JOIN staffs s ON t.remitting_id = s.user_id
    LEFT JOIN staffs_others so ON t.remitting_id = so.id
    WHERE t.credit_account = :account_id
    AND MONTH(t.date_of_payment) = :month 
    AND YEAR(t.date_of_payment) = :year
    AND (t.approval_status = 'Approved' OR t.approval_status = '')
    AND (s.user_id IS NOT NULL OR so.id IS NOT NULL)
    GROUP BY officer_id, officer_name, department, officer_type
    ORDER BY total_amount DESC
");

$analyzer->db->bind(':account_id', $account_id);
$analyzer->db->bind(':month', $selected_month);
$analyzer->db->bind(':year', $selected_year);
$officers_performance = $analyzer->db->resultSet();

// Get daily collections for this income line
$daily_collections = $analyzer->getDailyCollectionsForIncomeLine($account_id, $selected_month, $selected_year);
$sundays = $analyzer->getSundayPositions($selected_month, $selected_year);

// Calculate performance metrics
$total_amount = array_sum($daily_collections);
$active_days = count(array_filter($daily_collections));
$avg_daily = $active_days > 0 ? $total_amount / $active_days : 0;
$peak_day = array_keys($daily_collections, max($daily_collections))[0];
$peak_amount = max($daily_collections);

// Get comparison with previous month
$prev_month = $selected_month == 1 ? 12 : $selected_month - 1;
$prev_year = $selected_month == 1 ? $selected_year - 1 : $selected_year;
$prev_daily_collections = $analyzer->getDailyCollectionsForIncomeLine($account_id, $prev_month, $prev_year);
$prev_total = array_sum($prev_daily_collections);
$growth_rate = $prev_total > 0 ? (($total_amount - $prev_total) / $prev_total) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $account_info['acct_desc']; ?> Analysis</title>
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
                    <a href="mpr_income_lines.php?smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                       class="text-blue-600 hover:text-blue-800 mr-4">← Back to Income Lines</a>
                    <h1 class="text-xl font-bold text-gray-900"><?php echo $account_info['acct_desc']; ?> Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $selected_month_name . ' ' . $selected_year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Income Line Overview -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $account_info['acct_desc']; ?></h2>
                        <p class="text-gray-600">
                            <?php echo $account_info['gl_category'] ?? 'General'; ?> Account | 
                            <?php echo $account_info['gl_code_name'] ?? 'Standard'; ?> | 
                            Code: <strong><?php echo $account_info['acct_code'] ?? 'N/A'; ?></strong>
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl font-bold text-blue-600">₦<?php echo number_format($total_amount); ?></div>
                        <div class="text-sm text-gray-500">Total Collections</div>
                        <div class="text-sm <?php echo $growth_rate >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $growth_rate >= 0 ? '+' : ''; ?><?php echo number_format($growth_rate, 1); ?>% vs last month
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600"><?php echo $active_days; ?></div>
                        <div class="text-sm text-gray-600">Active Days</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">₦<?php echo number_format($avg_daily); ?></div>
                        <div class="text-sm text-gray-600">Daily Average</div>
                    </div>
                    <div class="text-center p-4 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $peak_day; ?></div>
                        <div class="text-sm text-gray-600">Peak Day</div>
                        <div class="text-xs text-gray-500">₦<?php echo number_format($peak_amount); ?></div>
                    </div>
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600"><?php echo count($officers_performance); ?></div>
                        <div class="text-sm text-gray-600">Contributing Officers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Daily Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Collection Pattern</h3>
                <div class="relative h-48">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Officer Contribution Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Officer Contributions</h3>
                <div class="relative h-48">
                    <canvas id="officerChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Officers Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Officer Performance Breakdown</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Contribution %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($officers_performance as $index => $officer): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold
                                    <?php echo $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-500' : ($index === 2 ? 'bg-orange-500' : 'bg-blue-500')); ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo $officer['officer_name']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $officer['department'] === 'Wealth Creation' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo $officer['department']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($officer['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format($officer['transaction_count']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $officer['active_days']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($officer['active_days'] > 0 ? $officer['total_amount'] / $officer['active_days'] : 0); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format(($officer['total_amount'] / $total_amount) * 100, 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="mpr_income_lines_officers.php?sstaff=<?php echo $officer['officer_id'] . ($officer['officer_type'] === 'so' ? '-so' : ''); ?>&smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Officer Details">
                                        <i class="fas fa-user-chart"></i>
                                    </a>
                                    <a href="ledger.php?acct_id=<?php echo $account_id; ?>" 
                                       class="text-green-600 hover:text-green-800" title="View Ledger">
                                        <i class="fas fa-book"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Daily Performance Breakdown -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Daily Performance Breakdown</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Day Type</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                            <?php 
                            if (!checkdate($selected_month, $day, $selected_year)) continue;
                            $amount = $daily_collections[$day];
                            $is_sunday = in_array($day, $sundays);
                            $performance_level = $amount > $avg_daily * 1.2 ? 'high' : ($amount > $avg_daily * 0.8 ? 'normal' : 'low');
                            ?>
                            <tr class="hover:bg-gray-50 <?php echo $is_sunday ? 'bg-red-50' : ''; ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo sprintf('%02d', $day); ?> <?php echo date('M Y', mktime(0, 0, 0, $selected_month, $day, $selected_year)); ?>
                                    <?php if ($is_sunday): ?>
                                        <span class="ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Sunday
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    <?php if ($amount > 0): ?>
                                        <span class="<?php echo $performance_level === 'high' ? 'text-green-600' : ($performance_level === 'low' ? 'text-red-600' : 'text-gray-900'); ?>">
                                            ₦<?php echo number_format($amount); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">₦0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($is_sunday): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Weekend
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Weekday
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($amount > 0): ?>
                                        <div class="flex items-center justify-center">
                                            <div class="w-16 bg-gray-200 rounded-full h-2">
                                                <div class="<?php echo $performance_level === 'high' ? 'bg-green-500' : ($performance_level === 'low' ? 'bg-red-500' : 'bg-blue-500'); ?> h-2 rounded-full" 
                                                     style="width: <?php echo min(100, ($amount / $peak_amount) * 100); ?>%"></div>
                                            </div>
                                            <span class="ml-2 text-xs text-gray-500">
                                                <?php echo number_format(($amount / $peak_amount) * 100, 1); ?>%
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">No activity</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($amount > 0): ?>
                                        <a href="ledger.php?acct_id=<?php echo $account_id; ?>&d1=<?php echo sprintf('%02d/%02d/%04d', $day, $selected_month, $selected_year); ?>&d2=<?php echo sprintf('%02d/%02d/%04d', $day, $selected_month, $selected_year); ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Analysis Tools -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analysis Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="ledger_analysis.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Detailed Analysis
                </a>
                
                <a href="ledger_trends.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>
                    Trend Analysis
                </a>
                
                <a href="ledger_reconciliation.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Reconciliation
                </a>
                
                <a href="ledger_audit.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>
                    Audit Trail
                </a>
            </div>
        </div>
    </div>

    <script>
        // Daily Performance Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_values($daily_collections)); ?>;
        const dailyLabels = <?php echo json_encode(array_keys($daily_collections)); ?>;
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
                    ),
                    pointBorderColor: dailyLabels.map(day => 
                        sundayPositions.includes(parseInt(day)) ? 'rgba(239, 68, 68, 1)' : 'rgba(59, 130, 246, 1)'
                    ),
                    pointRadius: 4
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
                                const isSunday = sundayPositions.includes(parseInt(context.label));
                                return 'Amount: ₦' + context.parsed.y.toLocaleString() + 
                                       (isSunday ? ' (Sunday)' : '');
                            }
                        }
                    }
                }
            }
        });

        // Officer Contribution Chart
        const officerCtx = document.getElementById('officerChart').getContext('2d');
        const officerData = <?php echo json_encode($officers_performance); ?>;
        
        new Chart(officerCtx, {
            type: 'doughnut',
            data: {
                labels: officerData.map(item => item.officer_name),
                datasets: [{
                    data: officerData.map(item => item.total_amount),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 69, 19, 0.8)',
                        'rgba(75, 85, 99, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const percentage = ((context.parsed / <?php echo $total_amount; ?>) * 100).toFixed(1);
                                return context.label + ': ₦' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>