#!/bin/bash

# Comprehensive Livewire Fix Deployment and Testing Script
# This script deploys the fix and verifies it works with automated tests

set -e # Exit on any error

echo "========================================="
echo "LIVEWIRE FIX DEPLOYMENT AND TESTING"
echo "========================================="

# Check if EC2_KEY is set
if [ -z "$EC2_KEY" ]; then
    echo "❌ Error: EC2_KEY environment variable not set"
    echo "Please set it with: export EC2_KEY='your-private-key-content'"
    exit 1
fi

echo "✓ EC2_KEY environment variable is set"

# Check if Node.js and npm are available for Playwright
if ! command -v node &> /dev/null || ! command -v npm &> /dev/null; then
    echo "❌ Error: Node.js and npm are required for running Playwright tests"
    echo "Please install Node.js from https://nodejs.org/"
    exit 1
fi

echo "✓ Node.js and npm are available"

# Check if Playwright is installed
if [ ! -d "node_modules/@playwright" ]; then
    echo "Installing Playwright dependencies..."
    npm install @playwright/test
    npx playwright install chromium
fi

echo "✓ Playwright dependencies ready"

echo ""
echo "=== STEP 1: DEPLOYING LIVEWIRE FIX ==="
echo "Running comprehensive Livewire fix script..."

# Run the fix script
./scripts/fix-production-livewire.sh

if [ $? -ne 0 ]; then
    echo "❌ Fix deployment failed"
    exit 1
fi

echo "✓ Livewire fix deployed successfully"

echo ""
echo "=== STEP 2: WAITING FOR SERVICES TO STABILIZE ==="
echo "Waiting 10 seconds for services to fully restart..."
sleep 10

echo ""
echo "=== STEP 3: RUNNING PRODUCTION TESTS ==="
echo "Testing the fixed Livewire functionality..."

# Set environment variable for production testing
export PRODUCTION_TEST=true

# Run Playwright tests against production
npx playwright test livewire-login.spec.js --reporter=list

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo "========================================="
    echo "🎉 SUCCESS! ALL TESTS PASSED"
    echo "========================================="
    echo ""
    echo "✅ FIXED ISSUES:"
    echo "   • Livewire 500 error resolved"
    echo "   • Login page loads correctly"
    echo "   • CSRF tokens working"
    echo "   • Session management fixed" 
    echo "   • Authentication flow working"
    echo ""
    echo "🔗 LOGIN DETAILS:"
    echo "   URL: http://13.115.38.179/admin/login"
    echo "   Email: admin@xsyumeno.com"
    echo "   Password: password"
    echo ""
    echo "📊 View detailed test results:"
    echo "   npx playwright show-report"
    
else
    echo "========================================="
    echo "❌ TESTS FAILED"
    echo "========================================="
    echo ""
    echo "Some tests failed. This could indicate:"
    echo "• The fix didn't fully resolve all issues"
    echo "• Network connectivity problems"
    echo "• Server configuration issues"
    echo ""
    echo "📋 TROUBLESHOOTING STEPS:"
    echo "1. Check server logs:"
    echo "   ssh -i key.pem ubuntu@13.115.38.179 'sudo tail -f /var/www/html/current/storage/logs/laravel.log'"
    echo ""
    echo "2. Check service status:"
    echo "   ssh -i key.pem ubuntu@13.115.38.179 'sudo systemctl status nginx php8.3-fpm'"
    echo ""
    echo "3. Test manually:"
    echo "   Open http://13.115.38.179/admin/login in browser"
    echo ""
    echo "4. View test results:"
    echo "   npx playwright show-report"
    echo ""
    echo "5. Re-run fix if needed:"
    echo "   ./scripts/fix-production-livewire.sh"
fi

echo ""
echo "=== STEP 4: GENERATING SUMMARY REPORT ==="

# Create a summary report
cat > livewire-fix-report.md << EOF
# Livewire 500 Error Fix Report

## Deployment Date
$(date)

## Issues Addressed
1. **Missing APP_KEY**: Fixed empty APP_KEY in production environment
2. **Malformed .env.production**: Cleaned up corrupted environment file  
3. **Session Configuration**: Properly configured database session driver
4. **Cache Configuration**: Fixed cache driver settings
5. **File Permissions**: Corrected storage directory permissions
6. **Missing Tables**: Ensured sessions and cache tables exist
7. **Service Configuration**: Restarted PHP-FPM and Nginx services

## Test Results
- Test Exit Code: $TEST_EXIT_CODE
- Test Status: $([ $TEST_EXIT_CODE -eq 0 ] && echo "✅ PASSED" || echo "❌ FAILED")

## Production Login Details
- URL: http://13.115.38.179/admin/login
- Email: admin@xsyumeno.com  
- Password: password

## Next Steps
$([ $TEST_EXIT_CODE -eq 0 ] && echo "✅ Fix successful - system is ready for use" || echo "❌ Additional troubleshooting needed - check logs and test results")

## Files Modified
- .env (production environment)
- Storage permissions
- Cache and session directories
- Service configurations

EOF

echo "📄 Summary report created: livewire-fix-report.md"

echo ""
echo "========================================="
echo "DEPLOYMENT AND TESTING COMPLETE"
echo "========================================="

exit $TEST_EXIT_CODE