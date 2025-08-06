<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class ForecastingEngine {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Simple linear regression forecast
     */
    public function generateForecast($months_ahead = 3) {
        // Get last 12 months of data
        $this->db->query("
            SELECT 
                YEAR(date_of_payment) as year,
                MONTH(date_of_payment) as month,
                SUM(amount_paid) as monthly_total
            FROM account_general_transaction_new 
            WHERE date_of_payment >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY YEAR(date_of_payment), MONTH(date_of_payment)
            ORDER BY year, month
        ");
        
        $historical_data = $this->db->resultSet();
        
        if (count($historical_data) < 6) {
            return null; // Need at least 6 months of data
        }
        
        // Calculate trend using simple linear regression
        $n = count($historical_data);
        $sum_x = 0;
        $sum_y = 0;
        $sum_xy = 0;
        $sum_x2 = 0;
        
        foreach ($historical_data as $index => $data) {
            $x = $index + 1; // Time period
            $y = $data['monthly_total'];
            
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Generate forecasts
        $forecasts = [];
        $current_date = new DateTime();
        
        for ($i = 1; $i <= $months_ahead; $i++) {
            $forecast_date = clone $current_date;
            $forecast_date->add(new DateInterval("P{$i}M"));
            
            $x = $n + $i;
            $forecast_value = $slope * $x + $intercept;
            
            // Add some confidence intervals (simple approach)
            $confidence_range = $forecast_value * 0.15; // ±15%
            
            $forecasts[] = [
                'month' => $forecast_date->format('F Y'),
                'forecast' => max(0, $forecast_value), // Ensure non-negative
                'lower_bound' => max(0, $forecast_value - $confidence_range),
                'upper_bound' => $forecast_value + $confidence_range,
                'confidence' => $this->calculateConfidence($historical_data)
            ];
        }
        
        return [
            'historical' => $historical_data,
            'forecasts' => $forecasts,
            'trend_slope' => $slope,
            'trend_direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable')
        ];
    }
    
    /**
     * Calculate forecast confidence based on data consistency
     */
    private function calculateConfidence($data) {
        if (count($data) < 3) return 50;
        
        $values = array_column($data, 'monthly_total');
        $mean = array_sum($values) / count($values);
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        
        $coefficient_of_variation = $mean > 0 ? sqrt($variance) / $mean : 1;
        
        // Convert to confidence percentage (lower CV = higher confidence)
        $confidence = max(50, min(95, 100 - ($coefficient_of_variation * 100)));
        
        return round($confidence);
    }
    
    /**
     * Get seasonal patterns
     */
    public function getSeasonalPatterns() {
        $this->db->query("
            SELECT 
                MONTH(date_of_payment) as month,
                AVG(amount_paid) as avg_daily,
                COUNT(*) as transaction_count
            FROM account_general_transaction_new 
            WHERE date_of_payment >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)
            GROUP BY MONTH(date_of_payment)
            ORDER BY month
        ");
        
        $patterns = $this->db->resultSet();
        
        // Calculate seasonal indices
        $overall_avg = 0;
        $total_transactions = 0;
        
        foreach ($patterns as $pattern) {
            $overall_avg += $pattern['avg_daily'] * $pattern['transaction_count'];
            $total_transactions += $pattern['transaction_count'];
        }
        
        $overall_avg = $total_transactions > 0 ? $overall_avg / $total_transactions : 0;
        
        foreach ($patterns as &$pattern) {
            $pattern['seasonal_index'] = $overall_avg > 0 ? ($pattern['avg_daily'] / $overall_avg) * 100 : 100;
            $pattern['month_name'] = date('F', mktime(0, 0, 0, $pattern['month'], 1));
        }
        
        return $patterns;
    }
}

$forecaster = new ForecastingEngine();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');

$forecast_data = $forecaster->generateForecast(6); // 6 months ahead
$seasonal_patterns = $forecaster->getSeasonalPatterns();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Revenue Forecasting</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Revenue Forecasting</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">6-Month Forecast</span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php if ($forecast_data): ?>
        <!-- Forecast Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Trend Direction</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo ucfirst($forecast_data['trend_direction']); ?></p>
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
                        <p class="text-sm font-medium text-gray-500">Next Month Forecast</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($forecast_data['forecasts'][0]['forecast']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Forecast Confidence</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $forecast_data['forecasts'][0]['confidence']; ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Forecast Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Revenue Forecast</h3>
                <div class="relative h-48">
                    <canvas id="forecastChart"></canvas>
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

        <!-- Forecast Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">6-Month Forecast</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Forecast</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Lower Bound</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Upper Bound</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($forecast_data['forecasts'] as $forecast): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $forecast['month']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($forecast['forecast']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                                ₦<?php echo number_format($forecast['lower_bound']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 text-right">
                                ₦<?php echo number_format($forecast['upper_bound']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $forecast['confidence'] >= 80 ? 'bg-green-100 text-green-800' : 
                                              ($forecast['confidence'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo $forecast['confidence']; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Planning Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Planning Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Short-term Actions (1-3 months)</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($forecast_data['trend_direction'] === 'increasing'): ?>
                            <li>• Prepare for increased operational capacity</li>
                            <li>• Consider expanding high-performing income lines</li>
                            <li>• Plan for additional staffing if needed</li>
                        <?php elseif ($forecast_data['trend_direction'] === 'decreasing'): ?>
                            <li>• Implement cost reduction measures</li>
                            <li>• Focus on improving underperforming areas</li>
                            <li>• Consider promotional activities to boost revenue</li>
                        <?php else: ?>
                            <li>• Maintain current operational levels</li>
                            <li>• Look for optimization opportunities</li>
                            <li>• Monitor for trend changes</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Long-term Strategy (3-6 months)</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Review and adjust pricing strategies</li>
                        <li>• Invest in technology improvements</li>
                        <li>• Develop new revenue streams</li>
                        <li>• Plan for seasonal variations</li>
                    </ul>
                </div>
            </div>
        </div>

        <?php else: ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Insufficient Data</h3>
            <p class="text-gray-600">At least 6 months of historical data is required to generate reliable forecasts.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($forecast_data): ?>
    <script>
        // Forecast Chart
        const forecastCtx = document.getElementById('forecastChart').getContext('2d');
        const historicalData = <?php echo json_encode($forecast_data['historical']); ?>;
        const forecastData = <?php echo json_encode($forecast_data['forecasts']); ?>;
        
        const allLabels = [
            ...historicalData.map(item => `${item.month}/${item.year}`),
            ...forecastData.map(item => item.month)
        ];
        
        const historicalValues = [
            ...historicalData.map(item => item.monthly_total),
            ...Array(forecastData.length).fill(null)
        ];
        
        const forecastValues = [
            ...Array(historicalData.length).fill(null),
            ...forecastData.map(item => item.forecast)
        ];
        
        new Chart(forecastCtx, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: [{
                    label: 'Historical',
                    data: historicalValues,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: false
                }, {
                    label: 'Forecast',
                    data: forecastValues,
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
                    label: 'Seasonal Index',
                    data: seasonalData.map(item => item.seasonal_index),
                    backgroundColor: seasonalData.map(item => 
                        item.seasonal_index > 100 ? 'rgba(16, 185, 129, 0.8)' : 'rgba(239, 68, 68, 0.8)'
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
    </script>
    <?php endif; ?>
</body>
</html>