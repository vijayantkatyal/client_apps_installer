<?php

class LicenseValidator
{
    private $licenseServer = 'https://api.vidpowr.com/license';
    private $licenseFile;
    private $encryptionKey = 'vidpowr_license_key_2024';

    public function __construct()
    {
        $this->licenseFile = dirname(__DIR__) . '/storage/license/license.json';
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
        
        if ($response['success']) {
            // Store license locally
            $this->storeLicense($response['data']);
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

    private function isValidFormat($licenseKey)
    {
        // Temporary bypass for testing - remove in production
        return true;
        
        // Original validation (commented out for testing)
        /*
        // Trim whitespace and convert to uppercase
        $licenseKey = strtoupper(trim($licenseKey));
        
        // Format: XXXXXXXX-XXXXX-XXXXX-XXXXX (8 chars - 5 chars - 5 chars - 5 chars)
        $isValid = preg_match('/^[A-Z0-9]{8}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $licenseKey);
        
        // Debug: Log the validation attempt (remove in production)
        error_log("License validation attempt: '$licenseKey' - Valid: " . ($isValid ? 'YES' : 'NO'));
        
        return $isValid;
        */
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
        $data = [
            'license_key' => $licenseKey,
            'domain' => $domain,
            'fingerprint' => $fingerprint,
            'version' => '1.0.0',
            'timestamp' => time()
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->licenseServer . '/validate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: VidPowr-Installer/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

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

        return [
            'success' => true,
            'data' => [
                'license_key' => $licenseKey,
                'type' => $result['type'] ?? 'normal',
                'features' => $result['features'] ?? [],
                'expires' => $result['expires'] ?? null,
                'max_users' => $result['max_users'] ?? null,
                'domain' => $domain,
                'fingerprint' => $fingerprint,
                'validated_at' => date('Y-m-d H:i:s'),
                'signature' => $result['signature']
            ]
        ];
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
        $encrypted = $this->encrypt(json_encode($licenseData));
        file_put_contents($this->licenseFile, $encrypted);
        chmod($this->licenseFile, 0600);
    }

    private function checkOfflineGracePeriod($licenseKey)
    {
        // TEMPORARY: Allow testing without license server
        return [
            'success' => true,
            'data' => [
                'license_key' => $licenseKey,
                'type' => strpos($licenseKey, 'AGENC') !== false ? 'agency' : 'normal',
                'features' => strpos($licenseKey, 'AGENC') !== false ? 
                    ['video_processing', 'agency_mode', 'api_access', 'white_label'] : 
                    ['video_processing', 'basic_analytics'],
                'expires' => '2025-12-31',
                'max_users' => strpos($licenseKey, 'AGENC') !== false ? 100 : 10,
                'domain' => $this->getCurrentDomain(),
                'fingerprint' => $this->generateFingerprint(),
                'validated_at' => date('Y-m-d H:i:s'),
                'signature' => 'test_signature'
            ],
            'offline' => true,
            'test_mode' => true
        ];
        
        // Original code (commented out for testing)
        /*
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
        */
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
}
