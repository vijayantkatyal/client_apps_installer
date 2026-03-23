<?php
/**
 * Example License Server Implementation
 * This shows what your license server should look like
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration (example)
define('DB_HOST', 'localhost');
define('DB_NAME', 'license_server');
define('DB_USER', 'username');
define('DB_PASS', 'password');

// Encryption key for signatures (must match client)
define('ENCRYPTION_KEY', 'vidpowr_license_key_2024');

class LicenseServer
{
    private $db;
    
    public function __construct()
    {
        $this->db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
    }
    
    public function validateLicense()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            return $this->error('Invalid JSON input');
        }
        
        $required = ['license_key', 'domain', 'fingerprint', 'version', 'timestamp'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                return $this->error("Missing required field: $field");
            }
        }
        
        // Parse app from license key
        $licensedApp = $this->parseAppFromLicenseKey($input['license_key']);
        
        // Check license in database
        $license = $this->getLicenseFromDB($input['license_key']);
        
        if (!$license) {
            return $this->error('License key not found');
        }
        
        // Check if license is active
        if (!$license['is_active']) {
            return $this->error('License is deactivated');
        }
        
        // Check expiration
        if ($license['expires_at'] && strtotime($license['expires_at']) < time()) {
            return $this->error('License has expired');
        }
        
        // Check domain restrictions
        if ($license['domain_restriction'] && $license['domain_restriction'] !== $input['domain']) {
            return $this->error('License domain mismatch');
        }
        
        // Check activation limits
        if (!$this->checkActivationLimit($license['id'], $input['domain'], $input['fingerprint'])) {
            return $this->error('License activation limit exceeded');
        }
        
        // Record this validation
        $this->recordValidation($license['id'], $input['domain'], $input['fingerprint']);
        
        // Return successful validation
        $response = [
            'valid' => true,
            'type' => $license['license_type'],
            'features' => json_decode($license['features'], true),
            'expires' => $license['expires_at'],
            'max_users' => $license['max_users'],
            'licensed_app' => $licensedApp,
            'signature' => $this->generateSignature([
                'valid' => true,
                'type' => $license['license_type'],
                'features' => json_decode($license['features'], true),
                'expires' => $license['expires_at'],
                'max_users' => $license['max_users'],
                'licensed_app' => $licensedApp
            ])
        ];
        
        return $response;
    }
    
    private function parseAppFromLicenseKey($licenseKey)
    {
        $licenseKey = strtoupper(trim($licenseKey));
        
        $appMap = [
            'VIDPOWR' => 'vidpowr',
            'FEEDPLAY' => 'feedplay', 
            'VIDCHAPTER' => 'vidchapter',
            'VIDTAGS' => 'vidtags'
        ];
        
        foreach ($appMap as $prefix => $appId) {
            if (strpos($licenseKey, $prefix) === 0) {
                return $appId;
            }
        }
        
        return 'vidpowr'; // default
    }
    
    private function getLicenseFromDB($licenseKey)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM licenses 
            WHERE license_key = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$licenseKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function checkActivationLimit($licenseId, $domain, $fingerprint)
    {
        // Check how many active installations this license has
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM license_activations 
            WHERE license_id = ? AND last_seen > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$licenseId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get license activation limit
        $stmt = $this->db->prepare("
            SELECT activation_limit FROM licenses WHERE id = ?
        ");
        $stmt->execute([$licenseId]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] < $license['activation_limit'];
    }
    
    private function recordValidation($licenseId, $domain, $fingerprint)
    {
        // Update or insert activation record
        $stmt = $this->db->prepare("
            INSERT INTO license_activations 
            (license_id, domain, fingerprint, last_seen, created_at)
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            last_seen = NOW()
        ");
        $stmt->execute([$licenseId, $domain, $fingerprint]);
    }
    
    private function generateSignature($data)
    {
        return hash_hmac('sha256', json_encode($data), ENCRYPTION_KEY);
    }
    
    private function error($message)
    {
        return [
            'valid' => false,
            'error' => $message
        ];
    }
}

// Database schema example:
/*
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    license_type ENUM('normal', 'agency', 'enterprise') NOT NULL,
    features JSON NOT NULL,
    expires_at DATE NULL,
    max_users INT DEFAULT 10,
    activation_limit INT DEFAULT 1,
    domain_restriction VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE TABLE license_activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    fingerprint VARCHAR(64) NOT NULL,
    last_seen TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_activation (license_id, domain, fingerprint),
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);
*/

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/license/validate') {
    $server = new LicenseServer();
    $response = $server->validateLicense();
    echo json_encode($response);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}
?>
