<?php
require_once 'TransactionManager.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration - in real implementation this would come from session
$staff = [
    'user_id' => $_GET['staff_id'] ?? 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts',
    'level' => 'manager' // ce, fc, manager, staff
];

$manager = new TransactionManager();

// Get all Wealth Creation officers for quick access buttons
$wc_officers = $manager->getStaffByDepartment('Wealth Creation');
$account_officers = $manager->getStaffByDepartment('Accounts');

// Check permissions based on level and department
function hasPermission($action, $staff) {
    switch ($action) {
        case 'view':
            return in_array($staff['department'], ['Accounts', 'Wealth Creation', 'Audit/Inspections', 'FC']);
        case 'approve_fc':
            return $staff['level'] === 'fc' || $staff['level'] === 'ce';
        case 'verify_audit':
            return $staff['department'] === 'Audit/Inspections' || $staff['level'] === 'ce';
        case 'review_account':
            return $staff['department'] === 'Accounts' || $staff['level'] === 'ce';
        case 'delete':
            return $staff['level'] === 'ce' || 
                   ($staff['department'] === 'Accounts' && in_array($staff['level'], ['manager', 'ce']));
        case 'edit':
            return $staff['level'] === 'ce' || 
                   ($staff['department'] === 'Accounts' && in_array($staff['level'], ['manager', 'ce']));
        default:
            return false;
    }
}

if (!hasPermission('view', $staff)) {
    header('Location: index.php?error=no_permission');
    exit;
}

// Handle pagination
$page = $_GET['page'] ?? 1;
$per_page = 10; // Match legacy system

// Handle filtering
$date_from = null;
$date_to = null;
$status_filter = $_GET['status'] ?? 'pending'; // Default to pending like legacy
$staff_filter = $_GET['staff_id'] ?? null;

if (isset($_GET['d1']) && isset($_GET['d2'])) {
    $date_parts_from = explode('/', $_GET['d1']);
    $date_parts_to = explode('/', $_GET['d2']);
    
    if (count($date_parts_from) === 3 && count($date_parts_to) === 3) {
        $date_from = $date_parts_from[2] . '-' . $date_parts_from[1] . '-' . $date_parts_from[0];
        $date_to = $date_parts_to[2] . '-' . $date_parts_to[1] . '-' . $date_parts_to[0];
    }
}

// Handle search
$search_term = $_GET['search'] ?? '';
$transactions = [];
$total_count = 0;

if ($search_term) {
    $transactions = $manager->searchTransactions($search_term, $page, $per_page);
    $total_count = count($transactions);
} else {
    $transactions = $manager->getTransactions($page, $per_page, $date_from, $date_to, $status_filter, $staff_filter);
    $total_count = $manager->getTransactionCount($date_from, $date_to, $status_filter, $staff_filter);
}

$total_pages = ceil($total_count / $per_page);
$stats = $manager->getDashboardStats();

