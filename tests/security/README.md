# Auth security regression tests

Run from the application's root, against a configured Auth HTTPS host:

```sh
AUTH_SECURITY_TESTS=1 sh bin/auth-security-tests.sh tasks_auth-v5.local.bsft.loc
```

The module installer installs the launcher into the application's `bin/`
directory. The execution chain is launcher -> `web/index.php` ->
`SecurityTestsController::Run()` -> `tests/security/Regression.php`.
The controller rejects HTTP requests, including PHP development-server requests.

## Safety and prerequisites

- Use a dedicated test deployment and database. Do not point the suite at production
  without an explicit decision to run active security probes there.
- PHP needs the application's dependencies and the cURL extension. The selected
  HTTPS host must be reachable and its certificate trusted by PHP/cURL. TLS
  verification remains enabled; redirects and outbound proxy use are disabled.
- `AUTH_SECURITY_TESTS=1` explicitly permits fixture writes. No existing account,
  application token or password is needed. Fixture credentials are random and
  never included in the report or command arguments.
- The suite creates one temporary application and uniquely named accounts,
  sessions, device credentials and reset codes. It uses only these identities for
  privileged operations. No emails or SMS messages are requested.
- Cleanup runs in `finally`: exact fixture accounts, dependent rows, observed
  session-cache keys and generated key files are removed. Empty fixture key
  directories are removed without recursively deleting shared directories.
  A killed process or disconnected HTTP server can leave fixtures behind:
  inspect only the exact `security-test-<run-id>` identities before cleanup.
- Run serially on a stable deployment. Allow a few minutes for HTTPS requests and
  RSA key generation. Tests intentionally wait two seconds for session expiration.

## Output and deployment gates

The launcher prints a JSON report containing assertion names, pass/fail results,
HTTP statuses, the run ID and cleanup status. It does not print response bodies,
passwords, reset codes, session tokens or application credentials.

Exit statuses:

| Status | Meaning |
|---|---|
| `0` | All assertions passed and cleanup completed. |
| `1` | At least one security assertion failed; do not approve deployment. |
| `2` | Incomplete/invalid report, missing opt-in, bootstrap, transport or cleanup error. |

The launcher validates both the report and process exit status because the
application entry point can catch bootstrap errors without returning a failing
process status. Do not treat an infrastructure error as a successful negative test.
Controller exceptions are reported by class only to avoid leaking sensitive data.
For diagnosis, inspect local application logs; do not publish their contents
without redaction.

## Coverage

The 48 assertions cover:

- Argon2id for new passwords, read-only legacy verification, gradual migration
  of an existing weak password after login, no migration on wrong credentials
  or pending/invalid MFA, and no rewrite of an up-to-date hash.
- Missing and invalid application credentials; guest profile mutation; HTTP
  access to the CLI-only runner.
- Registration with a requested administrator role and self-asserted email/phone
  confirmation.
- Session-bound email/phone verification, rejection of another guest's proof,
  successful verified registration and consumption of the verification records.
- Wrong-password rejection, successful login/decode, session data disclosure,
  cookie flags, token tampering, logout replay and expired authenticated sessions.
- Cross-account forced logout and blocking by a normal user, plus legitimate
  administrator operations and revocation of multiple target sessions.
- Device login without a signed challenge, with a mismatched device ID, while the
  feature is disabled, and for a blocked account.
- Wrong and valid password-reset codes, actual persisted password changes,
  existing-session revocation, single-use codes and explicit replay rejection.

Positive login controls must succeed before authenticated checks run. Reset
assertions inspect persisted password behavior, not just HTTP success statuses.
Security failures remain failures; tests are not relaxed to match a vulnerable
implementation.

This is not a comprehensive penetration test or a security certification. It does
not cover mail delivery, reset-code aging/rate limits, MFA challenge lifecycle,
CSRF/browser enforcement, invitation trust, application/tenant isolation, OAuth,
LDAP, concurrency races or infrastructure configuration. Existing LDAP tests are
separate. Run those and expand these scenarios when those contracts are defined.

## Deployment changes

- Public registration always assigns the application's `defaultrole`. Requested
  roles and invitation role metadata cannot grant administrator privileges.
  Assign privileged roles through the authenticated administration flow instead.
- Client-supplied contact-confirmation flags are ignored. Confirmation records
  must be verified by the same guest session and are consumed on registration.
  Pending pre-deployment confirmations must be requested again. Invitations alone
  do not prove email ownership.
- Authenticated sessions are rejected after `datecreated + expires`; blocked
  accounts also lose access on session resolution. Password reset revokes all
  authenticated sessions and consumed reset codes receive an explicit rejection.
  Previously overlong sessions may require users to log in again.
- Account owners can still revoke/block themselves. Only an authenticated
  `administrator` may do so to another account, following the module's existing
  administrator role name.
- The Auth cookie is `Secure`, `HttpOnly`, `SameSite=None`. The explicit `None`
  policy was selected by the user to preserve cross-site Auth integrations.
  Deploy the related
  Colibri Core changes too: `Web/Response.php` must honor the descriptor's
  `httponly`, and `ReactServer.php` must honor its `samesite`. The suite checks
  the deployed transport, not just the cookie descriptor.
- `SameSite=None` permits cross-site cookie transmission; it is not CSRF
  protection. Browser third-party-cookie restrictions can still apply.
  CSRF/browser enforcement requires a separate review and remains outside this
  suite's coverage.

## Deferred HIGH issue: device credential-ID authentication

The user explicitly postponed this fix during the local security review.
`SessionController::LoginByCreds()` and `MemberController::CreateDevice()` remain
unchanged. A known `rawId` is accepted without verifying a signed challenge or
binding the asserted device ID; the login endpoint also accepts blocked accounts.
Live account-state checks reject blocked sessions on subsequent requests, but do
not make the credential-ID login protocol secure.

The two assertions below deliberately remain failing:

- `device-id-without-proof-cannot-login`
- `blocked-account-cannot-use-device-login`

Consequently the full deployment gate still exits `1`; there is no allowlist or
success-shaped bypass for this risk. Decide separately between disabling the
legacy flow and implementing a real possession-proof protocol with server-side
public-key registration, challenge validation, expiry/replay protection and
account-state checks. A WebAuthn credential ID must not be treated as a password.
