# LTI 1.3 Platform Implementation Guide for PHP

## Overview

This guide provides comprehensive instructions for building an LTI 1.3 Platform in PHP to connect to this LTI Tool (Analysis Tool). The tool implements the complete LTI 1.3 security specification with OIDC authentication flow.

## Understanding the Tool

Based on the codebase analysis, this is an **LTI 1.3 Tool** that expects platforms to:

1. Implement OIDC Login Initiation flow
2. Support JWT-based authentication
3. Provide Names and Roles Provisioning Service (NRPS)
4. Support Assignment and Grade Services (AGS)
5. Handle proper LTI 1.3 claims and scopes

## Required Endpoints on Your Platform

### 1. OIDC Authentication URL
**Platform Endpoint**: Your platform's OIDC authentication endpoint
- Used in tool configuration as `platform_oidc_auth_url`
- Handles the initial OIDC authentication request

### 2. OAuth2 Token Endpoint
**Platform Endpoint**: Your OAuth2 token endpoint
- Used in tool configuration as `oauth2_url`
- Issues access tokens for service calls

### 3. JWKS Endpoint
**Platform Endpoint**: Your JWKS (JSON Web Key Set) endpoint
- Used in tool configuration as `keyset_url`
- Provides public keys for JWT verification

## Tool Configuration Requirements

When registering this tool in your platform, you need to configure:

```php
$toolConfig = [
    'name' => 'Analysis Tool',
    'client_id' => 'your-unique-client-id',
    'deployment_id' => 'your-deployment-id',
    'private_key' => $toolPrivateKey, // Tool's private key (RSA)
    'keyset_url' => 'https://your-platform.com/jwks',
    'oauth2_url' => 'https://your-platform.com/oauth2/token',
    'platform_oidc_auth_url' => 'https://your-platform.com/oidc/auth',
    'consumer_key' => 'legacy-consumer-key-for-1.1-compatibility'
];
```

## LTI 1.3 Launch Flow Implementation

### Step 1: Login Initiation Request

**Tool Endpoint**: `POST /lti/login_initiations`

Your platform initiates login by sending a POST request to the tool:

```php
<?php
class LTIPlatform {

    public function initiateLaunch($targetLinkUri, $userId, $contextId) {
        $loginHint = base64_encode(json_encode([
            'user_id' => $userId,
            'context_id' => $contextId
        ]));

        $messageHint = $this->generateMessageHint($contextId);

        $params = [
            'iss' => $this->platformUrl,
            'login_hint' => $loginHint,
            'target_link_uri' => $targetLinkUri,
            'client_id' => $this->clientId,
            'lti_deployment_id' => $this->deploymentId,
            'lti_message_hint' => $messageHint
        ];

        return $this->postToTool('/lti/login_initiations', $params);
    }
}
```

### Step 2: Handle Authentication Request

The tool will redirect back to your OIDC authentication URL with these parameters:

```php
public function handleOIDCAuth() {
    $responseType = $_GET['response_type']; // 'id_token'
    $scope = $_GET['scope']; // 'openid'
    $clientId = $_GET['client_id'];
    $redirectUri = $_GET['redirect_uri'];
    $loginHint = $_GET['login_hint'];
    $nonce = $_GET['nonce'];
    $state = $_GET['state'];
    $responseMode = $_GET['response_mode']; // 'form_post'
    $prompt = $_GET['prompt']; // 'none'
    $ltiMessageHint = $_GET['lti_message_hint'];

    // Validate request and generate ID token
    $idToken = $this->generateIdToken($loginHint, $nonce, $clientId);

    // Post back to redirect_uri (form POST to /lti/launches)
    $this->postFormResponse($redirectUri, [
        'id_token' => $idToken,
        'state' => $state
    ]);
}
```

### Step 3: Generate LTI 1.3 ID Token

The ID Token must contain specific LTI 1.3 claims:

```php
private function generateIdToken($loginHint, $nonce, $clientId) {
    $userInfo = $this->getUserFromLoginHint($loginHint);
    $contextInfo = $this->getContextFromLoginHint($loginHint);

    $payload = [
        // Standard OIDC claims
        'iss' => $this->platformUrl,
        'sub' => $userInfo['id'],
        'aud' => $clientId,
        'exp' => time() + 3600,
        'iat' => time(),
        'nonce' => $nonce,

        // User information
        'name' => $userInfo['name'],
        'given_name' => $userInfo['given_name'],
        'family_name' => $userInfo['family_name'],
        'middle_name' => $userInfo['middle_name'] ?? '',
        'picture' => $userInfo['picture'] ?? '',
        'email' => $userInfo['email'],

        // LTI specific claims
        'https://purl.imsglobal.org/spec/lti/claim/message_type' => 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version' => '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id' => $this->deploymentId,
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri' => $this->targetLinkUri,

        // Context claim
        'https://purl.imsglobal.org/spec/lti/claim/context' => [
            'id' => $contextInfo['id'],
            'label' => $contextInfo['label'],
            'title' => $contextInfo['title'],
            'type' => ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering']
        ],

        // Resource link claim
        'https://purl.imsglobal.org/spec/lti/claim/resource_link' => [
            'id' => $this->resourceLinkId,
            'title' => 'Analysis Tool Launch',
            'description' => 'Launch Analysis Tool for data analysis'
        ],

        // Roles claim
        'https://purl.imsglobal.org/spec/lti/claim/roles' => $this->getUserRoles($userInfo['id']),

        // Names and Roles Service
        'https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice' => [
            'context_memberships_url' => $this->platformUrl . '/api/lti/contexts/' . $contextInfo['id'] . '/memberships',
            'service_versions' => ['2.0']
        ],

        // Assignment and Grade Services (if supported)
        'https://purl.imsglobal.org/spec/lti-ags/claim/endpoint' => [
            'scope' => [
                'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                'https://purl.imsglobal.org/spec/lti-ags/scope/score'
            ],
            'lineitems' => $this->platformUrl . '/api/lti/contexts/' . $contextInfo['id'] . '/lineitems'
        ],

        // Launch presentation
        'https://purl.imsglobal.org/spec/lti/claim/launch_presentation' => [
            'document_target' => 'iframe',
            'height' => 600,
            'width' => 800,
            'return_url' => $this->platformUrl . '/lti/return'
        ],

        // Tool platform
        'https://purl.imsglobal.org/spec/lti/claim/tool_platform' => [
            'guid' => $this->platformGuid,
            'name' => 'Your LMS Platform',
            'version' => '1.0',
            'product_family_code' => 'your-lms'
        ]
    ];

    return $this->signJWT($payload);
}
```

### Step 4: Handle Tool Launch

**Tool Endpoint**: `POST /lti/launches`

After authentication, the tool will process the launch at this endpoint.

## OAuth2 Token Endpoint Implementation

The tool will request access tokens for service calls:

```php
public function handleTokenRequest() {
    $grantType = $_POST['grant_type']; // 'client_credentials'
    $clientAssertionType = $_POST['client_assertion_type'];
    $clientAssertion = $_POST['client_assertion'];
    $scope = $_POST['scope'];

    // Verify client assertion JWT
    if (!$this->verifyClientAssertion($clientAssertion)) {
        return $this->returnError('invalid_client');
    }

    // Generate access token
    $accessToken = $this->generateAccessToken($scope);

    return json_encode([
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => $scope
    ]);
}
```

## Names and Roles Provisioning Service (NRPS)

**Platform Endpoint**: `GET /api/lti/contexts/{contextId}/memberships`

The tool expects this endpoint to return course membership data:

```php
public function getCourseMemberships($contextId) {
    $members = $this->getCourseMembers($contextId);

    $response = [
        'id' => $this->platformUrl . '/api/lti/contexts/' . $contextId . '/memberships',
        'context' => [
            'id' => $contextId,
            'label' => $this->getContextLabel($contextId),
            'title' => $this->getContextTitle($contextId)
        ],
        'members' => []
    ];

    foreach ($members as $member) {
        $response['members'][] = [
            'status' => 'Active',
            'name' => $member['name'],
            'picture' => $member['picture'] ?? '',
            'given_name' => $member['given_name'],
            'family_name' => $member['family_name'],
            'middle_name' => $member['middle_name'] ?? '',
            'email' => $member['email'],
            'user_id' => (string)$member['id'], // CRITICAL: Tool maps this to 'userId'
            'lis_person_sourcedid' => $member['sis_id'] ?? '',
            'roles' => $this->getUserRoles($member['id'])
        ];
    }

    header('Content-Type: application/vnd.ims.lti-nrps.v2.membershipcontainer+json');
    return json_encode($response);
}
```

