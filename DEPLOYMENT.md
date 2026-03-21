# Universal Installer Deployment Guide

## 🚀 Quick Deployment

### **For End Users:**

1. **Upload the entire `client_apps_installer/` directory** to your web server
2. **Ensure proper permissions**:
   ```bash
   chmod 755 storage/
   chmod 755 storage/temp/
   chmod 755 storage/backups/
   chmod 755 storage/license/
   ```
3. **Navigate to the installer**:
   ```
   http://yourdomain.com/client_apps_installer/install.php
   ```
4. **Follow the installation wizard**

### **For Developers:**

1. **Configure your applications** in `config/apps.json`
2. **Set up license and release servers**
3. **Distribute only the installer directory** to customers
4. **Customers download your actual applications** during installation

## 📋 Server Requirements

### **Minimum Requirements:**
- **PHP**: 8.0+ (recommended 8.1+)
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Database**: MySQL 8.0+ or MariaDB 10.3+
- **Memory**: 256MB+ PHP memory limit
- **Disk Space**: 2GB+ free space
- **Extensions**: mysqli, PDO, PDO_MySQL, GD, Zip, BCMath, cURL, JSON, MBString, OpenSSL, Tokenizer, XML

### **Optional but Recommended:**
- **FFmpeg**: For video processing applications
- **Redis**: For caching and sessions
- **SSL Certificate**: For secure communications

## 🔧 Configuration

### **Application Setup**

Edit `config/apps.json` to add your applications:

```json
{
  "your_app": {
    "name": "Your Application",
    "description": "Application description",
    "version": "1.0.0",
    "license_server": "https://api.yourapp.com",
    "release_server": "https://releases.yourapp.com",
    "requirements": {
      "php": "^8.0",
      "extensions": ["mysqli", "gd", "zip"],
      "database": "mysql",
      "memory_limit": "256M",
      "disk_space": "1GB"
    }
  }
}
```

### **Server Configuration**

Edit `config/servers.json` to configure endpoints:

```json
{
  "license_servers": {
    "your_app": "https://api.yourapp.com"
  },
  "release_servers": {
    "your_app": "https://releases.yourapp.com"
  },
  "endpoints": {
    "validate_license": "/api/validate",
    "get_download_token": "/api/download/token",
    "get_download_url": "/api/download/url"
  }
}
```

## 🌐 Web Server Configuration

### **Apache Configuration**

Add to `.htaccess` or virtual host:

```apache
<Directory "/path/to/client_apps_installer">
    AllowOverride All
    Require all granted
</Directory>

# Ensure installer works properly
<Files "install.php">
    AcceptPathInfo On
</Files>
```

### **Nginx Configuration**

```nginx
location /client_apps_installer/ {
    root /var/www/html;
    index install.php;
    try_files $uri $uri/ /client_apps_installer/install.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index install.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## 🔐 Security Considerations

### **File Permissions**

```bash
# Set secure permissions
chmod 755 client_apps_installer/
chmod 644 client_apps_installer/*.php
chmod 644 client_apps_installer/*.json
chmod 755 client_apps_installer/storage/
chmod 755 client_apps_installer/storage/*/
chmod 600 client_apps_installer/storage/*/*  # License files
```

### **Web Server Security**

1. **Disable directory listing**:
   ```apache
   Options -Indexes
   ```

2. **Hide sensitive files**:
   ```apache
   <FilesMatch "\.(json|log|tmp)$">
       Require all denied
   </FilesMatch>
   ```

3. **Use HTTPS** for all communications

### **Post-Installation Security**

After installation, the installer creates `storage/install.lock` which prevents re-installation. You should:

1. **Delete the installer directory** (optional but recommended)
2. **Set proper file ownership**:
   ```bash
   chown -R www-data:www-data /path/to/application/storage/
   ```
3. **Configure firewall rules**
4. **Enable automatic security updates**

## 📦 Application Packaging

### **Creating Application ZIPs**

1. **Package your Laravel application**:
   ```bash
   cd /path/to/your/app
   zip -r your-app-v1.0.0.zip . -x "storage/*" ".env*" "node_modules/*" ".git/*"
   ```

2. **Calculate SHA256 checksum**:
   ```bash
   sha256sum your-app-v1.0.0.zip
   ```

3. **Upload to release server** with checksum verification

### **Version Management**

- Use semantic versioning (1.0.0, 1.0.1, 1.1.0, 2.0.0)
- Maintain backward compatibility when possible
- Include migration scripts for database updates
- Test thoroughly before release

## 🔄 Update Process

### **Automatic Updates**

The installer automatically checks for updates when accessed. Updates include:

1. **Backup creation** - Full application backup
2. **Download verification** - SHA256 integrity check
3. **Atomic installation** - Prevent partial updates
4. **Migration execution** - Database schema updates
5. **Cleanup** - Remove temporary files

### **Manual Updates**

Users can also update manually by accessing the installer.

## 🐛 Troubleshooting

### **Common Issues**

1. **404 Errors**: Check web server configuration and file permissions
2. **Permission Denied**: Ensure PHP can write to storage directories
3. **Download Failures**: Verify license server connectivity and SSL certificates
4. **Database Errors**: Check database credentials and server status

### **Debug Mode**

Enable debug mode by setting in `install.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### **Log Files**

Check these locations for errors:
- Web server error logs
- PHP error logs
- Application logs (after installation)

## 📞 Support

### **For Users**
- Check the system requirements
- Verify file permissions
- Contact your application provider

### **For Developers**
- Review server configuration
- Check API endpoints
- Verify license server connectivity

---

**Universal Installer v1.0.0**  
*Secure Laravel Application Distribution*
