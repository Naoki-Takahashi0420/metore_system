# Livewire 500 Error Fix

## Problem Description
The production environment at `http://13.115.38.179/admin/login` was experiencing a critical Livewire 500 error when attempting to login. The Livewire POST request to `/livewire/update` was returning HTTP 500 errors, preventing users from logging in despite correct credentials.

## Root Cause Analysis
After thorough analysis, the following issues were identified:

### 1. Missing APP_KEY
- The `.env.production` file had an empty `APP_KEY=` value
- Laravel requires a valid APP_KEY for encryption and CSRF token generation
- Without this key, Livewire requests fail due to encryption errors

### 2. Corrupted Environment File
- Line 79 in `.env.production` contained malformed data: `FILAMENT_FILESYSTEM_DISK=publicAWS_ACCESS_KEY_ID=（既存のキー）`
- This corruption caused environment parsing issues

### 3. Session Configuration Issues
- Session driver was set to `file` but file permissions were not properly configured
- Storage directories for sessions were not writable by www-data user

### 4. Cache Configuration Problems
- Cache driver configuration inconsistencies
- Missing cache tables or improper cache storage permissions

### 5. File Permissions
- Storage directories (`storage/framework/sessions`, `storage/framework/cache`) lacked proper write permissions
- Bootstrap cache directory had incorrect ownership

## Solution Implemented

### Comprehensive Fix Script
Created `/scripts/fix-production-livewire.sh` which addresses all identified issues:

1. **Environment Configuration**
   - Creates clean, properly formatted `.env` file
   - Sets valid `APP_KEY` for encryption
   - Configures session driver to use database
   - Fixes all configuration variables

2. **Database Tables**
   - Verifies existence of `sessions` and `cache` tables
   - Creates missing tables if needed
   - Ensures proper table structure

3. **File Permissions**
   - Sets correct ownership (`www-data:www-data`) for all Laravel directories
   - Sets proper permissions (775) for storage and cache directories
   - Creates missing storage subdirectories

4. **Cache Management**
   - Clears all existing caches that might contain corrupted data
   - Rebuilds Laravel configuration cache
   - Rebuilds route and view caches
   - Optimizes application for production

5. **Service Restart**
   - Restarts PHP-FPM and Nginx services
   - Ensures all configuration changes are loaded

### Testing Framework
Created comprehensive Playwright tests (`/tests/livewire-login.spec.js`) to verify:

- Login page loads correctly
- CSRF tokens are available
- Livewire updates work without 500 errors
- Authentication flow completes successfully
- Session persistence works
- AJAX requests are handled properly

## Usage Instructions

### Prerequisites
```bash
# Set EC2 private key as environment variable
export EC2_KEY='your-private-key-content'

# Ensure Node.js and npm are installed for testing
node --version
npm --version
```

### Deploy Fix Only
```bash
./scripts/fix-production-livewire.sh
```

### Deploy Fix and Run Tests
```bash
./scripts/deploy-and-test-fix.sh
```

### Run Tests Only (after fix is deployed)
```bash
export PRODUCTION_TEST=true
npx playwright test livewire-login.spec.js --reporter=list
```

## Login Credentials
After fix deployment:
- **URL**: http://13.115.38.179/admin/login
- **Email**: admin@xsyumeno.com
- **Password**: password

## Verification Steps

### Manual Testing
1. Open http://13.115.38.179/admin/login
2. Fill in login credentials
3. Click submit button
4. Should redirect to admin dashboard without errors

### Automated Testing
1. Run the test suite: `npm test`
2. Check test results and screenshots
3. Review generated test report: `npx playwright show-report`

## Troubleshooting

### If Login Still Fails
1. **Check Laravel Logs**:
   ```bash
   ssh -i key.pem ubuntu@13.115.38.179
   sudo tail -f /var/www/html/current/storage/logs/laravel.log
   ```

2. **Verify Services Status**:
   ```bash
   sudo systemctl status nginx php8.3-fpm
   ```

3. **Check Database Connection**:
   ```bash
   sudo -u www-data php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connected';"
   ```

4. **Re-run Fix Script**:
   ```bash
   ./scripts/fix-production-livewire.sh
   ```

### Common Issues and Solutions

#### 500 Error Still Occurs
- Check APP_KEY is properly set in `.env`
- Verify session table exists in database
- Check storage directory permissions

#### CSRF Token Errors
- Clear application cache: `php artisan cache:clear`
- Verify APP_KEY is consistent
- Check session configuration

#### Permission Denied Errors
- Run permission fix: `sudo chown -R www-data:www-data storage bootstrap/cache`
- Set proper permissions: `sudo chmod -R 775 storage bootstrap/cache`

## Technical Details

### Environment Variables Fixed
```env
APP_KEY=base64:Gjj1AmoxQPLReuwOG6jDOFfcN2m6Gk0IwCDzk4YYeIo=
SESSION_DRIVER=database
SESSION_ENCRYPT=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
```

### Database Tables Verified
- `sessions` - For database session storage
- `cache` - For database cache storage  
- `cache_locks` - For cache locking mechanism
- `users` - For authentication

### File Permissions Set
- `/storage` - 775 with www-data:www-data ownership
- `/bootstrap/cache` - 775 with www-data:www-data ownership
- `.env` - 644 with www-data:www-data ownership

## Success Criteria
- ✅ Login page loads without errors
- ✅ Form submission doesn't trigger 500 errors
- ✅ Authentication redirects to admin dashboard
- ✅ Session persists across page loads
- ✅ All Playwright tests pass

## Maintenance
This fix should be stable for production use. However, future deployments should:
1. Preserve the corrected `.env` file
2. Maintain proper file permissions
3. Ensure database tables remain intact
4. Keep services properly configured

## Files Created/Modified
- `/scripts/fix-production-livewire.sh` - Main fix deployment script
- `/scripts/deploy-and-test-fix.sh` - Combined deployment and testing script  
- `/tests/livewire-login.spec.js` - Playwright test suite
- `playwright.config.js` - Updated for production testing
- `.env` - Fixed production environment variables (on server)
- `LIVEWIRE_FIX_README.md` - This documentation