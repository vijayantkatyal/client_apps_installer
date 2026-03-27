<?php 
$currentStep = 2; 
$licenseData = $_SESSION['license_data'] ?? [];
$licenseType = $licenseData['type'] ?? 'normal';
$licenseFeatures = $licenseData['features'] ?? [];
$licensedApp = $licenseData['licensed_app'] ?? null;
?>
<?php require 'layout.php'; ?>

<div class="mb-20">
    <h2>🚀 Choose Application to Install</h2>
    <p>Select which application you'd like to install on your server.</p>
</div>

<?php if ($licenseData): ?>
    <div class="license-info" style="background: #d1ecf1; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #bee5eb;">
        <h4 style="color: #0c5460; margin-bottom: 15px;">🔑 Active License Information</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <strong>License Type:</strong> 
                <span style="text-transform: capitalize; color: #0c5460;"><?php echo htmlspecialchars($licenseType); ?></span>
            </div>
            <div>
                <strong>License Key:</strong> 
                <span style="font-family: monospace; color: #0c5460;"><?php echo htmlspecialchars($licenseData['license_key'] ?? ''); ?></span>
            </div>
            <?php if ($licensedApp): ?>
            <div>
                <strong>Licensed Application:</strong> 
                <span style="color: #0c5460; font-weight: bold;">
                    <?php 
                    $appsConfig = json_decode(file_get_contents(__DIR__ . '/../config/apps.json'), true);
                    echo htmlspecialchars($appsConfig[$licensedApp]['name'] ?? $licensedApp); 
                    ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($licensedApp): ?>
        <div style="margin-top: 15px; background: #fff3cd; padding: 10px; border-radius: 4px; border: 1px solid #ffeaa7;">
            <strong style="color: #856404;">📋 App-Specific License:</strong> 
            <span style="color: #856404;">This license is valid only for the application listed above.</span>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($apps)): ?>
    <div class="alert alert-warning" style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border: 1px solid #ffeaa7; text-align: center;">
        <h3>⚠️ No Applications Available</h3>
        <p>There are no applications available for your current license type.</p>
        <p>Please <a href="install.php?step=license" style="color: #667eea; text-decoration: none;">validate a different license</a> or contact support for assistance.</p>
    </div>
<?php else: ?>
    <div class="apps-grid">
        <?php foreach ($apps as $appId => $app): ?>
            <div class="app-card">
                <div class="app-icon"><?php echo $app['icon']; ?></div>
                <h3><?php echo htmlspecialchars($app['name']); ?></h3>
                <p class="app-description"><?php echo htmlspecialchars($app['description']); ?></p>
                
                <div class="app-info">
                    <div class="app-version">
                        <strong>Version:</strong> <?php echo htmlspecialchars($app['version']); ?>
                    </div>
                    <div class="app-requirements">
                        <strong>Requirements:</strong><br>
                        PHP <?php echo htmlspecialchars($app['requirements']['php']); ?><br>
                        <?php echo htmlspecialchars($app['requirements']['database']); ?><br>
                        <?php echo htmlspecialchars($app['requirements']['memory_limit']); ?> RAM<br>
                        <?php echo htmlspecialchars($app['requirements']['disk_space']); ?> space
                    </div>
                    <?php if (isset($app['website'])): ?>
                    <div class="app-website" style="margin-top: 10px;">
                        <strong>Website:</strong> 
                        <a href="<?php echo htmlspecialchars($app['website']); ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                            <?php echo htmlspecialchars($app['website']); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="install.php">
                    <input type="hidden" name="action" value="select_app">
                    <input type="hidden" name="app" value="<?php echo $appId; ?>">
                    <button type="submit" class="btn btn-primary btn-block">
                        Install <?php echo htmlspecialchars($app['name']); ?>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="mt-40">
    <h3>🔧 Installation Process</h3>
    <div class="process-steps">
        <div class="step">
            <div class="step-number completed">✓</div>
            <div class="step-content">
                <h4>License Validation</h4>
                <p>Your license key has been validated</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number active">2</div>
            <div class="step-content">
                <h4>Application Selection</h4>
                <p>Choose from applications available for your license type</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <div class="step-content">
                <h4>System Check</h4>
                <p>We'll verify your server meets all requirements</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <div class="step-content">
                <h4>Database Setup</h4>
                <p>Configure your database connection</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">5</div>
            <div class="step-content">
                <h4>Installation</h4>
                <p>Install dependencies and configure everything</p>
            </div>
        </div>
    </div>
</div>

<style>
.apps-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 30px;
    margin: 30px 0;
}

.app-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
    text-align: center;
}

.app-card:hover {
    border-color: #667eea;
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.app-icon {
    font-size: 60px;
    margin-bottom: 20px;
}

.app-card h3 {
    margin-bottom: 15px;
    color: #333;
    font-size: 1.5em;
}

.app-description {
    color: #6c757d;
    margin-bottom: 25px;
    min-height: 50px;
}

.app-info {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    text-align: left;
    font-size: 0.9em;
}

.app-version {
    margin-bottom: 10px;
}

.app-requirements {
    line-height: 1.4;
}

.process-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}

.step-number.completed {
    background: #28a745;
    color: white;
}

.step-number.active {
    background: #667eea;
    color: white;
}

.step-number {
    background: #e9ecef;
    color: #6c757d;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.step-content h4 {
    margin-bottom: 5px;
    color: #333;
}

.step-content p {
    color: #6c757d;
    font-size: 0.9em;
    margin: 0;
}

@media (max-width: 768px) {
    .apps-grid {
        grid-template-columns: 1fr;
    }
    
    .process-steps {
        grid-template-columns: 1fr;
    }
}
</style>

</div>
</body>
</html>
