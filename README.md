# HaatLink - Online Marketplace (DBMS Project - Phase 1 / 50% Milestone)

HaatLink is a database-driven online marketplace web application connecting local agricultural producers and cottage artisans directly with consumers. 

This repository contains the complete implementation of **Phase 1 (50% Milestone)** focusing on the Database foundation, Role-based Authentication, Admin Verification, Producer Product Management, and Public Customer Marketplace browsing.

---

## 🚀 Quick Setup Instructions (XAMPP)

### Step 1: Start XAMPP Services
- Open **XAMPP Control Panel**.
- Start **Apache** and **MySQL**.

### Step 2: Import Database Schema & Seed Data
1. Open your browser and navigate to: `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar, name the database `haatlink_db`, and select collation `utf8mb4_unicode_ci` (or let the script create it).
3. Click the **Import** tab at the top.
4. Click **Choose File** and select `database.sql` located inside `C:\xampp\htdocs\Haatlink\database.sql`.
5. Click **Go** at the bottom to execute.

### Step 3: Open the Project in Browser
Navigate to:
```
http://localhost/Haatlink/
```

---

## 🔑 Demo Accounts for Presentation

Each account is pre-configured with dummy data for immediate testing during class demonstrations:

| Role | Email | Password | Initial Status | Purpose in Presentation |
| :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin@haatlink.com` | `admin123` | `verified` | Approve/Reject pending producers, add categories |
| **Producer (Verified)** | `rohim.farm@haatlink.com` | `password123` | `verified` | Add products, view producer inventory dashboard |
| **Producer (Pending)** | `karim.agro@haatlink.com` | `password123` | `pending` | **Demo test account!** Shows warning banner & disabled product adding |
| **Customer** | `sakib@haatlink.com` | `password123` | `verified` | Browse shop and filter products |

> **Presentation Tip:** On the `login.php` page, you can click on any demo account in the demo box to automatically fill the login form!

---

## 📑 DBMS Presentation Guide (Query Cheatsheet)

Use this table during your class defense to explain where each DBMS requirement is implemented:

### 1. `CREATE TABLE` & Relational Integrity (`database.sql`)
- **Foreign Keys**: `products.producer_id -> users.id (ON DELETE CASCADE)` and `products.category_id -> categories.id (ON DELETE RESTRICT)`.
- **Enumerations**: `role ENUM('admin', 'producer', 'customer')` and `status ENUM('pending', 'verified', 'suspended')`.
- **Check Constraints & Indexes**: Unique email, unique category name.

### 2. `SELECT` with Prepared Statements (`login.php`, `register.php`, `shop.php`)
- Uniqueness validation in `register.php`:
  ```sql
  SELECT id FROM users WHERE email = ?;
  ```
- Credential fetching in `login.php`:
  ```sql
  SELECT * FROM users WHERE email = ?;
  ```

### 3. `INSERT` Operations (`register.php`, `add_product.php`, `add_category.php`)
- Role-based registration status assignment in `register.php`:
  ```sql
  INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?);
  ```
- Product creation with Foreign Keys in `add_product.php`:
  ```sql
  INSERT INTO products (producer_id, category_id, name, description, price, stock_quantity, status)
  VALUES (?, ?, ?, ?, ?, ?, ?);
  ```

### 4. `UPDATE` Operations (`admin_dashboard.php`)
- Admin approving pending producer:
  ```sql
  UPDATE users SET status = 'verified' WHERE id = ? AND role = 'producer';
  ```
- Admin rejecting producer:
  ```sql
  UPDATE users SET status = 'rejected' WHERE id = ? AND role = 'producer';
  ```
- Admin approving product:
  ```sql
  UPDATE products SET status = 'active' WHERE id = ?;
  ```
- Admin rejecting product:
  ```sql
  UPDATE products SET status = 'rejected' WHERE id = ?;
  ```

### 5. Multi-Table `JOIN` & Data Aggregation (`shop.php`, `producer_dashboard.php`, `add_category.php`)
- 3-Table INNER JOIN in `shop.php`:
  ```sql
  SELECT p.*, c.name AS category_name, u.name AS producer_name
  FROM products p
  JOIN categories c ON p.category_id = c.id
  JOIN users u ON p.producer_id = u.id
  WHERE p.status = 'active';
  ```
- `LEFT JOIN` and `GROUP BY` with `COUNT()` in `add_category.php`:
  ```sql
  SELECT c.id, c.name, COUNT(p.id) AS total_products
  FROM categories c
  LEFT JOIN products p ON c.id = p.category_id
  GROUP BY c.id, c.name;
  ```

---

## 📁 File Structure

```
Haatlink/
│── database.sql               # Complete MySQL schema & dummy seed data
│── db_connect.php             # PDO database connection with error handling
│── style.css                  # Clean, modern, responsive Vanilla CSS
│── index.php                  # Public homepage with Hero banner and featured items
│── shop.php                   # Public shop catalog with category & search filter
│── login.php                  # Role-based login and auto-fill presentation helpers
│── register.php               # Customer / Producer registration logic
│── logout.php                 # Session destruction and redirection
│── admin_dashboard.php        # Admin verification queue (Approve/Reject) & KPIs
│── add_category.php           # Admin category creation and inventory count
│── producer_dashboard.php     # Producer inventory panel & pending status lock
│── add_product.php            # Product creation form (verified producers only)
│── README.md                  # Project documentation & presentation guide
└── includes/
    ├── header.php             # Common HTML header and session startup
    ├── navbar.php             # Dynamic role-aware navigation bar
    └── footer.php             # Common footer and project metadata
```

---

## 🎯 Phase 2 Roadmap (Future 50%)
- Shopping Cart & Session-based cart persistence.
- Checkout & Order Processing (`orders` and `order_items` tables).
- Customer Order History & Tracking.
- Customer Product Reviews & Rating system (`reviews` table).
