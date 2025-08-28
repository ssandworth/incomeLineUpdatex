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
    if (isset($_POST['btn_bulk_assign'])) {
        $officer_id = $_POST['officer_id'];
        $month = $_POST['target_month'];
        $year = $_POST['target_year'];
        
        $income_lines = [];
        foreach ($_POST['income_lines'] as $line_data) {
            if ($line_data['selected'] && $line_data['target_amount'] > 0) {
                $income_lines[] = [
                    'acct_id' => $line_data['acct_id'],
                    'target_amount' => preg_replace('/[,]/', '', $line_data['target_amount'])
                ];
            }
        }
        
        if (!empty($income_lines)) {
            $result = $target_manager->bulkAssignTargets($officer_id, $month, $year, $income_lines, $staff['user_id']);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Please select at least one income line with a target amount.';
        }
    }
}

// Get data for form
$officers = $target_manager->getEligibleOfficers();
$income_lines = $budget_manager->getActiveIncomeLines();
$current_month = $_GET['month'] ?? date('n');
$current_year = $_GET['year'] ?? date('Y');
$selected_officer = $_GET['officer_id'] ?? null;
$month_name = date('F', mktime(0, 0, 0, $current_month, 1));

// Get budget data for reference
$budget_lines = $budget_manager->getBudgetLines($current_year);
$budget_lookup = [];
foreach ($budget_lines as $budget) {
    $month_field = strtolower(date('F', mktime(0, 0, 0, $current_month, 1))) . '_budget';
    $budget_lookup[$budget['acct_id']] = $budget[$month_field] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Bulk Target Assignment</title>
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
                    <h1 class="text-xl font-bold text-gray-900">Bulk Target Assignment</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $current_year; ?></span>
                    <?php if ($selected_officer): ?>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            Officer Pre-selected
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Period and Officer Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Assignment Filters</h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pre-select Officer</label>
                    <select name="officer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Choose Officer...</option>
                        <?php foreach ($officers as $officer): ?>
                            <option value="<?php echo $officer['user_id']; ?>" 
                                    <?php echo $selected_officer == $officer['user_id'] ? 'selected' : ''; ?>>
                                <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-calendar mr-2"></i>Load Period
                    </button>
                </div>
            </form>
        </div>

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

        <!-- Period Selection -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-calendar mr-2"></i>Load Period
                </button>
            </form>
        </div>

        <!-- Bulk Assignment Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center mb-6">
                <div class="p-3 bg-green-100 rounded-lg mr-4">
                    <i class="fas fa-users-cog text-green-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Bulk Target Assignment</h2>
                    <p class="text-gray-600">Assign multiple targets to an officer at once</p>
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
                    <script>
                        // Pre-select officer if provided in URL
                        <?php if ($selected_officer): ?>
                        document.addEventListener('DOMContentLoaded', function() {
                            document.getElementById('officer_id').value = '<?php echo $selected_officer; ?>';
                        });
                        <?php endif; ?>
                    </script>
                </div>

                <!-- Period -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Target Month</label>
                        <input type="hidden" name="target_month" value="<?php echo $current_month; ?>">
                        <input type="text" value="<?php echo $month_name; ?>" readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Target Year</label>
                        <input type="hidden" name="target_year" value="<?php echo $current_year; ?>">
                        <input type="text" value="<?php echo $current_year; ?>" readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                    </div>
                </div>

                <!-- Income Lines Selection -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-list mr-2"></i>Select Income Lines & Set Targets
                        </label>
                        <div class="flex space-x-2">
                            <button type="button" onclick="selectAll()" class="text-sm text-blue-600 hover:text-blue-800">
                                Select All
                            </button>
                            <button type="button" onclick="clearAll()" class="text-sm text-red-600 hover:text-red-800">
                                Clear All
                            </button>
                        </div>
                    </div>
                    
                    <div class="border border-gray-200 rounded-lg">
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="grid grid-cols-12 gap-4 text-xs font-medium text-gray-500 uppercase">
                                <div class="col-span-1">Select</div>
                                <div class="col-span-4">Income Line</div>
                                <div class="col-span-2">Budget Reference</div>
                                <div class="col-span-3">Target Amount</div>
                                <div class="col-span-2">Daily Target</div>
                            </div>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($income_lines as $index => $line): ?>
                            <div class="px-4 py-3 hover:bg-gray-50">
                                <div class="grid grid-cols-12 gap-4 items-center">
                                    <div class="col-span-1">
                                        <input type="checkbox" name="income_lines[<?php echo $index; ?>][selected]" 
                                               value="1" class="line-checkbox"
                                               onchange="toggleTargetInput(this, <?php echo $index; ?>)"
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <input type="hidden" name="income_lines[<?php echo $index; ?>][acct_id]" value="<?php echo $line['acct_id']; ?>">
                                    </div>
                                    <div class="col-span-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $line['acct_desc']; ?></div>
                                        <div class="text-xs text-gray-500">ID: <?php echo $line['acct_id']; ?></div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-sm text-gray-600">
                                            ₦<?php echo number_format($budget_lookup[$line['acct_id']] ?? 0); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">Budget</div>
                                    </div>
                                    <div class="col-span-3">
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                            <input type="text" name="income_lines[<?php echo $index; ?>][target_amount]" 
                                                   id="target_<?php echo $index; ?>" disabled
                                                   onkeyup="formatCurrency(this); calculateDailyTarget(<?php echo $index; ?>);"
                                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                            <input type="text" id="daily_<?php echo $index; ?>" readonly
                                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-medium text-blue-900 mb-2">Assignment Summary</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <span class="text-blue-700">Selected Lines:</span>
                            <span id="selected_count" class="font-bold text-blue-900">0</span>
                        </div>
                        <div>
                            <span class="text-blue-700">Total Monthly Target:</span>
                            <span id="total_target" class="font-bold text-blue-900">₦0</span>
                        </div>
                        <div>
                            <span class="text-blue-700">Total Daily Target:</span>
                            <span id="total_daily" class="font-bold text-blue-900">₦0</span>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="reset" onclick="resetForm()"
                            class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-undo mr-2"></i>Reset Form
                    </button>
                    <button type="submit" name="btn_bulk_assign" id="submit_btn" disabled
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-save mr-2"></i>Assign Targets
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Templates -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Assignment Templates</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <button onclick="applyTemplate('high')" 
                        class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-left">
                    <div class="font-medium text-gray-900">High Performer Template</div>
                    <div class="text-sm text-gray-600">120% of budget allocation</div>
                </button>
                
                <button onclick="applyTemplate('standard')" 
                        class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-left">
                    <div class="font-medium text-gray-900">Standard Template</div>
                    <div class="text-sm text-gray-600">100% of budget allocation</div>
                </button>
                
                <button onclick="applyTemplate('conservative')" 
                        class="p-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-left">
                    <div class="font-medium text-gray-900">Conservative Template</div>
                    <div class="text-sm text-gray-600">80% of budget allocation</div>
                </button>
            </div>
        </div>
    </div>

    <script>
        const budgetData = <?php echo json_encode($budget_lookup); ?>;
        const currentMonth = <?php echo $current_month; ?>;
        const currentYear = <?php echo $current_year; ?>;

        // Toggle target input based on checkbox
        function toggleTargetInput(checkbox, index) {
            const targetInput = document.getElementById(`target_${index}`);
            const dailyInput = document.getElementById(`daily_${index}`);
            
            if (checkbox.checked) {
                targetInput.disabled = false;
                targetInput.focus();
            } else {
                targetInput.disabled = true;
                targetInput.value = '';
                dailyInput.value = '';
            }
            
            updateSummary();
            updateSubmitButton();
        }

        // Format currency input
        function formatCurrency(input) {
            let value = input.value.replace(/[^\d]/g, '');
            if (value) {
                input.value = parseInt(value).toLocaleString();
            }
        }

        // Calculate daily target for specific line
        function calculateDailyTarget(index) {
            const monthlyTarget = document.getElementById(`target_${index}`).value.replace(/[^\d]/g, '');
            const dailyInput = document.getElementById(`daily_${index}`);
            
            if (monthlyTarget) {
                const workingDays = getWorkingDaysInMonth(currentMonth, currentYear);
                const dailyTarget = workingDays > 0 ? Math.round(parseInt(monthlyTarget) / workingDays) : 0;
                dailyInput.value = dailyTarget.toLocaleString();
            } else {
                dailyInput.value = '';
            }
            
            updateSummary();
        }

        // Get working days in month (excluding Sundays)
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

        // Update summary
        function updateSummary() {
            const checkboxes = document.querySelectorAll('.line-checkbox:checked');
            let totalMonthly = 0;
            let totalDaily = 0;
            
            checkboxes.forEach((checkbox, i) => {
                const index = checkbox.name.match(/\[(\d+)\]/)[1];
                const monthlyInput = document.getElementById(`target_${index}`);
                const dailyInput = document.getElementById(`daily_${index}`);
                
                if (monthlyInput.value) {
                    const monthlyValue = parseInt(monthlyInput.value.replace(/[^\d]/g, '')) || 0;
                    const dailyValue = parseInt(dailyInput.value.replace(/[^\d]/g, '')) || 0;
                    totalMonthly += monthlyValue;
                    totalDaily += dailyValue;
                }
            });
            
            document.getElementById('selected_count').textContent = checkboxes.length;
            document.getElementById('total_target').textContent = '₦' + totalMonthly.toLocaleString();
            document.getElementById('total_daily').textContent = '₦' + totalDaily.toLocaleString();
        }

        // Update submit button state
        function updateSubmitButton() {
            const checkboxes = document.querySelectorAll('.line-checkbox:checked');
            const submitBtn = document.getElementById('submit_btn');
            const officerSelected = document.getElementById('officer_id').value;
            
            let hasValidTargets = false;
            checkboxes.forEach((checkbox) => {
                const index = checkbox.name.match(/\[(\d+)\]/)[1];
                const targetInput = document.getElementById(`target_${index}`);
                if (targetInput.value && parseInt(targetInput.value.replace(/[^\d]/g, '')) > 0) {
                    hasValidTargets = true;
                }
            });
            
            submitBtn.disabled = !(officerSelected && hasValidTargets);
        }

        // Select all checkboxes
        function selectAll() {
            document.querySelectorAll('.line-checkbox').forEach((checkbox, index) => {
                checkbox.checked = true;
                toggleTargetInput(checkbox, index);
            });
        }

        // Clear all selections
        function clearAll() {
            document.querySelectorAll('.line-checkbox').forEach((checkbox, index) => {
                checkbox.checked = false;
                toggleTargetInput(checkbox, index);
            });
        }

        // Apply template
        function applyTemplate(type) {
            const multiplier = type === 'high' ? 1.2 : (type === 'standard' ? 1.0 : 0.8);
            
            document.querySelectorAll('.line-checkbox').forEach((checkbox, index) => {
                const acctId = checkbox.nextElementSibling.value;
                const budgetAmount = budgetData[acctId] || 0;
                
                if (budgetAmount > 0) {
                    checkbox.checked = true;
                    toggleTargetInput(checkbox, index);
                    
                    const targetAmount = Math.round(budgetAmount * multiplier);
                    const targetInput = document.getElementById(`target_${index}`);
                    targetInput.value = targetAmount.toLocaleString();
                    calculateDailyTarget(index);
                }
            });
        }

        // Reset form
        function resetForm() {
            document.querySelectorAll('.line-checkbox').forEach((checkbox, index) => {
                checkbox.checked = false;
                toggleTargetInput(checkbox, index);
            });
            updateSummary();
            updateSubmitButton();
        }

        // Event listeners
        document.getElementById('officer_id').addEventListener('change', updateSubmitButton);
        
        // Add event listeners to all target inputs
        document.querySelectorAll('input[name*="target_amount"]').forEach(input => {
            input.addEventListener('input', updateSubmitButton);
        });
    </script>
</body>
</html>