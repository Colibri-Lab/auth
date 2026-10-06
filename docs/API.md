---
title: Auth API
---

# Auth API

## Scope

This reference describes the public actions in `src/Auth/Controllers/` and the request methods used by the Auth PHP API client. It documents the current implementation; it is not a versioned OpenAPI contract.

## Base URL and Routes

Use the Auth host for the target environment. For example:

```text
https://auth.colibrilab.pro/modules/auth
```

Named controller actions use the Colibri route pattern:

```text
/{controller}/{action}.json
```

Controller names and action names are converted to lowercase kebab case. For example, `SessionController::Login()` is `POST /session/login.json`, and `MemberController::BeginPasswordResetProcess()` is `POST /member/begin-password-reset-process.json`.

The Auth PHP client uses the `.json` suffix. The checked-in Postman collection uses extensionless paths for its examples; keep the caller's route form consistent with the host application's routing configuration.

The Colibri RPC client sends POST requests by default. The controller methods do not consistently enforce HTTP verbs, so use POST with a JSON body for RPC actions unless an endpoint below explicitly describes a browser GET or the PHP client uses GET.

## Headers and Session

| Header or cookie | Purpose |
| --- | --- |
| `X-AppToken` | Identifies the registered Auth application for the application-aware controllers. It is not a user session token. |
| `Authorization` | May carry the current Auth session token. `Sessions::LoadFromRequest()` reads this header when the `cc-jwt` cookie is absent. |
| `cc-jwt` | Auth session cookie. The session loader prefers this cookie to the `Authorization` header. |
| `DeviceId` | Optional device identifier used by the Auth client and session/device logic. |
| `Colibri-Language` | Optional language preference for localized responses. |
| `X-AppName` | Application key used by `Autologin.Perform`. |

The module-level application check applies to `SessionController`, `MemberController`, `AppController`, and `InvitesController`. It resolves the application from the request and rejects unresolved applications with `403 Unauthorized`. This is separate from the per-action user-session checks listed below.

## Responses

Successful RPC actions generally return the Colibri response envelope:

```json
{
  "code": 200,
  "message": "ok",
  "result": {}
}
```

Many session-aware actions also issue or refresh the `cc-jwt` cookie. Validation failures commonly return `400`; authorization and credential failures commonly return `403`. Exact status and error bodies vary by action. Login can return status/code `206` while a second-factor code is required.

## Controller Actions

In the tables, paths are relative to `/modules/auth`. Body fields are JSON fields unless the row explicitly says query parameter or header. “Application” means the request must resolve to a registered Auth application; “session” means an authenticated member session is required.

### Default Controller

| Request | Input | Access and result |
| --- | --- | --- |
| `GET /` (`Controller.Index`) | None | Renders the Auth web interface. |
| `POST /bundle.json` (`Controller.Bundle`) | None | Builds or retrieves the current language/theme bundle and returns its JavaScript and CSS URLs. |
| `POST /bundle-all.json` (`Controller.BundleAll`) | None | Builds bundles for configured languages. Normally run as a maintenance/build operation. |

### Session

| Request | Input | Access and result |
| --- | --- | --- |
| `POST /session/start.json` | No body fields. | Application. Loads or creates the guest/current session, returns `result.session`, and sets the session cookie. |
| `POST /session/login.json` | `login`, `password`; optional `code` for second-factor verification. `login` may be a login, email, or phone. | Application; the current session must not already have a member. Returns the session and sets the cookie. A second-factor challenge can return `206`. |
| `POST /session/logout.json` | No body fields. | Application; clears the member from the current session and refreshes the cookie. |
| `POST /session/logout-from-all.json` | No body fields. | Application and authenticated session; clears the member from all of that member's sessions. |
| `GET` or `POST /session/decode.json` | No body fields. The session is taken from `cc-jwt` or `Authorization`. | Application. Returns the current session and refreshes the cookie. The PHP client's `Get()` method uses GET. |
| `POST /session/check-property.json` | `property` (`email` or `phone`), `value`. | Application; does not require an authenticated member. Returns `result.exists`. |
| `POST /session/login-by-creds.json` | `credentials` object containing `deviceId` and `rawId`. | Application; the current session must not already have a member. Authenticates a previously registered device credential and returns the session. |

