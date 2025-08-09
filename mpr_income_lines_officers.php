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

// Get current date info
$current_date = date('Y-m-d');
$selected_month = $_GET['smonth'] ?? date('n');
$selected_year = $_GET['syear'] ?? date('Y');
$page = $_GET['page'] ?? 1;
$per_page = 20;
$selected_officer = $_GET['sstaff'] ?? null;

// Parse officer ID and type
$is_other_staff = false;
$officer_id = null;
if ($selected_officer) {
    if (strpos($selected_officer, '-so') !== false) {
        $officer_id = str_replace('-so', '', $selected_officer);
        $is_other_staff = true;
    } else {
        $officer_id = $selected_officer;
    }
}

$selected_month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get data
$wc_officers = $analyzer->getWealthCreationOfficers();
$other_officers = $analyzer->getOtherOfficers();
$officer_comparison = $analyzer->getOfficerComparison($selected_month, $selected_year);
$top_performers = $analyzer->getTopPerformers($selected_month, $selected_year, 5);
$sundays = $analyzer->getSundayPositions($selected_month, $selected_year);

// Filter for Wealth Creation department only
$officer_comparison = array_filter($officer_comparison, function($officer) {
    return $officer['department'] === 'Wealth Creation';
});

// Pagination for officer comparison
$total_officers = count($officer_comparison);
$total_pages = ceil($total_officers / $per_page);
$offset = ($page - 1) * $per_page;
$paginated_officers = array_slice($officer_comparison, $offset, $per_page);

// Get specific officer data if selected
$officer_info = null;
$officer_performance = [];
$officer_daily_data = [];
$officer_rating = null;
$officer_trends = [];
$officer_insights = [];

