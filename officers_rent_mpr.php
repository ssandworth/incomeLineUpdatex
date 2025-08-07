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

class LeasingMPRAnalyzer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get leasing officers
     */
    public function getLeasingOfficers() {
        $this->db->query("
            SELECT user_id, full_name, first_name, department 
            FROM staffs 
            WHERE department = 'Wealth Creation' 
            AND level = 'leasing officer'
            ORDER BY full_name ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get facility types
     */
    public function getFacilityTypes() {
        $this->db->query("
            SELECT DISTINCT facility_type 
            FROM shops_facility_type 
            ORDER BY facility_type ASC
        ");
        return $this->db->resultSet();
    }
    
    /**
     * Get expected rent for officer and period
     */
    public function getExpectedRent($staff_id, $month, $year, $collection_type = 'rent') {
        if ($collection_type === 'service_charge') {
            $this->db->query("
                SELECT SUM(expected_service_charge) as expected 
                FROM customers 
                WHERE staff_id = :staff_id
            ");
        } else {
            $this->db->query("
                SELECT SUM(expected_rent) as expected 
                FROM customers 
                WHERE lease_start_date LIKE :month_pattern 
                AND staff_id = :staff_id
            ");
            $this->db->bind(':month_pattern', '%-' . sprintf('%02d', $month) . '-%');
        }
        
        $this->db->bind(':staff_id', $staff_id);
        $result = $this->db->single();
        return $result['expected'] ?? 0;
    }
    
    /**
     * Get collections for officer and period
     */
    public function getCollections($staff_id, $month, $year, $collection_type = 'rent') {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $period_string = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        // Current period collections
        $this->db->query("
            SELECT 
                SUM(CASE WHEN YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) = :month 
                     AND ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as current_collected,
                SUM(CASE WHEN ca.payment_month = :period_string 
                     AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) > :month) 
                          OR YEAR(ca.date_of_payment) > :year) THEN ca.amount_paid ELSE 0 END) as advance_collected,
                SUM(CASE WHEN ca.payment_month = :period_string 
                     AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) < :month) 
                          OR YEAR(ca.date_of_payment) < :year) THEN ca.amount_paid ELSE 0 END) as arrears_collected,
                SUM(CASE WHEN ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as total_collected
            FROM collection_analysis{$table_suffix} ca
            JOIN customers c ON ca.shop_no = c.shop_no
            WHERE c.staff_id = :staff_id
        ");
        
        $this->db->bind(':staff_id', $staff_id);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $this->db->bind(':period_string', $period_string);
        
        return $this->db->single();
    }
    
    /**
     * Get facility type collections
     */
    public function getFacilityTypeCollections($facility_type, $month, $year, $collection_type = 'rent') {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $period_string = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        // Expected amount
        if ($collection_type === 'service_charge') {
            $this->db->query("
                SELECT SUM(expected_service_charge) as expected 
                FROM customers 
                WHERE facility_type = :facility_type
            ");
        } else {
            $this->db->query("
                SELECT SUM(expected_rent) as expected 
                FROM customers 
                WHERE lease_start_date LIKE :month_pattern 
                AND facility_type = :facility_type
            ");
            $this->db->bind(':month_pattern', '%-' . sprintf('%02d', $month) . '-%');
        }
        $this->db->bind(':facility_type', $facility_type);
        $expected_result = $this->db->single();
        
        // Collections
        $this->db->query("
            SELECT 
                SUM(CASE WHEN YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) = :month 
                     AND ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as current_collected,
                SUM(CASE WHEN ca.payment_month = :period_string 
                     AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) > :month) 
                          OR YEAR(ca.date_of_payment) > :year) THEN ca.amount_paid ELSE 0 END) as advance_collected,
                SUM(CASE WHEN ca.payment_month = :period_string 
                     AND ((YEAR(ca.date_of_payment) = :year AND MONTH(ca.date_of_payment) < :month) 
                          OR YEAR(ca.date_of_payment) < :year) THEN ca.amount_paid ELSE 0 END) as arrears_collected,
                SUM(CASE WHEN ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as total_collected
            FROM collection_analysis{$table_suffix} ca
            JOIN customers c ON ca.shop_no = c.shop_no
            WHERE c.facility_type = :facility_type
        ");
        
        $this->db->bind(':facility_type', $facility_type);
        $this->db->bind(':month', $month);
        $this->db->bind(':year', $year);
        $this->db->bind(':period_string', $period_string);
        
        $collections = $this->db->single();
        
        return [
            'expected' => $expected_result['expected'] ?? 0,
            'current_collected' => $collections['current_collected'] ?? 0,
            'advance_collected' => $collections['advance_collected'] ?? 0,
            'arrears_collected' => $collections['arrears_collected'] ?? 0,
            'total_collected' => $collections['total_collected'] ?? 0,
            'balance' => ($expected_result['expected'] ?? 0) - ($collections['total_collected'] ?? 0)
        ];
    }
    
    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics($month, $year, $collection_type = 'rent') {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $period_string = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        // Current month total
        $this->db->query("
            SELECT 
                SUM(CASE WHEN ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as current_total,
                COUNT(DISTINCT c.staff_id) as active_officers,
                COUNT(DISTINCT ca.shop_no) as paying_shops
            FROM collection_analysis{$table_suffix} ca
            JOIN customers c ON ca.shop_no = c.shop_no
            JOIN staffs s ON c.staff_id = s.user_id
            WHERE s.department = 'Wealth Creation' 
            AND s.level = 'leasing officer'
        ");
        
        $this->db->bind(':period_string', $period_string);
        $current = $this->db->single();
        
        // Previous month total
        $prev_month = $month == 1 ? 12 : $month - 1;
        $prev_year = $month == 1 ? $year - 1 : $year;
        $prev_period_string = date('F Y', mktime(0, 0, 0, $prev_month, 1, $prev_year));
        
        $this->db->query("
            SELECT SUM(CASE WHEN ca.payment_month = :prev_period_string THEN ca.amount_paid ELSE 0 END) as previous_total
            FROM collection_analysis{$table_suffix} ca
            JOIN customers c ON ca.shop_no = c.shop_no
            JOIN staffs s ON c.staff_id = s.user_id
            WHERE s.department = 'Wealth Creation' 
            AND s.level = 'leasing officer'
        ");
        
        $this->db->bind(':prev_period_string', $prev_period_string);
        $previous = $this->db->single();
        
        // Calculate growth
        $growth = 0;
        if ($previous['previous_total'] > 0) {
            $growth = (($current['current_total'] - $previous['previous_total']) / $previous['previous_total']) * 100;
        }
        
        return [
            'current_total' => $current['current_total'] ?? 0,
            'previous_total' => $previous['previous_total'] ?? 0,
            'growth_percentage' => $growth,
            'active_officers' => $current['active_officers'] ?? 0,
            'paying_shops' => $current['paying_shops'] ?? 0
        ];
    }
    
    /**
     * Get top performing officers
     */
    public function getTopPerformingOfficers($month, $year, $collection_type = 'rent', $limit = 5) {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $period_string = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        
        $this->db->query("
            SELECT 
                s.user_id,
                s.full_name,
                s.first_name,
                SUM(CASE WHEN ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as total_collected,
                COUNT(DISTINCT ca.shop_no) as shops_collected,
                COUNT(ca.id) as transaction_count
            FROM staffs s
            LEFT JOIN customers c ON s.user_id = c.staff_id
            LEFT JOIN collection_analysis{$table_suffix} ca ON c.shop_no = ca.shop_no
                AND ca.payment_month = :period_string
            WHERE s.department = 'Wealth Creation' 
            AND s.level = 'leasing officer'
            GROUP BY s.user_id, s.full_name, s.first_name
            ORDER BY total_collected DESC
            LIMIT :limit
        ");
        
        $this->db->bind(':period_string', $period_string);
        $this->db->bind(':limit', $limit);
        
        return $this->db->resultSet();
    }
    
    /**
     * Get collection trends (last 6 months)
     */
    public function getCollectionTrends($collection_type = 'rent') {
        $table_suffix = $collection_type === 'service_charge' ? '_arena' : '';
        $trends = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $trend_month = date('n') - $i;
            $trend_year = date('Y');
            
            if ($trend_month <= 0) {
                $trend_month += 12;
                $trend_year--;
            }
            
            $period_string = date('F Y', mktime(0, 0, 0, $trend_month, 1, $trend_year));
            
            $this->db->query("
                SELECT SUM(CASE WHEN ca.payment_month = :period_string THEN ca.amount_paid ELSE 0 END) as monthly_total
                FROM collection_analysis{$table_suffix} ca
                JOIN customers c ON ca.shop_no = c.shop_no
                JOIN staffs s ON c.staff_id = s.user_id
                WHERE s.department = 'Wealth Creation' 
                AND s.level = 'leasing officer'
            ");
            
            $this->db->bind(':period_string', $period_string);
            $result = $this->db->single();
            
            $trends[] = [
                'month' => $trend_month,
                'year' => $trend_year,
                'month_name' => date('M Y', mktime(0, 0, 0, $trend_month, 1, $trend_year)),
                'total' => $result['monthly_total'] ?? 0
            ];
        }
        
        return $trends;
    }
}

$analyzer = new LeasingMPRAnalyzer();

// Get current date info
$selected_month = $_GET['smonth'] ?? date('n');
$selected_year = $_GET['syear'] ?? date('Y');
$collection_type = $_GET['type'] ?? 'rent';
$selected_month_name = date('F', mktime(0, 0, 0, $selected_month, 1));

// Get data
$officers = $analyzer->getLeasingOfficers();
$facility_types = $analyzer->getFacilityTypes();
$analytics = $analyzer->getPerformanceAnalytics($selected_month, $selected_year, $collection_type);
$top_performers = $analyzer->getTopPerformingOfficers($selected_month, $selected_year, $collection_type);
$trends = $analyzer->getCollectionTrends($collection_type);

// Calculate officer performance data
$officer_performance = [];
foreach ($officers as $officer) {
    $expected = $analyzer->getExpectedRent($officer['user_id'], $selected_month, $selected_year, $collection_type);
    $collections = $analyzer->getCollections($officer['user_id'], $selected_month, $selected_year, $collection_type);
    
    $collection_rate = $expected > 0 ? ($collections['total_collected'] / $expected) * 100 : 0;
    
    $officer_performance[] = [
        'officer' => $officer,
        'expected' => $expected,
        'collections' => $collections,
        'collection_rate' => $collection_rate,
        'balance' => $expected - $collections['total_collected']
    ];
}

// Sort by total collected
usort($officer_performance, function($a, $b) {
    return $b['collections']['total_collected'] <=> $a['collections']['total_collected'];
});

// Calculate facility type performance
$facility_performance = [];
foreach ($facility_types as $facility) {
    $facility_data = $analyzer->getFacilityTypeCollections(
        $facility['facility_type'], 
        $selected_month, 
        $selected_year, 
        $collection_type
    );
    
    $facility_performance[] = [
        'facility_type' => $facility['facility_type'],
        'data' => $facility_data,
        'collection_rate' => $facility_data['expected'] > 0 ? 
            ($facility_data['total_collected'] / $facility_data['expected']) * 100 : 0
    ];
}

// Sort by total collected
usort($facility_performance, function($a, $b) {
    return $b['data']['total_collected'] <=> $a['data']['total_collected'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Leasing Officers <?php echo ucfirst($collection_type); ?> MPR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1 class="text-xl font-bold text-gray-900"><?php echo APP_NAME; ?> - Leasing Officers MPR</h1>
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
                        <h2 class="text-2xl font-bold text-gray-900">
                            <?php echo ucfirst($collection_type); ?> Collection Performance Report
                        </h2>
                        <p class="text-gray-600"><?php echo $selected_month_name . ' ' . $selected_year; ?> Collection Summary</p>
                    </div>
                    
                    <!-- Period and Type Selection Form -->
                    <form method="GET" class="flex flex-col sm:flex-row gap-4">
                        <select name="type" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="rent" <?php echo $collection_type === 'rent' ? 'selected' : ''; ?>>Rent Collection</option>
                            <option value="service_charge" <?php echo $collection_type === 'service_charge' ? 'selected' : ''; ?>>Service Charge</option>
                        </select>
                        
                        <select name="smonth" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Month</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select name="syear" class="px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Year</option>
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Load Report
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Analytics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Collections</p>
                        <p class="text-2xl font-bold text-gray-900">₦<?php echo number_format($analytics['current_total']); ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Growth Rate</p>
                        <p class="text-2xl font-bold <?php echo $analytics['growth_percentage'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo number_format($analytics['growth_percentage'], 1); ?>%
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Officers</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $analytics['active_officers']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Paying Shops</p>
                        <p class="text-2xl font-bold text-gray-900"><?php echo $analytics['paying_shops']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Collection Trends Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">6-Month Collection Trends</h3>
                <div class="relative h-48">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>

            <!-- Top Performers Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Top Performing Officers</h3>
                <div class="relative h-48">
                    <canvas id="performersChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Facility Type Performance -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Facility Type Performance - <?php echo ucfirst($collection_type); ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facility Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Period</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Advance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Arrears</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Collected</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Collection Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($facility_performance as $facility): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo ucwords(strtolower($facility['facility_type'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($facility['data']['expected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($facility['data']['current_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($facility['data']['advance_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($facility['data']['arrears_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($facility['data']['total_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $facility['collection_rate'] >= 80 ? 'bg-green-500' : ($facility['collection_rate'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $facility['collection_rate']); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($facility['collection_rate'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <span class="<?php echo $facility['data']['balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?> font-medium">
                                    ₦<?php echo number_format(abs($facility['data']['balance'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Officers Performance Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    Leasing Officers Performance - <?php echo ucfirst($collection_type); ?>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Officer</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Period</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Advance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Arrears</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Collected</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Collection Rate</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($officer_performance as $index => $performance): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-bold
                                    <?php echo $index === 0 ? 'bg-yellow-500' : ($index === 1 ? 'bg-gray-500' : ($index === 2 ? 'bg-orange-500' : 'bg-blue-500')); ?>">
                                    <?php echo $index + 1; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo $performance['officer']['full_name']; ?></div>
                                <div class="text-xs text-gray-500">Leasing Officer</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['expected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['collections']['current_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['collections']['advance_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                ₦<?php echo number_format($performance['collections']['arrears_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-bold">
                                ₦<?php echo number_format($performance['collections']['total_collected']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <div class="<?php echo $performance['collection_rate'] >= 80 ? 'bg-green-500' : ($performance['collection_rate'] >= 60 ? 'bg-yellow-500' : 'bg-red-500'); ?> h-2 rounded-full" 
                                             style="width: <?php echo min(100, $performance['collection_rate']); ?>%"></div>
                                    </div>
                                    <span class="ml-2 text-xs text-gray-500">
                                        <?php echo number_format($performance['collection_rate'], 1); ?>%
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <span class="<?php echo $performance['balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?> font-medium">
                                    ₦<?php echo number_format(abs($performance['balance'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="officer_rent_details.php?officer_id=<?php echo $performance['officer']['user_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="View Details">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                    <a href="officer_rent_analysis.php?officer_id=<?php echo $performance['officer']['user_id']; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                                       class="text-green-600 hover:text-green-800" title="Detailed Analysis">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="2" class="px-6 py-3 text-left text-sm font-bold text-gray-900">TOTALS</th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column($officer_performance, 'expected'))); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column(array_column($officer_performance, 'collections'), 'current_collected'))); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column(array_column($officer_performance, 'collections'), 'advance_collected'))); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column(array_column($officer_performance, 'collections'), 'arrears_collected'))); ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column(array_column($officer_performance, 'collections'), 'total_collected'))); ?>
                            </th>
                            <th class="px-6 py-3 text-center text-sm font-bold text-gray-900">
                                <?php 
                                $total_expected = array_sum(array_column($officer_performance, 'expected'));
                                $total_collected = array_sum(array_column(array_column($officer_performance, 'collections'), 'total_collected'));
                                $overall_rate = $total_expected > 0 ? ($total_collected / $total_expected) * 100 : 0;
                                echo number_format($overall_rate, 1) . '%';
                                ?>
                            </th>
                            <th class="px-6 py-3 text-right text-sm font-bold text-gray-900">
                                ₦<?php echo number_format(array_sum(array_column($officer_performance, 'balance'))); ?>
                            </th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Decision Making Tools -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Management Decision Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="leasing_performance_analysis.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Performance Analysis
                </a>
                
                <a href="leasing_arrears_analysis.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    Arrears Analysis
                </a>
                
                <a href="leasing_trends.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                    Trend Analysis
                </a>
                
                <a href="leasing_recommendations.php?month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>&type=<?php echo $collection_type; ?>" 
                   class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Recommendations
                </a>
            </div>
        </div>
    </div>

    <script>
        // Collection Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsData = <?php echo json_encode($trends); ?>;
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => item.month_name),
                datasets: [{
                    label: '<?php echo ucfirst($collection_type); ?> Collections (₦)',
                    data: trendsData.map(item => item.total),
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Amount: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Top Performers Chart
        const performersCtx = document.getElementById('performersChart').getContext('2d');
        const performersData = <?php echo json_encode($top_performers); ?>;
        
        new Chart(performersCtx, {
            type: 'doughnut',
            data: {
                labels: performersData.map(item => item.first_name),
                datasets: [{
                    data: performersData.map(item => item.total_collected),
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(139, 69, 19, 0.8)'
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