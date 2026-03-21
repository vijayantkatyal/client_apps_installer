<?php

class SystemCheck
{
    private $requirements = [
        'php_version' => [
            'required' => '8.0',
            'name' => 'PHP Version',
            'check' => 'checkPhpVersion'
        ],
        'memory_limit' => [
            'required' => '256M',
            'name' => 'Memory Limit',
            'check' => 'checkMemoryLimit'
        ],
        'extensions' => [
            'required' => ['mysqli', 'pdo', 'pdo_mysql', 'gd', 'zip', 'bcmath', 'curl', 'json', 'mbstring', 'openssl', 'tokenizer', 'xml'],
            'name' => 'PHP Extensions',
            'check' => 'checkExtensions'
        ],
        'file_permissions' => [
            'required' => ['storage', 'bootstrap/cache'],
            'name' => 'File Permissions',
            'check' => 'checkFilePermissions'
        ],
        'database' => [
            'required' => true,
            'name' => 'MySQL/MariaDB',
            'check' => 'checkDatabase'
        ],
        'ffmpeg' => [
            'required' => false,
            'name' => 'FFmpeg (for video processing)',
            'check' => 'checkFFmpeg'
        ],
        'disk_space' => [
            'required' => '2GB',
            'name' => 'Disk Space',
            'check' => 'checkDiskSpace'
        ]
    ];

    public function checkAll()
    {
        $results = [];
        $allPassed = true;

        foreach ($this->requirements as $key => $requirement) {
            $method = $requirement['check'];
            $results[$key] = [
                'name' => $requirement['name'],
                'required' => $requirement['required'],
                'result' => $this->$method($requirement['required'])
            ];
            
            if (!$results[$key]['result']['status']) {
                $allPassed = false;
            }
        }

        return [
            'all_passed' => $allPassed,
            'requirements' => $results,
            'can_continue' => $this->canContinue($results)
        ];
    }

    private function checkPhpVersion($required)
    {
        $current = PHP_VERSION;
        $status = version_compare($current, $required, '>=');
        
        return [
            'status' => $status,
            'current' => $current,
            'required' => $required . '+',
            'message' => $status ? 'OK' : "PHP version $required or higher is required",
            'fix' => "Upgrade PHP to version $required or higher"
        ];
    }

    private function checkMemoryLimit($required)
    {
        $current = ini_get('memory_limit');
        $currentBytes = $this->parseMemoryLimit($current);
        $requiredBytes = $this->parseMemoryLimit($required);
        $status = $currentBytes >= $requiredBytes;
        
        return [
            'status' => $status,
            'current' => $current,
            'required' => $required . '+',
            'message' => $status ? 'OK' : "Memory limit $required or higher is recommended",
            'fix' => "Set memory_limit = $required in php.ini"
        ];
    }

    private function checkExtensions($required)
    {
        $missing = [];
        $installed = [];
        
        foreach ($required as $ext) {
            if (extension_loaded($ext)) {
                $installed[] = $ext;
            } else {
                $missing[] = $ext;
            }
        }
        
        $status = empty($missing);
        
        return [
            'status' => $status,
            'current' => $installed,
            'missing' => $missing,
            'required' => $required,
            'message' => $status ? 'All required extensions installed' : 'Missing extensions: ' . implode(', ', $missing),
            'fix' => "Install missing extensions: sudo apt-get install php-" . implode(' php-', $missing)
        ];
    }

    private function checkFilePermissions($required)
    {
        $issues = [];
        $writable = [];
        
        foreach ($required as $dir) {
            if (!file_exists($dir)) {
                // Don't try to create directories at runtime, just check if they exist
                error_log("Directory not found: $dir");
            }
            
            if (is_writable($dir)) {
                $writable[] = $dir;
            } else {
                $issues[] = $dir;
            }
        }
        
        $status = empty($issues);
        
        return [
            'status' => $status,
            'writable' => $writable,
            'issues' => $issues,
            'required' => $required,
            'message' => $status ? 'All directories writable' : 'Not writable: ' . implode(', ', $issues),
            'fix' => "Run: chmod -R 755 storage bootstrap/cache && chmod -R 777 storage bootstrap/cache"
        ];
    }

    private function checkDatabase($required)
    {
        $status = false;
        $message = '';
        $fix = '';
        
        // Check if MySQL/MariaDB functions are available
        if (function_exists('mysqli_connect')) {
            $status = true;
            $message = 'MySQL functions available';
            $fix = 'Configure database in next step';
        } else {
            $message = 'MySQL functions not available';
            $fix = 'Install MySQL/MariaDB or PHP MySQL extension';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'fix' => $fix
        ];
    }

    private function checkFFmpeg($required)
    {
        $status = false;
        $version = '';
        $message = '';
        $fix = '';
        
        // Try to find ffmpeg
        $paths = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', 'ffmpeg'];
        
        foreach ($paths as $path) {
            if (is_executable($path) || ($path === 'ffmpeg' && shell_exec("which ffmpeg"))) {
                $version = shell_exec("$path -version 2>&1 | head -n1");
                $status = true;
                $message = 'FFmpeg available';
                $fix = '';
                break;
            }
        }
        
        if (!$status) {
            $message = 'FFmpeg not found (optional but recommended)';
            $fix = 'Install FFmpeg: sudo apt-get install ffmpeg';
        }
        
        return [
            'status' => $status,
            'version' => trim($version),
            'message' => $message,
            'fix' => $fix,
            'optional' => true
        ];
    }

    private function checkDiskSpace($required)
    {
        $freeBytes = disk_free_space('.');
        $requiredBytes = $this->parseMemoryLimit(str_replace('GB', 'G', $required));
        $status = $freeBytes >= $requiredBytes;
        
        $freeGB = round($freeBytes / (1024 * 1024 * 1024), 2);
        
        return [
            'status' => $status,
            'current' => $freeGB . 'GB',
            'required' => $required,
            'message' => $status ? 'OK' : "At least $required disk space required",
            'fix' => "Free up disk space or choose a different location"
        ];
    }

    private function parseMemoryLimit($value)
    {
        $unit = strtolower(substr($value, -1));
        $value = (int)$value;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    private function canContinue($results)
    {
        // Check critical requirements that must pass
        $critical = ['php_version', 'extensions', 'file_permissions'];
        
        foreach ($critical as $key) {
            if (!$results[$key]['result']['status']) {
                return false;
            }
        }
        
        return true;
    }
}
