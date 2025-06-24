<?php
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';

/**
 * LTI 1.3 Platform Login Handler
 * This endpoint handles OIDC authentication responses from LTI tools (Moodle/Bookroll)
 * and redirects to our authentication endpoint to issue JWT tokens
 */

logMessage("=== Platform login handler called ===", $_REQUEST, 'LOGIN');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $params = $_GET;

    // This endpoint receives the OIDC response from LTI tools
    // Tools will redirect here with authentication parameters

    logMessage("OIDC response from LTI tool received", $params, 'LOGIN');

    // Validate required parameters and extract from state JWT if needed
    $requiredParams = ['login_hint'];
    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            logMessage("Missing required parameter: $param", $params, 'LOGIN');
            http_response_code(400);
            echo "Bad Request: Missing required parameter $param";
            exit;
        }
    }

    // Handle target_link_uri and iss parameters - some tools send them directly, others embed them in state JWT
    $iss = null;
    $target_link_uri = $params['target_link_uri'] ?? null;

    if (isset($params['iss'])) {
        $iss = $params['iss'];
        logMessage("Found iss in direct parameters", ['iss' => $iss], 'LOGIN');
    }

    // Try to decode the state JWT to extract missing parameters
    if (isset($params['state'])) {
        try {
            $stateParts = explode('.', $params['state']);
            if (count($stateParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $stateParts[1])), true);

                // Extract iss if not already found
                if (!$iss) {
                    if (isset($payload['originalIss'])) {
                        $iss = $payload['originalIss'];
                        logMessage("Extracted iss from state JWT", ['iss' => $iss], 'LOGIN');
                    } elseif (isset($payload['sub'])) {
                        $iss = $payload['sub'];
                        logMessage("Using sub as iss from state JWT", ['iss' => $iss], 'LOGIN');
                    }
                }

                // Extract target_link_uri if not already found
                if (!$target_link_uri && isset($payload['targetLinkUri'])) {
                    $target_link_uri = $payload['targetLinkUri'];
                    logMessage("Extracted target_link_uri from state JWT", ['target_link_uri' => $target_link_uri], 'LOGIN');
                }
            }
        } catch (Exception $e) {
            logMessage("Failed to decode state JWT", ['error' => $e->getMessage()], 'LOGIN');
        }
    }

    // Validate that we now have target_link_uri
    if (!$target_link_uri) {
        logMessage("Missing target_link_uri parameter and could not extract from state", $params, 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Missing required parameter target_link_uri";
        exit;
    }

    if (!$iss) {
        logMessage("Missing iss parameter and could not extract from state", $params, 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Missing required parameter iss";
        exit;
    }

    // In LTI 1.3 OIDC flow, the issuer should be our platform
    // We determine the tool type from the session data we stored during launch initiation
    $toolType = $_SESSION['target_tool'] ?? 'moodle'; // Default to moodle for backward compatibility

    // Validate that the issuer is our platform
    if ($iss !== PLATFORM_ISSUER) {
        logMessage("Invalid issuer - should be our platform", [
            'received' => $iss,
            'expected' => PLATFORM_ISSUER,
            'tool_type' => $toolType
        ], 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Invalid issuer - expected platform issuer";
        exit;
    }

    // Set client ID and launch URL based on tool type
    if ($toolType === 'bookroll') {
        $clientId = BOOKROLL_CLIENT_ID;
        $launchUrl = BOOKROLL_LAUNCH_URL;
        logMessage("Using Bookroll configuration", ['tool_type' => $toolType], 'LOGIN');
    } else {
        $clientId = MOODLE_CLIENT_ID;
        $launchUrl = MOODLE_LAUNCH_URL;
        logMessage("Using Moodle configuration", ['tool_type' => $toolType], 'LOGIN');
    }

    // Get our stored state and nonce from session
    $state = $_SESSION['state'] ?? null;
    $nonce = $_SESSION['nonce'] ?? null;

    if (!$state || !$nonce) {
        logMessage("Missing state or nonce in session", [
            'state' => $state ? 'present' : 'missing',
            'nonce' => $nonce ? 'present' : 'missing'
        ], 'LOGIN');
        http_response_code(400);
        echo "Bad Request: Invalid session state";
        exit;
    }

    logMessage("Session state validated", ['state' => $state, 'nonce' => $nonce], 'LOGIN');

    // Build authentication request parameters for our auth endpoint
    $authParams = [
        'response_type' => 'id_token',
        'scope' => 'openid',
        'response_mode' => 'form_post',
        'client_id' => $clientId,
        'redirect_uri' => $target_link_uri ?? $launchUrl,
        'login_hint' => $params['login_hint'],
        'state' => $state,
        'nonce' => $nonce,
        'tool_type' => $toolType  // Add tool type for auth endpoint
    ];

    // Add LTI message hint if provided
    if (isset($params['lti_message_hint'])) {
        $authParams['lti_message_hint'] = $params['lti_message_hint'];
    }

    // Redirect to our authentication endpoint
    $authUrl = PLATFORM_AUTH_URL . '?' . http_build_query($authParams);

    logMessage("Redirecting to platform auth endpoint", ['url' => $authUrl], 'LOGIN');

    header("Location: $authUrl");
    exit;

} else {
    logMessage("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']], 'LOGIN');
    http_response_code(405);
    echo "Method not allowed";
    exit;
}
?>