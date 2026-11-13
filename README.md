# SplitMate – Multi-Admin Expense Management & Smart Settlement System

SplitMate is a modern, responsive, secure full-stack web application designed for group expense tracking, multi-admin group isolation, and algorithmic debt minimization using an automatic **Greedy Debt Settlement Algorithm**.

Built with **Core PHP (OOP & PDO)**, **MySQL**, **Bootstrap 5**, **Font Awesome**, and **Chart.js**, running seamlessly on a local **XAMPP** environment.

---

## 🌟 Key Features

### 1. Multi-Admin Architecture & Strict Data Isolation
- Each Administrator manages an entirely independent group of members, expenses, and settlements.
- Every user is strictly associated with exactly one administrator via `admin_id`.
- Every expense and settlement transaction belongs to an `admin_id`.
- Cross-tenant data isolation is enforced at the database query level on every query (`WHERE admin_id = :admin_id`).
- Admins and members can never access, view, or manipulate data from other admin groups.

### 2. Dual Authentication & Role-Based Access Control
- **No Landing Page**: Root URL opens directly on the Login / Registration interface.
- **Admin Registration**: Protected by server-side verification of the secret code (`250506`). Secret code is never exposed to frontend HTML/JS.
- **User (Member) Registration**: Members choose their administrator from an active verified admin dropdown.
- **Compulsory Email OTP Verification**:
  - Generates a random 6-digit OTP.
  - Delivered via **PHPMailer with SMTP**.
  - 5-minute expiration timer.
  - Resend cooldown (60 seconds) and maximum attempt rate limiting.
  - Accounts remain inactive and unverified until the OTP is successfully validated.
  - **Local Development Mode**: Displays the generated OTP on screen/toast if SMTP credentials are blank, ensuring friction-free local testing.
- **Session Security**: Password hashing with `password_hash()` and `password_verify()`, session regeneration upon login, CSRF tokens on all state-changing requests, and input sanitization (`htmlspecialchars`).

### 3. Core Feature: Smart Greedy Expense Settlement System
- **Equal-Split Formula**:
  $$\text{Fair Share} = \frac{\text{Total Active Group Expenses}}{\text{Total Active Members}}$$
  $$\text{Individual Balance} = \text{Amount Paid} - \text{Fair Share}$$
- **Zero Floating-Point Error**: Internal debt calculations use **integer paise** ($1\text{ Rupee} = 100\text{ Paise}$) to prevent floating-point rounding errors.
- **Greedy Debt Minimization Algorithm**:
  1. Partitions group members into **Creditors** (positive balance, should receive money) and **Debtors** (negative balance, owe money).
  2. Sorts creditors descending (largest credit first) and debtors ascending (largest debt first).
  3. Matches highest debtor with highest creditor to settle $\min(|\text{debt}|, \text{credit})$.
  4. Minimizes the total number of peer-to-peer transactions.
- **Settlement Tracking & History**:
  - Track payments as **Pending** or **Paid**.
  - "Mark Paid" records confirmation timestamp, payment method (UPI, Cash, Card), and identity of who confirmed it.
  - Preserves original expense records and maintains an immutable payment history.

### 4. Admin Dashboard & Analytics
- **Summary Cards**: Total Group Expenses, Active Users, Total Expense Entries, Average Expense Per User, Top Spender, and Pending Settlements.
- **Interactive Chart.js Visualizations**:
  - Monthly Expense Overview (Bar Chart)
  - Category-wise Expense Distribution (Doughnut Chart)
  - User-wise Spending Comparison (Bar Chart)
  - Daily Expense Trends (Line Chart)
- **Member Management**: User summary table with actual calculated values (Total Spent, Entries, Calculated Balance), one-click activation/deactivation toggle, and member profile modal with full personal history.
- **User-wise Expenses**: Breakdown cards and ledger by individual member.

### 5. Advanced Dynamic Expense Filtering (AJAX / Fetch API)
- Real-time search by Title or Member Name.
- Filter by Category, Payment Method, Amount Range (Min/Max), Date Range, and Month.
- Sort by Newest, Oldest, Highest Amount, or Lowest Amount.
- Instant table re-render without full page reload.
- One-click **Export to CSV**.

