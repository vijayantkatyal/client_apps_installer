<?php $currentStep = 4; ?>
<?php require 'layout.php'; ?>

<?php
$adminCredentials = $_SESSION['admin_credentials'] ?? [
    'email' => 'admin@' . $_SERVER['HTTP_HOST'],
    'password' => 'admin123'
];
$licenseData = $_SESSION['license_data'] ?? [];
$appUrl = 'http://' . $_SERVER['HTTP_HOST'];
?>

<div class="text-center mb-40">
    <div style="font-size: 80px; margin-bottom: 20px;">🎉</div>
    <h2 style="color: #28a745; margin-bottom: 10px;">Installation Complete!</h2>
    <p style="font-size: 18px; color: #6c757d;">Your VidPowr application is ready to use.</p>
</div>

<div class="grid">
    <div class="card">
        <h3>🔑 Admin Login Details</h3>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
            <p><strong>Email:</strong> <?php echo htmlspecialchars($adminCredentials['email']); ?></p>
            <p><strong>Password:</strong> <code style="background: #fff; padding: 5px 10px; border-radius: 4px;"><?php echo htmlspecialchars($adminCredentials['password']); ?></code></p>
        </div>
        <div class="alert alert-warning">
            <strong>⚠️ Important:</strong> Save these credentials securely. Change the password after your first login.
        </div>
        <div class="text-center">
            <a href="<?php echo $appUrl; ?>/admin" target="_blank" class="btn btn-primary">Login to Admin Panel</a>
        </div>
    </div>
    
    <div class="card">
        <h3>📋 License Information</h3>
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0;">
            <p><strong>License Type:</strong> <?php echo htmlspecialchars($licenseData['type'] ?? 'Normal'); ?></p>
            <p><strong>Domain:</strong> <?php echo htmlspecialchars($licenseData['domain'] ?? $_SERVER['HTTP_HOST']); ?></p>
            <p><strong>Validated:</strong> <?php echo htmlspecialchars($licenseData['validated_at'] ?? date('Y-m-d H:i:s')); ?></p>
            <?php if (!empty($licenseData['expires'])): ?>
            <p><strong>Expires:</strong> <?php echo htmlspecialchars($licenseData['expires']); ?></p>
            <?php endif; ?>
        </div>
        <div class="text-center">
            <a href="<?php echo $appUrl; ?>" target="_blank" class="btn btn-success">Visit Your Site</a>
        </div>
    </div>
</div>

<div class="mt-40">
    <h3>🚀 Next Steps</h3>
    <div class="grid">
        <div class="card">
            <h4>1. Configure Your Application</h4>
            <ul>
                <li>Update your site settings</li>
                <li>Configure email settings</li>
                <li>Set up payment gateways (if applicable)</li>
                <li>Customize branding and appearance</li>
            </ul>
        </div>
        
        <div class="card">
            <h4>2. Security Recommendations</h4>
            <ul>
                <li>Change default admin password</li>
                <li>Enable HTTPS/SSL certificate</li>
                <li>Set up regular backups</li>
                <li>Configure firewall rules</li>
                <li>Keep software updated</li>
            </ul>
        </div>
        
        <div class="card">
            <h4>3. Performance Optimization</h4>
            <ul>
                <li>Configure caching</li>
                <li>Set up CDN for static assets</li>
                <li>Optimize database settings</li>
                <li>Monitor server resources</li>
            </ul>
        </div>
    </div>
</div>

<div class="mt-40">
    <h3>📚 Documentation & Support</h3>
    <div class="grid">
        <div class="card">
            <h4>📖 Documentation</h4>
            <ul>
                <li><a href="https://docs.vidpowr.com" target="_blank">User Guide</a></li>
                <li><a href="https://docs.vidpowr.com/admin" target="_blank">Admin Documentation</a></li>
                <li><a href="https://docs.vidpowr.com/api" target="_blank">API Reference</a></li>
                <li><a href="https://docs.vidpowr.com/troubleshooting" target="_blank">Troubleshooting</a></li>
            </ul>
        </div>
        
        <div class="card">
            <h4>💬 Support</h4>
            <ul>
                <li><a href="https://support.vidpowr.com" target="_blank">Support Center</a></li>
                <li><a href="mailto:support@vidpowr.com">Email Support</a></li>
                <li><a href="https://community.vidpowr.com" target="_blank">Community Forum</a></li>
                <li><a href="https://status.vidpowr.com" target="_blank">System Status</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="mt-40">
    <h3>🔧 Installation Details</h3>
    <div class="card">
        <h4>System Information</h4>
        <div class="grid">
            <div>
                <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                <p><strong>Server Software:</strong> <?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></p>
                <p><strong>Installation Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            <div>
                <p><strong>Application URL:</strong> <a href="<?php echo $appUrl; ?>" target="_blank"><?php echo $appUrl; ?></a></p>
                <p><strong>Admin URL:</strong> <a href="<?php echo $appUrl; ?>/admin" target="_blank"><?php echo $appUrl; ?>/admin</a></p>
                <p><strong>Install Log:</strong> storage/install.log</p>
            </div>
        </div>
    </div>
</div>

<div class="mt-40">
    <div class="alert alert-success">
        <h4>🎊 Congratulations!</h4>
        <p>Your VidPowr installation is complete and ready to use. You can now start creating amazing video experiences for your users.</p>
        <p class="mt-10"><strong>Thank you for choosing VidPowr!</strong></p>
    </div>
</div>

<div class="text-center mt-40">
    <a href="<?php echo $appUrl; ?>/admin" target="_blank" class="btn btn-primary btn-lg">Go to Admin Panel</a>
    <a href="<?php echo $appUrl; ?>" target="_blank" class="btn btn-success btn-lg">Visit Your Site</a>
</div>

<div class="text-center mt-20">
    <p><small>
        <strong>Important:</strong> For security reasons, delete the <code>install.php</code> file and <code>install/</code> directory after confirming everything works correctly.
    </small></p>
</div>

</div>
</body>
</html>
