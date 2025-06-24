<?php
/**
 * Test Endpoint for OAuth2 Token Request
 * This simulates what the Analysis tool does to get access tokens
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

echo "<h1>LTI OAuth2 Token Endpoint Test</h1>";

if ($_POST && isset($_POST['test_token_request'])) {
    echo "<h2>Testing Token Request...</h2>";

    // Simulate the Analysis tool's token request
    $tokenData = [
        'grant_type' => 'client_credentials',
        'client_id' => ANALYSIS_CLIENT_ID,
        'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        'client_assertion' => 'dummy.jwt.token', // In real scenario, this would be a proper JWT
        'scope' => 'https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly'
    ];

    $tokenUrl = PLATFORM_TOKEN_URL;

    // Make request to our token endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "<h3>Token Request Results:</h3>";
    echo "<p><strong>HTTP Code:</strong> " . htmlspecialchars($httpCode) . "</p>";

    if ($error) {
        echo "<p><strong>cURL Error:</strong> " . htmlspecialchars($error) . "</p>";
    }

    echo "<p><strong>Response:</strong></p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    if ($httpCode === 200 && $response) {
        $tokenResponse = json_decode($response, true);
        if (isset($tokenResponse['access_token'])) {
            echo "<h3>✅ Success! Testing NRPS with access token...</h3>";

            // Test NRPS endpoint with the access token
            $nrpsUrl = PLATFORM_DOMAIN . '/nrps_memberships.php';

            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $nrpsUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokenResponse['access_token'],
                'Accept: application/vnd.ims.lti-nrps.v2.membershipcontainer+json'
            ]);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);

            $nrpsResponse = curl_exec($ch2);
            $nrpsHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $nrpsError = curl_error($ch2);
            curl_close($ch2);

            echo "<h4>NRPS Request Results:</h4>";
            echo "<p><strong>HTTP Code:</strong> " . htmlspecialchars($nrpsHttpCode) . "</p>";

            if ($nrpsError) {
                echo "<p><strong>cURL Error:</strong> " . htmlspecialchars($nrpsError) . "</p>";
            }

            echo "<p><strong>NRPS Response:</strong></p>";
            echo "<pre>" . htmlspecialchars($nrpsResponse) . "</pre>";

        } else {
            echo "<h3>❌ No access_token in response</h3>";
        }
    } else {
        echo "<h3>❌ Token request failed</h3>";
    }

} else {
    // Show test form
    ?>
    <p>This page tests our OAuth2 token endpoint to see if it's working correctly for the Analysis tool.</p>

    <h2>Configuration Check</h2>
    <ul>
        <li><strong>Platform Token URL:</strong> <?php echo htmlspecialchars(PLATFORM_TOKEN_URL); ?></li>
        <li><strong>Analysis Client ID:</strong> <?php echo htmlspecialchars(ANALYSIS_CLIENT_ID); ?></li>
        <li><strong>NRPS URL:</strong> <?php echo htmlspecialchars(PLATFORM_DOMAIN . '/nrps_memberships.php'); ?></li>
        <li><strong>JWT Key ID:</strong> <?php echo htmlspecialchars(JWT_KEY_ID); ?></li>
    </ul>

    <h2>Test OAuth2 Token Request</h2>
    <form method="POST">
        <button type="submit" name="test_token_request" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px;">
            Test Token Request
        </button>
    </form>

    <h2>Recent Logs</h2>
    <p>Check the logs to see what's happening:</p>
    <ul>
        <li><a href="logs.php?filter=TOKEN" target="_blank">View TOKEN logs</a></li>
        <li><a href="logs.php?filter=NRPS" target="_blank">View NRPS logs</a></li>
        <li><a href="logs.php?filter=ANALYSIS" target="_blank">View ANALYSIS logs</a></li>
    </ul>
    <?php
}
?>