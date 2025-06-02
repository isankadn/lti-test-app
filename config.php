<?php
/**
 * LTI 1.3 Platform Configuration
 * This PHP app acts as an LTI Platform that launches to Saltire Tool
 */

// Auto-detect current domain and protocol
// Force HTTPS for ngrok deployment (LTI 1.3 requires HTTPS)
$protocol = 'https://';
$host = $_SERVER['HTTP_HOST'];
$currentDomain = $protocol . $host;

// LTI 1.3 Platform Configuration (this app)
define('PLATFORM_DOMAIN', $currentDomain);
define('PLATFORM_ISSUER', PLATFORM_DOMAIN);
define('PLATFORM_CLIENT_ID', 'php-lti-platform-' . md5(PLATFORM_DOMAIN));

// Saltire Tool Configuration (target)
define('SALTIRE_TOOL_DOMAIN', 'https://saltire.lti.app');
define('SALTIRE_CLIENT_ID', 'saltire-tool');
define('SALTIRE_OIDC_LOGIN_URL', SALTIRE_TOOL_DOMAIN . '/tool/oidc_login');
define('SALTIRE_LAUNCH_URL', SALTIRE_TOOL_DOMAIN . '/tool/launch');
define('SALTIRE_JWKS_URL', SALTIRE_TOOL_DOMAIN . '/tool/jwks');

// Platform endpoints
define('PLATFORM_AUTH_URL', PLATFORM_DOMAIN . '/auth.php');
define('PLATFORM_TOKEN_URL', PLATFORM_DOMAIN . '/token.php');
define('PLATFORM_JWKS_URL', PLATFORM_DOMAIN . '/jwks.php');

// Return URL for launch completion
define('PLATFORM_RETURN_URL', PLATFORM_DOMAIN . '/return.php');

// Default deployment ID
define('DEFAULT_DEPLOYMENT_ID', 'deployment-' . md5(PLATFORM_DOMAIN));

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
    'saltire_tool_domain' => SALTIRE_TOOL_DOMAIN,
    'platform_issuer' => PLATFORM_ISSUER
], 'CONFIG');
?>