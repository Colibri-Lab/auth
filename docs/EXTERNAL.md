---
title: External Auth Forms
---

# Integrating Auth Forms in TargetModule

`TargetModule` can load the Auth UI bundle from a remote Auth host at runtime. The bundle defines `App.Modules.Auth` and its form components; `TargetModule` initializes the Auth client, starts a session, and places the forms in its own UI container.

## Environment-Specific Module Settings

Add the Auth service mapping to the consuming module's `config-template/module-<mode>.yaml` files. For example, in `config-template/module-local.yaml`:

```yaml
config:
    services:
        auth:
            url: auth.example.com
            cookie: cc-jwt
            token: "REPLACE_WITH_LOCAL_AUTH_APP_TOKEN"
```

Add the same keys to `module-dev.yaml`, `module-test.yaml`, and `module-prod.yaml`. Set the Auth hostname and application credential for each environment; do not copy a local credential into another environment.

The TargetModule settings action should read `config.services.auth` and include that object in its response as `settings.services.auth`. The browser-side loader below reads these settings from the TargetModule store.

## Settings

The client expects these properties in `settings.services.auth`:

| Setting | Purpose |
| --- | --- |
| `url` | Auth hostname without a scheme, for example `auth.example.com`. The loader prefixes it with `https://`. |
| `cookie` | Auth session cookie name passed to `Auth.InitializeModule()`. |
| `token` | Auth application credential sent by the browser as `X-AppToken`. It is not a member session token. |

The settings are read from the TargetModule store, for example with `this._store.AsyncQuery('target.settings')`. Use an application credential intended for client-side Auth RPC calls. Do not use a member session token or a private server password, and do not commit real values to documentation or source control.

## Load and Initialize Auth

Add a loader to `TargetModule`. It requests the bundle descriptor, loads its JavaScript and CSS assets, then initializes the global Auth client from the settings:

```js
async LoadAuthModuleIfNotLoaded() {
    if (typeof Auth !== 'undefined' && Auth.IsReady) {
        return;
    }

    const settings = await this._store.AsyncQuery('target.settings');
    const auth = settings.services.auth;
    const language = settings.params?.lang?.name || null;
    const headers = language ? { 'Colibri-Language': language } : {};
    const response = await Colibri.IO.Request.Get(
        'https://' + auth.url + '/modules/auth/bundle.json',
        {},
        headers
    );

    if (response.status !== 200) {
        throw new Error('Unable to load the Auth bundle');
    }

    const files = typeof response.result === 'string'
        ? JSON.parse(response.result)
        : response.result;

    await Promise.all([
        Colibri.Common.LoadScript(files.js, 'target_auth_js'),
        Colibri.Common.LoadStyles(files.css, 'target_auth_css')
    ]);

    Auth.InitializeModule(
        true,
        auth.cookie,
        'https://' + auth.url,
        auth.token,
        language
    );
}
```

Call this method before accessing `Auth.Store`, `Auth.Session`, or `App.Modules.Auth.Components`. The Auth bundle endpoint returns the JavaScript and CSS asset URLs; the response result may be either an object or a JSON string.

## Start and Observe the Session

After Auth initialization, subscribe to the `auth.session` store path and start the session. A session with `member` represents an authenticated user; a guest session has no member.

```js
async InitializeAuth() {
    await this.LoadAuthModuleIfNotLoaded();

    Auth.Store.AddPathHandler('auth.session', session => {
        if (session && session.member) {
            this._authContainer.Hide();
            this._applicationContainer.Show();
        } else {
            this._applicationContainer.Hide();
            this._authContainer.Show();
        }
    });

    await Auth.Session.Start();
}
```

The Auth client manages the `cc-jwt` session cookie. Keep TargetModule's own authorization decisions on the server as well; hiding protected UI is not an access-control check.

## End-to-End Sign-In Flow

The Auth application credential and the member session token are different values:

