<?php $currentStep = 3; ?>
<?php require 'layout.php'; ?>

<div class="mb-20">
    <h2>System Requirements Check</h2>
    <p>We're checking if your server meets the requirements for VidPowr installation.</p>
</div>

<div class="requirements-table">
    <table>
        <thead>
            <tr>
                <th>Requirement</th>
                <th>Current</th>
                <th>Required</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requirements as $key => $requirement): ?>
                <tr>
                    <td><?php echo htmlspecialchars($requirement['name']); ?></td>
                    <td>
                        <?php 
                        if (isset($requirement['result']['current'])) {
                            if (is_array($requirement['result']['current'])) {
                                echo implode(', ', $requirement['result']['current']);
                            } else {
                                echo htmlspecialchars($requirement['result']['current']);
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if (isset($requirement['result']['required'])) {
                            if (is_array($requirement['result']['required'])) {
                                echo implode(', ', $requirement['result']['required']);
                            } else {
                                echo htmlspecialchars($requirement['result']['required']);
                            }
                        } else {
                            echo htmlspecialchars($requirement['required']);
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if (isset($requirement['result']['status']) && $requirement['result']['status']) {
                            echo '<span class="status-ok">✅ OK</span>';
                        } elseif (isset($requirement['result']['optional']) && $requirement['result']['optional']) {
                            echo '<span class="status-warning">⚠️ Optional</span>';
                        } else {
                            echo '<span class="status-error">❌ Failed</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php if (isset($requirement['result']['status']) && !$requirement['result']['status'] && !($requirement['result']['optional'] ?? false)): ?>
                <tr>
                    <td colspan="4" style="background: #fff5f5; padding: 10px;">
                        <strong>Fix:</strong> <?php echo htmlspecialchars($requirement['result']['fix'] ?? 'Contact support'); ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($all_passed): ?>
    <div class="alert alert-success">
        <strong>🎉 Great!</strong> Your server meets all requirements. You can proceed with the installation.
    </div>
    
    <div class="text-center mt-20">
        <a href="install.php?step=database" class="btn btn-primary">Continue to Database Setup</a>
    </div>
<?php else: ?>
    <div class="alert alert-danger">
        <strong>⚠️ Requirements Not Met</strong>
        <p>Please fix the failed requirements before continuing. Some requirements are critical and must be resolved.</p>
    </div>
    
    <?php if ($can_continue): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Warning</strong>
            <p>Some optional requirements are missing, but you can continue with the installation. Video processing features may not work properly without FFmpeg.</p>
        </div>
        
        <div class="text-center mt-20">
            <a href="install.php?step=2" class="btn btn-primary">Continue Anyway</a>
            <a href="install.php?step=1" class="btn btn-secondary">Re-check</a>
        </div>
    <?php else: ?>
        <div class="text-center mt-20">
            <a href="install.php?step=1" class="btn btn-primary">Re-check Requirements</a>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="mt-20">
    <h3>Installation Help</h3>
    <div class="grid">
        <div class="card">
            <h4>PHP Requirements</h4>
            <p><strong>PHP 8.0+ Required</strong></p>
            <p>Install on Ubuntu/Debian:</p>
            <code>sudo apt-get install php8.1 php8.1-mysql php8.1-gd php8.1-zip php8.1-bcmath php8.1-curl php8.1-mbstring php8.1-xml</code>
        </div>
        
        <div class="card">
            <h4>Database Setup</h4>
            <p><strong>MySQL 8.0+ or MariaDB 10.3+</strong></p>
            <p>Install on Ubuntu/Debian:</p>
            <code>sudo apt-get install mysql-server</code>
        </div>
        
        <div class="card">
            <h4>FFmpeg (Optional)</h4>
            <p><strong>For video processing</strong></p>
            <p>Install on Ubuntu/Debian:</p>
            <code>sudo apt-get install ffmpeg</code>
        </div>
        
        <div class="card">
            <h4>File Permissions</h4>
            <p><strong>After app download & extraction</strong></p>
            <p>Set permissions for extracted app:</p>
            <code>chmod -R 755 storage bootstrap/cache<br>chmod -R 777 storage</code>
            <p style="margin-top: 8px; font-size: 0.9em; color: #6c757d;">
                ⚠️ These directories will be created after the application is downloaded and extracted during installation.
            </p>
        </div>
    </div>
</div>

</div>
</body>
</html>
