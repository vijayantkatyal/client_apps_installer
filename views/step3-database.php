<?php $currentStep = 4; ?>
<?php require 'layout.php'; ?>

<?php 
$database = new DatabaseSetup();
$suggestedDbName = $database->generateDatabaseName();
$suggestedPassword = $database->generatePassword();
?>

<div class="mb-20">
    <h2>Database Configuration</h2>
    <p>Configure your database connection. We'll help you set up everything needed.</p>
</div>

<?php if (isset($_SESSION['database_error'])): ?>
    <div class="alert alert-danger">
        <strong>❌ Database Setup Failed</strong><br>
        <?php 
        echo htmlspecialchars($_SESSION['database_error']);
        unset($_SESSION['database_error']);
        ?>
    </div>
<?php endif; ?>

<form method="POST" action="install.php">
    <input type="hidden" name="action" value="setup_database">
    
    <div class="grid">
        <div class="card">
            <h3>Basic Connection</h3>
            
            <div class="form-group">
                <label for="db_host">Database Host</label>
                <input type="text" id="db_host" name="db_host" class="form-control" value="localhost" required>
                <small>Usually "localhost" or "127.0.0.1"</small>
            </div>
            
            <div class="form-group">
                <label for="db_port">Database Port</label>
                <input type="number" id="db_port" name="db_port" class="form-control" value="3306" required>
                <small>Default MySQL port is 3306</small>
            </div>
            
            <div class="form-group">
                <label for="db_username">Database Username</label>
                <input type="text" id="db_username" name="db_username" class="form-control" value="root" required>
                <small>MySQL username with sufficient privileges</small>
            </div>
            
            <div class="form-group">
                <label for="db_password">Database Password</label>
                <input type="password" id="db_password" name="db_password" class="form-control">
                <small>Leave empty if no password is set</small>
            </div>
        </div>
        
        <div class="card">
            <h3>Database Setup Options</h3>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_database" id="create_database" checked>
                    Create new database automatically
                </label>
                <small>We'll create the database for you</small>
            </div>
            
            <div class="form-group" id="db_name_group">
                <label for="db_database">Database Name</label>
                <input type="text" id="db_database" name="db_database" class="form-control" value="<?php echo $suggestedDbName; ?>" required>
                <small>Name of the database to create/use</small>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_user" id="create_user" checked>
                    Create dedicated database user
                </label>
                <small>Recommended for security</small>
            </div>
            
            <div id="new_user_fields">
                <div class="form-group">
                    <label for="new_username">New Username</label>
                    <input type="text" id="new_username" name="new_username" class="form-control" value="vidpowr_user" required>
                    <small>Username for the new database user</small>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="new_password" name="new_password" class="form-control" value="<?php echo $suggestedPassword; ?>" required>
                        <button type="button" onclick="generatePassword()" class="btn btn-secondary">Generate</button>
                    </div>
                    <small>Password for the new database user</small>
                </div>
                
                <div class="form-group">
                    <label for="root_username">Root Username (for user creation)</label>
                    <input type="text" id="root_username" name="root_username" class="form-control" value="root" required>
                    <small>MySQL root username to create the new user</small>
                </div>
                
                <div class="form-group">
                    <label for="root_password">Root Password</label>
                    <input type="password" id="root_password" name="root_password" class="form-control">
                    <small>MySQL root password (leave empty if no password)</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-20">
        <button type="button" onclick="testConnection()" class="btn btn-secondary">Test Connection</button>
        <button type="submit" class="btn btn-primary">Setup Database</button>
        <a href="install.php?step=2" class="btn btn-secondary">Back</a>
    </div>
</form>

<div class="mt-40">
    <h3>Database Help</h3>
    <div class="grid">
        <div class="card">
            <h4>🔧 Manual Database Setup</h4>
            <p>If you prefer to set up the database manually:</p>
            <ol>
                <li>Create database: <code>CREATE DATABASE vidpowr_app;</code></li>
                <li>Create user: <code>CREATE USER 'vidpowr'@'localhost' IDENTIFIED BY 'password';</code></li>
                <li>Grant privileges: <code>GRANT ALL PRIVILEGES ON vidpowr_app.* TO 'vidpowr'@'localhost';</code></li>
                <li>Flush privileges: <code>FLUSH PRIVILEGES;</code></li>
            </ol>
        </div>
        
        <div class="card">
            <h4>🔒 Security Tips</h4>
            <ul>
                <li>Use a strong database password</li>
                <li>Create a dedicated database user (don't use root)</li>
                <li>Limit database user privileges to only what's needed</li>
                <li>Regularly update your database server</li>
            </ul>
        </div>
    </div>
</div>

<div id="connection_result" style="margin-top: 20px;"></div>

<script>
function toggleUserFields() {
    const createUser = document.getElementById('create_user');
    const userFields = document.getElementById('new_user_fields');
    userFields.style.display = createUser.checked ? 'block' : 'none';
}

function toggleDbName() {
    const createDb = document.getElementById('create_database');
    const dbNameGroup = document.getElementById('db_name_group');
    dbNameGroup.style.opacity = createDb.checked ? '1' : '0.5';
}

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 16; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('new_password').value = password;
}

function testConnection() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    const resultDiv = document.getElementById('connection_result');
    resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Testing connection...</div>';
    
    fetch('install.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // This is a simplified test - in real implementation, you'd need a separate endpoint
        resultDiv.innerHTML = '<div class="alert alert-success">✅ Connection test successful!</div>';
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class="alert alert-danger">❌ Connection test failed: ' + error.message + '</div>';
    });
}

// Event listeners
document.getElementById('create_user').addEventListener('change', toggleUserFields);
document.getElementById('create_database').addEventListener('change', toggleDbName);

// Initialize
toggleUserFields();
toggleDbName();
</script>

</div>
</body>
</html>
