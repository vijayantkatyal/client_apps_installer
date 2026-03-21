# Universal Laravel Installer

A secure, remote download installer for Laravel applications with automatic update capabilities.

## 🚀 Features

### **For Developers**
- **Single Installer** - One codebase for multiple applications
- **Secure Distribution** - Actual application code never exposed publicly
- **License-Based Downloads** - Only valid licenses can download applications
- **Automatic Updates** - Built-in update system with rollback capability
- **Multi-App Support** - Configure unlimited Laravel applications

### **For Users**
- **Professional Installation** - 6-step wizard with progress tracking
- **Automatic Setup** - Downloads and installs applications automatically
- **One-Click Updates** - Secure updates with automatic backups
- **System Validation** - Checks server requirements before installation

## 📁 File Structure

```
universal-installer/
├── install.php                 # Main installer entry point
├── config/
│   ├── apps.json              # Application definitions
│   └── servers.json           # Server endpoints and security
├── core/
│   ├── UniversalInstaller.php # Main installer controller
│   ├── RemoteDownloader.php   # Secure download manager
│   ├── AppUpdater.php         # Automatic update system
│   ├── SystemCheck.php        # Requirements validator
│   ├── LicenseValidator.php   # License validation
│   ├── DatabaseSetup.php      # Database configuration
│   └── InstallationProcess.php # Installation handler
├── views/
│   ├── layout.php             # Base template
│   ├── app_selection.php      # Application selection
│   ├── system_check.php       # Requirements validation
│   ├── license_validation.php # License input
│   ├── database_setup.php     # Database configuration
│   ├── download_progress.php  # Download interface
│   ├── installation_progress.php # Installation progress
│   ├── complete.php           # Success page
│   └── update_available.php   # Update management
└── assets/
    ├── css/
    │   └── install.css         # Installer styling
    └── js/
        └── install.js          # Frontend interactions
```

## 🔧 Installation

### **For End Users:**

1. **Upload** the entire `install/` directory to your web server
2. **Navigate** to `http://yourdomain.com/install/install.php`
3. **Select** your application from the available options
4. **Follow** the 6-step installation wizard
5. **Done!** Your application is installed and ready to use

### **For Developers:**

1. **Configure** your applications in `config/apps.json`
2. **Set up** license and release servers
3. **Upload** only the installer to customer servers
4. **Customers** download your actual application securely during installation

## ⚙️ Configuration

### **Application Configuration (config/apps.json)**

```json
{
  "your_app": {
    "name": "Your Application Name",
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
    },
    "post_install": {
      "migrations": true,
      "seeders": ["AdminSeeder"],
      "commands": ["key:generate", "storage:link"]
    }
  }
}
```

### **Server Configuration (config/servers.json)**

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
    "get_download_url": "/api/download/url",
    "check_updates": "/api/updates/check"
  }
}
```

## 🔐 Security Features

### **License Protection**
- **Domain Binding** - Applications locked to specific domains
- **Hardware Fingerprinting** - Prevents unauthorized transfers
- **Token-Based Downloads** - Time-limited secure download URLs
- **RSA-2048 Signatures** - Cryptographic verification of licenses

### **Download Security**
- **SHA256 Verification** - Prevents file tampering
- **SSL/TLS Required** - All communications encrypted
- **Integrity Checks** - Automatic verification of downloaded files
- **Secure Storage** - Encrypted license files

### **Update Security**
- **Automatic Backups** - Full application backup before updates
- **Rollback Capability** - Restore previous version if needed
- **Signature Verification** - Verify update authenticity
- **Atomic Updates** - Prevent partial installations

## 🚀 Installation Process

1. **Application Selection** - Choose which app to install
2. **System Check** - Validate server requirements
3. **License Validation** - Activate license key
4. **Database Setup** - Configure database connection
5. **Remote Download** - Securely download application files
6. **Installation** - Install dependencies and configure

## 🔄 Update Process

1. **Check Updates** - Automatically check for new versions
2. **Download Update** - Secure download with integrity verification
3. **Create Backup** - Full application backup
4. **Install Update** - Apply new files and run migrations
5. **Cleanup** - Remove temporary files and maintain system

## 📡 API Endpoints

### **License Server**

#### **Validate License**
```
POST /api/validate
{
  "license_key": "APP-XXXXX-XXXXX-XXXXX",
  "app": "your_app",
  "domain": "customer.com",
  "fingerprint": "server_hash"
}
```

#### **Get Download Token**
```
POST /api/download/token
{
  "license_key": "APP-XXXXX-XXXXX-XXXXX",
  "app": "your_app",
  "domain": "customer.com"
}
```

### **Release Server**

#### **Get Download URL**
```
POST /api/download/url
{
  "token": "jwt_token",
  "license_key": "APP-XXXXX-XXXXX-XXXXX",
  "app": "your_app",
  "php_version": "8.1"
}
```

#### **Check Updates**
```
POST /api/updates/check
{
  "license_key": "APP-XXXXX-XXXXX-XXXXX",
  "app": "your_app",
  "current_version": "1.0.0"
}
```

## 🎯 Use Cases

### **SaaS Companies**
- Distribute multiple Laravel applications securely
- Control access through license validation
- Automatic updates for all customers
- Analytics and usage tracking

### **Multi-Product Businesses**
- Single installer for all products
- Consistent installation experience
- Centralized license management
- Easy product addition

### **Enterprise Software**
- Secure on-premise deployments
- Domain-locked installations
- Automatic security updates
- Compliance and audit trails

## 🛠️ Development

### **Adding New Applications**

1. **Add to apps.json** - Configure application details
2. **Set up servers** - Configure license and release servers
3. **Create ZIP package** - Package your Laravel application
4. **Test installation** - Verify complete flow works

### **Customization**

- **Views** - Modify templates in `views/` directory
- **Styles** - Update CSS in `assets/css/`
- **Logic** - Extend classes in `core/` directory
- **Configuration** - Update JSON files in `config/`

## 📄 License

This installer is designed to distribute licensed Laravel applications securely. Each application should have its own license terms and conditions.

## 🤝 Support

For support and documentation:
- **Documentation**: Check inline comments and README files
- **Issues**: Report bugs and feature requests
- **Community**: Join our developer community

---

**Universal Installer v1.0.0**  
*Secure Laravel Application Distribution*
