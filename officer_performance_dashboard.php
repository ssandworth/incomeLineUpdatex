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

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Get parameters
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Get real-time performance data
$officer_performance = $budget_manager->getOfficerPerformanceRealTime($month, $year);
$budget_performance = $budget_manager->getBudgetPerformanceRealTime($year, $month);

// Calculate summary statistics
$total_officers = count(array_unique(array_column($officer_performance, 'officer_id')));
$total_targets = array_sum(array_column($officer_performance, 'monthly_target'));
$total_achieved = array_sum(array_column($officer_performance, 'achieved_amount'));
$overall_achievement = $total_targets > 0 ? ($total_achieved / $total_targets) * 100 : 0;

// Performance distribution
$excellent_performers = count(array_filter($officer_performance, function($perf) {
    return $perf['performance_grade'] === 'A+' || $perf['performance_grade'] === 'A';
}));

$good_performers = count(array_filter($officer_performance, function($perf) {
    return $perf['performance_grade'] === 'B+' || $perf['performance_grade'] === 'B';
}));

$poor_performers = count(array_filter($officer_performance, function($perf) {
    return in_array($perf['performance_grade'], ['C+', 'C', 'D', 'F']);
}));

// Budget performance summary
$above_budget_count = count(array_filter($budget_performance, function($perf) {
    return $perf['performance_status'] === 'Above Budget';
}));

$on_budget_count = count(array_filter($budget_performance, function($perf) {
    return $perf['performance_status'] === 'On Budget';
}));

$below_budget_count = count(array_filter($budget_performance, function($perf) {
    return $perf['performance_status'] === 'Below Budget';
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Performance Dashboard</title>
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
                    <a href="officer_target_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Target Management</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer Performance Dashboard</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                    <div class="w-2 h-2 bg-green-500 rounded-full" title="Real-time data"></div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Period Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-sync mr-2"></i>Load Dashboard
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Officers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_officers; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Targets</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_targets); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Achieved</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_achieved); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $overall_achievement >= 100 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-percentage text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Overall Achievement</p>
                        <p class="text-2xl font-bold <?php echo $overall_achievement >= 100 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($overall_achievement, 1); ?>%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Officer Performance Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Officer Performance Distribution</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Excellent (A+, A)</span>
                        </div>
                        <span class="text-sm font-bold text-green-600"><?php echo $excellent_performers; ?> officers</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Good (B+, B)</span>
                        </div>
                        <span class="text-sm font-bold text-blue-600"><?php echo $good_performers; ?> officers</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Needs Improvement</span>
                        </div>
                        <span class="text-sm font-bold text-red-600"><?php echo $poor_performers; ?> officers</span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <canvas id="performanceDistributionChart" height="150"></canvas>
                </div>
            </div>

            <!-- Budget Performance Status -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Budget Performance Status</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Above Budget</span>
                        </div>
                        <span class="text-sm font-bold text-green-600"><?php echo $above_budget_count; ?> lines</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-blue-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">On Budget</span>
                        </div>
                        <span class="text-sm font-bold text-blue-600"><?php echo $on_budget_count; ?> lines</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-4 h-4 bg-red-500 rounded-full mr-3"></div>
                            <span class="text-sm text-gray-700">Below Budget</span>
                        </div>
                        <span class="text-sm font-bold text-red-600"><?php echo $below_budget_count; ?> lines</span>
                    </div>
                </div>
                
                <div class="mt-6">
                    <canvas id="budgetStatusChart" height="150"></canvas>
                </div>
            </div>
        </div>

        <!-- Officer Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Officer Performance Details</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Achieved</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Working Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Average</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($officer_performance)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                No officer targets set for <?php echo $month_name . ' ' . $year; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($officer_performance as $perf): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                            <?php echo strtoupper(substr($perf['officer_name'], 0, 2)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $perf['officer_name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $perf['department']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $perf['acct_desc']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($perf['monthly_target']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($perf['achieved_amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="<?php echo $perf['achievement_percentage'] >= 100 ? 'bg-green-500' : ($perf['achievement_percentage'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                                 style="width: <?php echo min(100, $perf['achievement_percentage']); ?>%"></div>
                                        </div>
                                        <span class="ml-2 text-xs text-gray-500">
                                            <?php echo number_format($perf['achievement_percentage'], 1); ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php 
                                        $grade_class = 'bg-gray-100 text-gray-800';
                                        switch($perf['performance_grade']) {
                                            case 'A+':
                                            case 'A':
                                                $grade_class = 'bg-green-100 text-green-800';
                                                break;
                                            case 'B+':
                                            case 'B':
                                                $grade_class = 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'C+':
                                            case 'C':
                                                $grade_class = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'D':
                                                $grade_class = 'bg-orange-100 text-orange-800';
                                                break;
                                            case 'F':
                                                $grade_class = 'bg-red-100 text-red-800';
                                                break;
                                        }
                                        echo $grade_class;
                                        ?>">
                                        <?php echo $perf['performance_grade']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                    <?php echo $perf['working_days']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($perf['working_days'] > 0 ? $perf['achieved_amount'] / $perf['working_days'] : 0); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Performance Distribution Chart
        const perfDistCtx = document.getElementById('performanceDistributionChart').getContext('2d');
        
        new Chart(perfDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent (A+, A)', 'Good (B+, B)', 'Needs Improvement'],
                datasets: [{
                    data: [<?php echo $excellent_performers; ?>, <?php echo $good_performers; ?>, <?php echo $poor_performers; ?>],
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
                                return context.label + ': ' + context.parsed + ' officers';
                            }
                        }
                    }
                }
            }
        });

        // Budget Status Chart
        const budgetStatusCtx = document.getElementById('budgetStatusChart').getContext('2d');
        
        new Chart(budgetStatusCtx, {
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
    </script>
</body>
</html>