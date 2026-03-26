<?php

class LicenseValidator
{
    private $licenseServer = 'https://license.lpal.net/api/license';
    private $licenseFile;
    private $encryptionKey = 'vidpowr_license_key_2024';

    public function __construct()
    {
        $baseDir = __DIR__ . '/..';
        $storageDir = $baseDir . '/storage/license';
        
        // Create storage directory if it doesn't exist
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        
        $this->licenseFile = $storageDir . '/license.json';
    }

    public function validate($licenseKey)
    {
        // First validate format
        if (!$this->isValidFormat($licenseKey)) {
            return [
                'success' => false,
                'error' => 'Invalid license key format'
            ];
        }

        // Get server fingerprint
        $fingerprint = $this->generateFingerprint();
        $domain = $this->getCurrentDomain();

        // Validate against license server
        $response = $this->validateWithServer($licenseKey, $domain, $fingerprint);
        
        // echo json_encode([
        //     "domain" => $domain,
        //     "fingerprint" => $fingerprint,
        //     "key" => $licenseKey,
        //     "response" => $response
        // ]);
        // die();
        
        if ($response['success']) {
            // Store license locally (but don't fail if storage fails)
            $stored = $this->storeLicense($response['data']);
            if (!$stored) {
                error_log("Warning: Failed to store license locally, but validation succeeded");
            }
            return $response;
        } else {
            // Check for offline grace period
            return $this->checkOfflineGracePeriod($licenseKey);
        }
    }

    public function getStoredLicense()
    {
        if (file_exists($this->licenseFile)) {
            $encrypted = file_get_contents($this->licenseFile);
            $licenseData = json_decode($this->decrypt($encrypted), true);
            return $licenseData;
        }
        return null;
    }
    
    public function getLicenseFile()
    {
        return $this->licenseFile;
    }

    private function isValidFormat($licenseKey)
    {
        // Trim whitespace and convert to uppercase
        $licenseKey = strtoupper(trim($licenseKey));
        
        // Format: PREFIX-XXXXXXXXXXXX-XXXX (variable prefix - 12 chars - 4 chars checksum)
        $isValid = preg_match('/^[A-Z]+-[A-Z0-9]{12}-[A-Z0-9]{4}$/', $licenseKey);
        
        return $isValid;
    }

    private function generateFingerprint()
    {
        $data = [
            'hostname' => gethostname(),
            'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
        ];
        
        return hash('sha256', serialize($data));
    }

    private function getCurrentDomain()
    {
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Remove www. prefix
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }
        
