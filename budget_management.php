<?php
require_once 'BudgetManager.php';
require_once 'config.php';
require_once 'functions.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Accounts'
];

$manager = new BudgetManager();

// Check access permissions
$can_create = $manager->checkAccess($staff['user_id'], 'can_create_budget');
$can_edit = $manager->checkAccess($staff['user_id'], 'can_edit_budget');
$can_delete = $manager->checkAccess($staff['user_id'], 'can_delete_budget');
$can_view = $manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

// Handle form submissions
$message = '';
$error = '';
$upload_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_save_budget']) && ($can_create || $can_edit)) {
        $budget_data = [
            'id' => $_POST['budget_id'] ?? null,
            'acct_id' => $_POST['acct_id'],
            'acct_desc' => $_POST['acct_desc'],
            'budget_year' => $_POST['budget_year'],
            'january_budget' => preg_replace('/[,]/', '', $_POST['january_budget']),
            'february_budget' => preg_replace('/[,]/', '', $_POST['february_budget']),
            'march_budget' => preg_replace('/[,]/', '', $_POST['march_budget']),
            'april_budget' => preg_replace('/[,]/', '', $_POST['april_budget']),
            'may_budget' => preg_replace('/[,]/', '', $_POST['may_budget']),
            'june_budget' => preg_replace('/[,]/', '', $_POST['june_budget']),
            'july_budget' => preg_replace('/[,]/', '', $_POST['july_budget']),
            'august_budget' => preg_replace('/[,]/', '', $_POST['august_budget']),
            'september_budget' => preg_replace('/[,]/', '', $_POST['september_budget']),
            'october_budget' => preg_replace('/[,]/', '', $_POST['october_budget']),
            'november_budget' => preg_replace('/[,]/', '', $_POST['november_budget']),
            'december_budget' => preg_replace('/[,]/', '', $_POST['december_budget']),
            'status' => $_POST['status'],
            'user_id' => $staff['user_id']
        ];
        
        $result = $manager->saveBudgetLine($budget_data);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    if (isset($_POST['btn_update_performance'])) {
        $result = $manager->updateBudgetPerformance($_POST['performance_year'], $_POST['performance_month']);
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
    
    // Handle Excel upload
    if (isset($_POST['btn_upload_excel']) && $can_create) {
        if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            $result = $manager->processExcelUpload($_FILES['excel_file'], $staff['user_id']);
            if ($result['success']) {
                $message = $result['message'];
                if (!empty($result['warnings'])) {
                    $upload_errors = $result['warnings'];
                }
            } else {
                $error = $result['message'];
                if (!empty($result['errors'])) {
                    $upload_errors = $result['errors'];
                }
            }
        } else {
            $error = 'Please select a valid Excel file to upload.';
        }
    }
}

// Handle deletion
if (isset($_GET['delete_id']) && $can_delete) {
    if ($manager->deleteBudgetLine($_GET['delete_id'])) {
        $message = 'Budget line deleted successfully!';
    } else {
        $error = 'Error deleting budget line!';
    }
}

// Get data for display
$selected_year = $_GET['year'] ?? date('Y');
$edit_id = $_GET['edit_id'] ?? null;

