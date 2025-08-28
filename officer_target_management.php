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
            'target_id' => $_POST['target_id'] ?? null,
            'officer_id' => $_POST['officer_id'],
            'target_month' => $_POST['target_month'],
            'target_year' => $_POST['target_year'],
            'acct_id' => $_POST['acct_id'],
            'monthly_target' => preg_replace('/[,]/', '', $_POST['monthly_target']),
            'status' => $_POST['status'],
            'user_id' => $staff['user_id']
        ];
        
        $result = $target_manager->saveOfficerTarget($target_data);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['btn_update_performance'])) {
        $result = $target_manager->updateOfficerPerformance($_POST['performance_month'], $_POST['performance_year']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Handle deletion
if (isset($_GET['delete_target_id'])) {
    if ($target_manager->deleteOfficerTarget($_GET['delete_target_id'])) {
        $message = 'Officer target deleted successfully!';
    } else {
        $error = 'Error deleting officer target!';
    }
}

// Get data for display
$selected_month = $_GET['month'] ?? date('n');
$selected_year = $_GET['year'] ?? date('Y');
$selected_officer = $_GET['officer_id'] ?? null;
$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

$officers = $target_manager->getEligibleOfficers();
$income_lines = $budget_manager->getActiveIncomeLines();
$officer_targets = $selected_officer ? $target_manager->getOfficerTargets($selected_officer, $selected_month, $selected_year) : [];
$all_targets = $target_manager->getAllOfficerTargets($selected_month, $selected_year);
$month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Officer Target Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="budget_management.php" class="text-blue-600 hover:text-blue-800 mr-4">← Back to Budget</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer Target Management</h1>
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
        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                    <div class="mb-4 lg:mb-0">
                        <h2 class="text-2xl font-bold text-gray-900">Officer Monthly Targets</h2>
                        <p class="text-gray-600">Set and manage monthly collection targets for officers</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Period Selection -->
                        <form method="GET" class="flex gap-2">
                            <input type="hidden" name="officer_id" value="<?php echo $selected_officer; ?>">
                            <select name="month" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="year" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Load Period
                            </button>
                        </form>
                        
                        <!-- Quick Actions -->
                        <div class="flex gap-2">
                            <button onclick="showTargetForm()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                <i class="fas fa-plus mr-2"></i>Add Target
                            </button>
                            
                            <a href="officer_target_setup.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                               class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                <i class="fas fa-bullseye mr-2"></i>Target Setup
                            </a>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="performance_month" value="<?php echo $selected_month; ?>">
                                <input type="hidden" name="performance_year" value="<?php echo $selected_year; ?>">
                                <button type="submit" name="btn_update_performance" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                    <i class="fas fa-sync mr-2"></i>Update Performance
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Officer Selection -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Officer Selection</h3>
                <form method="GET" class="flex flex-col sm:flex-row gap-4">
                    <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                    
                    <div class="flex-1">
                        <select name="officer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Officer to Manage Targets</option>
                            <?php foreach ($officers as $officer): ?>
                                <option value="<?php echo $officer['user_id']; ?>" 
                                        <?php echo $selected_officer == $officer['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Load Officer Targets
                    </button>
                </form>
            </div>
        </div>

        <!-- Target Form Modal -->
        <div id="targetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Set Officer Target</h3>
                            <button onclick="hideTargetForm()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="target_id" id="target_id">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Officer</label>
                                    <select name="officer_id" id="officer_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Officer</option>
                                        <?php foreach ($officers as $officer): ?>
                                            <option value="<?php echo $officer['user_id']; ?>">
                                                <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Income Line</label>
                                    <select name="acct_id" id="acct_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Income Line</option>
                                        <?php foreach ($income_lines as $line): ?>
                                            <option value="<?php echo $line['acct_id']; ?>">
                                                <?php echo $line['acct_desc']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Month</label>
                                    <select name="target_month" id="target_month" required onchange="calculateDailyTarget()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Target Year</label>
                                    <select name="target_year" id="target_year" required onchange="calculateDailyTarget()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <?php for ($y = date('Y'); $y <= date('Y') + 2; $y++): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Target</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                        <input type="text" name="monthly_target" id="monthly_target" required
                                               onkeyup="formatCurrency(this); calculateDailyTarget();"
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Daily Target (Auto-calculated)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                        <input type="text" id="daily_target" readonly
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Excludes Sundays from calculation</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-4 pt-6 border-t">
                                <button type="button" onclick="hideTargetForm()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" name="btn_save_target"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Target
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Officers Targets Overview -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Officers Targets Overview - <?php echo $month_name . ' ' . $selected_year; ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Lines</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Daily Target</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($all_targets)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No targets set for <?php echo $month_name . ' ' . $selected_year; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($all_targets as $target): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                            <?php echo strtoupper(substr($target['officer_name'], 0, 2)); ?>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $target['officer_name']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $target['department'] === 'Wealth Creation' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo $target['department']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                    <?php echo $target['assigned_lines']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($target['total_target']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($target['avg_daily_target']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="?officer_id=<?php echo $target['officer_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="Manage Targets">
                                            <i class="fas fa-cog"></i>
                                        </a>
                                        <a href="officer_performance_report.php?officer_id=<?php echo $target['officer_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                                           class="text-green-600 hover:text-green-800" title="View Performance">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Selected Officer Targets -->
        <?php if ($selected_officer && !empty($officer_targets)): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Individual Officer Targets - <?php echo $month_name . ' ' . $selected_year; ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Target</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Target</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($officer_targets as $target): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo $target['acct_desc']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($target['monthly_target']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($target['daily_target']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?php echo $target['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $target['status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <button onclick="editTarget(<?php echo htmlspecialchars(json_encode($target)); ?>)" 
                                            class="text-green-600 hover:text-green-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="confirmDeleteTarget(<?php echo $target['id']; ?>)" 
                                            class="text-red-600 hover:text-red-800" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTAL</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column($officer_targets, 'monthly_target'))); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column($officer_targets, 'daily_target'))); ?>
                            </th>
                            <th colspan="2" class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Management Tools -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Target Management Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="officer_performance_dashboard.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Performance Dashboard
                </a>
                
                <a href="target_vs_achievement_report.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Target vs Achievement
                </a>
                
                <a href="officer_ranking_report.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-trophy mr-2"></i>
                    Officer Ranking
                </a>
                
                <a href="bulk_target_assignment.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-users-cog mr-2"></i>
                    Bulk Assignment
                </a>
            </div>
        </div>
    </div>

    <script>
        // Show/hide target form
        function showTargetForm() {
            document.getElementById('targetModal').classList.remove('hidden');
            // Pre-select current officer if one is selected
            <?php if ($selected_officer): ?>
            document.getElementById('officer_id').value = '<?php echo $selected_officer; ?>';
            <?php endif; ?>
        }

        function hideTargetForm() {
            document.getElementById('targetModal').classList.add('hidden');
            document.querySelector('#targetModal form').reset();
            document.getElementById('target_id').value = '';
        }

        // Edit target
        function editTarget(target) {
            showTargetForm();
            document.getElementById('target_id').value = target.id;
            document.getElementById('officer_id').value = target.officer_id;
            document.getElementById('acct_id').value = target.acct_id;
            document.getElementById('target_month').value = target.target_month;
            document.getElementById('target_year').value = target.target_year;
            document.getElementById('monthly_target').value = parseInt(target.monthly_target).toLocaleString();
            calculateDailyTarget();
        }

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

        // Delete confirmation
        function confirmDeleteTarget(id) {
            if (confirm('Are you sure you want to delete this target? This action cannot be undone.')) {
                window.location.href = '?delete_target_id=' + id + '&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&officer_id=<?php echo $selected_officer; ?>';
            }
        }
    </script>
</body>
</html>