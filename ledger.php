<?php
require_once 'Database.php';
require_once 'config.php';
require_once 'functions.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

class LedgerAnalyzer {
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
     * Get all active accounts for dropdown
     */
    public function getActiveAccounts() {
        $this->db->query("
            SELECT acct_id, acct_desc 
            FROM accounts 
            WHERE active = 'Yes' 
            ORDER BY acct_desc ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get ledger entries with date filtering
     */
    public function getLedgerEntries($table_name, $date_from = null, $date_to = null) {
        $query = "
            SELECT * FROM {$table_name} 
            WHERE approval_status = 'Approved'
        ";
        
        if ($date_from && $date_to) {
            $query .= " AND date BETWEEN :date_from AND :date_to";
        } else {
            $query .= " AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())";
        }
        
        $query .= " ORDER BY date DESC";
        
        $this->db->query($query);
        
        if ($date_from && $date_to) {
            $this->db->bind(':date_from', $date_from);
            $this->db->bind(':date_to', $date_to);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Get officer-specific ledger entries
     */
    public function getOfficerLedgerEntries($table_name, $officer_id, $date_from = null, $date_to = null) {
        $query = "
            SELECT l.*, t.remitting_staff, t.posting_officer_name
            FROM {$table_name} l
            LEFT JOIN account_general_transaction_new t ON l.id = t.id
            WHERE l.approval_status = 'Approved'
            AND t.remitting_id = :officer_id
        ";
        
        if ($date_from && $date_to) {
            $query .= " AND l.date BETWEEN :date_from AND :date_to";
        } else {
            $query .= " AND YEAR(l.date) = YEAR(NOW()) AND MONTH(l.date) = MONTH(NOW())";
        }
        
        $query .= " ORDER BY l.date DESC";
        
        $this->db->query($query);
        $this->db->bind(':officer_id', $officer_id);
        
        if ($date_from && $date_to) {
            $this->db->bind(':date_from', $date_from);
            $this->db->bind(':date_to', $date_to);
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Get officer performance summary for ledger
     */
    public function getOfficerLedgerSummary($table_name, $officer_id, $date_from = null, $date_to = null) {
        $query = "
            SELECT 
                COUNT(*) as transaction_count,
                SUM(COALESCE(l.credit_amount, 0)) as total_credits,
                SUM(COALESCE(l.debit_amount, 0)) as total_debits,
                AVG(COALESCE(l.credit_amount, 0)) as avg_credit,
                MAX(COALESCE(l.credit_amount, 0)) as max_credit,
                MIN(CASE WHEN l.credit_amount > 0 THEN l.credit_amount END) as min_credit
            FROM {$table_name} l
            LEFT JOIN account_general_transaction_new t ON l.id = t.id
            WHERE l.approval_status = 'Approved'
            AND t.remitting_id = :officer_id
        ";
        
        if ($date_from && $date_to) {
            $query .= " AND l.date BETWEEN :date_from AND :date_to";
        } else {
            $query .= " AND YEAR(l.date) = YEAR(NOW()) AND MONTH(l.date) = MONTH(NOW())";
        }
        
        $this->db->query($query);
        $this->db->bind(':officer_id', $officer_id);
        
        if ($date_from && $date_to) {
            $this->db->bind(':date_from', $date_from);
            $this->db->bind(':date_to', $date_to);
        }
        
        return $this->db->single();
    }
    
    /**
     * Get brought forward balance
     */
    public function getBroughtForwardBalance($table_name, $date_from = null) {
        $query = "
            SELECT 
                COALESCE(SUM(debit_amount), 0) as total_debits,
                COALESCE(SUM(credit_amount), 0) as total_credits
            FROM {$table_name} 
            WHERE approval_status = 'Approved'
        ";
        
        if ($date_from) {
            $query .= " AND date < :date_from";
            $this->db->query($query);
            $this->db->bind(':date_from', $date_from);
        } else {
            $query .= " AND date < DATE_FORMAT(NOW(), '%Y-%m-01')";
            $this->db->query($query);
        }
        
        $result = $this->db->single();
        return $result['total_debits'] - $result['total_credits'];
    }
    
    /**
     * Get period totals
     */
    public function getPeriodTotals($table_name, $date_from = null, $date_to = null) {
        $query = "
            SELECT 
                COALESCE(SUM(debit_amount), 0) as period_debits,
                COALESCE(SUM(credit_amount), 0) as period_credits
            FROM {$table_name} 
            WHERE approval_status = 'Approved'
        ";
        
        if ($date_from && $date_to) {
            $query .= " AND date BETWEEN :date_from AND :date_to";
            $this->db->query($query);
            $this->db->bind(':date_from', $date_from);
            $this->db->bind(':date_to', $date_to);
        } else {
            $query .= " AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())";
            $this->db->query($query);
        }
        
        return $this->db->single();
    }
    
    /**
     * Get ledger analytics
     */
    public function getLedgerAnalytics($table_name, $date_from = null, $date_to = null) {
        // Get current period data
        $current_totals = $this->getPeriodTotals($table_name, $date_from, $date_to);
        
        // Get previous period for comparison
        if ($date_from && $date_to) {
            $start_date = new DateTime($date_from);
            $end_date = new DateTime($date_to);
            $interval = $start_date->diff($end_date);
            
            $prev_end = clone $start_date;
            $prev_end->sub(new DateInterval('P1D'));
            $prev_start = clone $prev_end;
            $prev_start->sub($interval);
            
            $prev_totals = $this->getPeriodTotals($table_name, $prev_start->format('Y-m-d'), $prev_end->format('Y-m-d'));
        } else {
            // Previous month
            $prev_month = date('Y-m-d', strtotime('first day of last month'));
            $prev_month_end = date('Y-m-d', strtotime('last day of last month'));
            $prev_totals = $this->getPeriodTotals($table_name, $prev_month, $prev_month_end);
        }
        
        // Calculate growth rates
        $debit_growth = $prev_totals['period_debits'] > 0 ? 
            (($current_totals['period_debits'] - $prev_totals['period_debits']) / $prev_totals['period_debits']) * 100 : 0;
        
        $credit_growth = $prev_totals['period_credits'] > 0 ? 
            (($current_totals['period_credits'] - $prev_totals['period_credits']) / $prev_totals['period_credits']) * 100 : 0;
        
        // Get transaction frequency
        $this->db->query("
            SELECT COUNT(*) as transaction_count,
                   COUNT(DISTINCT DATE(date)) as active_days
            FROM {$table_name} 
            WHERE approval_status = 'Approved'
            " . ($date_from && $date_to ? 
                "AND date BETWEEN '{$date_from}' AND '{$date_to}'" : 
                "AND YEAR(date) = YEAR(NOW()) AND MONTH(date) = MONTH(NOW())")
        );
        $frequency_data = $this->db->single();
        
        return [
            'current_debits' => $current_totals['period_debits'],
            'current_credits' => $current_totals['period_credits'],
            'previous_debits' => $prev_totals['period_debits'],
            'previous_credits' => $prev_totals['period_credits'],
            'debit_growth' => $debit_growth,
            'credit_growth' => $credit_growth,
            'transaction_count' => $frequency_data['transaction_count'],
            'active_days' => $frequency_data['active_days'],
            'avg_transactions_per_day' => $frequency_data['active_days'] > 0 ? 
                $frequency_data['transaction_count'] / $frequency_data['active_days'] : 0
        ];
    }
    
    /**
     * Get risk indicators
     */
    public function getRiskIndicators($table_name, $account_info) {
        $indicators = [];
        
        // Check for unusual transaction patterns
        $this->db->query("
            SELECT 
                AVG(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as avg_amount,
                MAX(COALESCE(debit_amount, 0) + COALESCE(credit_amount, 0)) as max_amount,
                COUNT(*) as total_transactions
            FROM {$table_name} 
            WHERE approval_status = 'Approved'
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $pattern_data = $this->db->single();
        
        if ($pattern_data['max_amount'] > ($pattern_data['avg_amount'] * 5)) {
            $indicators[] = [
                'type' => 'warning',
                'title' => 'Unusual Transaction Size',
                'message' => 'Large transaction detected that is 5x above average',
                'severity' => 'medium'
            ];
        }
        
        // Check for account inactivity
        $this->db->query("
            SELECT MAX(date) as last_transaction
            FROM {$table_name} 
            WHERE approval_status = 'Approved'
        ");
        
        $activity_data = $this->db->single();
        $days_since_last = $activity_data['last_transaction'] ? 
            (strtotime('now') - strtotime($activity_data['last_transaction'])) / (60 * 60 * 24) : 999;
        
        if ($days_since_last > 7) {
            $indicators[] = [
                'type' => 'warning',
                'title' => 'Account Inactivity',
                'message' => 'No transactions in the last ' . round($days_since_last) . ' days',
                'severity' => 'low'
            ];
        }
        
        return $indicators;
    }
}

$analyzer = new LedgerAnalyzer();

// Handle account selection
$account_info = null;
$ledger_entries = [];
$bf_balance = 0;
$period_totals = ['period_debits' => 0, 'period_credits' => 0];
$analytics = null;
$risk_indicators = [];

$account_id = $_GET['acct_id'] ?? $_POST['ledger_account'] ?? null;
$date_from = null;
$date_to = null;
$officer_filter = $_GET['officer_id'] ?? null;

// Handle date filtering - maintain legacy format (dd/mm/yyyy)
if (isset($_GET['d1']) && isset($_GET['d2'])) {
    $d1_raw = urldecode($_GET['d1']);
    $d2_raw = urldecode($_GET['d2']);
    
    // Check if dates are in legacy format (dd/mm/yyyy) or new format (yyyy-mm-dd)
    if (strpos($d1_raw, '/') !== false) {
        // Legacy format: dd/mm/yyyy
        $date_from_parts = explode('/', $d1_raw);
        $date_to_parts = explode('/', $d2_raw);
        
        if (count($date_from_parts) == 3 && count($date_to_parts) == 3) {
            $date_from = $date_from_parts[2] . '-' . $date_from_parts[1] . '-' . $date_from_parts[0];
            $date_to = $date_to_parts[2] . '-' . $date_to_parts[1] . '-' . $date_to_parts[0];
        }
    } else {
        // New format: yyyy-mm-dd
        $date_from = $d1_raw;
        $date_to = $d2_raw;
    }
}

if ($account_id) {
    $account_info = $analyzer->getAccountInfo($account_id);
    
    if ($account_info) {
        $table_name = $account_info['acct_table_name'];
        
        if ($officer_filter) {
            $ledger_entries = $analyzer->getOfficerLedgerEntries($table_name, $officer_filter, $date_from, $date_to);
            $officer_summary = $analyzer->getOfficerLedgerSummary($table_name, $officer_filter, $date_from, $date_to);
        } else {
            $ledger_entries = $analyzer->getLedgerEntries($table_name, $date_from, $date_to);
        }
        
        $bf_balance = $analyzer->getBroughtForwardBalance($table_name, $date_from);
        $period_totals = $analyzer->getPeriodTotals($table_name, $date_from, $date_to);
        $analytics = $analyzer->getLedgerAnalytics($table_name, $date_from, $date_to);
        $risk_indicators = $analyzer->getRiskIndicators($table_name, $account_info);
    }
}

$active_accounts = $analyzer->getActiveAccounts();

// Get officers for filtering
$analyzer->db->query("
    SELECT DISTINCT 
        t.remitting_id,
        CASE 
            WHEN s.full_name IS NOT NULL THEN s.full_name
            ELSE so.full_name
        END as officer_name,
        CASE 
            WHEN s.department IS NOT NULL THEN s.department
            ELSE so.department
        END as department
    FROM account_general_transaction_new t
    LEFT JOIN staffs s ON t.remitting_id = s.user_id
    LEFT JOIN staffs_others so ON t.remitting_id = so.id
    WHERE (s.user_id IS NOT NULL OR so.id IS NOT NULL)
    AND t.date_of_payment >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ORDER BY officer_name ASC
");
$available_officers = $analyzer->db->resultSet();

// Calculate final balance
$final_balance = $bf_balance + $period_totals['period_debits'] - $period_totals['period_credits'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - General Ledger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#059669',
                        accent: '#dc2626',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - General Ledger</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Welcome, <?php echo $staff['full_name']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $staff['department']; ?>
                    </span>
                    <span class="text-sm text-gray-700"><?php echo $account_info['acct_desc']; ?></span>
                    <?php if ($officer_filter): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            Officer Filter Active
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Account Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h2 class="text-xl font-bold text-gray-900">
                        <?php if ($account_info): ?>
                            <?php echo $account_info['acct_desc']; ?> Ledger
                        <?php else: ?>
                            Select Account Ledger
                        <?php endif; ?>
                    </h2>
                    <?php if ($account_info): ?>
                        <p class="text-gray-600">
                            <?php echo $account_info['gl_category'] ?? 'General'; ?> Account | 
                            <?php echo $account_info['gl_code_name'] ?? 'Standard'; ?> | 
                            Code: <strong><?php echo $account_info['acct_code'] ?? 'N/A'; ?></strong>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Account Selection Form -->
                <form method="POST" class="flex flex-col sm:flex-row gap-4">
                    <select name="ledger_account" required
                            class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Choose a ledger to view</option>
                        <?php foreach ($active_accounts as $account): ?>
                            <option value="<?php echo $account['acct_id']; ?>" 
                                    <?php echo $account_id == $account['acct_id'] ? 'selected' : ''; ?>>
                                <?php echo $account['acct_desc']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" name="btn_view_ledger"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        View Ledger
                    </button>
                </form>
            </div>
        </div>

        <?php if (!$account_info): ?>
        <!-- No Account Selected -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-yellow-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div>
                    <h3 class="text-lg font-medium text-yellow-800">No Account Selected</h3>
                    <p class="text-yellow-700">Please choose a ledger account to display the transactions and analytics.</p>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Analytics Cards -->
        <?php if ($analytics): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Credits</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['current_credits']); ?></p>
                        <p class="text-xs <?php echo $analytics['credit_growth'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $analytics['credit_growth'] >= 0 ? '+' : ''; ?><?php echo number_format($analytics['credit_growth'], 1); ?>% from previous period
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Debits</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['current_debits']); ?></p>
                        <p class="text-xs <?php echo $analytics['debit_growth'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $analytics['debit_growth'] >= 0 ? '+' : ''; ?><?php echo number_format($analytics['debit_growth'], 1); ?>% from previous period
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
                        <p class="text-sm font-medium text-gray-500">Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($analytics['transaction_count']); ?></p>
                        <p class="text-xs text-gray-500">
                            <?php echo number_format($analytics['avg_transactions_per_day'], 1); ?> avg per day
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Net Balance</p>
                        <p class="text-2xl font-bold <?php echo $final_balance >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            ₦<?php echo number_format(abs($final_balance)); ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php echo $final_balance >= 0 ? 'Credit' : 'Debit'; ?> Balance
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Officer Performance Summary (if officer filter is active) -->
        <?php if ($officer_filter && isset($officer_summary)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Officer Performance Summary</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $officer_summary['transaction_count']; ?></div>
                    <div class="text-sm text-gray-500">Transactions</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">₦<?php echo number_format($officer_summary['total_credits']); ?></div>
                    <div class="text-sm text-gray-500">Total Credits</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600">₦<?php echo number_format($officer_summary['avg_credit']); ?></div>
                    <div class="text-sm text-gray-500">Average per Transaction</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">₦<?php echo number_format($officer_summary['max_credit']); ?></div>
                    <div class="text-sm text-gray-500">Highest Transaction</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Risk Indicators -->
        <?php if (!empty($risk_indicators)): ?>
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Risk Indicators</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($risk_indicators as $indicator): ?>
                <div class="bg-white rounded-lg shadow-md p-4 border-l-4 
                    <?php echo $indicator['type'] === 'warning' ? 'border-yellow-500' : 'border-red-500'; ?>">
                    <h4 class="font-medium text-gray-900"><?php echo $indicator['title']; ?></h4>
                    <p class="text-sm text-gray-600 mt-1"><?php echo $indicator['message']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Decision Making Tools -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Decision Making Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="ledger_analysis.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Detailed Analysis
                </a>
                
                <a href="ledger_trends.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Trend Analysis
                </a>
                
                <a href="ledger_reconciliation.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Reconciliation
                </a>
                
                <a href="ledger_audit.php?acct_id=<?php echo $account_id; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Audit Trail
                </a>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <input type="hidden" name="acct_id" value="<?php echo $account_id; ?>">
                <input type="hidden" name="officer_id" value="<?php echo $officer_filter; ?>">
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="text" name="d1" placeholder="dd/mm/yyyy"
                           value="<?php echo $date_from ? date('d/m/Y', strtotime($date_from)) : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="text" name="d2" placeholder="dd/mm/yyyy"
                           value="<?php echo $date_to ? date('d/m/Y', strtotime($date_to)) : ''; ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Officer</label>
                    <select name="officer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Officers</option>
                        <?php foreach ($available_officers as $officer): ?>
                            <option value="<?php echo $officer['remitting_id']; ?>" 
                                    <?php echo $officer_filter == $officer['remitting_id'] ? 'selected' : ''; ?>>
                                <?php echo $officer['officer_name']; ?> - <?php echo $officer['department']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Filter Range
                    </button>
                    
                    <a href="ledger.php?acct_id=<?php echo $account_id; ?>" 
                       class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Current Month
                    </a>
                </div>
            </form>
        </div>

        <!-- Ledger Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Ledger Entries
                    <?php if ($date_from && $date_to): ?>
                        (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)
                    <?php else: ?>
                        (Current Month)
                    <?php endif; ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <?php if ($officer_filter): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit (₦)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit (₦)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance (₦)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- Brought Forward Balance -->
                        <tr class="bg-blue-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">1.</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">-</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">-</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">B/F (Brought Forward)</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                <?php echo number_format(abs($bf_balance), 2); ?>
                            </td>
                        </tr>
                        
                        <!-- Ledger Entries -->
                        <?php 
                        $running_balance = $bf_balance;
                        $counter = 2;
                        foreach ($ledger_entries as $entry): 
                            $running_balance += ($entry['debit_amount'] ?? 0) - ($entry['credit_amount'] ?? 0);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo $counter++; ?>.</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('d/m/Y', strtotime($entry['date'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $entry['receipt_no'] ?? '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <a href="transaction_details.php?txref=<?php echo $entry['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <?php echo ucwords(strtolower($entry['trans_desc'])); ?>
                                </a>
                            </td>
                            <?php if ($officer_filter): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo $entry['remitting_staff'] ?? 'N/A'; ?>
                                </td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo $entry['debit_amount'] ? number_format($entry['debit_amount'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo $entry['credit_amount'] ? number_format($entry['credit_amount'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo number_format(abs($running_balance), 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="<?php echo $officer_filter ? '5' : '4'; ?>" class="px-6 py-3 text-left text-sm font-medium text-gray-900">TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-red-600">
                                <?php echo number_format($period_totals['period_debits'], 2); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-green-600">
                                <?php echo number_format($period_totals['period_credits'], 2); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                <?php echo number_format(abs($final_balance), 2); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <script>
        // Auto-submit form when account is selected
        document.querySelector('select[name="ledger_account"]').addEventListener('change', function() {
            if (this.value) {
                window.location.href = 'ledger.php?acct_id=' + this.value;
            }
        });
        
        // Date format validation for legacy format
        function validateDateFormat(input) {
            const datePattern = /^(\d{2})\/(\d{2})\/(\d{4})$/;
            return datePattern.test(input.value);
        }
        
        document.querySelector('input[name="d1"]').addEventListener('blur', function() {
            if (this.value && !validateDateFormat(this)) {
                alert('Please use dd/mm/yyyy format');
                this.focus();
            }
        });
        
        document.querySelector('input[name="d2"]').addEventListener('blur', function() {
            if (this.value && !validateDateFormat(this)) {
                alert('Please use dd/mm/yyyy format');
                this.focus();
            }
        });
    </script>
</body>
</html>