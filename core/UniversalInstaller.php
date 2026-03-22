<?php

class UniversalInstaller
{
    private $basePath;
    private $appsConfig;
    private $serversConfig;
    private $currentApp;
    private $licenseKey;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__) . '/'; // This now points to the installer root
        $this->loadConfigurations();
        $this->ensureDirectories();
    }

    private function loadConfigurations()
    {
        $appsFile = $this->basePath . 'config/apps.json';
        $serversFile = $this->basePath . 'config/servers.json';

        if (file_exists($appsFile)) {
            $this->appsConfig = json_decode(file_get_contents($appsFile), true);
        }

        if (file_exists($serversFile)) {
            $this->serversConfig = json_decode(file_get_contents($serversFile), true);
        }
    }

    private function ensureDirectories()
    {
        // Directories are now created in Dockerfile, so just check they exist
        $dirs = [
            'storage',
            'storage/license',
            'storage/temp',
            'storage/updates',
            'storage/backups'
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($this->basePath . $dir)) {
                // Don't try to create, just log error
                error_log("Directory not found: " . $this->basePath . $dir);
            }
        }
    }

    public function showAppSelection()
    {
        // Check if license is validated
        if (!isset($_SESSION['license_validated']) || !$_SESSION['license_validated']) {
            header('Location: install.php?step=license');
            exit;
        }
        
        $apps = $this->getAvailableApps();
        include $this->basePath . 'views/app_selection.php';
    }

    public function selectApp($appId)
    {
        if (!isset($this->appsConfig[$appId])) {
            throw new Exception("Application '$appId' not found");
        }

        $this->currentApp = $appId;
        $_SESSION['selected_app'] = $appId;
        
        return $this->appsConfig[$appId];
    }

    public function getAvailableApps()
    {
        $allApps = $this->appsConfig ?? [];
        
        // If no license is validated, return empty array
        if (!isset($_SESSION['license_validated']) || !$_SESSION['license_validated']) {
            return [];
        }
        
        $licenseData = $_SESSION['license_data'] ?? [];
        $licenseType = $licenseData['type'] ?? 'normal';
        $licenseFeatures = $licenseData['features'] ?? [];
        
        $availableApps = [];
        
        foreach ($allApps as $appId => $app) {
            // Check if app supports the license type
            if (isset($app['features'][$licenseType])) {
                $availableApps[$appId] = $app;
                $availableApps[$appId]['available_features'] = $app['features'][$licenseType];
            }
        }
        
        return $availableApps;
    }

    public function getCurrentApp()
    {
        if ($this->currentApp) {
            return $this->appsConfig[$this->currentApp];
        }

        $appId = $_SESSION['selected_app'] ?? null;
        if ($appId && isset($this->appsConfig[$appId])) {
            $this->currentApp = $appId;
            return $this->appsConfig[$appId];
        }

        return null;
    }

    public function showSystemCheck()
    {
        $app = $this->getCurrentApp();
        if (!$app) {
            header('Location: install.php?step=app_selection');
            exit;
        }

        $systemCheck = new SystemCheck($app['requirements']);
        $checkResult = $systemCheck->checkAll();
        $requirements = $checkResult['requirements'];
        $all_passed = $checkResult['all_passed'];
        $can_continue = $checkResult['can_continue'];

        include $this->basePath . 'views/step1-system.php';
    }

    public function showLicenseValidation()
    {
        $licenseValidator = new LicenseValidator();
        $storedLicense = $licenseValidator->getStoredLicense();
        $licenseError = $_SESSION['license_error'] ?? null;
        
        // Clear the error after displaying it
        unset($_SESSION['license_error']);

        include $this->basePath . 'views/license_validation.php';
    }

    public function validateLicense($licenseKey)
    {
        $licenseValidator = new LicenseValidator();
        $result = $licenseValidator->validate($licenseKey);

        if ($result['success']) {
            $_SESSION['license_validated'] = true;
            $_SESSION['license_data'] = $result['data'];
            $_SESSION['license_key'] = $licenseKey;
            return $result;
        } else {
            $_SESSION['license_error'] = $result['error'];
            return $result;
        }
    }

    public function showDatabaseSetup()
    {
        if (!isset($_SESSION['license_validated'])) {
            header('Location: install.php?step=license');
            exit;
        }

        $database = new DatabaseSetup();
        include $this->basePath . 'views/step3-database.php';
    }

    public function setupDatabase($data)
    {
        if (!isset($_SESSION['license_validated'])) {
            return ['success' => false, 'error' => 'License not validated'];
        }

        $database = new DatabaseSetup();
        $result = $database->setup($data);

        if ($result['success']) {
            $_SESSION['database_configured'] = true;
            $_SESSION['db_config'] = $result['config'];
            header('Location: install.php?step=download');
            exit;
        } else {
            $_SESSION['database_error'] = $result['error'];
            header('Location: install.php?step=database');
            exit;
        }
    }

    public function showDownloadProgress()
    {
        if (!isset($_SESSION['license_validated']) || !isset($_SESSION['database_configured'])) {
            header('Location: install.php?step=license');
            exit;
        }

        $app = $this->getCurrentApp();
        include $this->basePath . 'views/download_progress.php';
    }

    public function downloadApplication()
    {
        if (!isset($_SESSION['license_key']) || !isset($_SESSION['selected_app'])) {
            return ['success' => false, 'error' => 'Missing license or app selection'];
        }

        try {
            $downloader = new RemoteDownloader($_SESSION['license_key'], $this->currentApp, $this->serversConfig);
            $result = $downloader->downloadApplication();

            if ($result['success']) {
                $_SESSION['app_downloaded'] = true;
                $_SESSION['app_version'] = $result['version'];
                header('Location: install.php?step=install');
                exit;
            } else {
                $_SESSION['download_error'] = $result['error'];
                header('Location: install.php?step=download');
                exit;
            }

        } catch (Exception $e) {
            $_SESSION['download_error'] = $e->getMessage();
            header('Location: install.php?step=download');
            exit;
        }
    }

    public function showInstallationProgress()
    {
        if (!isset($_SESSION['app_downloaded'])) {
            header('Location: install.php?step=download');
            exit;
        }

        $app = $this->getCurrentApp();
        include $this->basePath . 'views/step4-install.php';
    }

    public function performInstallation($data)
    {
        if (!isset($_SESSION['app_downloaded'])) {
            return ['success' => false, 'error' => 'Application not downloaded'];
        }

        try {
            $app = $this->getCurrentApp();
            $installer = new InstallationProcess($app);
            $result = $installer->install($_SESSION['license_data'], $_SESSION['db_config'], $data);

            if ($result['success']) {
                // Create install lock with app info
                $installInfo = [
                    'app' => $this->currentApp,
                    'version' => $_SESSION['app_version'],
                    'license_key' => $_SESSION['license_key'],
                    'installed_at' => date('Y-m-d H:i:s'),
                    'license_data' => $_SESSION['license_data']
                ];
                
                file_put_contents($this->basePath . 'storage/install.lock', json_encode($installInfo));
                
                header('Location: install.php?step=complete');
                exit;
            } else {
                $_SESSION['install_error'] = $result['error'];
                header('Location: install.php?step=install');
                exit;
            }

        } catch (Exception $e) {
            $_SESSION['install_error'] = $e->getMessage();
            header('Location: install.php?step=install');
            exit;
        }
    }

    public function showComplete()
    {
        if (!file_exists($this->basePath . 'storage/install.lock')) {
            header('Location: install.php?step=1');
            exit;
        }

        $installInfo = json_decode(file_get_contents($this->basePath . 'storage/install.lock'), true);
        $app = $this->appsConfig[$installInfo['app']] ?? null;
        
        include $this->basePath . 'views/complete.php';
    }

    public function checkForUpdates()
    {
        if (!file_exists($this->basePath . 'storage/install.lock')) {
            return ['success' => false, 'error' => 'Application not installed'];
        }

        $installInfo = json_decode(file_get_contents($this->basePath . 'storage/install.lock'), true);
        $app = $this->appsConfig[$installInfo['app']] ?? null;

        if (!$app) {
            return ['success' => false, 'error' => 'Unknown application'];
        }

        $updater = new AppUpdater($installInfo['app'], $installInfo['license_key'], $this->serversConfig);
        return $updater->checkForUpdates($installInfo['version']);
    }

    public function performUpdate()
    {
        if (!file_exists($this->basePath . 'storage/install.lock')) {
            return ['success' => false, 'error' => 'Application not installed'];
        }

        $installInfo = json_decode(file_get_contents($this->basePath . 'storage/install.lock'), true);
        
        $updater = new AppUpdater($installInfo['app'], $installInfo['license_key'], $this->serversConfig);
        return $updater->update($installInfo['version']);
    }
}
