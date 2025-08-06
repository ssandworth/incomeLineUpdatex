<?php
require_once 'Database.php';
require_once 'config.php';

session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class LedgerReconciliation {
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
     * Get reconciliation summary
     */
    public function getReconciliationSummary($table_name, $date_from = null, $date_to = null) {
        $date_condition = "";
        if ($date_from && $date_to) {
            $date_condition = "AND date BETWEEN '{$date_from}' AND '{$date_to}'";
        } else {
            $date_condition = "AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())";
        }
        
        // Get approved transactions
        $this->db->query("
            SELECT 
                COUNT(*) as approved_count,
                SUM(COALESCE(debit_amount, 0)) as approved_debits,
                SUM(COALESCE(credit_amount, 0)) as approved_credits
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            {$date_condition}
        ");
        $approved = $this->db->single();
        
        // Get pending transactions
        $this->db->query("
            SELECT 
                COUNT(*) as pending_count,
                SUM(COALESCE(debit_amount, 0)) as pending_debits,
                SUM(COALESCE(credit_amount, 0)) as pending_credits
            FROM {$table_name}
            WHERE approval_status = 'Pending'
            {$date_condition}
        ");
        $pending = $this->db->single();
        
        // Get rejected transactions
        $this->db->query("
            SELECT 
                COUNT(*) as rejected_count,
                SUM(COALESCE(debit_amount, 0)) as rejected_debits,
                SUM(COALESCE(credit_amount, 0)) as rejected_credits
            FROM {$table_name}
            WHERE approval_status = 'Rejected'
            {$date_condition}
        ");
        $rejected = $this->db->single();
        
        return [
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'total_transactions' => $approved['approved_count'] + $pending['pending_count'] + $rejected['rejected_count']
        ];
    }
    
    /**
     * Get unreconciled items
     */
    public function getUnreconciledItems($table_name, $limit = 50) {
        $this->db->query("
            SELECT *
            FROM {$table_name}
            WHERE approval_status IN ('Pending', '')
            ORDER BY date DESC
            LIMIT :limit
        ");
        $this->db->bind(':limit', $limit);
        return $this->db->resultSet();
    }
    
    /**
     * Get duplicate transactions
     */
    public function getDuplicateTransactions($table_name) {
        $this->db->query("
            SELECT 
                receipt_no,
                COUNT(*) as duplicate_count,
                GROUP_CONCAT(id) as transaction_ids,
                SUM(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as total_amount
            FROM {$table_name}
            WHERE receipt_no IS NOT NULL 
            AND receipt_no != ''
            AND date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY receipt_no
            HAVING COUNT(*) > 1
            ORDER BY duplicate_count DESC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get balance verification
     */
    public function getBalanceVerification($table_name, $date_to = null) {
        $date_condition = $date_to ? "AND date <= '{$date_to}'" : "";
        
        $this->db->query("
            SELECT 
                SUM(COALESCE(debit_amount, 0)) as total_debits,
                SUM(COALESCE(credit_amount, 0)) as total_credits,
                (SUM(COALESCE(debit_amount, 0)) - SUM(COALESCE(credit_amount, 0))) as calculated_balance
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            {$date_condition}
        ");
        
        $calculated = $this->db->single();
        
        // Get the last recorded balance
        $this->db->query("
            SELECT balance as recorded_balance
            FROM {$table_name}
            WHERE approval_status = 'Approved'
            " . ($date_to ? "AND date <= '{$date_to}'" : "") . "
            ORDER BY date DESC, id DESC
            LIMIT 1
        ");
        
        $last_record = $this->db->single();
        $recorded_balance = $last_record['recorded_balance'] ?? 0;
        
        return [
            'calculated_balance' => $calculated['calculated_balance'],
            'recorded_balance' => $recorded_balance,
            'variance' => $calculated['calculated_balance'] - $recorded_balance,
            'total_debits' => $calculated['total_debits'],
            'total_credits' => $calculated['total_credits']
        ];
    }
    
    /**
     * Get reconciliation exceptions
     */
    public function getReconciliationExceptions($table_name) {
        $exceptions = [];
        
        // Missing receipt numbers
        $this->db->query("
            SELECT COUNT(*) as missing_receipts
            FROM {$table_name}
            WHERE (receipt_no IS NULL OR receipt_no = '')
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND approval_status = 'Approved'
        ");
        $missing_receipts = $this->db->single();
        
        if ($missing_receipts['missing_receipts'] > 0) {
            $exceptions[] = [
                'type' => 'Missing Receipt Numbers',
                'count' => $missing_receipts['missing_receipts'],
                'severity' => 'medium',
                'description' => 'Approved transactions without receipt numbers'
            ];
        }
        
        // Zero amount transactions
        $this->db->query("
            SELECT COUNT(*) as zero_amounts
            FROM {$table_name}
            WHERE (COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) = 0
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $zero_amounts = $this->db->single();
        
        if ($zero_amounts['zero_amounts'] > 0) {
            $exceptions[] = [
                'type' => 'Zero Amount Transactions',
                'count' => $zero_amounts['zero_amounts'],
                'severity' => 'high',
                'description' => 'Transactions with zero debit and credit amounts'
            ];
        }
        
        // Future dated transactions
        $this->db->query("
            SELECT COUNT(*) as future_dated
            FROM {$table_name}
            WHERE date > CURDATE()
        ");
        $future_dated = $this->db->single();
        
        if ($future_dated['future_dated'] > 0) {
            $exceptions[] = [
                'type' => 'Future Dated Transactions',
                'count' => $future_dated['future_dated'],
                'severity' => 'high',
                'description' => 'Transactions with future dates'
            ];
        }
        
        return $exceptions;
    }
}

$reconciler = new LedgerReconciliation();
$account_id = $_GET['acct_id'] ?? null;

if (!$account_id) {
    header('Location: ledger.php');
    exit;
}

$account_info = $reconciler->getAccountInfo($account_id);
if (!$account_info) {
    header('Location: ledger.php');
    exit;
}

$table_name = $account_info['acct_table_name'];

// Handle date filtering
$date_from = null;
$date_to = null;
if (isset($_GET['date_from']) && isset($_GET['date_to'])) {
    $date_from = $_GET['date_from'];
    $date_to = $_GET['date_to'];
}

$summary = $reconciler->getReconciliationSummary($table_name, $date_from, $date_to);
$unreconciled = $reconciler->getUnreconciledItems($table_name);
$duplicates = $reconciler->getDuplicateTransactions($table_name);
$balance_verification = $reconciler->getBalanceVerification($table_name, $date_to);
$exceptions = $reconciler->getReconciliationExceptions($table_name);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Ledger Reconciliation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="ledger.php?acct_id=<?php echo $account_id; ?>" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Ledger</a>
                    <h1 class="text-xl font-bold text-gray-900">Ledger Reconciliation</h1>
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
                    
                    <a href="ledger_reconciliation.php?acct_id=<?php echo $account_id; ?>" 
                       class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Reconciliation Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Approved</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['approved']['approved_count']); ?></p>
                        <p class="text-xs text-gray-500">₦<?php echo number_format($summary['approved']['approved_credits']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['pending']['pending_count']); ?></p>
                        <p class="text-xs text-gray-500">₦<?php echo number_format($summary['pending']['pending_credits']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Rejected</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['rejected']['rejected_count']); ?></p>
                        <p class="text-xs text-gray-500">₦<?php echo number_format($summary['rejected']['rejected_credits']); ?></p>
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
                        <p class="text-sm font-medium text-gray-500">Total</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($summary['total_transactions']); ?></p>
                        <p class="text-xs text-gray-500">All transactions</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Verification -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Balance Verification</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Calculated Balance</p>
                    <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($balance_verification['calculated_balance']); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Recorded Balance</p>
                    <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($balance_verification['recorded_balance']); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-sm font-medium text-gray-500">Variance</p>
                    <p class="text-2xl font-bold <?php echo $balance_verification['variance'] == 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        ₦<?php echo number_format(abs($balance_verification['variance'])); ?>
                    </p>
                    <?php if ($balance_verification['variance'] == 0): ?>
                        <p class="text-xs text-green-600">✓ Balanced</p>
                    <?php else: ?>
                        <p class="text-xs text-red-600">⚠ Variance detected</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Exceptions -->
        <?php if (!empty($exceptions)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Reconciliation Exceptions</h3>
            <div class="space-y-4">
                <?php foreach ($exceptions as $exception): ?>
                <div class="border-l-4 <?php echo $exception['severity'] === 'high' ? 'border-red-500' : 'border-yellow-500'; ?> pl-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h4 class="font-medium text-gray-900"><?php echo $exception['type']; ?></h4>
                            <p class="text-sm text-gray-600"><?php echo $exception['description']; ?></p>
                        </div>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            <?php echo $exception['severity'] === 'high' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                            <?php echo $exception['count']; ?> items
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Duplicate Transactions -->
        <?php if (!empty($duplicates)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Duplicate Transactions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Count</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($duplicates as $duplicate): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $duplicate['receipt_no']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    <?php echo $duplicate['duplicate_count']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($duplicate['total_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Investigate
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Unreconciled Items -->
        <?php if (!empty($unreconciled)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Unreconciled Items (Latest 50)</h3>
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
                        <?php foreach ($unreconciled as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('d/m/Y', strtotime($item['date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $item['receipt_no'] ?? '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <?php echo substr($item['trans_desc'], 0, 50) . (strlen($item['trans_desc']) > 50 ? '...' : ''); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format(($item['debit_amount'] ?? 0) + ($item['credit_amount'] ?? 0)); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                    <?php echo $item['approval_status'] ?: 'Pending'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>