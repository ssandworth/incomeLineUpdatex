<?php
require_once 'BudgetManager.php';
require_once 'OfficerTargetManager.php';
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
$target_manager = new OfficerTargetManager();

// Check access permissions
$can_view = $budget_manager->checkAccess($staff['user_id'], 'can_view_budget');
$can_create = $budget_manager->checkAccess($staff['user_id'], 'can_create_budget');
$can_manage_targets = $budget_manager->checkAccess($staff['user_id'], 'can_manage_targets');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get current year data
$current_year = date('Y');
$current_month = date('n');

// Get dashboard statistics
$budget_lines = $budget_manager->getBudgetLines($current_year);
$performance_data = $budget_manager->getBudgetPerformance($current_year, $current_month);
$all_targets = $target_manager->getAllOfficerTargets($current_month, $current_year);

// Calculate summary statistics
$total_budget = array_sum(array_column($budget_lines, 'annual_budget'));
$total_actual = array_sum(array_column($performance_data, 'actual_amount'));
$budget_variance = $total_budget > 0 ? (($total_actual - $total_budget) / $total_budget) * 100 : 0;

$active_budget_lines = count(array_filter($budget_lines, function($line) { return $line['status'] === 'Active'; }));
$total_officers_with_targets = count($all_targets);
$total_monthly_targets = array_sum(array_column($all_targets, 'total_target'));

// Get recent activities (mock data for demonstration)
$recent_activities = [
    ['action' => 'Budget Created', 'user' => 'John Doe', 'item' => 'Car Park Revenue Budget', 'time' => '2 hours ago'],
    ['action' => 'Target Assigned', 'user' => 'Jane Smith', 'item' => 'Monthly Target for Officer Mike', 'time' => '4 hours ago'],
    ['action' => 'Performance Updated', 'user' => 'System', 'item' => 'Monthly Performance Data', 'time' => '6 hours ago'],
    ['action' => 'Budget Modified', 'user' => 'John Doe', 'item' => 'Service Charge Budget Q2', 'time' => '1 day ago'],
];

