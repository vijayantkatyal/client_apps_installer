<?php

class Installer
{
    private $basePath;
    private $steps = [
        1 => 'System Requirements',
        2 => 'License Validation',
        3 => 'Database Setup',
        4 => 'Installation'
    ];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__) . '/';
        
        if (!file_exists($this->basePath . 'storage')) {
            mkdir($this->basePath . 'storage', 0755, true);
        }
        if (!file_exists($this->basePath . 'storage/license')) {
            mkdir($this->basePath . 'storage/license', 0755, true);
        }
    }

    public function showSystemCheck()
    {
        $systemCheck = new SystemCheck();
        $checkResult = $systemCheck->checkAll();
        $requirements = $checkResult['requirements'];
        $all_passed = $checkResult['all_passed'];
        $can_continue = $checkResult['can_continue'];
        
        include $this->basePath . 'install/views/step1-system.php';
    }

    public function showLicenseValidation()
    {
        $license = new LicenseValidator();
        $storedLicense = $license->getStoredLicense();
        
        include $this->basePath . 'install/views/step2-license.php';
    }

    public function validateLicense($licenseKey)
    {
        $license = new LicenseValidator();
        $result = $license->validate($licenseKey);
        
        if ($result['success']) {
            $_SESSION['license_validated'] = true;
            $_SESSION['license_data'] = $result['data'];
            header('Location: install.php?step=3');
            exit;
        } else {
            $_SESSION['license_error'] = $result['error'];
            header('Location: install.php?step=2');
            exit;
        }
    }

    public function showDatabaseSetup()
    {
        if (!isset($_SESSION['license_validated'])) {
            header('Location: install.php?step=2');
            exit;
        }
        
        $database = new DatabaseSetup();
        include $this->basePath . 'install/views/step3-database.php';
    }

    public function setupDatabase($data)
    {
        $database = new DatabaseSetup();
        $result = $database->setup($data);
        
        if ($result['success']) {
            $_SESSION['database_configured'] = true;
            $_SESSION['db_config'] = $result['config'];
            header('Location: install.php?step=4');
            exit;
        } else {
            $_SESSION['database_error'] = $result['error'];
            header('Location: install.php?step=3');
            exit;
        }
    }

    public function showInstallationProgress()
    {
        if (!isset($_SESSION['license_validated']) || !isset($_SESSION['database_configured'])) {
            header('Location: install.php?step=2');
            exit;
        }
        
        include $this->basePath . 'install/views/step4-install.php';
    }

    public function performInstallation($data)
    {
        $installer = new InstallationProcess();
        $result = $installer->install($_SESSION['license_data'], $_SESSION['db_config'], $data);
        
        if ($result['success']) {
            // Create install lock
            file_put_contents($this->basePath . 'storage/install.lock', date('Y-m-d H:i:s'));
            header('Location: install.php?step=complete');
            exit;
        } else {
            $_SESSION['install_error'] = $result['error'];
            header('Location: install.php?step=4');
            exit;
        }
    }

    public function showComplete()
    {
        if (!file_exists($this->basePath . 'storage/install.lock')) {
            header('Location: install.php?step=1');
            exit;
        }
        
        include $this->basePath . 'install/views/complete.php';
    }
}
