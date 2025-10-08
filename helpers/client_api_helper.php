<?php
/**
 * Client API Helper Class
 * Handles communication with remote Client API server
 */

class ClientAPIHelper {
    private $config;
    private $apiKey;
    private $baseUrl;
    private $timeout;
    private $retryAttempts;
    private $retryDelay;

    public function __construct($apiKey = null) {
        // Load configuration
        $this->config = require __DIR__ . '/../config.php';
        
        // Get API key from parameter or installation data
        $this->apiKey = $apiKey ?: $this->getInstallationApiKey();
        
        // Set configuration
        $this->baseUrl = $this->config['client_api']['remote_clientapi_endpoint_url'] ?? '';
        $this->timeout = $this->config['client_api']['timeout'] ?? 30;
        $this->retryAttempts = $this->config['client_api']['retry_attempts'] ?? 3;
        $this->retryDelay = $this->config['client_api']['retry_delay'] ?? 2;
    }

    /**
     * Get installation API key from database
     */
    private function getInstallationApiKey() {
        try {
            require_once __DIR__ . '/database.php';
            $db = get_db_connection();
            
            $stmt = $db->query("SELECT api_key FROM installation_data ORDER BY id DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['api_key'] : null;
        } catch (Exception $e) {
            error_log("Error getting installation API key: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Make API request with retry logic
     */
    private function makeRequest($method, $action, $data = null) {
        if (!$this->apiKey) {
            return [
                'success' => false,
                'message' => 'API key not found',
                'error' => 'NO_API_KEY'
            ];
        }

        if (!$this->baseUrl) {
            return [
                'success' => false,
                'message' => 'Client API not configured',
                'error' => 'NO_API_URL'
            ];
        }

        $url = $this->baseUrl . '?action=' . $action . '&api_key=' . urlencode($this->apiKey);
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'X-API-Key: ' . $this->apiKey
                ],
                'timeout' => $this->timeout
            ]
        ];

        if ($data && in_array($method, ['POST', 'PUT'])) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);

        // Retry logic
        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                // Use cURL for better error handling
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'X-API-Key: ' . $this->apiKey
                ]);
                
                if ($data && in_array($method, ['POST', 'PUT'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                
                $result = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($result === false) {
                    throw new Exception('cURL error: ' . $curlError);
                }

                // Log the raw response for debugging
                error_log("Client API raw response (HTTP $httpCode): " . $result);

                $response = json_decode($result, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    error_log("Raw response that failed to decode: " . $result);
                    throw new Exception('Invalid JSON response from Client API: ' . json_last_error_msg());
                }

                // Log the parsed response for debugging
                error_log("Client API parsed response: " . json_encode($response));

                return $response;

            } catch (Exception $e) {
                error_log("Client API request attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt < $this->retryAttempts) {
                    sleep($this->retryDelay);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Client API request failed after ' . $this->retryAttempts . ' attempts',
                        'error' => 'API_REQUEST_FAILED',
                        'details' => $e->getMessage()
                    ];
                }
            }
        }
    }

    /**
     * Get client information from remote server
     */
    public function getClientInfo() {
        return $this->makeRequest('GET', 'info');
    }

    /**
     * Create client on remote server
     */
    public function createClient($data) {
        $response = $this->makeRequest('POST', 'create', $data);
        return $this->handleValidationError($response);
    }

    /**
     * Update client on remote server
     */
    public function updateClient($data) {
        // Map data to the exact format expected by the Client API
        $updateData = [
            'client_id' => $data['client_name'] ?? $data['Clientid'] ?? '',
            'logo_url' => $data['logo_url'] ?? $data['App_logo_url'] ?? ''
        ];
        
        $response = $this->makeRequest('PUT', 'update', $updateData);
        return $this->handleValidationError($response);
    }

    /**
     * Delete client on remote server (soft delete)
     */
    public function deleteClient() {
        return $this->makeRequest('DELETE', 'delete');
    }

    /**
     * Check if Client API is available
     */
    public function isApiAvailable() {
        $response = $this->getClientInfo();
        return $response['success'] ?? false;
    }

    /**
     * Sync local data with remote server
     */
    public function syncWithRemote($localData) {
        try {
            // First, try to get existing client info
            $clientInfo = $this->getClientInfo();
            
            if ($clientInfo['success']) {
                // Client exists, update it using PUT request
                return $this->updateClient($localData);
            } else {
                // Client doesn't exist, create it
                return $this->createClient($localData);
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
                'error' => 'SYNC_FAILED'
            ];
        }
    }

    /**
     * Get client data for mobile app settings
     */
    public function getMobileAppData() {
        $response = $this->getClientInfo();
        
        if ($response['success'] && isset($response['data'])) {
            $data = $response['data'];
            return [
                'success' => true,
                'data' => [
                    'Clientid' => $data['client_id'] ?? '',
                    'APIKey' => $data['api_key'] ?? '',
                    'App_logo_url' => $data['logo_url'] ?? ''
                ]
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to get client data from remote server',
            'error' => 'REMOTE_DATA_FAILED'
        ];
    }

    /**
     * Handle Client ID validation errors
     */
    private function handleValidationError($response) {
        if (!$response['success']) {
            $error = $response['error'] ?? '';
            $message = $response['message'] ?? 'Unknown error';
            
            switch ($error) {
                case 'CLIENT_ID_EXISTS':
                    return [
                        'success' => false,
                        'message' => 'Client ID already exists. Please choose a different Client ID.',
                        'error' => 'DUPLICATE_CLIENT_ID',
                        'validation_error' => true,
                        'field' => 'Clientid',
                        'suggestion' => 'Try adding numbers or changing the format (e.g., ' . ($response['client_id'] ?? 'CLIENT') . '_' . date('Y') . ')'
                    ];
                    
                case 'INVALID_CLIENT_ID':
                    $criteria = $response['criteria'] ?? [];
                    $formatMessage = 'Client ID format is invalid. ';
                    
                    if (isset($criteria['max_length'])) {
                        $formatMessage .= "Maximum length: {$criteria['max_length']} characters. ";
                    }
                    if (isset($criteria['min_length'])) {
                        $formatMessage .= "Minimum length: {$criteria['min_length']} characters. ";
                    }
                    if (isset($criteria['allowed_characters'])) {
                        $formatMessage .= "Allowed characters: {$criteria['allowed_characters']}. ";
                    }
                    if (isset($criteria['format'])) {
                        $formatMessage .= "Format: {$criteria['format']}.";
                    }
                    
                    return [
                        'success' => false,
                        'message' => $formatMessage,
                        'error' => 'INVALID_CLIENT_ID_FORMAT',
                        'validation_error' => true,
                        'field' => 'Clientid',
                        'criteria' => $criteria
                    ];
                    
                default:
                    return [
                        'success' => false,
                        'message' => $message,
                        'error' => 'REMOTE_VALIDATION_ERROR',
                        'validation_error' => true
                    ];
            }
        }
        
        return $response;
    }

    /**
     * Validate Client ID format locally
     */
    public function validateClientId($clientId) {
        $errors = [];
        
        // Check length
        if (strlen($clientId) > 10) {
            $errors[] = 'Client ID cannot exceed 10 characters';
        }
        if (strlen($clientId) < 3) {
            $errors[] = 'Client ID must be at least 3 characters';
        }
        
        // Check format (alphanumeric and underscore only)
        if (!preg_match('/^[A-Za-z0-9_]+$/', $clientId)) {
            $errors[] = 'Client ID can only contain letters, numbers, and underscores';
        }
        
        // Check reserved prefixes
        if (preg_match('/^(CLI_|INST_|API_)/', $clientId)) {
            $errors[] = 'Client ID cannot start with reserved prefixes (CLI_, INST_, API_)';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'criteria' => [
                'max_length' => 10,
                'min_length' => 3,
                'allowed_characters' => 'alphanumeric and underscore only',
                'format' => 'Should be alphanumeric with optional underscore',
                'reserved_prefixes' => ['CLI_', 'INST_', 'API_']
            ]
        ];
    }
}
?>
