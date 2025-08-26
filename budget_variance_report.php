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

class VarianceAnalyzer {
    private $db;
    private $manager;
    
    public function __construct() {
        $this->db = new Database();
        $this->manager = new BudgetManager();
    }
    
    /**
     * Get comprehensive variance analysis
     */
    public function getVarianceAnalysis($year, $acct_id = null) {
        $where_clause = $acct_id ? "AND bl.acct_id = :acct_id" : "";
        
        $this->db->query("
            SELECT 
                bl.acct_id,
                bl.acct_desc,
                bl.budget_year,
                bl.annual_budget,
                bp.performance_month,
                bp.budgeted_amount,
                bp.actual_amount,
                bp.variance_amount,
                bp.variance_percentage,
                bp.performance_status
            FROM budget_lines bl
            LEFT JOIN budget_performance bp ON bl.acct_id = bp.acct_id 
                AND bl.budget_year = bp.performance_year
            WHERE bl.budget_year = :year
            {$where_clause}
            ORDER BY bl.acct_desc ASC, bp.performance_month ASC
        ");
        
        $this->db->bind(':year', $year);
        if ($acct_id) {
            $this->db->bind(':acct_id', $acct_id);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Get variance trends over time
     */
    public function getVarianceTrends($year) {
        $this->db->query("
            SELECT 
                bp.performance_month,
                SUM(bp.budgeted_amount) as total_budget,
                SUM(bp.actual_amount) as total_actual,
                SUM(bp.variance_amount) as total_variance,
                AVG(bp.variance_percentage) as avg_variance_percentage,
                COUNT(CASE WHEN bp.performance_status = 'Above Budget' THEN 1 END) as above_count,
                COUNT(CASE WHEN bp.performance_status = 'On Budget' THEN 1 END) as on_count,
                COUNT(CASE WHEN bp.performance_status = 'Below Budget' THEN 1 END) as below_count
            FROM budget_performance bp
            WHERE bp.performance_year = :year
            GROUP BY bp.performance_month
            ORDER BY bp.performance_month
        ");
        
        $this->db->bind(':year', $year);
        return $this->db->resultSet();
    }
    
    /**
     * Get critical variances requiring attention
     */
    public function getCriticalVariances($year, $threshold = 20) {
        $this->db->query("
            SELECT 
                bl.acct_desc,
                bp.performance_month,
                bp.budgeted_amount,
                bp.actual_amount,
                bp.variance_amount,
                bp.variance_percentage,
                bp.performance_status
            FROM budget_lines bl
            JOIN budget_performance bp ON bl.acct_id = bp.acct_id 
                AND bl.budget_year = bp.performance_year
            WHERE bl.budget_year = :year
            AND ABS(bp.variance_percentage) > :threshold
            ORDER BY ABS(bp.variance_percentage) DESC
        ");
        
        $this->db->bind(':year', $year);
        $this->db->bind(':threshold', $threshold);
        
        return $this->db->resultSet();
    }
}

$analyzer = new VarianceAnalyzer();
$manager = new BudgetManager();

// Check access permissions
$can_view = $manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

$year = $_GET['year'] ?? date('Y');
$acct_id = $_GET['acct_id'] ?? null;

$variance_data = $analyzer->getVarianceAnalysis($year, $acct_id);
$variance_trends = $analyzer->getVarianceTrends($year);
$critical_variances = $analyzer->getCriticalVariances($year);

// Calculate summary statistics
$total_budget = 0;
$total_actual = 0;
$favorable_count = 0;
$unfavorable_count = 0;

foreach ($variance_data as $data) {
    if ($data['budgeted_amount'] > 0) {
        $total_budget += $data['budgeted_amount'];
        $total_actual += $data['actual_amount'];
        
        if ($data['variance_percentage'] >= 0) {
            $favorable_count++;
        } else {
            $unfavorable_count++;
        }
    }
}

$overall_variance = $total_budget > 0 ? (($total_actual - $total_budget) / $total_budget) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Variance Report</title>
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
                    <a href="budget_management.php?year=<?php echo $year; ?>" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Budget Management</a>
                    <h1 class="text-xl font-bold text-gray-900">Budget Variance Report</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">FY <?php echo $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                        <p class="text-sm font-medium text-gray-500">Actual Performance</p>
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
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Critical Variances</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($critical_variances); ?></p>
                        <p class="text-xs text-gray-500">>20% variance</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Variance Trends Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Variance Trends</h3>
                <div class="relative h-48">
                    <canvas id="varianceTrendChart"></canvas>
                </div>
            </div>

            <!-- Performance Status Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Status Distribution</h3>
                <div class="relative h-48">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Critical Variances -->
        <?php if (!empty($critical_variances)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Critical Variances (>20%)</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($critical_variances as $variance): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $variance['acct_desc']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo date('M Y', mktime(0, 0, 0, $variance['performance_month'], 1, $year)); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($variance['budgeted_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($variance['actual_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold
                                <?php echo $variance['variance_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $variance['variance_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($variance['variance_percentage'], 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $variance['performance_status'] === 'Above Budget' ? 'bg-green-100 text-green-800' : 
                                              ($variance['performance_status'] === 'On Budget' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo $variance['performance_status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Variances Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Complete Variance Analysis</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (₦)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (%)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($variance_data)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No variance data available for <?php echo $year; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($variance_data as $data): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="budget_details.php?id=<?php echo $data['acct_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <?php echo $data['acct_desc']; ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                    <?php echo $data['performance_month'] ? date('M Y', mktime(0, 0, 0, $data['performance_month'], 1, $year)) : '-'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($data['budgeted_amount'] ?? 0); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($data['actual_amount'] ?? 0); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                    <?php echo ($data['variance_amount'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo ($data['variance_amount'] ?? 0) >= 0 ? '+' : ''; ?>₦<?php echo number_format($data['variance_amount'] ?? 0); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold
                                    <?php echo ($data['variance_percentage'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo ($data['variance_percentage'] ?? 0) >= 0 ? '+' : ''; ?><?php echo number_format($data['variance_percentage'] ?? 0, 1); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($data['performance_status']): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php echo $data['performance_status'] === 'Above Budget' ? 'bg-green-100 text-green-800' : 
                                                      ($data['performance_status'] === 'On Budget' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); ?>">
                                            <?php echo $data['performance_status']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">No data</span>
                                    <?php endif; ?>
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
        // Variance Trends Chart
        const trendCtx = document.getElementById('varianceTrendChart').getContext('2d');
        const trendData = <?php echo json_encode($variance_trends); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => new Date(<?php echo $year; ?>, item.performance_month - 1).toLocaleDateString('en-US', {month: 'short'})),
                datasets: [{
                    label: 'Budget (₦)',
                    data: trendData.map(item => item.total_budget),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: false
                }, {
                    label: 'Actual (₦)',
                    data: trendData.map(item => item.total_actual),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
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
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        
        // Calculate status distribution from trends data
        let totalAbove = 0, totalOn = 0, totalBelow = 0;
        trendData.forEach(item => {
            totalAbove += item.above_count;
            totalOn += item.on_count;
            totalBelow += item.below_count;
        });
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Above Budget', 'On Budget', 'Below Budget'],
                datasets: [{
                    data: [totalAbove, totalOn, totalBelow],
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
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
                                return context.label + ': ' + context.parsed + ' instances';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>