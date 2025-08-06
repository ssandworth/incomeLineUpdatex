<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class TrendAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get 12-month trend data
     */
    public function getTwelveMonthTrend($current_month, $current_year) {
        $trend_data = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $month = $current_month - $i;
            $year = $current_year;
            
            if ($month <= 0) {
                $month += 12;
                $year--;
            }
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as monthly_total 
                FROM account_general_transaction_new 
                WHERE MONTH(date_of_payment) = :month 
                AND YEAR(date_of_payment) = :year
            ");
            
            $this->db->bind(':month', $month);
            $this->db->bind(':year', $year);
            
            $result = $this->db->single();
            
            $trend_data[] = [
                'month' => $month,
                'year' => $year,
                'month_name' => date('M Y', mktime(0, 0, 0, $month, 1, $year)),
                'total' => $result['monthly_total']
            ];
        }
        
        return $trend_data;
    }
    
    /**
     * Get seasonal analysis
     */
    public function getSeasonalAnalysis($year) {
        $seasons = [
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12]
        ];
        
        $seasonal_data = [];
        
        foreach ($seasons as $quarter => $months) {
            $month_list = implode(',', $months);
            
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as quarter_total 
                FROM account_general_transaction_new 
                WHERE MONTH(date_of_payment) IN ($month_list) 
                AND YEAR(date_of_payment) = :year
            ");
            
            $this->db->bind(':year', $year);
            $result = $this->db->single();
            
            $seasonal_data[] = [
                'quarter' => $quarter,
                'total' => $result['quarter_total']
            ];
        }
        
        return $seasonal_data;
    }
    
    /**
     * Calculate trend indicators
     */
    public function getTrendIndicators($trend_data) {
        if (count($trend_data) < 2) return null;
        
        $recent_months = array_slice($trend_data, -3); // Last 3 months
        $earlier_months = array_slice($trend_data, -6, 3); // 3 months before that
        
        $recent_avg = array_sum(array_column($recent_months, 'total')) / count($recent_months);
        $earlier_avg = array_sum(array_column($earlier_months, 'total')) / count($earlier_months);
        
        $trend_direction = $recent_avg > $earlier_avg ? 'up' : ($recent_avg < $earlier_avg ? 'down' : 'stable');
        $trend_strength = $earlier_avg > 0 ? abs(($recent_avg - $earlier_avg) / $earlier_avg) * 100 : 0;
        
        return [
            'direction' => $trend_direction,
            'strength' => $trend_strength,
            'recent_average' => $recent_avg,
            'earlier_average' => $earlier_avg
        ];
    }
}

$analyzer = new TrendAnalyzer();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

$trend_data = $analyzer->getTwelveMonthTrend($month, $year);
$seasonal_data = $analyzer->getSeasonalAnalysis($year);
$trend_indicators = $analyzer->getTrendIndicators($trend_data);
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
                    <a href="mpr.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to MPR</a>
                    <h1 class="text-xl font-bold text-gray-900">Trend Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">12-Month Analysis</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Trend Indicators -->
        <?php if ($trend_indicators): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $trend_indicators['direction'] === 'up' ? 'bg-green-100 text-green-600' : ($trend_indicators['direction'] === 'down' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'); ?>">
                        <?php if ($trend_indicators['direction'] === 'up'): ?>
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        <?php elseif ($trend_indicators['direction'] === 'down'): ?>
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
                        <p class="text-sm font-medium text-gray-500">Trend Direction</p>
                        <p class="text-2xl font-bold <?php echo $trend_indicators['direction'] === 'up' ? 'text-green-600' : ($trend_indicators['direction'] === 'down' ? 'text-red-600' : 'text-gray-600'); ?>">
                            <?php echo ucfirst($trend_indicators['direction']); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Trend Strength</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($trend_indicators['strength'], 1); ?>%</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Recent Average</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($trend_indicators['recent_average']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- 12-Month Trend Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">12-Month Performance Trend</h3>
                <div class="relative h-48">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <!-- Seasonal Analysis Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Quarterly Performance</h3>
                <div class="relative h-48">
                    <canvas id="seasonalChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Trend Analysis Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Monthly Breakdown</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Collections</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Month-over-Month</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $previous_total = 0;
                        foreach ($trend_data as $index => $data): 
                            $mom_change = 0;
                            $mom_percentage = 0;
                            
                            if ($index > 0 && $previous_total > 0) {
                                $mom_change = $data['total'] - $previous_total;
                                $mom_percentage = ($mom_change / $previous_total) * 100;
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $data['month_name']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($data['total']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $mom_change >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php if ($index > 0): ?>
                                    <?php echo $mom_change >= 0 ? '+' : ''; ?><?php echo number_format($mom_percentage, 1); ?>%
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($index > 0): ?>
                                    <?php if ($mom_percentage > 10): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Excellent</span>
                                    <?php elseif ($mom_percentage > 0): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Good</span>
                                    <?php elseif ($mom_percentage > -10): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Fair</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Poor</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Baseline</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                        $previous_total = $data['total'];
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 12-Month Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendData = <?php echo json_encode($trend_data); ?>;
        
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => item.month_name),
                datasets: [{
                    label: 'Monthly Collections (₦)',
                    data: trendData.map(item => item.total),
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
                                return 'Amount: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Seasonal Chart
        const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
        const seasonalData = <?php echo json_encode($seasonal_data); ?>;
        
        new Chart(seasonalCtx, {
            type: 'doughnut',
            data: {
                labels: seasonalData.map(item => item.quarter),
                datasets: [{
                    data: seasonalData.map(item => item.total),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderWidth: 2
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