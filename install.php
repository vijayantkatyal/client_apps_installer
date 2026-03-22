<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if already installed
if (file_exists('storage/install.lock')) {
    // Check for updates
    include 'core/UniversalInstaller.php';
    $installer = new UniversalInstaller();
    $updateCheck = $installer->checkForUpdates();
    
    if ($updateCheck['success'] && $updateCheck['update_available']) {
        // Show update available page
        include 'views/update_available.php';
    } else {
        header('Location: ./');
    }
    exit;
}

// Define base path for includes
$basePath = __DIR__ . '/';

// Load installer classes
require_once __DIR__ . '/core/UniversalInstaller.php';
require_once __DIR__ . '/core/RemoteDownloader.php';
require_once __DIR__ . '/core/AppUpdater.php';
require_once __DIR__ . '/core/SystemCheck.php';
require_once __DIR__ . '/core/LicenseValidator.php';
require_once __DIR__ . '/core/DatabaseSetup.php';
require_once __DIR__ . '/core/InstallationProcess.php';

$installer = new UniversalInstaller();
$step = $_GET['step'] ?? 'license';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'select_app':
            $installer->selectApp($_POST['app']);
            header('Location: install.php?step=system_check');
            exit;
            
        case 'validate_license':
            $result = $installer->validateLicense($_POST['license_key'] ?? '');
            if ($result['success']) {
                header('Location: install.php?step=app_selection');
                exit;
            }
            break;
            
        case 'setup_database':
            $installer->setupDatabase($_POST);
            break;
            
        case 'download_app':
            $installer->downloadApplication();
            break;
            
        case 'install_app':
            $installer->performInstallation($_POST);
            break;
            
        case 'check_updates':
            $updateCheck = $installer->checkForUpdates();
            echo json_encode($updateCheck);
            exit;
            
        case 'perform_update':
            $updateResult = $installer->performUpdate();
            echo json_encode($updateResult);
            exit;
    }
}

// Display appropriate step
switch ($step) {
    case 'app_selection':
        $installer->showAppSelection();
        break;
    case 'system_check':
        $installer->showSystemCheck();
        break;
    case 'license':
        $installer->showLicenseValidation();
        break;
    case 'database':
        $installer->showDatabaseSetup();
        break;
    case 'download':
        $installer->showDownloadProgress();
        break;
    case 'install':
        $installer->showInstallationProgress();
        break;
    case 'complete':
        $installer->showComplete();
        break;
    default:
        $installer->showLicenseValidation();
}
?>
