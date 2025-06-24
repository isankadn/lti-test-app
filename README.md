# LTI 1.3 Platform - Multi-Tool Integration

A PHP implementation of an LTI 1.3 Platform for seamless integration with multiple LTI tools including **BookRoll** digital textbook system and **Analysis** learning analytics tool.

## 🔑 Key Architecture Overview

This platform supports two major LTI 1.3 tools:
- **BookRoll**: Digital textbook and reading analytics platform
- **Analysis**: Learning analytics and course data analysis tool

In LTI 1.3, there are two types of applications that need their own key pairs:

- **Platform** (this LTI-test application): Signs JWT tokens sent to tools
- **Tools** (BookRoll & Analysis): Verify platform JWTs and sign their own responses

Each application uses:
- **Private Key**: To sign JWT tokens
- **Public Key**: Shared via JWKS endpoint for others to verify signatures

## 🚀 Initial Setup

### Step 1: Generate Platform Keys

The platform needs its own RSA key pair for signing JWT tokens:

```bash
# Generate platform's private key
openssl genrsa -out platform_keypair.pem 2048

# Extract platform's public key
openssl rsa -in platform_keypair.pem -pubout -out platform_publickey.crt

# Convert private key to PKCS8 format
openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in platform_keypair.pem -out platform_pkcs8.key
```

### Step 2: Configure Platform

Update configuration in `demo_config.php`:

```php
// Tool Domains
define('EXTERNAL_TOOL_BASE_DOMAIN', 'https://your-tools-domain.com');

// Platform Domain
define('DEFAULT_PLATFORM_HOST', 'your-platform.ngrok.io');

// Course Information
define('DEMO_COURSE_CODE', 'YOUR-COURSE-101');
define('DEMO_COURSE_TITLE', 'Your Course Title');

// Email Domain
define('DEMO_EMAIL_DOMAIN', 'your-university.edu');
```

## 📁 File Structure

After setup, your directory should contain:

```
lti-test/
├── platform_keypair.pem     # Platform's full key pair
├── platform_pkcs8.key       # Platform's private key (for signing)
├── platform_publickey.crt   # Platform's public key (for JWKS)
├── demo_config.php          # ✨ Main configuration file
├── config.php               # Platform configuration
├── bookroll_config.php      # BookRoll-specific config
├── analysis_config.php      # Analysis-specific config
├── auth.php                 # JWT signing endpoint
├── jwks.php                 # Platform's public key endpoint
├── token.php                # OAuth2 token endpoint
├── nrps_memberships.php     # Names and Roles service
├── oidc_login.php           # OIDC login handler
└── index.php                # Launch interface
```

---

# 📚 BookRoll Integration

BookRoll is a digital textbook platform that provides reading analytics and interactive learning experiences.

## BookRoll-Specific Setup

### Step 1: Generate BookRoll Keys

BookRoll tool needs its own key pair (if not already generated):

```bash
# Generate BookRoll's private key
openssl genrsa -out keypair.pem 2048

# Extract BookRoll's public key
openssl rsa -in keypair.pem -pubout -out publickey.crt

# Convert private key to PKCS8 format
openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in keypair.pem -out pkcs8.key
```

### Step 2: BookRoll Configuration

Update `bookroll_config.php` if needed:

```php
define('BOOKROLL_CLIENT_ID', 'bookroll-client-id');
define('BOOKROLL_DEPLOYMENT_ID', '2');
define('BOOKROLL_CONSUMER_KEY', 'bookroll-consumer-key');
```

## ⚙️ BookRoll Platform Registration

Configure BookRoll's LTI 1.3 Platform Deployment with these values:

| Field | Value |
|-------|-------|
| **iss** | `https://your-ngrok-url.ngrok-free.app` |
| **client id** | `bookroll-client-id` |
| **oidc endpoint** | `https://your-ngrok-url.ngrok-free.app/oidc_login.php` |
| **jwks endpoint** | `https://your-ngrok-url.ngrok-free.app/jwks.php` |
| **OAuth2 token endpoint** | `https://your-ngrok-url.ngrok-free.app/token.php` |
| **deployment id** | `2` |

Configure BookRoll's Tool Settings:

| Field | Value |
|-------|-------|
| **private key** | Contents of `pkcs8.key` |
| **public key** | Contents of `publickey.crt` |