- `settings.services.auth.token` identifies the TargetModule application to Auth and is sent as `X-AppToken`.
- `Auth.Store.Query('auth.session').token` is the current Auth session token. It is not `session.member.token`, which identifies the member.

The complete browser-to-server flow is:

1. `Auth.Session.Start()` reads the Auth session cookie `cc-jwt`, or creates a guest session if there is no cookie.
2. `LoginForm` calls `Auth.Session.Login(...)`. Auth associates the member with the session, returns the session data, and updates the `cc-jwt` cookie. The current session is available at `auth.session` in `Auth.Store`.
3. When `auth.session` changes, mirror `session.token` into the TargetModule browser storage. A custom key such as `robin` can be used when the TargetModule backend expects that header:

```js
Auth.Store.AddPathHandler('auth.session', session => {
    if (session && session.token) {
        App.Browser.Set('robin', session.token);
    }
});
```

4. Add the stored session token to requests from TargetModule to its own backend:

```js
Call(controller, action, params = null, headers = {}, ...rest) {
    headers = headers || {};
    headers['robin'] = App.Browser.Get('robin');
    return super.Call(controller, action, params, headers, ...rest);
}
```

`robin` is an application-defined forwarding header, not a standard Auth header. Use the same name on both sides. The value is a bearer session credential accessible to client-side JavaScript; use HTTPS and account for the XSS risk of browser-readable storage.

5. In a TargetModule controller or module service, read the forwarded token and validate it with the PHP Auth client. Construct that client from the server-side Auth settings (`config.services.auth.url` and `config.services.auth.token`), then pass the received session token to `Get()`:

```php
$sessionToken = App::$request->headers->{'robin'}
    ?? App::$request->cookie->{'robin'}
    ?? null;

if (!$sessionToken) {
    throw new PermissionDeniedException(PermissionDeniedException::PermissionDeniedMessage, 403);
}

$authResponse = $this->GetAuthClient()->Get($sessionToken);
$session = $authResponse->session ?? null;
if (!$session || !$session->member) {
    throw new PermissionDeniedException(PermissionDeniedException::PermissionDeniedMessage, 403);
}
```

### Add the PHP Client to TargetModule

Declare the client as a dependency in the TargetModule package's `composer.json`:

```json
{
    "require": {
        "colibri/colibriauthclient": "dev-master"
    }
}
```

Run Composer in the consuming project so the package is installed and available through Composer autoloading. In the TargetModule module class, cache one client instance and build it from the server-side module settings:

```php
use ColibriAuthClient\Client as AuthClient;

/**
 * @var AuthClient|null
 * @private
 * @ignore
 */
private ?AuthClient $_authClient = null;

/**
 * Returns the Auth API client.
 *
 * @public
 * @return AuthClient Auth API client.
 */
public function GetAuthClient(): AuthClient
{
        if (!$this->_authClient) {
                $config = $this->Config()->Query('config.services.auth')->AsObject();
                $this->_authClient = new AuthClient($config->url, $config->token);
        }

        return $this->_authClient;
}
```

`url` is the Auth hostname without a scheme; the client builds HTTPS URLs. `token` is the Auth application credential. `Get($sessionToken)` takes the user session token and calls Auth's session-decode endpoint using that token as the authorization credential. Do not pass `session.member.token` to `Get()`.

The current `colibri/colibriauthclient` implementation disables TLS certificate verification on its requests. Account for this before using it across an untrusted network.

Only treat the request as authenticated after the Auth API validates the session and the returned session has a member. Do not trust the forwarded token, `member.token`, or client-side store data on their own. A server may cache decoded sessions briefly, but the cache must be invalidated or kept short enough to respect Auth logout and revocation.

6. On logout, perform TargetModule-specific cleanup first if needed, then call `Auth.Session.Logout()`. That Auth call clears the member from the remote session and updates the Auth cookie/store. A TargetModule `Logout` action that only changes application state (for example, online presence) does not replace `Auth.Session.Logout()`.

