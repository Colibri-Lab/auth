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