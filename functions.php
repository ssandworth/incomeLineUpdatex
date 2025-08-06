<?php
/**
 * Helper functions for the transaction system
 */

/**
 * Calculate time elapsed since a given datetime
 */
function time_elapsed_string($datetime, $full = false) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Format currency amount
 */
function format_currency($amount) {
    return '₦' . number_format((float)$amount, 2);
}

/**
 * Get status badge class
 */
function get_status_badge_class($status) {
    switch (strtolower($status)) {
        case 'approved':
        case 'verified':
            return 'bg-green-100 text-green-800';
        case 'declined':
            return 'bg-red-100 text-red-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'flagged':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate date format
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Convert date format from d/m/Y to Y-m-d
 */
function convert_date_format($date, $from_format = 'd/m/Y', $to_format = 'Y-m-d') {
    $d = DateTime::createFromFormat($from_format, $date);
    return $d ? $d->format($to_format) : false;
}

/**
 * Check if user has permission
 */
function has_permission($user_id, $permission, $db) {
    $db->query("SELECT {$permission} as has_permission FROM roles WHERE user_id = :user_id");
    $db->bind(':user_id', $user_id);
    $result = $db->single();
    
    return $result && $result['has_permission'] === 'Yes';
}

/**
 * Log user activity
 */
function log_activity($user_id, $action, $details, $db) {
    $db->query("
        INSERT INTO activity_log (user_id, action, details, timestamp) 
        VALUES (:user_id, :action, :details, NOW())
    ");
    
    $db->bind(':user_id', $user_id);
    $db->bind(':action', $action);
    $db->bind(':details', $details);
    
    return $db->execute();
}

/**
 * Generate transaction reference
 */
function generate_transaction_ref() {
    return time() . mt_rand(1000, 9999);
}

/**
 * Get department color
 */
function get_department_color($department) {
    switch (strtolower($department)) {
        case 'accounts':
            return 'blue';
        case 'wealth creation':
            return 'green';
        case 'audit/inspections':
            return 'purple';
        case 'leasing':
            return 'orange';
        default:
            return 'gray';
    }
}

/**
 * Format phone number
 */
function format_phone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format Nigerian phone numbers
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return '+234' . substr($phone, 1);
    } elseif (strlen($phone) === 10) {
        return '+234' . $phone;
    }
    
    return $phone;
}

/**
 * Validate receipt number format
 */
function validate_receipt_number($receipt_no) {
    return preg_match('/^\d{7}$/', $receipt_no);
}

/**
 * Get file size in human readable format
 */
function human_filesize($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Generate secure random string
 */
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Check if request is AJAX
 */
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Display flash message
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        
        $color_class = $type === 'success' ? 'green' : ($type === 'error' ? 'red' : 'yellow');
        
        echo "<div class='mb-4 p-4 rounded-md bg-{$color_class}-50 border border-{$color_class}-200'>
                <p class='text-{$color_class}-800'>{$message}</p>
              </div>";
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Escape HTML output
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if string contains only numbers
 */
function is_numeric_string($str) {
    return ctype_digit($str);
}

/**
 * Format date for display
 */
function format_date_display($date, $format = 'd/m/Y') {
    if (!$date || $date === '0000-00-00') {
        return 'N/A';
    }
    
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function format_datetime_display($datetime, $format = 'd/m/Y H:i:s') {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    
    return date($format, strtotime($datetime));
}

/**
 * Get user's full name by ID
 */
function get_user_name($user_id, $db) {
    $db->query("SELECT full_name FROM staffs WHERE user_id = :user_id");
    $db->bind(':user_id', $user_id);
    $result = $db->single();
    
    return $result ? $result['full_name'] : 'Unknown User';
}

/**
 * Calculate percentage change
 */
function calculate_percentage_change($old_value, $new_value) {
    if ($old_value == 0) {
        return $new_value > 0 ? 100 : 0;
    }
    
    return (($new_value - $old_value) / $old_value) * 100;
}

/**
 * Truncate text with ellipsis
 */
function truncate_text($text, $length = 50, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}
?>