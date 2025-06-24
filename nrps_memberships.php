<?php
/**
 * LTI 1.3 Names and Roles Provisioning Service (NRPS) Endpoint
 *
 * This endpoint provides course membership data to LTI tools.
 * The Analysis tool makes authenticated requests here to get student/instructor lists.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/bookroll_config.php';
require_once __DIR__ . '/analysis_config.php';

// Set correct content type for NRPS
header('Content-Type: application/vnd.ims.lti-nrps.v2.membershipcontainer+json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Log all requests for debugging
logMessage("NRPS ENDPOINT CALLED", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'headers' => getallheaders(),
    'get_params' => $_GET
], 'NRPS');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader || !preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    logMessage("Missing or invalid Authorization header", [
        'auth_header' => $authHeader
    ], 'NRPS');
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized', 'message' => 'Bearer token required']);
    exit;
}

$accessToken = $matches[1];
logMessage("NRPS request with access token", [
    'token_length' => strlen($accessToken),
    'token_preview' => substr($accessToken, 0, 50) . '...'
], 'NRPS');

// In production, you should validate the access token
// For demo purposes, we'll accept any non-empty token

try {
    // Get context ID from query parameter (sent by Analysis tool)
    $contextId = $_GET['context_id'] ?? null;

    if (!$contextId) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_request', 'message' => 'context_id parameter required']);
        exit;
    }

    logMessage("NRPS processing membership request", [
        'context_id' => $contextId,
        'request_source' => 'analysis_tool'
    ], 'NRPS');

    // Generate course information based on context ID
    // In production, you'd look this up from your database
    $courseInfo = getDemoCourseInfo($contextId);

    // Create mock course membership data - Analysis tool needs this for course registration
    $members = getDemoCourseMembers();

    // Build NRPS response according to specification
    $response = [
        'id' => PLATFORM_DOMAIN . '/nrps_memberships.php?context_id=' . $contextId,
        'context' => $courseInfo, // CRITICAL: Analysis tool uses this for course registration
        'members' => $members
    ];

    logMessage("NRPS response generated", [
        'context_id' => $contextId,
        'member_count' => count($members),
        'members_preview' => array_map(function($member) {
            return [
                'name' => $member['name'],
                'user_id' => $member['user_id'],
                'roles' => $member['roles']
            ];
        }, $members)
    ], 'NRPS');

    // Return the membership container
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    logMessage("NRPS error", ['error' => $e->getMessage()], 'NRPS');
    http_response_code(500);
    echo json_encode([
        'error' => 'internal_server_error',
        'message' => $e->getMessage()
    ]);
}
?>