The browser storage key may continue to contain the token for the resulting guest session. Authorization must depend on the server-validated session's `member`, not merely on whether the browser key exists.

## Host the Forms

Create an Auth container in TargetModule with a child component or pane that will hold the active form. Instantiate the Auth components after loading the bundle:

```js
const form = new App.Modules.Auth.Components.LoginForm('login-form', formHost);
form.Show();
```

Available form components include:

- `App.Modules.Auth.Components.LoginForm`
- `App.Modules.Auth.Components.RegisterForm`
- `App.Modules.Auth.Components.ResetForm`
- `App.Modules.Auth.Components.ProfileForm`
- `App.Modules.Auth.Components.ChangeIdentityForm`
- `App.Modules.Auth.Components.ChangePassForm`

The forms call `Auth.Session` or `Auth.Members` themselves. The host container should manage form creation, disposal, and navigation; it should not duplicate credential handling or Auth RPC requests.

## Form Navigation

Keep a small host-side state such as `login`, `register`, or `reset`. Dispose the previous form when switching and listen to the forms' events:

| Form | Event | Host action |
| --- | --- | --- |
| `LoginForm` | `RegisterButtonClicked` | Show `RegisterForm`. |
| `LoginForm` | `ResetButtonClicked` | Show `ResetForm`. |
| `RegisterForm` | `LoginButtonClicked` | Return to `LoginForm`. |
| `ResetForm` | `LoginButtonClicked` | Return to `LoginForm`. |
| `RegisterForm` | `ExternalValidation` | Optionally run host-specific validation and report the result through the provided validator. |

For invitation flows, pass the invitation data to `RegisterForm.Show(...)`. Successful login and registration update `Auth.Store` at `auth.session`; use the session path handler to switch from the forms to the authenticated application view.

## Profile and Settings Page Ownership

Auth does not provide or automatically render a universal profile/settings page. `TargetModule` owns that page: its layout, sections, labels, visibility rules, and application-specific controls. Auth provides the current member data, application capabilities, reusable forms, and RPC methods.

Read the member from `Auth.Store.Query('auth.session.member')` and the Auth application capabilities from `await Auth.App.Settings()`. Components using the root `App.Store` can bind to `app.auth.session.member`. Use the returned capability flags to show only controls enabled for this Auth application:

| Profile section | Data and visibility | Auth component or operation |
| --- | --- | --- |
| Name and profile details | `first_name`, `last_name`, `patronymic`, `birthdate`, `gender` on the member. | `ProfileForm`; set `simple = false` to include the extended fields. |
| Email | `member.email`, `member.email_confirmed`; show when `settings.params.askforemail` is enabled. | `ChangeIdentityForm` with `property = 'email'`; Auth runs the confirmation flow. |
| Phone | `member.phone`, `member.phone_confirmed`; show when `settings.params.askforphone` is enabled. | `ChangeIdentityForm` with `property = 'phone'`; Auth runs the confirmation flow. |
| Message-based two-factor | `member.two_factor`; show controls when `settings.params.enable_two_factor_authentification` is enabled. | `Auth.Members.ToggleTwoFactorAuth()`. |
| App-based TOTP | `member.two_factor_application`; show controls when `settings.params.enable_two_factor_authentification_applications` is enabled. | `Auth.Members.ToggleTwoFactorAppAuth()`, then verify the generated code with `Auth.Members.CheckTwoFactorAppAuth(code)`. |
| Device credentials | Availability is indicated by `settings.params.enable_device_authentification`. | `Auth.Members.CreateDeviceCredentials()`. |
| Password and sessions | Actions are available for the signed-in member. | `ChangePassForm`, `Auth.Session.Logout()`, and `Auth.Session.LogoutFromAll()`. |
| Avatar, local keys, app preferences, and app data | May use Auth member fields, but upload pipelines and application-only settings belong to `TargetModule`. | TargetModule-owned UI and handlers; call Auth profile APIs where appropriate. |