## 🔄 BookRoll LTI Flow

```
1. User clicks "Launch BookRoll" on Platform
2. Platform redirects to BookRoll OIDC login
3. BookRoll redirects back to Platform auth endpoint
4. Platform signs JWT with platform_pkcs8.key
5. Platform posts JWT to BookRoll
6. BookRoll gets Platform's public key from JWKS endpoint
7. BookRoll verifies JWT signature and launches content
```

---

# 🔬 Analysis Tool Integration

Analysis is a learning analytics tool that provides course-level data analysis and student performance insights.

## Analysis-Specific Setup

### Step 1: Analysis Configuration

The Analysis tool configuration is handled in `analysis_config.php`:

```php
define('ANALYSIS_CLIENT_ID', 'LQJKIL4wvUhVbWvFG');
define('ANALYSIS_DEPLOYMENT_ID', '10');
define('ANALYSIS_CONSUMER_KEY', '5a44e433-cfcf-497e-8365-2e4115a5a291');
```

### Step 2: Analysis Tool Registration

Configure the Analysis tool's LTI 1.3 Platform with these values:

| Field | Value |
|-------|-------|
| **name** | `LTI 1.3 Demo Platform` |
| **client_id** | `LQJKIL4wvUhVbWvFG` |
| **deployment_id** | `10` |
| **private_key** | Your platform's private key (RSA format) |
| **keyset_url** | `https://your-ngrok-url.ngrok-free.app/jwks.php` |
| **oauth2_url** | `https://your-ngrok-url.ngrok-free.app/token.php` |
| **platform_oidc_auth_url** | `https://your-ngrok-url.ngrok-free.app/oidc_login.php` |
| **consumer_key** | `5a44e433-cfcf-497e-8365-2e4115a5a291` |

## 🔄 Analysis LTI Flow

```
1. User clicks "Launch Analysis" on Platform
2. Platform POSTs to Analysis /lti/login_initiations
3. Analysis redirects back to Platform OIDC auth endpoint
4. Platform generates JWT with course context and NRPS URL
5. Platform POSTs JWT to Analysis /lti/launches
6. Analysis requests access token from Platform /token.php
7. Analysis calls Platform /nrps_memberships.php for course data
8. Analysis registers course and members in its database
9. Analysis launches with course-specific analytics
```

## 🎯 Analysis Tool Features

- **Course Registration**: Automatically registers courses from LTI context
- **Member Import**: Imports all course members via NRPS
- **Learning Analytics**: Provides course-level data analysis
- **Role-Based Access**: Different views for instructors vs students
- **Multi-Course Support**: Handles multiple courses per instructor

---

# ⚙️ Configuration Management

## Main Configuration Files

### `demo_config.php` - Central Configuration
All customizable values are centralized here:

```php
// Domain Configuration
define('EXTERNAL_TOOL_BASE_DOMAIN', 'https://newleaf.let.media.kyoto-u.ac.jp');
define('DEFAULT_PLATFORM_HOST', 'your-platform.ngrok.io');
define('DEMO_EMAIL_DOMAIN', 'example.edu');

// Course Configuration
define('DEMO_COURSE_CODE', 'DEMO-CS101');
define('DEMO_COURSE_TITLE', 'Introduction to Computer Science - Demo Course');

// Platform Configuration
define('DEMO_PLATFORM_NAME', 'LTI 1.3 Demo Platform');
```

### Configuration Categories

| **Category** | **Variables** | **Purpose** |
|--------------|---------------|-------------|
| **Domains** | `EXTERNAL_TOOL_BASE_DOMAIN`<br/>`DEFAULT_PLATFORM_HOST`<br/>`DEMO_EMAIL_DOMAIN` | Tool URLs, platform domain, email addresses |
| **Course** | `DEMO_COURSE_CODE`<br/>`DEMO_COURSE_TITLE`<br/>`DEMO_COURSE_PREFIX` | Course information and IDs |
| **Users** | `DEMO_MEMBERS` array | All instructors and students |
| **Platform** | `DEMO_PLATFORM_NAME`<br/>`DEMO_LAUNCH_HEIGHT/WIDTH` | Platform branding and UI |

### Helper Functions

The configuration provides utility functions:

- `getDemoCourseInfo($contextId)` - Get course information
- `getDemoCourseMembers()` - Get all course members
- `getDemoInstructor()` - Get instructor information
- `getDemoStudents()` - Get student list
- `generateDemoContextId()` - Generate consistent context ID

## Quick Customization Examples

### Change Tool Domain
```php
// In demo_config.php
define('EXTERNAL_TOOL_BASE_DOMAIN', 'https://your-tools.university.edu');
```

### Change Course Information
```php
// In demo_config.php
define('DEMO_COURSE_CODE', 'MATH-201');
define('DEMO_COURSE_TITLE', 'Advanced Calculus');
```

### Add Course Members
```php
// In demo_config.php - Edit DEMO_MEMBERS array
define('DEMO_MEMBERS', [
    [
        'type' => 'instructor',
        'user_id' => 'prof-smith-001',
        'name' => 'Dr. John Smith',
        'given_name' => 'John',
        'family_name' => 'Smith',
        'email' => 'john.smith@your-university.edu',
        // ... other fields
    ],
    // Add more students...
]);
```

## Environment-Specific Configuration

For different environments:

```php
// In demo_config.php
$environment = $_ENV['LTI_ENVIRONMENT'] ?? 'demo';
if ($environment === 'production') {
    require_once __DIR__ . '/demo_config_prod.php';
} else {
    require_once __DIR__ . '/demo_config_dev.php';
}
```

---

# 🎯 Platform Endpoints

## Core LTI Endpoints
- **`/index.php`** - Launch interface for both tools
- **`/oidc_login.php`** - OIDC login handler
- **`/auth.php`** - JWT signing and authentication
- **`/jwks.php`** - Platform's public key (JWKS format)

## Service Endpoints
- **`/token.php`** - OAuth2 token endpoint (for Analysis)
- **`/nrps_memberships.php`** - Names and Roles Provisioning Service
- **`/logs.php`** - Debug logging interface

## Tool-Specific Endpoints
- **BookRoll**: Uses standard LTI 1.3 flow
- **Analysis**: Uses LTI 1.3 + OAuth2 services (NRPS)

---

# 🔧 Testing & Deployment

## Development Testing

1. Start your web server and ngrok:
   ```bash
   ngrok http 80
   ```

2. Update `demo_config.php` with ngrok URL:
   ```php
   define('DEFAULT_PLATFORM_HOST', 'abc123.ngrok-free.app');
   ```

3. Configure tools with platform endpoints

4. Visit `/index.php` and test launches

## Validation Checklist

After configuration changes:

- ✅ **NRPS Endpoint**: Test course membership data
- ✅ **LTI Launch**: Verify course information in JWT
- ✅ **BookRoll Integration**: Test textbook launch
- ✅ **Analysis Integration**: Test analytics launch and course registration

---

# 🔒 Security Details

### Platform (this application):
- **Signs JWTs with**: `platform_pkcs8.key`
- **Provides public key via**: `https://your-domain/jwks.php`
- **JWKS serves**: `platform_publickey.crt`

### BookRoll Tool:
- **Verifies platform JWTs using**: Platform's public key from JWKS
- **Signs responses with**: Its own `pkcs8.key`
- **Provides public key**: Via its own JWKS endpoint

### Analysis Tool:
- **Verifies platform JWTs using**: Platform's public key from JWKS
- **Makes OAuth2 requests**: To platform's token endpoint
- **Calls NRPS service**: For course membership data

---

# 🚨 Important Notes

- **Never share private keys** between applications
- **HTTPS required** for production deployments
- **Each application** needs its own key pair
- **JWKS endpoints** must be accessible to verify signatures
- **Key rotation** should be planned for production use
- **Configuration centralized** in `demo_config.php` for easy customization

---

# 📚 Key Concepts

- **Platform**: Signs JWTs, provides JWKS endpoint, hosts services
- **Tools**: Verify platform JWTs, have own key pairs
- **JWKS**: JSON Web Key Set - public key distribution format
- **JWT**: JSON Web Token - signed authentication token
- **OIDC**: OpenID Connect - authentication layer
- **NRPS**: Names and Roles Provisioning Service - course membership API
- **OAuth2**: Token-based authorization for service calls

---

**For production use**: Consider using established LTI libraries and implement proper key management, logging, and error handling.
