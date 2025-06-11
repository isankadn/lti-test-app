<?php
/**
 * LTI 1.3 Platform Configuration
 * This PHP app acts as an LTI Platform that launches to Moodle
 */

// Auto-detect current domain and protocol
// Force HTTPS for ngrok deployment (LTI 1.3 requires HTTPS)
$protocol = 'https://';
$host = $_SERVER['HTTP_HOST'];
$currentDomain = $protocol . $host;

// LTI 1.3 Platform Configuration (this app)
define('PLATFORM_DOMAIN', $currentDomain);
define('PLATFORM_ISSUER', PLATFORM_DOMAIN);
define('PLATFORM_CLIENT_ID', 'php-lti-platform-client'); // Must match Moodle Client ID

// Moodle Tool Configuration (target) - LTI 1.3
define('MOODLE_TOOL_DOMAIN', 'https://twdemo.leaf.ederc.jp/moodle');
define('MOODLE_CLIENT_ID', 'php-lti-platform-client'); // Must match Client ID in Moodle
define('MOODLE_OIDC_LOGIN_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/login.php?id=f9eb37adaea447d0c1295833104a3616277b534cad049de9fcf9ace1ca12');
define('MOODLE_LAUNCH_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/launch.php');
define('MOODLE_JWKS_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/jwks.php');

// Platform endpoints
define('PLATFORM_AUTH_URL', PLATFORM_DOMAIN . '/auth.php');
define('PLATFORM_TOKEN_URL', PLATFORM_DOMAIN . '/token.php');
define('PLATFORM_JWKS_URL', PLATFORM_DOMAIN . '/jwks.php');

// Return URL for launch completion
define('PLATFORM_RETURN_URL', PLATFORM_DOMAIN . '/return.php');

// Default deployment ID - Must match Moodle deployment
define('DEFAULT_DEPLOYMENT_ID', 'Main Deployment');

// LTI Tool Configuration
define('MOODLE_PUBLISHED_TOOL_ID', '0fb3eb68-d9ea-4cc2-addb-9b9dd5f9f262'); // Published LTI tool ID from Moodle
define('JWT_KEY_ID', 'demo-key-1'); // JWT Key ID for signing
define('JWT_EXPIRY_HOURS', 1); // JWT token expiry in hours

// Demo User Configuration
define('DEMO_USER_NAME', 'Demo User');
define('DEMO_USER_GIVEN_NAME', 'Demo');
define('DEMO_USER_FAMILY_NAME', 'User');
define('DEMO_USER_EMAIL', 'demo.user@example.edu');
define('DEMO_USER_ROLE', 'learner');

// LTI Resource Configuration
define('LTI_RESOURCE_TITLE', 'Sample Learning Resource');
define('LTI_RESOURCE_DESCRIPTION', 'A sample learning resource launched from our PHP LTI Platform');

// LTI Context (Course) Configuration
define('LTI_CONTEXT_LABEL', 'CS101');
define('LTI_CONTEXT_TITLE', 'Introduction to Computer Science');

// Platform Information
define('PLATFORM_NAME', 'PHP LTI Platform');
define('PLATFORM_VERSION', '1.0');
define('PLATFORM_PRODUCT_FAMILY', 'php-lti-platform');

// Launch Presentation Configuration
define('LAUNCH_DOCUMENT_TARGET', 'iframe');
define('LAUNCH_HEIGHT', 800);
define('LAUNCH_WIDTH', 1200);

// Logging function
function logMessage($message, $data = null, $component = 'PLATFORM') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $component: $message";
    if ($data !== null) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_SLASHES);
    }
    $logEntry .= "\n";
    file_put_contents(__DIR__ . '/launch.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Generate random user data (simulating logged-in users)
function generateRandomUser() {
    $firstNames = ['Alice', 'Bob', 'Charlie', 'Diana', 'Edward', 'Fiona', 'George', 'Hannah', 'Ian', 'Julia'];
    $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
    $roles = ['learner', 'instructor', 'administrator'];

    $firstName = $firstNames[array_rand($firstNames)];
    $lastName = $lastNames[array_rand($lastNames)];
    $role = $roles[array_rand($roles)];

    return [
        'user_id' => 'user_' . bin2hex(random_bytes(8)),
        'name' => $firstName . ' ' . $lastName,
        'given_name' => $firstName,
        'family_name' => $lastName,
        'email' => strtolower($firstName . '.' . $lastName . '@example.edu'),
        'role' => $role,
        'roles' => [$role === 'learner' ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner' :
                   ($role === 'instructor' ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor' :
                    'http://purl.imsglobal.org/vocab/lis/v2/membership#Administrator')]
    ];
}

// Log that config was loaded
logMessage("Platform configuration loaded", [
    'platform_domain' => PLATFORM_DOMAIN,
    'moodle_tool_domain' => MOODLE_TOOL_DOMAIN,
    'platform_issuer' => PLATFORM_ISSUER
], 'CONFIG');
?>