// Handle AJAX requests for approvals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($_POST['action']) {
        case 'review_approve':
            if (hasPermission('review_account', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $response = $manager->reviewApproveTransaction($transaction_id, $staff['user_id'], $staff['full_name']);
            } else {
                $response = ['success' => false, 'message' => 'No permission for this action'];
            }
            break;
            
        case 'review_decline':
            if (hasPermission('review_account', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $reason = $_POST['reason'] ?? '';
                $response = $manager->reviewDeclineTransaction($transaction_id, $staff['user_id'], $staff['full_name'], $reason);
            } else {
                $response = ['success' => false, 'message' => 'No permission for this action'];
            }
            break;
            
        case 'fc_approve':
            if (hasPermission('approve_fc', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $response = $manager->approveTransaction($transaction_id, $staff['user_id'], $staff['full_name'], 'FC');
            } else {
                $response = ['success' => false, 'message' => 'No permission for FC approval'];
            }
            break;
            
        case 'fc_decline':
            if (hasPermission('approve_fc', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $reason = $_POST['reason'] ?? '';
                $response = $manager->declineTransaction($transaction_id, $staff['user_id'], $staff['full_name'], 'FC', $reason);
            } else {
                $response = ['success' => false, 'message' => 'No permission for FC approval'];
            }
            break;
            
        case 'audit_verify':
            if (hasPermission('verify_audit', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $response = $manager->approveTransaction($transaction_id, $staff['user_id'], $staff['full_name'], 'Audit/Inspections');
            } else {
                $response = ['success' => false, 'message' => 'No permission for audit verification'];
            }
            break;
            
        case 'audit_decline':
            if (hasPermission('verify_audit', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $reason = $_POST['reason'] ?? '';
                $response = $manager->declineTransaction($transaction_id, $staff['user_id'], $staff['full_name'], 'Audit/Inspections', $reason);
            } else {
                $response = ['success' => false, 'message' => 'No permission for audit verification'];
            }
            break;
            
        case 'flag':
            if (hasPermission('verify_audit', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $flag_reason = $_POST['flag_reason'] ?? '';
                $response = $manager->flagTransaction($transaction_id, $staff['user_id'], $staff['full_name'], $flag_reason);
            } else {
                $response = ['success' => false, 'message' => 'No permission to flag transactions'];
            }
            break;
            
        case 'bulk_approve':
            $transaction_ids = $_POST['transaction_ids'] ?? [];
            if (!empty($transaction_ids)) {
                if (hasPermission('approve_fc', $staff)) {
                    $response = $manager->bulkApproveTransactions($transaction_ids, $staff['user_id'], $staff['full_name'], $staff['department']);
                } else {
                    $response = ['success' => false, 'message' => 'No permission for bulk approval'];
                }
            } else {
                $response = ['success' => false, 'message' => 'No transactions selected'];
            }
            break;
            
        case 'delete':
            if (hasPermission('delete', $staff)) {
                $transaction_id = $_POST['transaction_id'];
                $response = $manager->deleteTransaction($transaction_id, $staff['user_id']);
            } else {
                $response = ['success' => false, 'message' => 'No permission to delete transactions'];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Handle direct delete from URL (legacy support)
if (isset($_GET['delete_id']) && hasPermission('delete', $staff)) {
    $result = $manager->deleteTransaction($_GET['delete_id'], $staff['user_id']);
    if ($result['success']) {
        header('Location: view_transactions.php?success=deleted');
    } else {
        header('Location: view_transactions.php?error=' . urlencode($result['message']));
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Transaction Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-chart-line text-2xl text-blue-600"></i>
                        <span class="text-xl font-bold text-gray-900">WC ERP</span>
                    </div>
                    <div class="ml-8">
                        <h1 class="text-lg font-semibold text-gray-900">
                         <?php 
                            if ($staff['department'] === 'Accounts') { 
                                echo 'Account Dept. Dashboard';
                                echo '<p class="text-sm text-gray-500">View, Post, Approve & Manage lines of Income</p>';
                            } elseif ($staff['department'] === 'Wealth Creation') { 
                                echo 'Wealth Creation Dashboard';
                                echo '<p class="text-sm text-gray-500">View, Post, & Manage lines of Income</p>';
                            } elseif ($staff['department'] === 'Audit/Inspections') { 
                                echo 'Audit/Inspections Dashboard';
                                echo '<p class="text-sm text-gray-500">View, Verify, & Manage lines of Income</p>';
                            } else {
                                echo 'Transaction Dashboard';
                            }
                         ?>
                        </h1>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($staff['full_name']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($staff['department']) ?> - <?= htmlspecialchars($staff['level']) ?></div>
                    </div>
                    <div class="relative">
                        <button class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg transition-colors" onclick="toggleDropdown()">
                            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                <?= strtoupper($staff['full_name'][0]) ?>
                            </div>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border py-2 hidden z-50">
                            <a href="index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                            </a>
                            <a href="ledger.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-book mr-2"></i> General Ledger
                            </a>
                            <a href="mpr.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-chart-bar mr-2"></i> Monthly Reports
                            </a>
                            <div class="border-t my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section with Staff Quick Access -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">General Transaction Dashboard</h2>
                
                <!-- Quick Access Staff Buttons -->
                <div class="mb-6">
                    <div class="mb-3">
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">
                            <strong>Wealth Creation:</strong>
                        </h5>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($wc_officers as $officer): ?>
                                <a href="view_transactions.php?staff_id=<?php echo $officer['user_id']; ?>" 
                                   class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded-md transition-colors <?php echo $staff_filter == $officer['user_id'] ? 'bg-blue-200 text-blue-800' : ''; ?>">
                                    <?php echo explode(' ', $officer['full_name'])[0]; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div>
                        <h5 class="text-sm font-semibold text-gray-700 mb-2">
                            <strong>Account Dept:</strong>
                        </h5>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($account_officers as $officer): ?>
                                <a href="view_transactions.php?staff_id=<?php echo $officer['user_id']; ?>" 
                                   class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded-md transition-colors <?php echo $staff_filter == $officer['user_id'] ? 'bg-blue-200 text-blue-800' : ''; ?>">
                                    <?php echo explode(' ', $officer['full_name'])[0]; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <a href="view_transactions.php?status=pending" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Posts</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending_posts']); ?></p>
                    </div>
                </div>
            </a>

            <a href="view_transactions.php?status=fc_pending" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending FC Approvals</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending_fc_approvals']); ?></p>
                    </div>
                </div>
            </a>

            <a href="view_transactions.php?status=audit_pending" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Pending Audit Verifications</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['pending_audit_verifications']); ?></p>
                    </div>
                </div>
            </a>

            <a href="view_transactions.php?status=declined" class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Declined Transactions</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['declined_transactions']); ?></p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Date Range Filter -->
                <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <input type="hidden" name="staff_id" value="<?php echo $staff_filter; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="text" name="d1" placeholder="dd/mm/yyyy" 
                               value="<?php echo isset($_GET['d1']) ? $_GET['d1'] : ''; ?>"
                               class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="text" name="d2" placeholder="dd/mm/yyyy" 
                               value="<?php echo isset($_GET['d2']) ? $_GET['d2'] : ''; ?>"
                               class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Show Range
                        </button>
                        <a href="view_transactions.php" class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-signal mr-1"></i> View ALL Records
                        </a>
                    </div>
                </form>

                <!-- Search -->
                <form method="GET" class="flex gap-2">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <input type="hidden" name="staff_id" value="<?php echo $staff_filter; ?>">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="Search transactions..."
                           class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>

            <!-- Date Range Display -->
            <?php if (isset($_GET['d1']) && isset($_GET['d2'])): ?>
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
                <span class="text-red-700">
                    Showing search result of transactions between <strong><?php echo $_GET['d1']; ?></strong> and <strong><?php echo $_GET['d2']; ?></strong>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bulk Actions -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm font-medium text-gray-700">Select All</span>
                    </label>
                    <span id="selectedCount" class="text-sm text-gray-500">0 selected</span>
                </div>
                
                <div class="flex gap-2">
                    <?php if (hasPermission('approve_fc', $staff)): ?>
                    <button id="bulkApprove" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Bulk Approve
                    </button>
                    <button id="bulkDecline" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        Bulk Decline
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Transactions 
                    <?php if ($date_from && $date_to): ?>
                        (<?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>)
                    <?php endif; ?>
                    <?php if ($search_term): ?>
                        - Search: "<?php echo htmlspecialchars($search_term); ?>"
                    <?php endif; ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="selectAllHeader" class="rounded border-gray-300 text-blue-600">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Review</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">FC</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Audit</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Authorization</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="13" class="px-6 py-4 text-center text-gray-500">
                                No transactions found.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php 
                            $counter = (($page - 1) * $per_page) + 1;
                            foreach ($transactions as $transaction): 
                                $today_date = date('Y-m-d');
                                $can_edit = ($staff['department'] === 'Accounts' && $transaction['leasing_post_status'] === '' && $transaction['approval_status'] !== 'Approved' && $staff['level'] !== 'fc') ||
                                           ($staff['department'] === 'Wealth Creation' && $transaction['leasing_post_status'] !== 'Approved' && $staff['level'] !== 'fc') ||
                                           $staff['level'] === 'ce';
                                
                                $can_delete = ($today_date === $transaction['date_of_payment'] && $staff['user_id'] == $transaction['posting_officer_id']) || 
                                             $staff['level'] === 'ce';
                            ?>
                            <tr class="hover:bg-gray-50 transaction-row" data-id="<?php echo $transaction['id']; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if (($transaction['approval_status'] === 'Pending' && hasPermission('approve_fc', $staff)) || 
                                              ($transaction['verification_status'] === 'Pending' && hasPermission('verify_audit', $staff))): ?>
                                    <input type="checkbox" class="transaction-checkbox rounded border-gray-300 text-blue-600" 
                                           value="<?php echo $transaction['id']; ?>">
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <!-- Edit Button -->
                                        <?php if ($can_edit): ?>
                                        <button onclick="editTransaction('<?php echo $transaction['id']; ?>')" 
                                                class="text-yellow-600 hover:text-yellow-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <!-- Delete Button -->
                                        <?php if ($can_delete): ?>
                                        <button onclick="deleteTransaction('<?php echo $transaction['id']; ?>')" 
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                    <?php echo $counter++; ?>.
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button onclick="viewDetails('<?php echo $transaction['id']; ?>')" 
                                            class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                        Details
                                    </button>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('d/m/Y', strtotime($transaction['date_of_payment'])); ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php if ($transaction['shop_no']): ?>
                                        <button onclick="viewShopDetails('<?php echo $transaction['shop_no']; ?>')" 
                                                class="px-2 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                            <span class="text-yellow-300 font-bold"><?php echo $transaction['shop_no']; ?></span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="max-w-xs">
                                        <?php if ($transaction['plate_no']): ?>
                                            <span class="font-medium text-blue-600"><?php echo strtoupper($transaction['plate_no']); ?></span> - 
                                        <?php endif; ?>
                                        <?php echo ucwords(strtolower($transaction['transaction_desc'])); ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php if ($transaction['leasing_post_status'] !== 'Pending'): ?>
                                        <span class="font-bold">₦<?php echo number_format($transaction['amount_paid']); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php if ($transaction['leasing_post_status'] !== 'Pending'): ?>
                                        <?php echo $transaction['receipt_no']; ?>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Review Status -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($transaction['leasing_post_status'] === 'Approved'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-green-600 text-white">Approved</span>
                                    <?php elseif ($transaction['leasing_post_status'] === 'Declined'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-red-600 text-white">
                                            Declined <?php echo $manager->timeElapsed($transaction['leasing_post_approval_time']); ?>
                                        </span>
                                    <?php elseif ($transaction['leasing_post_status'] === 'Pending'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">
                                            Pending <?php echo $manager->timeElapsed($transaction['posting_time']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- FC Status -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($transaction['approval_status'] === 'Approved'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-green-600 text-white">Approved</span>
                                    <?php elseif ($transaction['approval_status'] === 'Declined'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-red-600 text-white">
                                            Declined <?php echo $manager->timeElapsed($transaction['approval_time']); ?>
                                        </span>
                                    <?php elseif ($transaction['approval_status'] === 'Pending' && $transaction['leasing_post_status'] === 'Approved'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">
                                            Pending <?php echo $manager->timeElapsed($transaction['leasing_post_approval_time']); ?>
                                        </span>
                                    <?php elseif ($transaction['approval_status'] === 'Pending' && $transaction['leasing_post_status'] === ''): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">
                                            Pending <?php echo $manager->timeElapsed($transaction['posting_time']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Audit Status -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if (in_array($transaction['approval_status'], ['Pending', 'Declined', ''])): ?>
                                        <!-- Empty for pending/declined FC -->
                                    <?php elseif ($transaction['verification_status'] === 'Verified'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-green-600 text-white">Verified</span>
                                    <?php elseif ($transaction['verification_status'] === 'Declined'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-red-600 text-white">
                                            Declined <?php echo $manager->timeElapsed($transaction['verification_time']); ?>
                                        </span>
                                    <?php elseif ($transaction['verification_status'] === 'Flagged'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-red-600 text-white">
                                            FLAGGED <?php echo $manager->timeElapsed($transaction['verification_time']); ?>
                                        </span>
                                    <?php elseif ($transaction['approval_status'] === 'Approved'): ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">
                                            Pending <?php echo $manager->timeElapsed($transaction['approval_time']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Authorization Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex flex-col gap-1">
                                        <?php if ($staff['department'] === 'Audit/Inspections'): ?>
                                            <?php if ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Verified' && $staff['level'] === 'Head, Audit & Inspection'): ?>
                                                <button onclick="flagTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                    Flag
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Pending'): ?>
                                                <?php if ($transaction['flag_status'] !== 'Flagged'): ?>
                                                    <button onclick="verifyTransaction('<?php echo $transaction['id']; ?>')" 
                                                            class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                                        Verify
                                                    </button>
                                                    <button onclick="flagTransaction('<?php echo $transaction['id']; ?>')" 
                                                            class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                        Flag
                                                    </button>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs font-semibold rounded bg-blue-600 text-white">CURRENTLY FLAGGED</span>
                                                    <button onclick="verifyTransaction('<?php echo $transaction['id']; ?>')" 
                                                            class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                                        Re-verify
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Flagged'): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">FLAGGED</span>
                                            <?php elseif ($transaction['approval_status'] === 'Declined' && $transaction['flag_status'] === 'Flagged'): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">FLAGGED</span>
                                            <?php endif; ?>
                                            
                                        <?php elseif ($staff['level'] === 'fc'): ?>
                                            <?php if ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Verified'): ?>
                                                <button onclick="flagTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                    Flag
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Pending'): ?>
                                                <button onclick="declineTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                    Decline
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Approved' && $transaction['verification_status'] === 'Flagged'): ?>
                                                <button onclick="declineTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                    Decline
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Pending'): ?>
                                                <button onclick="approveTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                                    Approve
                                                </button>
                                                <button onclick="declineTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                                    Decline
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Declined' && $transaction['leasing_post_status'] !== 'Declined' && $transaction['flag_status'] !== 'Flagged'): ?>
                                                <button onclick="approveTransaction('<?php echo $transaction['id']; ?>')" 
                                                        class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                                    Approve
                                                </button>
                                            <?php elseif ($transaction['approval_status'] === 'Declined' && $transaction['flag_status'] === 'Flagged'): ?>
                                                <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-600 text-white">FLAGGED</span>
                                            <?php endif; ?>
                                            
                                        <?php elseif (($staff['department'] === 'Accounts' && $transaction['leasing_post_status'] !== '' && $transaction['approval_status'] !== 'Approved') || $staff['level'] === 'ce'): ?>
                                            <a href="review_trans.php?txref=<?php echo $transaction['id']; ?>" 
                                               class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                                Review
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Payment Breakdown Row (for shop transactions) -->
                            <?php if ($transaction['shop_no']): ?>
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="9" class="px-6 py-2 text-sm text-gray-600">
                                    <strong>Payment Break Down:</strong>
                                    <?php 
                                    $payment_breakdown = $manager->getPaymentBreakdown(
                                        $transaction['shop_no'], 
                                        $transaction['receipt_no'], 
                                        $transaction['payment_category']
                                    );
                                    foreach ($payment_breakdown as $payment): ?>
                                        <span class="text-red-600"><?php echo $payment['payment_month']; ?></span>: ₦<?php echo number_format($payment['amount_paid']); ?> |
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Account Details Row -->
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="4" class="px-6 py-2 text-sm text-gray-600">
                                    <?php if ($transaction['entry_status'] === 'Journal'): ?>
                                        <?php if ($transaction['credit_account_jrn2']): ?>
                                            <span class="text-red-600"><strong>Multi Credit Journal Entry:</strong></span>
                                            <strong><?php echo $transaction['multi_credit_accounts']; ?></strong>
                                        <?php elseif ($transaction['debit_account_jrn2']): ?>
                                            <span class="text-red-600"><strong>Multi Debit Journal Entry:</strong></span>
                                            <strong><?php echo $transaction['multi_debit_accounts']; ?></strong>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td colspan="6" class="px-6 py-2 text-sm text-gray-600">
                                    <span class="text-red-600"><strong>Debit:</strong></span> <strong><?php echo $transaction['debit_account_desc']; ?></strong><br>
                                    <span class="text-red-600"><strong>Credit:</strong></span> <strong><?php echo $transaction['credit_account_desc']; ?></strong>
                                </td>
                            </tr>

                            <!-- Staff Details Row -->
                            <tr>
                                <td colspan="3"></td>
                                <td colspan="10" class="px-6 py-2 text-sm text-gray-600 text-right">
                                    <?php if (!$transaction['leasing_post_approving_officer_name']): ?>
                                        Posted by: <a href="view_transactions.php?staff_id=<?php echo $transaction['posting_officer_id']; ?>" class="font-bold text-blue-600 hover:text-blue-800"><?php echo $transaction['posting_officer_name']; ?></a>
                                    <?php else: ?>
                                        Posted by: <a href="view_transactions.php?staff_id=<?php echo $transaction['posting_officer_id']; ?>" class="font-bold text-blue-600 hover:text-blue-800"><?php echo $transaction['posting_officer_name']; ?></a> | 
                                        Reviewed by: <a href="view_transactions.php?staff_id=<?php echo $transaction['leasing_post_approving_officer_id']; ?>" class="font-bold text-blue-600 hover:text-blue-800"><?php echo $transaction['leasing_post_approving_officer_name']; ?></a>
                                    <?php endif; ?>
                                    
                                    <?php if ($transaction['approving_acct_officer_name']): ?>
                                        | Approved by: <strong><?php echo $transaction['approving_acct_officer_name']; ?></strong>
                                    <?php endif; ?>
                                    
                                    <?php if ($transaction['verifying_auditor_name']): ?>
                                        | Verified by: <strong><?php echo $transaction['verifying_auditor_name']; ?></strong>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Spacing Rows -->
                            <tr><td colspan="13" class="bg-blue-50 h-1"></td></tr>
                            <tr><td colspan="13" class="bg-blue-50 h-1"></td></tr>
                            <tr><td colspan="13" class="bg-blue-50 h-1"></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-backward mr-1"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next <i class="fas fa-forward ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo (($page - 1) * $per_page) + 1; ?></span> 
                            to <span class="font-medium"><?php echo min($page * $per_page, $total_count); ?></span> 
                            of <span class="font-medium"><?php echo number_format($total_count); ?></span> results
                        </p>
                    </div>
                    
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-l-md text-gray-700 bg-white hover:bg-gray-50">
                                    <i class="fas fa-backward mr-1"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next <i class="fas fa-forward ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Details Modal -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Transaction Details</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="modalContent" class="space-y-4">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Legacy-style approval functions for different departments
        <?php if ($staff['department'] === 'Audit/Inspections'): ?>
        function verifyTransaction(recordId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This record will be verified!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, verify!',
                showLoaderOnConfirm: true,
                preConfirm: function() {
                    return new Promise(function(resolve) {
                        fetch('view_transactions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=audit_verify&transaction_id=' + recordId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Verified!', data.message, 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Oops...', 'Something went wrong, record not verified!', 'error');
                        });
                    });
                },
                allowOutsideClick: false
            });
        }

        function declineAuditTransaction(declineId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This record will NOT be verified!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, decline!',
                showLoaderOnConfirm: true,
                preConfirm: function() {
                    return new Promise(function(resolve) {
                        fetch('view_transactions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=audit_decline&transaction_id=' + declineId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Declined!', data.message, 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Oops...', 'Something went wrong, unable to decline record!', 'error');
                        });
                    });
                },
                allowOutsideClick: false
            });
        }
        <?php endif; ?>

        <?php if ($staff['level'] === 'fc' || $staff['department'] === 'Accounts'): ?>
        function approveTransaction(recordId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This record will hit the Financials!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, approve!',
                showLoaderOnConfirm: true,
                preConfirm: function() {
                    return new Promise(function(resolve) {
                        fetch('view_transactions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=fc_approve&transaction_id=' + recordId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Approved!', data.message, 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Oops...', 'Something went wrong, record not approved!', 'error');
                        });
                    });
                },
                allowOutsideClick: false
            });
        }

        function declineTransaction(declineId) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This record will NOT hit the Financials!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, decline!',
                showLoaderOnConfirm: true,
                preConfirm: function() {
                    return new Promise(function(resolve) {
                        fetch('view_transactions.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=fc_decline&transaction_id=' + declineId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Declined!', data.message, 'success');
                                setTimeout(() => window.location.reload(), 1500);
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Oops...', 'Something went wrong, unable to decline record!', 'error');
                        });
                    });
                },
                allowOutsideClick: false
            });
        }
        <?php endif; ?>

        // Flag transaction function
        function flagTransaction(transactionId) {
            Swal.fire({
                title: 'Flag Transaction',
                text: 'Please provide a reason for flagging this transaction:',
                input: 'textarea',
                inputPlaceholder: 'Enter flag reason...',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Flag Transaction',
                preConfirm: (reason) => {
                    if (!reason) {
                        Swal.showValidationMessage('Please provide a reason for flagging');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('view_transactions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=flag&transaction_id=' + transactionId + '&flag_reason=' + encodeURIComponent(result.value)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Flagged!', data.message, 'success');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error!', 'Something went wrong', 'error');
                    });
                }
            });
        }

        // Legacy-style edit and delete functions
        function editTransaction(id) {
            if (confirm('Are you sure you want to EDIT this transaction record?')) {
                window.location.href = 'edit_posting.php?edit_id=' + id;
            }
        }

        function deleteTransaction(id) {
            if (confirm('Are you sure you want to COMPLETELY DELETE details?')) {
                window.location.href = 'view_transactions.php?delete_id=' + id;
            }
        }

        function viewShopDetails(shopNo) {
            window.location.href = 'customer_details.php?shop_no=' + shopNo;
        }

        function viewDetails(transactionId) {
            // Load transaction details via AJAX
            fetch(`transaction_details.php?id=${transactionId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalContent').innerHTML = html;
                    document.getElementById('detailsModal').classList.remove('hidden');
                })
                .catch(error => {
                    Swal.fire('Error!', 'Could not load transaction details', 'error');
                });
        }

        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Checkbox handling
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.transaction-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkButtons();
        });

        document.getElementById('selectAllHeader').addEventListener('change', function() {
            document.getElementById('selectAll').checked = this.checked;
            document.getElementById('selectAll').dispatchEvent(new Event('change'));
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('transaction-checkbox')) {
                updateBulkButtons();
            }
        });

        function updateBulkButtons() {
            const selectedCheckboxes = document.querySelectorAll('.transaction-checkbox:checked');
            const count = selectedCheckboxes.length;
            
            document.getElementById('selectedCount').textContent = count + ' selected';
            const bulkApprove = document.getElementById('bulkApprove');
            const bulkDecline = document.getElementById('bulkDecline');
            
            if (bulkApprove) bulkApprove.disabled = count === 0;
            if (bulkDecline) bulkDecline.disabled = count === 0;
        }

        // Bulk actions
        <?php if (hasPermission('approve_fc', $staff)): ?>
        document.getElementById('bulkApprove').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) return;
            
            Swal.fire({
                title: 'Bulk Approve Transactions',
                text: `Are you sure you want to approve ${selectedIds.length} transactions?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, approve all!'
            }).then((result) => {
                if (result.isConfirmed) {
                    performBulkAction('bulk_approve', selectedIds);
                }
            });
        });

        document.getElementById('bulkDecline').addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.transaction-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selectedIds.length === 0) return;
            
            Swal.fire({
                title: 'Bulk Decline Transactions',
                text: `Are you sure you want to decline ${selectedIds.length} transactions?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, decline all!'
            }).then((result) => {
                if (result.isConfirmed) {
                    performBulkAction('bulk_decline', selectedIds);
                }
            });
        });

        function performBulkAction(action, transactionIds) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_ids', JSON.stringify(transactionIds));

            fetch('view_transactions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.fire('Error!', 'Something went wrong', 'error');
            });
        }
        <?php endif; ?>

        // Toggle dropdown
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = event.target.closest('button');
            
            if (!button || !button.onclick) {
                dropdown.classList.add('hidden');
            }
        });

        // Auto-refresh every 10 minutes (like legacy system)
        setInterval(() => {
            const statsSection = document.querySelector('#stats');
            if (statsSection) {
                window.location.reload();
            }
        }, 600000);

        // Success/Error message handling
        <?php if (isset($_GET['success'])): ?>
            Swal.fire('Success!', 'Transaction deleted successfully', 'success');
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            Swal.fire('Error!', '<?php echo htmlspecialchars($_GET['error']); ?>', 'error');
        <?php endif; ?>
    </script>
</body>
</html>