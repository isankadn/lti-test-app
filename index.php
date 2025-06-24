<?php
// Configure session cookies for cross-origin LTI support
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => '', // Keep empty for current domain
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None' // Allow cross-site cookies for LTI
]);

// Set additional cookie specifically for Analysis tool_id persistence
if (isset($_POST['launch_to_analysis']) || (isset($_SESSION['target_tool']) && $_SESSION['target_tool'] === 'analysis')) {
    setcookie('lti_tool_id', '2', [
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => false, // Allow JavaScript access for debugging
        'samesite' => 'None'
    ]);
}

session_start();

// Load central configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

/**
 * Create a JWT state parameter for Bookroll
 */
function createBookrollStateJWT($nonce, $loginHint, $messageHint) {
    // Load the private key
    $keyFile = __DIR__ . '/demo_private_key.pem';
    if (!file_exists($keyFile)) {
        // Generate key if it doesn't exist
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $resource = openssl_pkey_new($config);
        openssl_pkey_export($resource, $privateKey);
        file_put_contents($keyFile, $privateKey);
    }

    $privateKey = file_get_contents($keyFile);

    // Create JWT header
    $header = [
        'kid' => JWT_KEY_ID,
        'typ' => 'JWT',
        'alg' => 'RS256'
    ];

    // Create JWT payload for state
    $now = time();
    $payload = [
        'iss' => 'ltiStarter',
        'sub' => PLATFORM_ISSUER,
        'aud' => BOOKROLL_CLIENT_ID,
        'exp' => $now + 3600, // 1 hour
        'nbf' => $now,
        'iat' => $now,
        'jti' => $nonce,
        'nonce' => $nonce,
        'originalIss' => PLATFORM_ISSUER,
        'loginHint' => $loginHint,
        'ltiMessageHint' => $messageHint,
        'targetLinkUri' => BOOKROLL_LAUNCH_URL,
        'clientId' => BOOKROLL_CLIENT_ID,
        'ltiDeploymentId' => BOOKROLL_DEPLOYMENT_ID,
        'controller' => '/oidc/login_initiations'
    ];

    // Base64URL encode header and payload
    $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $dataToSign = $headerEncoded . '.' . $payloadEncoded;

    // Sign with private key
    $key = openssl_pkey_get_private($privateKey);
    if (!$key) {
        throw new Exception('Invalid private key');
    }

    $signResult = openssl_sign($dataToSign, $signature, $key, OPENSSL_ALGO_SHA256);
    if (!$signResult) {
        throw new Exception('Failed to sign JWT');
    }

    $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    return $dataToSign . '.' . $signatureEncoded;
}



// Handle form submission for launching to Bookroll
if ($_POST && isset($_POST['launch_to_bookroll'])) {
    // Generate random user (simulating logged-in user)
    $user = generateRandomUser();

    // Store user in session
    $_SESSION['user'] = $user;
    $_SESSION['target_tool'] = 'bookroll'; // Store which tool we're launching to

    // Generate nonce for security
    $nonce = bin2hex(random_bytes(16));
    $ltiMessageHint = 'hint_' . bin2hex(random_bytes(8));

    // Create JWT state parameter for Bookroll
    $state = createBookrollStateJWT($nonce, $user['user_id'], $ltiMessageHint);

    // Store security values in session
    $_SESSION['state'] = $state;
    $_SESSION['nonce'] = $nonce;
    $_SESSION['lti_message_hint'] = $ltiMessageHint;

    // Force session save before redirect
    session_write_close();
    session_start();

    logMessage("Initiating LTI 1.3 launch to Bookroll", [
        'user' => $user,
        'state' => $state,
        'nonce' => $nonce,
        'session_id' => session_id(),
        'session_data_check' => [
            'user_exists' => isset($_SESSION['user']),
            'user_id' => $_SESSION['user']['user_id'] ?? 'missing'
        ]
    ], 'LAUNCH_INIT');

    // Build OIDC login initiation request for Bookroll
    $oidcParams = [
        'iss' => PLATFORM_ISSUER,
        'login_hint' => $user['user_id'],
        'target_link_uri' => BOOKROLL_LAUNCH_URL,
        'client_id' => BOOKROLL_CLIENT_ID,
        'lti_deployment_id' => BOOKROLL_DEPLOYMENT_ID,
        'lti_message_hint' => $_SESSION['lti_message_hint']
    ];

    // Check if BOOKROLL_OIDC_LOGIN_URL already has parameters
    $separator = (strpos(BOOKROLL_OIDC_LOGIN_URL, '?') !== false) ? '&' : '?';
    $oidcUrl = BOOKROLL_OIDC_LOGIN_URL . $separator . http_build_query($oidcParams);

    logMessage("Redirecting to Bookroll OIDC login", ['url' => $oidcUrl], 'LAUNCH_INIT');

    // Redirect to Bookroll's OIDC login endpoint
    header("Location: $oidcUrl");
    exit;
}

