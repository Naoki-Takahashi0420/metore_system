import fetch from 'node-fetch';

const BASE_URL = 'http://localhost:8004';

async function testMedicalRecordFlow() {
    console.log('=== Testing Medical Record Flow ===\n');

    // Test 1: Direct access to /stores (without from_medical_record)
    console.log('1. Direct access to /stores:');
    let response = await fetch(`${BASE_URL}/stores`, {
        headers: {
            'Cookie': ''
        }
    });
    const directCookie = response.headers.get('set-cookie');
    console.log('   Response status:', response.status);
    console.log('   Session cleared for new customers\n');

    // Test 2: Access from medical record
    console.log('2. Access from medical record (/stores?from_medical_record=1&customer_id=1):');
    response = await fetch(`${BASE_URL}/stores?from_medical_record=1&customer_id=1`, {
        headers: {
            'Cookie': ''
        }
    });
    const medicalCookie = response.headers.get('set-cookie');
    console.log('   Response status:', response.status);

    // Get session data by accessing category page
    console.log('\n3. Checking session after medical record access:');
    response = await fetch(`${BASE_URL}/reservation/category?store_id=1`, {
        headers: {
            'Cookie': medicalCookie || ''
        },
        redirect: 'manual'
    });
    console.log('   Response status:', response.status);

    console.log('\nTest complete! Check Laravel logs for detailed information.');
    console.log('Run: tail -n 50 storage/logs/laravel.log | grep CRITICAL');
}

testMedicalRecordFlow().catch(console.error);