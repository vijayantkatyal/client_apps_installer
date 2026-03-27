<?php $currentStep = 2; ?>
<?php require 'layout.php'; ?>

<div class="mb-20">
    <h2>License Validation</h2>
    <p>Enter your VidPowr license key to activate your installation.</p>
</div>

<?php if (isset($_SESSION['license_error'])): ?>
    <div class="alert alert-danger">
        <strong>❌ License Validation Failed</strong><br>
        <?php 
        echo htmlspecialchars($_SESSION['license_error']);
        unset($_SESSION['license_error']);
        ?>
    </div>
<?php endif; ?>

<?php if ($storedLicense): ?>
    <div class="alert alert-success">
        <strong>✅ License Already Validated</strong><br>
        License Type: <strong><?php echo htmlspecialchars($storedLicense['type']); ?></strong><br>
        Domain: <strong><?php echo htmlspecialchars($storedLicense['domain']); ?></strong><br>
        Validated: <strong><?php echo htmlspecialchars($storedLicense['validated_at']); ?></strong>
    </div>
    
    <div class="text-center mt-20">
        <a href="install.php?step=database" class="btn btn-primary">Continue to Database Setup</a>
        <a href="install.php?step=license_validation&revalidate=1" class="btn btn-secondary">Re-validate License</a>
    </div>
<?php else: ?>
    <form method="POST" action="install.php">
        <input type="hidden" name="action" value="validate_license">
        
        <div class="form-group">
            <label for="license_key">License Key</label>
            <input 
                type="text" 
                id="license_key" 
                name="license_key" 
                class="form-control" 
                placeholder="VIDPOWR-XXXXX-XXXXX-XXXXX"
                pattern="^VIDPOWR-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$"
                required
                style="font-family: monospace; font-size: 18px; text-align: center; letter-spacing: 2px;"
            >
            <small style="color: #6c757d;">Format: VIDPOWR-XXXXX-XXXXX-XXXXX</small>
        </div>

        <div class="alert alert-info">
            <strong>ℹ️ About Your License Key</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>Your license key is tied to your domain name</li>
                <li>One license can be used on one domain only</li>
                <li>License validation requires internet connection</li>
                <li>30-day offline grace period available</li>
            </ul>
        </div>

        <div class="text-center mt-20">
            <button type="submit" class="btn btn-primary">Validate License</button>
            <a href="https://vidpowr.com/buy-license" target="_blank" class="btn btn-secondary">Buy License</a>
        </div>
    </form>

    <div class="mt-40">
        <h3>License Types</h3>
        <div class="grid">
            <div class="card">
                <h4>🏢 Normal License</h4>
                <ul>
                    <li>Single domain usage</li>
                    <li>Standard video features</li>
                    <li>Basic analytics</li>
                    <li>Email support</li>
                </ul>
            </div>
            
            <div class="card">
                <h4>🚀 Agency License</h4>
                <ul>
                    <li>Multi-domain support</li>
                    <li>Advanced video features</li>
                    <li>Premium analytics</li>
                    <li>Priority support</li>
                    <li>White-label options</li>
                    <li>API access</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="mt-40">
        <h3>Troubleshooting</h3>
        <div class="card">
            <h4>Common Issues</h4>
            <div style="margin-bottom: 15px;">
                <strong>❌ "Cannot connect to license server"</strong><br>
                <small>Check your internet connection and firewall settings.</small>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>❌ "Invalid license key"</strong><br>
                <small>Make sure you enter the license key exactly as provided</small>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>❌ "License domain mismatch"</strong><br>
                <small>The license is already activated on a different domain. Contact support for assistance</small>
            </div>
            <div>
                <strong>❌ "License expired"</strong><br>
                <small>Your license has expired. Please renew your license to continue</small>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
</body>
</html>