// Handle form submission for launching to Analysis
if ($_POST && isset($_POST['launch_to_analysis'])) {
    // Generate random user (simulating logged-in user)
    $user = generateRandomUser();

    // Store user in session
    $_SESSION['user'] = $user;
    $_SESSION['target_tool'] = 'analysis'; // Store which tool we're launching to

    // Generate nonce and message hint for security
    $nonce = bin2hex(random_bytes(16));
    $ltiMessageHint = 'hint_' . bin2hex(random_bytes(8));

    // Store security values in session for when tool redirects back
    $_SESSION['nonce'] = $nonce;
    $_SESSION['lti_message_hint'] = $ltiMessageHint;

    // Force session save before redirect
    session_write_close();
    session_start();

    logMessage("Initiating LTI 1.3 launch to Analysis - CORRECT FLOW", [
        'user' => $user,
        'nonce' => $nonce,
        'session_id' => session_id(),
        'launch_flow' => 'Platform initiates by POSTing to tool login initiation endpoint'
    ], 'LAUNCH_INIT');

    // Step 1: Platform initiates login by POSTing to tool's login initiation endpoint
    // This is the CORRECT flow according to LTI 1.3 specification
    $loginInitiationParams = [
        'iss' => PLATFORM_ISSUER,                    // Platform issuer URL
        'login_hint' => $user['user_id'],            // User identifier
        'target_link_uri' => ANALYSIS_LAUNCH_URL,    // Where tool should launch to
        'client_id' => ANALYSIS_CLIENT_ID,           // Analysis tool's client ID
        'lti_deployment_id' => ANALYSIS_DEPLOYMENT_ID, // Deployment ID
        'lti_message_hint' => $ltiMessageHint        // Message hint for correlation
    ];

    logMessage("Analysis login initiation parameters", [
        'params' => $loginInitiationParams,
        'tool_login_endpoint' => ANALYSIS_OIDC_LOGIN_URL
    ], 'LAUNCH_INIT');

    // Create HTML form to POST to tool's login initiation endpoint
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Launching Analysis Tool...</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0; padding: 20px; display: flex; align-items: center;
                justify-content: center; min-height: 100vh;
            }
            .container {
                background: white; padding: 40px; border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.15); text-align: center;
            }
            .spinner {
                width: 40px; height: 40px; border: 4px solid #f3f3f3;
                border-top: 4px solid #007bff; border-radius: 50%;
                animation: spin 1s linear infinite; margin: 20px auto;
            }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>🚀 Launching Analysis Tool</h2>
            <div class="spinner"></div>
            <p>Initiating secure LTI 1.3 connection...</p>
            <p><small>Redirecting to Analysis login initiation endpoint</small></p>

            <form id="launchForm" method="POST" action="' . htmlspecialchars(ANALYSIS_OIDC_LOGIN_URL) . '">';

    foreach ($loginInitiationParams as $key => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
    }

    echo '      <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; margin-top: 20px;">
                    Continue to Analysis
                </button>
            </form>
        </div>

        <script>
            // Auto-submit the form after a short delay
            setTimeout(function() {
                document.getElementById("launchForm").submit();
            }, 2000);
        </script>
    </body>
    </html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTI 1.3 Platform - Launch to Moodle</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #666;
            font-size: 1.1em;
            line-height: 1.6;
        }

        .launch-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            border: 2px solid #e9ecef;
        }

        .launch-section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .launch-section p {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .launch-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .launch-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(40, 167, 69, 0.4);
            background: linear-gradient(135deg, #218838 0%, #1cc88a 100%);
        }

        .launch-btn:active {
            transform: translateY(0);
        }

        .analysis-btn {
            background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            border-radius: 50px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .analysis-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(0, 123, 255, 0.4);
            background: linear-gradient(135deg, #0056b3 0%, #5a32a3 100%);
        }

        .analysis-btn:active {
            transform: translateY(0);
        }

        .info-section {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #2196f3;
        }

        .info-section h3 {
            color: #1976d2;
            margin-bottom: 10px;
            font-size: 1.2em;
        }

        .info-section p {
            color: #333;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .platform-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 4px solid #ffc107;
        }

        .platform-info h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .platform-info p {
            color: #856404;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .platform-info code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.85em;
            color: #495057;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .launch-btn {
                padding: 15px 30px;
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 LTI 1.3 Platform</h1>
            <p>Welcome to LTI 1.3 Test Platform. Connect seamlessly to Moodle LMS using the latest LTI 1.3 standard.</p>
        </div>

        <div class="launch-section">
            <h2>🎯 Launch Bookroll Course</h2>
            <p>Click the button below to securely launch and login to <strong>Bookroll</strong> with a randomly generated user. A user identity will be created automatically to simulate a logged-in student or instructor.</p>

            <form method="POST" action="">
                <button type="submit" name="launch_to_bookroll" class="launch-btn">
                    🔗 Login to Bookroll
                </button>
            </form>
        </div>

        <div class="launch-section">
            <h2>📊 Launch Analysis Application</h2>
            <p>Click the button below to securely launch and login to <strong>Analysis</strong> application with a randomly generated user. This will connect you to the data analysis platform for educational insights.</p>

            <form method="POST" action="">
                <button type="submit" name="launch_to_analysis" class="analysis-btn">
                    📈 Login to Analysis
                </button>
            </form>
        </div>

        <div class="info-section">
            <h3>🔒 What happens when you click?</h3>
            <p><strong>1. User Generation:</strong> We'll create a random user identity (name, email, role)</p>
            <p><strong>2. Security Setup:</strong> Generate secure state and nonce values for the session</p>
            <p><strong>3. OIDC Initiation:</strong> Start the OpenID Connect login flow with your selected tool</p>
            <p><strong>4. Tool Launch:</strong> The selected tool will authenticate and launch the application</p>
        </div>

        <div class="platform-info">
            <h3>📋 Platform Information</h3>
            <p><strong>Platform Issuer:</strong> <code><?php echo PLATFORM_ISSUER; ?></code></p>

            <h4 style="margin-top: 15px; color: #856404;">📚 BookRoll Configuration:</h4>
            <p><strong>Tool Domain:</strong> <code><?php echo BOOKROLL_TOOL_DOMAIN; ?></code></p>
            <p><strong>Client ID:</strong> <code><?php echo BOOKROLL_CLIENT_ID; ?></code></p>
            <p><strong>Deployment ID:</strong> <code><?php echo BOOKROLL_DEPLOYMENT_ID; ?></code></p>

            <h4 style="margin-top: 15px; color: #856404;">📊 Analysis Configuration:</h4>
            <p><strong>Tool Domain:</strong> <code><?php echo ANALYSIS_TOOL_DOMAIN; ?></code></p>
            <p><strong>Client ID:</strong> <code><?php echo ANALYSIS_CLIENT_ID; ?></code></p>
            <p><strong>Deployment ID:</strong> <code><?php echo ANALYSIS_DEPLOYMENT_ID; ?></code></p>
        </div>
    </div>
</body>
</html>
