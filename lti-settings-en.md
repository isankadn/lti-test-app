Registering LTI 1.3 Tool to Moodle
========

1. Create RSA keys for BookRoll's LTI 1.3 connection

  ```
  $ openssl genrsa -out keypair.pem 2048
  $ openssl rsa -in keypair.pem -pubout -out publickey.crt
  $ cat publickey.crt
  $ openssl pkcs8 -topk8 -inform PEM -outform PEM -nocrypt -in keypair.pem -out pkcs8.key
  $ cat pkcs8.key
  ```

2. **[BookRoll]** Start BookRoll.
  ```
  ※ The following explanation assumes it's running on this URL.
  http://localhost:8080
  ```

3. **[Moodle]** Navigate as follows:
  Site administration → Plugins → Activity modules → External tool → Manage tools

4. **[Moodle]** In "Manage tools", click the "configure a tool manually" link in the "Add tool" section to add a new tool.
  Or select "Edit (gear icon)" for an existing tool.

5. **[Moodle]** Enter the following data in the external tool configuration form:
- External tool configuration
  - Tool settings
    - Tool name: BookRoll
    - Tool URL: http://localhost:8080/lti3
    - LTI version: LTI 1.3
    - Client ID: (provided by Moodle)
    - Initiate login URL: http://localhost:8080/oidc/login_initiations
    - Redirect URI: http://localhost:8080/lti3
    - Tool configuration usage: Show as preconfigured tool when adding external tool
    - Default launch container: New window

Click the "Save changes" button.

6. **[Moodle]** Click "View configuration details (horizontal bar icon)" for the added or edited tool.
  A dialog will be displayed as follows. (Save these values as you'll need them)
   (Note that some values differ for each external tool)
  ```
  Tool configuration details

    Platform ID: https://md4.ksy.jpn.com
    Client ID: imrm754kXqTkwne
    Deployment ID: 3
    Public keyset URL: https://md4.ksy.jpn.com/mod/lti/certs.php
    Access token URL: https://md4.ksy.jpn.com/mod/lti/token.php
    Authentication request URL: https://md4.ksy.jpn.com/mod/lti/auth.php
  ```

7. **[BookRoll]** Log in as administrator. http://localhost:8080/bookroll

   In "Site administration" > "LTI 1.3 Platform Deployment Settings", configure the values from Moodle's tool configuration details.

| Item name            | Tool configuration details item                | Actual value                              |
|---------------------|-----------------------------------------------|-------------------------------------------|
| key id              | -                                             | 1                                         |
| iss                 | Platform ID                                   | https://md4.ksy.jpn.com                   |
| client id           | Client ID                                     | imrm754kXqTkwne                           |
| oidc endpoint       | Authentication request URL                    | https://md4.ksy.jpn.com/mod/lti/auth.php  |
| jwks endpoint       | Public keyset URL                             | https://md4.ksy.jpn.com/mod/lti/certs.php |
| OAuth2 token url    | Access token URL                              | https://md4.ksy.jpn.com/mod/lti/token.php |
| OAuth2 token aud    | -                                             | null                                      |
| deployment id       | Deployment ID                                 | 3                                         |

   In "Site administration" > "LTI 1.3 BookRoll Settings", configure the values to be used for LTI 1.3.
  Use the values created in step 1 for the private key and public key.

| Item name            | Actual value                       |
|---------------------|-----------------------------------|
| id                  | 1                                 |
| url                 | http://localhost:8080/bookroll    |
| private key         | (value from pkcs8.key)            |
| public key          | (value from publickey.crt)        |

8. **[Moodle]** Click the "Turn editing on" button in the course to enter edit mode.

  Click the "Add an activity or resource" link to add an external tool.

  Or select "Edit → Edit settings" for an existing external tool.

9. **[Moodle]** Enter the following data in the "Updating XXX External tool" form:
- General
  - Activity name: (any name)
  - Preconfigured tool: BookRoll (tool name configured in step 4)

Click the "Save and return to course" button.

10. **[Moodle]** Click the "Turn editing off" button to exit edit mode.

11. **[BookRoll]** Log out the administrator.

12. **[Moodle]** Course → Topic → Click the activity link configured in step 8.

The BookRoll screen will be displayed with the Moodle user.