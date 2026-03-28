<?php

class AppUpdater
{
    private $appId;
    private $licenseKey;
    private $serversConfig;
    private $baseUrl;
    private $tempDir;
    private $backupDir;

    public function __construct($appId, $licenseKey, $serversConfig, $basePath = null)
    {
        $this->appId = $appId;
        $this->licenseKey = $licenseKey;
        $this->serversConfig = $serversConfig;
        $this->baseUrl = $serversConfig['base_url'] ?? '';
        
        // Use provided base path or default to core directory
        $baseDir = $basePath ?? dirname(__DIR__);
        $this->tempDir = $baseDir . '/storage/temp/';
        $this->backupDir = $baseDir . '/storage/backups/';
        $this->ensureDirectories();
    }

    public function checkForUpdates($currentVersion)
    {
        try {
            $endpoint = $this->serversConfig['endpoints']['check_updates'] ?? '/api/updates/check';
            $data = [
                'license_key' => $this->licenseKey,
                'app' => $this->appId,
                'current_version' => $currentVersion,
                'domain' => $this->getCurrentDomain(),
                'fingerprint' => $this->generateFingerprint(),
                'action' => 'check_updates'
            ];

            $response = $this->makeRequest($endpoint, $data);

            if (!$response['success']) {
                return ['success' => false, 'error' => 'Failed to check for updates: ' . $response['error']];
            }

            $updateData = $response['data'];
            
            return [
                'success' => true,
                'update_available' => $updateData['update_available'] ?? false,
                'latest_version' => $updateData['latest_version'] ?? $currentVersion,
                'release_notes' => $updateData['release_notes'] ?? '',
                'download_size' => $updateData['download_size'] ?? 'Unknown',
                'required_php' => $updateData['required_php'] ?? null,
                'breaking_changes' => $updateData['breaking_changes'] ?? false
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Update check failed: ' . $e->getMessage()];
        }
    }

    public function update($currentVersion)
    {
        try {
            // Step 1: Check for updates first
            $checkResult = $this->checkForUpdates($currentVersion);
            if (!$checkResult['success']) {
                return ['success' => false, 'error' => $checkResult['error']];
            }

            if (!$checkResult['update_available']) {
                return ['success' => false, 'error' => 'No updates available'];
            }

            // Step 2: Create backup
            $backupResult = $this->createBackup($currentVersion);
            if (!$backupResult['success']) {
                return ['success' => false, 'error' => 'Failed to create backup: ' . $backupResult['error']];
            }

            // Step 3: Download update
            $downloadResult = $this->downloadUpdate($checkResult['latest_version']);
            if (!$downloadResult['success']) {
                return ['success' => false, 'error' => 'Failed to download update: ' . $downloadResult['error']];
            }

            // Step 4: Verify update
            $verifyResult = $this->verifyUpdate($downloadResult['filepath'], $downloadResult['checksum']);
            if (!$verifyResult['success']) {
                return ['success' => false, 'error' => 'Update verification failed: ' . $verifyResult['error']];
            }

            // Step 5: Install update
            $installResult = $this->installUpdate($downloadResult['filepath'], $currentVersion, $checkResult['latest_version']);
            if (!$installResult['success']) {
                // Attempt rollback
                $this->rollback($backupResult['backup_path']);
                return ['success' => false, 'error' => 'Update installation failed: ' . $installResult['error']];
            }

            // Step 6: Run post-update commands
            $postUpdateResult = $this->runPostUpdateCommands($checkResult['latest_version']);
            if (!$postUpdateResult['success']) {
                // Non-critical, log but continue
                error_log('Post-update commands failed: ' . $postUpdateResult['error']);
            }

            // Step 7: Update version info
            $this->updateVersionInfo($checkResult['latest_version']);

            // Step 8: Cleanup
            $this->cleanup($downloadResult['filepath']);

            return [
                'success' => true,
                'message' => 'Application updated successfully to version ' . $checkResult['latest_version'],
                'new_version' => $checkResult['latest_version'],
                'backup_path' => $backupResult['backup_path']
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }

    private function createBackup($currentVersion)
    {
        $backupName = $this->appId . '_v' . $currentVersion . '_' . date('Y-m-d_H-i-s');
        $backupPath = $this->backupDir . $backupName . '.zip';

        $basePath = dirname(__DIR__);

        // Create backup of current application
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'error' => 'Failed to create backup archive'];
        }

        // Add application files (excluding temp, storage, node_modules, etc.)
        $excludedDirs = ['storage/temp', 'storage/backups', 'node_modules', 'vendor', '.git'];
        $this->addDirectoryToZip($basePath, $basePath, $zip, $excludedDirs);

        $zip->close();

        return ['success' => true, 'backup_path' => $backupPath];
    }

    private function addDirectoryToZip($rootPath, $dir, $zip, $excludedDirs)
    {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }

            $filePath = $dir . '/' . $file;
            $relativePath = str_replace($rootPath, '', $filePath);

            // Check if this path should be excluded
            $shouldExclude = false;
            foreach ($excludedDirs as $excluded) {
                if (strpos($relativePath, $excluded) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                continue;
            }

            if (is_dir($filePath)) {
                $this->addDirectoryToZip($rootPath, $filePath, $zip, $excludedDirs);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    private function downloadUpdate($version)
    {
        $endpoint = $this->serversConfig['endpoints']['get_update_url'] ?? '/api/updates/download';
        $data = [
            'license_key' => $this->licenseKey,
            'app' => $this->appId,
            'version' => $version,
            'action' => 'get_update_url'
        ];

        $response = $this->makeRequest($endpoint, $data);

        if (!$response['success']) {
            return ['success' => false, 'error' => 'Failed to get update URL: ' . $response['error']];
        }

        $downloadInfo = $response['data'];
        
        // Download the update file
        $filepath = $this->tempDir . 'update_' . $version . '.zip';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadInfo['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->serversConfig['security']['verify_ssl'] ?? true);

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

        if (file_put_contents($filepath, $fileData) === false) {
            return ['success' => false, 'error' => 'Failed to save update file'];
        }

        return [
            'success' => true,
            'filepath' => $filepath,
            'checksum' => $downloadInfo['checksum'] ?? null
        ];
    }

    private function verifyUpdate($filepath, $expectedChecksum)
    {
        if (!$expectedChecksum) {
            return ['success' => true]; // Skip verification if no checksum provided
        }

        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Update file not found'];
        }

        $actualChecksum = hash_file('sha256', $filepath);

        if ($actualChecksum !== $expectedChecksum) {
            unlink($filepath);
            return ['success' => false, 'error' => 'Update file integrity check failed'];
        }

        return ['success' => true, 'checksum' => $actualChecksum];
    }

    private function installUpdate($updateFile, $currentVersion, $newVersion)
    {
        $basePath = dirname(__DIR__);

        // Extract update
        $zip = new ZipArchive();
        $result = $zip->open($updateFile);

        if ($result !== true) {
            return ['success' => false, 'error' => 'Failed to open update file: ' . $result];
        }

        // Extract to temporary directory first
        $tempExtractDir = $this->tempDir . 'update_' . $newVersion . '/';
        if (!$zip->extractTo($tempExtractDir)) {
            $zip->close();
            return ['success' => false, 'error' => 'Failed to extract update file'];
        }

        $zip->close();

        // Move files to application directory
        $result = $this->moveUpdatedFiles($tempExtractDir, $basePath);

        // Clean up temp extract directory
        $this->removeDirectory($tempExtractDir);

        return $result;
    }

    private function moveUpdatedFiles($sourceDir, $targetDir)
    {
        if (!file_exists($sourceDir)) {
            return ['success' => false, 'error' => 'Update source directory not found'];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $targetPath = $targetDir . '/' . $iterator->getSubPathName();

            if ($file->isDir()) {
                if (!file_exists($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                if (copy($file, $targetPath)) {
                    chmod($targetPath, 0644);
                } else {
                    return ['success' => false, 'error' => 'Failed to copy file: ' . $iterator->getSubPathName()];
                }
            }
        }

        return ['success' => true];
    }

    private function runPostUpdateCommands($version)
    {
        $basePath = dirname(__DIR__);
        
        // Run Laravel migrations
        $commands = [
            'php artisan migrate --force',
            'php artisan config:clear',
            'php artisan route:clear',
            'php artisan view:clear',
            'php artisan cache:clear'
        ];

        foreach ($commands as $command) {
            $output = shell_exec("cd $basePath && $command 2>&1");
            if (strpos($output, 'Error') !== false) {
                return ['success' => false, 'error' => "Command failed: $command. Output: $output"];
            }
        }

        return ['success' => true];
    }

    private function updateVersionInfo($newVersion)
    {
        $installFile = dirname(__DIR__) . '/storage/install.lock';
        
        if (file_exists($installFile)) {
            $installInfo = json_decode(file_get_contents($installFile), true);
            $installInfo['version'] = $newVersion;
            $installInfo['updated_at'] = date('Y-m-d H:i:s');
            file_put_contents($installFile, json_encode($installInfo));
        }
    }

    private function rollback($backupPath)
    {
        if (!file_exists($backupPath)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }

        $basePath = dirname(__DIR__);
        
        // Remove current application files
        $this->removeDirectory($basePath, ['storage', '.env', 'composer.json', 'composer.lock']);
        
        // Restore from backup
        $zip = new ZipArchive();
        $result = $zip->open($backupPath);
        
        if ($result === true) {
            $zip->extractTo($basePath);
            $zip->close();
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Failed to restore backup'];
    }

    private function removeDirectory($dir, $exclude = [])
    {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (in_array($file, $exclude)) {
                continue;
            }
            
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
    }

    private function ensureDirectories()
    {
        $dirs = [$this->tempDir, $this->backupDir];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    // Try with shell command if PHP mkdir fails
                    shell_exec("mkdir -p $dir");
                    shell_exec("chmod 777 $dir");
                }
            }
        }
    }

    private function cleanup($updateFile)
    {
        if (file_exists($updateFile)) {
            unlink($updateFile);
        }
        
        // Clean old temp files (older than 24 hours)
        $files = glob($this->tempDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > 86400) {
                unlink($file);
            }
        }
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
}
