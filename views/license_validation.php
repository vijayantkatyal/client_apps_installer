<?php $currentStep = 0; ?>
<?php require 'layout.php'; ?>

<div class="mb-20">
    <h2>🔑 License Validation</h2>
    <p>Please enter your license key to continue with the installation.</p>
</div>

<?php if ($licenseError): ?>
    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <strong>Error:</strong> <?php echo htmlspecialchars($licenseError); ?>
    </div>
<?php endif; ?>

<?php if ($storedLicense): ?>
    <div class="alert alert-info" style="background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bee5eb;">
        <strong>Stored License Found:</strong><br>
        License Type: <strong><?php echo htmlspecialchars($storedLicense['type']); ?></strong><br>
        Validated: <?php echo htmlspecialchars($storedLicense['validated_at']); ?><br>
        <div style="margin-top: 10px;">
            <a href="install.php?step=app_selection" class="btn btn-primary" style="display: inline-block; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px;">Continue with Stored License</a>
            <a href="install.php?action=remove_license&step=license_validation" class="btn btn-danger" style="display: inline-block; padding: 8px 16px; background: #dc3545; color: white; text-decoration: none; border-radius: 6px;" onclick="return confirm('Are you sure you want to remove the stored license?')">Remove Stored License</a>
        </div>
    </div>
<?php endif; ?>

<form method="POST" action="install.php" class="license-form">
    <input type="hidden" name="action" value="validate_license">
    
    <div class="form-group">
        <label for="license_key" class="form-label">License Key</label>
        <input type="text" 
               id="license_key" 
               name="license_key" 
               class="form-control" 
               placeholder="XXXXXXXX-XXXXX-XXXXX-XXXXX"
               value="<?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>"
               required
               style="width: 100%; padding: 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 16px; font-family: monospace;">
        <small class="form-text" style="color: #6c757d; font-size: 0.9em; margin-top: 5px; display: block;">
            Format: XXXXXXXX-XXXXX-XXXXX-XXXXX
        </small>
    </div>
    
    <div class="form-actions" style="margin-top: 20px;">
        <button type="submit" class="btn btn-primary btn-block" style="width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.3s;">
            Validate License
        </button>
    </div>
</form>

<div class="mt-40">
    <h3>📋 License Information</h3>
    <div class="license-info" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px;">
        <div style="margin-bottom: 15px;">
            <h4>License Types:</h4>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">
                    <strong>Normal License:</strong> Access to basic features for single domain usage
                </li>
                <li style="margin-bottom: 10px;">
                    <strong>Agency License:</strong> Full access to all features including multi-domain support and API access
                </li>
            </ul>
        </div>
        
        <div>
            <h4>Available Applications:</h4>
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 8px;">
                    <span style="font-size: 1.2em;">🎬</span> <strong>VidPowr:</strong> Professional video hosting and streaming platform
                </li>
                <li style="margin-bottom: 8px;">
                    <span style="font-size: 1.2em;">🎵</span> <strong>FeedPlay:</strong> RSS feed and podcast management platform
                </li>
                <li style="margin-bottom: 8px;">
                    <span style="font-size: 1.2em;">📖</span> <strong>VidChapter:</strong> Video chaptering and timestamp management platform
                </li>
                <li style="margin-bottom: 8px;">
                    <span style="font-size: 1.2em;">🏷️</span> <strong>VidTags:</strong> Video tagging and metadata management platform
                </li>
            </ul>
        </div>
    </div>
</div>

<div class="mt-30">
    <h3>🔧 Installation Process</h3>
    <div class="process-steps">
        <div class="step">
            <div class="step-number active">1</div>
            <div class="step-content">
                <h4>License Validation</h4>
                <p>Validate your license key to unlock available applications</p>
            </div>
        </div>
        <div class="step">
            <div class="step-number">2</div>
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
.license-form {
    background: white;
    padding: 30px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    margin: 20px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary:hover {
    background: #5a6fd8 !important;
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

.step-number.active {
    background: #667eea;
    color: white;
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
    .process-steps {
        grid-template-columns: 1fr;
    }
}
</style>

</div>
</body>
</html>
