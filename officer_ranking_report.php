<?php
require_once 'OfficerTargetManager.php';
require_once 'BudgetManager.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

$target_manager = new OfficerTargetManager();
$budget_manager = new BudgetManager();

// Check access permissions
$can_view = $budget_manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Get real-time ranking data
$officer_ranking = $target_manager->getOfficerRankingRealTime($month, $year);
$department_comparison = $target_manager->getDepartmentPerformanceComparison($month, $year);

// Calculate statistics
$total_officers = count($officer_ranking);
$total_targets = array_sum(array_column($officer_ranking, 'total_target'));
$total_achieved = array_sum(array_column($officer_ranking, 'total_achieved'));
$overall_achievement = $total_targets > 0 ? ($total_achieved / $total_targets) * 100 : 0;

// Performance categories
$top_performers = array_filter($officer_ranking, function($officer) {
    return $officer['avg_achievement'] >= 120;
});

$good_performers = array_filter($officer_ranking, function($officer) {
    return $officer['avg_achievement'] >= 80 && $officer['avg_achievement'] < 120;
});

$underperformers = array_filter($officer_ranking, function($officer) {
    return $officer['avg_achievement'] < 80;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Ranking Report</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Officer Ranking Report</h1>
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
                    <i class="fas fa-search mr-2"></i>Load Report
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
                        <p class="text-sm font-medium text-gray-500">Total Officers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $total_officers; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-trophy text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Top Performers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($top_performers); ?></p>
                        <p class="text-xs text-gray-500">≥120% achievement</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Underperformers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($underperformers); ?></p>
                        <p class="text-xs text-gray-500">&lt;80% achievement</p>
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

        <!-- Performance Categories -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Top Performers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-crown text-yellow-500 mr-2"></i>
                    Top Performers
                </h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($top_performers, 0, 5) as $index => $performer): ?>
                    <div class="flex items-center justify-between p-3 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo $performer['officer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $performer['department']; ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-yellow-700"><?php echo number_format($performer['avg_achievement'], 1); ?>%</div>
                            <div class="text-xs text-gray-500">₦<?php echo number_format($performer['total_achieved']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Good Performers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-thumbs-up text-blue-500 mr-2"></i>
                    Good Performers
                </h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($good_performers, 0, 5) as $index => $performer): ?>
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo $performer['officer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $performer['department']; ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-blue-700"><?php echo number_format($performer['avg_achievement'], 1); ?>%</div>
                            <div class="text-xs text-gray-500">₦<?php echo number_format($performer['total_achieved']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Underperformers -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Needs Improvement
                </h3>
                <div class="space-y-3">
                    <?php foreach (array_slice($underperformers, 0, 5) as $index => $performer): ?>
                    <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900"><?php echo $performer['officer_name']; ?></div>
                                <div class="text-xs text-gray-500"><?php echo $performer['department']; ?></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold text-red-700"><?php echo number_format($performer['avg_achievement'], 1); ?>%</div>
                            <div class="text-xs text-gray-500">₦<?php echo number_format($performer['total_achieved']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Complete Ranking Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Complete Officer Ranking</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Achieved</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($officer_ranking)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                No officer targets found for <?php echo $month_name . ' ' . $year; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($officer_ranking as $index => $officer): ?>
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
                                    ₦<?php echo number_format($officer['total_target']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($officer['total_achieved']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="<?php echo $officer['avg_achievement'] >= 100 ? 'bg-green-500' : ($officer['avg_achievement'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                                 style="width: <?php echo min(100, $officer['avg_achievement']); ?>%"></div>
                                        </div>
                                        <span class="ml-2 text-xs text-gray-500">
                                            <?php echo number_format($officer['avg_achievement'], 1); ?>%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-lg font-bold <?php echo $officer['overall_score'] >= 80 ? 'text-green-600' : ($officer['overall_score'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                        <?php echo number_format($officer['overall_score'], 1); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-1">
                                        <span class="px-1 py-1 text-xs bg-green-100 text-green-800 rounded" title="Excellent lines">
                                            <?php echo $officer['excellent_lines']; ?>A
                                        </span>
                                        <span class="px-1 py-1 text-xs bg-blue-100 text-blue-800 rounded" title="Good lines">
                                            <?php echo $officer['good_lines']; ?>B
                                        </span>
                                        <span class="px-1 py-1 text-xs bg-red-100 text-red-800 rounded" title="Poor lines">
                                            <?php echo $officer['poor_lines']; ?>P
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="officer_performance_report.php?officer_id=<?php echo $officer['officer_id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="officer_target_setup.php" 
                                           class="text-green-600 hover:text-green-800" title="Set New Target">
                                            <i class="fas fa-plus"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Action Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2 text-green-600">
                        <i class="fas fa-trophy mr-2"></i>Recognition
                    </h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Recognize top <?php echo min(3, count($top_performers)); ?> performers publicly</li>
                        <li>• Consider performance bonuses</li>
                        <li>• Share best practices with team</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2 text-blue-600">
                        <i class="fas fa-graduation-cap mr-2"></i>Development
                    </h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Provide training for underperformers</li>
                        <li>• Pair with high performers for mentoring</li>
                        <li>• Review target setting methodology</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2 text-purple-600">
                        <i class="fas fa-chart-line mr-2"></i>Strategy
                    </h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Analyze performance patterns</li>
                        <li>• Adjust targets for next period</li>
                        <li>• Implement performance improvement plans</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>