<?php
/**
 * Analysis LTI 1.3 Tool Configuration
 *
 * Analysis is an LTI 1.3 TOOL that we (the Platform) connect to.
 * This file contains the configuration for connecting to the Analysis tool.
 */

// Load main configuration
require_once __DIR__ . '/config.php';

// Analysis Tool Configuration (Tool endpoints and identifiers)
define('ANALYSIS_TOOL_DOMAIN', ANALYSIS_TOOL_BASE_DOMAIN);
define('ANALYSIS_CLIENT_ID', 'LQJKIL4wvUhVbWvFG');
define('ANALYSIS_CONSUMER_KEY', '5a44e433-cfcf-497e-8365-2e4115a5a291');

// Analysis Tool Endpoints (where we send requests TO the tool)
define('ANALYSIS_OIDC_LOGIN_URL', ANALYSIS_TOOL_BASE_DOMAIN . '/lti/login_initiations');
define('ANALYSIS_LAUNCH_URL', ANALYSIS_TOOL_BASE_DOMAIN . '/lti/launches');

// Analysis Tool Configuration
define('ANALYSIS_DEPLOYMENT_ID', '10');
define('ANALYSIS_KEYSET_URL', ANALYSIS_TOOL_BASE_DOMAIN . '/lti/tools/2/jwks.json');

// Configuration Summary for Analysis Tool Integration
function getAnalysisToolConfig() {
    return [
        // Tool Information
        'tool_name' => 'Analysis Tool',
        'tool_domain' => ANALYSIS_TOOL_DOMAIN,
        'client_id' => ANALYSIS_CLIENT_ID,
        'consumer_key' => ANALYSIS_CONSUMER_KEY,
        'deployment_id' => ANALYSIS_DEPLOYMENT_ID,

        // Tool Endpoints (where we send requests TO the tool)
        'login_initiation_url' => ANALYSIS_OIDC_LOGIN_URL,  // Step 1: We POST here to initiate
        'launch_url' => ANALYSIS_LAUNCH_URL,                // Step 4: We POST JWT here to launch
        'tool_keyset_url' => ANALYSIS_KEYSET_URL,           // Tool's public keys

        // Platform Endpoints (what we configure IN the tool)
        'platform_issuer' => PLATFORM_ISSUER,              // Our platform issuer
        'platform_oidc_auth_url' => PLATFORM_AUTH_URL,     // Step 2: Tool redirects here
        'platform_oauth2_token_url' => PLATFORM_TOKEN_URL, // For service calls
        'platform_jwks_url' => PLATFORM_JWKS_URL,          // Our public keys

        // Platform Services (what we provide TO the tool)
        'nrps_memberships_url' => PLATFORM_DOMAIN . '/nrps_memberships.php',
        'ags_lineitems_url' => PLATFORM_DOMAIN . '/ags/lineitems',
        'return_url' => PLATFORM_RETURN_URL
    ];
}

// Display configuration for manual tool setup
function displayAnalysisToolSetup() {
    $config = getAnalysisToolConfig();

    echo "<h2>Analysis Tool Integration Configuration</h2>";
    echo "<p>The Analysis application is an LTI 1.3 Tool. We are the Platform connecting to it.</p>";

    echo "<h3>📋 Configuration to set in Analysis Tool</h3>";
    echo "<p>When registering our platform in the Analysis tool, use these values:</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th style='padding: 10px; background: #f0f0f0;'>Field</th><th style='padding: 10px; background: #f0f0f0;'>Value</th></tr>";

    $toolConfig = [
        'Platform Issuer (iss)' => $config['platform_issuer'],
        'Client ID' => $config['client_id'],
        'Deployment ID' => $config['deployment_id'],
        'OIDC Auth URL' => $config['platform_oidc_auth_url'],
        'OAuth2 Token URL' => $config['platform_oauth2_token_url'],
        'Platform JWKS URL' => $config['platform_jwks_url']
    ];

    foreach ($toolConfig as $field => $value) {
        echo "<tr>";
        echo "<td style='padding: 10px; font-weight: bold;'>" . htmlspecialchars($field) . "</td>";
        echo "<td style='padding: 10px; font-family: monospace;'>" . htmlspecialchars($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>🔗 Analysis Tool Endpoints (used by our platform)</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th style='padding: 10px; background: #e3f2fd;'>Endpoint</th><th style='padding: 10px; background: #e3f2fd;'>URL</th></tr>";

    $endpoints = [
        'Login Initiation' => $config['login_initiation_url'],
        'Launch URL' => $config['launch_url'],
        'Tool JWKS' => $config['tool_keyset_url']
    ];

    foreach ($endpoints as $endpoint => $url) {
        echo "<tr>";
        echo "<td style='padding: 10px; font-weight: bold;'>" . htmlspecialchars($endpoint) . "</td>";
        echo "<td style='padding: 10px; font-family: monospace;'>" . htmlspecialchars($url) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<br><h3>🔄 LTI 1.3 Launch Flow</h3>";
    echo "<ol>";
    echo "<li><strong>Platform initiates:</strong> We POST to " . htmlspecialchars($config['login_initiation_url']) . "</li>";
    echo "<li><strong>Tool redirects:</strong> Analysis redirects user to " . htmlspecialchars($config['platform_oidc_auth_url']) . "</li>";
    echo "<li><strong>Platform authenticates:</strong> We generate JWT ID token and POST to " . htmlspecialchars($config['launch_url']) . "</li>";
    echo "<li><strong>Tool launches:</strong> Analysis processes the launch and shows the application</li>";
    echo "</ol>";
}

// Log configuration for debugging
logMessage("Analysis tool configuration loaded", [
    'analysis_domain' => ANALYSIS_TOOL_DOMAIN,
    'platform_issuer' => PLATFORM_ISSUER,
    'correct_flow' => 'Platform initiates -> Tool redirects -> Platform authenticates -> Tool launches'
], 'ANALYSIS_CONFIG');
?>