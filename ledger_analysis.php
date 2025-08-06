<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class LedgerDetailedAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get account information
     */
    public function getAccountInfo($account_id) {
        $this->db->query("
            SELECT a.*, gl.gl_code_name, gl.gl_category 
            FROM accounts a
            LEFT JOIN account_gl_code gl ON a.gl_code = gl.gl_code
            WHERE a.acct_id = :account_id
        ");
        $this->db->bind(':account_id', $account_id);
        return $this->db->single();
    }
    
    /**
     * Get transaction patterns analysis
     */
    public function getTransactionPatterns($table_name) {
        // Daily pattern
        $this->db->query("
            SELECT 
                DAYNAME(date) as day_name,
                DAYOFWEEK(date) as day_number,
                COUNT(*) as transaction_count,
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as avg_amount
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY DAYOFWEEK(date), DAYNAME(date)
            ORDER BY day_number
        ");
        $daily_patterns = $this->db->resultSet();
        
        // Monthly pattern
        $this->db->query("
            SELECT 
                MONTHNAME(date) as month_name,
                MONTH(date) as month_number,
                YEAR(date) as year,
                COUNT(*) as transaction_count,
                SUM(COALESCE(debit_amount, 0)) as total_debits,
                SUM(COALESCE(credit_amount, 0)) as total_credits
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY year DESC, month_number DESC
            LIMIT 12
        ");
        $monthly_patterns = $this->db->resultSet();
        
        return [
            'daily' => $daily_patterns,
            'monthly' => array_reverse($monthly_patterns)
        ];
    }
    
    /**
     * Get transaction size distribution
     */
    public function getTransactionSizeDistribution($table_name) {
        $this->db->query("
            SELECT 
                CASE 
                    WHEN (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) <= 1000 THEN '≤ ₦1,000'
                    WHEN (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) <= 5000 THEN '₦1,001 - ₦5,000'
                    WHEN (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) <= 10000 THEN '₦5,001 - ₦10,000'
                    WHEN (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) <= 50000 THEN '₦10,001 - ₦50,000'
                    WHEN (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) <= 100000 THEN '₦50,001 - ₦100,000'
                    ELSE '> ₦100,000'
                END as size_range,
                COUNT(*) as transaction_count,
                SUM(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as total_amount
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY size_range
            ORDER BY MIN(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0))
        ");
        
        return $this->db->resultSet();
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics($table_name) {
        // Current month
        $this->db->query("
            SELECT 
                COUNT(*) as current_transactions,
                SUM(COALESCE(debit_amount, 0)) as current_debits,
                SUM(COALESCE(credit_amount, 0)) as current_credits,
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as current_avg_amount
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())
        ");
        $current = $this->db->single();
        
        // Previous month
        $this->db->query("
            SELECT 
                COUNT(*) as prev_transactions,
                SUM(COALESCE(debit_amount, 0)) as prev_debits,
                SUM(COALESCE(credit_amount, 0)) as prev_credits,
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as prev_avg_amount
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(DATE_SUB(NOW(), INTERVAL 1 MONTH), INTERVAL DAY(NOW())-1 DAY)
            AND date < DATE_SUB(NOW(), INTERVAL DAY(NOW())-1 DAY)
        ");
        $previous = $this->db->single();
        
        // Calculate growth rates
        $transaction_growth = $previous['prev_transactions'] > 0 ? 
            (($current['current_transactions'] - $previous['prev_transactions']) / $previous['prev_transactions']) * 100 : 0;
        
        $debit_growth = $previous['prev_debits'] > 0 ? 
            (($current['current_debits'] - $previous['prev_debits']) / $previous['prev_debits']) * 100 : 0;
        
        $credit_growth = $previous['prev_credits'] > 0 ? 
            (($current['current_credits'] - $previous['prev_credits']) / $previous['prev_credits']) * 100 : 0;
        
        return [
            'current' => $current,
            'previous' => $previous,
            'growth' => [
                'transactions' => $transaction_growth,
                'debits' => $debit_growth,
                'credits' => $credit_growth
            ]
        ];
    }
    
    /**
     * Get anomaly detection
     */
    public function getAnomalies($table_name) {
        $anomalies = [];
        
        // Large transactions (3x above average)
        $this->db->query("
            SELECT AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as avg_amount
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $avg_data = $this->db->single();
        $threshold = $avg_data['avg_amount'] * 3;
        
        $this->db->query("
            SELECT *
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) > :threshold
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY date DESC
            LIMIT 10
        ");
        $this->db->bind(':threshold', $threshold);
        $large_transactions = $this->db->resultSet();
        
        if (!empty($large_transactions)) {
            $anomalies[] = [
                'type' => 'Large Transactions',
                'count' => count($large_transactions),
                'description' => 'Transactions significantly above average amount',
                'data' => $large_transactions
            ];
        }
        
        // Weekend transactions
        $this->db->query("
            SELECT *
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND DAYOFWEEK(date) IN (1, 7)
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY date DESC
            LIMIT 10
        ");
        $weekend_transactions = $this->db->resultSet();
        
        if (!empty($weekend_transactions)) {
            $anomalies[] = [
                'type' => 'Weekend Activity',
                'count' => count($weekend_transactions),
                'description' => 'Transactions occurring on weekends',
                'data' => $weekend_transactions
            ];
        }
        
        return $anomalies;
    }
}

$analyzer = new LedgerDetailedAnalyzer();
$account_id = $_GET['acct_id'] ?? null;

if (!$account_id) {
    header('Location: ledger.php');
    exit;
}

$account_info = $analyzer->getAccountInfo($account_id);
if (!$account_info) {
    header('Location: ledger.php');
    exit;
}

$table_name = $account_info['acct_table_name'];
$patterns = $analyzer->getTransactionPatterns($table_name);
$size_distribution = $analyzer->getTransactionSizeDistribution($table_name);
$performance = $analyzer->getPerformanceMetrics($table_name);
$anomalies = $analyzer->getAnomalies($table_name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Ledger Analysis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="ledger.php?acct_id=<?php echo $account_id; ?>" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Ledger</a>
                    <h1 class="text-xl font-bold text-gray-900">Detailed Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $account_info['acct_desc']; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Performance Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Transaction Growth</p>
                        <p class="text-2xl font-bold <?php echo $performance['growth']['transactions'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $performance['growth']['transactions'] >= 0 ? '+' : ''; ?><?php echo number_format($performance['growth']['transactions'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Credit Growth</p>
                        <p class="text-2xl font-bold <?php echo $performance['growth']['credits'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $performance['growth']['credits'] >= 0 ? '+' : ''; ?><?php echo number_format($performance['growth']['credits'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Avg Transaction</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($performance['current']['current_avg_amount']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Daily Pattern Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Transaction Patterns</h3>
                <div class="relative h-48">
                    <canvas id="dailyPatternChart"></canvas>
                </div>
            </div>

            <!-- Transaction Size Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Size Distribution</h3>
                <div class="relative h-48">
                    <canvas id="sizeDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Trends -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">12-Month Trend Analysis</h3>
            <div class="relative h-48">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <!-- Anomalies Section -->
        <?php if (!empty($anomalies)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Detected Anomalies</h3>
            <div class="space-y-4">
                <?php foreach ($anomalies as $anomaly): ?>
                <div class="border-l-4 border-yellow-500 pl-4">
                    <h4 class="font-medium text-gray-900"><?php echo $anomaly['type']; ?></h4>
                    <p class="text-sm text-gray-600"><?php echo $anomaly['description']; ?></p>
                    <p class="text-sm text-yellow-600 font-medium"><?php echo $anomaly['count']; ?> instances found</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Analysis Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Performance Insights</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($performance['growth']['transactions'] > 10): ?>
                            <li>• Strong transaction growth indicates healthy account activity</li>
                        <?php elseif ($performance['growth']['transactions'] < -10): ?>
                            <li>• Declining transaction volume requires investigation</li>
                        <?php else: ?>
                            <li>• Transaction volume is stable with normal fluctuations</li>
                        <?php endif; ?>
                        
                        <?php if ($performance['growth']['credits'] > 20): ?>
                            <li>• Exceptional credit growth suggests increased revenue</li>
                        <?php elseif ($performance['growth']['credits'] < -20): ?>
                            <li>• Significant credit decline needs immediate attention</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Action Items</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Monitor transaction patterns for consistency</li>
                        <li>• Review large transactions for accuracy</li>
                        <li>• Investigate any weekend activity if unusual</li>
                        <li>• Set up alerts for significant deviations</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Daily Pattern Chart
        const dailyCtx = document.getElementById('dailyPatternChart').getContext('2d');
        const dailyData = <?php echo json_encode($patterns['daily']); ?>;
        
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dailyData.map(item => item.day_name),
                datasets: [{
                    label: 'Transaction Count',
                    data: dailyData.map(item => item.transaction_count),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Size Distribution Chart
        const sizeCtx = document.getElementById('sizeDistributionChart').getContext('2d');
        const sizeData = <?php echo json_encode($size_distribution); ?>;
        
        new Chart(sizeCtx, {
            type: 'doughnut',
            data: {
                labels: sizeData.map(item => item.size_range),
                datasets: [{
                    data: sizeData.map(item => item.transaction_count),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 69, 19, 0.8)',
                        'rgba(75, 85, 99, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Monthly Trend Chart
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyData = <?php echo json_encode($patterns['monthly']); ?>;
        
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month_name + ' ' + item.year),
                datasets: [{
                    label: 'Credits (₦)',
                    data: monthlyData.map(item => item.total_credits),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Debits (₦)',
                    data: monthlyData.map(item => item.total_debits),
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
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