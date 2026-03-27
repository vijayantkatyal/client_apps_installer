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
        // Create required directories
        $dirs = [
            'storage',
            'storage/license',
            'storage/temp',
            'storage/updates',
            'storage/backups'
        ];

        foreach ($dirs as $dir) {
            $fullPath = $this->basePath . $dir;
            if (!file_exists($fullPath)) {
                mkdir($fullPath, 0755, true);
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
        // If no license is validated, return empty array
        if (!isset($_SESSION['license_validated']) || !$_SESSION['license_validated']) {
            return [];
        }
        
        $licenseData = $_SESSION['license_data'] ?? [];
        $licenseType = $licenseData['type'] ?? 'normal';
        $licenseFeatures = $licenseData['features'] ?? [];
        $licensedApp = $licenseData['licensed_app'] ?? null;
        
        // Try to get products from license server first
        $products = $_SESSION['products_info'] ?? [];
        
        if (!empty($products)) {
            $availableApps = [];
            
            // Map license server products to installer format
            foreach ($products as $product) {
                $appId = $this->getAppIdFromProduct($product);
                
                // If license is app-specific, only show that app
                if ($licensedApp && $appId !== $licensedApp) {
                    continue;
                }
                
                // Check if product name matches the license type
                $productMatchesType = false;
                if ($licenseType === 'normal' && strpos($product['name'], 'Agency') === false) {
                    $productMatchesType = true;
                } elseif ($licenseType === 'agency' && strpos($product['name'], 'Agency') !== false) {
                    $productMatchesType = true;
                }
                
                // Only include products that match the license type
                if ($productMatchesType && isset($product['features'][$licenseType])) {
                    $availableApps[$appId] = [
                        'uid' => $product['uid'],
                        'name' => $product['name'],
                        'description' => $product['description'],
                        'version' => $product['version'],
                        'website' => $product['website'],
                        'icon' => $product['icon'],
                        'category' => $product['category'],
                        'requirements' => $product['requirements'] ?? [],
                        'available_features' => $product['features'][$licenseType],
                        'features' => $product['features']
                    ];
                }
            }
            
            return $availableApps;
        }
        
        // Fallback to local apps.json if no products from license server
        $allApps = $this->appsConfig ?? [];
        $availableApps = [];
        
        // If license is app-specific, only show that app
        if ($licensedApp && isset($allApps[$licensedApp])) {
            $app = $allApps[$licensedApp];
            // Check if app supports the license type
            if (isset($app['features'][$licenseType])) {
                $availableApps[$licensedApp] = $app;
                $availableApps[$licensedApp]['available_features'] = $app['features'][$licenseType];
            }
        } else {
            // Fallback: show all apps that support the license type (for backward compatibility)
            foreach ($allApps as $appId => $app) {
                // Check if app supports the license type
                if (isset($app['features'][$licenseType])) {
                    $availableApps[$appId] = $app;
                    $availableApps[$appId]['available_features'] = $app['features'][$licenseType];
                }
            }
        }
        
        return $availableApps;
    }
    
    private function getAppIdFromProduct($product)
    {
        // Map product names back to app IDs
        $nameToAppMap = [
            'VidPowr' => 'vidpowr',
            'VidPowr Agency' => 'vidpowr',
            'VidTags Standard' => 'vidtags',
            'VidTags Agency' => 'vidtags',
            'FeedPlay Standard' => 'feedplay',
            'FeedPlay Agency' => 'feedplay',
            'VidChapter Standard' => 'vidchapter',
            'VidChapter Agency' => 'vidchapter'
        ];
        
        return $nameToAppMap[$product['name']] ?? 'vidpowr';
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
        if (!isset($_SESSION['license_validated'])) {
            header('Location: install.php?step=license_validation');
            exit;
        }

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
        // Temporarily disable access control for debugging
        // if (!isset($_SESSION['license_validated'])) {
        //     header('Location: install.php?step=license_validation');
        //     exit;
        // }

        $licenseValidator = new LicenseValidator();
        $storedLicense = $licenseValidator->getStoredLicense();
        $licenseError = $_SESSION['license_error'] ?? null;
        
        // Clear the error after displaying it
        unset($_SESSION['license_error']);
        
        include $this->basePath . 'views/license_validation.php';
    }

    public function validateLicense($licenseKey)
    {
        // Prevent infinite validation loops
        if (isset($_SESSION['validation_attempted']) && time() - $_SESSION['validation_attempted'] < 30) {
            return ['success' => false, 'error' => 'Please wait before trying again'];
        }

        $licenseValidator = new LicenseValidator();
        $result = $licenseValidator->validate($licenseKey);
        
        // Mark validation attempt
        $_SESSION['validation_attempted'] = time();

        if ($result['success']) {
            // Clear validation flag on successful validation
            unset($_SESSION['validation_attempted']);
            
            $_SESSION['license_validated'] = true;
            $_SESSION['license_data'] = $result['data'];
            $_SESSION['license_key'] = $licenseKey;
            
            // Fetch products info from license server
            $productsResult = $licenseValidator->getProductsInfo();
            if ($productsResult['success']) {
                $_SESSION['products_info'] = $productsResult['products'];
            }
            
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
            header('Location: install.php?step=install');
            exit;
        } else {
            $_SESSION['database_error'] = $result['error'];
            header('Location: install.php?step=database');
            exit;
        }
    }

    public function showDownloadProgress()
    {
        if (!isset($_SESSION['license_validated'])) {
            header('Location: install.php?step=license_validation');
            exit;
        }
        
        if (!isset($_SESSION['database_configured'])) {
            header('Location: install.php?step=database');
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

        // Mark that download is being attempted
        $_SESSION['download_attempted'] = true;

        try {
            $downloader = new RemoteDownloader($_SESSION['license_key'], $_SESSION['selected_app'], $this->serversConfig, $this->basePath);
            $result = $downloader->downloadApplication();

            if ($result['success']) {
                $_SESSION['app_downloaded'] = true;
                $_SESSION['app_version'] = $result['version'];
                header('Location: install.php?step=install');
                exit;
            } else {
                $_SESSION['download_error'] = $result['error'];
                return ['success' => false, 'error' => $result['error']];
            }

        } catch (Exception $e) {
            $_SESSION['download_error'] = $e->getMessage();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function showInstallationProgress()
    {
        if (!isset($_SESSION['license_validated'])) {
            header('Location: install.php?step=license_validation');
            exit;
        }
        
        if (!isset($_SESSION['database_configured'])) {
            header('Location: install.php?step=database');
            exit;
        }

        $app = $this->getCurrentApp();
        include $this->basePath . 'views/step4-install.php';
    }

    public function performInstallation($data)
    {
        try {
            // First, download the application if not already downloaded
            if (!isset($_SESSION['app_downloaded'])) {
                $downloader = new RemoteDownloader($_SESSION['license_key'], $_SESSION['selected_app'], $this->serversConfig, $this->basePath);
                $result = $downloader->downloadApplication();

                if ($result['success']) {
                    $_SESSION['app_downloaded'] = true;
                    $_SESSION['app_version'] = $result['version'];
                } else {
                    $_SESSION['download_error'] = $result['error'];
                    return ['success' => false, 'error' => $result['error']];
                }
            }

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
                return ['success' => false, 'error' => $result['error']];
            }

        } catch (Exception $e) {
            $_SESSION['install_error'] = $e->getMessage();
            return ['success' => false, 'error' => $e->getMessage()];
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

        $updater = new AppUpdater($installInfo['app'], $installInfo['license_key'], $this->serversConfig, $this->basePath);
        return $updater->checkForUpdates($installInfo['version']);
    }

    public function performUpdate()
    {
        if (!file_exists($this->basePath . 'storage/install.lock')) {
            return ['success' => false, 'error' => 'Application not installed'];
        }

        $installInfo = json_decode(file_get_contents($this->basePath . 'storage/install.lock'), true);
        
        $updater = new AppUpdater($installInfo['app'], $installInfo['license_key'], $this->serversConfig, $this->basePath);
        return $updater->update($installInfo['version']);
    }
    
    public function removeStoredLicense()
    {
        // Remove license file
        $licenseValidator = new LicenseValidator();
        $licenseFile = $licenseValidator->getLicenseFile();
        
        if (file_exists($licenseFile)) {
            // Check if file is writable before attempting deletion
            if (!is_writable($licenseFile)) {
                // Try to make it writable
                chmod($licenseFile, 0644);
            }
            
            // Attempt deletion with error suppression
            if (!@unlink($licenseFile)) {
                return ['success' => false, 'error' => 'Cannot delete license file. Please check file permissions.'];
            }
        }
        
        // Clear session data
        unset($_SESSION['license_validated']);
        unset($_SESSION['license_data']);
        unset($_SESSION['license_key']);
        unset($_SESSION['products_info']);
        
        return ['success' => true, 'message' => 'License removed successfully'];
    }
}
