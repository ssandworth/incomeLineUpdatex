<?php
require_once 'Database.php';
require_once 'config.php';

// Start session
session_start();

// Mock session data for demonstration
$staff = [
    'user_id' => 1,
    'full_name' => 'John Doe',
    'department' => 'Leasing'
];

class OfficerRentDetailsAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get officer information
     */
    public function getOfficerInfo($officer_id) {
        $this->db->query("
            SELECT user_id, full_name, first_name, department, level, phone, email 
            FROM staffs 
            WHERE user_id = :officer_id
        ");
        $this->db->bind(':officer_id', $officer_id);
        return $this->db->single();
    }
    
    /**
     * Get detailed shop analysis for officer
     */
    public function getOfficerShopAnalysis($officer_id, $month, $year, $collection_type = 'rent') {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $period_string = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        if ($collection_type === 'service_charge') {
            $expected_field = 'expected_service_charge';
        } else {
            $expected_field = 'expected_rent';
        }
        
        $this->db->query("
            SELECT 
                c.id as shop_id,
                c.shop_no,
                c.customer_name,
                c.shop_size,
                c.lease_tenure,
                c.lease_start_date,
                c.lease_end_date,
                c.{$expected_field} as expected_amount,
                c.facility_type,
                COALESCE(SUM(CASE WHEN ca.payment_month = :period_string 
                    AND YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) = :month 
                    THEN ca.amount_paid ELSE 0 END), 0) as current_collected,
                COALESCE(SUM(CASE WHEN ca.payment_month = :period_string 
                    AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) > :month) 
                         OR YEAR(ca.date_of_payment) > :year) 
                    THEN ca.amount_paid ELSE 0 END), 0) as advance_collected,
                COALESCE(SUM(CASE WHEN ca.payment_month = :period_string 
                    AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) < :month) 
                         OR YEAR(ca.date_of_payment) < :year) 
                    THEN ca.amount_paid ELSE 0 END), 0) as arrears_collected,
                COALESCE(SUM(CASE WHEN ca.payment_month = :period_string 
                    THEN ca.amount_paid ELSE 0 END), 0) as total_collected
            FROM customers c
            LEFT JOIN collection_analysis{$table_suffix} ca ON c.shop_no = ca.shop_no
            WHERE c.staff_id = :officer_id
            " . ($collection_type === 'rent' ? "AND c.lease_start_date LIKE :month_pattern" : "") . "
            GROUP BY c.id, c.shop_no, c.customer_name, c.shop_size, c.lease_tenure, 
                     c.lease_start_date, c.lease_end_date, c.{$expected_field}, c.facility_type
            ORDER BY c.shop_no ASC
        ");
        
        $this->db->bind(':officer_id', $officer_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $this->db->bind(':period_string', $period_string);
        
        if ($collection_type === 'rent') {
            $this->db->bind(':month_pattern', '%-' . sprintf('%02d', $month) . '-%');
        }
        
        $shops = $this->db->resultSet();
        
        // Calculate balances and additional metrics
        foreach ($shops as &$shop) {
            $shop['balance'] = $shop['expected_amount'] - $shop['total_collected'];
            $shop['collection_rate'] = $shop['expected_amount'] > 0 ? 
                ($shop['total_collected'] / $shop['expected_amount']) * 100 : 0;
            
            // Determine status
            if ($shop['collection_rate'] >= 100) {
                $shop['status'] = 'Fully Paid';
                $shop['status_class'] = 'bg-green-100 text-green-800';
            } elseif ($shop['collection_rate'] >= 80) {
                $shop['status'] = 'Nearly Complete';
                $shop['status_class'] = 'bg-blue-100 text-blue-800';
            } elseif ($shop['collection_rate'] >= 50) {
                $shop['status'] = 'Partial Payment';
                $shop['status_class'] = 'bg-yellow-100 text-yellow-800';
            } elseif ($shop['collection_rate'] > 0) {
                $shop['status'] = 'Minimal Payment';
                $shop['status_class'] = 'bg-orange-100 text-orange-800';
            } else {
                $shop['status'] = 'No Payment';
                $shop['status_class'] = 'bg-red-100 text-red-800';
            }
        }
        
        return $shops;
    }
    
    /**
     * Get officer performance summary
     */
    public function getOfficerSummary($officer_id, $month, $year, $collection_type = 'rent') {
        $shops = $this->getOfficerShopAnalysis($officer_id, $month, $year, $collection_type);
        
        $summary = [
            'total_shops' => count($shops),
            'total_expected' => array_sum(array_column($shops, 'expected_amount')),
            'total_collected' => array_sum(array_column($shops, 'total_collected')),
            'current_collected' => array_sum(array_column($shops, 'current_collected')),
            'advance_collected' => array_sum(array_column($shops, 'advance_collected')),
            'arrears_collected' => array_sum(array_column($shops, 'arrears_collected')),
            'total_balance' => array_sum(array_column($shops, 'balance')),
            'fully_paid_shops' => count(array_filter($shops, function($shop) { return $shop['collection_rate'] >= 100; })),
            'partial_paid_shops' => count(array_filter($shops, function($shop) { return $shop['collection_rate'] > 0 && $shop['collection_rate'] < 100; })),
            'unpaid_shops' => count(array_filter($shops, function($shop) { return $shop['collection_rate'] == 0; }))
        ];
        
        $summary['collection_rate'] = $summary['total_expected'] > 0 ? 
            ($summary['total_collected'] / $summary['total_expected']) * 100 : 0;
        
        return $summary;
    }
}

