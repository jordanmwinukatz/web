<?php
/**
 * bin/test.php
 * Minimalistic zero-dependency test runner.
 * 
 * Usage: php bin/test.php
 */

require_once __DIR__ . '/../config/env.php';
Env::load();

// Force testing environment
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

if (php_sapi_name() !== 'cli') {
    die("Tests must run via CLI.");
}

echo "\n🧪 Starting Test Suite\n";
echo str_repeat('=', 40) . "\n";

$testsDir = __DIR__ . '/../tests';
$files = scandir($testsDir);
$testFiles = array_filter($files, function($f) {
    return str_ends_with($f, 'Test.php');
});

$total = 0;
$passed = 0;
$failed = 0;

// Simple assertion helpers injected globally
function assertTrue($condition, $message = 'Assertion failed') {
    if (!$condition) throw new Exception($message);
}

function assertEquals($expected, $actual, $message = null) {
    if ($expected !== $actual) {
        $msg = $message ?? "Expected '{$expected}' but got '{$actual}'";
        throw new Exception($msg);
    }
}

foreach ($testFiles as $file) {
    require_once $testsDir . '/' . $file;
    $className = basename($file, '.php');
    
    if (class_exists($className)) {
        $instance = new $className();
        $methods = get_class_methods($instance);
        
        foreach ($methods as $method) {
            if (str_starts_with($method, 'test')) {
                $total++;
                
                try {
                    // Make testing cleaner by isolating output and preventing headers_sent errors
                    ob_start();
                    $instance->$method();
                    ob_end_clean();
                    
                    echo "⏳ {$className}::{$method} ... ✅ PASS\n";
                    $passed++;
                } catch (Exception $e) {
                    ob_end_clean();
                    echo "⏳ {$className}::{$method} ... ❌ FAIL\n";
                    echo "   └── " . $e->getMessage() . "\n";
                    $failed++;
                }
            }
        }
    }
}

echo str_repeat('=', 40) . "\n";
echo "📊 Results: {$passed} passed, {$failed} failed. Total tests: {$total}\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