### Application

| Request | Input | Access and result |
| --- | --- | --- |
| `POST /app/settings.json` | No body fields. | Application. Returns public application settings and the registration form schema. |
| `POST /app/session.json` | `token` (member token, not the session token). | Application and caller IP must exactly match the application's configured `allowed_ip`. Returns the first existing session for that member. Used by the PHP client's `Restore()` method; this is not a login endpoint. |

### Member

| Request | Input | Access and result |
| --- | --- | --- |
| `POST /member/register.json` | Required by the current application settings: `login`, `email`, `phone`, `password`, `confirmation`. Optional: `first_name`, `last_name`, `patronymic`, `gender`, `birthdate`, `role`, `invitation`, `email_confirmed`, `phone_confirmed`. | Application; no authenticated member required. Creates the member, starts a session, and sets the cookie. Email/phone may be generated when the application does not ask for them; `role` defaults to the application's default role. `invitation` is an invitation code. |
| `POST /member/begin-confirmation-process.json` | `property` (`email` or `phone`), `value`. | Application; starts a property confirmation. In development mode the response includes the confirmation `code`; otherwise it sends a confirmation message. |
| `POST /member/begin-password-reset-process.json` | `email`; `phone` is additionally required when the application is configured to ask for a phone number. | Application; the caller must not have an authenticated member session. Starts password recovery. Development mode returns the code; otherwise a reset message is sent. |
| `POST /member/begin-identity-update-process.json` | `property` (`email` or `phone`), `value` (new value). | Application and session. Starts confirmation for changing the current member's email or phone. |
| `POST /member/confirm-property.json` | `property`, `value`, `code`. | Application. Confirms a pending email/phone property confirmation. |
| `POST /member/reset-password.json` | `email`, `code`, `password`, `confirmation`; `phone` is additionally required when configured by the application. | Application; the caller must not have an authenticated member session. Resets the password after validating the confirmation code. |
| `POST /member/change-identity.json` | `property` (`email` or `phone`), `code`, `value` (new value). | Application and session. Updates the current member's confirmed identity. |
| `POST /member/change-password.json` | `original`, `password`, `confirmation`. | Application and session. Changes the current member's password after checking the original password. |
| `POST /member/block-account.json` | No body fields. | Application and session. Blocks the current member and clears that member from the current session. |
| `POST /member/toggle-two-factor-auth.json` | No body fields. | Application and session. Toggles the member's message-based two-factor setting. |
| `POST /member/toggle-two-factor-app-auth.json` | No body fields. | Application and session. Enables or disables app-based TOTP. When enabling, returns the setup secret, `totp` URI, and `qrcode`; confirm the generated code with `CheckTwoFactorAppAuth`. |
| `POST /member/check-two-factor-app-auth.json` | `code` (current TOTP code). | Application and session. Returns `result.checked`. |
| `POST /member/update-profile.json` | Required: `first_name`, `last_name`. Optional: `patronymic`, `gender`, `birthdate`, `avatar`. | Application and session. Updates the current member's profile. |
| `POST /member/update-password.json` | `current`, `new`. | Application and session. Updates the current member's password through the alternate password-update flow. |
| `POST /member/list.json` | `tokens` (array of member tokens). | Application and session; the current member's role must not be `user`. Returns a token-keyed member list. |
| `POST /member/list-by-role.json` | `role`. | Application and session. Returns members assigned to the specified role. |
| `POST /member/search.json` | `term` (string or array of member/invitation tokens). | Application and session. Searches members and invitations. |
| `POST /member/perform-mutation.json` | `member` (target member token), `mutation` (field/value object). | Application, session, and `administrator` role. Applies the mutation and sends a notice to the member. |
| `POST /member/change-role.json` | `email`, `role`. | Application, session, and `administrator` role. Changes the target member's role. |
| `POST /member/force-logout.json` | `token` (target member token). | Application and session. Invalidates the target member's sessions. The current implementation checks for a logged-in session but does not check an administrator role. |
| `POST /member/force-block.json` | `token` (target member token). | Application and session. Blocks the target member and invalidates their sessions. The current implementation checks for a logged-in session but does not check an administrator role. |
| `POST /member/request-autologin.json` | `token` (target member token), optional `return` URL. | Application, session, and application setting `autologin=true`. Returns a generated one-time link. |
| `POST /member/decrypt-message.json` | `message` (encrypted message). | Application and session. Decrypts a message for the current member. |
| `POST /member/encrypt-message.json` | `message` (plaintext), `for` (recipient member token). | Application and session. Encrypts a message for the recipient. |
| `POST /member/save-local-public-key.json` | `public` (public key). | Application and session. Saves the current member's local public key. |
| `POST /member/create-device.json` | `credentials` object containing `deviceId` and `rawId`. | Application and session. Registers device credentials for the current member. |

