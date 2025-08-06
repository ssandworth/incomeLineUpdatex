# Modern ERP Payment System

A modernized version of the Wealth Creation ERP payment system built with PHP, PDO, and Tailwind CSS.

## Features

- Modern, responsive UI with Tailwind CSS
- Secure database operations using PDO with prepared statements
- Multiple income line processing (Car Park, Loading, Hawkers, etc.)
- Department-based access control
- Real-time amount calculations
- Receipt number validation
- Remittance balance tracking

## Installation

1. **Setup Database**:
   - Create a MySQL database
   - Import the `install.sql` file to create the required tables
   - Update database credentials in `config.php`

2. **Configure XAMPP**:
   - Place files in your XAMPP htdocs directory
   - Ensure PHP 5.6+ is running
   - Start Apache and MySQL services

3. **Access the System**:
   - Navigate to `http://localhost/your-folder-name/`
   - The system will load with sample data

## File Structure

- `config.php` - Database and application configuration
- `Database.php` - PDO database wrapper class
- `PaymentProcessor.php` - Business logic for payment processing
- `index.php` - Main application interface
- `install.sql` - Database schema and sample data

## Key Improvements

1. **Security**: 
   - PDO prepared statements prevent SQL injection
   - Input validation and sanitization

2. **Performance**:
   - Optimized queries with proper indexing
   - Reduced database calls through efficient joins

3. **Maintainability**:
   - Object-oriented design
   - Separated concerns (database, business logic, presentation)
   - Clean, readable code structure

4. **User Experience**:
   - Modern, responsive design
   - Real-time calculations
   - Clear error messaging
   - Intuitive navigation

## Usage

1. **Select Income Line**: Choose from the sidebar menu
2. **Fill Payment Form**: Enter required payment details
3. **Calculate Amount**: Amount is calculated automatically based on tickets
4. **Validate Receipt**: System checks for duplicate receipts
5. **Submit Payment**: Payment is processed and stored in database

## Supported Income Lines

- Car Park Tickets
- Car Loading Tickets  
- Hawkers Tickets
- WheelBarrow Tickets
- Daily Trade Tickets
- Abattoir Payments
- Loading & Offloading
- Overnight Parking
- Scroll Board
- Other POS Tickets
- Car Sticker

## Department Access

- **Wealth Creation**: Can post payments with remittance validation
- **Accounts**: Can post payments with account selection
- **Different approval workflows** based on department

## Technical Requirements

- PHP 5.6 or higher
- MySQL 5.5 or higher
- Web server (Apache/Nginx)
- Modern web browser with JavaScript enabled

The system maintains compatibility with XAMPP 5.6 while providing modern features and security improvements.