        return $domain;
    }

    private function validateWithServer($licenseKey, $domain, $fingerprint)
    {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config/servers.json'), true);
        $baseUrl = $config['license_servers']['vidpowr'] ?? 'https://license.lpal.net';
        $endpoint = $config['endpoints']['validate_license'] ?? '/license/validate';
        
        $data = [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'fingerprint' => $fingerprint,
            'version' => '1.0.0',
            'timestamp' => time()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: VidPowr-Installer/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Debug logging
        error_log("License Server URL: " . $baseUrl . $endpoint);
        error_log("Request Data: " . json_encode($data));
        error_log("HTTP Code: " . $httpCode);
        error_log("CURL Error: " . $error);
        error_log("Response: " . $response);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Cannot connect to license server: ' . $error
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'License server returned error: HTTP ' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        
        if (!$result || !isset($result['valid'])) {
            return [
                'success' => false,
                'error' => 'Invalid response from license server'
            ];
        }

        if (!$result['valid']) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'License key is invalid or expired'
            ];
        }

        // Verify signature
        if (!$this->verifySignature($result)) {
            return [
                'success' => false,
                'error' => 'Invalid license signature'
            ];
        }

        // Map license server response to installer format
        return [
            'success' => true,
            'data' => [
                'license_key' => $licenseKey,
                'type' => $result['license_type'] ?? 'normal',
                'features' => $this->getFeaturesForLicenseType($result['license_type'] ?? 'normal'),
                'expires' => $result['expires_at'] ?? null,
                'domain' => $domain,
                'fingerprint' => $fingerprint,
                'validated_at' => date('Y-m-d H:i:s'),
                'signature' => $result['signature'] ?? 'no_signature',
                'licensed_app' => $this->parseAppFromLicenseKey($licenseKey)
            ]
        ];
    }
    
    private function getFeaturesForLicenseType($licenseType)
    {
        if ($licenseType === 'agency') {
            return ['video_processing', 'agency_mode', 'api_access', 'white_label', 'multi_domain'];
        } else {
            return ['video_processing', 'basic_analytics'];
        }
    }

    private function verifySignature($data)
    {
        if (!isset($data['signature'])) {
            return false;
        }

        $signature = $data['signature'];
        unset($data['signature']);
        
        $expectedSignature = hash_hmac('sha256', json_encode($data), $this->encryptionKey);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function storeLicense($licenseData)
    {
        try {
            $encrypted = $this->encrypt(json_encode($licenseData));
            $result = file_put_contents($this->licenseFile, $encrypted);
            
            if ($result === false) {
                error_log("Failed to write license file: " . $this->licenseFile);
                return false;
            }
            
            // Try to set permissions, but don't fail if it doesn't work
            @chmod($this->licenseFile, 0600);
            
            return true;
        } catch (\Exception $e) {
            error_log("Error storing license: " . $e->getMessage());
            return false;
        }
    }

    private function checkOfflineGracePeriod($licenseKey)
    {
        $storedLicense = $this->getStoredLicense();
        
        if (!$storedLicense) {
            return [
                'success' => false,
                'error' => 'No internet connection and no stored license found'
            ];
        }

        // Check if within grace period (30 days)
        $validatedAt = strtotime($storedLicense['validated_at']);
        $gracePeriod = 30 * 24 * 60 * 60; // 30 days in seconds
        
        if (time() - $validatedAt > $gracePeriod) {
            return [
                'success' => false,
                'error' => 'License grace period expired. Please connect to internet to validate.'
            ];
        }

        // Verify domain and fingerprint haven't changed
        if ($storedLicense['domain'] !== $this->getCurrentDomain()) {
            return [
                'success' => false,
                'error' => 'License domain mismatch. Please contact support.'
            ];
        }

        return [
            'success' => true,
            'data' => $storedLicense,
            'offline' => true
        ];
    }

    private function encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private function decrypt($encryptedData)
    {
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryptionKey, 0, $iv);
    }

    public function isValidLicense()
    {
        $license = $this->getStoredLicense();
        
        if (!$license) {
            return false;
        }

        // Check expiration
        if ($license['expires'] && strtotime($license['expires']) < time()) {
            return false;
        }

        // Check domain
        if ($license['domain'] !== $this->getCurrentDomain()) {
            return false;
        }

        return true;
    }

    public function getLicenseType()
    {
        $license = $this->getStoredLicense();
        return $license ? $license['type'] : 'normal';
    }

    public function hasFeature($feature)
    {
        $license = $this->getStoredLicense();
        return $license && in_array($feature, $license['features'] ?? []);
    }

    public function getLicensedApp()
    {
        $license = $this->getStoredLicense();
        return $license ? $license['licensed_app'] ?? null : null;
    }

    public function getProductsInfo()
    {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/config/servers.json'), true);
        $baseUrl = $config['license_servers']['vidpowr'] ?? 'https://license.lpal.net';
        $endpoint = $config['endpoints']['get_products_info'] ?? '/api/license/all/products';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: VidPowr-Installer/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'Cannot connect to license server: ' . $error
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'License server returned error: HTTP ' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        
        if (!$result) {
            return [
                'success' => false,
                'error' => 'Invalid response from license server'
            ];
        }

        return $result;
    }

    private function parseAppFromLicenseKey($licenseKey)
    {
        // Parse app from license key pattern
        // Format: PREFIX-XXXXXXXXXXXX-XXXXX where PREFIX identifies the application
        $licenseKey = strtoupper(trim($licenseKey));
        
        // Extract prefix from the license key (everything before the first dash)
        $parts = explode('-', $licenseKey);
        $prefix = $parts[0] ?? '';
        
        // Map app prefixes to app IDs
        $appMap = [
            'VIDP' => 'vidpowr',
            'FEED' => 'feedplay', 
            'VIDC' => 'vidchapter',
            'VIDT' => 'vidtags'
        ];
        
        return $appMap[$prefix] ?? 'vidpowr';
    }
}
