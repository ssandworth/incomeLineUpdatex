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

class RewardSystemAnalyzer {
    private $db;
    private $analyzer;
    
    public function __construct() {
        $this->db = new Database();
        $this->analyzer = new OfficerPerformanceAnalyzer();
    }
    
    /**
     * Calculate reward recommendations
     */
    public function calculateRewardRecommendations($month, $year) {
        $officers = $this->analyzer->getOfficerComparison($month, $year);
        $recommendations = [];
        
        // Filter officers with collections
        $active_officers = array_filter($officers, function($officer) {
            return $officer['total_collections'] > 0;
        });
        
        if (empty($active_officers)) {
            return $recommendations;
        }
        
        // Calculate percentiles
        $collections = array_column($active_officers, 'total_collections');
        sort($collections);
        $count = count($collections);
        
        $percentile_90 = $collections[floor($count * 0.9)] ?? 0;
        $percentile_75 = $collections[floor($count * 0.75)] ?? 0;
        $percentile_50 = $collections[floor($count * 0.5)] ?? 0;
        
        foreach ($active_officers as $officer) {
            $rating = $this->analyzer->getOfficerRating(
                $officer['user_id'], 
                $month, 
                $year, 
                $officer['department'] !== 'Wealth Creation'
            );
            
            $efficiency = $this->analyzer->getOfficerEfficiencyMetrics(
                $officer['user_id'], 
                $month, 
                $year, 
                $officer['department'] !== 'Wealth Creation'
            );
            
            $reward_category = 'None';
            $reward_amount = 0;
            $reward_type = 'Recognition';
            
            if ($officer['total_collections'] >= $percentile_90) {
                $reward_category = 'Platinum';
                $reward_amount = 50000;
                $reward_type = 'Cash Bonus + Recognition';
            } elseif ($officer['total_collections'] >= $percentile_75) {
                $reward_category = 'Gold';
                $reward_amount = 30000;
                $reward_type = 'Cash Bonus + Certificate';
            } elseif ($officer['total_collections'] >= $percentile_50) {
                $reward_category = 'Silver';
                $reward_amount = 15000;
                $reward_type = 'Cash Bonus';
            } elseif ($efficiency['attendance_rate'] >= 90) {
                $reward_category = 'Bronze';
                $reward_amount = 5000;
                $reward_type = 'Attendance Bonus';
            }
            
            $recommendations[] = [
                'officer' => $officer,
                'rating' => $rating,
                'efficiency' => $efficiency,
                'reward_category' => $reward_category,
                'reward_amount' => $reward_amount,
                'reward_type' => $reward_type,
                'justification' => $this->getRewardJustification($officer, $rating, $efficiency)
            ];
        }
        
        // Sort by reward amount descending
        usort($recommendations, function($a, $b) {
            return $b['reward_amount'] <=> $a['reward_amount'];
        });
        
        return $recommendations;
    }
    
    /**
     * Get reward justification
     */
    private function getRewardJustification($officer, $rating, $efficiency) {
        $justifications = [];
        
        if ($rating['performance_ratio'] >= 150) {
            $justifications[] = 'Exceptional performance (' . number_format($rating['performance_ratio'], 1) . '% of average)';
        }
        
        if ($efficiency['attendance_rate'] >= 90) {
            $justifications[] = 'Excellent attendance (' . number_format($efficiency['attendance_rate'], 1) . '%)';
        }
        
        if ($efficiency['daily_average'] > 50000) {
            $justifications[] = 'High daily productivity (₦' . number_format($efficiency['daily_average']) . '/day)';
        }
        
        if ($officer['total_transactions'] > 100) {
            $justifications[] = 'High transaction volume (' . $officer['total_transactions'] . ' transactions)';
        }
        
        return !empty($justifications) ? implode('; ', $justifications) : 'Standard performance metrics met';
    }
    
