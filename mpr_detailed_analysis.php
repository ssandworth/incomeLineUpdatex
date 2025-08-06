<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class DetailedAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get variance analysis
     */
    public function getVarianceAnalysis($month, $year) {
        // Get current month data by income line
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                COALESCE(SUM(t.amount_paid), 0) as current_amount,
                COUNT(t.id) as current_transactions
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND MONTH(t.date_of_payment) = :month 
                AND YEAR(t.date_of_payment) = :year
            WHERE a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            ORDER BY current_amount DESC
        ");
        
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $current_data = $this->db->resultSet();
        
        // Get previous month data
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        
        $this->db->query("
            SELECT 
                a.acct_desc as income_line,
                COALESCE(SUM(t.amount_paid), 0) as previous_amount,
                COUNT(t.id) as previous_transactions
            FROM accounts a
            LEFT JOIN account_general_transaction_new t ON a.acct_id = t.credit_account
                AND MONTH(t.date_of_payment) = :prev_month 
                AND YEAR(t.date_of_payment) = :prev_year
            WHERE a.active = 'Yes'
            GROUP BY a.acct_id, a.acct_desc
            ORDER BY previous_amount DESC
        ");
        
        $this->db->bind(':prev_month', $prev_month);
        $this->db->bind(':prev_year', $prev_year);
        $previous_data = $this->db->resultSet();
        
        // Combine data for variance analysis
        $variance_data = [];
        $prev_lookup = [];
        
        foreach ($previous_data as $prev) {
            $prev_lookup[$prev['income_line']] = $prev;
        }
        
        foreach ($current_data as $current) {
            $prev = $prev_lookup[$current['income_line']] ?? ['previous_amount' => 0, 'previous_transactions' => 0];
            
            $variance_amount = $current['current_amount'] - $prev['previous_amount'];
            $variance_percentage = $prev['previous_amount'] > 0 ? 
                (($variance_amount / $prev['previous_amount']) * 100) : 
                ($current['current_amount'] > 0 ? 100 : 0);
            
            $variance_data[] = [
                'income_line' => $current['income_line'],
                'current_amount' => $current['current_amount'],
                'previous_amount' => $prev['previous_amount'],
                'variance_amount' => $variance_amount,
                'variance_percentage' => $variance_percentage,
                'current_transactions' => $current['current_transactions'],
                'previous_transactions' => $prev['previous_transactions']
            ];
        }
        
        return $variance_data;
    }
    
    /**
     * Get performance insights
     */
    public function getPerformanceInsights($month, $year) {
        $insights = [];
        
        // Get declining income lines
        $variance_data = $this->getVarianceAnalysis($month, $year);
        $declining = array_filter($variance_data, function($item) {
            return $item['variance_percentage'] < -10; // More than 10% decline
        });
        
        if (!empty($declining)) {
            $insights[] = [
                'type' => 'warning',
                'title' => 'Declining Performance Alert',
                'message' => count($declining) . ' income lines showing significant decline (>10%)',
                'action' => 'Review operational efficiency and market conditions'
            ];
        }
        
        // Get high performers
        $high_performers = array_filter($variance_data, function($item) {
            return $item['variance_percentage'] > 20; // More than 20% growth
        });
        
        if (!empty($high_performers)) {
            $insights[] = [
                'type' => 'success',
                'title' => 'High Growth Opportunities',
                'message' => count($high_performers) . ' income lines showing exceptional growth (>20%)',
                'action' => 'Analyze success factors for replication across other lines'
            ];
        }
        
        // Check for zero performance lines
        $zero_performers = array_filter($variance_data, function($item) {
            return $item['current_amount'] == 0 && $item['previous_amount'] > 0;
        });
        
        if (!empty($zero_performers)) {
            $insights[] = [
                'type' => 'danger',
                'title' => 'Critical Performance Issue',
                'message' => count($zero_performers) . ' income lines with zero collections this month',
                'action' => 'Immediate investigation required for operational issues'
            ];
        }
        
        return $insights;
    }
}

$analyzer = new DetailedAnalyzer();
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$month_name = date('F', mktime(0, 0, 0, $month, 1));

$variance_data = $analyzer->getVarianceAnalysis($month, $year);
$insights = $analyzer->getPerformanceInsights($month, $year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Detailed Analysis</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Detailed Performance Analysis</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Insights Section -->
        <?php if (!empty($insights)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Performance Insights</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($insights as $insight): ?>
                <div class="bg-white rounded-lg shadow-md p-6 border-l-4 
                    <?php echo $insight['type'] === 'success' ? 'border-green-500' : 
                              ($insight['type'] === 'warning' ? 'border-yellow-500' : 'border-red-500'); ?>">
                    <h3 class="font-semibold text-gray-900 mb-2"><?php echo $insight['title']; ?></h3>
                    <p class="text-sm text-gray-600 mb-3"><?php echo $insight['message']; ?></p>
                    <p class="text-xs text-gray-500 italic"><?php echo $insight['action']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Variance Analysis Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Variance Analysis</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Previous Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (₦)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance (%)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($variance_data as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $item['income_line']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($item['current_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($item['previous_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right 
                                <?php echo $item['variance_amount'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $item['variance_amount'] >= 0 ? '+' : ''; ?>₦<?php echo number_format($item['variance_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium
                                <?php echo $item['variance_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $item['variance_percentage'] >= 0 ? '+' : ''; ?><?php echo number_format($item['variance_percentage'], 1); ?>%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($item['variance_percentage'] > 20): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Excellent</span>
                                <?php elseif ($item['variance_percentage'] > 0): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Good</span>
                                <?php elseif ($item['variance_percentage'] > -10): ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Fair</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Poor</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Action Recommendations -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recommended Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Immediate Actions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Review declining income lines for operational issues</li>
                        <li>• Investigate zero-performance areas</li>
                        <li>• Implement corrective measures for underperforming lines</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Strategic Actions</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Analyze high-performing lines for best practices</li>
                        <li>• Consider resource reallocation to growth areas</li>
                        <li>• Develop improvement plans for consistent performers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>