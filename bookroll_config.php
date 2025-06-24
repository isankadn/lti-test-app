<?php
/**
 * Bookroll LTI 1.3 Integration Configuration
 * This extends the main config.php with Bookroll-specific settings
 */

// Load main configuration
require_once __DIR__ . '/config.php';

// Bookroll Tool Configuration (Tool endpoints and identifiers)
define('BOOKROLL_TOOL_DOMAIN', BOOKROLL_TOOL_BASE_DOMAIN);
define('BOOKROLL_CLIENT_ID', 'bookroll-client-id');
define('BOOKROLL_CONSUMER_KEY', 'bookroll-consumer-key');

// Bookroll Tool Endpoints (where we send requests TO the tool)
define('BOOKROLL_OIDC_LOGIN_URL', BOOKROLL_TOOL_BASE_DOMAIN . '/oidc/login_initiations');
define('BOOKROLL_LAUNCH_URL', BOOKROLL_TOOL_BASE_DOMAIN . '/lti3');
define('BOOKROLL_DEPLOYMENT_ID', '2');

// Configuration Summary for Bookroll Setup
function getBookrollConfig() {
    return [
        'client_id' => BOOKROLL_CLIENT_ID,
        'consumer_key' => BOOKROLL_CONSUMER_KEY,
        'iss' => PLATFORM_ISSUER,
        'oidc_endpoint' => BOOKROLL_OIDC_LOGIN_URL,
        'jwks_endpoint' => PLATFORM_JWKS_URL,
        'oauth2_token_endpoint' => PLATFORM_TOKEN_URL,
        'oauth2_token_aud' => BOOKROLL_CLIENT_ID,
        'deployment_id' => BOOKROLL_DEPLOYMENT_ID,
        'tool_url' => BOOKROLL_LAUNCH_URL,
        'redirection_uris' => [BOOKROLL_LAUNCH_URL],
        'public_keyset_url' => PLATFORM_JWKS_URL
    ];
}

// Display configuration
function displayBookrollConfig() {
    $config = getBookrollConfig();

    echo "<h2>Bookroll LTI 1.3 Configuration</h2>";
    echo "<p>Use these values in your Bookroll LTI 1.3 connection form:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th style='padding: 10px; background: #f0f0f0;'>Field</th><th style='padding: 10px; background: #f0f0f0;'>Value</th></tr>";

    foreach ($config as $field => $value) {
        echo "<tr>";
        echo "<td style='padding: 10px; font-weight: bold;'>" . ucwords(str_replace('_', ' ', $field)) . "</td>";
        echo "<td style='padding: 10px; font-family: monospace;'>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    echo "<br><h3>Additional Notes:</h3>";
    echo "<ul>";
    echo "<li><strong>Private Key:</strong> Use the same private key from your demo_private_key.pem file</li>";
    echo "<li><strong>Public Key:</strong> Extract from your private key using get_public_key.php</li>";
    echo "<li><strong>Default Directory:</strong> Use whatever directory structure Bookroll provides</li>";
    echo "</ul>";
}

// Log configuration for debugging
logMessage("Bookroll configuration loaded", [
    'bookroll_domain' => BOOKROLL_TOOL_DOMAIN,
    'platform_issuer' => PLATFORM_ISSUER,
    'platform_jwks' => PLATFORM_JWKS_URL
], 'BOOKROLL_CONFIG');
?>