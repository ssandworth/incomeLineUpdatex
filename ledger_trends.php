<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class LedgerTrendAnalyzer {
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
     * Get 24-month trend data
     */
    public function getTwentyFourMonthTrend($table_name) {
        $this->db->query("
            SELECT 
                YEAR(date) as year,
                MONTH(date) as month,
                MONTHNAME(date) as month_name,
                COUNT(*) as transaction_count,
                SUM(COALESCE(debit_amount, 0)) as total_debits,
                SUM(COALESCE(credit_amount, 0)) as total_credits,
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as avg_transaction_size
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY year, month
        ");
        
        return $this->db->resultSet();
    }
    
    /**
     * Get quarterly analysis
     */
    public function getQuarterlyAnalysis($table_name) {
        $this->db->query("
            SELECT 
                YEAR(date) as year,
                QUARTER(date) as quarter,
                CONCAT('Q', QUARTER(date), ' ', YEAR(date)) as quarter_label,
                COUNT(*) as transaction_count,
                SUM(COALESCE(debit_amount, 0)) as total_debits,
                SUM(COALESCE(credit_amount, 0)) as total_credits
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY YEAR(date), QUARTER(date)
            ORDER BY year, quarter
        ");
        
        return $this->db->resultSet();
    }
    
    /**
     * Calculate trend indicators
     */
    public function getTrendIndicators($trend_data) {
        if (count($trend_data) < 6) return null;
        
        // Get last 6 months for trend calculation
        $recent_data = array_slice($trend_data, -6);
        $earlier_data = array_slice($trend_data, -12, 6);
        
        $recent_avg_credits = array_sum(array_column($recent_data, 'total_credits')) / count($recent_data);
        $earlier_avg_credits = array_sum(array_column($earlier_data, 'total_credits')) / count($earlier_data);
        
        $recent_avg_debits = array_sum(array_column($recent_data, 'total_debits')) / count($recent_data);
        $earlier_avg_debits = array_sum(array_column($earlier_data, 'total_debits')) / count($earlier_data);
        
        $credit_trend = $earlier_avg_credits > 0 ? 
            (($recent_avg_credits - $earlier_avg_credits) / $earlier_avg_credits) * 100 : 0;
        
        $debit_trend = $earlier_avg_debits > 0 ? 
            (($recent_avg_debits - $earlier_avg_debits) / $earlier_avg_debits) * 100 : 0;
        
        // Determine trend direction
        $credit_direction = $credit_trend > 5 ? 'increasing' : ($credit_trend < -5 ? 'decreasing' : 'stable');
        $debit_direction = $debit_trend > 5 ? 'increasing' : ($debit_trend < -5 ? 'decreasing' : 'stable');
        
        return [
            'credit_trend' => $credit_trend,
            'debit_trend' => $debit_trend,
            'credit_direction' => $credit_direction,
            'debit_direction' => $debit_direction,
            'recent_avg_credits' => $recent_avg_credits,
            'recent_avg_debits' => $recent_avg_debits
        ];
    }
    
    /**
     * Get seasonal patterns
     */
    public function getSeasonalPatterns($table_name) {
        $this->db->query("
            SELECT 
                MONTH(date) as month_number,
                MONTHNAME(date) as month_name,
                AVG(COALESCE(credit_amount, 0)) as avg_credits,
                AVG(COALESCE(debit_amount, 0)) as avg_debits,
                COUNT(*) as transaction_count
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
            GROUP BY MONTH(date), MONTHNAME(date)
            ORDER BY month_number
        ");
        
        $patterns = $this->db->resultSet();
        
        // Calculate seasonal indices
        $overall_avg_credits = array_sum(array_column($patterns, 'avg_credits')) / count($patterns);
        $overall_avg_debits = array_sum(array_column($patterns, 'avg_debits')) / count($patterns);
        
        foreach ($patterns as &$pattern) {
            $pattern['credit_seasonal_index'] = $overall_avg_credits > 0 ? 
                ($pattern['avg_credits'] / $overall_avg_credits) * 100 : 100;
            $pattern['debit_seasonal_index'] = $overall_avg_debits > 0 ? 
                ($pattern['avg_debits'] / $overall_avg_debits) * 100 : 100;
        }
        
        return $patterns;
    }
    
    /**
     * Get volatility analysis
     */
    public function getVolatilityAnalysis($table_name) {
        $this->db->query("
            SELECT 
                YEAR(date) as year,
                MONTH(date) as month,
                SUM(COALESCE(credit_amount, 0)) as monthly_credits,
                SUM(COALESCE(debit_amount, 0)) as monthly_debits
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY year, month
        ");
        
        $monthly_data = $this->db->resultSet();
        
        if (count($monthly_data) < 3) return null;
        
        // Calculate standard deviation for credits
        $credit_values = array_column($monthly_data, 'monthly_credits');
        $credit_mean = array_sum($credit_values) / count($credit_values);
        $credit_variance = array_sum(array_map(function($x) use ($credit_mean) { 
            return pow($x - $credit_mean, 2); 
        }, $credit_values)) / count($credit_values);
        $credit_std_dev = sqrt($credit_variance);
        
        // Calculate coefficient of variation
        $credit_cv = $credit_mean > 0 ? ($credit_std_dev / $credit_mean) * 100 : 0;
        
        // Determine volatility level
        $volatility_level = $credit_cv < 15 ? 'Low' : ($credit_cv < 30 ? 'Medium' : 'High');
        
        return [
            'coefficient_of_variation' => $credit_cv,
            'volatility_level' => $volatility_level,
            'standard_deviation' => $credit_std_dev,
            'mean_value' => $credit_mean
        ];
    }
}

$analyzer = new LedgerTrendAnalyzer();
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
$trend_data = $analyzer->getTwentyFourMonthTrend($table_name);
$quarterly_data = $analyzer->getQuarterlyAnalysis($table_name);
$trend_indicators = $analyzer->getTrendIndicators($trend_data);
$seasonal_patterns = $analyzer->getSeasonalPatterns($table_name);
$volatility = $analyzer->getVolatilityAnalysis($table_name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Trend Analysis</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Trend Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $account_info['acct_desc']; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Trend Indicators -->
        <?php if ($trend_indicators): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $trend_indicators['credit_direction'] === 'increasing' ? 'bg-green-100 text-green-600' : ($trend_indicators['credit_direction'] === 'decreasing' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'); ?>">
                        <?php if ($trend_indicators['credit_direction'] === 'increasing'): ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        <?php elseif ($trend_indicators['credit_direction'] === 'decreasing'): ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        <?php else: ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h8"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Credit Trend</p>
                        <p class="text-2xl font-bold <?php echo $trend_indicators['credit_direction'] === 'increasing' ? 'text-green-600' : ($trend_indicators['credit_direction'] === 'decreasing' ? 'text-red-600' : 'text-gray-600'); ?>">
                            <?php echo ucfirst($trend_indicators['credit_direction']); ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php echo $trend_indicators['credit_trend'] >= 0 ? '+' : ''; ?><?php echo number_format($trend_indicators['credit_trend'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Avg Monthly Credits</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($trend_indicators['recent_avg_credits']); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($volatility): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $volatility['volatility_level'] === 'Low' ? 'bg-green-100 text-green-600' : ($volatility['volatility_level'] === 'High' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600'); ?>">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Volatility</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $volatility['volatility_level']; ?></p>
                        <p class="text-xs text-gray-500">
                            CV: <?php echo number_format($volatility['coefficient_of_variation'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Data Points</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($trend_data); ?></p>
                        <p class="text-xs text-gray-500">24-month period</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- 24-Month Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">24-Month Performance Trend</h3>
                <div class="relative h-48">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Seasonal Patterns Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Seasonal Patterns</h3>
                <div class="relative h-48">
                    <canvas id="seasonalChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quarterly Analysis -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quarterly Performance</h3>
            <div class="relative h-48">
                <canvas id="quarterlyChart"></canvas>
            </div>
        </div>

        <!-- Insights and Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Trend Insights & Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Key Observations</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($trend_indicators): ?>
                            <li>• Credit trend is <?php echo $trend_indicators['credit_direction']; ?> with <?php echo number_format(abs($trend_indicators['credit_trend']), 1); ?>% change</li>
                        <?php endif; ?>
                        
                        <?php if ($volatility): ?>
                            <li>• Account shows <?php echo strtolower($volatility['volatility_level']); ?> volatility (<?php echo number_format($volatility['coefficient_of_variation'], 1); ?>% CV)</li>
                        <?php endif; ?>
                        
                        <li>• Historical data spans <?php echo count($trend_data); ?> months for comprehensive analysis</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Strategic Recommendations</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($trend_indicators && $trend_indicators['credit_direction'] === 'increasing'): ?>
                            <li>• Capitalize on positive trend with strategic investments</li>
                        <?php elseif ($trend_indicators && $trend_indicators['credit_direction'] === 'decreasing'): ?>
                            <li>• Investigate causes of declining performance</li>
                            <li>• Implement corrective measures to reverse trend</li>
                        <?php endif; ?>
                        
                        <?php if ($volatility && $volatility['volatility_level'] === 'High'): ?>
                            <li>• Implement risk management strategies</li>
                            <li>• Consider diversification to reduce volatility</li>
                        <?php endif; ?>
                        
                        <li>• Monitor seasonal patterns for planning purposes</li>
                        <li>• Set up automated alerts for trend changes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 24-Month Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php echo json_encode($trend_data); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month_name + ' ' + item.year),
                datasets: [{
                    label: 'Credits (₦)',
                    data: trendData.map(item => item.total_credits),
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Debits (₦)',
                    data: trendData.map(item => item.total_debits),
                    borderColor: 'rgba(239, 68, 68, 1)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
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
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Seasonal Chart
        const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
        const seasonalData = <?php echo json_encode($seasonal_patterns); ?>;
        
        new Chart(seasonalCtx, {
            type: 'bar',
            data: {
                labels: seasonalData.map(item => item.month_name),
                datasets: [{
                    label: 'Credit Seasonal Index',
                    data: seasonalData.map(item => item.credit_seasonal_index),
                    backgroundColor: seasonalData.map(item => 
                        item.credit_seasonal_index > 100 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.8)'
                    ),
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
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Index: ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });

        // Quarterly Chart
        const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
        const quarterlyData = <?php echo json_encode($quarterly_data); ?>;
        
        new Chart(quarterlyCtx, {
            type: 'bar',
            data: {
                labels: quarterlyData.map(item => item.quarter_label),
                datasets: [{
                    label: 'Credits (₦)',
                    data: quarterlyData.map(item => item.total_credits),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }, {
                    label: 'Debits (₦)',
                    data: quarterlyData.map(item => item.total_debits),
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderColor: 'rgba(239, 68, 68, 1)',
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