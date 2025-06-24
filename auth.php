<?php
/**
 * LTI 1.3 Authentication Endpoint
 * This receives the authentication request from Moodle and responds with a JWT
 */
// Configure session cookies for cross-origin LTI support
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '', // Keep empty for current domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None' // Allow cross-site cookies for LTI
]);

session_start();

// Load central configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

/**
 * LTI 1.3 Platform Authentication Endpoint
 * This endpoint receives authentication requests from Moodle tool
 * and responds with a JWT ID token containing the LTI launch claims
 */

/**
 * Get the platform's private key for JWT signing
 */
function getPlatformPrivateKey() {
    $keyFile = __DIR__ . '/platform_pkcs8.key';

    if (!file_exists($keyFile)) {
        throw new Exception("Platform private key file not found: $keyFile. Please ensure platform keys are generated.");
    }

    return file_get_contents($keyFile);
}

/**
 * Create JWT with proper RS256 signing
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function createJWT($header, $payload) {
    $headerEncoded = base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadEncoded = base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $dataToSign = $headerEncoded . '.' . $payloadEncoded;

    // Get platform's private key for signing
    $privateKey = getPlatformPrivateKey();
    $key = openssl_pkey_get_private($privateKey);

    if (!$key) {
        throw new Exception('Invalid private key: ' . openssl_error_string());
    }

    // Sign with RS256
    $signResult = openssl_sign($dataToSign, $signature, $key, OPENSSL_ALGO_SHA256);
    if (!$signResult) {
        throw new Exception('Failed to sign JWT: ' . openssl_error_string());
    }

    $signatureEncoded = base64UrlEncode($signature);
    return $dataToSign . '.' . $signatureEncoded;
}

logMessage("=== Authentication endpoint called ===", array_merge($_REQUEST, [
    'session_id' => session_id(),
    'session_user_exists' => isset($_SESSION['user']),
    'session_contents' => array_keys($_SESSION)
]), 'AUTH');

// Handle both GET and POST requests (Moodle can use either)
if ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $params = $_REQUEST;  // This handles both GET and POST parameters

    // Validate required parameters
    $requiredParams = ['client_id', 'redirect_uri', 'login_hint', 'state', 'nonce'];
    foreach ($requiredParams as $param) {
        if (!isset($params[$param])) {
            logMessage("Missing required parameter: $param", $params, 'AUTH');
            http_response_code(400);
            echo "Bad Request: Missing required parameter $param";
            exit;
        }
    }

    // Validate client_id (supports Moodle, Bookroll, and Analysis)
    $validClientIds = [MOODLE_CLIENT_ID, BOOKROLL_CLIENT_ID, ANALYSIS_CLIENT_ID];
    if (!in_array($params['client_id'], $validClientIds)) {
        logMessage("Invalid client_id", [
            'received' => $params['client_id'],
            'expected_moodle' => MOODLE_CLIENT_ID,
            'expected_bookroll' => BOOKROLL_CLIENT_ID,
            'expected_analysis' => ANALYSIS_CLIENT_ID
        ], 'AUTH');
        http_response_code(400);
        echo "Bad Request: Invalid client_id";
        exit;
    }

    // Determine tool type based on client_id or tool_type parameter
    $toolType = isset($params['tool_type']) ? $params['tool_type'] : 'moodle'; // default

    if (!isset($params['tool_type'])) {
        if ($params['client_id'] === BOOKROLL_CLIENT_ID) {
            $toolType = 'bookroll';
        } elseif ($params['client_id'] === ANALYSIS_CLIENT_ID) {
            $toolType = 'analysis';
        } else {
            $toolType = 'moodle'; // default for MOODLE_CLIENT_ID
        }
    }

    logMessage("Tool type determined", ['tool_type' => $toolType, 'client_id' => $params['client_id']], 'AUTH');

    // Ensure lti_tool_id is maintained in session for Analysis tool
    if ($toolType === 'analysis' && !isset($_SESSION['lti_tool_id'])) {
        $_SESSION['lti_tool_id'] = 2;
        logMessage("Setting lti_tool_id=2 in session for Analysis tool", ['session_id' => session_id()], 'AUTH');
    } elseif ($toolType === 'bookroll' && !isset($_SESSION['lti_tool_id'])) {
        $_SESSION['lti_tool_id'] = 1;
        logMessage("Setting lti_tool_id=1 in session for Bookroll tool", ['session_id' => session_id()], 'AUTH');
    }

    // Try to get user from session first
    $user = $_SESSION['user'] ?? null;

    // If user not in session, recreate from login_hint (dummy user for demo)
    if (!$user) {
        logMessage("User not found in session, creating dummy user from login_hint", [
            'login_hint' => $params['login_hint'],
            'session_id' => session_id()
        ], 'AUTH');

        // Create a dummy user based on login_hint for demonstration
        // In production, you'd look up the real user from your database
        $user = [
            'user_id' => $params['login_hint'],
            'name' => DEMO_USER_NAME,
            'given_name' => DEMO_USER_GIVEN_NAME,
            'family_name' => DEMO_USER_FAMILY_NAME,
            'email' => DEMO_USER_EMAIL,
            'role' => DEMO_USER_ROLE,
            'roles' => ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner']
        ];

        // Store in session for potential future use
        $_SESSION['user'] = $user;
    }

            logMessage("Creating LTI launch JWT token", [
        'user' => $user,
        'client_id' => $params['client_id'],
        'redirect_uri' => $params['redirect_uri'],
        'state' => $params['state'],
        'nonce' => $params['nonce'],
        'tool_type' => $toolType,
        'session_debug' => [
            'lti_tool_id' => $_SESSION['lti_tool_id'] ?? 'not_set',
            'target_tool' => $_SESSION['target_tool'] ?? 'not_set',
            'nonce' => $_SESSION['nonce'] ?? 'not_set'
        ],
        'cookies_available' => $_COOKIE
    ], 'AUTH');

    try {
        // Create JWT header - RS256 as required by LTI 1.3
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => JWT_KEY_ID
        ];

        // Create JWT payload with LTI 1.3 claims
        $now = time();

        // CRITICAL: Generate consistent context ID for Analysis tool course registration
        $contextId = $_SESSION['context_id'] ?? generateDemoContextId();
        $_SESSION['context_id'] = $contextId; // Store for NRPS consistency

        $payload = [
            // Standard OIDC claims
            'iss' => PLATFORM_ISSUER,
            'sub' => $user['user_id'],
            'aud' => $params['client_id'],
            'exp' => $now + (JWT_EXPIRY_HOURS * 3600), // Configurable expiry
            'iat' => $now,
            'nonce' => $params['nonce'], // Use the nonce from the tool's request

            // User information
            'name' => $user['name'],
            'given_name' => $user['given_name'],
            'family_name' => $user['family_name'],
            'middle_name' => $user['middle_name'] ?? '',
            'picture' => $user['picture'] ?? '',
            'email' => $user['email'],

            // LTI specific claims
            'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
            'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
            'https://purl.imsglobal.org/spec/lti/claim/deployment_id' =>
                ($toolType === 'bookroll') ? BOOKROLL_DEPLOYMENT_ID :
                (($toolType === 'analysis') ? ANALYSIS_DEPLOYMENT_ID : DEFAULT_DEPLOYMENT_ID),
            'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => $params['redirect_uri'],

            // Context claim (course/context information)
            'https://purl.imsglobal.org/spec/lti/claim/context' => [
                'id' => $contextId,
                'label' => LTI_CONTEXT_LABEL,
                'title' => LTI_CONTEXT_TITLE,
                'type' => ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering']
            ],

            // Resource link claim
            'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
                'id' => 'resource-' . bin2hex(random_bytes(8)),
                'title' => ($toolType === 'analysis') ? 'Analysis Tool Launch' : LTI_RESOURCE_TITLE,
                'description' => ($toolType === 'analysis') ? 'Launch Analysis Tool for data analysis' : LTI_RESOURCE_DESCRIPTION
            ],

            // Roles claim
            'https://purl.imsglobal.org/spec/lti/claim/roles' => $user['roles'],

            // Names and Roles Provisioning Service (NRPS) - Required by Analysis tool
            'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
                'context_memberships_url' => PLATFORM_DOMAIN . '/nrps_memberships.php?context_id=' . $contextId,
                'service_versions' => ['2.0']
            ],

            // Assignment and Grade Services (AGS) - Required by Analysis tool
            'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
                'scope' => [
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                    'https://purl.imsglobal.org/spec/lti-ags/scope/score'
                ],
                'lineitems' => PLATFORM_DOMAIN . '/ags/lineitems'
            ],

            // Launch presentation
            'https://purl.imsglobal.org/spec/lti/claim/launch_presentation' => [
                'document_target' => LAUNCH_DOCUMENT_TARGET,
                'height' => LAUNCH_HEIGHT,
                'width' => LAUNCH_WIDTH,
                'return_url' => PLATFORM_RETURN_URL
            ],

            // Tool platform information
            'https://purl.imsglobal.org/spec/lti/claim/tool_platform' => [
                'guid' => PLATFORM_DOMAIN,
                'name' => PLATFORM_NAME,
                'version' => PLATFORM_VERSION,
                'product_family_code' => PLATFORM_PRODUCT_FAMILY
            ],

            // Custom claims (tool-specific)
            'https://purl.imsglobal.org/spec/lti/claim/custom' => [],

            // Add OAuth2 token endpoint for service authentication - CRITICAL for Analysis tool
            'https://purl.imsglobal.org/spec/lti/claim/oauth2_token_endpoint' => PLATFORM_TOKEN_URL
        ];

        // Add LTI message hint if available
        if (isset($_SESSION['lti_message_hint'])) {
            $payload['https://purl.imsglobal.org/spec/lti/claim/lti_message_hint'] = $_SESSION['lti_message_hint'];
        } elseif (isset($params['lti_message_hint'])) {
            $payload['https://purl.imsglobal.org/spec/lti/claim/lti_message_hint'] = $params['lti_message_hint'];
        }

        // Tool-specific customizations
        if ($toolType === 'bookroll') {
            // Bookroll expects these additional claims
            $payload['client_id'] = $params['client_id']; // Bookroll looks for this as a direct claim
            $payload['originalIss'] = PLATFORM_ISSUER; // Bookroll checks for originalIss first

        } elseif ($toolType === 'analysis') {
            // Analysis tool specific customizations
            $payload['https://purl.imsglobal.org/spec/lti/claim/custom'] = array_merge(
                $payload['https://purl.imsglobal.org/spec/lti/claim/custom'],
                [
                    'analysis_tool_id' => '2',
                    'platform_type' => 'php_lti_platform',
                    'integration_version' => '1.0'
                ]
            );
        }

        // Create the JWT token
        $idToken = createJWT($header, $payload);

        logMessage("JWT token created successfully", [
            'token_length' => strlen($idToken),
            'algorithm' => $header['alg'],
            'payload_summary' => [
                'iss' => $payload['iss'],
                'aud' => $payload['aud'],
                'sub' => $payload['sub'],
                'user_name' => $payload['name']
            ]
        ], 'AUTH');

        // Log the actual JWT token for debugging
        logMessage("ACTUAL JWT TOKEN SENT TO " . strtoupper($toolType), [
            'jwt_token' => $idToken
        ], strtoupper($toolType) . '_DEBUG');

        // Log the exact claims that the tool will use for database lookup
        logMessage(strtoupper($toolType) . " DATABASE LOOKUP CLAIMS", [
            'originalIss' => $payload['originalIss'] ?? 'NOT_SET',
            'iss' => $payload['iss'],
            'client_id' => $payload['client_id'] ?? 'NOT_SET',
            'deployment_id' => $payload['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] ?? 'NOT_SET',
            'tool_type' => $toolType,
            'full_payload_keys' => array_keys($payload)
        ], strtoupper($toolType) . '_DEBUG');

        // Log LTI service endpoints for Analysis tool
        if ($toolType === 'analysis') {
            logMessage("ANALYSIS LTI SERVICES CLAIMS", [
                'oauth2_token_endpoint' => $payload['https://purl.imsglobal.org/spec/lti/claim/oauth2_token_endpoint'] ?? 'NOT_SET',
                'nrps_url' => $payload['https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice']['context_memberships_url'] ?? 'NOT_SET',
                'ags_lineitems' => $payload['https://purl.imsglobal.org/spec/lti-ags/claim/endpoint']['lineitems'] ?? 'NOT_SET',
                'deep_linking_return' => $payload['https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings']['deep_link_return_url'] ?? 'NOT_SET'
            ], 'ANALYSIS_SERVICES');
        }

        // Also log what's being searched in database (tool-specific)
        $toolPrefix = $toolType === 'bookroll' ? 'br_' : ($toolType === 'analysis' ? 'analysis_' : '');
        logMessage("DATABASE LOOKUP QUERY EQUIVALENT", [
            'tool_type' => $toolType,
            'query' => "SELECT * FROM {$toolPrefix}lti13_iss_configuration WHERE iss = '" . $payload['originalIss'] . "' AND client_id = '" . $payload['client_id'] . "' AND deployment_id = '" . $payload['https://purl.imsglobal.org/spec/lti/claim/deployment_id'] . "'"
        ], strtoupper($toolType) . '_DEBUG');

        // Respond with form post to Moodle
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>LTI 1.3 Launch - Redirecting to <?php echo ucfirst($toolType); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 15px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
                    text-align: center;
                    max-width: 500px;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 20px auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h2>🚀 Launching <?php echo ucfirst($toolType); ?> Course</h2>
                <div class="spinner"></div>
                <p>Redirecting to <?php echo ucfirst($toolType); ?>...</p>
                <p><small>If you are not redirected automatically, please click the button below.</small></p>

                <form id="launchForm" method="POST" action="<?php echo htmlspecialchars($params['redirect_uri']); ?>">
                    <input type="hidden" name="id_token" value="<?php echo htmlspecialchars($idToken); ?>">
                    <input type="hidden" name="state" value="<?php echo htmlspecialchars($params['state']); ?>">
                    <button type="submit" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 5px; margin-top: 20px;">
                        Continue to <?php echo ucfirst($toolType); ?>
                    </button>
                </form>
            </div>

            <script>
                // Auto-submit the form after a short delay
                setTimeout(function() {
                    document.getElementById('launchForm').submit();
                }, 2000);
            </script>
        </body>
        </html>
        <?php

    } catch (Exception $e) {
        logMessage("JWT creation failed", ['error' => $e->getMessage()], 'AUTH');
        http_response_code(500);
        echo "JWT creation failed: " . htmlspecialchars($e->getMessage());
    }

} else {
    logMessage("Invalid request method", ['method' => $_SERVER['REQUEST_METHOD']], 'AUTH');
    http_response_code(405);
    echo "Method not allowed";
}