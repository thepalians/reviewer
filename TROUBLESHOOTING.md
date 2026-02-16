# Troubleshooting Guide for ReviewFlow

## HTTP 500 Errors

### Common Causes and Solutions

#### 1. Database Connection Issues

**Symptoms:**
- HTTP 500 error on any page
- "Service Temporarily Unavailable" error page displayed
- Error log shows: `Database Connection Failed: SQLSTATE[HY000] [2002]`

**Solutions:**

1. **Check if MySQL is running:**
   ```bash
   sudo systemctl status mysql
   # or
   sudo service mysql status
   ```

2. **Start MySQL if it's stopped:**
   ```bash
   sudo systemctl start mysql
   # or
   sudo service mysql start
   ```

3. **Verify database credentials in `/includes/config.php`:**
   - DB_HOST (default: localhost)
   - DB_USER (default: reviewflow_user)
   - DB_PASS
   - DB_NAME (default: reviewflow)

4. **Test database connection:**
   ```bash
   mysql -u reviewflow_user -p -h localhost
   ```

5. **Check error logs:**
   ```bash
   tail -f /home/runner/work/reviewer/reviewer/logs/error.log
   ```

#### 2. Missing PHP Extensions

**Symptoms:**
- Fatal error: Call to undefined function
- HTTP 500 error

**Solutions:**

1. **Check required PHP extensions:**
   ```bash
   php -m | grep -E 'pdo|mysql|mbstring|json'
   ```

2. **Install missing extensions (Ubuntu/Debian):**
   ```bash
   sudo apt-get install php-mysql php-mbstring php-json php-xml
   ```

#### 3. File Permission Issues

**Symptoms:**
- Cannot write to error log
- Upload errors
- Cache errors

**Solutions:**

1. **Set proper permissions:**
   ```bash
   chmod 755 /home/runner/work/reviewer/reviewer
   chmod 777 /home/runner/work/reviewer/reviewer/logs
   chmod 777 /home/runner/work/reviewer/reviewer/uploads
   chmod 777 /home/runner/work/reviewer/reviewer/cache
   ```

2. **Set proper ownership:**
   ```bash
   sudo chown -R www-data:www-data /home/runner/work/reviewer/reviewer
   # or for Nginx:
   sudo chown -R nginx:nginx /home/runner/work/reviewer/reviewer
   ```

### Debug Mode

Enable debug mode in `/includes/config.php` to see detailed error messages:

```php
const DEBUG = true;
```

**⚠️ Important:** Always set `DEBUG = false` in production!

### Error Logs

Error logs are stored in:
- `/home/runner/work/reviewer/reviewer/logs/error.log`
- System PHP error log (location varies by system)

View recent errors:
```bash
tail -n 50 /home/runner/work/reviewer/reviewer/logs/error.log
```

## Session Issues

**Symptoms:**
- Logged out unexpectedly
- "Please login" message after successful login

**Solutions:**

1. **Check session directory permissions:**
   ```bash
   ls -la /var/lib/php/sessions
   ```

2. **Verify session settings in `config.php`:**
   - SESSION_TIMEOUT (default: 3600 seconds)
   - Cookie domain and path settings

3. **Clear sessions:**
   ```bash
   sudo rm /var/lib/php/sessions/sess_*
   ```

## Performance Issues

**Symptoms:**
- Slow page loads
- Timeouts

**Solutions:**

1. **Check database performance:**
   ```sql
   SHOW PROCESSLIST;
   SHOW STATUS LIKE '%slow%';
   ```

2. **Enable query caching**
3. **Optimize database tables:**
   ```sql
   OPTIMIZE TABLE tasks, orders, users;
   ```

## Contact Support

If issues persist after trying these solutions:
1. Check error logs
2. Enable debug mode temporarily
3. Contact the development team with:
   - Error message from logs
   - Steps to reproduce
   - PHP version and system info
