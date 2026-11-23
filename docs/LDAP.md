# Auth LDAP service

Experimental read-only LDAPv3 using `Colibri\Threading\Worker` and native PHP
streams. No ReactPHP, new libraries or external LDAP server products. HTTP
continues to use nginx/PHP-FPM; LDAP runs in a separate PHP worker process.

## Startup

Requirements: an installed Colibri booth, PHP 8.3+ CLI with Auth's extensions,
database access, and OpenSSL for TLS. Optional PCNTL enables graceful shutdown.
Run as the application service user, not root. Restrict access with a firewall.

From the booth root, supply `AUTH_LDAP_APP_TOKEN` through a protected environment
file or supervisor environment. Never commit it or put it in command arguments.
The token must match the existing application selected by the second script argument.
Set `AUTH_HOST` to an Auth hostname configured in this booth's `config/hosts.yaml`,
not the Tasks website hostname. The first argument selects Colibri configuration;
`listen` selects the listening interface. They are separate settings.

The module installer installs `auth-ldap.sh` into the booth's `bin/` directory.
The script invokes `LdapController::Start()` through `web/index.php`; the
CLI-only action starts `LdapWorker` through `Colibri\Threading\Process`.
The application token is inherited through the environment, not serialized into
the worker's command-line parameters. The action returns the spawned worker PID;
this is not confirmation that LDAP has finished starting. Check the listener
with an LDAP client before using it.

```sh
sh bin/auth-ldap.sh "$AUTH_HOST" tasks listen=tcp://127.0.0.1:1389
```

Encrypted LDAP on test server `10.10.10.8`:

```sh
sh bin/auth-ldap.sh "$AUTH_HOST" tasks \
  listen=tls://10.10.10.8:1636 \
  cert=/path/to/fullchain.pem key=/path/to/private-key.pem
```

Clients use `ldaps://<certificate-hostname>:1636`. The hostname must resolve to
the server and match the certificate. For connections by IP, the certificate
must include that IP in subjectAltName. TLS 1.2/1.3 are supported, not StartTLS.

Only for an isolated test network, plaintext can be explicitly enabled:

```sh
sh bin/auth-ldap.sh "$AUTH_HOST" tasks \
  listen=tcp://10.10.10.8:1389 allow-insecure=true
```

Without TLS, Simple Bind transmits passwords in cleartext. Non-loopback plaintext
is rejected without `allow-insecure=true`. No migrations or new tables are required.
The application must already exist; an Auth hostname does not create it.

## Supervision

The controller invocation exits after spawning the worker; the LDAP listener
continues in the child process. Stop that PID with SIGTERM/SIGINT, not the shell
script's PID. Do not start the action through HTTP. A foreground `Type=simple`
systemd unit cannot supervise this launcher as if it were the listener itself.
Configure supervision of the returned worker PID separately if required.

The protected launcher environment supplies `AUTH_HOST`, `AUTH_LDAP_APP_TOKEN`
and the booth's `COLIBRI_MODE` when needed. Without PCNTL, SIGTERM terminates
the process directly instead of invoking the graceful shutdown handler.

## Directory and authentication

For `tasks`, the base DN is `dc=tasks`. Users live at
`uid=<member-token>,ou=users,dc=tasks`; roles at
`cn=<application-role-name>,ou=groups,dc=tasks`.

- Users expose `objectClass`, `uid`, `cn`, `sn`, `givenName`, `displayName`,
  `mail`, `telephoneNumber`, `entryUUID` and `memberOf`. `uid` includes the token
  and login when present. `entryUUID` formats the stable token as a UUID.
- Groups come from the application's `params.roles`: `groupOfNames` with
  `cn`, `description` and `member` user DNs. Empty groups contain the non-account
  placeholder `cn=empty,dc=tasks`, since `groupOfNames.member` is required.
- Bind accepts a canonical user DN or bare login/email/phone and calls
  `Member::Authorize()`. Password storage is unchanged. No passwords, 2FA secrets
  or application tokens are returned; web sessions are not created.
- Users belong to every assigned role group configured by the selected application;
  `memberOf` contains all corresponding group DNs.
- Only unblocked members without email/app 2FA, with at least one role in the selected
  application, can authenticate or appear in this directory. LDAP does not
  bypass 2FA or send confirmation messages.
- Auth stores users globally, without a separate application membership relation.
  A role name shared by two applications admits the same eligible users to both.
  This service does not invent a membership relation.
- Every admitted user can search the whole selected directory. No separate
  administrator/service-account bypass or anonymous user search is provided.
- Account state, application token validity and role configuration are rechecked
  on subsequent requests. A failed rebind clears the previous identity.

Typical client settings:

```text
Base DN:       dc=tasks
User base:     ou=users,dc=tasks
User filter:   (&(objectClass=inetOrgPerson)(uid=<login>))
Email filter:  (&(objectClass=inetOrgPerson)(mail=<email>))
Group base:    ou=groups,dc=tasks
Group filter:  (objectClass=groupOfNames)
Group members: member
User groups:   memberOf
```

Search-then-bind clients first bind as an ordinary eligible Auth account to
discover the target user's canonical DN. Anonymous access is limited to
base-scope Root DSE. Empty-name/empty-password Bind switches to anonymous;
named accounts cannot bind with empty passwords.

## Protocol subset and limits

- LDAPv3 simple Bind, Unbind, Search and Root DSE discovery.
- Base, one-level and subtree scopes using canonical directory DNs.
- Equality, presence, substring, AND, OR and NOT filters. Comparisons are
  case-insensitive byte-string matches, not a complete schema matching engine.
- Explicit attribute lists, `*`, operational attributes through `+`, `1.1`
  and `typesOnly`. TCP fragmentation, pipelining and partial writes are handled.
- Unknown critical controls are rejected; noncritical controls are ignored.
  Paging controls are neither advertised nor implemented.
- No writes, password changes, referrals, SASL, schema discovery, StartTLS or
  Active Directory compatibility. Unsupported operations return an error.
- Searches are synchronous. Abandon has no response and cannot interrupt a
  search already executing. Database calls depend on the driver's timeouts.
- Maximum incoming message: 1 MiB; search response: 2 MiB; queued connection
  output: 4 MiB; simultaneous clients: 64.
- Searches return at most 500 entries, run for at most 5 seconds between directory
  iterations, and scan at most 10,000 active database rows. Smaller client limits
  are respected. Limit errors are explicit, not truncated success responses.
- Filter depth: 20 maximum, at most 100 direct boolean clauses.
- Idle connections expire after 60 seconds; incomplete TLS handshakes after 5.
- Bind budget: 30 requests per remote IP per 60 seconds, including successful
  and anonymous Bind. Excess connections close. The budget survives reconnects;
  clients sharing an IP share the budget. This is prototype protection, not
  distributed rate limiting.

## Verification

No test package installation is required:

```sh
php vendor/colibri/auth/tests/ldap/run.php "$PWD"
```

Tests use fixture accounts, temporary certificates and ephemeral loopback ports.
They cover BER bounds/fragmentation, authentication, filters/scopes, attributes,
groups, rebind, revocation, limits, actual TCP/TLS sockets and certificate
verification. Real Auth users are not modified; test processes and temporary
certificates are cleaned up.

Before production exposure, verify every target LDAP client and review
authentication policy, directory disclosure, schema compatibility and load.