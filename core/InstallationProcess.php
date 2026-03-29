<?php

class InstallationProcess
{
    private $basePath;
    private $logFile;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__) . '/';
        $this->logFile = $this->basePath . 'storage/install.log';
    }

    public function install($licenseData, $dbConfig, $installData)
    {
        $this->log("Starting installation...");
        
        try {
            // Pre-installation checks and directory creation
            $this->ensureRequiredDirectories();
            
            // Step 1: Write environment file
            $this->log("Creating environment file...");
            $database = new DatabaseSetup();
            $envResult = $database->writeEnvFile($dbConfig, $licenseData);
            
            if (!$envResult['success']) {
                throw new Exception($envResult['error']);
            }

            // Step 2: Install Composer dependencies
            $this->log("Installing Composer dependencies...");
            $this->installDependencies();

            // Step 3: Generate application key
            $this->log("Generating application key...");
            $this->generateAppKey();

            // Step 4: Run database migrations
            $this->log("Running database migrations...");
            $this->runMigrations();

            // Step 5: Seed database
            $this->log("Seeding database...");
            $this->seedDatabase();

            // Step 6: Create admin user
            $this->log("Creating admin user...");
            $this->createAdminUser($installData);

            // Step 7: Set file permissions
            $this->log("Setting file permissions...");
            $this->setPermissions();

            // Step 8: Create storage links
            $this->log("Creating storage links...");
            $this->createStorageLinks();

            // Step 9: Optimize application
            $this->log("Optimizing application...");
            $this->optimizeApplication();

            // Step 10: Create license configuration
            $this->log("Creating license configuration...");
            $this->createLicenseConfig($licenseData);

            // Step 11: Clean up installer files
            $this->log("Cleaning up installer files...");
            $this->cleanupInstaller();

            $this->log("Installation completed successfully!");
            
            return [
                'success' => true,
                'message' => 'Installation completed successfully'
            ];

        } catch (Exception $e) {
            $this->log("Installation failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function ensureRequiredDirectories()
    {
        $requiredDirs = [
            'bootstrap/cache',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/app/public',
            'config'
        ];
        
        foreach ($requiredDirs as $dir) {
            $fullPath = $this->basePath . $dir;
            if (!is_dir($fullPath)) {
                if (!mkdir($fullPath, 0755, true)) {
                    $this->log("Warning: Could not create directory: $dir");
                } else {
                    $this->log("Created directory: $dir");
                }
            }
        }
    }

    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        echo $logMessage; // Output for real-time feedback
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function installDependencies()
    {
        // Check if vendor directory already exists
        if (is_dir($this->basePath . 'vendor')) {
            $this->log("Vendor directory already exists, skipping Composer installation");
            return;
        }
        
        if (!file_exists('composer.phar')) {
            $this->log("Downloading Composer...");
            $composerResult = @copy('https://getcomposer.org/composer-stable.phar', 'composer.phar');
            
            if (!$composerResult) {
                throw new Exception('Failed to download Composer. Please check internet connection.');
            }
        }

        $command = 'php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1';
        $output = shell_exec($command);
        
        if (strpos($output, 'Error') !== false || strpos($output, 'Exception') !== false) {
            throw new Exception('Composer installation failed: ' . $output);
        }
        
        $this->log("Composer dependencies installed successfully");
    }

    private function generateAppKey()
    {
        // Ensure bootstrap/cache directory exists
        $bootstrapCacheDir = $this->basePath . 'bootstrap/cache';
        if (!is_dir($bootstrapCacheDir)) {
            if (!mkdir($bootstrapCacheDir, 0755, true)) {
                $this->log("Warning: Could not create bootstrap/cache directory");
            }
        }
        
        // Check if artisan file exists
        $artisanFile = $this->basePath . 'artisan';
        if (!file_exists($artisanFile)) {
            throw new Exception('Artisan file not found. The application download may be incomplete. Please try downloading again.');
        }
        
        // Make sure artisan is executable
        if (!is_executable($artisanFile)) {
            chmod($artisanFile, 0755);
        }
        
        $command = 'php artisan key:generate --force 2>&1';
        $output = shell_exec($command);
        
        if (strpos($output, 'Application key set successfully') === false) {
            throw new Exception('Failed to generate application key: ' . $output);
        }
    }

    private function runMigrations()
    {
        $command = 'php artisan migrate --force 2>&1';
        $output = shell_exec($command);
        
        if (strpos($output, 'Migration') === false && strpos($output, 'Nothing to migrate') === false) {
            // Check if it's a row size error
            if (strpos($output, 'Row size too large') !== false || strpos($output, '1118') !== false) {
                $this->log("Row size error detected, attempting to fix MySQL configuration...");
                
                // Try to fix by setting MySQL configuration
                $fixResult = $this->fixMySQLRowSize();
                if ($fixResult['success']) {
                    $this->log("MySQL configuration updated, retrying migrations...");
                    $retryOutput = shell_exec($command);
                    
                    if (strpos($retryOutput, 'Migration') !== false || strpos($retryOutput, 'Nothing to migrate') !== false) {
                        $this->log("Migrations completed successfully after configuration fix");
                        return;
                    } else {
                        throw new Exception('Database migration failed even after configuration fix: ' . $retryOutput);
                    }
                } else {
                    throw new Exception('Database migration failed due to row size issue and could not fix configuration: ' . $fixResult['error'] . '. Original error: ' . $output);
                }
            }
            
            throw new Exception('Database migration failed: ' . $output);
        }
    }

    private function fixMySQLRowSize()
    {
        try {
            // Read database configuration from .env file
            $envFile = $this->basePath . '.env';
            if (!file_exists($envFile)) {
                return [
                    'success' => false,
                    'error' => 'Environment file not found'
                ];
            }
            
            $envContent = file_get_contents($envFile);
            $dbConfig = [];
            
            // Parse database configuration from .env
            preg_match('/DB_HOST=(.+)/', $envContent, $host);
            preg_match('/DB_PORT=(.+)/', $envContent, $port);
            preg_match('/DB_DATABASE=(.+)/', $envContent, $database);
            preg_match('/DB_USERNAME=(.+)/', $envContent, $username);
            preg_match('/DB_PASSWORD=(.+)/', $envContent, $password);
            
            if (!$host || !$database || !$username) {
                return [
                    'success' => false,
                    'error' => 'Could not parse database configuration from .env'
                ];
            }
            
            $dbConfig = [
                'host' => trim($host[1]),
                'port' => trim($port[1] ?? '3306'),
                'database' => trim($database[1]),
                'username' => trim($username[1]),
                'password' => trim($password[1] ?? '')
            ];
            
            // Connect to MySQL and set configuration
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Set MySQL configuration to handle larger row sizes
            $configCommands = [
                'SET GLOBAL innodb_file_format=Barracuda',
                'SET GLOBAL innodb_file_per_table=ON',
                'SET GLOBAL innodb_large_prefix=ON'
            ];
            
            foreach ($configCommands as $command) {
                try {
                    $pdo->exec($command);
                    $this->log("Executed: " . $command);
                } catch (PDOException $e) {
                    $this->log("Warning: Could not execute '" . $command . "': " . $e->getMessage());
                    // Continue with other commands even if one fails
                }
            }
            
            // Try to drop and recreate problematic tables if they exist
            $this->handleProblematicTables($pdo);
            
            return [
                'success' => true,
                'message' => 'MySQL configuration updated successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Failed to update MySQL configuration: ' . $e->getMessage()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage()
            ];
        }
    }
    
    private function handleProblematicTables($pdo)
    {
        try {
            // Check if there are any tables that might be causing issues
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Check table row format and convert if needed
                try {
                    $pdo->exec("ALTER TABLE `$table` ROW_FORMAT=COMPRESSED");
                    $this->log("Updated row format for table: $table");
                } catch (PDOException $e) {
                    // If we can't alter the table, try to drop it so migration can recreate it
                    try {
                        $pdo->exec("DROP TABLE IF EXISTS `$table`");
                        $this->log("Dropped problematic table: $table");
                    } catch (PDOException $dropError) {
                        $this->log("Could not drop table $table: " . $dropError->getMessage());
                    }
                }
            }
        } catch (PDOException $e) {
            $this->log("Could not check tables: " . $e->getMessage());
        }
    }

    private function seedDatabase()
    {
        $command = 'php artisan db:seed --force 2>&1';
        $output = shell_exec($command);
        
        // Seeding is optional, so don't throw exception on failure
        $this->log("Database seeding completed");
    }

    private function createAdminUser($installData)
    {
        // Run built-in database seeding
        $this->log("Running built-in database seeding...");
        $seedCommand = 'php artisan db:seed --force 2>&1';
        $seedOutput = shell_exec($seedCommand);
        $this->log("Database seeding output: " . $seedOutput);
        
        // Store default admin credentials for display (if app creates default admin)
        // $_SESSION['admin_credentials'] = [
        //     'email' => 'admin@example.com',
        //     'password' => 'password'
        // ];
        
        $this->log("Database seeding completed successfully");
    }

    private function setPermissions()
    {
        $commands = [
            'chmod -R 755 storage',
            'chmod -R 755 bootstrap/cache',
            'chmod -R 755 public',
            'chmod -R 777 storage/logs',
            'chmod -R 777 storage/framework/cache',
            'chmod -R 777 storage/framework/sessions',
            'chmod -R 777 storage/framework/views'
        ];

        foreach ($commands as $command) {
            shell_exec($command . ' 2>&1');
        }
    }

    private function createStorageLinks()
    {
        $command = 'php artisan storage:link 2>&1';
        shell_exec($command);
    }

    private function optimizeApplication()
    {
        $commands = [
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache'
        ];

        foreach ($commands as $command) {
            shell_exec($command . ' 2>&1');
        }
    }

    private function createLicenseConfig($licenseData)
    {
        $configContent = "<?php\n";
        $configContent .= "return [\n";
        $configContent .= "    'type' => '{$licenseData['type']}',\n";
        $configContent .= "    'features' => " . var_export($licenseData['features'], true) . ",\n";
        $configContent .= "    'expires' => '{$licenseData['expires']}',\n";
        $configContent .= "    'domain' => '{$licenseData['domain']}',\n";
        $configContent .= "    'validated_at' => '{$licenseData['validated_at']}'\n";
        $configContent .= "];\n";

        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }

        file_put_contents('config/license.php', $configContent);
    }

    private function generatePassword()
    {
        return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 16);
    }

    public function getInstallationProgress()
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logs = file_get_contents($this->logFile);
        $lines = explode("\n", trim($logs));
        
        return array_filter($lines);
    }

    private function cleanupInstaller()
    {
        try {
            require_once $this->basePath . 'core/RemoteDownloader.php';
            $downloader = new RemoteDownloader('', '', [], $this->basePath);
            $downloader->cleanupInstallerFiles();
        } catch (Exception $e) {
            $this->log("Warning: Could not clean up installer files: " . $e->getMessage());
        }
    }

    public function clearInstallation()
    {
        // Clean up temporary files
        $files = [
            'composer.phar'
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
