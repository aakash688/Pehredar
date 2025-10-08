<?php

require_once 'DatabaseManager.php';

class SchemaManager {
    private $db;

    public function __construct() {
        $this->db = new DatabaseManager();
    }

    public function createTables() {
        $this->createUsersTable();
        $this->createUserSessionsTable();
        $this->createPasswordResetTokensTable();
        $this->createOtpVerificationTable();
        $this->createUserRolesTable();
        $this->createPermissionsTable();
        $this->createRolePermissionsTable();
        $this->createAuditLogsTable();
    }

    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            avatar VARCHAR(255),
            is_active BOOLEAN DEFAULT TRUE,
            is_verified BOOLEAN DEFAULT FALSE,
            email_verified_at TIMESTAMP NULL,
            phone_verified_at TIMESTAMP NULL,
            last_login_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_username (username),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createUserSessionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_session_token (session_token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createPasswordResetTokensTable() {
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createOtpVerificationTable() {
        $sql = "CREATE TABLE IF NOT EXISTS otp_verification (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            email VARCHAR(100),
            phone VARCHAR(20),
            otp_code VARCHAR(10) NOT NULL,
            verification_type ENUM('email', 'phone', 'password_reset') NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            verified_at TIMESTAMP NULL,
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_otp_code (otp_code),
            INDEX idx_email (email),
            INDEX idx_phone (phone),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createUserRolesTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            role_name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id, role_name),
            INDEX idx_user_id (user_id),
            INDEX idx_role_name (role_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createPermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            permission_name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_permission_name (permission_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createRolePermissionsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_name VARCHAR(50) NOT NULL,
            permission_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_permission (role_name, permission_name),
            INDEX idx_role_name (role_name),
            INDEX idx_permission_name (permission_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    private function createAuditLogsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100),
            record_id INT,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_table_name (table_name),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->executeQuery($sql);
    }

    public function initializeDefaultData() {
        $this->insertDefaultRoles();
        $this->insertDefaultPermissions();
        $this->assignDefaultRolePermissions();
    }

    private function insertDefaultRoles() {
        $roles = [
            ['role_name' => 'admin'],
            ['role_name' => 'moderator'],
            ['role_name' => 'user']
        ];

        foreach ($roles as $role) {
            $existing = $this->db->fetchOne("SELECT id FROM user_roles WHERE role_name = ?", [$role['role_name']]);
            if (!$existing) {
                $this->db->insert('user_roles', $role);
            }
        }
    }

    private function insertDefaultPermissions() {
        $permissions = [
            ['permission_name' => 'user.create', 'description' => 'Create users'],
            ['permission_name' => 'user.read', 'description' => 'View users'],
            ['permission_name' => 'user.update', 'description' => 'Update users'],
            ['permission_name' => 'user.delete', 'description' => 'Delete users'],
            ['permission_name' => 'admin.dashboard', 'description' => 'Access admin dashboard'],
            ['permission_name' => 'admin.settings', 'description' => 'Manage system settings']
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->fetchOne("SELECT id FROM permissions WHERE permission_name = ?", [$permission['permission_name']]);
            if (!$existing) {
                $this->db->insert('permissions', $permission);
            }
        }
    }

    private function assignDefaultRolePermissions() {
        $rolePermissions = [
            ['role_name' => 'admin', 'permission_name' => 'user.create'],
            ['role_name' => 'admin', 'permission_name' => 'user.read'],
            ['role_name' => 'admin', 'permission_name' => 'user.update'],
            ['role_name' => 'admin', 'permission_name' => 'user.delete'],
            ['role_name' => 'admin', 'permission_name' => 'admin.dashboard'],
            ['role_name' => 'admin', 'permission_name' => 'admin.settings'],
            ['role_name' => 'moderator', 'permission_name' => 'user.read'],
            ['role_name' => 'moderator', 'permission_name' => 'user.update'],
            ['role_name' => 'moderator', 'permission_name' => 'admin.dashboard'],
            ['role_name' => 'user', 'permission_name' => 'user.read']
        ];

        foreach ($rolePermissions as $rolePermission) {
            $existing = $this->db->fetchOne(
                "SELECT id FROM role_permissions WHERE role_name = ? AND permission_name = ?",
                [$rolePermission['role_name'], $rolePermission['permission_name']]
            );
            if (!$existing) {
                $this->db->insert('role_permissions', $rolePermission);
            }
        }
    }

    public function syncSchema() {
        try {
            $this->createTables();
            $this->initializeDefaultData();
            return ['success' => true, 'message' => 'Database schema synchronized successfully'];
        } catch (Exception $e) {
            error_log("Schema sync failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Schema synchronization failed: ' . $e->getMessage()];
        }
    }
}

?>

