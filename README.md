# LTI 1.3 Platform - BookRoll Integration

A PHP implementation of an LTI 1.3 Platform for seamless integration with BookRoll digital textbook system.

## 🔑 Key Architecture Overview

In LTI 1.3, there are two types of applications that need their own key pairs:

- **Platform** (this LTI-test application): Signs JWT tokens sent to tools
- **Tool** (BookRoll): Verifies platform JWTs and signs its own responses

Each application uses:
- **Private Key**: To sign JWT tokens
- **Public Key**: Shared via JWKS endpoint for others to verify signatures

## 🚀 Setup Instructions

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

### Step 2: Generate BookRoll Keys

BookRoll tool needs its own key pair (if not already generated):

```bash
# Generate BookRoll's private key
openssl genrsa -out keypair.pem 2048

# Extract BookRoll's public key
openssl rsa -in keypair.pem -pubout -out publickey.crt

# Convert private key to PKCS8 format
openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in keypair.pem -out pkcs8.key
```

### Step 3: Configure Platform

Update `config.php` with your domain settings:

```php
define('PLATFORM_DOMAIN', 'https://your-ngrok-url.ngrok-free.app');
define('BOOKROLL_CLIENT_ID', 'bookroll-client-id');
define('BOOKROLL_DEPLOYMENT_ID', '2');
```

## 📁 File Structure

After setup, your directory should contain:

```
lti-test/
├── platform_keypair.pem     # Platform's full key pair
├── platform_pkcs8.key       # Platform's private key (for signing)
├── platform_publickey.crt   # Platform's public key (for JWKS)
├── keypair.pem              # BookRoll's full key pair
├── pkcs8.key                # BookRoll's private key
├── publickey.crt            # BookRoll's public key
├── config.php               # Platform configuration
├── auth.php                 # JWT signing endpoint
├── jwks.php                 # Platform's public key endpoint
└── index.php                # Launch interface
```

## ⚙️ BookRoll Configuration

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

## 🔄 LTI 1.3 Flow

```
1. User clicks launch button on Platform
2. Platform redirects to BookRoll OIDC login
3. BookRoll redirects back to Platform auth endpoint
4. Platform signs JWT with platform_pkcs8.key
5. Platform posts JWT to BookRoll
6. BookRoll gets Platform's public key from JWKS endpoint
7. BookRoll verifies JWT signature and launches content
```

## 🔒 Security Details

### Platform (this application):
- **Signs JWTs with**: `platform_pkcs8.key`
- **Provides public key via**: `https://your-domain/jwks.php`
- **JWKS serves**: `platform_publickey.crt`

### BookRoll Tool:
- **Verifies platform JWTs using**: Platform's public key from JWKS
- **Signs responses with**: Its own `pkcs8.key`
- **Provides public key**: Via its own JWKS endpoint

## 🎯 Key Endpoints

- **`/index.php`** - Launch interface
- **`/oidc_login.php`** - OIDC login handler
- **`/auth.php`** - JWT signing and authentication
- **`/jwks.php`** - Platform's public key (JWKS format)
- **`/token.php`** - OAuth2 token endpoint
- **`/logs.php`** - Debug logging interface

## 🔧 Testing

1. Start your web server and ngrok:
   ```bash
   ngrok http 80
   ```

2. Update `config.php` with ngrok URL

3. Configure BookRoll with platform endpoints

4. Visit `/index.php` and test launch

## 📚 Key Concepts

- **Platform**: Signs JWTs, provides JWKS endpoint
- **Tool**: Verifies platform JWTs, has own key pair
- **JWKS**: JSON Web Key Set - public key distribution format
- **JWT**: JSON Web Token - signed authentication token
- **OIDC**: OpenID Connect - authentication layer

## 🚨 Important Notes

- **Never share private keys** between applications
- **HTTPS required** for production deployments
- **Each application** needs its own key pair
- **JWKS endpoints** must be accessible to verify signatures
- **Key rotation** should be planned for production use

---

**For production use**: Consider using established LTI libraries and implement proper key management, logging, and error handling.
