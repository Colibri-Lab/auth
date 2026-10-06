---
title: Auth Documentation
---

# Auth Documentation

## Getting Started

- [Module architecture](ARCHITECTURE.md) describes server-side checks, sessions, the client bundle, and installation.
- The [project README](../README.md) contains existing notes on migrations, model generation, and nginx configuration.
- [Composer package metadata](../composer.json) lists dependencies, the package name, and PSR-4 autoload mapping.

## Configuration

Configuration templates are located in `../src/Auth/config-template/`:

- `module-local.yaml`, `module-dev.yaml`, `module-test.yaml`, and `module-prod.yaml` contain Auth settings for each environment.
- `auth-storages.yaml` defines the module's storages.
- `auth-langtexts.yaml` contains the module's error and message texts.

During installation, `src/Auth/Installer.php` also modifies the consuming Colibri project's configuration and installs required resources. Check the Installer conditions for the selected mode before relying on those changes.

## Initial Setup

1. Install Composer dependencies and select the consuming project's environment mode.
2. Verify the generated Auth files in `config/`, the module registration in `modules.yaml`, and the domain settings in `hosts.yaml`.
3. Verify the database connection and enabled storage definitions.
4. Run migrations and generate models using the consuming project's tools. Use the current project runbook for the exact commands and domain; the examples in the Auth root README contain placeholders and their paths must be checked before use.
5. Verify that the Auth API and client assets are available on the selected environment's domain.

Do not put real application credentials, user tokens, or passwords in this document. Use the environment's approved secret store.

## Client Integrations

Auth provides two distinct integration layers:

- UI components and RPC wrappers are in `src/Auth/.Bundle/` and are available to consumers that load the Auth bundle.
- `colibri/colibriauthclient` is a separate PHP client for server-side integrations. Its methods work with existing credentials and sessions; it does not replace the login or registration forms.

For server-side checks, pass the user session according to the Auth contract. The application credential and session token serve different purposes.

## Operations

The Installer installs `auth-bundle.sh`. Document its exact purpose, arguments, and schedule only after checking the script and deployment configuration. Do not schedule a recurring run based on the filename alone.

Before a release, verify:

- migrations and generated model state;
- environment configuration and domains;
- session issuance and validation;
- TLS settings and allowed origins for browser requests;
- scheduled jobs, if any are configured in the environment.

## Maintaining the Documentation

When controllers or session handling change, update `ARCHITECTURE.md`. When installation, configuration, migrations, or recurring operations change, update this file and the root README.
