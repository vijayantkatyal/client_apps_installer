<?php
// Mock License and Release Server for Testing

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data
$postData = json_decode(file_get_contents('php://input'), true) ?: [];

// Mock responses based on endpoint
switch ($path) {
    case '/api/validate':
        handleLicenseValidation($postData);
        break;
        
    case '/api/download/token':
        handleDownloadToken($postData);
        break;
        
    case '/api/download/url':
        handleDownloadUrl($postData);
        break;
        
    case '/api/versions':
        handleListVersions($postData);
        break;
        
    case '/api/updates/check':
        handleUpdateCheck($postData);
        break;
        
    case '/api/updates/download':
        handleUpdateDownload($postData);
        break;
        
    case '/download/test-app.zip':
        handleFileDownload();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

function handleLicenseValidation($data) {
    $licenseKey = $data['license_key'] ?? '';
    $app = $data['app'] ?? '';
    $domain = $data['domain'] ?? '';
    
    // Mock license validation
    if (preg_match('/^VIDPOWR-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $licenseKey)) {
        $isAgency = strpos($licenseKey, 'AGENC') !== false;
        
        echo json_encode([
            'valid' => true,
            'app' => $app,
            'type' => $isAgency ? 'agency' : 'normal',
            'features' => $isAgency ? 
                ['video_processing', 'agency_mode', 'api_access', 'white_label'] : 
                ['video_processing', 'basic_analytics'],
            'expires' => '2025-12-31',
            'max_users' => $isAgency ? 100 : 10,
            'domain' => $domain,
            'download_allowed' => true
        ]);
    } else {
        echo json_encode([
            'valid' => false,
            'error' => 'Invalid license key format'
        ]);
    }
}

function handleDownloadToken($data) {
    $licenseKey = $data['license_key'] ?? '';
    $app = $data['app'] ?? '';
    
    if (preg_match('/^VIDPOWR-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/', $licenseKey)) {
        echo json_encode([
            'token' => 'mock_token_' . bin2hex(random_bytes(16)),
            'expires_in' => 3600
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid license']);
    }
}

function handleDownloadUrl($data) {
    $token = $data['token'] ?? '';
    $app = $data['app'] ?? '';
    
    if (strpos($token, 'mock_token_') === 0) {
        echo json_encode([
            'url' => 'http://localhost:9001/download/test-app.zip',
            'filename' => 'test-app.zip',
            'checksum' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'version' => '1.0.0'
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
    }
}

function handleListVersions($data) {
    echo json_encode([
        'versions' => [
            [
                'version' => '1.0.0',
                'release_date' => '2024-01-01',
                'stable' => true
            ],
            [
                'version' => '1.1.0-beta',
                'release_date' => '2024-01-15',
                'stable' => false
            ]
        ]
    ]);
}

function handleUpdateCheck($data) {
    $currentVersion = $data['current_version'] ?? '1.0.0';
    
    if ($currentVersion === '1.0.0') {
        echo json_encode([
            'update_available' => true,
            'latest_version' => '1.1.0',
            'release_notes' => "New features:\n- Improved performance\n- Bug fixes\n- New dashboard",
            'download_size' => '25MB',
            'required_php' => '8.0',
            'breaking_changes' => false
        ]);
    } else {
        echo json_encode([
            'update_available' => false,
            'latest_version' => $currentVersion
        ]);
    }
}

function handleUpdateDownload($data) {
    $token = 'mock_update_token_' . bin2hex(random_bytes(16));
    
    echo json_encode([
        'url' => 'http://localhost:9001/download/test-app-update.zip',
        'filename' => 'test-app-update.zip',
        'checksum' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
    ]);
}

function handleFileDownload() {
    // Create a mock ZIP file
    $zip = new ZipArchive();
    $zipFile = '/tmp/test-app.zip';
    
    if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
        // Add some mock files
        $zip->addFromString('README.md', "# Test Application\n\nThis is a test application for the universal installer.\n");
        $zip->addFromString('composer.json', json_encode([
            'name' => 'test/app',
            'description' => 'Test Application',
            'require' => [
                'php' => '^8.0'
            ]
        ], JSON_PRETTY_PRINT));
        $zip->addFromString('app/Http/Controllers/TestController.php', "<?php\n\nclass TestController {\n    public function index() {\n        return 'Hello World';\n    }\n}\n");
        $zip->close();
        
        // Send the file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="test-app.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        
        // Clean up
        unlink($zipFile);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create test file']);
    }
}
?>
