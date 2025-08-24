<?php
require_once 'OfficerTargetManager.php';
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

$target_manager = new OfficerTargetManager();
$performance_analyzer = new OfficerPerformanceAnalyzer();

$officer_id = $_GET['officer_id'] ?? null;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

if (!$officer_id) {
    header('Location: officer_target_management.php');
    exit;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));

// Get officer information
$officer_info = $performance_analyzer->getOfficerInfo($officer_id, false);
if (!$officer_info) {
    header('Location: officer_target_management.php');
    exit;
}

// Get performance data
$performance_summary = $target_manager->getOfficerPerformanceSummary($officer_id, $month, $year);
$detailed_performance = $target_manager->getOfficerDetailedPerformance($officer_id, $month, $year);
$daily_performance = $performance_analyzer->getOfficerDailyPerformance($officer_id, $month, $year, false);
$trends = $performance_analyzer->getOfficerTrends($officer_id, $month, $year, false);
$insights = $performance_analyzer->getPerformanceInsights($officer_id, $month, $year, false);

// Calculate additional metrics
$working_days = count(array_filter($daily_performance));
$peak_day = array_keys($daily_performance, max($daily_performance))[0];
$peak_amount = max($daily_performance);
$consistency_score = $working_days > 0 ? (count(array_filter($daily_performance, function($amount) use ($performance_summary) {
    return $performance_summary && $performance_summary['total_target'] > 0 && 
           $amount >= ($performance_summary['total_target'] / 26); // Approximate daily target
})) / $working_days) * 100 : 0;

$sundays = $performance_analyzer->getSundayPositions($month, $year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Performance Report</title>
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
                    <a href="officer_target_management.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" 
                       class="text-blue-600 hover:text-blue-800 mr-4">← Back to Targets</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer Performance Report</h1>
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
                        <?php echo strtoupper(substr($officer_info['full_name'], 0, 2)); ?>
                    </div>
                    <div class="ml-6">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo $officer_info['full_name']; ?></h2>
                        <p class="text-gray-600"><?php echo $officer_info['department']; ?></p>
                        <?php if ($officer_info['phone']): ?>
                            <p class="text-sm text-gray-500"><?php echo $officer_info['phone']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-right">
                    <?php if ($performance_summary): ?>
                        <div class="text-3xl font-bold text-blue-600">₦<?php echo number_format($performance_summary['total_achieved']); ?></div>
                        <div class="text-sm text-gray-500">Total Achieved</div>
                        <div class="text-sm <?php echo $performance_summary['avg_achievement_percentage'] >= 100 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($performance_summary['avg_achievement_percentage'], 1); ?>% of target
                        </div>
                    <?php else: ?>
                        <div class="text-lg text-gray-500">No targets set</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Performance Summary Cards -->
        <?php if ($performance_summary): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-bullseye text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Target</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($performance_summary['total_target']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Achievement Rate</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($performance_summary['avg_achievement_percentage'], 1); ?>%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-star text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Performance Score</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($performance_summary['avg_performance_score'], 1); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <i class="fas fa-tasks text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Assigned Lines</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $performance_summary['assigned_lines']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $performance_summary['excellent_count']; ?> excellent</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Daily Performance Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Performance vs Target</h3>
                <div class="relative h-48">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>

            <!-- Income Line Performance Chart -->
            <?php if (!empty($detailed_performance)): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Income Line Achievement</h3>
                <div class="relative h-48">
                    <canvas id="achievementChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Detailed Performance by Income Line -->
        <?php if (!empty($detailed_performance)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Performance by Income Line</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Achieved</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Achievement %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Working Days</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Avg</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($detailed_performance as $performance): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $performance['acct_desc']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['monthly_target']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($performance['achieved_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $performance['achievement_percentage'] >= 100 ? 'bg-green-500' : ($performance['achievement_percentage'] >= 80 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $performance['achievement_percentage']); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($performance['achievement_percentage'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php 
                                    $grade_class = 'bg-gray-100 text-gray-800';
                                    switch($performance['performance_grade']) {
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
                                    <?php echo $performance['performance_grade']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo number_format($performance['total_transactions']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $performance['working_days']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['working_days'] > 0 ? $performance['achieved_amount'] / $performance['working_days'] : 0); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($performance_summary): ?>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTAL</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($performance_summary['total_target']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($performance_summary['total_achieved']); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php echo number_format($performance_summary['avg_achievement_percentage'], 1); ?>%
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php echo number_format($performance_summary['avg_performance_score'], 1); ?>
                            </th>
                            <th colspan="3" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Performance Insights -->
        <?php if (!empty($insights)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($insights as $insight): ?>
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

        <!-- Action Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Action Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Immediate Actions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($performance_summary && $performance_summary['avg_achievement_percentage'] < 80): ?>
                            <li>• Review and adjust daily collection strategies</li>
                            <li>• Provide additional training and support</li>
                            <li>• Analyze underperforming income lines</li>
                        <?php elseif ($performance_summary && $performance_summary['avg_achievement_percentage'] >= 120): ?>
                            <li>• Recognize exceptional performance</li>
                            <li>• Share best practices with other officers</li>
                            <li>• Consider increasing targets for next period</li>
                        <?php else: ?>
                            <li>• Maintain current performance levels</li>
                            <li>• Look for optimization opportunities</li>
                            <li>• Monitor consistency across income lines</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Strategic Actions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Review target setting methodology</li>
                        <li>• Analyze seasonal performance patterns</li>
                        <li>• Plan for capacity building initiatives</li>
                        <li>• Develop performance improvement plans</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Performance Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode(array_values($daily_performance)); ?>;
        const dailyLabels = <?php echo json_encode(array_keys($daily_performance)); ?>;
        const sundayPositions = <?php echo json_encode($sundays); ?>;
        
        // Calculate daily target line
        const dailyTarget = <?php echo $performance_summary ? $performance_summary['total_target'] / 26 : 0; ?>; // Approximate daily target
        const targetLine = Array(dailyLabels.length).fill(dailyTarget);
        
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
                }, {
                    label: 'Daily Target',
                    data: targetLine,
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false
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
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString() + 
                                       (isSunday ? ' (Sunday)' : '');
                            }
                        }
                    }
                }
            }
        });

        <?php if (!empty($detailed_performance)): ?>
        // Achievement Chart
        const achievementCtx = document.getElementById('achievementChart').getContext('2d');
        const achievementData = <?php echo json_encode($detailed_performance); ?>;
        
        new Chart(achievementCtx, {
            type: 'horizontalBar',
            data: {
                labels: achievementData.map(item => 
                    item.acct_desc.length > 20 ? item.acct_desc.substring(0, 20) + '...' : item.acct_desc
                ),
                datasets: [{
                    label: 'Achievement %',
                    data: achievementData.map(item => item.achievement_percentage),
                    backgroundColor: achievementData.map(item => 
                        item.achievement_percentage >= 100 ? 'rgba(16, 185, 129, 0.8)' : 
                        (item.achievement_percentage >= 80 ? 'rgba(245, 158, 11, 0.8)' : 'rgba(239, 68, 68, 0.8)')
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
                        max: Math.max(150, Math.max(...achievementData.map(item => item.achievement_percentage))),
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
                                return 'Achievement: ' + context.parsed.x.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>