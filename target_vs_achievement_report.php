<?php
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

$target_manager = new OfficerTargetManager();

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Get comprehensive data
$officer_ranking = $target_manager->getOfficerRankingRealTime($month, $year);
$department_comparison = $target_manager->getDepartmentPerformanceComparison($month, $year);

// Calculate overall statistics
$total_officers = count($officer_ranking);
$total_target = array_sum(array_column($officer_ranking, 'total_target'));
$total_achieved = array_sum(array_column($officer_ranking, 'total_achieved'));
$overall_achievement = $total_target > 0 ? ($total_achieved / $total_target) * 100 : 0;

// Performance distribution
$grade_distribution = [];
foreach ($officer_ranking as $officer) {
    $avg_achievement = $officer['avg_achievement'];
    if ($avg_achievement >= 150) $grade = 'A+';
    elseif ($avg_achievement >= 120) $grade = 'A';
    elseif ($avg_achievement >= 100) $grade = 'B+';
    elseif ($avg_achievement >= 80) $grade = 'B';
    elseif ($avg_achievement >= 60) $grade = 'C+';
    elseif ($avg_achievement >= 40) $grade = 'C';
    elseif ($avg_achievement >= 20) $grade = 'D';
    else $grade = 'F';
    
    $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Target vs Achievement Report</title>
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
                    <a href="officer_target_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Targets</a>
                    <h1 class="text-xl font-bold text-gray-900">Target vs Achievement Report</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Overall Performance Summary -->
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
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Target</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_target); ?></p>
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

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Department Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Department Performance Comparison</h3>
                <div class="relative h-48">
                    <canvas id="departmentChart"></canvas>
                </div>
            </div>

            <!-- Grade Distribution Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Grade Distribution</h3>
                <div class="relative h-48">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Officer Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Officer Performance Ranking</h3>
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
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Lines Performance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
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
                                        <div class="text-xs text-gray-500"><?php echo $officer['total_assigned_lines']; ?> assigned lines</div>
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
                                    <span class="px-1 py-1 text-xs bg-green-100 text-green-800 rounded"><?php echo $officer['excellent_lines']; ?>A</span>
                                    <span class="px-1 py-1 text-xs bg-blue-100 text-blue-800 rounded"><?php echo $officer['good_lines']; ?>B</span>
                                    <span class="px-1 py-1 text-xs bg-red-100 text-red-800 rounded"><?php echo $officer['poor_lines']; ?>P</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="officer_performance_report.php?officer_id=<?php echo $officer['officer_id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="officer_target_management.php?officer_id=<?php echo $officer['officer_id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                                       class="text-green-600 hover:text-green-800" title="Manage Targets">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="3" class="px-6 py-3 text-left text-sm font-bold text-gray-900">OVERALL TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_target); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_achieved); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php echo number_format($overall_achievement, 1); ?>%
                            </th>
                            <th colspan="3" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Department Performance Comparison -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Department Performance Comparison</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Officers</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total Targets</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Department Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Department Achieved</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($department_comparison as $dept): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $dept['department']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $dept['officer_count']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $dept['total_targets']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($dept['total_department_target']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($dept['total_department_achieved']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $dept['avg_achievement_percentage'] >= 100 ? 'bg-green-500' : ($dept['avg_achievement_percentage'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $dept['avg_achievement_percentage']); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($dept['avg_achievement_percentage'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="text-lg font-bold <?php echo $dept['avg_performance_score'] >= 80 ? 'text-green-600' : ($dept['avg_performance_score'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo number_format($dept['avg_performance_score'], 1); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Performance Insights -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights & Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Top Performers</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $top_performers = array_slice($officer_ranking, 0, 3);
                        foreach ($top_performers as $performer): 
                        ?>
                            <li>• <?php echo $performer['officer_name']; ?> - <?php echo number_format($performer['avg_achievement'], 1); ?>%</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Needs Improvement</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $low_performers = array_filter($officer_ranking, function($officer) {
                            return $officer['avg_achievement'] < 80;
                        });
                        $low_performers = array_slice($low_performers, -3);
                        foreach ($low_performers as $performer): 
                        ?>
                            <li>• <?php echo $performer['officer_name']; ?> - <?php echo number_format($performer['avg_achievement'], 1); ?>%</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Action Items</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Review targets for underperforming officers</li>
                        <li>• Provide additional training and support</li>
                        <li>• Recognize and reward top performers</li>
                        <li>• Analyze departmental performance gaps</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Department Performance Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        const deptData = <?php echo json_encode($department_comparison); ?>;
        
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: deptData.map(item => item.department),
                datasets: [{
                    label: 'Target (₦)',
                    data: deptData.map(item => item.total_department_target),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }, {
                    label: 'Achieved (₦)',
                    data: deptData.map(item => item.total_department_achieved),
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    borderColor: 'rgba(16, 185, 129, 1)',
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
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        const gradeData = <?php echo json_encode($grade_distribution); ?>;
        
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(gradeData),
                datasets: [{
                    data: Object.values(gradeData),
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',  // A+
                        'rgba(34, 197, 94, 0.8)',   // A
                        'rgba(59, 130, 246, 0.8)',  // B+
                        'rgba(99, 102, 241, 0.8)',  // B
                        'rgba(245, 158, 11, 0.8)',  // C+
                        'rgba(251, 191, 36, 0.8)',  // C
                        'rgba(249, 115, 22, 0.8)',  // D
                        'rgba(239, 68, 68, 0.8)'    // F
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
                                return context.label + ': ' + context.parsed + ' officers';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>