A small initialization example for a TargetModule-owned page:

```js
async LoadProfileSettings() {
    const settings = await Auth.App.Settings();
    const member = Auth.Store.Query('auth.session.member');

    this.Children('profile/email').shown = Boolean(settings.params.askforemail);
    this.Children('profile/phone').shown = Boolean(settings.params.askforphone);
    this.Children('profile/two-factor').shown = Boolean(settings.params.enable_two_factor_authentification);
    this.Children('profile/two-factor-app').shown = Boolean(settings.params.enable_two_factor_authentification_applications);
    this.Children('profile/form').value = member;
}
```

The page should subscribe to `auth.session` or bind to the member path so profile changes are reflected. Auth does not decide which profile controls the application exposes; the consuming module makes that choice from the settings and operations available to it.

## Email and SMS Delivery Proxies

Auth can delegate confirmation-message delivery to endpoints owned by the consuming application. In `Application.php`, the application model exposes `params` as a `ParamsObjectField`; `ParamsObjectField.php` defines its optional `proxies` object through `ParamsProxiesObjectField`. The `email` and `phone` entries are callback URL values saved on each Auth Application record, not settings in `config-template/module-<mode>.yaml`.

The serialized Application data has this shape:

```json
{
    "key": "target-module",
    "params": {
        "proxies": {
            "email": "https://target.example.com/modules/target-module/external/send-email.json",
            "phone": "https://target.example.com/modules/target-module/external/send-sms.json"
        }
    }
}
```

The TargetModule's actual SMTP and SMS-provider configuration remains in its own environment-specific module configuration. Only the callback URLs belong in the Auth Application record.

`email` is used for email confirmations and for login/password-reset messages. `phone` is used for phone confirmations. If a proxy URL is not configured, Auth uses its own default `Notices::Send()` delivery path.

For a configured proxy, Auth sends a JSON `POST` request with this payload:

```json
{
    "recipient": "user@example.com",
    "subject": "Confirmation message",
    "body": "Your confirmation code is ...",
    "attachments": []
}
```

The SMS proxy receives the same payload shape; `recipient` contains the phone number. The SMS action normally uses `recipient` and `body` and ignores `subject` and `attachments`. Auth considers delivery successful only when the proxy responds with HTTP `200`.

The consuming application's `ExternalController` implements the two callback actions:

| Action | Required fields | Expected behavior |
| --- | --- | --- |
| `SendEmail` | `recipient`, `subject`, `body`; `attachments` is optional. | Validate the recipient as an email address, send through the TargetModule's configured mail provider, and return HTTP `200` only on success. |
| `SendSms` | `recipient`, `body`. | Sanitize the message body as required by the SMS provider, send through the TargetModule's configured SMS provider, and return HTTP `200` only on success. |

The provider calls are the application-specific part of these methods. Keep SMTP and SMS-provider credentials in the TargetModule's environment-specific `module-<mode>.yaml` configuration; the Auth application proxy settings should contain only the callback URLs.

The current Auth proxy request sends no session or application-authentication header, and its HTTP client has TLS certificate verification disabled. Do not assume the TargetModule's normal user-session guard authenticates these callbacks. Protect the endpoints with an explicitly designed server-to-server policy, and enable TLS certificate verification before relying on these requests across an untrusted network.

## Cross-Origin Requirements

When TargetModule and Auth use different origins, configure the Auth host's CORS and cookie settings for the TargetModule origin. Credentialed requests must be allowed, and the session cookie must be usable in the deployed browser context. Browsers may restrict cookies for unrelated local domains as third-party cookies.

Use HTTPS for the bundle URL and RPC host. Keep `url`, `cookie`, and the application credential environment-specific. The separate Composer package `colibri/colibriauthclient` is for server-side API requests; it does not load or render these UI forms.