### Invitations

| Request | Input | Access and result |
| --- | --- | --- |
| `POST /invites/create.json` | Required: `email`. Optional: `fio`, `params`. | Application and authenticated session. Sends an invitation. If a member with that email already exists, returns that member instead. |
| `POST /invites/get.json` | `code`. | Application; no authenticated member required. Returns the invitation identified by the code. |

### Backend Management

These methods are not in `Module::NeedAuthorization`; the controller applies its own `manage`-domain check.

| Request | Input | Access and result |
| --- | --- | --- |
| `POST /backend/request-autologin.json` | `app` (target application token), `token` (member token), optional `return` URL. | `manage` domain; the target application must allow autologin. Returns a generated link. |
| `POST /backend/change-password.json` | `member` (member token), `password`. | `manage` domain. Changes the selected member's password. |

### Autologin

| Request | Input | Access and result |
| --- | --- | --- |
| `GET /autologin/perform.json?code=...` | Query parameter `code`; header `X-AppName` with the application key. | Resolves the application and one-time autologin code, creates a session, consumes the code, sets the session cookie, and redirects to the configured return URL. |

## PHP Client Mapping

`colibri/colibriauthclient` is a separate package. Its public requests map to the endpoints above:

| Client method | Auth endpoint | Notes |
| --- | --- | --- |
| `Get(sessionToken)` | `GET /session/decode.json` | Decodes the supplied session token. |
| `Restore(memberToken)` | `POST /app/session.json` | Requires the Auth application's `allowed_ip`; it restores an existing session by member token. |
| `List(sessionToken, tokens)` | `POST /member/list.json` | Lists members by token. |
| `Invite(sessionToken, email, phone, params)` | `POST /invites/create.json` | Creates an invitation. |
| `Search(sessionToken, term)` | `POST /member/search.json` | Searches by term or token list. |
| `ListByRole(sessionToken, role)` | `POST /member/list-by-role.json` | Lists members by role. |
| `PerformMutation(sessionToken, memberToken, mutation)` | `POST /member/perform-mutation.json` | Administrator operation. |
| `UpdateRole(sessionToken, email, role)` | `POST /member/change-role.json` | Administrator operation. |
| `UpdateProfile(sessionToken, fields...)` | `POST /member/update-profile.json` | Updates the current profile. |
| `CheckPropertyExists(sessionToken, property, value)` | `POST /session/check-property.json` | Checks email or phone existence. |
| `ForceLogout(sessionToken, mutationToken)` | `POST /member/force-logout.json` | Invalidates target sessions. |
| `ForceBlock(sessionToken, mutationToken)` | `POST /member/force-block.json` | Blocks the target member. |

The PHP client does not provide login, registration, or password-reset methods.

## Implementation Notes

- The module-wide application guard covers only `SessionController`, `MemberController`, `AppController`, and `InvitesController`. `BackendController` and `AutologinController` rely on their own checks.
- Session requirements and role checks are action-specific; do not infer them from the controller name alone.
- `SessionController::Login()` currently reads the payload's `password` field when assigning the second-factor `code`. The documented request field is `code`, but JSON-body second-factor login may not work until that implementation mismatch is fixed.
- The checked-in Postman collection calls `/member/update-identity`, while the current controller action is `ChangeIdentity` (generated action name: `change-identity`). Verify or update that collection entry before treating it as the current route contract.
- `LoginByCreds()` accepts `credentials.deviceId` and `credentials.rawId`; the current lookup is performed using `rawId`.
- Never put real application credentials, session tokens, passwords, or confirmation codes in URLs, logs, source control, or examples.
