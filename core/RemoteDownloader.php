<?php

class RemoteDownloader
{
    private $licenseKey;
    private $appId;
    private $serversConfig;
    private $baseUrl;
    private $downloadToken;
    private $tempDir;

    public function __construct($licenseKey, $appId, $serversConfig)
    {
        $this->licenseKey = $licenseKey;
        $this->appId = $appId;
        $this->serversConfig = $serversConfig;
        $this->baseUrl = $serversConfig['release_servers'][$appId] ?? '';
        $this->tempDir = dirname(__DIR__) . '/storage/temp/';
        $this->ensureTempDir();
    }

    public function downloadApplication()
    {
        try {
            // Step 1: Get download token
            $token = $this->getDownloadToken();
            if (!$token['success']) {
                return ['success' => false, 'error' => $token['error']];
            }

            $this->downloadToken = $token['token'];

            // Step 2: Get download URL
            $downloadInfo = $this->getDownloadUrl();
            if (!$downloadInfo['success']) {
                return ['success' => false, 'error' => $downloadInfo['error']];
            }

            // Step 3: Download the file
            $downloadResult = $this->downloadFile($downloadInfo['url'], $downloadInfo['filename']);
            if (!$downloadResult['success']) {
                return ['success' => false, 'error' => $downloadResult['error']];
            }

            // Step 4: Verify integrity
            $verifyResult = $this->verifyFileIntegrity($downloadResult['filepath'], $downloadInfo['checksum']);
            if (!$verifyResult['success']) {
                return ['success' => false, 'error' => $verifyResult['error']];
            }

            // Step 5: Extract files
            $extractResult = $this->extractFiles($downloadResult['filepath']);
            if (!$extractResult['success']) {
                return ['success' => false, 'error' => $extractResult['error']];
            }

            // Step 6: Clean up
            $this->cleanup($downloadResult['filepath']);

            return [
                'success' => true,
                'message' => 'Application downloaded and installed successfully',
                'version' => $downloadInfo['version']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Download failed: ' . $e->getMessage()
            ];
        }
    }

    private function getDownloadToken()
    {
        $endpoint = $this->serversConfig['endpoints']['get_download_token'] ?? '/api/download/token';
        $data = [
            'license_key' => $this->licenseKey,
            'app' => $this->appId,
            'domain' => $this->getCurrentDomain(),
            'fingerprint' => $this->generateFingerprint(),
            'action' => 'get_download_token'
        ];

        $response = $this->makeRequest($endpoint, $data);

        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to get download token: ' . $response['error']];
        }

        if (!isset($response['data']['token'])) {
            return ['success' => false, 'error' => 'Invalid download token response'];
        }

        return ['success' => true, 'token' => $response['data']['token']];
    }

    private function getDownloadUrl()
    {
        $endpoint = $this->serversConfig['endpoints']['get_download_url'] ?? '/api/download/url';
        $data = [
            'token' => $this->downloadToken,
            'license_key' => $this->licenseKey,
            'app' => $this->appId,
            'php_version' => PHP_VERSION,
            'action' => 'get_download_url'
        ];

        $response = $this->makeRequest($endpoint, $data);

        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to get download URL: ' . $response['error']];
        }

        $required = ['url', 'filename', 'checksum', 'version'];
        foreach ($required as $field) {
            if (!isset($response['data'][$field])) {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        return [
            'success' => true,
            'url' => $response['data']['url'],
            'filename' => $response['data']['filename'],
            'checksum' => $response['data']['checksum'],
            'version' => $response['data']['version']
        ];
    }

    private function downloadFile($url, $filename)
    {
        $filepath = $this->tempDir . $filename;

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->serversConfig['security']['verify_ssl'] ?? true);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$this, 'downloadProgress']);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);

        $fileData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => 'Download error: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP error: ' . $httpCode];
        }

        if (empty($fileData)) {
            return ['success' => false, 'error' => 'Downloaded file is empty'];
        }

        // Save file
        if (file_put_contents($filepath, $fileData) === false) {
            return ['success' => false, 'error' => 'Failed to save downloaded file'];
        }

        return ['success' => true, 'filepath' => $filepath];
    }

    private function verifyFileIntegrity($filepath, $expectedChecksum)
    {
        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Downloaded file not found'];
        }

        $actualChecksum = hash_file('sha256', $filepath);

        if ($actualChecksum !== $expectedChecksum) {
            unlink($filepath);
            return ['success' => false, 'error' => 'File integrity check failed. Possible corruption or tampering.'];
        }

        return ['success' => true, 'checksum' => $actualChecksum];
    }

    private function extractFiles($zipFile)
    {
        $extractPath = dirname(__DIR__) . '/';

        // Ensure ZipArchive is available
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'ZipArchive extension not available. Please install php-zip.'];
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== true) {
            return ['success' => false, 'error' => 'Failed to open zip file: ' . $result];
        }

        // Extract files
        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            return ['success' => false, 'error' => 'Failed to extract zip file'];
        }

        $zip->close();

        return ['success' => true, 'extracted_to' => $extractPath];
    }

    private function makeRequest($endpoint, $data)
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: UniversalInstaller/1.0',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->serversConfig['fallback']['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->serversConfig['security']['verify_ssl'] ?? true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }

        return ['success' => true, 'data' => $data];
    }

    private function ensureTempDir()
    {
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    private function cleanup($zipFile)
    {
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }

        // Clean old temp files (older than 1 hour)
        $files = glob($this->tempDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }

    private function getCurrentDomain()
    {
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }
        return $domain;
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

    public function downloadProgress($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {
        if ($download_size > 0) {
            $progress = round(($downloaded / $download_size) * 100, 2);
            echo "<script>updateDownloadProgress($progress);</script>";
            flush();
        }
    }

    public function getAvailableVersions()
    {
        $endpoint = $this->serversConfig['endpoints']['list_versions'] ?? '/api/versions';
        $data = [
            'license_key' => $this->licenseKey,
            'app' => $this->appId,
            'action' => 'list_versions'
        ];

        $response = $this->makeRequest($endpoint, $data);

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        return ['success' => true, 'versions' => $response['data']['versions'] ?? []];
    }
}
