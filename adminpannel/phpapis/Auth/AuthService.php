<?php

require_once '../Database/DatabaseManager.php';

class AuthService {
    private $db;
    private $jwtSecret = 'your-secret-key-change-this-in-production';

    public function __construct() {
        $this->db = new DatabaseManager();
    }

    public function register($data) {
        try {
            // Validate input
            $this->validateRegistrationData($data);

            // Check if user already exists
            $existingUser = $this->db->fetchOne(
                "SELECT id FROM users WHERE email = ? OR username = ?",
                [$data['email'], $data['username']]
            );

            if ($existingUser) {
                return ['success' => false, 'message' => 'User already exists with this email or username'];
            }

            // Hash password
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user
            $userId = $this->db->insert('users', [
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $passwordHash,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
                'is_verified' => false
            ]);

            // Assign default user role
            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role_name' => 'user'
            ]);

            // Generate OTP for email verification
            $otpCode = $this->generateOTP();
            $this->storeOTP($userId, $data['email'], $otpCode, 'email');

            return [
                'success' => true,
                'message' => 'User registered successfully. Please verify your email.',
                'user_id' => $userId,
                'otp_sent' => true
            ];

        } catch (Exception $e) {
            error_log("Registration failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }

    public function login($email, $password) {
        try {
            // Find user by email
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ? AND is_active = TRUE",
                [$email]
            );

            if (!$user || !password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            // Update last login
            $this->db->update('users', 
                ['last_login_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$user['id']]
            );

            // Generate session token
            $sessionToken = $this->generateSessionToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $this->db->insert('user_sessions', [
                'user_id' => $user['id'],
                'session_token' => $sessionToken,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'expires_at' => $expiresAt
            ]);

            // Get user roles
            $roles = $this->db->fetchAll(
                "SELECT role_name FROM user_roles WHERE user_id = ?",
                [$user['id']]
            );

            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'is_verified' => $user['is_verified'],
                    'roles' => array_column($roles, 'role_name')
                ],
                'session_token' => $sessionToken
            ];

        } catch (Exception $e) {
            error_log("Login failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }

    public function verifyOTP($email, $otpCode, $type = 'email') {
        try {
            $otpRecord = $this->db->fetchOne(
                "SELECT * FROM otp_verification WHERE email = ? AND otp_code = ? AND verification_type = ? AND expires_at > NOW() AND verified_at IS NULL",
                [$email, $otpCode, $type]
            );

            if (!$otpRecord) {
                return ['success' => false, 'message' => 'Invalid or expired OTP'];
            }

            // Mark OTP as verified
            $this->db->update('otp_verification', 
                ['verified_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$otpRecord['id']]
            );

            // If email verification, mark user as verified
            if ($type === 'email' && $otpRecord['user_id']) {
                $this->db->update('users', 
                    ['is_verified' => true, 'email_verified_at' => date('Y-m-d H:i:s')], 
                    'id = ?', 
                    [$otpRecord['user_id']]
                );
            }

            return ['success' => true, 'message' => 'OTP verified successfully'];

        } catch (Exception $e) {
            error_log("OTP verification failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'OTP verification failed: ' . $e->getMessage()];
        }
    }

    public function requestPasswordReset($email) {
        try {
            $user = $this->db->fetchOne(
                "SELECT id FROM users WHERE email = ? AND is_active = TRUE",
                [$email]
            );

            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }

            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store reset token
            $this->db->insert('password_reset_tokens', [
                'user_id' => $user['id'],
                'token' => $resetToken,
                'expires_at' => $expiresAt
            ]);

            // Generate OTP for verification
            $otpCode = $this->generateOTP();
            $this->storeOTP($user['id'], $email, $otpCode, 'password_reset');

            return [
                'success' => true,
                'message' => 'Password reset instructions sent to your email',
                'reset_token' => $resetToken,
                'otp_sent' => true
            ];

        } catch (Exception $e) {
            error_log("Password reset request failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password reset request failed: ' . $e->getMessage()];
        }
    }

    public function resetPassword($token, $otpCode, $newPassword) {
        try {
            // Verify OTP first
            $otpResult = $this->verifyOTP(null, $otpCode, 'password_reset');
            if (!$otpResult['success']) {
                return $otpResult;
            }

            // Find valid reset token
            $resetToken = $this->db->fetchOne(
                "SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() AND used_at IS NULL",
                [$token]
            );

            if (!$resetToken) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }

            // Update password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->update('users', 
                ['password_hash' => $passwordHash], 
                'id = ?', 
                [$resetToken['user_id']]
            );

            // Mark token as used
            $this->db->update('password_reset_tokens', 
                ['used_at' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$resetToken['id']]
            );

            // Invalidate all user sessions
            $this->db->executeQuery(
                "DELETE FROM user_sessions WHERE user_id = ?",
                [$resetToken['user_id']]
            );

            return ['success' => true, 'message' => 'Password reset successfully'];

        } catch (Exception $e) {
            error_log("Password reset failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Password reset failed: ' . $e->getMessage()];
        }
    }

    public function validateSession($sessionToken) {
        try {
            $session = $this->db->fetchOne(
                "SELECT s.*, u.* FROM user_sessions s 
                 JOIN users u ON s.user_id = u.id 
                 WHERE s.session_token = ? AND s.expires_at > NOW() AND u.is_active = TRUE",
                [$sessionToken]
            );

            if (!$session) {
                return ['success' => false, 'message' => 'Invalid or expired session'];
            }

            // Get user roles
            $roles = $this->db->fetchAll(
                "SELECT role_name FROM user_roles WHERE user_id = ?",
                [$session['user_id']]
            );

            return [
                'success' => true,
                'user' => [
                    'id' => $session['user_id'],
                    'username' => $session['username'],
                    'email' => $session['email'],
                    'first_name' => $session['first_name'],
                    'last_name' => $session['last_name'],
                    'is_verified' => $session['is_verified'],
                    'roles' => array_column($roles, 'role_name')
                ]
            ];

        } catch (Exception $e) {
            error_log("Session validation failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Session validation failed: ' . $e->getMessage()];
        }
    }

    public function logout($sessionToken) {
        try {
            $this->db->executeQuery(
                "DELETE FROM user_sessions WHERE session_token = ?",
                [$sessionToken]
            );

            return ['success' => true, 'message' => 'Logged out successfully'];

        } catch (Exception $e) {
            error_log("Logout failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Logout failed: ' . $e->getMessage()];
        }
    }

    private function validateRegistrationData($data) {
        $required = ['username', 'email', 'password', 'first_name', 'last_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if (strlen($data['password']) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }
    }

    private function generateOTP() {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function storeOTP($userId, $email, $otpCode, $type) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $this->db->insert('otp_verification', [
            'user_id' => $userId,
            'email' => $email,
            'otp_code' => $otpCode,
            'verification_type' => $type,
            'expires_at' => $expiresAt
        ]);
    }

    private function generateSessionToken() {
        return bin2hex(random_bytes(32));
    }
}

?>

