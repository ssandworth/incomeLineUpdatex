<?php
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

$manager = new BudgetManager();

// Check access permissions
$can_view = $manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

$budget_id = $_GET['id'] ?? null;

if (!$budget_id) {
    header('Location: budget_management.php?error=no_budget_id');
    exit;
}

$budget = $manager->getBudgetLine($budget_id);

if (!$budget) {
    header('Location: budget_management.php?error=budget_not_found');
    exit;
}

// Get performance data for this budget line
$performance_data = $manager->getBudgetPerformance($budget['budget_year']);
$budget_performance = array_filter($performance_data, function($perf) use ($budget) {
    return $perf['acct_id'] === $budget['acct_id'];
});

// Calculate quarterly totals
$quarters = [
    'Q1' => $budget['january_budget'] + $budget['february_budget'] + $budget['march_budget'],
    'Q2' => $budget['april_budget'] + $budget['may_budget'] + $budget['june_budget'],
    'Q3' => $budget['july_budget'] + $budget['august_budget'] + $budget['september_budget'],
    'Q4' => $budget['october_budget'] + $budget['november_budget'] + $budget['december_budget']
];

// Calculate YTD performance
$current_month = date('n');
$ytd_budget = 0;
$ytd_actual = 0;

for ($month = 1; $month <= $current_month; $month++) {
    $month_name = strtolower(date('F', mktime(0, 0, 0, $month, 1)));
    $ytd_budget += $budget[$month_name . '_budget'];
    
    foreach ($budget_performance as $perf) {
        if ($perf['performance_month'] == $month) {
            $ytd_actual += $perf['actual_amount'];
        }
    }
}

$ytd_variance = $ytd_budget > 0 ? (($ytd_actual - $ytd_budget) / $ytd_budget) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Details</title>
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
                    <a href="budget_management.php?year=<?php echo $budget['budget_year']; ?>" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Budget Management</a>
                    <h1 class="text-xl font-bold text-gray-900">Budget Details</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $budget['acct_desc']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        FY <?php echo $budget['budget_year']; ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Budget Overview -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900"><?php echo $budget['acct_desc']; ?></h2>
                    <p class="text-gray-600">Budget Year: <?php echo $budget['budget_year']; ?></p>
                    <p class="text-sm text-gray-500">Account ID: <?php echo $budget['acct_id']; ?></p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">₦<?php echo number_format($budget['annual_budget']); ?></div>
                    <div class="text-sm text-gray-500">Annual Budget</div>
                    <span class="px-3 py-1 text-sm font-medium rounded-full 
                        <?php echo $budget['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo $budget['status']; ?>
                    </span>
                </div>
            </div>

            <!-- YTD Performance -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">₦<?php echo number_format($ytd_budget); ?></div>
                    <div class="text-sm text-gray-600">YTD Budget</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">₦<?php echo number_format($ytd_actual); ?></div>
                    <div class="text-sm text-gray-600">YTD Actual</div>
                </div>
                <div class="text-center p-4 <?php echo $ytd_variance >= 0 ? 'bg-green-50' : 'bg-red-50'; ?> rounded-lg">
                    <div class="text-2xl font-bold <?php echo $ytd_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <?php echo $ytd_variance >= 0 ? '+' : ''; ?><?php echo number_format($ytd_variance, 1); ?>%
                    </div>
                    <div class="text-sm text-gray-600">YTD Variance</div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Monthly Budget Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Budget Distribution</h3>
                <div class="relative h-48">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Quarterly Budget Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quarterly Budget Breakdown</h3>
                <div class="relative h-48">
                    <canvas id="quarterlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Budget Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Monthly Budget Breakdown</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Target</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        
                        foreach ($months as $month_num => $month_name): 
                            $month_budget = $budget[strtolower($month_name) . '_budget'];
                            $month_actual = 0;
                            $month_variance = 0;
                            $status = 'Pending';
                            
                            // Find actual performance for this month
                            foreach ($budget_performance as $perf) {
                                if ($perf['performance_month'] == $month_num) {
                                    $month_actual = $perf['actual_amount'];
                                    $month_variance = $perf['variance_percentage'];
                                    $status = $perf['performance_status'];
                                    break;
                                }
                            }
                            
                            $daily_target = $this->calculateDailyBudget($month_budget, $month_num, $budget['budget_year']);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $month_name; ?> <?php echo $budget['budget_year']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($month_budget); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($month_actual); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $month_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $month_variance >= 0 ? '+' : ''; ?><?php echo number_format($month_variance, 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $status === 'Above Budget' ? 'bg-green-100 text-green-800' : 
                                              ($status === 'On Budget' ? 'bg-blue-100 text-blue-800' : 
                                              ($status === 'Below Budget' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($daily_target); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTAL</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($budget['annual_budget']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($ytd_actual); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                <?php echo $ytd_variance >= 0 ? '+' : ''; ?><?php echo number_format($ytd_variance, 1); ?>%
                            </th>
                            <th colspan="2" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Analysis Tools -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Budget Analysis Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="budget_variance_report.php?year=<?php echo $budget['budget_year']; ?>&acct_id=<?php echo $budget['acct_id']; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Variance Analysis
                </a>
                
                <a href="budget_forecasting.php?year=<?php echo $budget['budget_year']; ?>&acct_id=<?php echo $budget['acct_id']; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-crystal-ball mr-2"></i>
                    Forecasting
                </a>
                
                <a href="ledger.php?acct_id=<?php echo $budget['acct_id']; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-book mr-2"></i>
                    View Ledger
                </a>
                
                <a href="budget_management.php?edit_id=<?php echo $budget['id']; ?>&year=<?php echo $budget['budget_year']; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-edit mr-2"></i>
                    Edit Budget
                </a>
            </div>
        </div>
    </div>

    <script>
        // Monthly Budget Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = [
            <?php echo $budget['january_budget']; ?>, <?php echo $budget['february_budget']; ?>, 
            <?php echo $budget['march_budget']; ?>, <?php echo $budget['april_budget']; ?>,
            <?php echo $budget['may_budget']; ?>, <?php echo $budget['june_budget']; ?>,
            <?php echo $budget['july_budget']; ?>, <?php echo $budget['august_budget']; ?>,
            <?php echo $budget['september_budget']; ?>, <?php echo $budget['october_budget']; ?>,
            <?php echo $budget['november_budget']; ?>, <?php echo $budget['december_budget']; ?>
        ];
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Budget (₦)',
                    data: monthlyData,
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
                                return 'Budget: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Quarterly Chart
        const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
        const quarterlyData = <?php echo json_encode(array_values($quarters)); ?>;
        
        new Chart(quarterlyCtx, {
            type: 'doughnut',
            data: {
                labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                datasets: [{
                    data: quarterlyData,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
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
</body>
</html>