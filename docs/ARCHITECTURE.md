---
title: Auth Architecture
---

# Auth Architecture

## Purpose

`colibri/auth` is an authorization module for Colibri applications. The Composer package `colibri/auth` maps the `App\Modules\Auth\` namespace to `src/Auth/` and depends on Colibri Core and UI, JWT, and a QR code generator.

## Server Module

The entry point is `App\Modules\Auth\Module` in `src/Auth/Module.php`.

During initialization, the module subscribes to `RpcGotRequest`. For controllers listed in `Module::NeedAuthorization`, it loads the application associated with the current request. If the application cannot be resolved, the request is cancelled with a `403 Unauthorized` response. The protected controller list is explicit; a controller is not covered by this check merely because it belongs to the Auth module.

`Module::LoadApplication()` resolves the application through `Applications::LoadFromRequest()`. `Module::GetSession()` lazily loads and caches the current session through `Sessions::LoadFromRequest()`.

Main server-side components:

- `Controllers/SessionController.php` handles session creation, login, logout, and decoding.
- `Controllers/MemberController.php` handles member and profile operations.
- `Controllers/AppController.php` provides application settings and the supported session restoration flow.
- `Controllers/InvitesController.php` and `Controllers/AutologinController.php` handle invitations and automatic login.
- `Models/` and `config-template/auth-storages.yaml` contain the models and storage definitions.

## Application and Session Credentials

Integrations must distinguish between two credentials:

1. The application credential is used to resolve the registered Auth application.
2. The user session is loaded by `Sessions::LoadFromRequest()` from the `cc-jwt` cookie or the `Authorization` header.

The application credential does not replace the user session. Do not put real keys or tokens in documentation, source code, or public client-side resources.

## Client Bundle

`src/Auth/.Bundle/` contains the module's JavaScript API and Colibri UI components. During initialization, Auth creates the `app.auth` store and the `Auth.Session`, `Auth.App`, and `Auth.Members` objects. The login, registration, password reset, and profile forms use these APIs and expose components under `App.Modules.Auth.Components`.

The client bundle provides the interface and RPC wrappers. Server-side module and controller logic remains responsible for authorization checks; hiding client-side UI is not an authorization boundary.

## Installation and Configuration

Environment-specific configuration templates are located in `src/Auth/config-template/`: `module-local.yaml`, `module-dev.yaml`, `module-test.yaml`, and `module-prod.yaml`, along with storage and language-text templates.

`src/Auth/Installer.php` contains the package setup procedure. It registers Auth in the host application's module configuration, configures domains and config files, and installs the bundle script and styles. In local mode, files are symlinked; in other modes, they are copied. Before rerunning installation, account for existing files and host application configuration.

## PHP API Client

`colibri/colibriauthclient` is a separate Composer package used by server applications to work with the Auth API. It is not a UI component and is not part of `colibri/auth`; refer to that client's documentation and source code for its contract.
