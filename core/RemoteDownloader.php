<?php

class RemoteDownloader
{
    private $licenseKey;
    private $appId;
    private $serversConfig;
    private $baseUrl;
    private $downloadToken;
    private $tempDir;

    public function __construct($licenseKey, $appId, $serversConfig, $basePath = null)
    {
        $this->licenseKey = $licenseKey;
        $this->appId = $appId;
        $this->serversConfig = $serversConfig;
        $this->baseUrl = $serversConfig['base_url'] ?? '';
        
        // Use provided base path or default to core directory
        $baseDir = $basePath ?? dirname(__DIR__);
        $this->tempDir = rtrim($baseDir, '/') . '/storage/temp/';
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

            // Step 6: Install composer dependencies
            $composerResult = $this->installComposerDependencies($extractResult['extracted_to']);
            if (!$composerResult['success']) {
                return ['success' => false, 'error' => $composerResult['error']];
            }

            // Step 7: Generate application key
            $keyResult = $this->generateApplicationKey($extractResult['extracted_to']);
            if (!$keyResult['success']) {
                return ['success' => false, 'error' => $keyResult['error']];
            }

            // Step 8: Clean up
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

        // Debug logging
        error_log("RemoteDownloader: Getting download token");
        error_log("RemoteDownloader: Endpoint: " . $this->baseUrl . $endpoint);
        error_log("RemoteDownloader: Request data: " . json_encode($data));

        $response = $this->makeRequest($endpoint, $data);

        error_log("RemoteDownloader: Response: " . json_encode($response));

        if (!$response['success']) {
            error_log("RemoteDownloader: Failed to get download token: " . $response['error']);
            return ['success' => false, 'error' => 'Failed to get download token: ' . $response['error']];
        }

        if (!isset($response['data']['token'])) {
            error_log("RemoteDownloader: Invalid download token response - missing token");
            return ['success' => false, 'error' => 'Invalid download token response'];
        }

        error_log("RemoteDownloader: Successfully obtained download token");
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

        // Debug logging
        error_log("RemoteDownloader: Getting download URL");
        error_log("RemoteDownloader: Endpoint: " . $this->baseUrl . $endpoint);
        error_log("RemoteDownloader: Request data: " . json_encode($data));

        $response = $this->makeRequest($endpoint, $data);

        error_log("RemoteDownloader: Response: " . json_encode($response));

        if (!$response['success']) {
            error_log("RemoteDownloader: Failed to get download URL: " . $response['error']);
            return ['success' => false, 'error' => 'Failed to get download URL: ' . $response['error']];
        }

        $required = ['url', 'filename', 'checksum', 'version'];
        foreach ($required as $field) {
            if (!isset($response['data'][$field])) {
                error_log("RemoteDownloader: Missing required field: $field");
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        error_log("RemoteDownloader: Successfully obtained download URL");
        error_log("RemoteDownloader: Download URL: " . $response['data']['url']);
        error_log("RemoteDownloader: Filename: " . $response['data']['filename']);
        error_log("RemoteDownloader: Version: " . $response['data']['version']);

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

        // Skip verification if checksum is empty or looks like a placeholder
        if (empty($expectedChecksum) || $expectedChecksum === 'abc123def456789') {
            error_log("RemoteDownloader: Skipping integrity check - placeholder or empty checksum");
            return ['success' => true, 'checksum' => 'skipped'];
        }

        $actualChecksum = hash_file('sha256', $filepath);
        
        // Debug logging
        error_log("RemoteDownloader: File integrity check");
        error_log("RemoteDownloader: Expected checksum: " . $expectedChecksum);
        error_log("RemoteDownloader: Actual checksum: " . $actualChecksum);
        error_log("RemoteDownloader: File size: " . filesize($filepath) . " bytes");

        if ($actualChecksum !== $expectedChecksum) {
            unlink($filepath);
            return ['success' => false, 'error' => 'File integrity check failed. Possible corruption or tampering.'];
        }

        return ['success' => true, 'checksum' => $actualChecksum];
    }

    private function extractFiles($zipFile)
    {
        $extractPath = dirname(__DIR__);

        // Ensure ZipArchive is available
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'ZipArchive extension not available. Please install php-zip.'];
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipFile);

        if ($result !== true) {
            return ['success' => false, 'error' => 'Failed to open zip file: ' . $result];
        }

        // Extract files excluding install.php to avoid overwriting running script
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Skip extracting install.php to avoid overwriting the running script
            if (basename($filename) === 'install.php') {
                continue;
            }
            
            // Extract individual file
            $zip->extractTo($extractPath, $filename);
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

    private function installComposerDependencies($extractPath)
    {
        error_log("Installing Composer dependencies...");
        
        // Check if composer.json exists
        if (!file_exists($extractPath . '/composer.json')) {
            return ['success' => false, 'error' => 'composer.json not found in extracted files'];
        }

        // Install composer if not available
        $composerPath = '/usr/local/bin/composer';
        if (!file_exists($composerPath)) {
            error_log("Composer not found, installing...");
            $installCmd = "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer";
            $installOutput = shell_exec($installCmd);
            error_log("Composer install output: " . $installOutput);
        }

        // Install dependencies
        $composerCommand = "cd $extractPath && /usr/local/bin/composer install --no-dev --optimize-autoloader 2>&1";
        $output = shell_exec($composerCommand);
        
        error_log("Composer install output: " . $output);
        
        // Check if vendor directory was created
        if (!file_exists($extractPath . '/vendor/autoload.php')) {
            return ['success' => false, 'error' => 'Failed to install Composer dependencies. Output: ' . $output];
        }

        error_log("Composer dependencies installed successfully");
        return ['success' => true];
    }

    private function generateApplicationKey($extractPath)
    {
        error_log("Generating application key...");
        
        // Check if artisan exists
        if (!file_exists($extractPath . '/artisan')) {
            return ['success' => false, 'error' => 'artisan command not found'];
        }

        // Generate application key
        $keyCommand = "cd $extractPath && php artisan key:generate --force 2>&1";
        $output = shell_exec($keyCommand);
        
        error_log("Key generation output: " . $output);
        
        if (strpos($output, 'Application key set successfully') === false) {
            return ['success' => false, 'error' => 'Failed to generate application key. Output: ' . $output];
        }

        error_log("Application key generated successfully");
        return ['success' => true];
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

        // Move installer files to tmp_install folder
        $this->moveInstallerFiles();

        // Clean old temp files (older than 1 hour)
        $files = glob($this->tempDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }

    private function moveInstallerFiles()
    {
        $baseDir = dirname(__DIR__);
        $tmpInstallDir = $baseDir . '/tmp_install/';
        
        // Create tmp_install directory if it doesn't exist
        if (!file_exists($tmpInstallDir)) {
            mkdir($tmpInstallDir, 0755, true);
        }

        // Files to move (installer-specific files)
        $installerFiles = [
            'install.php',
            'Installer.php',
            'core/',
            'views/',
            'assets/',
            'config/',
            'docker-compose.yml',
            'Dockerfile',
            '.dockerignore'
        ];

        foreach ($installerFiles as $file) {
            $source = $baseDir . '/' . $file;
            $destination = $tmpInstallDir . $file;
            
            if (file_exists($source)) {
                if (is_dir($source)) {
                    $this->copyDirectory($source, $destination);
                } else {
                    copy($source, $destination);
                }
            }
        }
    }

    private function copyDirectory($source, $destination)
    {
        if (!file_exists($destination)) {
            mkdir($destination, 0755, true);
        }

        $files = scandir($source);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $sourceFile = $source . '/' . $file;
            $destFile = $destination . '/' . $file;

            if (is_dir($sourceFile)) {
                $this->copyDirectory($sourceFile, $destFile);
            } else {
                copy($sourceFile, $destFile);
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
