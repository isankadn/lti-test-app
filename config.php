<?php
/**
 * LTI 1.3 Platform Configuration
 * This PHP app acts as an LTI Platform that launches to Moodle
 */

// Load demo configuration first
require_once __DIR__ . '/demo_config.php';

// Platform domain detection (with fallback from demo config)
$host = $_SERVER['HTTP_HOST'] ?? DEFAULT_PLATFORM_HOST;
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'https'; // Force HTTPS for LTI
define('PLATFORM_DOMAIN', $protocol . '://' . $host);
define('PLATFORM_ISSUER', PLATFORM_DOMAIN);

// LTI 1.3 Platform Configuration (this app)
define('PLATFORM_CLIENT_ID', 'php-lti-platform-client'); // Must match Moodle Client ID

// Moodle Tool Configuration (target) - LTI 1.3
define('MOODLE_TOOL_DOMAIN', MOODLE_TOOL_BASE_DOMAIN);
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
define('JWT_KEY_ID', 'platform-key-1'); // JWT Key ID for signing
define('JWT_EXPIRY_HOURS', 1); // JWT token expiry in hours

// Demo User Configuration (now using demo config)
$demoInstructor = getDemoInstructor();
define('DEMO_USER_NAME', $demoInstructor['name']);
define('DEMO_USER_GIVEN_NAME', $demoInstructor['given_name']);
define('DEMO_USER_FAMILY_NAME', $demoInstructor['family_name']);
define('DEMO_USER_EMAIL', $demoInstructor['email']);
define('DEMO_USER_ROLE', $demoInstructor['type']);

// LTI Resource Configuration
define('LTI_RESOURCE_TITLE', 'Demo LTI Resource');
define('LTI_RESOURCE_DESCRIPTION', 'A demonstration LTI 1.3 resource launch');

// LTI Context (Course) Configuration
define('LTI_CONTEXT_LABEL', DEFAULT_CONTEXT_LABEL);
define('LTI_CONTEXT_TITLE', DEFAULT_CONTEXT_TITLE);

// Platform Information
define('PLATFORM_NAME', DEMO_PLATFORM_NAME);
define('PLATFORM_VERSION', DEMO_PLATFORM_VERSION);
define('PLATFORM_PRODUCT_FAMILY', DEMO_PLATFORM_PRODUCT_FAMILY);

// Launch Presentation Configuration
define('LAUNCH_DOCUMENT_TARGET', DEMO_LAUNCH_DOCUMENT_TARGET);
define('LAUNCH_HEIGHT', DEMO_LAUNCH_HEIGHT);
define('LAUNCH_WIDTH', DEMO_LAUNCH_WIDTH);

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
        'email' => strtolower($firstName . '.' . $lastName . '@' . DEMO_EMAIL_DOMAIN),
        'role' => $role,
        'roles' => [$role === 'learner' ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner' :
                   ($role === 'instructor' ? 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor' :
                    'http://purl.imsglobal.org/vocab/lis/v2/membership#Administrator')]
    ];
}

// Log that config was loaded
/**
 * Configure secure session handling for LTI cross-origin requests
 */
function initializeLTISession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 3600,
            'path' => '/',
            'domain' => '', // Keep empty for current domain
            'secure' => true, // Required for HTTPS
            'httponly' => true, // Prevent XSS
            'samesite' => 'None' // Allow cross-site cookies for LTI
        ]);
        session_start();
    }
}

/**
 * Create a Rails-compatible signed cookie value
 * Rails format: base64(value)--hmac_signature
 */
function createRailsSignedCookie($value, $secret_key = 'lti_platform_secret_key_2024') {
    // Base64 encode the value (Rails style)
    $encoded_value = base64_encode($value);

    // Create HMAC signature (Rails uses SHA1 for cookie signatures)
    $signature = hash_hmac('sha1', $encoded_value, $secret_key);

    // Combine in Rails format: encoded_value--signature
    return $encoded_value . '--' . $signature;
}

/**
 * Set a Rails-compatible signed cookie
 */
function setRailsSignedCookie($name, $value, $options = []) {
    $signed_value = createRailsSignedCookie($value);

    $default_options = [
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => false,
        'samesite' => 'None'
    ];

    $cookie_options = array_merge($default_options, $options);

    return setcookie($name, $signed_value, $cookie_options);
}

logMessage("Platform configuration loaded", [
    'platform_domain' => PLATFORM_DOMAIN,
    'moodle_tool_domain' => MOODLE_TOOL_DOMAIN,
    'platform_issuer' => PLATFORM_ISSUER
], 'CONFIG');
?>