### 6. User (Member) Dashboard & Features
- Individual metrics: My Total Spending, Group Total Spending, My Fair Share, My Current Balance.
- Personal expense management (Add, Edit, Delete own expenses).
- Transparent **Who Owes Whom** visual flow and payment confirmation.
- Read-only access to group expenses (users cannot tamper with other members' entries).

### 7. Reports & Analytics
- 6 Report Types: Overall Group Expenses, Individual User Expenses, Monthly Report, Category-wise Report, Settlement Reconciliation, and Pending Payments.
- Custom date range, member, and category filters.
- Print / PDF layout and CSV export.

---

## 🛠 Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3, Font Awesome 6.5
- **Charts**: Chart.js 4.4
- **Backend**: Core PHP 8.2 (Modular Architecture, OOP, PDO Prepared Statements)
- **Database**: MySQL 8.0 (InnoDB, Foreign Key Constraints, Cascades, Indexes)
- **Email**: PHPMailer 7.2 (SMTP with TLS/SSL support)
- **Environment**: XAMPP (Apache + MySQL on Windows)

---

## 📁 Project Structure

```
SplitMate/
│
├── config/
│   ├── config.php            # Main app settings, session config, CSRF, flash, .env parser
│   └── database.php          # Singleton PDO connection handler
│
├── includes/
│   ├── auth.php              # Auth middleware, role checks, session helpers
│   ├── functions.php         # Greedy settlement algorithm, stats helpers, PHPMailer OTP
│   ├── header.php            # Responsive navbar, topbar, flash alerts, stylesheets
│   ├── sidebar.php           # SaaS dark navy sidebar (Admin vs User menus)
│   └── footer.php            # Footer markup, Bootstrap bundle, JavaScript scripts
│
├── admin/
│   ├── dashboard.php         # Admin metrics, 4 Chart.js analytics, recent expenses
│   ├── expenses.php          # All group expenses with dynamic AJAX filters & CRUD
│   ├── users.php             # User management table, status toggle, member modal
│   ├── user_expenses.php     # User-wise spending breakdown and ledger
│   ├── settlements.php       # Smart Greedy Settlement dashboard, sync & mark paid
│   ├── reports.php           # 6 report generators, date/category filters, PDF & CSV export
│   └── profile.php           # Admin profile, password change, audit activity log
│
├── user/
│   ├── dashboard.php         # Personal spending, fair share, balance card, settlements
│   ├── add_expense.php       # Add expense form with auto admin_id & user_id binding
│   ├── my_expenses.php       # Personal expenses list with dynamic filters & CRUD
│   ├── group_expenses.php    # Transparent group ledger (read-only for peers)
│   ├── settlements.php       # "Who Owes Whom" visual flow and peer payment action
│   └── profile.php           # User profile, password change, activity history
│
├── auth/
│   ├── login.php             # Role-based login (Admin / Member) with demo credentials fill
│   ├── register_admin.php    # Admin registration with server-side secret code check
│   ├── register_user.php     # Member registration with admin dropdown & OTP generation
│   ├── verify_otp.php        # 6-digit OTP verification screen, cooldown, dev helper
│   └── logout.php            # Secure session destruction and redirection
│
├── api/
│   ├── expenses.php          # RESTful JSON API: Add, edit, delete, get expenses
│   ├── filters.php           # Dynamic AJAX filtering endpoint (HTML & JSON data)
│   ├── settlements.php       # Settlement matrix calculation, DB sync, mark paid
│   └── dashboard.php         # Live stats provider for Chart.js
│
├── assets/
│   ├── css/
│   │   └── style.css         # Modern SaaS dark navy & emerald design system
│   └── js/
│       └── main.js           # AJAX handlers, toast notification system, CSV export
│
├── database/
│   └── splitmate_db.sql      # Database schema, foreign keys & prompt test seed data
│
├── vendor/                   # Composer dependencies (PHPMailer v7.2)
├── .env                      # Active environment configuration
├── .env.example              # Example environment template
├── index.php                 # Direct router to dashboard / login (no landing page)
└── README.md                 # Complete documentation and testing instructions
```

---

## 🚀 Installation & Setup Guide (XAMPP)

### Step 1: Place in XAMPP `htdocs`
Ensure the project is located in your XAMPP web directory:
```
D:\xampp\htdocs\SplitMate
```

### Step 2: Start Apache & MySQL
1. Open the **XAMPP Control Panel**.
2. Start the **Apache** and **MySQL** modules.

### Step 3: Import Database
Open your terminal (or phpMyAdmin at `http://localhost/phpmyadmin`) and import `database/splitmate_db.sql`:

**Via Command Line (PowerShell / Command Prompt):**
```powershell
Get-Content "D:\xampp\htdocs\SplitMate\database\splitmate_db.sql" -Raw | mysql -u root
```
*Or in Command Prompt:*
```cmd
mysql -u root < D:\xampp\htdocs\SplitMate\database\splitmate_db.sql
```

The database name will be `splitmate_db`.

### Step 4: Configure Environment (`.env`)
The `.env` file is located at the root of `SplitMate/`:
```env
APP_NAME="SplitMate"
APP_ENV="development"
APP_URL="http://localhost/SplitMate"

# Admin Secret Registration Code
ADMIN_SECRET_CODE="250506"

# Database Configuration
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="splitmate_db"
DB_USER="root"
DB_PASS=""

# SMTP Email Configuration for OTP Verification
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USER=""
SMTP_PASS=""
SMTP_ENCRYPTION="tls"
SMTP_FROM_EMAIL="no-reply@splitmate.com"
SMTP_FROM_NAME="SplitMate System"

# Dev Mode: 1 enables on-screen OTP helper if SMTP is blank
DEV_MODE=1
```

### Step 5: Launch the Application
Open your browser and navigate to:
```
http://localhost/SplitMate
```
It will immediately direct you to the **SplitMate Login Interface**.

---

## 🔑 Test Accounts & Default Credentials

All pre-seeded test accounts share the password:
```
Password@123
```

### Admin Accounts
| Role | Name | Email | Password | Group ID | Secret Code |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Admin 1** | Avadh Narola | `admin@splitmate.com` | `Password@123` | `#1` | `250506` |
| **Admin 2** | Vikram Malhotra | `vikram@splitmate.com` | `Password@123` | `#2` | `250506` |

---

### Member Accounts for Admin 1 (Avadh Narola Group - 5 Members)
This group tests the **exact sample scenario from the prompt**:
| Member | Email | Password | Pre-seeded Spending | Net Balance | Expected Action |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **User 1 (Avadh)** | `avadh@gmail.com` | `Password@123` | ₹1,000.00 | **+₹500.00** | Should receive ₹500 |
| **User 2 (Rahul)** | `user2@gmail.com` | `Password@123` | ₹1,500.00 | **+₹1,000.00** | Should receive ₹1,000 |
| **User 3 (Priya)** | `user3@gmail.com` | `Password@123` | ₹0.00 | **-₹500.00** | Needs to pay ₹500 |
| **User 4 (Rohan)** | `user4@gmail.com` | `Password@123` | ₹0.00 | **-₹500.00** | Needs to pay ₹500 |
| **User 5 (Sneha)** | `user5@gmail.com` | `Password@123` | ₹0.00 | **-₹500.00** | Needs to pay ₹500 |

**Group Total**: ₹2,500.00 across 5 members $\rightarrow$ **Fair Share**: ₹500.00/person.  
**Greedy Settlement Output**:
- **User 3 (Priya)** pays **Avadh** $\rightarrow$ ₹500.00
- **User 4 (Rohan)** pays **User 2 (Rahul)** $\rightarrow$ ₹500.00
- **User 5 (Sneha)** pays **User 2 (Rahul)** $\rightarrow$ ₹500.00

---

### Member Accounts for Admin 2 (Vikram Malhotra Group - Isolated Tenant)
| Member | Email | Password | Pre-seeded Spending | Group ID |
| :--- | :--- | :--- | :--- | :--- |
| **User A (Amit)** | `usera@gmail.com` | `Password@123` | ₹3,000.00 | `#2` |
| **User B (Neha)** | `userb@gmail.com` | `Password@123` | ₹600.00 | `#2` |
| **User C (Rajesh)**| `userc@gmail.com` | `Password@123` | ₹0.00 | `#2` |

---

## 🧪 Comprehensive Testing Guide

### 1. Test Admin Registration
1. Go to `http://localhost/SplitMate/auth/register_admin.php`.
2. Fill in Full Name, Email, Phone, and Password.
3. Test submitting with an incorrect secret code (e.g. `123456`) $\rightarrow$ Validation blocks creation with *"Invalid Administrator Secret Code"*.
4. Enter the correct secret code: `250506`.
5. Submit form $\rightarrow$ Admin account is created, you are logged in, and redirected to `admin/dashboard.php`.

### 2. Test User Registration & Email OTP Verification
1. Go to `http://localhost/SplitMate/auth/register_user.php`.
2. Enter your details and select **Avadh Narola (Group #1)** from the admin dropdown.
3. Click **Continue & Verify Email OTP**.
4. You are taken to `auth/verify_otp.php`.
   - If SMTP is configured, the OTP is emailed to your address.
   - If in local dev mode, the generated 6-digit OTP is also conveniently displayed in the yellow Dev Helper alert.
5. Test entering an invalid 6-digit code $\rightarrow$ Attempt counter decrements.
6. Enter the correct 6-digit code and click **Verify**.
7. The account is immediately marked verified (`email_verified = 1`), activated, and you are logged in to `user/dashboard.php`.

### 3. Test Multi-Admin Data Isolation
1. Log in as **Admin 1 (`admin@splitmate.com`)**:
   - Navigate to **Manage Users** $\rightarrow$ You see only User 1, User 2, User 3, User 4, User 5.
   - You **never** see User A, User B, or User C.
   - Total group expenses: ₹2,500.00.
2. Log in as **Admin 2 (`vikram@splitmate.com`)**:
   - Navigate to **Manage Users** $\rightarrow$ You see only User A, User B, User C.
   - Total group expenses: ₹3,600.00.
   - None of Admin 1's expenses, settlements, or users are accessible or visible.

### 4. Test the Greedy Debt Settlement Algorithm
1. Log in as **Admin 1** and open **Settlement Management** (`admin/settlements.php`).
2. Verify the 5 summary cards:
   - Total Group Expense: **₹2,500.00**
   - Per Person Share: **₹500.00**
   - Total Receivable: **₹1,500.00**
   - Total Payable: **₹1,500.00**
   - Pending Payments: **3**
3. Verify the Optimized Transfer Flow:
   - Step 1: User 3 pays User 2 ₹500
   - Step 2: User 4 pays User 2 ₹500
   - Step 3: User 5 pays Avadh ₹500
4. Click **Mark Paid** on any pending settlement:
   - Select payment method: **UPI**.
   - Enter reference note: *"GPay transaction #789012"*.
   - Click **Confirm Payment Receipt**.
   - The status updates to **Paid**, with timestamp and your name recorded as confirmer.

### 5. Test Dynamic AJAX Expense Filtering
1. Open **All Expenses** (`admin/expenses.php`) or **My Expenses** (`user/my_expenses.php`).
2. Search keywords in real-time (e.g. *"Grocery"* or *"Dinner"*).
3. Change category dropdown (e.g. *"Food & Dining"*).
4. Enter minimum and maximum amounts (e.g. ₹1,200 to ₹2,000).
5. Switch sorting (e.g. *"Highest Amount"*).
6. Note that table records and total amount update dynamically via Fetch API without full page reload.
7. Click **Export CSV** to download a clean spreadsheet of the filtered results.

### 6. Test Reports & PDF/Print Export
1. Log in as Admin and open **Reports & Analytics** (`admin/reports.php`).
2. Switch report type between **Overall Group Expenses**, **Individual User Expenses**, **Monthly**, and **Category-wise**.
3. Apply date range filters.
4. Click **Print / PDF** $\rightarrow$ Clean print preview renders with no sidebar or buttons, formatted for PDF saving.

---

## 🔒 Security Best Practices Implemented

- **Prepared SQL Statements**: 100% of queries use PDO parameter binding with prepared statements to protect against SQL Injection.
- **Server-Side Secret Code**: The admin secret code `250506` is defined server-side and never sent in frontend JS or HTML.
- **XSS Protection**: All user-generated outputs are sanitized with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **CSRF Token Validation**: Every form and AJAX POST endpoint checks a cryptographically secure token stored in `$_SESSION['csrf_token']`.
- **Session Hijacking Prevention**: `session_regenerate_id(true)` is invoked upon successful authentication. Strict session cookie parameters (`httponly`, `use_strict_mode`) are enforced.
- **Rate Limiting**: Protects login and OTP verification endpoints against brute-force attacks.
- **Strict Tenant Authorization**: Both Admin and User endpoints check permissions and enforce `admin_id` ownership on every read, update, and delete operation.

