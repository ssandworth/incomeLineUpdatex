<?php
require_once 'Database.php';
require_once 'config.php';

// Start session (assuming session management exists)
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

class RemittanceManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get staff list for officers dropdown
     */
    public function getWealthCreationOfficers() {
        $this->db->query("
            SELECT user_id, full_name 
            FROM staffs 
            WHERE department = 'Wealth Creation' 
            ORDER BY full_name ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get remittances for a specific date
     */
    public function getRemittancesByDate($date) {
        $this->db->query("
            SELECT cr.*, s.full_name as officer_name
            FROM cash_remittance cr
            LEFT JOIN staffs s ON cr.remitting_officer_id = s.user_id
            WHERE cr.date = :date
            ORDER BY cr.remitting_time DESC
        ");
        $this->db->bind(':date', $date);
        return $this->db->resultSet();
    }
    
    /**
     * Get remittance summary by category for a date
     */
    public function getRemittanceSummary($date) {
        $categories = ['Rent', 'Service Charge', 'Other Collection'];
        $summary = [];
        
        foreach ($categories as $category) {
            // Get remitted amounts
            $this->db->query("
                SELECT 
                    s.full_name as officer_name,
                    COALESCE(SUM(cr.amount_paid), 0) as amount_remitted,
                    COALESCE(SUM(cr.no_of_receipts), 0) as receipts_count
                FROM staffs s
                LEFT JOIN cash_remittance cr ON s.user_id = cr.remitting_officer_id 
                    AND cr.date = :date AND cr.category = :category
                WHERE s.department = 'Wealth Creation'
                GROUP BY s.user_id, s.full_name
                ORDER BY s.full_name ASC
            ");
            $this->db->bind(':date', $date);
            $this->db->bind(':category', $category);
            $remitted = $this->db->resultSet();
            
            // Get posted amounts based on category
            $posted = [];
            foreach ($remitted as $officer) {
                $posted_amount = $this->getPostedAmount($officer['officer_name'], $date, $category);
                $posted[] = [
                    'officer_name' => $officer['officer_name'],
                    'amount_posted' => $posted_amount
                ];
            }
            
            $summary[$category] = [
                'remitted' => $remitted,
                'posted' => $posted
            ];
        }
        
        return $summary;
    }
    
    /**
     * Get posted amount for an officer by category
     */
    private function getPostedAmount($officer_name, $date, $category) {
        if ($category === 'Other Collection') {
            $this->db->query("
                SELECT COALESCE(SUM(amount_paid), 0) as amount_posted
                FROM account_general_transaction_new 
                WHERE posting_officer_name LIKE :officer_name 
                AND date_of_payment = :date 
                AND payment_category = 'Other Collection'
            ");
        } else {
            // For Rent and Service Charge, you might need different tables
            // Based on the original code, these seem to use different analysis tables
            return 0; // Placeholder - implement based on your specific tables
        }
        
        $this->db->bind(':officer_name', '%' . $officer_name . '%');
        $this->db->bind(':date', $date);
        $result = $this->db->single();
        
        return $result['amount_posted'] ?? 0;
    }
    
    /**
     * Process new remittance
     */
    public function processRemittance($data) {
        $this->db->beginTransaction();
        
        try {
            // Check if remittance already exists for this officer, date, and category
            $this->db->query("
                SELECT remit_id 
                FROM cash_remittance 
                WHERE remitting_officer_id = :officer_id 
                AND date = :date 
                AND category = :category
            ");
            $this->db->bind(':officer_id', $data['officer_id']);
            $this->db->bind(':date', $data['date']);
            $this->db->bind(':category', $data['category']);
            $existing = $this->db->single();
            
            // Generate or use existing remit_id
            if ($existing) {
                $remit_id = $existing['remit_id'];
            } else {
                $remit_id = time() . mt_rand(5000, 5300);
            }
            
            // Get officer name
            $this->db->query("SELECT full_name FROM staffs WHERE user_id = :officer_id");
            $this->db->bind(':officer_id', $data['officer_id']);
            $officer = $this->db->single();
            
            // Insert remittance
            $this->db->query("
                INSERT INTO cash_remittance (
                    remit_id, date, amount_paid, no_of_receipts, category,
                    remitting_officer_id, remitting_officer_name,
                    posting_officer_id, posting_officer_name, remitting_time
                ) VALUES (
                    :remit_id, :date, :amount_paid, :no_of_receipts, :category,
                    :remitting_officer_id, :remitting_officer_name,
                    :posting_officer_id, :posting_officer_name, NOW()
                )
            ");
            
            $this->db->bind(':remit_id', $remit_id);
            $this->db->bind(':date', $data['date']);
            $this->db->bind(':amount_paid', $data['amount_paid']);
            $this->db->bind(':no_of_receipts', $data['no_of_receipts']);
            $this->db->bind(':category', $data['category']);
            $this->db->bind(':remitting_officer_id', $data['officer_id']);
            $this->db->bind(':remitting_officer_name', $officer['full_name']);
            $this->db->bind(':posting_officer_id', $data['posting_officer_id']);
            $this->db->bind(':posting_officer_name', $data['posting_officer_name']);
            
            $this->db->execute();
            
            $this->db->endTransaction();
            return ['success' => true, 'message' => 'Cash remittance successful!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error occurred while posting: ' . $e->getMessage()];
        }
    }
    
    /**
     * Delete remittance
     */
    public function deleteRemittance($remittance_id) {
        $this->db->query("DELETE FROM cash_remittance WHERE id = :id");
        $this->db->bind(':id', $remittance_id);
        return $this->db->execute();
    }
    
    /**
     * Check if remittance can be deleted
     */
    public function canDeleteRemittance($remittance_id, $officer_id, $date, $category) {
        // Check if there are posted transactions
        $this->db->query("
            SELECT COUNT(*) as count
            FROM account_general_transaction_new 
            WHERE posting_officer_id = :officer_id 
            AND date_of_payment = :date 
            AND payment_category = :category
        ");
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':date', $date);
        $this->db->bind(':category', $category);
        $result = $this->db->single();
        
        return $result['count'] == 0;
    }
}

$manager = new RemittanceManager();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_post'])) {
        // Validate amounts match
        if ($_POST['amount_paid'] !== $_POST['confirm_amount_paid']) {
            $error = 'Amount and confirmation amount do not match!';
        } else {
            $date_parts = explode('/', $_POST['date_of_payment']);
            $formatted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
            
            $remittance_data = [
                'officer_id' => $_POST['officer'],
                'date' => $formatted_date,
                'amount_paid' => preg_replace('/[,]/', '', $_POST['amount_paid']),
                'no_of_receipts' => $_POST['no_of_receipts'],
                'category' => $_POST['category'],
                'posting_officer_id' => $staff['user_id'],
                'posting_officer_name' => $staff['full_name']
            ];
            
            $result = $manager->processRemittance($remittance_data);
            
            if ($result['success']) {
                $message = $result['message'];
                // Redirect to prevent resubmission
                header('Location: remittance_dashboard.php?success=1');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    if ($manager->deleteRemittance($_GET['delete_id'])) {
        $message = 'Remittance deleted successfully!';
    } else {
        $error = 'Error deleting remittance!';
    }
}

// Get current date for display
$current_date = $_GET['d1'] ?? date('Y-m-d');
if (isset($_GET['d1'])) {
    $date_parts = explode('/', $_GET['d1']);
    if (count($date_parts) === 3) {
        $current_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
    }
}

$officers = $manager->getWealthCreationOfficers();
$remittances = $manager->getRemittancesByDate($current_date);
$summary = $manager->getRemittanceSummary($current_date);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Remittance Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Remittance Dashboard</h1>
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
        <!-- Header Section -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-4 lg:mb-0">
                        <h2 class="text-2xl font-bold text-gray-900">Account Remittance Dashboard</h2>
                        <p class="text-gray-600">Manage cash remittances from collection officers</p>
                    </div>
                    
                    <!-- Date Filter Form -->
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Remittance</label>
                            <input type="date" name="d1" 
                                   value="<?php echo $current_date; ?>"
                                   class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                View Remittance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
            <span class="block sm:inline">Cash remittance successful!</span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
            <span class="block sm:inline"><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
            <span class="block sm:inline"><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Cash Remittance Form -->
            <?php if ($staff['department'] === 'Accounts'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-2 bg-red-100 rounded-lg mr-3">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Cash Remittance</h3>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="posting_officer_id" value="<?php echo $staff['user_id']; ?>">
                        <input type="hidden" name="posting_officer_name" value="<?php echo $staff['full_name']; ?>">

                        <!-- Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date of Payment</label>
                            <input type="text" name="date_of_payment" 
                                   value="<?php echo date('d/m/Y'); ?>" 
                                   readonly
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                        </div>

                        <!-- Officer -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Officer</label>
                            <select name="officer" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select...</option>
                                <?php foreach ($officers as $officer): ?>
                                    <option value="<?php echo $officer['user_id']; ?>">
                                        <?php echo $officer['full_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount Remitted</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                <input type="password" name="amount_paid" id="amount_paid" required
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Confirm Amount -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Amount Remitted</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                <input type="text" name="confirm_amount_paid" id="confirm_amount_paid" required
                                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <span id="message" class="text-sm mt-1"></span>
                        </div>

                        <!-- Number of Receipts -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">No of Receipts</label>
                            <input type="number" name="no_of_receipts" required min="1"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Category -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select...</option>
                                <option value="Rent">Rent Collection</option>
                                <option value="Service Charge">Service Charge Collection</option>
                                <option value="Other Collection">Other Collection</option>
                            </select>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex space-x-3">
                            <button type="submit" name="btn_post"
                                    class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Post Remittance
                            </button>
                            <button type="reset"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Remittance Summary -->
            <div class="<?php echo $staff['department'] === 'Accounts' ? 'lg:col-span-2' : 'lg:col-span-3'; ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <?php foreach ($summary as $category => $data): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <?php echo $category; ?> Remittance
                        </h3>
                        <div class="space-y-2">
                            <?php 
                            $total_remitted = 0;
                            $total_posted = 0;
                            foreach ($data['remitted'] as $index => $officer): 
                                $total_remitted += $officer['amount_remitted'];
                                $posted_amount = $data['posted'][$index]['amount_posted'] ?? 0;
                                $total_posted += $posted_amount;
                            ?>
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-medium"><?php echo $officer['officer_name']; ?></span>
                                <div class="text-right">
                                    <div class="text-green-600">₦<?php echo number_format($officer['amount_remitted']); ?></div>
                                    <div class="text-blue-600">₦<?php echo number_format($posted_amount); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div class="border-t pt-2 flex justify-between items-center font-bold">
                                <span>Total</span>
                                <div class="text-right">
                                    <div class="text-green-600">₦<?php echo number_format($total_remitted); ?></div>
                                    <div class="text-blue-600">₦<?php echo number_format($total_posted); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Remittances Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    <?php echo date('d/m/Y', strtotime($current_date)); ?> Remittances
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($remittances)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No remittances found for this date.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($remittances as $remittance): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($staff['department'] === 'Accounts'): ?>
                                        <?php if ($manager->canDeleteRemittance($remittance['id'], $remittance['remitting_officer_id'], $remittance['date'], $remittance['category'])): ?>
                                        <button onclick="confirmDelete(<?php echo $remittance['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('H:i:s', strtotime($remittance['remitting_time'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo $remittance['officer_name']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $remittance['category'] === 'Rent' ? 'bg-blue-100 text-blue-800' : 
                                                  ($remittance['category'] === 'Service Charge' ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800'); ?>">
                                        <?php echo $remittance['category']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <div class="font-bold">₦<?php echo number_format($remittance['amount_paid']); ?></div>
                                    <div class="text-xs text-gray-500">(<?php echo $remittance['no_of_receipts']; ?> receipts)</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    Posted by: <?php echo $remittance['posting_officer_name']; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Amount confirmation validation
        document.getElementById('amount_paid').addEventListener('input', validateAmounts);
        document.getElementById('confirm_amount_paid').addEventListener('input', validateAmounts);

        function validateAmounts() {
            const amount = document.getElementById('amount_paid').value;
            const confirmAmount = document.getElementById('confirm_amount_paid').value;
            const message = document.getElementById('message');

            if (amount && confirmAmount) {
                if (amount === confirmAmount) {
                    message.textContent = 'Confirmed!';
                    message.className = 'text-sm mt-1 text-green-600';
                } else {
                    message.textContent = 'Amount mismatch!';
                    message.className = 'text-sm mt-1 text-red-600';
                }
            } else {
                message.textContent = '';
            }
        }

        // Delete confirmation
        function confirmDelete(id) {
            if (confirm('Are you sure you want to COMPLETELY DELETE this remittance?')) {
                window.location.href = 'remittance_dashboard.php?delete_id=' + id;
            }
        }
    </script>
</body>
</html>