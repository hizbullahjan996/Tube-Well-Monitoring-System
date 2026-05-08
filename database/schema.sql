-- ============================================================
-- HydroLogic OS - Tube Well Monitoring System
-- Database Schema v1.0
-- Compatible with MySQL 8.0+
-- ============================================================

-- Create and select the database
CREATE DATABASE IF NOT EXISTS hydrologic_os CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hydrologic_os;

-- ============================================================
-- TABLE: users
-- Stores all system users (Admin and Operator roles)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    UNIQUE NOT NULL,
    password    VARCHAR(255)    NOT NULL,                         -- bcrypt hash
    role        ENUM('Admin', 'Operator') DEFAULT 'Operator',
    is_active   TINYINT(1)      DEFAULT 1,                       -- soft disable
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: tube_wells
-- Core asset registry for all registered tube wells
-- ============================================================
CREATE TABLE IF NOT EXISTS tube_wells (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    well_code           VARCHAR(30)     NOT NULL UNIQUE,          -- e.g. TW-001-A
    tube_well_name      VARCHAR(150)    NOT NULL,
    location            VARCHAR(255)    NOT NULL,
    installation_date   DATE            NOT NULL,
    status              ENUM('Active', 'Inactive', 'Under Maintenance') DEFAULT 'Active',
    description         TEXT,
    created_by          INT,                                      -- FK to users.id
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: water_usage
-- Daily water usage records per tube well
-- ============================================================
CREATE TABLE IF NOT EXISTS water_usage (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tube_well_id    INT             NOT NULL,
    usage_date      DATE            NOT NULL,
    water_amount    DECIMAL(12, 2)  NOT NULL,                     -- in Liters
    remarks         TEXT,
    recorded_by     INT,                                          -- FK to users.id
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tube_well_id) REFERENCES tube_wells(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: electricity_usage
-- Daily electricity consumption per tube well
-- ============================================================
CREATE TABLE IF NOT EXISTS electricity_usage (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    tube_well_id    INT             NOT NULL,
    usage_date      DATE            NOT NULL,
    units_consumed  DECIMAL(10, 2)  NOT NULL,                     -- kWh
    cost_per_unit   DECIMAL(8, 2)   NOT NULL DEFAULT 0.00,        -- local currency per kWh
    total_cost      DECIMAL(12, 2)  GENERATED ALWAYS AS (units_consumed * cost_per_unit) STORED,
    remarks         TEXT,
    recorded_by     INT,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tube_well_id) REFERENCES tube_wells(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE: maintenance
-- Maintenance tickets / repair history per tube well
-- ============================================================
CREATE TABLE IF NOT EXISTS maintenance (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    tube_well_id        INT             NOT NULL,
    issue               TEXT            NOT NULL,
    maintenance_date    DATE            NOT NULL,
    status              ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
    severity            ENUM('Routine', 'Warning', 'Critical')      DEFAULT 'Routine',
    technician_name     VARCHAR(100),
    remarks             TEXT,
    reported_by         INT,                                        -- FK to users.id
    resolved_at         DATETIME,
    created_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tube_well_id) REFERENCES tube_wells(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by)  REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA: Default User Accounts
-- Passwords generated with password_hash('admin123', PASSWORD_BCRYPT)
-- ============================================================
INSERT INTO users (name, email, password, role) VALUES
('System Admin',    'admin@hydrologic.io',    '$2y$10$0cuw5A4dPyr8Q5S/BAThe./NifLaXrf/P1bVEkRLcb6ZOYYf55p0m', 'Admin'),
('Field Operator',  'operator@hydrologic.io', '$2y$10$hUGhoroLEb6Itg0uIMySguKxs1awHhspQk9WaoxTyavL/Vhmo4m7y', 'Operator');
-- Passwords: admin@hydrologic.io → admin123 | operator@hydrologic.io → operator123

-- ============================================================
-- SEED DATA: Tube Wells
-- ============================================================
INSERT INTO tube_wells (well_code, tube_well_name, location, installation_date, status, description, created_by) VALUES
('TW-001-A', 'Sector A Main Well',       'North Gate, Sector A',         '2022-03-15', 'Active',            'Primary water source for Sector A residential area.',          1),
('TW-002-B', 'Sector B Backup',          'Green Park, Sector B',         '2022-06-10', 'Under Maintenance', 'Backup unit for Sector B. Awaiting pump replacement.',         1),
('TW-003-C', 'Industrial Zone Feed',     'Factory Area, Block 4',        '2021-11-20', 'Active',            'High-capacity industrial pump for manufacturing blocks.',       1),
('TW-004-D', 'South Reservoir Feed',     'Basin Area, South Wing',       '2023-01-08', 'Active',            'Primary feed for south storage reservoir.',                    1),
('TW-005-E', 'Emergency Backup 01',      'Perimeter Fence, East Side',   '2023-03-22', 'Active',            'Standby emergency unit. Activated on grid failure.',           1),
('TW-006-F', 'Central Hub Main',         'Central Hub, Zone C',          '2020-07-14', 'Active',            'Core distribution pump feeding the main grid.',                1),
('TW-007-G', 'Northern Well Alpha',      'Sector 4, Zone G',             '2021-03-12', 'Active',            'Northern cluster primary extraction well.',                    1),
('TW-008-H', 'Main Plant Intake B',      'Central Hub, Main Plant',      '2022-11-28', 'Under Maintenance', 'High-flow intake. Under maintenance for bearing replacement.', 1),
('TW-009-I', 'Western Boundary Well',    'West Perimeter, Block 9',      '2023-05-05', 'Inactive',          'Decommissioned pending zone rezoning approval.',               1),
('TW-010-J', 'Deep Aquifer Tap 01',      'Underground Facility, Floor 2','2024-01-15', 'Active',            'Deep aquifer access point for emergency reserves.',            1);

-- ============================================================
-- SEED DATA: Water Usage Records (last 30 days)
-- ============================================================
INSERT INTO water_usage (tube_well_id, usage_date, water_amount, remarks, recorded_by) VALUES
(1, CURDATE() - INTERVAL 1  DAY, 14250.00, 'Routine industrial cooling cycle.',         2),
(3, CURDATE() - INTERVAL 1  DAY, 8400.00,  'Shift changeover consumption.',             2),
(6, CURDATE() - INTERVAL 2  DAY, 12100.00, 'Standard operations.',                      2),
(4, CURDATE() - INTERVAL 2  DAY, 5600.00,  'Tank refilling procedure.',                 2),
(1, CURDATE() - INTERVAL 3  DAY, 16800.00, 'Peak demand period - day shift.',           2),
(7, CURDATE() - INTERVAL 3  DAY, 9200.00,  'Normal extraction flow.',                   2),
(6, CURDATE() - INTERVAL 4  DAY, 11500.00, 'Standard operations.',                      2),
(3, CURDATE() - INTERVAL 4  DAY, 7300.00,  'Reduced output during maintenance window.', 2),
(1, CURDATE() - INTERVAL 5  DAY, 15200.00, 'Irrigation supply peak.',                   2),
(4, CURDATE() - INTERVAL 5  DAY, 6100.00,  'Reservoir top-up.',                         2),
(10,CURDATE() - INTERVAL 6  DAY, 4800.00,  'Test extraction from new deep tap.',        1),
(6, CURDATE() - INTERVAL 6  DAY, 13400.00, 'High-flow weekend operations.',             2),
(1, CURDATE() - INTERVAL 7  DAY, 17100.00, 'Monthly peak record.',                      2),
(3, CURDATE() - INTERVAL 7  DAY, 8900.00,  'Industrial use.',                           2),
(7, CURDATE() - INTERVAL 8  DAY, 9700.00,  'Northern sector demand.',                   2),
(4, CURDATE() - INTERVAL 8  DAY, 5200.00,  'Routine daily fill.',                       2),
(6, CURDATE() - INTERVAL 9  DAY, 12300.00, 'Standard flow.',                            2),
(1, CURDATE() - INTERVAL 9  DAY, 14900.00, 'Day shift peak.',                           2),
(3, CURDATE() - INTERVAL 10 DAY, 7600.00,  'Industrial process cooling.',               2),
(10,CURDATE() - INTERVAL 10 DAY, 5100.00,  'Deep aquifer maintenance draw.',            1);

-- ============================================================
-- SEED DATA: Electricity Usage Records
-- ============================================================
INSERT INTO electricity_usage (tube_well_id, usage_date, units_consumed, cost_per_unit, remarks, recorded_by) VALUES
(1, CURDATE() - INTERVAL 1  DAY, 145.50, 18.50, 'Normal operating cycle.',        2),
(3, CURDATE() - INTERVAL 1  DAY, 210.25, 18.50, 'Industrial heavy load.',         2),
(6, CURDATE() - INTERVAL 2  DAY, 188.00, 18.50, 'Central hub distribution.',      2),
(4, CURDATE() - INTERVAL 2  DAY,  95.75, 18.50, 'Low-draw reservoir pump.',       2),
(1, CURDATE() - INTERVAL 3  DAY, 162.00, 18.50, 'Peak demand.',                   2),
(7, CURDATE() - INTERVAL 3  DAY, 113.40, 18.50, 'Normal northern ops.',           2),
(6, CURDATE() - INTERVAL 4  DAY, 175.60, 18.50, 'Standard load.',                 2),
(3, CURDATE() - INTERVAL 4  DAY, 198.20, 18.50, 'Heavy industrial cycle.',        2),
(1, CURDATE() - INTERVAL 5  DAY, 155.80, 18.50, 'Irrigation peak.',               2),
(10,CURDATE() - INTERVAL 5  DAY,  87.30, 18.50, 'Deep pump test.',                1),
(6, CURDATE() - INTERVAL 6  DAY, 201.10, 18.50, 'Weekend high flow.',             2),
(1, CURDATE() - INTERVAL 7  DAY, 178.90, 18.50, 'Monthly record day.',            2);

-- ============================================================
-- SEED DATA: Maintenance Tickets
-- ============================================================
INSERT INTO maintenance (tube_well_id, issue, maintenance_date, status, severity, technician_name, remarks, reported_by) VALUES
(2, 'Pump bearing overheating – thermal sensor triggered.',          CURDATE() - INTERVAL 2  DAY, 'In Progress', 'Critical',  'Khalid Mahmood',  'Bearing ordered. Awaiting part delivery.',           2),
(8, 'Scheduled quarterly filter replacement.',                       CURDATE() - INTERVAL 5  DAY, 'Pending',     'Routine',   NULL,              'Queued for next scheduled maintenance window.',     2),
(7, 'Inconsistent pressure reading – calibration required.',         CURDATE() - INTERVAL 8  DAY, 'Completed',   'Warning',   'Tariq Hassan',    'Calibration completed. Sensor replaced.',           1),
(9, 'Power surge damage after storm – backup offline.',              CURDATE() - INTERVAL 12 DAY, 'Completed',   'Critical',  'Zubair Ali',      'Electrical panel replaced. Tested and cleared.',    1),
(1, 'Minor gasket leak at junction valve.',                          CURDATE() - INTERVAL 3  DAY, 'In Progress', 'Warning',   'Bilal Farooq',    'Gasket replacement in progress.',                   2),
(6, 'Annual inspection and lubrication.',                            CURDATE() - INTERVAL 15 DAY, 'Completed',   'Routine',   'Aman Ullah',      'All systems verified. Lubrication done.',           1),
(10,'Deep pump motor showing unusual vibration at startup.',         CURDATE() - INTERVAL 1  DAY, 'Pending',     'Critical',  NULL,              'Vibration analysis scheduled.',                     2),
(3, 'High-pressure relief valve needs replacement.',                 CURDATE() - INTERVAL 6  DAY, 'In Progress', 'Warning',   'Saleem Baig',     'Valve sourced. Installation scheduled for tomorrow.',2),
(4, 'Flow meter calibration drift detected.',                        CURDATE() - INTERVAL 20 DAY, 'Completed',   'Routine',   'Khalid Mahmood',  'Recalibrated and verified against master gauge.',   1),
(5, 'Routine check before monsoon season.',                          CURDATE() - INTERVAL 25 DAY, 'Completed',   'Routine',   'Tariq Hassan',    'All checks passed. Emergency systems armed.',       1);