// Get quick stats for cards
$above_budget_count = count(array_filter($performance_data, function($perf) { 
    return $perf['variance_percentage'] > 5; 
}));
$below_budget_count = count(array_filter($performance_data, function($perf) { 
    return $perf['variance_percentage'] < -5; 
}));
$on_budget_count = count($performance_data) - $above_budget_count - $below_budget_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Management Dashboard</title>
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
                    <div class="flex items-center mr-8">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-chart-pie text-white"></i>
                        </div>
                        <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Budget Dashboard</h1>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-700">Welcome, <?php echo $staff['full_name']; ?></span>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                            <?php echo $staff['department']; ?>
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs text-gray-500">FY <?php echo $selected_year; ?></span>
                        <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                        <?php if ($selected_officer): ?>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                                Officer Filter
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Dashboard Filters</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($y = date('Y') - 3; $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Officer (Optional)</label>
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
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Hero Section -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-700 rounded-xl shadow-lg p-8 mb-8 text-white">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-6 lg:mb-0">
                    <h1 class="text-3xl font-bold mb-2">Budget Management Center</h1>
                    <p class="text-blue-100 text-lg">
                        Comprehensive budget planning, target setting, and performance tracking for <?php echo $selected_year; ?>
                    </p>
                    <div class="mt-4 flex items-center space-x-6">
                        <div class="flex items-center">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            <span class="text-sm">Selected Period: <?php echo $month_name . ' ' . $selected_year; ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-users mr-2"></i>
                            <span class="text-sm"><?php echo $total_officers_with_targets; ?> Officers with Targets</span>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-4xl font-bold mb-2">₦<?php echo number_format(array_sum(array_column($budget_lines, 'annual_budget'))); ?></div>
                    <div class="text-blue-100">Annual Budget</div>
                    <div class="mt-2 flex items-center justify-end">
                        <span class="text-sm text-blue-200">
                            Real-time Performance Data
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Performance Indicators -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Budget Lines</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $active_budget_lines; ?></p>
                        <p class="text-xs text-gray-500">of <?php echo count($budget_lines); ?> total lines</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Monthly Targets</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_monthly_targets); ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('F'); ?> targets set</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Officers with Targets</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_officers_with_targets; ?></p>
                        <p class="text-xs text-gray-500">active assignments</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $budget_variance >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-bar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Budget Performance</p>
                        <p class="text-2xl font-bold <?php echo $budget_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $budget_variance >= 0 ? '+' : ''; ?><?php echo number_format($budget_variance, 1); ?>%
                        </p>
                        <p class="text-xs text-gray-500">vs annual budget</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Performance Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Status</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Above Budget</span>
                        </div>
                        <span class="text-sm font-bold text-green-600"><?php echo $above_budget_count; ?> lines</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">On Budget</span>
                        </div>
                        <span class="text-sm font-bold text-blue-600"><?php echo $on_budget_count; ?> lines</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Below Budget</span>
                        </div>
                        <span class="text-sm font-bold text-red-600"><?php echo $below_budget_count; ?> lines</span>
                    </div>
                </div>
                
                <!-- Performance Chart -->
                <div class="mt-6">
                    <canvas id="performanceStatusChart" height="150"></canvas>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <?php if ($can_create): ?>
                    <a href="budget_management.php" 
                       class="flex items-center p-3 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-blue-600 rounded-lg mr-3 group-hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Create Budget Line</div>
                            <div class="text-xs text-gray-500">Set up new income line budget</div>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if ($can_manage_targets): ?>
                    <a href="officer_target_management.php" 
                       class="flex items-center p-3 bg-green-50 hover:bg-green-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-green-600 rounded-lg mr-3 group-hover:bg-green-700 transition-colors">
                            <i class="fas fa-bullseye text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Manage Officer Targets</div>
                            <div class="text-xs text-gray-500">Set monthly collection targets</div>
                        </div>
                    </a>
                    <?php endif; ?>

                    <a href="budget_performance_analysis.php?year=<?php echo $current_year; ?>" 
                       class="flex items-center p-3 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-purple-600 rounded-lg mr-3 group-hover:bg-purple-700 transition-colors">
                            <i class="fas fa-chart-bar text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Performance Analysis</div>
                            <div class="text-xs text-gray-500">View detailed performance reports</div>
                        </div>
                    </a>

                    <a href="target_vs_achievement_report.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                       class="flex items-center p-3 bg-orange-50 hover:bg-orange-100 rounded-lg transition-colors group">
                        <div class="p-2 bg-orange-600 rounded-lg mr-3 group-hover:bg-orange-700 transition-colors">
                            <i class="fas fa-trophy text-white"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900">Target vs Achievement</div>
                            <div class="text-xs text-gray-500">Compare targets with actual performance</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activities</h3>
                <div class="space-y-3">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="flex items-start">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-900">
                                <span class="font-medium"><?php echo $activity['action']; ?></span>
                                <span class="text-gray-600">by <?php echo $activity['user']; ?></span>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo $activity['item']; ?></div>
                            <div class="text-xs text-gray-400"><?php echo $activity['time']; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 pt-4 border-t">
                    <a href="budget_activity_log.php" class="text-sm text-blue-600 hover:text-blue-800">
                        View all activities →
                    </a>
                </div>
            </div>
        </div>

        <!-- Budget Management Features -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Budget Management Features</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Budget Planning -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-blue-100 rounded-lg mr-4">
                                <i class="fas fa-calculator text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Budget Planning</h3>
                                <p class="text-sm text-gray-600">Annual budget setup and management</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="budget_management.php" 
                               class="block w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-center transition-colors">
                                <i class="fas fa-cog mr-2"></i>Manage Budgets
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo $active_budget_lines; ?></div>
                                    <div class="text-gray-500">Active Lines</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900">₦<?php echo number_format($total_budget / 1000000, 1); ?>M</div>
                                    <div class="text-gray-500">Total Budget</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Officer Targets -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-green-100 rounded-lg mr-4">
                                <i class="fas fa-bullseye text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Officer Targets</h3>
                                <p class="text-sm text-gray-600">Monthly collection targets and tracking</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="officer_target_management.php" 
                               class="block w-full px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-center transition-colors">
                                <i class="fas fa-users-cog mr-2"></i>Manage Targets
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo $total_officers_with_targets; ?></div>
                                    <div class="text-gray-500">Officers</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900">₦<?php echo number_format($total_monthly_targets / 1000, 0); ?>K</div>
                                    <div class="text-gray-500">Monthly Target</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Analysis -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-purple-100 rounded-lg mr-4">
                                <i class="fas fa-chart-area text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Performance Analysis</h3>
                                <p class="text-sm text-gray-600">Budget vs actual performance tracking</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="budget_performance_analysis.php?year=<?php echo $current_year; ?>" 
                               class="block w-full px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 text-center transition-colors">
                                <i class="fas fa-analytics mr-2"></i>View Analysis
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-green-600"><?php echo $above_budget_count; ?></div>
                                    <div class="text-gray-500">Above Budget</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-red-600"><?php echo $below_budget_count; ?></div>
                                    <div class="text-gray-500">Below Budget</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Variance Reports -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-orange-100 rounded-lg mr-4">
                                <i class="fas fa-exclamation-triangle text-orange-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Variance Reports</h3>
                                <p class="text-sm text-gray-600">Budget variance analysis and alerts</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="budget_variance_report.php?year=<?php echo $current_year; ?>" 
                               class="block w-full px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 text-center transition-colors">
                                <i class="fas fa-file-alt mr-2"></i>View Reports
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo number_format(abs($budget_variance), 1); ?>%</div>
                                    <div class="text-gray-500">Avg Variance</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo count($performance_data); ?></div>
                                    <div class="text-gray-500">Tracked Lines</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forecasting -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-indigo-100 rounded-lg mr-4">
                                <i class="fas fa-crystal-ball text-indigo-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Budget Forecasting</h3>
                                <p class="text-sm text-gray-600">Predictive budget analysis and planning</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="budget_forecasting.php?year=<?php echo $current_year; ?>" 
                               class="block w-full px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-center transition-colors">
                                <i class="fas fa-chart-line mr-2"></i>View Forecasts
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900">Q<?php echo ceil($current_month / 3); ?></div>
                                    <div class="text-gray-500">Current Quarter</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo 12 - $current_month; ?></div>
                                    <div class="text-gray-500">Months Left</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Officer Performance -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="p-3 bg-teal-100 rounded-lg mr-4">
                                <i class="fas fa-user-chart text-teal-600 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Officer Performance</h3>
                                <p class="text-sm text-gray-600">Individual officer performance tracking</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <a href="mpr_income_lines_officers.php?smonth=<?php echo $current_month; ?>&syear=<?php echo $current_year; ?>" 
                               class="block w-full px-4 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700 text-center transition-colors">
                                <i class="fas fa-users mr-2"></i>Officer Reports
                            </a>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo date('j'); ?></div>
                                    <div class="text-gray-500">Day of Month</div>
                                </div>
                                <div class="text-center p-2 bg-gray-50 rounded">
                                    <div class="font-bold text-gray-900"><?php echo date('t') - date('j'); ?></div>
                                    <div class="text-gray-500">Days Left</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Features -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Advanced Budget Tools</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <a href="budget_comparison.php" 
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow group">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-blue-100 rounded-lg mr-4 group-hover:bg-blue-200 transition-colors">
                            <i class="fas fa-balance-scale text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Budget Comparison</h3>
                            <p class="text-sm text-gray-600">Compare budgets across years</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Compare current year budget with previous years and identify trends
                    </div>
                </a>

                <a href="budget_allocation.php" 
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow group">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-green-100 rounded-lg mr-4 group-hover:bg-green-200 transition-colors">
                            <i class="fas fa-pie-chart text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Budget Allocation</h3>
                            <p class="text-sm text-gray-600">Resource allocation analysis</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Analyze budget distribution across income lines and departments
                    </div>
                </a>

                <a href="budget_alerts.php" 
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow group">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-red-100 rounded-lg mr-4 group-hover:bg-red-200 transition-colors">
                            <i class="fas fa-bell text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Budget Alerts</h3>
                            <p class="text-sm text-gray-600">Automated variance alerts</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Set up automated alerts for budget variances and performance issues
                    </div>
                </a>

                <a href="budget_reports.php" 
                   class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow group">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-purple-100 rounded-lg mr-4 group-hover:bg-purple-200 transition-colors">
                            <i class="fas fa-file-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Budget Reports</h3>
                            <p class="text-sm text-gray-600">Comprehensive reporting suite</p>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Generate detailed budget reports for management and stakeholders
                    </div>
                </a>
            </div>
        </div>

        <!-- Monthly Performance Analysis -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Monthly Performance Analysis</h2>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Cumulative Performance Tracker</h3>
                    <a href="monthly_budget_performance.php?year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>" 
                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        View Detailed Analysis →
                    </a>
                </div>
                <p class="text-gray-600 mb-4">
                    Track cumulative budget vs actual performance and officer achievements up to any selected month.
                    Perfect for mid-year reviews and performance assessments.
                </p>
                <div class="flex flex-wrap gap-2">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <a href="monthly_budget_performance.php?year=<?php echo $current_year; ?>&month=<?php echo $m; ?>" 
                           class="px-3 py-1 text-xs font-medium rounded-full <?php echo $m == $current_month ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-colors">
                            <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Current Month Performance Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">
                    <?php echo date('F Y'); ?> Performance Summary
                </h3>
                <a href="budget_performance_analysis.php?year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>" 
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View Detailed Analysis →
                </a>
            </div>
            
            <?php if (!empty($performance_data)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budgeted</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($performance_data, 0, 8) as $perf): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $perf['acct_desc']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($perf['budgeted_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($perf['actual_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $perf['variance_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $perf['variance_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($perf['variance_percentage'], 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $perf['performance_status'] === 'Above Budget' ? 'bg-green-100 text-green-800' : 
                                              ($perf['performance_status'] === 'On Budget' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo $perf['performance_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <i class="fas fa-chart-line text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">No performance data available for this month.</p>
                <p class="text-sm text-gray-400">Performance data will appear once budget lines are created and actual collections are recorded.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Performance Status Chart
        const statusCtx = document.getElementById('performanceStatusChart').getContext('2d');
        
        new Chart(statusCtx, {
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