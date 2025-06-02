<?php
session_start();

// Load central configuration
require_once __DIR__ . '/config.php';

// Handle form submission for launching to Saltire
if ($_POST && isset($_POST['launch_to_saltire'])) {
    // Generate random user (simulating logged-in user)
    $user = generateRandomUser();

    // Store user in session
    $_SESSION['user'] = $user;

    // Generate state and nonce for security
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));

    // Store security values in session
    $_SESSION['state'] = $state;
    $_SESSION['nonce'] = $nonce;
    $_SESSION['lti_message_hint'] = 'hint_' . bin2hex(random_bytes(8));

    // Force session save before redirect
    session_write_close();
    session_start();

    logMessage("Initiating LTI 1.3 launch to Saltire", [
        'user' => $user,
        'state' => $state,
        'nonce' => $nonce,
        'session_id' => session_id(),
        'session_data_check' => [
            'user_exists' => isset($_SESSION['user']),
            'user_id' => $_SESSION['user']['user_id'] ?? 'missing'
        ]
    ], 'LAUNCH_INIT');

    // Build OIDC login initiation request
    $oidcParams = [
        'iss' => PLATFORM_ISSUER,
        'login_hint' => $user['user_id'],
        'target_link_uri' => SALTIRE_LAUNCH_URL,
        'client_id' => SALTIRE_CLIENT_ID,
        'lti_deployment_id' => DEFAULT_DEPLOYMENT_ID,
        'lti_message_hint' => $_SESSION['lti_message_hint']
    ];

    $oidcUrl = SALTIRE_OIDC_LOGIN_URL . '?' . http_build_query($oidcParams);

    logMessage("Redirecting to Saltire OIDC login", ['url' => $oidcUrl], 'LAUNCH_INIT');

    // Redirect to Saltire's OIDC login endpoint
    header("Location: $oidcUrl");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LTI 1.3 Platform - Launch to Application B</title>
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
            <p>Welcome to TALP LTI 1.3 Test Application. Connect seamlessly to external learning tools using the latest LTI 1.3 standard.</p>
        </div>

        <div class="launch-section">
            <h2>🎯 Launch External Learning Tool</h2>
            <p>Click the button below to securely launch and access <strong>LEAF</strong> (Saltire Learning Tool for testing). A random user identity will be generated automatically to simulate a logged-in student or instructor.</p>

            <form method="POST" action="">
                <button type="submit" name="launch_to_saltire" class="launch-btn">
                    🔗 Login to LEAF
                </button>
            </form>
        </div>

        <div class="info-section">
            <h3>🔒 What happens when you click?</h3>
            <p><strong>1. User Generation:</strong> We'll create a random user identity (name, email, role)</p>
            <p><strong>2. Security Setup:</strong> Generate secure state and nonce values for the session</p>
            <p><strong>3. OIDC Initiation:</strong> Start the OpenID Connect login flow with Saltire</p>
            <p><strong>4. Tool Launch:</strong> Saltire will authenticate and launch the learning tool</p>
        </div>

        <div class="platform-info">
            <h3>📋 Platform Information</h3>
            <p><strong>Platform Issuer:</strong> <code><?php echo PLATFORM_ISSUER; ?></code></p>
            <p><strong>Target Tool:</strong> <code><?php echo SALTIRE_TOOL_DOMAIN; ?></code></p>
            <p><strong>Client ID:</strong> <code><?php echo SALTIRE_CLIENT_ID; ?></code></p>
            <p><strong>Deployment ID:</strong> <code><?php echo DEFAULT_DEPLOYMENT_ID; ?></code></p>
        </div>
    </div>
</body>
</html>
