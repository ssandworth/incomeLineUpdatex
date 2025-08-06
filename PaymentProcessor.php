<?php
require_once 'Database.php';

class PaymentProcessor {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Check if receipt number already exists
     */
    public function checkReceiptExists($receipt_no) {
        $this->db->query("SELECT posting_officer_name, date_of_payment FROM account_general_transaction_new WHERE receipt_no = :receipt_no");
        $this->db->bind(':receipt_no', $receipt_no);
        return $this->db->single();
    }
    
    /**
     * Get staff information
     */
    public function getStaffInfo($staff_id, $staff_type = 'wc') {
        if ($staff_type === 'wc') {
            $this->db->query("SELECT full_name FROM staffs WHERE user_id = :staff_id");
        } else {
            $this->db->query("SELECT full_name FROM staffs_others WHERE id = :staff_id");
        }
        $this->db->bind(':staff_id', $staff_id);
        return $this->db->single();
    }
    
    /**
     * Get account information
     */
    public function getAccountInfo($account_identifier, $by_alias = false) {
        if ($by_alias) {
            $this->db->query("SELECT acct_id, acct_table_name, acct_desc FROM accounts WHERE acct_alias = :identifier");
        } else {
            $this->db->query("SELECT acct_id, acct_table_name, acct_desc FROM accounts WHERE acct_id = :identifier");
        }
        $this->db->bind(':identifier', $account_identifier);
        return $this->db->single();
    }
    
    /**
     * Get remittance balance for Wealth Creation staff
     */
    public function getRemittanceBalance($posting_officer_id, $current_date) {
        // Get amount posted today
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as amount_posted 
            FROM account_general_transaction_new 
            WHERE posting_officer_id = :officer_id 
            AND payment_category = 'Other Collection' 
            AND date_of_payment = :current_date
        ");
        $this->db->bind(':officer_id', $posting_officer_id);
        $this->db->bind(':current_date', $current_date);
        $posted = $this->db->single();
        
        // Get amount remitted today
        $this->db->query("
            SELECT COALESCE(SUM(amount_paid), 0) as amount_remitted, remit_id, date
            FROM cash_remittance 
            WHERE remitting_officer_id = :officer_id 
            AND category = 'Other Collection' 
            AND date = :current_date
        ");
        $this->db->bind(':officer_id', $posting_officer_id);
        $this->db->bind(':current_date', $current_date);
        $remitted = $this->db->single();
        
        return [
            'amount_posted' => $posted['amount_posted'],
            'amount_remitted' => $remitted['amount_remitted'],
            'unposted' => $remitted['amount_remitted'] - $posted['amount_posted'],
            'remit_id' => $remitted['remit_id'] ?? '',
            'date' => $remitted['date'] ?? ''
        ];
    }
    
    /**
     * Process car park payment
     */
    public function processCarParkPayment($data) {
        $this->db->beginTransaction();
        
        try {
            $txref = time() . mt_rand(0, 9);
            $now = date('Y-m-d H:i:s');
            
            // Insert main transaction
            $this->db->query("
                INSERT INTO account_general_transaction_new (
                    id, date_of_payment, ticket_category, transaction_desc, receipt_no, 
                    amount_paid, remitting_id, remitting_staff, posting_officer_id, 
                    posting_officer_name, posting_time, leasing_post_status, approval_status, 
                    verification_status, debit_account, credit_account, payment_category, 
                    no_of_tickets, remit_id, income_line
                ) VALUES (
                    :txref, :date_payment, :ticket_category, :trans_desc, :receipt_no,
                    :amount_paid, :remitting_id, :remitting_staff, :posting_officer_id,
                    :posting_officer_name, :posting_time, :leasing_status, :approval_status,
                    :verification_status, :debit_account, :credit_account, :payment_category,
                    :no_tickets, :remit_id, :income_line
                )
            ");
            
            $this->db->bind(':txref', $txref);
            $this->db->bind(':date_payment', $data['date_of_payment']);
            $this->db->bind(':ticket_category', $data['ticket_category']);
            $this->db->bind(':trans_desc', $data['transaction_desc']);
            $this->db->bind(':receipt_no', $data['receipt_no']);
            $this->db->bind(':amount_paid', $data['amount_paid']);
            $this->db->bind(':remitting_id', $data['remitting_id']);
            $this->db->bind(':remitting_staff', $data['remitting_staff']);
            $this->db->bind(':posting_officer_id', $data['posting_officer_id']);
            $this->db->bind(':posting_officer_name', $data['posting_officer_name']);
            $this->db->bind(':posting_time', $now);
            $this->db->bind(':leasing_status', $data['leasing_post_status']);
            $this->db->bind(':approval_status', $data['approval_status']);
            $this->db->bind(':verification_status', $data['verification_status']);
            $this->db->bind(':debit_account', $data['debit_account']);
            $this->db->bind(':credit_account', $data['credit_account']);
            $this->db->bind(':payment_category', 'Other Collection');
            $this->db->bind(':no_tickets', $data['no_of_tickets']);
            $this->db->bind(':remit_id', $data['remit_id']);
            $this->db->bind(':income_line', $data['income_line']);
            
            $this->db->execute();
            
            // Insert debit entry
            $this->db->query("
                INSERT INTO {$data['db_debit_table']} (
                    id, acct_id, date, receipt_no, trans_desc, debit_amount, balance, approval_status
                ) VALUES (
                    :txref, :acct_id, :date, :receipt_no, :trans_desc, :amount, :balance, :approval_status
                )
            ");
            
            $this->db->bind(':txref', $txref);
            $this->db->bind(':acct_id', $data['debit_account']);
            $this->db->bind(':date', $data['date_of_payment']);
            $this->db->bind(':receipt_no', $data['receipt_no']);
            $this->db->bind(':trans_desc', $data['transaction_desc']);
            $this->db->bind(':amount', $data['amount_paid']);
            $this->db->bind(':balance', '');
            $this->db->bind(':approval_status', $data['approval_status']);
            
            $this->db->execute();
            
            // Insert credit entry
            $this->db->query("
                INSERT INTO {$data['db_credit_table']} (
                    id, acct_id, date, receipt_no, trans_desc, credit_amount, balance, approval_status
                ) VALUES (
                    :txref, :acct_id, :date, :receipt_no, :trans_desc, :amount, :balance, :approval_status
                )
            ");
            
            $this->db->bind(':txref', $txref);
            $this->db->bind(':acct_id', $data['credit_account']);
            $this->db->bind(':date', $data['date_of_payment']);
            $this->db->bind(':receipt_no', $data['receipt_no']);
            $this->db->bind(':trans_desc', $data['transaction_desc']);
            $this->db->bind(':amount', $data['amount_paid']);
            $this->db->bind(':balance', '');
            $this->db->bind(':approval_status', $data['approval_status']);
            
            $this->db->execute();
            
            $this->db->endTransaction();
            return ['success' => true, 'message' => 'Payment successfully posted for approval!'];
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return ['success' => false, 'message' => 'Error occurred while posting: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get staff list for dropdown
     */
    public function getStaffList($department = null) {
        if ($department) {
            $this->db->query("SELECT user_id, full_name FROM staffs WHERE department = :department ORDER BY full_name ASC");
            $this->db->bind(':department', $department);
        } else {
            $this->db->query("SELECT user_id, full_name FROM staffs ORDER BY full_name ASC");
        }
        
        return $this->db->resultSet();
    }
    
    /**
     * Get other staff list
     */
    public function getOtherStaffList() {
        $this->db->query("SELECT id, full_name, department FROM staffs_others ORDER BY full_name ASC");
        return $this->db->resultSet();
    }
}
?>