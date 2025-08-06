<?php
require_once 'TransactionManager.php';
require_once 'config.php';
require_once 'functions.php';

// Start session
session_start();

$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

$manager = new TransactionManager();
$transaction_id = $_GET['txref'] ?? null;

if (!$transaction_id) {
    echo '<p class="text-red-600">Transaction ID not provided.</p>';
    exit;
}

$transaction = $manager->getTransactionDetails($transaction_id);

if (!$transaction) {
    echo '<p class="text-red-600">Transaction not found.</p>';
    exit;
}

// Get payment breakdown if it's a shop transaction
$payment_breakdown = [];
if ($transaction['shop_no']) {
    $payment_breakdown = $manager->getPaymentBreakdown(
        $transaction['shop_no'], 
        $transaction['receipt_no'], 
        $transaction['payment_category']
    );
}

// Get account details for debit and credit accounts
$manager->db->query("SELECT acct_desc FROM accounts WHERE acct_id = :debit_account");
$manager->db->bind(':debit_account', $transaction['debit_account']);
$debit_account = $manager->db->single();

$manager->db->query("SELECT acct_desc FROM accounts WHERE acct_id = :credit_account");
$manager->db->bind(':credit_account', $transaction['credit_account']);
$credit_account = $manager->db->single();

// Get customer details if shop_no exists
$customer_details = null;
if ($transaction['shop_no']) {
    $manager->db->query("SELECT * FROM customers WHERE shop_no = :shop_no");
    $manager->db->bind(':shop_no', $transaction['shop_no']);
    $customer_details = $manager->db->single();
}