    /**
     * Get reward budget summary
     */
    public function getRewardBudgetSummary($recommendations) {
        $total_budget = array_sum(array_column($recommendations, 'reward_amount'));
        $categories = [];
        
        foreach ($recommendations as $rec) {
            if ($rec['reward_category'] !== 'None') {
                if (!isset($categories[$rec['reward_category']])) {
                    $categories[$rec['reward_category']] = [
                        'count' => 0,
                        'total_amount' => 0
                    ];
                }
                $categories[$rec['reward_category']]['count']++;
                $categories[$rec['reward_category']]['total_amount'] += $rec['reward_amount'];
            }
        }
        
        return [
            'total_budget' => $total_budget,
            'categories' => $categories,
            'eligible_officers' => count(array_filter($recommendations, function($rec) {
                return $rec['reward_category'] !== 'None';
            }))
        ];
    }
}

$reward_analyzer = new RewardSystemAnalyzer();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

$reward_recommendations = $reward_analyzer->calculateRewardRecommendations($month, $year);
$budget_summary = $reward_analyzer->getRewardBudgetSummary($reward_recommendations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Reward System</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Officer Reward System</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Budget Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-money-bill-wave text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Budget</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($budget_summary['total_budget']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Eligible Officers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $budget_summary['eligible_officers']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-trophy text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Platinum Awards</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $budget_summary['categories']['Platinum']['count'] ?? 0; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-medal text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Gold Awards</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $budget_summary['categories']['Gold']['count'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reward Categories -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Reward Categories Breakdown</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($budget_summary['categories'] as $category => $data): ?>
                <div class="border rounded-lg p-4 <?php echo $category === 'Platinum' ? 'border-yellow-300 bg-yellow-50' : 
                    ($category === 'Gold' ? 'border-yellow-200 bg-yellow-25' : 
                    ($category === 'Silver' ? 'border-gray-300 bg-gray-50' : 'border-orange-300 bg-orange-50')); ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold text-gray-900"><?php echo $category; ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $data['count']; ?> officers</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900">₦<?php echo number_format($data['total_amount']); ?></p>
                            <p class="text-xs text-gray-500">Total allocation</p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reward Recommendations Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Reward Recommendations</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Collections</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Performance Rating</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Reward Category</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Reward Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reward Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Justification</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reward_recommendations as $recommendation): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                        <?php echo strtoupper(substr($recommendation['officer']['full_name'], 0, 2)); ?>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $recommendation['officer']['full_name']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $recommendation['officer']['department'] === 'Wealth Creation' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo $recommendation['officer']['department']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($recommendation['officer']['total_collections']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $recommendation['rating']['rating_class']; ?>">
                                    <?php echo $recommendation['rating']['rating']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($recommendation['reward_category'] !== 'None'): ?>
                                    <span class="px-3 py-1 text-sm font-semibold rounded-full 
                                        <?php echo $recommendation['reward_category'] === 'Platinum' ? 'bg-yellow-100 text-yellow-800' : 
                                                  ($recommendation['reward_category'] === 'Gold' ? 'bg-yellow-50 text-yellow-700' : 
                                                  ($recommendation['reward_category'] === 'Silver' ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800')); ?>">
                                        <i class="fas fa-<?php echo $recommendation['reward_category'] === 'Platinum' ? 'crown' : 
                                                                ($recommendation['reward_category'] === 'Gold' ? 'medal' : 
                                                                ($recommendation['reward_category'] === 'Silver' ? 'award' : 'star')); ?> mr-1"></i>
                                        <?php echo $recommendation['reward_category']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">No reward</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                <?php if ($recommendation['reward_amount'] > 0): ?>
                                    <span class="text-green-600">₦<?php echo number_format($recommendation['reward_amount']); ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">₦0</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $recommendation['reward_type']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo $recommendation['justification']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Implementation Guidelines -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Implementation Guidelines</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Reward Criteria</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• <strong>Platinum:</strong> Top 10% performers (₦50,000 + Recognition)</li>
                        <li>• <strong>Gold:</strong> Top 25% performers (₦30,000 + Certificate)</li>
                        <li>• <strong>Silver:</strong> Above median performers (₦15,000)</li>
                        <li>• <strong>Bronze:</strong> Excellent attendance (₦5,000)</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Implementation Steps</h4>
                    <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                        <li>Review and approve reward recommendations</li>
                        <li>Prepare budget allocation and approvals</li>
                        <li>Schedule recognition ceremony</li>
                        <li>Process cash bonuses through payroll</li>
                        <li>Document awards in personnel records</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>