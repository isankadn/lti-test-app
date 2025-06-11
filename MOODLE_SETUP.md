# Moodle LTI Provider Setup Guide

This guide will help you configure your Moodle instance (`https://twdemo.leaf.ederc.jp/moodle`) to accept launches from this LTI Platform.

## Overview
- **Your Platform**: Acts as LTI Platform (this PHP application)
- **Moodle**: Acts as LTI Tool Provider (receives launches)
- **Flow**: Click "Login to Moodle" → Platform launches user → User appears in Moodle course

## Step 1: Enable LTI Provider in Moodle

### 1.1: Login to Moodle Admin
1. Go to: `https://twdemo.leaf.ederc.jp/moodle`
2. Login with administrator credentials

### 1.2: Enable LTI Provider Plugin
1. Navigate to **Site administration → Plugins → Manage plugins**
2. Search for "LTI Provider" under **Enrolment plugins**
3. Click **Enable** if it's disabled
4. Click **Settings** to configure

### 1.3: Configure LTI Provider Global Settings
1. Go to **Site administration → Plugins → Enrolments → LTI Provider**
2. Configure these settings:
   - ✅ **Enable LTI Provider**: Yes
   - ✅ **Allow tool to be used**: Yes
   - ✅ **Create user accounts**: Yes
   - ✅ **Update user details**: Yes
   - ✅ **Require matching email**: No (for testing)
   - **Default role**: Student
3. **Save changes**

## Step 2: Create or Select a Course

### 2.1: Create Test Course (if needed)
1. Go to **Site administration → Courses → Add a new course**
2. Fill in:
   - **Course full name**: `LTI Test Course`
   - **Course short name**: `lti-test`
   - **Course category**: Choose any
3. **Save and display**

### 2.2: Note the Course ID
- After creating/selecting your course, note the course ID from the URL
- Example: `https://twdemo.leaf.ederc.jp/moodle/course/view.php?id=2` → Course ID is `2`

## Step 3: Configure LTI Provider for Your Course

### 3.1: Add LTI Provider Enrolment
1. Go to your course
2. Navigate to **Course administration → Users → Enrolment methods**
3. Click **Add method**
4. Select **Publish as LTI tool**

### 3.2: Configure the LTI Tool Settings
Fill in these **exact** settings:

**Basic Settings:**
- **Tool to be provided**: Select your course
- **Launch container**: New window (or Embed)
- **Consumer key**: `php-lti-platform`
- **Shared secret**: `your-secret-key-123` (remember this!)
- **Custom parameters**: (leave empty for now)

**Advanced Settings:**
- ✅ **Send user's name**: Yes
- ✅ **Send user's email**: Yes
- ✅ **Accept grades from tool**: No (for testing)
- **Role instructor**: Instructor
- **Role learner**: Student

**Privacy Settings:**
- ✅ **Share launcher's name with tool**: Yes
- ✅ **Share launcher's email with tool**: Yes

3. **Save changes**

### 3.3: Get the Tool URLs
After saving, Moodle will show you the tool URLs:
- **Tool URL**: `https://twdemo.leaf.ederc.jp/moodle/enrol/lti/tool.php?id=X`
- **Launch URL**: `https://twdemo.leaf.ederc.jp/moodle/enrol/lti/tool.php?id=X`

Note the `id=X` part - this is your tool ID.

## Step 4: Update Platform Configuration

The consumer key is already set to `php-lti-platform` in your `config.php`. You may need to add the shared secret for authentication.

Add this to your `config.php`:

```php
// Add after the existing Moodle configuration
define('MOODLE_SHARED_SECRET', 'your-secret-key-123'); // Same as in Moodle
```

## Step 5: Test the Integration

### 5.1: Test from Platform
1. Navigate to your platform: `https://your-platform-domain.com`
2. Click **"Login to Moodle"**
3. Check the browser network tab and `launch.log` for debugging

### 5.2: Expected Flow
1. Platform generates random user
2. Platform redirects to Moodle login endpoint
3. Moodle authenticates the request
4. User appears in your Moodle course

## Step 6: Alternative Method (if above doesn't work)

If the LTI Provider method doesn't work, try configuring Moodle as an External Tool consumer instead:

### 6.1: Configure External Tool in Moodle
1. Go to **Site administration → Plugins → Activity modules → External tool → Manage tools**
2. Click **Configure a tool manually**
3. Enter:
   - **Tool name**: `External Platform`
   - **Tool URL**: `https://your-platform-domain.com/auth.php`
   - **Consumer key**: `moodle-consumer`
   - **Shared secret**: `shared-secret-123`
   - **LTI version**: LTI 1.3
4. Save

### 6.2: Update Your Platform Config
```php
define('MOODLE_CLIENT_ID', 'moodle-consumer');
define('MOODLE_SHARED_SECRET', 'shared-secret-123');
```

## Debugging Tips

### Check Moodle Logs
1. **Site administration → Reports → Logs**
2. Filter by:
   - **Component**: LTI Provider
   - **Event**: Tool launched

### Common Issues

1. **HTTPS Required**: Both platforms must use HTTPS
2. **CORS Issues**: Moodle may block cross-origin requests
3. **Consumer Key Mismatch**: Ensure exact match between platform and Moodle
4. **Secret Validation**: Moodle validates the shared secret

### Debug URLs to Test

Test these URLs directly:
- **Tool URL**: `https://twdemo.leaf.ederc.jp/moodle/enrol/lti/tool.php`
- **Login URL**: `https://twdemo.leaf.ederc.jp/moodle/enrol/lti/login.php`
- **Certs URL**: `https://twdemo.leaf.ederc.jp/moodle/enrol/lti/certs.php`

## Final Configuration Summary

Your `config.php` should have:
```php
define('MOODLE_TOOL_DOMAIN', 'https://twdemo.leaf.ederc.jp/moodle');
define('MOODLE_CLIENT_ID', 'php-lti-platform');
define('MOODLE_OIDC_LOGIN_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/login.php');
define('MOODLE_LAUNCH_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/tool.php');
define('MOODLE_JWKS_URL', MOODLE_TOOL_DOMAIN . '/enrol/lti/certs.php');
```

Your Moodle LTI Provider should have:
- **Consumer key**: `php-lti-platform`
- **Shared secret**: `your-secret-key-123`
- **Tool URL**: Points to your course

After completing these steps, clicking "Login to Moodle" should launch users directly into your Moodle course!