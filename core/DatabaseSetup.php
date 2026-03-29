<?php

class DatabaseSetup
{
    private $basePath;
    private $configFile;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__) . '/';
        $this->configFile = $this->basePath . '.env';
    }

    public function setup($data)
    {
        $config = $this->buildConfig($data);
        
        // Test database connection (without database name if creating new database)
        $testResult = $this->testConnection($config, $data['create_database'] ?? false);
        if (!$testResult['success']) {
            return [
                'success' => false,
                'error' => $testResult['error']
            ];
        }

        // Create database if needed
        if ($data['create_database'] ?? false) {
            $createResult = $this->createDatabase($config);
            if (!$createResult['success']) {
                return [
                    'success' => false,
                    'error' => $createResult['error']
                ];
            }
        }

        // Create user if needed
        if ($data['create_user'] ?? false) {
            $userResult = $this->createDatabaseUser($config, $data);
            if (!$userResult['success']) {
                return [
                    'success' => false,
                    'error' => $userResult['error']
                ];
            }
        }

        return [
            'success' => true,
            'config' => $config
        ];
    }

    private function buildConfig($data)
    {
        return [
            'DB_HOST' => $data['db_host'] ?? 'localhost',
            'DB_PORT' => $data['db_port'] ?? '3306',
            'DB_DATABASE' => $data['db_database'] ?? 'vidpowr_app',
            'DB_USERNAME' => $data['db_username'] ?? 'root',
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'DB_CONNECTION' => 'mysql'
        ];
    }

    public function testConnection($config, $skipDatabase = false)
    {
        try {
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']}";
            
            if (!$skipDatabase && !empty($config['DB_DATABASE'])) {
                $dsn .= ";dbname={$config['DB_DATABASE']}";
            }

            $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 10
            ]);

            // Test simple query
            $pdo->query("SELECT 1");

            return [
                'success' => true,
                'message' => 'Database connection successful'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    private function createDatabase($config)
    {
        try {
            // Connect without database name
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']}";
            $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['DB_DATABASE']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return [
                'success' => true,
                'message' => 'Database created successfully'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to create database: ' . $e->getMessage()
            ];
        }
    }

    private function createDatabaseUser($config, $data)
    {
        try {
            // Connect with root privileges
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']}";
            $pdo = new PDO($dsn, $data['root_username'] ?? 'root', $data['root_password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $newUsername = $data['new_username'];
            $newPassword = $data['new_password'];
            $database = $config['DB_DATABASE'];

            // Create user
            $pdo->exec("CREATE USER IF NOT EXISTS '$newUsername'@'%' IDENTIFIED BY '$newPassword'");
            
            // Grant privileges
            $pdo->exec("GRANT ALL PRIVILEGES ON `$database`.* TO '$newUsername'@'%'");
            $pdo->exec("FLUSH PRIVILEGES");

            return [
                'success' => true,
                'message' => 'Database user created successfully'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to create database user: ' . $e->getMessage()
            ];
        }
    }

    public function generateDatabaseName()
    {
        return 'vidpowr_' . substr(md5(time() . rand()), 0, 8);
    }

    public function generatePassword()
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 16);
    }

    public function getExistingDatabases($config)
    {
        try {
            $dsn = "mysql:host={$config['DB_HOST']};port={$config['DB_PORT']}";
            $pdo = new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter out system databases
            $databases = array_filter($databases, function($db) {
                return !in_array($db, ['information_schema', 'mysql', 'performance_schema', 'sys']);
            });

            return $databases;

        } catch (PDOException $e) {
            return [];
        }
    }

    public function writeEnvFile($config, $licenseData)
    {
        $envContent = $this->generateEnvContent($config, $licenseData);
        
        if (file_exists('.env')) {
            // Backup existing .env
            copy('.env', '.env.backup.' . date('Y-m-d-H-i-s'));
        }

        if (file_put_contents($this->configFile, $envContent)) {
            return [
                'success' => true,
                'message' => 'Environment file created successfully'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to write environment file'
            ];
        }
    }

    private function detectAppUrl()
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Get the request URI and remove query string
        $requestUri = $_SERVER['REQUEST_URI'];
        $requestUri = explode('?', $requestUri)[0];
        
        // Remove the installer script name from the path
        $requestUri = str_replace('/index.php', '', $requestUri);
        $requestUri = str_replace('/install.php', '', $requestUri);
        $requestUri = str_replace('/install', '', $requestUri);
        
        // Remove trailing slash if present
        $requestUri = rtrim($requestUri, '/');
        
        // Build the full URL
        $appUrl = $scheme . '://' . $host . $requestUri;
        
        // If requestUri is empty, it means we're at root
        if (empty($requestUri)) {
            $appUrl = $scheme . '://' . $host;
        }
        
        return $appUrl;
    }

    private function generateEnvContent($config, $licenseData)
    {
        $appUrl = $this->detectAppUrl();
        $appKey = 'base64:' . base64_encode(random_bytes(32));

        $content = <<<ENV
APP_NAME=VidPowr
APP_ENV=production
APP_KEY={$appKey}
APP_DEBUG=false
APP_URL={$appUrl}
ASSET_URL={$appUrl}/public

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION={$config['DB_CONNECTION']}
DB_HOST={$config['DB_HOST']}
DB_PORT={$config['DB_PORT']}
DB_DATABASE={$config['DB_DATABASE']}
DB_USERNAME={$config['DB_USERNAME']}
DB_PASSWORD={$config['DB_PASSWORD']}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

# License Configuration
LICENSE_TYPE={$licenseData['type']}
LICENSE_KEY={$licenseData['license_key']}
LICENSE_EXPIRES={$licenseData['expires']}
LICENSE_DOMAIN={$licenseData['domain']}
ENV;

        return $content;
    }
}
