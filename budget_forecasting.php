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

class BudgetForecastingEngine {
    private $db;
    private $manager;
    
    public function __construct() {
        $this->db = new Database();
        $this->manager = new BudgetManager();
    }
    
    /**
     * Generate budget forecasts for next year
     */
    public function generateBudgetForecasts($current_year, $acct_id = null) {
        $where_clause = $acct_id ? "AND bl.acct_id = :acct_id" : "";
        
        // Get historical performance data
        $this->db->query("
            SELECT 
                bl.acct_id,
                bl.acct_desc,
                bp.performance_month,
                bp.performance_year,
                bp.budgeted_amount,
                bp.actual_amount,
                bp.variance_percentage
            FROM budget_lines bl
            JOIN budget_performance bp ON bl.acct_id = bp.acct_id 
                AND bl.budget_year = bp.performance_year
            WHERE bp.performance_year >= :start_year
            {$where_clause}
            ORDER BY bl.acct_id, bp.performance_year, bp.performance_month
        ");
        
        $this->db->bind(':start_year', $current_year - 2);
        if ($acct_id) {
            $this->db->bind(':acct_id', $acct_id);
        }
        
        $historical_data = $this->db->resultSet();
        
        // Group by account
        $grouped_data = [];
        foreach ($historical_data as $data) {
            $grouped_data[$data['acct_id']][] = $data;
        }
        
        $forecasts = [];
        
        foreach ($grouped_data as $account_id => $account_data) {
            $account_name = $account_data[0]['acct_desc'];
            
            // Calculate trend for each month
            $monthly_forecasts = [];
            
            for ($month = 1; $month <= 12; $month++) {
                $month_data = array_filter($account_data, function($d) use ($month) {
                    return $d['performance_month'] == $month;
                });
                
                if (count($month_data) >= 2) {
                    // Simple linear regression
                    $values = array_column($month_data, 'actual_amount');
                    $years = array_column($month_data, 'performance_year');
                    
                    $forecast = $this->calculateLinearForecast($years, $values, $current_year + 1);
                    $confidence = $this->calculateConfidence($values);
                } else {
                    // Use average if insufficient data
                    $avg_actual = count($month_data) > 0 ? 
                        array_sum(array_column($month_data, 'actual_amount')) / count($month_data) : 0;
                    $forecast = $avg_actual * 1.05; // 5% growth assumption
                    $confidence = 60; // Lower confidence
                }
                
                $monthly_forecasts[] = [
                    'month' => $month,
                    'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                    'forecast' => max(0, $forecast),
                    'confidence' => $confidence
                ];
            }
            
            $annual_forecast = array_sum(array_column($monthly_forecasts, 'forecast'));
            $avg_confidence = array_sum(array_column($monthly_forecasts, 'confidence')) / 12;
            
            $forecasts[] = [
                'acct_id' => $account_id,
                'acct_desc' => $account_name,
                'monthly_forecasts' => $monthly_forecasts,
                'annual_forecast' => $annual_forecast,
                'avg_confidence' => $avg_confidence
            ];
        }
        
        return $forecasts;
    }
    
    /**
     * Simple linear regression forecast
     */
    private function calculateLinearForecast($x_values, $y_values, $forecast_x) {
        $n = count($x_values);
        if ($n < 2) return 0;
        
        $sum_x = array_sum($x_values);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_values[$i] * $y_values[$i];
            $sum_x2 += $x_values[$i] * $x_values[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        return $slope * $forecast_x + $intercept;
    }
    
    /**
     * Calculate forecast confidence
     */
    private function calculateConfidence($values) {
        if (count($values) < 2) return 50;
        
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        
        $coefficient_of_variation = $mean > 0 ? sqrt($variance) / $mean : 1;
        
        // Convert to confidence percentage
        return max(50, min(95, 100 - ($coefficient_of_variation * 50)));
    }
    
    /**
     * Get seasonal patterns
     */
    public function getSeasonalPatterns($current_year) {
        $this->db->query("
            SELECT 
                bp.performance_month,
                AVG(bp.actual_amount) as avg_actual,
                COUNT(*) as data_points
            FROM budget_performance bp
            WHERE bp.performance_year >= :start_year
            GROUP BY bp.performance_month
            ORDER BY bp.performance_month
        ");
        
        $this->db->bind(':start_year', $current_year - 2);
        $patterns = $this->db->resultSet();
        
        // Calculate seasonal indices
        $overall_avg = 0;
        $total_points = 0;
        
        foreach ($patterns as $pattern) {
            $overall_avg += $pattern['avg_actual'] * $pattern['data_points'];
            $total_points += $pattern['data_points'];
        }
        
        $overall_avg = $total_points > 0 ? $overall_avg / $total_points : 0;
        
        foreach ($patterns as &$pattern) {
            $pattern['seasonal_index'] = $overall_avg > 0 ? ($pattern['avg_actual'] / $overall_avg) * 100 : 100;
        }
        
        return $patterns;
    }
}

$forecaster = new BudgetForecastingEngine();
$manager = new BudgetManager();

// Check access permissions
$can_view = $manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

$year = $_GET['year'] ?? date('Y');
$acct_id = $_GET['acct_id'] ?? null;

$forecasts = $forecaster->generateBudgetForecasts($year, $acct_id);
$seasonal_patterns = $forecaster->getSeasonalPatterns($year);

// Calculate summary statistics
$total_current_budget = 0;
$total_forecast = 0;
$high_confidence_count = 0;

foreach ($forecasts as $forecast) {
    $total_forecast += $forecast['annual_forecast'];
    if ($forecast['avg_confidence'] >= 80) {
        $high_confidence_count++;
    }
}

// Get current year budget for comparison
$current_budget_lines = $manager->getBudgetLines($year);
foreach ($current_budget_lines as $line) {
    $total_current_budget += $line['annual_budget'];
}

$forecast_growth = $total_current_budget > 0 ? 
    (($total_forecast - $total_current_budget) / $total_current_budget) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Forecasting</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Budget Forecasting</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Forecast for <?php echo $year + 1; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Forecast Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-calendar text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Current Year Budget</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_current_budget); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-crystal-ball text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Forecast <?php echo $year + 1; ?></p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($total_forecast); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full <?php echo $forecast_growth >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                        <i class="fas fa-chart-line text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Projected Growth</p>
                        <p class="text-2xl font-bold <?php echo $forecast_growth >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $forecast_growth >= 0 ? '+' : ''; ?><?php echo number_format($forecast_growth, 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-check-circle text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">High Confidence</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $high_confidence_count; ?></p>
                        <p class="text-xs text-gray-500">of <?php echo count($forecasts); ?> forecasts</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Forecast vs Current Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Forecast vs Current Budget</h3>
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

        <!-- Forecast Details Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Detailed Budget Forecasts for <?php echo $year + 1; ?></h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Budget</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Forecast</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Growth</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Recommendation</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($forecasts as $forecast): ?>
                        <?php 
                        // Get current budget for comparison
                        $current_budget = 0;
                        foreach ($current_budget_lines as $line) {
                            if ($line['acct_id'] === $forecast['acct_id']) {
                                $current_budget = $line['annual_budget'];
                                break;
                            }
                        }
                        
                        $growth_rate = $current_budget > 0 ? 
                            (($forecast['annual_forecast'] - $current_budget) / $current_budget) * 100 : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="budget_details.php?id=<?php echo $forecast['acct_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <?php echo $forecast['acct_desc']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($current_budget); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($forecast['annual_forecast']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $growth_rate >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $growth_rate >= 0 ? '+' : ''; ?><?php echo number_format($growth_rate, 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $forecast['avg_confidence'] >= 80 ? 'bg-green-100 text-green-800' : 
                                              ($forecast['avg_confidence'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo number_format($forecast['avg_confidence'], 0); ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php if ($growth_rate > 20): ?>
                                    <span class="text-green-600">Strong growth expected - increase budget allocation</span>
                                <?php elseif ($growth_rate > 5): ?>
                                    <span class="text-blue-600">Moderate growth - maintain current strategy</span>
                                <?php elseif ($growth_rate > -5): ?>
                                    <span class="text-yellow-600">Stable performance - monitor closely</span>
                                <?php else: ?>
                                    <span class="text-red-600">Declining trend - review and adjust strategy</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTAL</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_current_budget); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($total_forecast); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                <?php echo $forecast_growth >= 0 ? '+' : ''; ?><?php echo number_format($forecast_growth, 1); ?>%
                            </th>
                            <th colspan="2" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Strategic Recommendations -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Strategic Budget Recommendations</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Budget Planning Insights</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php if ($forecast_growth > 10): ?>
                            <li>• Strong growth forecast suggests increased investment opportunities</li>
                            <li>• Consider expanding high-performing income lines</li>
                            <li>• Plan for additional operational capacity</li>
                        <?php elseif ($forecast_growth > 0): ?>
                            <li>• Moderate growth expected - maintain current strategies</li>
                            <li>• Focus on operational efficiency improvements</li>
                            <li>• Monitor performance indicators closely</li>
                        <?php else: ?>
                            <li>• Declining forecast requires strategic review</li>
                            <li>• Implement cost optimization measures</li>
                            <li>• Explore new revenue opportunities</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Implementation Actions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Review and adjust budget allocations based on forecasts</li>
                        <li>• Set realistic targets for <?php echo $year + 1; ?></li>
                        <li>• Plan resource allocation and staffing needs</li>
                        <li>• Establish monitoring and review processes</li>
                        <li>• Prepare contingency plans for variance scenarios</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Forecast vs Current Chart
        const forecastCtx = document.getElementById('forecastChart').getContext('2d');
        const forecastData = <?php echo json_encode($forecasts); ?>;
        const currentBudgets = <?php echo json_encode($current_budget_lines); ?>;
        
        // Match current budgets with forecasts
        const chartData = forecastData.map(forecast => {
            const currentBudget = currentBudgets.find(budget => budget.acct_id === forecast.acct_id);
            return {
                label: forecast.acct_desc.length > 15 ? forecast.acct_desc.substring(0, 15) + '...' : forecast.acct_desc,
                current: currentBudget ? currentBudget.annual_budget : 0,
                forecast: forecast.annual_forecast
            };
        });
        
        new Chart(forecastCtx, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.label),
                datasets: [{
                    label: 'Current Budget (₦)',
                    data: chartData.map(item => item.current),
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }, {
                    label: 'Forecast <?php echo $year + 1; ?> (₦)',
                    data: chartData.map(item => item.forecast),
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

        // Seasonal Patterns Chart
        const seasonalCtx = document.getElementById('seasonalChart').getContext('2d');
        const seasonalData = <?php echo json_encode($seasonal_patterns); ?>;
        
        new Chart(seasonalCtx, {
            type: 'line',
            data: {
                labels: seasonalData.map(item => new Date(2024, item.performance_month - 1).toLocaleDateString('en-US', {month: 'short'})),
                datasets: [{
                    label: 'Seasonal Index',
                    data: seasonalData.map(item => item.seasonal_index),
                    borderColor: 'rgba(139, 69, 19, 1)',
                    backgroundColor: 'rgba(139, 69, 19, 0.1)',
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
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Seasonal Index: ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>