$budget_lines = $manager->getBudgetLines($selected_year);
$income_lines = $manager->getActiveIncomeLines();
$edit_budget = $edit_id ? $manager->getBudgetLine($edit_id) : null;
$performance_data = $manager->getBudgetPerformance($selected_year);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Budget Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Budget Management</h1>
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
                        <h2 class="text-2xl font-bold text-gray-900">Annual Budget Management</h2>
                        <p class="text-gray-600">Manage income line budgets and track performance</p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-4">
                        <!-- Year Selection -->
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select name="month" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 3; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Officer</label>
                                <select name="officer_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Officers</option>
                                    <?php foreach ($officers as $officer): ?>
                                        <option value="<?php echo $officer['user_id']; ?>" 
                                                <?php echo $selected_officer == $officer['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo $officer['full_name']; ?> - <?php echo $officer['department']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Load Data
                                </button>
                            </div>
                        </form>
                        
                        <!-- Legacy year selection -->
                        <form method="GET" class="hidden gap-2">
                            <select name="year" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <?php for ($y = date('Y') - 2; $y <= date('Y') + 3; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Load Year
                            </button>
                        </form>
                        
                        <!-- Quick Actions -->
                        <div class="flex gap-2">
                            <?php if ($can_create): ?>
                            <button onclick="showBudgetForm()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                <i class="fas fa-plus mr-2"></i>Add Budget
                            </button>
                            <button onclick="showExcelUpload()" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                <i class="fas fa-file-excel mr-2"></i>Excel Upload
                            </button>
                            <?php endif; ?>
                            
                            <form method="POST" class="inline">
                                <input type="hidden" name="performance_year" value="<?php echo $selected_year; ?>">
                                <input type="hidden" name="performance_month" value="<?php echo $selected_month; ?>">
                                <button type="submit" name="btn_update_performance" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                                    <i class="fas fa-sync mr-2"></i>Update Performance
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Excel Upload Errors -->
        <?php if (!empty($upload_errors)): ?>
        <div class="mb-6 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
            <h4 class="font-bold mb-2">Upload Warnings/Errors:</h4>
            <ul class="list-disc list-inside text-sm">
                <?php foreach ($upload_errors as $upload_error): ?>
                    <li><?php echo htmlspecialchars($upload_error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Excel Upload Modal -->
        <div id="excelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Excel Budget Upload</h3>
                            <button onclick="hideExcelUpload()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="mb-6">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="font-medium text-blue-900 mb-2">Instructions:</h4>
                                <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                                    <li>Download the Excel template below</li>
                                    <li>Fill in the budget amounts for each income line and month</li>
                                    <li>Save the file and upload it using the form below</li>
                                    <li>Review the uploaded data before final submission</li>
                                </ol>
                            </div>
                        </div>

                        <div class="mb-6">
                            <a href="download_budget_template.php?year=<?php echo $selected_year; ?>" 
                               class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                <i class="fas fa-download mr-2"></i>
                                Download Excel Template
                            </a>
                        </div>

                        <form method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Select Excel File</label>
                                <input type="file" name="excel_file" accept=".xlsx,.xls" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Supported formats: .xlsx, .xls</p>
                            </div>

                            <div class="flex justify-end space-x-4 pt-6 border-t">
                                <button type="button" onclick="hideExcelUpload()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" name="btn_upload_excel"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-upload mr-2"></i>
                                    Upload Budget
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Form Modal -->
        <div id="budgetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <?php echo $edit_budget ? 'Edit' : 'Add'; ?> Budget Line
                            </h3>
                            <button onclick="hideBudgetForm()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <form method="POST" class="space-y-6">
                            <?php if ($edit_budget): ?>
                                <input type="hidden" name="budget_id" value="<?php echo $edit_budget['id']; ?>">
                            <?php endif; ?>

                            <!-- Basic Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Income Line</label>
                                    <select name="acct_id" id="acct_id" required onchange="updateAccountDesc()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Income Line</option>
                                        <?php foreach ($income_lines as $line): ?>
                                            <option value="<?php echo $line['acct_id']; ?>" 
                                                    data-desc="<?php echo $line['acct_desc']; ?>"
                                                    <?php echo ($edit_budget && $edit_budget['acct_id'] === $line['acct_id']) ? 'selected' : ''; ?>>
                                                <?php echo $line['acct_desc']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Budget Year</label>
                                    <select name="budget_year" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <?php for ($y = date('Y'); $y <= date('Y') + 3; $y++): ?>
                                            <option value="<?php echo $y; ?>" 
                                                    <?php echo ($edit_budget && $edit_budget['budget_year'] == $y) ? 'selected' : ($y == $selected_year ? 'selected' : ''); ?>>
                                                <?php echo $y; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <input type="hidden" name="acct_desc" id="acct_desc" value="<?php echo $edit_budget['acct_desc'] ?? ''; ?>">

                            <!-- Monthly Budgets -->
                            <div>
                                <h4 class="text-lg font-medium text-gray-900 mb-4">Monthly Budget Allocation</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php 
                                    $months = [
                                        'january' => 'January', 'february' => 'February', 'march' => 'March',
                                        'april' => 'April', 'may' => 'May', 'june' => 'June',
                                        'july' => 'July', 'august' => 'August', 'september' => 'September',
                                        'october' => 'October', 'november' => 'November', 'december' => 'December'
                                    ];
                                    
                                    foreach ($months as $key => $label): 
                                    ?>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo $label; ?></label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                            <input type="text" name="<?php echo $key; ?>_budget" 
                                                   value="<?php echo $edit_budget ? number_format($edit_budget[$key . '_budget']) : ''; ?>"
                                                   class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 budget-input"
                                                   onkeyup="formatCurrency(this); calculateTotal();">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Total and Status -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Annual Total</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-gray-500">₦</span>
                                        <input type="text" id="annual_total" readonly
                                               class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 text-lg font-bold">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <select name="status" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        <option value="Active" <?php echo ($edit_budget && $edit_budget['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo ($edit_budget && $edit_budget['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-end space-x-4 pt-6 border-t">
                                <button type="button" onclick="hideBudgetForm()" 
                                        class="px-6 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" name="btn_save_budget"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Budget
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget Lines Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Budget Lines - <?php echo $selected_year; ?></h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Income Line</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Q1</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Q2</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Q3</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Q4</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Annual Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($budget_lines)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                No budget lines found for <?php echo $selected_year; ?>. 
                                <?php if ($can_create): ?>
                                    <button onclick="showBudgetForm()" class="text-blue-600 hover:text-blue-800 ml-2">
                                        Create your first budget line
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($budget_lines as $budget): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo $budget['acct_desc']; ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo $budget['acct_id']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($budget['january_budget'] + $budget['february_budget'] + $budget['march_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($budget['april_budget'] + $budget['may_budget'] + $budget['june_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($budget['july_budget'] + $budget['august_budget'] + $budget['september_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    ₦<?php echo number_format($budget['october_budget'] + $budget['november_budget'] + $budget['december_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                    ₦<?php echo number_format($budget['annual_budget']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        <?php echo $budget['status'] === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $budget['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="budget_details.php?id=<?php echo $budget['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($can_edit): ?>
                                        <a href="?edit_id=<?php echo $budget['id']; ?>&year=<?php echo $selected_year; ?>" 
                                           onclick="editBudget(<?php echo $budget['id']; ?>)"
                                           class="text-green-600 hover:text-green-800" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($can_delete): ?>
                                        <button onclick="confirmDelete(<?php echo $budget['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Budget Management Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="officer_target_management.php" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-bullseye mr-2"></i>
                    Officer Targets
                </a>
                
                <a href="budget_performance_analysis.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <i class="fas fa-chart-line mr-2"></i>
                    Performance Analysis
                </a>
                
                <a href="budget_variance_report.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>
                    Variance Report
                </a>
                
                <a href="budget_forecasting.php?year=<?php echo $selected_year; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-crystal-ball mr-2"></i>
                    Budget Forecasting
                </a>
            </div>
        </div>
    </div>

    <script>
        // Show/hide budget form
        function showBudgetForm() {
            document.getElementById('budgetModal').classList.remove('hidden');
        }

        function hideBudgetForm() {
            document.getElementById('budgetModal').classList.add('hidden');
        }

        // Show/hide excel upload
        function showExcelUpload() {
            document.getElementById('excelModal').classList.remove('hidden');
        }

        function hideExcelUpload() {
            document.getElementById('excelModal').classList.add('hidden');
        }

        // Update account description when income line is selected
        function updateAccountDesc() {
            const select = document.getElementById('acct_id');
            const selectedOption = select.options[select.selectedIndex];
            const descField = document.getElementById('acct_desc');
            
            if (selectedOption.dataset.desc) {
                descField.value = selectedOption.dataset.desc;
            }
        }

        // Format currency input
        function formatCurrency(input) {
            let value = input.value.replace(/[^\d]/g, '');
            if (value) {
                input.value = parseInt(value).toLocaleString();
            }
        }

        // Calculate total budget
        function calculateTotal() {
            const inputs = document.querySelectorAll('.budget-input');
            let total = 0;
            
            inputs.forEach(input => {
                const value = input.value.replace(/[^\d]/g, '');
                if (value) {
                    total += parseInt(value);
                }
            });
            
            document.getElementById('annual_total').value = total.toLocaleString();
        }

        // Delete confirmation
        function confirmDelete(id) {
            if (confirm('Are you sure you want to delete this budget line? This action cannot be undone.')) {
                window.location.href = '?delete_id=' + id + '&year=<?php echo $selected_year; ?>';
            }
        }

        // Edit budget
        function editBudget(id) {
            showBudgetForm();
        }

        // Show edit form if edit_id is present
        <?php if ($edit_id): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showBudgetForm();
            calculateTotal();
        });
        <?php endif; ?>
    </script>
</body>
</html>