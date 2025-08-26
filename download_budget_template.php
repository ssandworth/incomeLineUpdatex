<?php
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

$manager = new BudgetManager();

// Check access permissions
$can_view = $manager->checkAccess($staff['user_id'], 'can_view_budget');

if (!$can_view) {
    header('Location: index.php?error=access_denied');
    exit;
}

$year = $_GET['year'] ?? date('Y');

// Get active income lines
$income_lines = $manager->getActiveIncomeLines();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Budget_Template_' . $year . '.xls"');
header('Cache-Control: max-age=0');

// Start output buffering
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Budget Template <?php echo $year; ?></title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .number { text-align: right; }
        .header { background-color: #4472C4; color: white; font-weight: bold; }
        .subheader { background-color: #D9E1F2; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <th colspan="14" class="header">BUDGET TEMPLATE FOR <?php echo $year; ?></th>
        </tr>
        <tr>
            <th colspan="14" class="subheader">Instructions: Fill in the budget amounts for each income line and month. Do not modify the structure or headers.</th>
        </tr>
        <tr>
            <th>Account ID</th>
            <th>Income Line Description</th>
            <th>January</th>
            <th>February</th>
            <th>March</th>
            <th>April</th>
            <th>May</th>
            <th>June</th>
            <th>July</th>
            <th>August</th>
            <th>September</th>
            <th>October</th>
            <th>November</th>
            <th>December</th>
        </tr>
        <?php foreach ($income_lines as $line): ?>
        <tr>
            <td><?php echo $line['acct_id']; ?></td>
            <td><?php echo $line['acct_desc']; ?></td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
            <td class="number">0</td>
        </tr>
        <?php endforeach; ?>
        <tr>
            <th colspan="14" class="subheader">
                NOTES: 
                1. Enter amounts without commas or currency symbols
                2. Use whole numbers (e.g., 500000 not 500,000)
                3. Leave cells as 0 if no budget is planned for that month
                4. Do not add or remove rows
                5. Save as Excel format (.xlsx or .xls)
            </th>
        </tr>
    </table>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
exit;
?>