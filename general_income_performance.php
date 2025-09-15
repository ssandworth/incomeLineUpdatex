<?php
require_once 'BudgetManager.php';
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

$budget_manager = new BudgetManager();
$performance_analyzer = new OfficerPerformanceAnalyzer();

// Check access permissions
$can_view = $budget_manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get parameters
$selected_month = $_GET['month'] ?? date('n');
$selected_year = $_GET['year'] ?? date('Y');
$selected_income_line = $_GET['income_line'] ?? null;
$view_type = $_GET['view'] ?? 'monthly'; // monthly, ytd, annual

$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get real-time data
$budget_performance = $budget_manager->getBudgetPerformanceRealTime($selected_year, $selected_month);
$income_lines_performance = $performance_analyzer->getIncomeLinePerformance($selected_month, $selected_year);

// Get budget lines for the year
$budget_lines = $budget_manager->getBudgetLines($selected_year);

// Create comprehensive performance data
$comprehensive_performance = [];
foreach ($budget_lines as $budget_line) {
    // Get budget amount for selected month
    $month_field = strtolower(date('F', mktime(0, 0, 0, $selected_month, 1))) . '_budget';
    $monthly_budget = $budget_line[$month_field];
    
    // Get actual collections from real-time data
    $actual_amount = 0;
    foreach ($budget_performance as $perf) {
        if ($perf['acct_id'] === $budget_line['acct_id']) {
            $actual_amount = $perf['actual_amount'];
            break;
        }
    }
    
    // Get additional metrics from income lines performance
    $transaction_count = 0;
    $unique_officers = 0;
    foreach ($income_lines_performance as $income_perf) {
        if ($income_perf['acct_id'] === $budget_line['acct_id']) {
            $transaction_count = $income_perf['transaction_count'];
            $unique_officers = $income_perf['unique_officers'];
            break;
        }
    }
    
    // Calculate performance metrics
    $variance_amount = $actual_amount - $monthly_budget;
    $variance_percentage = $monthly_budget > 0 ? (($actual_amount - $monthly_budget) / $monthly_budget) * 100 : 0;
    
    // Determine performance status
    if ($actual_amount > $monthly_budget * 1.05) {
        $status = 'Above Budget';
        $status_class = 'bg-green-100 text-green-800';
    } elseif ($actual_amount >= $monthly_budget * 0.95) {
        $status = 'On Budget';
        $status_class = 'bg-blue-100 text-blue-800';
    } else {
        $status = 'Below Budget';
        $status_class = 'bg-red-100 text-red-800';
    }
    
    // Calculate daily average and progress
    $days_in_month = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
    $current_day = date('j');
    $daily_budget = $monthly_budget / $days_in_month;
    $daily_actual = $actual_amount / $current_day;
    $progress_percentage = ($current_day / $days_in_month) * 100;
    $expected_to_date = $daily_budget * $current_day;
    
    $comprehensive_performance[] = [
        'acct_id' => $budget_line['acct_id'],
        'acct_desc' => $budget_line['acct_desc'],
        'monthly_budget' => $monthly_budget,
        'annual_budget' => $budget_line['annual_budget'],
        'actual_amount' => $actual_amount,
        'variance_amount' => $variance_amount,
        'variance_percentage' => $variance_percentage,
        'status' => $status,
        'status_class' => $status_class,
        'transaction_count' => $transaction_count,
        'unique_officers' => $unique_officers,
        'daily_budget' => $daily_budget,
        'daily_actual' => $daily_actual,
        'progress_percentage' => $progress_percentage,
        'expected_to_date' => $expected_to_date,
        'performance_ratio' => $expected_to_date > 0 ? ($actual_amount / $expected_to_date) * 100 : 0
    ];
}

// Sort by actual amount descending
usort($comprehensive_performance, function($a, $b) {
    return $b['actual_amount'] <=> $a['actual_amount'];
});

