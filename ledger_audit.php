<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class LedgerAuditTrail {
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
     * Get audit trail summary
     */
    public function getAuditSummary($table_name, $date_from = null, $date_to = null) {
        $date_condition = "";
        if ($date_from && $date_to) {
            $date_condition = "AND date BETWEEN '{$date_from}' AND '{$date_to}'";
        } else {
            $date_condition = "AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
        
        // Get transaction summary by status
        $this->db->query("
            SELECT 
                approval_status,
                COUNT(*) as count,
                SUM(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as total_amount
            FROM {$table_name}
            WHERE 1=1 {$date_condition}
            GROUP BY approval_status
        ");
        $status_summary = $this->db->resultSet();
        
        // Get daily activity
        $this->db->query("
            SELECT 
                DATE(date) as activity_date,
                COUNT(*) as daily_transactions,
                SUM(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as daily_amount
            FROM {$table_name}
            WHERE 1=1 {$date_condition}
            GROUP BY DATE(date)
            ORDER BY activity_date DESC
            LIMIT 30
        ");
        $daily_activity = $this->db->resultSet();
        
        return [
            'status_summary' => $status_summary,
            'daily_activity' => $daily_activity
        ];
    }
    
    /**
     * Get recent modifications
     */
    public function getRecentModifications($table_name, $limit = 100) {
        $this->db->query("
            SELECT *
            FROM {$table_name}
            WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY id DESC
            LIMIT :limit
        ");
        $this->db->bind(':limit', $limit);
        return $this->db->resultSet();
    }
    
    /**
     * Get user activity analysis
     */
    public function getUserActivity($table_name) {
        // This would typically join with a user activity log table
        // For now, we'll analyze based on available data
        $this->db->query("
            SELECT 
                'System' as user_name,
                COUNT(*) as transaction_count,
                SUM(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as total_amount,
                MIN(date) as first_transaction,
                MAX(date) as last_transaction
            FROM {$table_name}
            WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        return $this->db->resultSet();
    }
    
    /**
     * Get compliance checks
     */
    public function getComplianceChecks($table_name) {
        $checks = [];
        
        // Check for transactions without proper documentation
        $this->db->query("
            SELECT COUNT(*) as undocumented_count
            FROM {$table_name}
            WHERE (receipt_no IS NULL OR receipt_no = '')
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $undocumented = $this->db->single();
        
        $checks[] = [
            'check_name' => 'Documentation Compliance',
            'status' => $undocumented['undocumented_count'] == 0 ? 'PASS' : 'FAIL',
            'details' => $undocumented['undocumented_count'] . ' transactions without receipt numbers',
            'severity' => $undocumented['undocumented_count'] > 0 ? 'medium' : 'low'
        ];
        
        // Check for unusual transaction times (if timestamp available)
        $this->db->query("
            SELECT COUNT(*) as weekend_count
            FROM {$table_name}
            WHERE DAYOFWEEK(date) IN (1, 7)
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $weekend_transactions = $this->db->single();
        
        $checks[] = [
            'check_name' => 'Business Hours Compliance',
            'status' => $weekend_transactions['weekend_count'] == 0 ? 'PASS' : 'REVIEW',
            'details' => $weekend_transactions['weekend_count'] . ' weekend transactions found',
            'severity' => $weekend_transactions['weekend_count'] > 5 ? 'medium' : 'low'
        ];
        
        // Check for sequential receipt numbers (basic check)
        $this->db->query("
            SELECT 
                receipt_no,
                COUNT(*) as duplicate_receipts
            FROM {$table_name}
            WHERE receipt_no IS NOT NULL 
            AND receipt_no != ''
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY receipt_no
            HAVING COUNT(*) > 1
        ");
        $duplicate_receipts = $this->db->resultSet();
        
        $checks[] = [
            'check_name' => 'Receipt Number Integrity',
            'status' => empty($duplicate_receipts) ? 'PASS' : 'FAIL',
            'details' => count($duplicate_receipts) . ' duplicate receipt numbers found',
            'severity' => !empty($duplicate_receipts) ? 'high' : 'low'
        ];
        
        return $checks;
    }
    
    /**
     * Get risk assessment
     */
    public function getRiskAssessment($table_name) {
        $risks = [];
        
        // High-value transactions
        $this->db->query("
            SELECT 
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as avg_amount,
                MAX(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as max_amount
            FROM {$table_name}
            WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $amount_stats = $this->db->single();
        
        if ($amount_stats['max_amount'] > ($amount_stats['avg_amount'] * 10)) {
            $risks[] = [
                'risk_type' => 'High Value Transactions',
                'level' => 'Medium',
                'description' => 'Transactions significantly above average detected',
                'recommendation' => 'Review high-value transactions for authorization'
            ];
        }
        
        // Frequency analysis
        $this->db->query("
            SELECT 
                COUNT(DISTINCT DATE(date)) as active_days,
                COUNT(*) as total_transactions
            FROM {$table_name}
            WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $frequency = $this->db->single();
        
        $avg_daily = $frequency['active_days'] > 0 ? $frequency['total_transactions'] / $frequency['active_days'] : 0;
        
        if ($avg_daily > 100) {
            $risks[] = [
                'risk_type' => 'High Transaction Volume',
                'level' => 'Low',
                'description' => 'High daily transaction volume detected',
                'recommendation' => 'Monitor for processing capacity and accuracy'
            ];
        }
        
        return $risks;
    }
}

$auditor = new LedgerAuditTrail();
$account_id = $_GET['acct_id'] ?? null;

if (!$account_id) {
    header('Location: ledger.php');
    exit;
}

$account_info = $auditor->getAccountInfo($account_id);
if (!$account_info) {
    header('Location: ledger.php');
    exit;
}

$table_name = $account_info['acct_table_name'];

// Handle date filtering
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;

$audit_summary = $auditor->getAuditSummary($table_name, $date_from, $date_to);
$recent_modifications = $auditor->getRecentModifications($table_name);
$user_activity = $auditor->getUserActivity($table_name);
$compliance_checks = $auditor->getComplianceChecks($table_name);
$risk_assessment = $auditor->getRiskAssessment($table_name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Audit Trail</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Audit Trail</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $account_info['acct_desc']; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Date Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <input type="hidden" name="acct_id" value="<?php echo $account_id; ?>">
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Apply Filter
                    </button>
                    
                    <a href="ledger_audit.php?acct_id=<?php echo $account_id; ?>" 
                       class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Compliance Checks -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Compliance Checks</h3>
            <div class="space-y-4">
                <?php foreach ($compliance_checks as $check): ?>
                <div class="flex items-center justify-between p-4 border rounded-lg">
                    <div class="flex items-center">
                        <div class="mr-3">
                            <?php if ($check['status'] === 'PASS'): ?>
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php elseif ($check['status'] === 'FAIL'): ?>
                                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900"><?php echo $check['check_name']; ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $check['details']; ?></p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="px-3 py-1 text-xs font-semibold rounded-full 
                            <?php echo $check['status'] === 'PASS' ? 'bg-green-100 text-green-800' : 
                                      ($check['status'] === 'FAIL' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                            <?php echo $check['status']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Risk Assessment -->
        <?php if (!empty($risk_assessment)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Assessment</h3>
            <div class="space-y-4">
                <?php foreach ($risk_assessment as $risk): ?>
                <div class="border-l-4 <?php echo $risk['level'] === 'High' ? 'border-red-500' : ($risk['level'] === 'Medium' ? 'border-yellow-500' : 'border-blue-500'); ?> pl-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-medium text-gray-900"><?php echo $risk['risk_type']; ?></h4>
                            <p class="text-sm text-gray-600 mb-1"><?php echo $risk['description']; ?></p>
                            <p class="text-sm text-blue-600 italic"><?php echo $risk['recommendation']; ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            <?php echo $risk['level'] === 'High' ? 'bg-red-100 text-red-800' : 
                                      ($risk['level'] === 'Medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800'); ?>">
                            <?php echo $risk['level']; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Transaction Status Distribution -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Status Distribution</h3>
                <div class="relative h-48">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Daily Activity Trend -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daily Activity Trend (Last 30 Days)</h3>
                <div class="relative h-48">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Modifications -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Recent Modifications (Last 7 Days)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($recent_modifications, 0, 20) as $modification): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('d/m/Y', strtotime($modification['date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $modification['receipt_no'] ?? '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo substr($modification['trans_desc'], 0, 50) . (strlen($modification['trans_desc']) > 50 ? '...' : ''); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format(($modification['debit_amount'] ?? 0) + ($modification['credit_amount'] ?? 0)); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $modification['approval_status'] === 'Approved' ? 'bg-green-100 text-green-800' : 
                                              ($modification['approval_status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                    <?php echo $modification['approval_status'] ?: 'Pending'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($audit_summary['status_summary']); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.approval_status || 'Pending'),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(59, 130, 246, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Activity Trend Chart
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        const activityData = <?php echo json_encode(array_reverse($audit_summary['daily_activity'])); ?>;
        
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: activityData.map(item => new Date(item.activity_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Transactions',
                    data: activityData.map(item => item.daily_transactions),
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
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>