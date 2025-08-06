<?php
require_once 'OfficerPerformanceAnalyzer.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class DetailedPerformanceAnalyzer {
    private $db;
    private $analyzer;
    
    public function __construct() {
        $this->db = new Database();
        $this->analyzer = new OfficerPerformanceAnalyzer();
    }
    
    /**
     * Get comprehensive performance analysis
     */
    public function getComprehensiveAnalysis($month, $year) {
        $officers = $this->analyzer->getOfficerComparison($month, $year);
        $analysis = [];
        
        foreach ($officers as $officer) {
            if ($officer['total_collections'] > 0) {
                $efficiency = $this->analyzer->getOfficerEfficiencyMetrics(
                    $officer['user_id'], 
                    $month, 
                    $year, 
                    $officer['department'] !== 'Wealth Creation'
                );
                
                $trends = $this->analyzer->getOfficerTrends(
                    $officer['user_id'], 
                    $month, 
                    $year, 
                    $officer['department'] !== 'Wealth Creation'
                );
                
                $rating = $this->analyzer->getOfficerRating(
                    $officer['user_id'], 
                    $month, 
                    $year, 
                    $officer['department'] !== 'Wealth Creation'
                );
                
                // Calculate trend direction
                $trend_direction = 'stable';
                if (count($trends) >= 3) {
                    $recent_avg = array_sum(array_slice(array_column($trends, 'total'), -3)) / 3;
                    $earlier_avg = array_sum(array_slice(array_column($trends, 'total'), 0, 3)) / 3;
                    
                    if ($recent_avg > $earlier_avg * 1.1) {
                        $trend_direction = 'improving';
                    } elseif ($recent_avg < $earlier_avg * 0.9) {
                        $trend_direction = 'declining';
                    }
                }
                
                $analysis[] = [
                    'officer' => $officer,
                    'efficiency' => $efficiency,
                    'rating' => $rating,
                    'trend_direction' => $trend_direction,
                    'consistency_score' => $this->calculateConsistencyScore($trends)
                ];
            }
        }
        
        return $analysis;
    }
    
    /**
     * Calculate consistency score based on performance variance
     */
    private function calculateConsistencyScore($trends) {
        if (count($trends) < 3) return 0;
        
        $values = array_column($trends, 'total');
        $mean = array_sum($values) / count($values);
        
        if ($mean == 0) return 0;
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        
        $coefficient_of_variation = sqrt($variance) / $mean;
        
        // Convert to consistency score (lower CV = higher consistency)
        return max(0, min(100, 100 - ($coefficient_of_variation * 100)));
    }
    
    /**
     * Get department performance comparison
     */
    public function getDepartmentComparison($month, $year) {
        $this->db->query("
            SELECT 
                CASE 
                    WHEN s.department IS NOT NULL THEN s.department
                    ELSE so.department
                END as department,
                COUNT(DISTINCT CASE 
                    WHEN s.user_id IS NOT NULL THEN s.user_id
                    ELSE so.id
                END) as officer_count,
                COALESCE(SUM(t.amount_paid), 0) as total_collections,
                COUNT(t.id) as total_transactions
            FROM account_general_transaction_new t
            LEFT JOIN staffs s ON t.remitting_id = s.user_id
            LEFT JOIN staffs_others so ON t.remitting_id = so.id
            WHERE MONTH(t.date_of_payment) = :month 
            AND YEAR(t.date_of_payment) = :year
            AND (t.approval_status = 'Approved' OR t.approval_status = '')
            AND (s.user_id IS NOT NULL OR so.id IS NOT NULL)
            GROUP BY department
            ORDER BY total_collections DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        
        return $this->db->resultSet();
    }
}

$detailed_analyzer = new DetailedPerformanceAnalyzer();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

