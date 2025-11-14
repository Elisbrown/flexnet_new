# Flexnet Admin Interface - Setup Guide

## Overview
The Flexnet Admin Interface is a fully functional PHP-based administration dashboard with:
- ✅ Secure authentication system with hashed passwords
- ✅ Session management with timeout and role-based access control
- ✅ Auto-populated data from MySQL database
- ✅ Dashboard with statistics and recent activity
- ✅ Admin, Location, Household, and Payment management
- ✅ Prepared statements for SQL injection protection

## Database Configuration

### Database Credentials
```
Host: 82.197.82.142
User: u123583059_elisbrown
Password: Q1oJeMQu>1S
Database: u123583059_flexnet
Port: 3306
```

The credentials are configured in:
- `/admin/includes/db_connection.php`

## Files Structure

### Core Backend Files
```
admin/
├── includes/
│   ├── db_connection.php      # Database connection handler
│   ├── db_functions.php       # Utility functions (CRUD operations)
│   └── session.php            # Session & authentication management
├── login.php                  # Login handler
├── login-form.html            # Login form UI
├── logout.php                 # Logout handler
├── dashboard.php              # Main dashboard
├── profile.php                # Admin profile page
├── admins.php                 # Admin management
├── locations.php              # Location management
├── payments.php               # Payment management
└── db_connections.txt         # Deprecated (use includes/db_connection.php)
```

## Authentication System

### Login Process
1. User submits credentials via `login-form.html`
2. Request goes to `login.php`
3. Credentials verified against `admins` table
4. Password validated using `password_verify()`
5. Admin roles fetched from `admin_roles` & `roles` tables
6. Secure session created with timeout (1 hour)
7. Redirect to dashboard on success

### Session Management
- **Session timeout**: 1 hour (3600 seconds)
- **Secure cookies**: HttpOnly, SameSite=Lax
- **Session validation**: Auto-refresh on each request
- **Logout**: Clears all session data

### Default Admin Account
```
Email: admin@flexnet.cm
Password: [Use password_hash() to set your own]
Note: Default password is hashed. You'll need to update it via database.
```

To set a new admin password:
```php
$hashed = password_hash('your_password_here', PASSWORD_BCRYPT);
// Update in database: UPDATE admins SET password_hash = '$hashed' WHERE id = 1;
```

## Key Features

### 1. Database Functions (`db_functions.php`)
- `executeQuery()` - Execute prepared statements
- `fetchOne()` - Get single row
- `fetchAll()` - Get multiple rows
- `insert()` - Insert record
- `update()` - Update record
- `delete()` - Delete record
- `count()` - Count records

### 2. Session Management (`session.php`)
- `initSession()` - Initialize secure session
- `isLoggedIn()` - Check if admin logged in
- `getCurrentAdmin()` - Get current admin data
- `authenticateAdmin()` - Authenticate with email/password
- `getAdminRoles()` - Get admin roles
- `hasRole()` - Check specific role
- `logoutAdmin()` - Logout admin
- `requireAuth()` - Protect pages (redirect if not logged in)
- `requireRole()` - Protect pages by role

### 3. Dashboard Pages

#### Dashboard (`dashboard.php`)
- Auto-populated admin greeting
- Statistics cards:
  - Active locations count
  - Total households count
  - Active subscriptions count
- Recent payments table
- Admin activity table

#### Locations (`locations.php`)
- Lists all active locations
- Shows household count per location
- Auto-populated from `locations` table
- Quick actions for location management

#### Admin Management (`admins.php`)
- Lists all admin users
- Shows assigned roles
- Last login time
- Admin status (Active/Inactive)
- Requires SUPER_ADMIN role

#### Payments (`payments.php`)
- Revenue statistics
- Complete payment history
- Payment status indicators
- Linked to households and locations

#### Profile (`profile.php`)
- Current admin information
- Assigned roles
- Account status

## How to Use

### Step 1: Access Login Page
```
http://your-domain.com/admin/login.php
```

### Step 2: Login
- Email: admin@flexnet.cm
- Password: [Your password]

### Step 3: Authenticated Pages
After login, you can access:
- Dashboard: `/admin/dashboard.php`
- Locations: `/admin/locations.php`
- Admins: `/admin/admins.php`
- Payments: `/admin/payments.php`
- Profile: `/admin/profile.php`

### Step 4: Logout
Click "Logout" in the user menu to end session.

## Database Tables Used

### admins
```sql
- id (PK)
- full_name
- email
- password_hash
- is_active
- last_login_at
- created_at
- updated_at
```

### admin_roles
```sql
- admin_id (FK)
- role_id (FK)
```

### roles
```sql
- id (PK)
- name (SUPER_ADMIN, BILLING_ADMIN, SUPPORT_AGENT)
- description
- is_system
```

### locations
```sql
- id (PK)
- name
- code
- address_line1
- address_line2
- city
- region
- is_active
- created_at
- updated_at
```

### households
```sql
- id (PK)
- location_id (FK)
- apartment_label
- primary_full_name
- email
- phone_msisdn
- is_active
- subscription_status
- subscription_end_date
- created_at
- updated_at
```

### payments
```sql
- id (PK)
- household_id (FK)
- amount_xaf
- payment_method
- status
- created_at
- updated_at
```

## Security Features

✅ **Prepared Statements**: All queries use parameterized statements to prevent SQL injection
✅ **Password Hashing**: Bcrypt with PASSWORD_BCRYPT algorithm
✅ **Session Security**: HttpOnly cookies, SameSite protection, timeout
✅ **Role-Based Access**: Pages protected by role requirements
✅ **Error Logging**: Errors logged to PHP error log (not displayed to users)
✅ **Input Validation**: Email and password required and validated

## Troubleshooting

### 1. Login Not Working
- Check database credentials in `includes/db_connection.php`
- Verify admin exists in `admins` table
- Ensure password is correctly hashed with Bcrypt

### 2. Session Expired Error
- Session timeout is 1 hour of inactivity
- User will be redirected to login page

### 3. Access Denied
- User role may not have required permissions
- Check `admin_roles` and `roles` tables
- Use `requireRole('SUPER_ADMIN')` to restrict access

### 4. Database Connection Error
- Verify DB_HOST, DB_USER, DB_PASS, DB_NAME
- Check if database server is reachable on port 3306
- Ensure user has proper permissions

## Future Enhancements

1. **CRUD Operations**: Add/Edit/Delete for admins, locations, households
2. **Bulk Import**: Upload CSV for locations and households
3. **Reports**: Export payments and subscription reports
4. **Notifications**: Email alerts for important events
5. **Two-Factor Authentication**: Additional security layer
6. **API Integration**: RESTful API for mobile apps
7. **Activity Logs**: Detailed audit trail of all actions
8. **Email Templates**: Automated email notifications

## Support

For issues or questions:
1. Check error logs: `php error_log`
2. Verify database connection
3. Ensure all PHP files are in correct locations
4. Check file permissions (readable by web server)
5. Verify PHP version supports mysqli (PHP 5.5+)

---
**Last Updated**: November 11, 2025
**Version**: 1.0
