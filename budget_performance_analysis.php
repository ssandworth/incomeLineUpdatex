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

$selected_year = $_GET['year'] ?? date('Y');
$selected_month = $_GET['month'] ?? date('n');
$selected_officer = $_GET['officer_id'] ?? null;
$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get performance data
$performance_data = $manager->getBudgetPerformanceRealTime($selected_year, $selected_month);
$budget_lines = $manager->getBudgetLines($selected_year);

// Get officers for filtering
$manager->db->query("
    SELECT DISTINCT user_id, full_name, department 
    FROM staffs 
    WHERE department IN ('Wealth Creation', 'Accounts', 'Leasing')
    ORDER BY full_name ASC
");
$officers = $manager->db->resultSet();

// Filter performance data by officer if selected
if ($selected_officer) {
    $filtered_performance = [];
    foreach ($performance_data as $perf) {
        // Get actual collections for this officer and account
        $manager->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as officer_actual
            FROM account_general_transaction_new 
            WHERE remitting_id = :officer_id
            AND credit_account = :acct_id
            AND MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
            AND (approval_status = 'Approved' OR approval_status = '')
        ");
        $manager->db->bind(':officer_id', $selected_officer);
        $manager->db->bind(':acct_id', $perf['acct_id']);
        $manager->db->bind(':month', $selected_month);
        $manager->db->bind(':year', $selected_year);
        $officer_result = $manager->db->single();
        
        if ($officer_result['officer_actual'] > 0) {
            $perf['actual_amount'] = $officer_result['officer_actual'];
            $perf['variance_amount'] = $perf['actual_amount'] - $perf['budgeted_amount'];
            $perf['variance_percentage'] = $perf['budgeted_amount'] > 0 ? 
                (($perf['actual_amount'] - $perf['budgeted_amount']) / $perf['budgeted_amount']) * 100 : 0;
            $filtered_performance[] = $perf;
        }
    }
    $performance_data = $filtered_performance;
}

// Calculate summary statistics
$total_budget = array_sum(array_column($budget_lines, 'annual_budget'));
$total_actual = 0;
$above_budget_count = 0;
$on_budget_count = 0;
$below_budget_count = 0;

foreach ($performance_data as $perf) {
    $total_actual += $perf['actual_amount'];
    switch ($perf['performance_status']) {
        case 'Above Budget':
            $above_budget_count++;
            break;
        case 'On Budget':
            $on_budget_count++;
            break;
        case 'Below Budget':
            $below_budget_count++;
            break;
    }
}

$overall_variance = $total_budget > 0 ? (($total_actual - $total_budget) / $total_budget) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Performance Analysis</title>
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
                    <a href="budget_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Budget</a>
                    <h1 class="text-xl font-bold text-gray-900">Budget Performance Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $selected_year; ?></span>
                    <?php if ($selected_officer): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            Officer Filter Active
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Filters</h3>
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
                        <i class="fas fa-search mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-chart-pie text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Budget</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_budget); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-coins text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Actual Collections</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_actual); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $overall_variance >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Overall Variance</p>
                        <p class="text-2xl font-bold <?php echo $overall_variance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $overall_variance >= 0 ? '+' : ''; ?><?php echo number_format($overall_variance, 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-tasks text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Performance Status</p>
                        <div class="text-sm text-gray-900">
                            <div class="text-green-600"><?php echo $above_budget_count; ?> Above</div>
                            <div class="text-blue-600"><?php echo $on_budget_count; ?> On Target</div>
                            <div class="text-red-600"><?php echo $below_budget_count; ?> Below</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Analysis Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Budget vs Actual Performance</h3>
            <div class="relative h-64">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Detailed Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Detailed Performance Analysis</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budgeted</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Variance %</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($performance_data as $perf): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $perf['acct_desc']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo date('M Y', mktime(0, 0, 0, $perf['performance_month'], 1, $perf['performance_year'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($perf['budgeted_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($perf['actual_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $perf['variance_amount'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $perf['variance_amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format($perf['variance_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
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
        </div>
    </div>

    <script>
        // Performance Chart
        const perfCtx = document.getElementById('performanceChart').getContext('2d');
        const perfData = <?php echo json_encode($performance_data); ?>;
        
        new Chart(perfCtx, {
            type: 'bar',
            data: {
                labels: perfData.map(item => item.acct_desc.length > 15 ? 
                    item.acct_desc.substring(0, 15) + '...' : item.acct_desc),
                datasets: [{
                    label: 'Budgeted (₦)',
                    data: perfData.map(item => item.budgeted_amount),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }, {
                    label: 'Actual (₦)',
                    data: perfData.map(item => item.actual_amount),
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
    </script>
</body>
</html>