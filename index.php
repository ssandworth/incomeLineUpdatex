<?php
require_once 'PaymentProcessor.php';

// Start session (assuming session management exists)
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Wealth Creation'
];

$menu = [
    'department' => 'Wealth Creation'
];

$processor = new PaymentProcessor();
$current_date = date('Y-m-d');
$income_line = $_GET['income_line'] ?? 'car_park';

// Get remittance balance for Wealth Creation staff
$remittance_data = [];
if ($staff['department'] === 'Wealth Creation') {
    $remittance_data = $processor->getRemittanceBalance($staff['user_id'], $current_date);
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_post_car_park'])) {
    // Validate receipt number
    $receipt_check = $processor->checkReceiptExists($_POST['receipt_no']);
    if ($receipt_check) {
        $error = "Transaction failed! Receipt No: {$_POST['receipt_no']} has already been used by {$receipt_check['posting_officer_name']} on {$receipt_check['date_of_payment']}!";
    } else {
        // Process payment data
        $date_parts = explode('/', $_POST['date_of_payment']);
        $formatted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
        
        // Get staff info
        list($remitting_id, $remitting_check) = explode('-', $_POST['remitting_staff']);
        $staff_info = $processor->getStaffInfo($remitting_id, $remitting_check);
        
        // Get account info
        $debit_account_info = $processor->getAccountInfo('till', true);
        $credit_account_info = $processor->getAccountInfo('carpark', true);
        
        $payment_data = [
            'date_of_payment' => $formatted_date,
            'ticket_category' => $_POST['ticket_category'],
            'transaction_desc' => $_POST['category'] . ' - ' . $staff_info['full_name'],
            'receipt_no' => $_POST['receipt_no'],
            'amount_paid' => preg_replace('/[,]/', '', $_POST['amount_paid']),
            'remitting_id' => $remitting_id,
            'remitting_staff' => $staff_info['full_name'],
            'posting_officer_id' => $staff['user_id'],
            'posting_officer_name' => $staff['full_name'],
            'leasing_post_status' => $staff['department'] === 'Accounts' ? '' : 'Pending',
            'approval_status' => $staff['department'] === 'Accounts' ? 'Pending' : '',
            'verification_status' => $staff['department'] === 'Accounts' ? 'Pending' : '',
            'debit_account' => $debit_account_info['acct_id'],
            'credit_account' => $credit_account_info['acct_id'],
            'db_debit_table' => $debit_account_info['acct_table_name'],
            'db_credit_table' => $credit_account_info['acct_table_name'],
            'no_of_tickets' => $_POST['no_of_tickets'],
            'remit_id' => $_POST['remit_id'] ?? '',
            'income_line' => $income_line
        ];
        
        $result = $processor->processCarParkPayment($payment_data);
        
        if ($result['success']) {
            $message = $result['message'];
            // Redirect to prevent resubmission
            header('Location: index.php?income_line=' . $income_line . '&success=1');
            exit;
        } else {
            $error = $result['message'];
        }
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
    <title><?php echo APP_NAME; ?> - Payment System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#059669',
                        accent: '#dc2626'
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
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700">Welcome, <?php echo $staff['full_name']; ?></span>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                        <?php echo $staff['department']; ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-wrap -mx-4">
            <!-- Sidebar - Income Lines -->
            <div class="w-full lg:w-1/4 px-4 mb-6 lg:mb-0">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Lines of Income</h2>
                    <nav class="space-y-2">
                        <?php
                        $income_lines = [
                            'general' => 'General',
                            'car_park' => 'Car Park Tickets',
                            'car_loading' => 'Car Loading Tickets',
                            'hawkers' => 'Hawkers Tickets',
                            'wheelbarrow' => 'WheelBarrow Tickets',
                            'daily_trade' => 'Daily Trade Tickets',
                            'abattoir' => 'Abattoir',
                            'toilet_collection' => 'Toilet Collection',
                            'loading' => 'Loading & Offloading',
                            'overnight_parking' => 'Overnight Parking',
                            'scroll_board' => 'Scroll Board',
                            'other_pos' => 'Other POS Tickets',
                            'car_sticker' => 'Car Sticker'
                        ];
                        
                        foreach ($income_lines as $key => $label) {
                            $active = $income_line === $key ? 'bg-blue-50 text-blue-700 border-blue-200' : 'text-gray-700 hover:bg-gray-50';
                            echo "<a href='?income_line={$key}' class='block px-3 py-2 rounded-md text-sm font-medium border {$active}'>{$label}</a>";
                        }
                        ?>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="w-full lg:w-3/4 px-4">
                <!-- Status Cards -->
                <?php if ($staff['department'] === 'Wealth Creation'): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="text-sm font-medium text-gray-500 mb-1">Amount Remitted</div>
                        <div class="text-2xl font-bold text-green-600">₦<?php echo number_format($remittance_data['amount_remitted']); ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="text-sm font-medium text-gray-500 mb-1">Amount Posted</div>
                        <div class="text-2xl font-bold text-blue-600">₦<?php echo number_format($remittance_data['amount_posted']); ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="text-sm font-medium text-gray-500 mb-1">Unposted Balance</div>
                        <div class="text-2xl font-bold text-orange-600">₦<?php echo number_format($remittance_data['unposted']); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Payment Form -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">
                            <?php echo ucwords(str_replace('_', ' ', $income_line)); ?> Payment
                        </h2>
                        <?php if (isset($_GET['success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                            <span class="block sm:inline">Payment successfully posted for approval!</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                        <span class="block sm:inline"><?php echo $error; ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Car Park Form -->
                    <?php if ($income_line === 'car_park'): ?>
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="posting_officer_id" value="<?php echo $staff['user_id']; ?>">
                        <input type="hidden" name="posting_officer_name" value="<?php echo $staff['full_name']; ?>">
                        <input type="hidden" name="income_line" value="<?php echo $income_line; ?>">
                        <input type="hidden" name="posting_officer_dept" value="<?php echo $menu['department']; ?>">

                        <!-- Date -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date of Payment</label>
                                <input type="text" name="date_of_payment" 
                                       value="<?php echo date('d/m/Y'); ?>" 
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <?php if ($staff['department'] === 'Wealth Creation' && $remittance_data['unposted'] > 0): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Remittance</label>
                                <select name="remit_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select...</option>
                                    <option value="<?php echo $remittance_data['remit_id']; ?>">
                                        <?php echo $remittance_data['date'] . ': Remittance - ₦' . number_format($remittance_data['unposted']); ?>
                                    </option>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Category and Ticket Category -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" id="category" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a category</option>
                                    <option value="Car Park 1 (Alpha 1)">Car Park 1 (Alpha 1)</option>
                                    <option value="Car Park 2 (Alpha 2)">Car Park 2 (Alpha 2)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ticket Category (₦)</label>
                                <select name="ticket_category" id="ticket_category" required onchange="calculateAmount()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="500">500</option>
                                    <option value="700">700</option>
                                </select>
                            </div>
                        </div>

                        <!-- Number of Tickets and Receipt Number -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">No of Tickets</label>
                                <input type="number" name="no_of_tickets" id="no_of_tickets" required min="1" 
                                       onchange="calculateAmount()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Receipt No</label>
                                <input type="text" name="receipt_no" required maxlength="7" pattern="^\d{7}$"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Amount and Staff -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Amount Remitted (₦)</label>
                                <input type="text" name="amount_paid" id="amount_paid" readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Remitter's Name</label>
                                <select name="remitting_staff" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select...</option>
                                    <?php foreach ($wc_staff as $staff_member): ?>
                                        <option value="<?php echo $staff_member['user_id']; ?>-wc">
                                            <?php echo $staff_member['full_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($other_staff as $staff_member): ?>
                                        <option value="<?php echo $staff_member['id']; ?>-so">
                                            <?php echo $staff_member['full_name'] . ' - ' . $staff_member['department']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4">
                            <button type="reset" 
                                    class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Clear
                            </button>
                            
                            <?php if ($staff['department'] === 'Wealth Creation' && $remittance_data['unposted'] <= 0): ?>
                                <p class="text-red-600 font-medium">You do not have any unposted remittances for today.</p>
                            <?php else: ?>
                                <button type="submit" name="btn_post_car_park"
                                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Post Car Park Payment
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php endif; ?>

                    <!-- Other income line forms would go here -->
                    <?php if ($income_line !== 'car_park'): ?>
                    <div class="text-center py-12">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            <?php echo ucwords(str_replace('_', ' ', $income_line)); ?> Form
                        </h3>
                        <p class="text-gray-500">This form is under development. Please select Car Park for now.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calculateAmount() {
            const ticketCategory = parseFloat(document.getElementById('ticket_category').value) || 0;
            const noOfTickets = parseFloat(document.getElementById('no_of_tickets').value) || 0;
            const totalAmount = ticketCategory * noOfTickets;
            document.getElementById('amount_paid').value = totalAmount.toLocaleString();
        }
    </script>
</body>
</html>