## JWKS Endpoint Implementation

**Platform Endpoint**: `GET /jwks`

Provide your platform's public keys:

```php
public function getJWKS() {
    $publicKey = openssl_pkey_get_public($this->publicKeyPEM);
    $keyDetails = openssl_pkey_get_details($publicKey);

    $jwk = [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => $this->keyId,
        'n' => $this->base64urlEncode($keyDetails['rsa']['n']),
        'e' => $this->base64urlEncode($keyDetails['rsa']['e'])
    ];

    return json_encode(['keys' => [$jwk]]);
}
```

## JWT Signing and Verification

```php
class JWTHelper {
    private $privateKey;
    private $keyId;

    public function signJWT($payload) {
        $header = [
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => $this->keyId  // CRITICAL: Must match JWKS endpoint
        ];

        $encodedHeader = $this->base64urlEncode(json_encode($header));
        $encodedPayload = $this->base64urlEncode(json_encode($payload));

        $signature = '';
        openssl_sign(
            $encodedHeader . '.' . $encodedPayload,
            $signature,
            $this->privateKey,
            OPENSSL_ALGO_SHA256
        );

        $encodedSignature = $this->base64urlEncode($signature);

        return $encodedHeader . '.' . $encodedPayload . '.' . $encodedSignature;
    }

    private function base64urlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // CRITICAL: Tool verifies client assertion JWTs for OAuth2 token requests
    public function verifyClientAssertion($jwt) {
        // Verify tool's JWT signature using tool's public key from their JWKS
        $header = $this->parseJWTHeader($jwt);
        $toolPublicKey = $this->getToolPublicKey($header['kid']);
        return JWT::decode($jwt, $toolPublicKey, ['RS256']);
    }
}
```

## Role Mapping

Map your platform roles to LTI standard roles:

```php
private function getUserRoles($userId) {
    $platformRoles = $this->getUserPlatformRoles($userId);
    $ltiRoles = [];

    foreach ($platformRoles as $role) {
        switch ($role) {
            case 'instructor':
            case 'teacher':
                $ltiRoles[] = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Instructor';
                break;
            case 'student':
                $ltiRoles[] = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student';
                $ltiRoles[] = 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
                break;
            case 'admin':
                $ltiRoles[] = 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator';
                break;
        }
    }

    return $ltiRoles;
}
```

## Complete Platform Integration Example

```php
<?php
class AnalysisToolPlatform {
    private $config;
    private $jwtHelper;

    public function __construct($config) {
        $this->config = $config;
        $this->jwtHelper = new JWTHelper($config['private_key'], $config['key_id']);
    }

    public function launchAnalysisTool($userId, $contextId, $resourceLinkId) {
        // Step 1: Initiate login
        $targetLinkUri = $this->config['tool_url'] . '/lti/launches';

        $loginHint = base64_encode(json_encode([
            'user_id' => $userId,
            'context_id' => $contextId,
            'resource_link_id' => $resourceLinkId
        ]));

        $params = [
            'iss' => $this->config['platform_url'],
            'login_hint' => $loginHint,
            'target_link_uri' => $targetLinkUri,
            'client_id' => $this->config['client_id'],
            'lti_deployment_id' => $this->config['deployment_id']
        ];

        // Redirect user to tool's login initiation endpoint
        $this->redirectToTool('/lti/login_initiations', $params);
    }
}
```

## Security Considerations

1. **JWT Security**: Always use RS256 algorithm with proper key management
2. **Nonce Validation**: Implement proper nonce validation to prevent replay attacks
3. **State Parameter**: Use state parameter for CSRF protection
4. **HTTPS Only**: All communications must use HTTPS
5. **Token Expiration**: Implement proper token expiration and refresh mechanisms
6. **Key Rotation**: Plan for periodic key rotation

## Testing Your Implementation

