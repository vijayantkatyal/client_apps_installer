<?php require 'layout.php'; ?>

<div class="text-center mb-40">
    <div style="font-size: 60px; margin-bottom: 20px;">🔄</div>
    <h2 style="color: #667eea; margin-bottom: 10px;">Update Available!</h2>
    <p style="font-size: 18px; color: #6c757d;">A new version of your application is ready to install.</p>
</div>

<?php if ($updateCheck['update_available']): ?>
    <div class="update-info">
        <div class="grid">
            <div class="card">
                <h3>📦 Update Information</h3>
                <div class="info-grid">
                    <div>
                        <strong>Current Version:</strong><br>
                        <span class="current-version"><?php echo htmlspecialchars($currentVersion ?? 'Unknown'); ?></span>
                    </div>
                    <div>
                        <strong>Latest Version:</strong><br>
                        <span class="latest-version"><?php echo htmlspecialchars($updateCheck['latest_version']); ?></span>
                    </div>
                    <div>
                        <strong>Download Size:</strong><br>
                        <span><?php echo htmlspecialchars($updateCheck['download_size']); ?></span>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($updateCheck['release_notes'])): ?>
            <div class="card">
                <h3>📝 Release Notes</h3>
                <div class="release-notes">
                    <?php echo nl2br(htmlspecialchars($updateCheck['release_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($updateCheck['breaking_changes'] ?? false): ?>
            <div class="alert alert-warning">
                <h4>⚠️ Breaking Changes</h4>
                <p>This update contains breaking changes. Please review the release notes carefully and backup your data before proceeding.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($updateCheck['required_php'] && version_compare(PHP_VERSION, $updateCheck['required_php'], '<')): ?>
            <div class="alert alert-danger">
                <h4>❌ PHP Version Requirement</h4>
                <p>This update requires PHP <?php echo htmlspecialchars($updateCheck['required_php']); ?> or higher. Your current version is <?php echo PHP_VERSION; ?>.</p>
                <p>Please upgrade your PHP version before installing this update.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="update-actions">
        <div class="text-center">
            <button onclick="performUpdate()" class="btn btn-primary btn-lg" id="update-btn">
                🚀 Install Update
            </button>
            <button onclick="skipUpdate()" class="btn btn-secondary btn-lg">
                ⏭️ Skip for Now
            </button>
        </div>
        
        <div class="text-center mt-20">
            <a href="./" class="btn btn-outline">
                🏠 Go to Application
            </a>
        </div>
    </div>
    
    <div id="update-progress" style="display: none;">
        <div class="progress-container">
            <h3>Installing Update...</h3>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progress-fill" style="width: 0%;"></div>
            </div>
            <div id="progress-text">Preparing update...</div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-success text-center">
        <h3>✅ Up to Date</h3>
        <p>Your application is running the latest version.</p>
    </div>
    
    <div class="text-center">
        <a href="./" class="btn btn-primary btn-lg">
            🏠 Go to Application
        </a>
    </div>
<?php endif; ?>

<style>
.update-info {
    margin: 30px 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.current-version {
    color: #6c757d;
    font-weight: normal;
}

.latest-version {
    color: #28a745;
    font-weight: bold;
}

.release-notes {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.update-actions {
    margin: 40px 0;
}

.progress-container {
    background: white;
    border-radius: 12px;
    padding: 30px;
    border: 2px solid #e9ecef;
    text-align: center;
}

.progress-bar-container {
    width: 100%;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    margin: 20px 0;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    width: 0%;
    transition: width 0.3s ease;
    border-radius: 10px;
}

#progress-text {
    color: #6c757d;
    margin-top: 15px;
}
</style>

<script>
function performUpdate() {
    const btn = document.getElementById('update-btn');
    const progress = document.getElementById('update-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    
    // Hide button and show progress
    btn.style.display = 'none';
    progress.style.display = 'block';
    
    // Simulate progress
    let progressValue = 0;
    const interval = setInterval(() => {
        progressValue += Math.random() * 15;
        if (progressValue >= 90) {
            progressValue = 90;
            clearInterval(interval);
        }
        progressFill.style.width = progressValue + '%';
        
        if (progressValue < 30) {
            progressText.textContent = 'Creating backup...';
        } else if (progressValue < 50) {
            progressText.textContent = 'Downloading update...';
        } else if (progressValue < 70) {
            progressText.textContent = 'Installing files...';
        } else if (progressValue < 90) {
            progressText.textContent = 'Running migrations...';
        } else {
            progressText.textContent = 'Finalizing update...';
        }
    }, 500);
    
    // Perform actual update
    fetch('install_universal.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=perform_update'
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(interval);
        progressFill.style.width = '100%';
        
        if (data.success) {
            progressText.textContent = '✅ Update completed successfully!';
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            progressText.textContent = '❌ Update failed: ' + data.error;
            btn.style.display = 'inline-block';
            btn.textContent = '🔄 Retry Update';
        }
    })
    .catch(error => {
        clearInterval(interval);
        progressText.textContent = '❌ Update failed: ' + error.message;
        btn.style.display = 'inline-block';
        btn.textContent = '🔄 Retry Update';
    });
}

function skipUpdate() {
    if (confirm('Are you sure you want to skip this update? You can update later from the admin panel.')) {
        window.location.href = './';
    }
}
</script>

</div>
</body>
</html>