// Check if transaction is flagged
$flag_details = null;
if ($transaction['flag_status'] === 'Flagged') {
    $manager->db->query("SELECT * FROM account_flagged_record WHERE id = :transaction_id");
    $manager->db->bind(':transaction_id', $transaction_id);
    $flag_details = $manager->db->single();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Transaction Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800 mr-4">← Back</a>
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> Transaction Details</h1>
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flag Status Alert -->
        <?php if ($transaction['flag_status'] === 'Flagged' && $flag_details): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <div class="flex items-center">
                <i class="fas fa-flag mr-2"></i>
                <div>
                    <strong>FLAGGED by <?php echo $flag_details['flag_officer_name']; ?></strong>
                    <p class="text-sm">Flagged on <?php echo format_datetime_display($flag_details['flag_time']); ?> 
                       [<?php echo time_elapsed_string($flag_details['flag_time']); ?>]</p>
                    <p class="mt-2 font-medium"><?php echo $flag_details['comment']; ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Posted Record -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 bg-green-100 px-4 py-2 rounded">Posted Record</h3>
                
                <dl class="space-y-3">
                    <?php if ($transaction['customer_name']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Customer's Name:</dt>
                        <dd class="text-sm text-gray-900 font-medium"><?php echo ucwords(strtolower($transaction['customer_name'])); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($customer_details && $customer_details['off_takers_name']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Current Occupant:</dt>
                        <dd class="text-sm text-gray-900"><?php echo ucwords(strtolower($customer_details['off_takers_name'])); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['shop_no']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Space No:</dt>
                        <dd class="text-sm text-gray-900">
                            <span class="px-3 py-1 bg-green-100 text-green-800 rounded font-medium">
                                <?php echo $transaction['shop_no']; ?>
                            </span>
                        </dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['shop_size']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Space Size:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['shop_size']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Date of Payment:</dt>
                        <dd class="text-sm text-gray-900 font-medium"><?php echo format_date_display($transaction['date_of_payment']); ?></dd>
                    </div>
                    
                    <?php if ($transaction['date_on_receipt'] && $transaction['date_on_receipt'] !== '0000-00-00'): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Date on Receipt:</dt>
                        <dd class="text-sm text-gray-900"><?php echo format_date_display($transaction['date_on_receipt']); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['start_date'] && $transaction['end_date']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Period Covered:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['start_date'] . ' - ' . $transaction['end_date']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['payment_type']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Payment Type:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['payment_type']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Transaction Description:</dt>
                        <dd class="text-sm text-gray-900"><?php echo ucwords(strtolower($transaction['transaction_desc'])); ?></dd>
                    </div>
                    
                    <?php if ($transaction['no_of_tickets']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">No of Tickets:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['no_of_tickets']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['plate_no']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Plate No:</dt>
                        <dd class="text-sm text-gray-900 font-mono"><?php echo strtoupper($transaction['plate_no']); ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['cheque_no']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Cheque No:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['cheque_no']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($transaction['teller_no']): ?>
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Teller No:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['teller_no']; ?></dd>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Receipt No:</dt>
                        <dd class="text-sm text-gray-900 font-mono"><?php echo $transaction['receipt_no']; ?></dd>
                    </div>
                    
                    <div class="flex justify-between">
                        <dt class="text-sm font-medium text-gray-500">Payment Category:</dt>
                        <dd class="text-sm text-gray-900"><?php echo $transaction['payment_category']; ?></dd>
                    </div>
                    
                    <div class="flex justify-between border-t pt-3">
                        <dt class="text-sm font-medium text-gray-500">Amount Paid:</dt>
                        <dd class="text-lg text-gray-900 font-bold">₦<?php echo number_format($transaction['amount_paid']); ?></dd>
                    </div>
                    
                    <!-- Payment Breakdown -->
                    <?php if (!empty($payment_breakdown)): ?>
                    <div class="mt-4">
                        <dt class="text-sm font-medium text-gray-500 mb-2">Payment Breakdown:</dt>
                        <dd class="text-sm text-gray-900">
                            <div class="bg-gray-50 rounded p-3">
                                <?php foreach ($payment_breakdown as $payment): ?>
                                    <span class="inline-block mr-3 mb-1">
                                        <span class="text-red-600 font-medium"><?php echo $payment['payment_month']; ?>:</span> 
                                        ₦<?php echo number_format($payment['amount_paid']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
                
                <!-- Account Information -->
                <div class="mt-6 pt-6 border-t">
                    <h4 class="font-medium text-gray-900 mb-3 bg-blue-100 px-3 py-1 rounded">Account Information</h4>
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm font-medium text-red-600">Debit Account:</span>
                            <div class="mt-1">
                                <?php if ($staff['department'] !== 'Leasing'): ?>
                                    <a href="ledger.php?ref=<?php echo $transaction_id; ?>&acct_id=<?php echo $transaction['debit_account']; ?>#txref" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <?php echo ucwords(strtolower($debit_account['acct_desc'])); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-900"><?php echo ucwords(strtolower($debit_account['acct_desc'])); ?></span>
                                <?php endif; ?>
                                <div class="text-sm text-gray-600">₦<?php echo number_format($transaction['amount_paid']); ?></div>
                            </div>
                        </div>
                        
                        <div>
                            <span class="text-sm font-medium text-red-600">Credit Account:</span>
                            <div class="mt-1">
                                <?php if ($staff['department'] !== 'Leasing'): ?>
                                    <a href="ledger.php?ref=<?php echo $transaction_id; ?>&acct_id=<?php echo $transaction['credit_account']; ?>#txref" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        <?php echo ucwords(strtolower($credit_account['acct_desc'])); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-900"><?php echo ucwords(strtolower($credit_account['acct_desc'])); ?></span>
                                <?php endif; ?>
                                <div class="text-sm text-gray-600">₦<?php echo number_format($transaction['amount_paid']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Trail -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 bg-green-100 px-4 py-2 rounded">Transaction Trail</h3>
                
                <div class="space-y-4">
                    <!-- Remitting Information -->
                    <?php if ($transaction['remitting_staff']): ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">This transaction was REMITTED by:</span>
                            <span class="text-sm text-red-600 font-bold"><?php echo $transaction['remitting_staff']; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Posting Information -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between">
                            <span class="text-sm font-medium text-gray-500">Posted by:</span>
                            <span class="text-sm text-gray-900">
                                <?php echo ucwords(strtolower($transaction['posting_officer_name'])); ?> 
                                on <span class="text-red-600"><?php echo format_datetime_display($transaction['posting_time']); ?></span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Post Approval Status -->
                    <?php if ($transaction['leasing_post_status']): ?>
                    <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Post Approval Status:</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $transaction['leasing_post_status']; ?></span>
                            </div>
                            <?php if ($transaction['leasing_post_approval_time']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Post Approval Time:</span>
                                <span class="text-sm text-gray-900"><?php echo format_datetime_display($transaction['leasing_post_approval_time']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($transaction['leasing_post_approving_officer_name']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Payment Approved by:</span>
                                <span class="text-sm text-gray-900"><?php echo $transaction['leasing_post_approving_officer_name']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- FC Approval Status -->
                    <?php if ($transaction['approval_status']): ?>
                    <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">FC Approval Status:</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $transaction['approval_status']; ?></span>
                            </div>
                            <?php if ($transaction['approval_time']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">FC Approval Time:</span>
                                <span class="text-sm text-gray-900"><?php echo format_datetime_display($transaction['approval_time']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($transaction['approving_acct_officer_name']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Approving FC Name:</span>
                                <span class="text-sm text-gray-900"><?php echo $transaction['approving_acct_officer_name']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Audit Verification Status -->
                    <?php if ($transaction['verification_status']): ?>
                    <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Audit Verification Status:</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo $transaction['verification_status']; ?></span>
                            </div>
                            <?php if ($transaction['verifying_auditor_name']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Payment Verified by:</span>
                                <span class="text-sm text-gray-900"><?php echo $transaction['verifying_auditor_name']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($transaction['verification_time']): ?>
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-gray-500">Audit Verification Time:</span>
                                <span class="text-sm text-gray-900"><?php echo format_datetime_display($transaction['verification_time']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Exception Report Form -->
                <?php if ($transaction['flag_status'] !== 'Flagged' && ($staff['level'] === 'fc' || $staff['level'] === 'Head, Audit & Inspection')): ?>
                <div class="mt-6 pt-6 border-t">
                    <h4 class="font-medium text-gray-900 mb-4">Exception Report</h4>
                    <form method="POST" action="flagged_record_processing.php" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Comment:</label>
                            <textarea name="comment" id="comment" rows="4" maxlength="250" 
                                      data-minlength="100" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Add comment (minimum 100 characters)"></textarea>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Min count: <span id="mincount">100</span></span>
                                <span>Max character left: <span id="maxcount">250</span></span>
                            </div>
                        </div>
                        
                        <input type="hidden" name="txref" value="<?php echo $transaction_id; ?>">
                        <input type="hidden" name="posting_officer_id" value="<?php echo $staff['user_id']; ?>">
                        <input type="hidden" name="posting_officer_name" value="<?php echo $staff['full_name']; ?>">
                        <input type="hidden" name="verification_status" value="<?php echo $transaction['verification_status']; ?>">
                        <input type="hidden" name="posting_officer_level" value="<?php echo $staff['level']; ?>">
                        
                        <div class="text-center">
                            <button type="submit" name="btn_comment" id="btn_comment" disabled
                                    class="px-6 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-flag mr-2"></i>
                                Flag Record
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Comment validation
        document.getElementById('comment').addEventListener('keyup', function() {
            const minLength = parseInt(this.getAttribute('data-minlength'));
            const currentLength = this.value.length;
            const remaining = Math.max(0, minLength - currentLength);
            const maxRemaining = 250 - currentLength;
            
            document.getElementById('mincount').textContent = remaining;
            document.getElementById('maxcount').textContent = maxRemaining;
            
            const submitBtn = document.getElementById('btn_comment');
            if (currentLength < minLength) {
                submitBtn.disabled = true;
            } else {
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>