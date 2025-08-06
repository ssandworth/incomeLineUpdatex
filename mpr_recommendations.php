<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class RecommendationEngine {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Generate comprehensive recommendations
     */
    public function generateRecommendations($month, $year) {
        $recommendations = [];
        
        // Performance-based recommendations
        $performance_recs = $this->getPerformanceRecommendations($month, $year);
        $recommendations = array_merge($recommendations, $performance_recs);
        
        // Operational recommendations
        $operational_recs = $this->getOperationalRecommendations($month, $year);
        $recommendations = array_merge($recommendations, $operational_recs);
        
        // Strategic recommendations
        $strategic_recs = $this->getStrategicRecommendations($month, $year);
        $recommendations = array_merge($recommendations, $strategic_recs);
        
        // Risk management recommendations
        $risk_recs = $this->getRiskRecommendations($month, $year);
        $recommendations = array_merge($recommendations, $risk_recs);
        
        return $recommendations;
    }
    
    /**
     * Performance-based recommendations
     */
    private function getPerformanceRecommendations($month, $year) {
        $recommendations = [];
        
        // Get variance data
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                COALESCE(SUM(CASE WHEN MONTH(t.date_of_payment) = :month AND YEAR(t.date_of_payment) = :year THEN t.amount_paid END), 0) as current_amount,
                COALESCE(SUM(CASE WHEN MONTH(t.date_of_payment) = :prev_month AND YEAR(t.date_of_payment) = :prev_year THEN t.amount_paid END), 0) as previous_amount
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
            WHERE a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            HAVING current_amount > 0 OR previous_amount > 0
            ORDER BY current_amount DESC
        ");
        
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $this->db->bind(':prev_month', $prev_month);
        $this->db->bind(':prev_year', $prev_year);
        
        $performance_data = $this->db->resultSet();
        
        foreach ($performance_data as $data) {
            $variance = $data['previous_amount'] > 0 ? 
                (($data['current_amount'] - $data['previous_amount']) / $data['previous_amount']) * 100 : 0;
            
            if ($variance < -20) {
                $recommendations[] = [
                    'category' => 'Performance',
                    'priority' => 'High',
                    'title' => 'Critical Decline in ' . $data['income_line'],
                    'description' => 'This income line has declined by ' . number_format(abs($variance), 1) . '% compared to last month.',
                    'action' => 'Immediate investigation required. Review operational processes, staff performance, and market conditions.',
                    'timeline' => 'Within 1 week',
                    'impact' => 'High'
                ];
            } elseif ($variance > 30) {
                $recommendations[] = [
                    'category' => 'Performance',
                    'priority' => 'Medium',
                    'title' => 'Exceptional Growth in ' . $data['income_line'],
                    'description' => 'This income line has grown by ' . number_format($variance, 1) . '% compared to last month.',
                    'action' => 'Analyze success factors and consider replicating strategies across other income lines.',
                    'timeline' => 'Within 2 weeks',
                    'impact' => 'Medium'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Operational recommendations
     */
    private function getOperationalRecommendations($month, $year) {
        $recommendations = [];
        
        // Check for processing delays
        $this->db->query("
            SELECT COUNT(*) as pending_count
            FROM account_general_transaction_new 
            WHERE approval_status = 'Pending'
            AND MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $pending_result = $this->db->single();
        
        if ($pending_result['pending_count'] > 50) {
            $recommendations[] = [
                'category' => 'Operations',
                'priority' => 'High',
                'title' => 'High Volume of Pending Approvals',
                'description' => 'There are ' . $pending_result['pending_count'] . ' transactions pending approval.',
                'action' => 'Review approval workflow and consider additional approval staff or process automation.',
                'timeline' => 'Within 3 days',
                'impact' => 'High'
            ];
        }
        
        // Check for weekend performance
        $this->db->query("
            SELECT 
                AVG(CASE WHEN DAYOFWEEK(date_of_payment) IN (1,7) THEN amount_paid END) as weekend_avg,
                AVG(CASE WHEN DAYOFWEEK(date_of_payment) NOT IN (1,7) THEN amount_paid END) as weekday_avg
            FROM account_general_transaction_new 
            WHERE MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $weekend_result = $this->db->single();
        
        if ($weekend_result['weekend_avg'] > 0 && $weekend_result['weekday_avg'] > 0) {
            $weekend_ratio = $weekend_result['weekend_avg'] / $weekend_result['weekday_avg'];
            
            if ($weekend_ratio < 0.5) {
                $recommendations[] = [
                    'category' => 'Operations',
                    'priority' => 'Medium',
                    'title' => 'Low Weekend Performance',
                    'description' => 'Weekend collections are significantly lower than weekday collections.',
                    'action' => 'Consider weekend staffing adjustments or promotional activities to boost weekend revenue.',
                    'timeline' => 'Within 1 month',
                    'impact' => 'Medium'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Strategic recommendations
     */
    private function getStrategicRecommendations($month, $year) {
        $recommendations = [];
        
        // Revenue concentration analysis
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                SUM(t.amount_paid) as total_amount
            FROM accounts a
            JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
            WHERE MONTH(t.date_of_payment) = :month 
            AND YEAR(t.date_of_payment) = :year
            GROUP BY a.acct_id, a.acct_desc
            ORDER BY total_amount DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $revenue_data = $this->db->resultSet();
        
        if (!empty($revenue_data)) {
            $total_revenue = array_sum(array_column($revenue_data, 'total_amount'));
            $top_line_percentage = ($revenue_data[0]['total_amount'] / $total_revenue) * 100;
            
            if ($top_line_percentage > 60) {
                $recommendations[] = [
                    'category' => 'Strategy',
                    'priority' => 'Medium',
                    'title' => 'High Revenue Concentration Risk',
                    'description' => $revenue_data[0]['income_line'] . ' accounts for ' . number_format($top_line_percentage, 1) . '% of total revenue.',
                    'action' => 'Diversify revenue streams to reduce dependency on single income line. Develop growth strategies for underperforming lines.',
                    'timeline' => 'Within 3 months',
                    'impact' => 'High'
                ];
            }
        }
        
        // Growth opportunity analysis
        $this->db->query("
            SELECT COUNT(DISTINCT income_line) as active_lines
            FROM account_general_transaction_new 
            WHERE MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
            AND amount_paid > 0
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $active_lines = $this->db->single();
        
        $this->db->query("SELECT COUNT(*) as total_lines FROM accounts WHERE active = 'Yes'");
        $total_lines = $this->db->single();
        
        $utilization_rate = ($active_lines['active_lines'] / $total_lines['total_lines']) * 100;
        
        if ($utilization_rate < 70) {
            $recommendations[] = [
                'category' => 'Strategy',
                'priority' => 'Medium',
                'title' => 'Underutilized Income Lines',
                'description' => 'Only ' . number_format($utilization_rate, 1) . '% of available income lines are generating revenue.',
                'action' => 'Investigate inactive income lines and develop activation strategies. Consider market research for new opportunities.',
                'timeline' => 'Within 2 months',
                'impact' => 'Medium'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Risk management recommendations
     */
    private function getRiskRecommendations($month, $year) {
        $recommendations = [];
        
        // Check for unusual patterns
        $this->db->query("
            SELECT 
                DATE(date_of_payment) as payment_date,
                COUNT(*) as transaction_count,
                SUM(amount_paid) as daily_total
            FROM account_general_transaction_new 
            WHERE MONTH(date_of_payment) = :month 
            AND YEAR(date_of_payment) = :year
            GROUP BY DATE(date_of_payment)
            ORDER BY daily_total DESC
            LIMIT 5
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $daily_data = $this->db->resultSet();
        
        if (!empty($daily_data)) {
            $avg_daily = array_sum(array_column($daily_data, 'daily_total')) / count($daily_data);
            $max_daily = max(array_column($daily_data, 'daily_total'));
            
            if ($max_daily > $avg_daily * 3) {
                $recommendations[] = [
                    'category' => 'Risk Management',
                    'priority' => 'Medium',
                    'title' => 'Unusual Daily Revenue Spike Detected',
                    'description' => 'Significant deviation from normal daily patterns detected.',
                    'action' => 'Review high-volume days for data accuracy and investigate potential causes of unusual spikes.',
                    'timeline' => 'Within 1 week',
                    'impact' => 'Medium'
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Get implementation roadmap
     */
    public function getImplementationRoadmap($recommendations) {
        $roadmap = [
            'immediate' => [],
            'short_term' => [],
            'medium_term' => [],
            'long_term' => []
        ];
        
        foreach ($recommendations as $rec) {
            switch ($rec['timeline']) {
                case 'Within 1 week':
                case 'Within 3 days':
                    $roadmap['immediate'][] = $rec;
                    break;
                case 'Within 2 weeks':
                case 'Within 1 month':
                    $roadmap['short_term'][] = $rec;
                    break;
                case 'Within 2 months':
                case 'Within 3 months':
                    $roadmap['medium_term'][] = $rec;
                    break;
                default:
                    $roadmap['long_term'][] = $rec;
            }
        }
        
        return $roadmap;
    }
}

$engine = new RecommendationEngine();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

$recommendations = $engine->generateRecommendations($month, $year);
$roadmap = $engine->getImplementationRoadmap($recommendations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Strategic Recommendations</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="mpr.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to MPR</a>
                    <h1 class="text-xl font-bold text-gray-900">Strategic Recommendations</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">High Priority</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo count(array_filter($recommendations, function($r) { return $r['priority'] === 'High'; })); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Medium Priority</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?php echo count(array_filter($recommendations, function($r) { return $r['priority'] === 'Medium'; })); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Actions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($recommendations); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Immediate Actions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo count($roadmap['immediate']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Implementation Roadmap -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Immediate Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>
                    Immediate Actions (1-7 days)
                </h3>
                <?php if (!empty($roadmap['immediate'])): ?>
                    <div class="space-y-4">
                        <?php foreach ($roadmap['immediate'] as $rec): ?>
                        <div class="border-l-4 border-red-500 pl-4">
                            <h4 class="font-medium text-gray-900"><?php echo $rec['title']; ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?php echo $rec['action']; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">No immediate actions required.</p>
                <?php endif; ?>
            </div>

            <!-- Short-term Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                    Short-term Actions (2 weeks - 1 month)
                </h3>
                <?php if (!empty($roadmap['short_term'])): ?>
                    <div class="space-y-4">
                        <?php foreach ($roadmap['short_term'] as $rec): ?>
                        <div class="border-l-4 border-yellow-500 pl-4">
                            <h4 class="font-medium text-gray-900"><?php echo $rec['title']; ?></h4>
                            <p class="text-sm text-gray-600 mt-1"><?php echo $rec['action']; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">No short-term actions identified.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Recommendations Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">All Recommendations</h3>
            </div>
            
            <?php if (!empty($recommendations)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action Required</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Timeline</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Impact</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recommendations as $rec): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $rec['category'] === 'Performance' ? 'bg-blue-100 text-blue-800' : 
                                              ($rec['category'] === 'Operations' ? 'bg-green-100 text-green-800' : 
                                              ($rec['category'] === 'Strategy' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800')); ?>">
                                    <?php echo $rec['category']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div>
                                    <div class="font-medium"><?php echo $rec['title']; ?></div>
                                    <div class="text-gray-500 text-xs mt-1"><?php echo $rec['description']; ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo $rec['action']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $rec['priority'] === 'High' ? 'bg-red-100 text-red-800' : 
                                              ($rec['priority'] === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'); ?>">
                                    <?php echo $rec['priority']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?php echo $rec['timeline']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $rec['impact'] === 'High' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo $rec['impact']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-6 text-center">
                <p class="text-gray-500">No specific recommendations at this time. System performance appears to be within normal parameters.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Action Plan Template -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Action Plan Template</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Implementation Steps</h4>
                    <ol class="text-sm text-gray-600 space-y-1 list-decimal list-inside">
                        <li>Review and prioritize all recommendations</li>
                        <li>Assign responsible team members for each action</li>
                        <li>Set specific deadlines and milestones</li>
                        <li>Allocate necessary resources and budget</li>
                        <li>Establish monitoring and review processes</li>
                    </ol>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Success Metrics</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Revenue growth targets achieved</li>
                        <li>• Operational efficiency improvements</li>
                        <li>• Risk mitigation measures implemented</li>
                        <li>• Strategic objectives advanced</li>
                        <li>• Stakeholder satisfaction maintained</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>