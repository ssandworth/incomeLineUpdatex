<?php
require_once 'TransactionManager.php';
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
$transaction_id = $_GET['txref'] ?? null;

if (!$transaction_id) {
    header('Location: view_transactions.php?error=no_transaction_id');
    exit;
}

$transaction = $manager->getTransactionDetails($transaction_id);

if (!$transaction) {
    header('Location: view_transactions.php?error=transaction_not_found');
    exit;
}

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_review'])) {
        $result = $manager->reviewApproveTransaction($transaction_id, $staff['user_id'], $staff['full_name']);
        if ($result['success']) {
            header('Location: view_transactions.php?success=reviewed_approved');
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['decline_review'])) {
        $reason = $_POST['decline_reason'] ?? '';
        $result = $manager->reviewDeclineTransaction($transaction_id, $staff['user_id'], $staff['full_name'], $reason);
        if ($result['success']) {
            header('Location: view_transactions.php?success=reviewed_declined');
        } else {
            $error = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Review Transaction</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Review Transaction</h1>
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
        <!-- Transaction Details Card -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Transaction Review</h2>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm font-medium">
                    Pending Review
                </span>
            </div>

            <!-- Transaction Information -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Transaction Details</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Transaction ID:</dt>
                            <dd class="text-sm text-gray-900 font-mono"><?php echo $transaction['id']; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Date of Payment:</dt>
                            <dd class="text-sm text-gray-900"><?php echo date('d/m/Y', strtotime($transaction['date_of_payment'])); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Amount:</dt>
                            <dd class="text-sm text-gray-900 font-bold">₦<?php echo number_format($transaction['amount_paid']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Receipt No:</dt>
                            <dd class="text-sm text-gray-900"><?php echo $transaction['receipt_no']; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Description:</dt>
                            <dd class="text-sm text-gray-900"><?php echo ucwords(strtolower($transaction['transaction_desc'])); ?></dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h3>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Debit Account:</dt>
                            <dd class="text-sm text-gray-900"><?php echo $transaction['debit_account_desc']; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Credit Account:</dt>
                            <dd class="text-sm text-gray-900"><?php echo $transaction['credit_account_desc']; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Posted by:</dt>
                            <dd class="text-sm text-gray-900"><?php echo $transaction['posting_officer_full_name']; ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm font-medium text-gray-500">Posted on:</dt>
                            <dd class="text-sm text-gray-900"><?php echo date('d/m/Y H:i:s', strtotime($transaction['posting_time'])); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Review Actions -->
            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Review Actions</h3>
                
                <?php if (isset($error)): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Approve Button -->
                        <button type="submit" name="approve_review" 
                                class="flex-1 px-6 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-check mr-2"></i>
                            Approve & Move to FC
                        </button>
                        
                        <!-- Decline Button -->
                        <button type="button" onclick="showDeclineForm()" 
                                class="flex-1 px-6 py-3 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <i class="fas fa-times mr-2"></i>
                            Decline Transaction
                        </button>
                    </div>

                    <!-- Decline Reason Form (Hidden by default) -->
                    <div id="declineForm" class="hidden">
                        <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Declining:</label>
                            <textarea name="decline_reason" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-red-500 focus:border-red-500"
                                      placeholder="Please provide a reason for declining this transaction..."></textarea>
                            <div class="mt-3 flex gap-2">
                                <button type="submit" name="decline_review" 
                                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                    Confirm Decline
                                </button>
                                <button type="button" onclick="hideDeclineForm()" 
                                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showDeclineForm() {
            document.getElementById('declineForm').classList.remove('hidden');
        }

        function hideDeclineForm() {
            document.getElementById('declineForm').classList.add('hidden');
        }
    </script>
</body>
</html>