# Docker Testing Environment

## 🐳 Quick Start

### **1. Build and Start Services**

```bash
# Build and start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### **2. Access Points**

Once running, you can access:

- **Universal Installer**: http://localhost:8000/installer/install.php
- **phpMyAdmin**: http://localhost:8080 (root/root123456)
- **Mock License Server**: http://localhost:9000
- **Mock Release Server**: http://localhost:9001

### **3. Database Credentials**

For testing the installer:

- **Host**: mysql (or localhost:3306)
- **Database**: test_database
- **Username**: test_user
- **Password**: test123456
- **Root Password**: root123456

## 🧪 Testing Scenarios

### **Scenario 1: Fresh Installation**

1. **Access**: http://localhost:8000/installer/install.php
2. **Select**: VidPowr (or any configured app)
3. **License**: Use test license: `VIDPOWR-TEST1-12345-ABCDE`
4. **Database**: Use the credentials above
5. **Download**: Should work with mock server

### **Scenario 2: Update Testing**

1. **Install** an application first
2. **Modify** the version in `config/apps.json`
3. **Access** installer again
4. **Should show** update available

### **Scenario 3: Error Testing**

1. **Invalid license**: Try wrong format
2. **Database errors**: Use wrong credentials
3. **Network issues**: Stop mock servers

## 🔧 Configuration

### **Mock Servers**

The mock servers simulate:
- **License validation** at port 9000
- **File downloads** at port 9001
- **Update checking** endpoints

### **Environment Variables**

You can customize the test environment:

```yaml
environment:
  - PHP_MEMORY_LIMIT=512M
  - UPLOAD_MAX_FILESIZE=100M
  - MYSQL_ROOT_PASSWORD=custom_password
```

## 🐛 Debugging

### **View Logs**

```bash
# Web server logs
docker-compose logs web

# MySQL logs
docker-compose logs mysql

# Mock server logs
docker-compose logs license_server
```

### **Access Containers**

```bash
# Access web container
docker-compose exec web bash

# Access MySQL
docker-compose exec mysql mysql -u root -p

# Access database directly
mysql -h localhost -P 3306 -u test_user -p test123456 test_database
```

### **Common Issues**

1. **Port conflicts**: Change ports in docker-compose.yml
2. **Permission errors**: Check file permissions in container
3. **Network issues**: Verify containers can communicate

## 📋 Test Checklist

- [ ] Installer loads correctly
- [ ] App selection works
- [ ] System requirements check passes
- [ ] License validation works with mock server
- [ ] Database connection test succeeds
- [ ] Remote download works (mock)
- [ ] Installation completes successfully
- [ ] Update checking works
- [ ] Error handling works correctly

## 🔄 Development Workflow

### **Making Changes**

1. **Edit files** in the `client_apps_installer/` directory
2. **Restart web container**: `docker-compose restart web`
3. **Test changes** immediately

### **Adding New Mock Endpoints**

Edit `mock-server/index.php` to add new API endpoints for testing.

### **Database Schema Testing**

Use phpMyAdmin at http://localhost:8080 to inspect database changes after installation.

## 🚀 Production Considerations

This Docker environment is for **testing only**. For production:

1. **Use official images** with security updates
2. **Configure proper SSL/TLS**
3. **Set up environment variables**
4. **Use external databases**
5. **Implement proper logging**
6. **Set up monitoring**

---

**Testing Environment v1.0.0**  
*Universal Installer Docker Testing*
