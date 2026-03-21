<?php $currentStep = 4; ?>
<?php require 'layout.php'; ?>

<div class="mb-20">
    <h2>Installation Progress</h2>
    <p>Your VidPowr application is being installed. This may take a few minutes.</p>
</div>

<?php if (isset($_SESSION['install_error'])): ?>
    <div class="alert alert-danger">
        <strong>❌ Installation Failed</strong><br>
        <?php 
        echo htmlspecialchars($_SESSION['install_error']);
        unset($_SESSION['install_error']);
        ?>
    </div>
    
    <div class="text-center mt-20">
        <a href="install.php?step=4" class="btn btn-primary">Retry Installation</a>
        <a href="install.php?step=3" class="btn btn-secondary">Back to Database Setup</a>
    </div>
<?php else: ?>
    <div class="loading">
        <div class="spinner"></div>
        <h3>Installing VidPowr...</h3>
        <p id="status-text">Initializing installation...</p>
    </div>

    <div class="progress-steps" id="progress-steps">
        <div class="progress-item" data-step="1">
            <span class="step-icon">⏳</span>
            <span class="step-text">Creating environment file</span>
        </div>
        <div class="progress-item" data-step="2">
            <span class="step-icon">⏳</span>
            <span class="step-text">Installing dependencies</span>
        </div>
        <div class="progress-item" data-step="3">
            <span class="step-icon">⏳</span>
            <span class="step-text">Generating application key</span>
        </div>
        <div class="progress-item" data-step="4">
            <span class="step-icon">⏳</span>
            <span class="step-text">Running database migrations</span>
        </div>
        <div class="progress-item" data-step="5">
            <span class="step-icon">⏳</span>
            <span class="step-text">Seeding database</span>
        </div>
        <div class="progress-item" data-step="6">
            <span class="step-icon">⏳</span>
            <span class="step-text">Creating admin user</span>
        </div>
        <div class="progress-item" data-step="7">
            <span class="step-icon">⏳</span>
            <span class="step-text">Setting file permissions</span>
        </div>
        <div class="progress-item" data-step="8">
            <span class="step-icon">⏳</span>
            <span class="step-text">Creating storage links</span>
        </div>
        <div class="progress-item" data-step="9">
            <span class="step-icon">⏳</span>
            <span class="step-text">Optimizing application</span>
        </div>
        <div class="progress-item" data-step="10">
            <span class="step-icon">⏳</span>
            <span class="step-text">Finalizing installation</span>
        </div>
    </div>

    <form method="POST" action="install.php" id="install-form" style="display: none;">
        <input type="hidden" name="action" value="install">
        <input type="hidden" name="admin_name" value="Admin User">
        <input type="hidden" name="admin_email" value="admin@<?php echo $_SERVER['HTTP_HOST']; ?>">
        <input type="hidden" name="admin_password" value="<?php echo substr(md5(time() . rand()), 0, 12); ?>">
    </form>

    <div class="text-center mt-20">
        <button type="button" onclick="startInstallation()" class="btn btn-primary" id="start-btn">Start Installation</button>
        <button type="button" onclick="checkProgress()" class="btn btn-secondary" id="check-btn" style="display: none;">Check Progress</button>
    </div>
<?php endif; ?>

<style>
.progress-steps {
    margin: 30px 0;
}

.progress-item {
    display: flex;
    align-items: center;
    padding: 12px;
    margin-bottom: 8px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #e9ecef;
    transition: all 0.3s ease;
}

.progress-item.completed {
    background: #f0fff4;
    border-left-color: #28a745;
}

.progress-item.error {
    background: #fff5f5;
    border-left-color: #dc3545;
}

.progress-item.active {
    background: #f0f4ff;
    border-left-color: #667eea;
}

.step-icon {
    font-size: 20px;
    margin-right: 12px;
    min-width: 30px;
}

.step-text {
    font-weight: 500;
}

.installation-log {
    background: #1e1e1e;
    color: #fff;
    padding: 20px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    max-height: 300px;
    overflow-y: auto;
    margin-top: 20px;
    display: none;
}

.log-line {
    margin-bottom: 5px;
    white-space: pre-wrap;
}

.log-success {
    color: #4ade80;
}

.log-error {
    color: #f87171;
}

.log-info {
    color: #60a5fa;
}
</style>

<script>
let installationStarted = false;
let progressInterval;

function startInstallation() {
    if (installationStarted) return;
    
    installationStarted = true;
    document.getElementById('start-btn').style.display = 'none';
    document.getElementById('status-text').textContent = 'Starting installation...';
    
    // Submit the form
    document.getElementById('install-form').submit();
    
    // Start checking progress
    setTimeout(checkProgress, 2000);
}

function checkProgress() {
    document.getElementById('check-btn').style.display = 'inline-block';
    
    progressInterval = setInterval(() => {
        // Simulate progress (in real implementation, this would check actual progress)
        updateProgress();
    }, 3000);
}

function updateProgress() {
    const steps = document.querySelectorAll('.progress-item');
    let currentStep = 0;
    
    // Find current active step
    steps.forEach((step, index) => {
        if (step.classList.contains('active')) {
            currentStep = index;
        }
    });
    
    // Update progress
    if (currentStep < steps.length - 1) {
        // Mark current as completed
        steps[currentStep].classList.remove('active');
        steps[currentStep].classList.add('completed');
        steps[currentStep].querySelector('.step-icon').textContent = '✅';
        
        // Activate next step
        currentStep++;
        steps[currentStep].classList.add('active');
        steps[currentStep].querySelector('.step-icon').textContent = '⏳';
        
        // Update status text
        const stepText = steps[currentStep].querySelector('.step-text').textContent;
        document.getElementById('status-text').textContent = stepText + '...';
    } else {
        // Installation complete
        clearInterval(progressInterval);
        steps[steps.length - 1].classList.remove('active');
        steps[steps.length - 1].classList.add('completed');
        steps[steps.length - 1].querySelector('.step-icon').textContent = '✅';
        
        document.getElementById('status-text').textContent = 'Installation completed successfully!';
        
        // Redirect to completion page
        setTimeout(() => {
            window.location.href = 'install.php?step=complete';
        }, 2000);
    }
}

// Auto-start installation when page loads
window.addEventListener('load', () => {
    setTimeout(() => {
        if (!installationStarted) {
            startInstallation();
        }
    }, 1000);
});
</script>

</div>
</body>
</html>
