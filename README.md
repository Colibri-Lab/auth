# LDAP

Read-only LDAP service: [configuration, CLI startup and tests](docs/LDAP.md).

# Security regression tests

Deployment checks and isolated HTTP fixtures: [security test instructions](tests/security/README.md).

# Password hashing

New, changed and reset passwords use PHP's Argon2id password hashing.
Deployments must provide PHP with Argon2id support.
Legacy `md5(Crypt::Encrypt(...))` hashes remain valid and are upgraded on the
next successful password login (after MFA, when enabled), including LDAP Bind.
Verification alone does not write to storage. Migration preserves the existing
password without reapplying the current password-strength policy.
Up-to-date hashes are not rewritten; persistence failures abort the login.
The existing 128-character password column accommodates Argon2id hashes.
No bulk password migration or client API change is required.

# миграция

## Migrating member roles

Existing databases need a staged migration during a maintenance window. Stop web
traffic and LDAP workers and back up the members table before proceeding.
First install the new `BackendController::MigrateMemberRoles()` action and its
launcher while keeping the old scalar storage definition active:

```sh
sh bin/auth-migrate-member-roles.sh localhost
```

Use the configured Manage host for that deployment. The CLI-only action converts
each existing nonempty role into `[{"role":"<existing-role>"}]`; null/empty roles
become `[]`. It builds a temporary JSON column and switches columns only after
the conversion succeeds. DDL is not transactional; a failure leaves the original
column and may leave `members_role_array` for inspection. Do not resume traffic
or blindly retry after an error.

Then immediately activate the updated `auth-storages.yaml` definition (including
removal of the scalar role index) and updated models before restarting traffic.
The action invalidates configured hosts' storage cache keys, including the
localhost/Manage key. Explicitly invalidate any additional concrete wildcard
hosts used by that deployment. Run `sh bin/model Auth.members` to refresh models
after activating the new definition. Do not run automatic schema migration with
the new JSON definition before converting legacy strings, or with the old
definition after conversion. New databases use the JSON definition directly.

The API now returns role arrays; consumers that compared `member.role` to a string
must check the array instead. See [API role contract](docs/API.md#member-roles).
 
cd ./web
php index.php [domain] / command=migrate
 
# создание моделей
 
cd ./Web
php index.php [domen] / command=models-generate storage=[storage name]
 
# настойка nginx
 
! обязательно
 
client_body_buffer_size 1m;
fastcgi_buffers 16 16k;
fastcgi_buffer_size 32k;
 
 
# дополнительные сведения о настройке модуля
! обязательно опишите какие необходимы вызовы скриптов при первичной настройке
! опишите какие необходимо запускать скрипты периодически
! запишите какие настройки за что отвечают