$analyzer = new OfficerRentDetailsAnalyzer();

$officer_id = $_GET['officer_id'] ?? null;
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$collection_type = $_GET['type'] ?? 'rent';

if (!$officer_id) {
    header('Location: officers_rent_mpr.php');
    exit;
}

$officer_info = $analyzer->getOfficerInfo($officer_id);
if (!$officer_info) {
    header('Location: officers_rent_mpr.php');
    exit;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1));
$shops_analysis = $analyzer->getOfficerShopAnalysis($officer_id, $month, $year, $collection_type);
$summary = $analyzer->getOfficerSummary($officer_id, $month, $year, $collection_type);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo $officer_info['full_name']; ?> <?php echo ucfirst($collection_type); ?> Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="officers_rent_mpr.php?smonth=<?php echo $month; ?>&syear=<?php echo $year; ?>&type=<?php echo $collection_type; ?>" 
                       class="text-blue-600 hover:text-blue-800 mr-4">← Back to MPR</a>
                    <h1 class="text-xl font-bold text-gray-900">Officer <?php echo ucfirst($collection_type); ?> Details</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-700"><?php echo $month_name . ' ' . $year; ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Officer Profile -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
                        <?php echo strtoupper(substr($officer_info['full_name'], 0, 2)); ?>
                    </div>
                    <div class="ml-4">
                        <h2 class="text-xl font-bold text-gray-900"><?php echo $officer_info['full_name']; ?></h2>
                        <p class="text-gray-600"><?php echo $officer_info['level']; ?> - <?php echo $officer_info['department']; ?></p>
                        <?php if ($officer_info['phone']): ?>
                            <p class="text-sm text-gray-500"><?php echo $officer_info['phone']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">₦<?php echo number_format($summary['total_collected']); ?></div>
                    <div class="text-sm text-gray-500">Total <?php echo ucfirst($collection_type); ?> Collected</div>
                    <div class="text-sm <?php echo $summary['collection_rate'] >= 80 ? 'text-green-600' : ($summary['collection_rate'] >= 60 ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo number_format($summary['collection_rate'], 1); ?>% Collection Rate
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Shops</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $summary['total_shops']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Fully Paid</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $summary['fully_paid_shops']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Partial Payment</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $summary['partial_paid_shops']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">No Payment</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $summary['unpaid_shops']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Collection Breakdown Chart -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Collection Breakdown</h3>
            <div class="relative h-48">
                <canvas id="breakdownChart"></canvas>
            </div>
        </div>

        <!-- Detailed Shop Analysis -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Shop-by-Shop <?php echo ucfirst($collection_type); ?> Analysis
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shop No</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facility Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Advance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Arrears</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($shops_analysis as $index => $shop): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $index + 1; ?>.
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="shop_details.php?shop_id=<?php echo $shop['shop_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline">
                                    <?php echo $shop['shop_no']; ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo ucwords(strtolower($shop['customer_name'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo $shop['shop_size']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo ucwords(strtolower($shop['facility_type'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($shop['expected_amount']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($shop['current_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($shop['advance_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($shop['arrears_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($shop['total_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $shop['status_class']; ?>">
                                    <?php echo $shop['status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <span class="<?php echo $shop['balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?> font-medium">
                                    ₦<?php echo number_format(abs($shop['balance'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="5" class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['total_expected']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['current_collected']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['advance_collected']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['arrears_collected']); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['total_collected']); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php echo number_format($summary['collection_rate'], 1); ?>%
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format($summary['total_balance']); ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Collection Breakdown Chart
        const breakdownCtx = document.getElementById('breakdownChart').getContext('2d');
        
        new Chart(breakdownCtx, {
            type: 'doughnut',
            data: {
                labels: ['Current Period', 'Advance', 'Arrears'],
                datasets: [{
                    data: [
                        <?php echo $summary['current_collected']; ?>,
                        <?php echo $summary['advance_collected']; ?>,
                        <?php echo $summary['arrears_collected']; ?>
                    ],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₦' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>