1. **Tool Registration**: Register your platform with the tool using the configuration above
2. **Launch Flow**: Test the complete OIDC launch flow
3. **Service Calls**: Verify NRPS and AGS service calls work correctly
4. **Role Handling**: Test different user roles (instructor, student, admin)
5. **Error Handling**: Test error scenarios and edge cases

## Common Issues and Troubleshooting

1. **JWT Verification Failures**: Check key ID (kid) and algorithm matches
2. **Missing Claims**: Ensure all required LTI 1.3 claims are included
3. **Role Issues**: Verify role URIs match LTI standards exactly
4. **Service Endpoints**: Ensure all service URLs are properly configured and accessible
5. **Content-Type Headers**: Use correct MIME types for service responses

## Course Information and Analytics Integration

### How the Tool Uses Course Data

This Analysis Tool is designed as a **course-centric learning analytics platform** where all data and analysis is organized by course. Here's how course information flows through the system:

#### 1. Course Information Extraction
The tool extracts course information from LTI JWT claims:

```php
// Your platform must provide these context claims
'https://purl.imsglobal.org/spec/lti/claim/context' => [
    'id' => $contextInfo['id'],           // 🎯 Primary course identifier (becomes context_id)
    'label' => $contextInfo['label'],     // Course code (e.g., "CS101")
    'title' => $contextInfo['title'],     // Course name (e.g., "Intro to Computer Science")
    'type' => ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering']
],
```

#### 2. Automatic Course Registration
On every launch, the tool automatically:

- **Saves course data** to `courses` table with `context_id` as primary key
- **Registers all course members** from NRPS as `course_students`
- **Links instructor** as course owner for data access control

```php
// Database structure created by tool
courses: {
    context_id: "your-course-id",      // From LTI context claim
    context_label: "CS101",            // Course code
    context_title: "Intro to CS",      // Course name
    user_id: "instructor-id"           // Course instructor
}
```

#### 3. Course-Based Data Segmentation
All analytics data is filtered and organized by course:

- **Student Analytics**: All learning data filtered by `context_id`
- **Course Reports**: Generated per course with course-specific metrics
- **Access Control**: Students only see data from their enrolled courses
- **Multi-Course Support**: Instructors can manage multiple courses separately

#### 4. Required Course Context in NRPS
Your NRPS endpoint must include course context:

```php
public function getCourseMemberships($contextId) {
    // CRITICAL: contextId must match the course ID from LTI launch
    $response = [
        'context' => [
            'id' => $contextId,                    // Must match context claim
            'label' => $this->getContextLabel($contextId),
            'title' => $this->getContextTitle($contextId)
        ],
        'members' => [
            // Course members with user_id field
        ]
    ];
}
```

## Tool-Specific Notes

This Analysis Tool specifically:

- **CRITICAL**: Requires Names and Roles Service for course membership data (tool will fail without access token)
- **CRITICAL**: Expects `user_id` field in NRPS response, which it maps to `userId` internally
- **CRITICAL**: Must have valid OAuth2 URL configured - tool requests access tokens immediately on launch
- **CRITICAL**: Course ID (`context_id`) is the primary key for all data segmentation and analytics
- **CRITICAL**: All analysis features are course-scoped - no cross-course data access
- Uses Assignment and Grade Services for gradebook integration
- Supports both instructor and student role access with different permissions
- Implements terms of service consent flow for instructors (renders separate view)
- Stores course and user data for analytics purposes in `courses` and `course_students` tables
- Expects specific claim formats as defined in `config/lti_claims_and_scopes.yml`
- Supports pagination in NRPS responses via HTTP Link headers
- Uses nonce validation between login initiation and launch (stored in sessions/cookies)
- **Multi-course support**: Instructors can launch into different courses and see course-specific analytics
- **Course persistence**: Course data is permanently stored for historical analysis and reporting

## Further Resources

- [LTI 1.3 Core Specification](https://www.imsglobal.org/spec/lti/v1p3)
- [LTI 1.3 Security Framework](https://www.imsglobal.org/spec/security/v1p0)
- [Names and Roles Provisioning Service](https://www.imsglobal.org/spec/lti-nrps/v2p0)
- [Assignment and Grade Services](https://www.imsglobal.org/spec/lti-ags/v2p0)