$comprehensive_analysis = $detailed_analyzer->getComprehensiveAnalysis($month, $year);
$department_comparison = $detailed_analyzer->getDepartmentComparison($month, $year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Detailed Performance Analysis</title>
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
                    <a href="mpr_income_lines_officers.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Officers</a>
                    <h1 class="text-xl font-bold text-gray-900">Detailed Performance Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Department Comparison -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Department Performance Comparison</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($department_comparison as $dept): ?>
                    <div class="border rounded-lg p-4">
                        <h4 class="font-semibold text-gray-900"><?php echo $dept['department']; ?></h4>
                        <div class="mt-2">
                            <div class="text-2xl font-bold text-blue-600">₦<?php echo number_format($dept['total_collections']); ?></div>
                            <div class="text-sm text-gray-500">
                                <?php echo $dept['officer_count']; ?> officers • 
                                <?php echo number_format($dept['total_transactions']); ?> transactions
                            </div>
                            <div class="text-sm text-gray-500">
                                Avg per officer: ₦<?php echo number_format($dept['officer_count'] > 0 ? $dept['total_collections'] / $dept['officer_count'] : 0); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Performance Matrix -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Officer Performance Matrix</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collections</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Rating</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Attendance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Consistency</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Avg</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Productivity</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($comprehensive_analysis as $analysis): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                        <?php echo strtoupper(substr($analysis['officer']['full_name'], 0, 2)); ?>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $analysis['officer']['full_name']; ?></div>
                                        <div class="text-xs text-gray-500"><?php echo $analysis['officer']['department']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($analysis['officer']['total_collections']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $analysis['rating']['rating_class']; ?>">
                                    <?php echo $analysis['rating']['rating']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-12 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $analysis['efficiency']['attendance_rate'] >= 90 ? 'bg-green-500' : ($analysis['efficiency']['attendance_rate'] >= 70 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo $analysis['efficiency']['attendance_rate']; ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($analysis['efficiency']['attendance_rate'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-12 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $analysis['consistency_score'] >= 80 ? 'bg-green-500' : ($analysis['consistency_score'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo $analysis['consistency_score']; ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($analysis['consistency_score'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $analysis['trend_direction'] === 'improving' ? 'bg-green-100 text-green-800' : 
                                              ($analysis['trend_direction'] === 'declining' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'); ?>">
                                    <i class="fas fa-<?php echo $analysis['trend_direction'] === 'improving' ? 'arrow-up' : 
                                                            ($analysis['trend_direction'] === 'declining' ? 'arrow-down' : 'minus'); ?> mr-1"></i>
                                    <?php echo ucfirst($analysis['trend_direction']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($analysis['efficiency']['daily_average']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-12 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $analysis['efficiency']['productivity_score'] >= 80 ? 'bg-green-500' : ($analysis['efficiency']['productivity_score'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $analysis['efficiency']['productivity_score'] / 100 * 100); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($analysis['efficiency']['productivity_score'], 1); ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Performance Insights -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Performance Insights</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">High Performers</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $high_performers = array_filter($comprehensive_analysis, function($a) {
                            return $a['rating']['performance_ratio'] >= 120;
                        });
                        ?>
                        <?php if (!empty($high_performers)): ?>
                            <?php foreach (array_slice($high_performers, 0, 3) as $performer): ?>
                                <li>• <?php echo $performer['officer']['full_name']; ?> - <?php echo number_format($performer['rating']['performance_ratio'], 1); ?>%</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>• No officers significantly above average this month</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Improvement Needed</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $low_performers = array_filter($comprehensive_analysis, function($a) {
                            return $a['rating']['performance_ratio'] < 80;
                        });
                        ?>
                        <?php if (!empty($low_performers)): ?>
                            <?php foreach (array_slice($low_performers, 0, 3) as $performer): ?>
                                <li>• <?php echo $performer['officer']['full_name']; ?> - <?php echo number_format($performer['rating']['performance_ratio'], 1); ?>%</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>• All officers meeting minimum performance standards</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Trending Up</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <?php 
                        $improving = array_filter($comprehensive_analysis, function($a) {
                            return $a['trend_direction'] === 'improving';
                        });
                        ?>
                        <?php if (!empty($improving)): ?>
                            <?php foreach (array_slice($improving, 0, 3) as $performer): ?>
                                <li>• <?php echo $performer['officer']['full_name']; ?> - Improving trend</li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li>• No significant improving trends detected</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>