if ($officer_id) {
    $officer_info = $analyzer->getOfficerInfo($officer_id, $is_other_staff);
    $officer_performance = $analyzer->getOfficerPerformance($officer_id, $selected_month, $selected_year, $is_other_staff);
    $officer_daily_data = $analyzer->getOfficerDailyPerformance($officer_id, $selected_month, $selected_year, $is_other_staff);
    $officer_rating = $analyzer->getOfficerRating($officer_id, $selected_month, $selected_year, $is_other_staff);
    $officer_trends = $analyzer->getOfficerTrends($officer_id, $selected_month, $selected_year, $is_other_staff);
    $officer_insights = $analyzer->getPerformanceInsights($officer_id, $selected_month, $selected_year, $is_other_staff);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Performance Analysis</title>
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
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Officer Performance</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="mpr_income_lines.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-chart-line mr-1"></i>
                        General Summary
                    </a>
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
                        <h2 class="text-2xl font-bold text-gray-900">Officer Performance Analysis</h2>
                        <p class="text-gray-600">
                            <?php echo $selected_month_name . ' ' . $selected_year; ?> Collection Performance by Officer
                        </p>
                    </div>
                    
                    <!-- Filter Form -->
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <select name="sstaff" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Officers</option>
                            <optgroup label="Wealth Creation Officers">
                                <?php foreach ($wc_officers as $officer): ?>
                                    <option value="<?php echo $officer['user_id']; ?>" 
                                            <?php echo $selected_officer == $officer['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo $officer['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Other Officers">
                                <?php foreach ($other_officers as $officer): ?>
                                    <option value="<?php echo $officer['id']; ?>-so" 
                                            <?php echo $selected_officer == $officer['id'] . '-so' ? 'selected' : ''; ?>>
                                        <?php echo $officer['full_name'] . ' - ' . $officer['department']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        
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
                            <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-search mr-2"></i>
                            Analyze
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($officer_info): ?>
        <!-- Officer Details Section -->
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
                            <?php if ($officer_info['phone']): ?>
                                <p class="text-sm text-gray-500"><?php echo $officer_info['phone']; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-right">
                        <span class="px-4 py-2 rounded-full text-sm font-medium <?php echo $officer_rating['rating_class']; ?>">
                            <?php echo $officer_rating['rating']; ?>
                        </span>
                        <p class="text-sm text-gray-500 mt-1">
                            Performance: <?php echo number_format($officer_rating['performance_ratio'], 1); ?>%
                        </p>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">₦<?php echo number_format($officer_rating['officer_total']); ?></div>
                        <div class="text-sm text-gray-500">Total Collections</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?php echo $analyzer->getOfficerEfficiencyMetrics($officer_id, $selected_month, $selected_year, $is_other_staff)['working_days']; ?></div>
                        <div class="text-sm text-gray-500">Working Days</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600"><?php echo $analyzer->getOfficerEfficiencyMetrics($officer_id, $selected_month, $selected_year, $is_other_staff)['total_transactions']; ?></div>
                        <div class="text-sm text-gray-500">Transactions</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600">₦<?php echo number_format($analyzer->getOfficerEfficiencyMetrics($officer_id, $selected_month, $selected_year, $is_other_staff)['daily_average']); ?></div>
                        <div class="text-sm text-gray-500">Daily Average</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Insights -->
        <?php if (!empty($officer_insights)): ?>
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($officer_insights as $insight): ?>
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
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <?php if ($officer_id): ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Performance Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">6-Month Performance Trend</h3>
                <canvas id="trendChart" width="400" height="200"></canvas>
            </div>

            <!-- Income Line Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Income Line Distribution</h3>
                <canvas id="distributionChart" width="400" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Top Performers Section -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Top Performers - <?php echo $selected_month_name . ' ' . $selected_year; ?></h3>
                    <a href="officer_performance_detailed.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        <i class="fas fa-chart-bar mr-1"></i>
                        Detailed Analysis
                    </a>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach (array_slice($top_performers, 0, 6) as $index => $performer): ?>
                    <div class="bg-gradient-to-r <?php echo $index === 0 ? 'from-yellow-400 to-yellow-600' : ($index === 1 ? 'from-gray-400 to-gray-600' : ($index === 2 ? 'from-orange-400 to-orange-600' : 'from-blue-400 to-blue-600')); ?> rounded-lg p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold">#<?php echo $index + 1; ?></div>
                                <div class="text-sm opacity-90">Rank</div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold">₦<?php echo number_format($performer['total_collections']); ?></div>
                                <div class="text-xs opacity-90"><?php echo $performer['total_transactions']; ?> transactions</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="font-medium"><?php echo $performer['full_name']; ?></div>
                            <div class="text-xs opacity-90"><?php echo $performer['department']; ?></div>
                        </div>
                        <div class="mt-2 flex justify-between text-xs">
                            <span><?php echo $performer['active_days']; ?> active days</span>
                            <span>₦<?php echo number_format($performer['avg_per_day']); ?>/day</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Officer Comparison Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Wealth Creation Officers Performance Comparison</h3>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Collections</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Active Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Average</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($paginated_officers as $index => $officer): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <?php $global_index = $offset + $index; ?>
                                    <?php if ($global_index < 3): ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold
                                            <?php echo $global_index === 0 ? 'bg-yellow-500' : ($global_index === 1 ? 'bg-gray-500' : 'bg-orange-500'); ?>">
                                            <?php echo $global_index + 1; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center bg-blue-100 text-blue-800 text-sm font-bold">
                                            <?php echo $global_index + 1; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo $officer['full_name']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $officer['department'] === 'Wealth Creation' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo $officer['department']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($officer['total_collections']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format($officer['total_transactions']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $officer['active_days']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($officer['avg_per_day']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php
                                $rating = $analyzer->getOfficerRating($officer['user_id'], $selected_month, $selected_year, 
                                    $officer['department'] !== 'Wealth Creation');
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $rating['rating_class']; ?>">
                                    <?php echo $rating['rating']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="?sstaff=<?php echo $officer['user_id'] . ($officer['department'] !== 'Wealth Creation' ? '-so' : ''); ?>&smonth=<?php echo $selected_month; ?>&syear=<?php echo $selected_year; ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="officer_detailed_report.php?officer_id=<?php echo $officer['user_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                                       class="text-green-600 hover:text-green-800">
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
                               class="px-3 py-2 text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-500 bg-white border-gray-300'; ?> border rounded-md hover:bg-gray-50">
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

        <!-- Decision Making Tools -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Management Decision Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="officer_reward_system.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-trophy mr-2"></i>
                    Reward System
                </a>
                
                <a href="officer_training_needs.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-graduation-cap mr-2"></i>
                    Training Needs
                </a>
                
                <a href="officer_performance_trends.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>
                    Trend Analysis
                </a>
                
                <a href="officer_benchmarking.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-balance-scale mr-2"></i>
                    Benchmarking
                </a>
                
                <a href="officer_general_performance.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-chart-pie mr-2"></i>
                    General Performance
                </a>
            </div>
        </div>
    </div>

    <?php if ($officer_id && !empty($officer_trends)): ?>
    <script>
        // Performance Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php echo json_encode($officer_trends); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month_name),
                datasets: [{
                    label: 'Monthly Collections (₦)',
                    data: trendData.map(item => item.total),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
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

        // Income Line Distribution Chart
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        const performanceData = <?php echo json_encode($officer_performance); ?>;
        
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: performanceData.map(item => item.income_line),
                datasets: [{
                    data: performanceData.map(item => item.total_amount),
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
                                return context.label + ': ₦' + context.parsed.toLocaleString();
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