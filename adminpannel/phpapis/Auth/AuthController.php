<?php

require_once 'AuthService.php';

class AuthController {
    private $authService;

    public function __construct() {
        $this->authService = new AuthService();
        $this->setCorsHeaders();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'register':
                if ($method === 'POST') {
                    $this->register();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'verify-otp':
                if ($method === 'POST') {
                    $this->verifyOTP();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'request-password-reset':
                if ($method === 'POST') {
                    $this->requestPasswordReset();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'reset-password':
                if ($method === 'POST') {
                    $this->resetPassword();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'validate-session':
                if ($method === 'GET') {
                    $this->validateSession();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            case 'logout':
                if ($method === 'POST') {
                    $this->logout();
                } else {
                    $this->sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
                }
                break;

            default:
                $this->sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    }

    private function register() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
            return;
        }

        $result = $this->authService->register($input);
        $statusCode = $result['success'] ? 201 : 400;
        $this->sendResponse($result, $statusCode);
    }

    private function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['email']) || !isset($input['password'])) {
            $this->sendResponse(['success' => false, 'message' => 'Email and password are required'], 400);
            return;
        }

        $result = $this->authService->login($input['email'], $input['password']);
        $statusCode = $result['success'] ? 200 : 401;
        $this->sendResponse($result, $statusCode);
    }

    private function verifyOTP() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['email']) || !isset($input['otp_code'])) {
            $this->sendResponse(['success' => false, 'message' => 'Email and OTP code are required'], 400);
            return;
        }

        $type = $input['type'] ?? 'email';
        $result = $this->authService->verifyOTP($input['email'], $input['otp_code'], $type);
        $statusCode = $result['success'] ? 200 : 400;
        $this->sendResponse($result, $statusCode);
    }

    private function requestPasswordReset() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['email'])) {
            $this->sendResponse(['success' => false, 'message' => 'Email is required'], 400);
            return;
        }

        $result = $this->authService->requestPasswordReset($input['email']);
        $statusCode = $result['success'] ? 200 : 400;
        $this->sendResponse($result, $statusCode);
    }

    private function resetPassword() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['token']) || !isset($input['otp_code']) || !isset($input['new_password'])) {
            $this->sendResponse(['success' => false, 'message' => 'Token, OTP code, and new password are required'], 400);
            return;
        }

        $result = $this->authService->resetPassword($input['token'], $input['otp_code'], $input['new_password']);
        $statusCode = $result['success'] ? 200 : 400;
        $this->sendResponse($result, $statusCode);
    }

    private function validateSession() {
        $sessionToken = $_GET['token'] ?? '';
        
        if (empty($sessionToken)) {
            $this->sendResponse(['success' => false, 'message' => 'Session token is required'], 400);
            return;
        }

        $result = $this->authService->validateSession($sessionToken);
        $statusCode = $result['success'] ? 200 : 401;
        $this->sendResponse($result, $statusCode);
    }

    private function logout() {
        $input = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $input['session_token'] ?? '';
        
        if (empty($sessionToken)) {
            $this->sendResponse(['success' => false, 'message' => 'Session token is required'], 400);
            return;
        }

        $result = $this->authService->logout($sessionToken);
        $statusCode = $result['success'] ? 200 : 400;
        $this->sendResponse($result, $statusCode);
    }

    private function setCorsHeaders() {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit();
    }
}

// Handle the request
$controller = new AuthController();
$controller->handleRequest();

?>

