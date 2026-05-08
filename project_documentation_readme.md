# HydroLogic OS - Technical Documentation

## Project Overview
HydroLogic OS is a full-stack Tube Well Monitoring System designed for industrial water infrastructure management. It provides real-time telemetry, utility tracking, maintenance scheduling, and secure user management.

## System Architecture
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript (ES6+), Chart.js
- **Backend**: Core PHP (Procedural)
- **Database**: MySQL 8.0+
- **Authentication**: Session-based with `password_hash()` and prepared statements.

## Folder Structure
```text
hydrologic-os/
│
├── admin/                  # Admin-only modules
│   ├── dashboard.php       # Main telemetry overview
│   ├── users.php           # User management & roles
│   └── reports.php         # System-wide exports
│
├── user/                   # Operator/User modules
│   ├── monitoring.php      # Data entry for usage
│   └── maintenance.php     # Ticket submission
│
├── includes/               # Reusable components
│   ├── header.php          # Global navigation & meta
│   ├── footer.php          # Copyright & scripts
│   ├── sidebar.php         # Role-based navigation
│   └── functions.php       # Logic helpers
│
├── config/                 # System configuration
│   └── db_connect.php      # MySQL PDO/Prepared connection
│
├── assets/                 # Static assets
│   ├── css/                # Custom styling
│   ├── js/                 # Validation & Charts
│   └── images/             # Brand assets
│
├── login.php               # System entry
├── logout.php              # Session destruction
└── index.php               # Landing / Redirect logic
```

## Setup Instructions
1. **Database**: Import `database/schema.sql` into your MySQL server.
2. **Connection**: Update `config/db_connect.php` with your local credentials (DB_NAME, DB_USER, DB_PASS).
3. **Roles**: 
   - Admin: `admin@hydrologic.io` / `admin123`
   - Operator: `operator@hydrologic.io` / `operator123`
4. **Server**: Deploy to XAMPP/WAMP `htdocs` folder.

## Security Features
- **SQLi Prevention**: All queries use prepared statements.
- **XSS Protection**: Data sanitization on all output.
- **CSRF Tokens**: Implemented on all CRUD forms.
- **Role RBAC**: Strict session checks on every page load.