// Calculate summary statistics
$total_budget = array_sum(array_column($comprehensive_performance, 'monthly_budget'));
$total_actual = array_sum(array_column($comprehensive_performance, 'actual_amount'));
$total_variance = $total_actual - $total_budget;
$overall_variance_percentage = $total_budget > 0 ? ($total_variance / $total_budget) * 100 : 0;

$above_budget_count = count(array_filter($comprehensive_performance, function($perf) {
    return $perf['status'] === 'Above Budget';
}));

$on_budget_count = count(array_filter($comprehensive_performance, function($perf) {
    return $perf['status'] === 'On Budget';
}));

$below_budget_count = count(array_filter($comprehensive_performance, function($perf) {
    return $perf['status'] === 'Below Budget';
}));

// Get YTD data if view type is YTD
$ytd_data = [];
if ($view_type === 'ytd') {
    for ($m = 1; $m <= $selected_month; $m++) {
        $ytd_performance = $budget_manager->getBudgetPerformanceRealTime($selected_year, $m);
        foreach ($ytd_performance as $perf) {
            if (!isset($ytd_data[$perf['acct_id']])) {
                $ytd_data[$perf['acct_id']] = [
                    'acct_desc' => $perf['acct_desc'],
                    'total_budget' => 0,
                    'total_actual' => 0
                ];
            }
            $ytd_data[$perf['acct_id']]['total_budget'] += $perf['budgeted_amount'];
            $ytd_data[$perf['acct_id']]['total_actual'] += $perf['actual_amount'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - General Income Line Performance</title>
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
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Income Line Performance</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-xs text-gray-500">Real-time Data</span>
                    </div>
                    <span class="text-sm text-gray-700">Welcome, <?php echo $staff['full_name']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $staff['department']; ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section with Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h2 class="text-2xl font-bold text-gray-900">General Income Line Performance Dashboard</h2>
                    <p class="text-gray-600">
                        Real-time budget vs actual performance tracking for 
                        <span class="font-semibold text-blue-600"><?php echo $month_name . ' ' . $selected_year; ?></span>
                    </p>
                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                        <div class="flex items-center">
                            <i class="fas fa-clock mr-1"></i>
                            <span>Last updated: <?php echo date('d/m/Y H:i:s'); ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-calendar mr-1"></i>
                            <span>Day <?php echo date('j'); ?> of <?php echo date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Controls -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">View Type</label>
                            <select name="view" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="monthly" <?php echo $view_type === 'monthly' ? 'selected' : ''; ?>>Monthly View</option>
                                <option value="ytd" <?php echo $view_type === 'ytd' ? 'selected' : ''; ?>>Year-to-Date</option>
                                <option value="annual" <?php echo $view_type === 'annual' ? 'selected' : ''; ?>>Annual View</option>
                            </select>
                        </div>
                        
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
                                <?php for ($y = date('Y') - 3; $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-sync mr-2"></i>Refresh
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Budget</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_budget); ?></p>
                        <p class="text-xs text-gray-500"><?php echo $month_name; ?> target</p>
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
                        <p class="text-xs text-gray-500">Real-time data</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $overall_variance_percentage >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Overall Variance</p>
                        <p class="text-2xl font-bold <?php echo $overall_variance_percentage >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $overall_variance_percentage >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance_percentage, 1); ?>%
                        </p>
                        <p class="text-xs text-gray-500">vs budget</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-pie text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Performance Status</p>
                        <div class="text-sm text-gray-900">
                            <div class="text-green-600 font-bold"><?php echo $above_budget_count; ?> Above</div>
                            <div class="text-blue-600"><?php echo $on_budget_count; ?> On Target</div>
                            <div class="text-red-600"><?php echo $below_budget_count; ?> Below</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Overview Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Budget vs Actual Performance Overview</h3>
                <div class="flex items-center space-x-2">
                    <button onclick="toggleChartType()" id="chartToggle" 
                            class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm hover:bg-blue-200">
                        <i class="fas fa-exchange-alt mr-1"></i>Switch View
                    </button>
                    <button onclick="exportChart()" 
                            class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200">
                        <i class="fas fa-download mr-1"></i>Export
                    </button>
                </div>
            </div>
            <div class="relative h-64">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Detailed Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Detailed Income Line Performance - <?php echo $month_name . ' ' . $selected_year; ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <button onclick="exportToExcel()" 
                                class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                            <i class="fas fa-file-excel mr-1"></i>Excel
                        </button>
                        <button onclick="printReport()" 
                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                            <i class="fas fa-print mr-1"></i>Print
                        </button>
                        <button onclick="refreshData()" 
                                class="px-3 py-1 bg-purple-600 text-white rounded text-sm hover:bg-purple-700">
                            <i class="fas fa-sync mr-1"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table id="performanceTable" class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Variance %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Officers</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Avg</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($comprehensive_performance as $perf): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 rounded-full mr-3 <?php echo $perf['status'] === 'Above Budget' ? 'bg-green-500' : ($perf['status'] === 'On Budget' ? 'bg-blue-500' : 'bg-red-500'); ?>"></div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo $perf['acct_desc']; ?></div>
                                        <div class="text-xs text-gray-500">ID: <?php echo $perf['acct_id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <div class="font-medium">₦<?php echo number_format($perf['monthly_budget']); ?></div>
                                <div class="text-xs text-gray-500">₦<?php echo number_format($perf['daily_budget']); ?>/day</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <div class="font-bold text-lg">₦<?php echo number_format($perf['actual_amount']); ?></div>
                                <div class="text-xs text-gray-500">₦<?php echo number_format($perf['daily_actual']); ?>/day</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                                <?php echo $perf['variance_amount'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $perf['variance_amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format(abs($perf['variance_amount'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $perf['variance_percentage'] >= 0 ? 'bg-green-500' : 'bg-red-500'; ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, abs($perf['variance_percentage'])); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs font-medium <?php echo $perf['variance_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $perf['variance_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($perf['variance_percentage'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $perf['status_class']; ?>">
                                    <?php echo $perf['status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-12 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $perf['performance_ratio'] >= 100 ? 'bg-green-500' : ($perf['performance_ratio'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $perf['performance_ratio']); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($perf['performance_ratio'], 1); ?>%
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?php echo number_format($perf['progress_percentage'], 1); ?>% of month
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm font-bold text-gray-900"><?php echo number_format($perf['transaction_count']); ?></div>
                                <div class="text-xs text-gray-500">transactions</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-sm font-bold text-gray-900"><?php echo $perf['unique_officers']; ?></div>
                                <div class="text-xs text-gray-500">officers</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <div class="font-medium">₦<?php echo number_format($perf['daily_actual']); ?></div>
                                <div class="text-xs text-gray-500">current rate</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="budget_details.php?id=<?php echo $perf['acct_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Budget Details">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                    <a href="ledger.php?acct_id=<?php echo $perf['acct_id']; ?>" 
                                       class="text-green-600 hover:text-green-800" title="View Ledger">
                                        <i class="fas fa-book"></i>
                                    </a>
                                    <a href="mpr_income_line.php?acct_id=<?php echo $perf['acct_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                                       class="text-purple-600 hover:text-purple-800" title="Detailed Analysis">
                                        <i class="fas fa-analytics"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_budget); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_actual); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold <?php echo $total_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $total_variance >= 0 ? '+' : ''; ?>₦<?php echo number_format(abs($total_variance)); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold <?php echo $overall_variance_percentage >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $overall_variance_percentage >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance_percentage, 1); ?>%
                            </th>
                            <th colspan="6" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Performance Insights and Quick Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Performance Insights -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
                <div class="space-y-4">
                    <?php 
                    $top_performer = $comprehensive_performance[0] ?? null;
                    $worst_performer = end($comprehensive_performance);
                    ?>
                    
                    <?php if ($top_performer): ?>
                    <div class="border-l-4 border-green-500 pl-4 bg-green-50 p-3 rounded-r">
                        <h4 class="font-medium text-green-900">Top Performer</h4>
                        <p class="text-sm text-green-800">
                            <strong><?php echo $top_performer['acct_desc']; ?></strong> - 
                            ₦<?php echo number_format($top_performer['actual_amount']); ?> collected
                            (<?php echo number_format($top_performer['variance_percentage'], 1); ?>% vs budget)
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($worst_performer && $worst_performer['variance_percentage'] < -10): ?>
                    <div class="border-l-4 border-red-500 pl-4 bg-red-50 p-3 rounded-r">
                        <h4 class="font-medium text-red-900">Needs Attention</h4>
                        <p class="text-sm text-red-800">
                            <strong><?php echo $worst_performer['acct_desc']; ?></strong> - 
                            <?php echo number_format(abs($worst_performer['variance_percentage']), 1); ?>% below budget
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="border-l-4 border-blue-500 pl-4 bg-blue-50 p-3 rounded-r">
                        <h4 class="font-medium text-blue-900">Month Progress</h4>
                        <p class="text-sm text-blue-800">
                            Day <?php echo date('j'); ?> of <?php echo date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year)); ?> 
                            (<?php echo number_format((date('j') / date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year))) * 100, 1); ?>% complete)
                        </p>
                    </div>
                    
                    <?php if ($overall_variance_percentage >= 10): ?>
                    <div class="border-l-4 border-green-500 pl-4 bg-green-50 p-3 rounded-r">
                        <h4 class="font-medium text-green-900">Excellent Performance</h4>
                        <p class="text-sm text-green-800">
                            Overall performance is <?php echo number_format($overall_variance_percentage, 1); ?>% above budget. 
                            Consider increasing targets for next period.
                        </p>
                    </div>
                    <?php elseif ($overall_variance_percentage <= -10): ?>
                    <div class="border-l-4 border-red-500 pl-4 bg-red-50 p-3 rounded-r">
                        <h4 class="font-medium text-red-900">Performance Alert</h4>
                        <p class="text-sm text-red-800">
                            Overall performance is <?php echo number_format(abs($overall_variance_percentage), 1); ?>% below budget. 
                            Review strategies and provide support.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 gap-3">
                    <a href="budget_management.php?year=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-blue-600 rounded-lg mr-3 group-hover:bg-blue-700 transition-colors">
                            <i class="fas fa-cog text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Manage Budgets</div>
                            <div class="text-xs text-gray-500">Edit budget allocations</div>
                        </div>
                    </a>

                    <a href="officer_target_management.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-green-600 rounded-lg mr-3 group-hover:bg-green-700 transition-colors">
                            <i class="fas fa-bullseye text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Officer Targets</div>
                            <div class="text-xs text-gray-500">Set monthly targets</div>
                        </div>
                    </a>

                    <a href="budget_variance_report.php?year=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-purple-600 rounded-lg mr-3 group-hover:bg-purple-700 transition-colors">
                            <i class="fas fa-chart-bar text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Variance Analysis</div>
                            <div class="text-xs text-gray-500">Detailed variance report</div>
                        </div>
                    </a>

                    <a href="budget_forecasting.php?year=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-orange-600 rounded-lg mr-3 group-hover:bg-orange-700 transition-colors">
                            <i class="fas fa-crystal-ball text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Budget Forecasting</div>
                            <div class="text-xs text-gray-500">Predict future performance</div>
                        </div>
                    </a>

                    <a href="mpr_income_lines.php?smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-teal-50 hover:bg-teal-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-teal-600 rounded-lg mr-3 group-hover:bg-teal-700 transition-colors">
                            <i class="fas fa-table text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Daily MPR</div>
                            <div class="text-xs text-gray-500">Daily collection summary</div>
                        </div>
                    </a>

                    <a href="mpr_income_lines_officers.php?smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                       class="flex items-center p-3 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-indigo-600 rounded-lg mr-3 group-hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-users text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Officer Performance</div>
                            <div class="text-xs text-gray-500">Individual officer analysis</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChartType = 'bar';
        let performanceChart;

        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
        });

        function initializeChart() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceData = <?php echo json_encode($comprehensive_performance); ?>;
            
            if (performanceChart) {
                performanceChart.destroy();
            }
            
            performanceChart = new Chart(ctx, {
                type: currentChartType,
                data: {
                    labels: performanceData.map(item => 
                        item.acct_desc.length > 20 ? item.acct_desc.substring(0, 20) + '...' : item.acct_desc
                    ),
                    datasets: [{
                        label: 'Budget (₦)',
                        data: performanceData.map(item => item.monthly_budget),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2
                    }, {
                        label: 'Actual (₦)',
                        data: performanceData.map(item => item.actual_amount),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
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
                                },
                                afterLabel: function(context) {
                                    const dataIndex = context.dataIndex;
                                    const item = performanceData[dataIndex];
                                    return [
                                        'Variance: ' + (item.variance_percentage >= 0 ? '+' : '') + item.variance_percentage.toFixed(1) + '%',
                                        'Status: ' + item.status,
                                        'Transactions: ' + item.transaction_count.toLocaleString(),
                                        'Officers: ' + item.unique_officers
                                    ];
                                }
                            }
                        },
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        function toggleChartType() {
            currentChartType = currentChartType === 'bar' ? 'line' : 'bar';
            initializeChart();
            
            const toggleBtn = document.getElementById('chartToggle');
            toggleBtn.innerHTML = '<i class="fas fa-exchange-alt mr-1"></i>' + 
                (currentChartType === 'bar' ? 'Line View' : 'Bar View');
        }

        function exportChart() {
            const link = document.createElement('a');
            link.download = `income_line_performance_${<?php echo $selected_month; ?>}_${<?php echo $selected_year; ?>}.png`;
            link.href = performanceChart.toBase64Image();
            link.click();
        }

        function exportToExcel() {
            // Simple CSV export
            let csv = 'Income Line,Budget,Actual,Variance,Variance %,Status,Transactions,Officers,Daily Average\n';
            
            const table = document.getElementById('performanceTable');
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [];
                
                // Income Line
                rowData.push('"' + cells[0].querySelector('.text-sm.font-medium').textContent.trim() + '"');
                // Budget
                rowData.push(cells[1].querySelector('.font-medium').textContent.replace(/[₦,]/g, ''));
                // Actual
                rowData.push(cells[2].querySelector('.font-bold').textContent.replace(/[₦,]/g, ''));
                // Variance
                rowData.push(cells[3].textContent.replace(/[₦,+]/g, ''));
                // Variance %
                rowData.push(cells[4].querySelector('span:last-child').textContent.replace(/[%+]/g, ''));
                // Status
                rowData.push('"' + cells[5].querySelector('span').textContent.trim() + '"');
                // Transactions
                rowData.push(cells[7].querySelector('.font-bold').textContent.replace(/,/g, ''));
                // Officers
                rowData.push(cells[8].querySelector('.font-bold').textContent);
                // Daily Average
                rowData.push(cells[9].querySelector('.font-medium').textContent.replace(/[₦,]/g, ''));
                
                csv += rowData.join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `income_line_performance_${<?php echo $selected_month; ?>}_${<?php echo $selected_year; ?>}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function printReport() {
            window.print();
        }

        function refreshData() {
            window.location.reload();
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            const refreshIndicator = document.createElement('div');
            refreshIndicator.className = 'fixed top-4 right-4 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm z-50';
            refreshIndicator.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i>Refreshing data...';
            document.body.appendChild(refreshIndicator);
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }, 300000); // 5 minutes

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

        // Add loading states for buttons
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', function() {
                if (this.type === 'submit') {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                    this.disabled = true;
                }
            });
        });
    </script>

    <!-- Print Styles -->
    <style media="print">
        .no-print { display: none !important; }
        body { background: white !important; }
        .shadow-md { box-shadow: none !important; }
        .bg-gray-50 { background: white !important; }
    </style>
</body>
</html>