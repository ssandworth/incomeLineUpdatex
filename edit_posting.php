<?php
require_once 'TransactionManager.php';
require_once 'PaymentProcessor.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts',
    'level' => 'manager'
];

$manager = new TransactionManager();
$processor = new PaymentProcessor();
$transaction_id = $_GET['edit_id'] ?? null;

if (!$transaction_id) {
    header('Location: view_transactions.php?error=no_transaction_id');
    exit;
}

$transaction = $manager->getTransactionDetails($transaction_id);

if (!$transaction) {
    header('Location: view_transactions.php?error=transaction_not_found');
    exit;
}

// Check if user can edit this transaction
$can_edit = ($staff['level'] === 'ce') || 
            ($staff['user_id'] == $transaction['posting_officer_id'] && $transaction['approval_status'] !== 'Approved');

if (!$can_edit) {
    header('Location: view_transactions.php?error=no_edit_permission');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_update'])) {
    // Process the update
    $update_data = [
        'transaction_id' => $transaction_id,
        'transaction_desc' => $_POST['transaction_desc'],
        'amount_paid' => preg_replace('/[,]/', '', $_POST['amount_paid']),
        'receipt_no' => $_POST['receipt_no'],
        'no_of_tickets' => $_POST['no_of_tickets'] ?? null,
        'plate_no' => $_POST['plate_no'] ?? null,
        'shop_no' => $_POST['shop_no'] ?? null
    ];
    
    $result = $manager->updateTransaction($update_data);
    
    if ($result['success']) {
        header('Location: view_transactions.php?success=transaction_updated');
        exit;
    } else {
        $error = $result['message'];
    }
}

// Get staff lists for dropdowns
$wc_staff = $processor->getStaffList('Wealth Creation');
$other_staff = $processor->getOtherStaffList();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Edit Transaction</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="view_transactions.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Transactions</a>
                    <h1 class="text-xl font-bold text-gray-900">Edit Transaction</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $staff['full_name']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $staff['department']; ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Edit Transaction #<?php echo $transaction['id']; ?></h2>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    <?php echo ucfirst($transaction['approval_status'] ?: 'Pending'); ?>
                </span>
            </div>

            <?php if (isset($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date of Payment</label>
                        <input type="text" value="<?php echo date('d/m/Y', strtotime($transaction['date_of_payment'])); ?>" 
                               readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Receipt No</label>
                        <input type="text" name="receipt_no" 
                               value="<?php echo $transaction['receipt_no']; ?>" 
                               pattern="^\d{7}$" maxlength="7" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Transaction Description</label>
                    <input type="text" name="transaction_desc" 
                           value="<?php echo htmlspecialchars($transaction['transaction_desc']); ?>" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Amount Paid</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                            <input type="text" name="amount_paid" 
                                   value="<?php echo number_format($transaction['amount_paid']); ?>" 
                                   required
                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>

                    <?php if ($transaction['no_of_tickets']): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">No of Tickets</label>
                        <input type="number" name="no_of_tickets" 
                               value="<?php echo $transaction['no_of_tickets']; ?>" 
                               min="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($transaction['plate_no'] || $transaction['shop_no']): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php if ($transaction['plate_no']): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Plate No</label>
                        <input type="text" name="plate_no" 
                               value="<?php echo $transaction['plate_no']; ?>" 
                               maxlength="8"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['shop_no']): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Shop No</label>
                        <input type="text" name="shop_no" 
                               value="<?php echo $transaction['shop_no']; ?>" 
                               readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Account Information (Read-only) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Debit Account</label>
                        <input type="text" value="<?php echo $transaction['debit_account_desc']; ?>" 
                               readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Credit Account</label>
                        <input type="text" value="<?php echo $transaction['credit_account_desc']; ?>" 
                               readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <a href="view_transactions.php" 
                       class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" name="btn_update"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>
                        Update Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Format amount input
        document.querySelector('input[name="amount_paid"]').addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            if (value) {
                this.value = parseInt(value).toLocaleString();
            }
        });
    </script>
</body>
</html>