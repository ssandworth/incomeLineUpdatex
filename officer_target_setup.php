<?php
require_once 'OfficerTargetManager.php';
require_once 'BudgetManager.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

$target_manager = new OfficerTargetManager();
$budget_manager = new BudgetManager();

// Check access permissions
$can_manage = $budget_manager->checkAccess($staff['user_id'], 'can_manage_targets');

if (!$can_manage) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_save_target'])) {
        $target_data = [
            'officer_id' => $_POST['officer_id'],
            'target_month' => $_POST['target_month'],
            'target_year' => $_POST['target_year'],
            'acct_id' => $_POST['acct_id'],
            'monthly_target' => preg_replace('/[,]/', '', $_POST['monthly_target']),
            'status' => 'Active',
            'user_id' => $staff['user_id']
        ];
        
        $result = $target_manager->saveOfficerTarget($target_data);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Get data for dropdowns
$officers = $target_manager->getEligibleOfficers();
$income_lines = $budget_manager->getActiveIncomeLines();
$current_month = date('n');
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Target Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="officer_target_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Target Management</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer Target Setup</h1>
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

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $message; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo $error; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Target Setup Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-6">
                <div class="p-3 bg-blue-100 rounded-lg mr-4">
                    <i class="fas fa-bullseye text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Set Officer Target</h2>
                    <p class="text-gray-600">Assign monthly collection targets to officers</p>
                </div>
            </div>

            <form method="POST" class="space-y-6">
                <!-- Officer Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Select Officer
                    </label>
                    <select name="officer_id" id="officer_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Choose an officer...</option>
                        <?php foreach ($officers as $officer): ?>
                            <option value="<?php echo $officer['user_id']; ?>" data-department="<?php echo $officer['department']; ?>">
                                <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Period Selection -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar mr-2"></i>Target Month
                        </label>
                        <select name="target_month" id="target_month" required onchange="calculateDailyTarget()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt mr-2"></i>Target Year
                        </label>
                        <select name="target_year" id="target_year" required onchange="calculateDailyTarget()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Income Line Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-list mr-2"></i>Income Line
                    </label>
                    <select name="acct_id" id="acct_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select income line...</option>
                        <?php foreach ($income_lines as $line): ?>
                            <option value="<?php echo $line['acct_id']; ?>">
                                <?php echo $line['acct_desc']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Target Amount -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-money-bill-wave mr-2"></i>Monthly Target Amount
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                            <input type="text" name="monthly_target" id="monthly_target" required
                                   onkeyup="formatCurrency(this); calculateDailyTarget();"
                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Enter target amount">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calculator mr-2"></i>Daily Target (Auto-calculated)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                            <input type="text" id="daily_target" readonly
                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-gray-700">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Calculated excluding Sundays
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="reset" onclick="resetForm()"
                            class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-undo mr-2"></i>Reset
                    </button>
                    <button type="submit" name="btn_save_target"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save Target
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="officer_target_management.php" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-cog mr-2"></i>
                    Manage All Targets
                </a>
                
                <a href="bulk_target_assignment.php" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-users-cog mr-2"></i>
                    Bulk Assignment
                </a>
                
                <a href="officer_performance_dashboard.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Performance Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        // Format currency input
        function formatCurrency(input) {
            let value = input.value.replace(/[^\d]/g, '');
            if (value) {
                input.value = parseInt(value).toLocaleString();
            }
        }

        // Calculate daily target based on working days (excluding Sundays)
        function calculateDailyTarget() {
            const monthlyTarget = document.getElementById('monthly_target').value.replace(/[^\d]/g, '');
            const month = document.getElementById('target_month').value;
            const year = document.getElementById('target_year').value;
            
            if (monthlyTarget && month && year) {
                const workingDays = getWorkingDaysInMonth(parseInt(month), parseInt(year));
                const dailyTarget = workingDays > 0 ? Math.round(parseInt(monthlyTarget) / workingDays) : 0;
                document.getElementById('daily_target').value = dailyTarget.toLocaleString();
            }
        }

        // Calculate working days in month (excluding Sundays)
        function getWorkingDaysInMonth(month, year) {
            const daysInMonth = new Date(year, month, 0).getDate();
            let workingDays = 0;
            
            for (let day = 1; day <= daysInMonth; day++) {
                const dayOfWeek = new Date(year, month - 1, day).getDay();
                if (dayOfWeek !== 0) { // 0 = Sunday
                    workingDays++;
                }
            }
            
            return workingDays;
        }

        // Reset form
        function resetForm() {
            document.getElementById('daily_target').value = '';
        }

        // Auto-calculate on page load if values are present
        document.addEventListener('DOMContentLoaded', function() {
            calculateDailyTarget();
        });
    </script>
</body>
</html>