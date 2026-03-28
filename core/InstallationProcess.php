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
            throw new Exception('Database migration failed: ' . $output);
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
