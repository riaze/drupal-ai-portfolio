# infra (placeholder)

Infrastructure as code for hosting will live here (provisioning, web server
and PHP-FPM configuration, backups, TLS). The hosting target is not chosen
yet; this phase only makes the Drupal application ready to deploy.

What the application already expects from any hosting target is documented in
[`docs/architecture.md`](../docs/architecture.md#production-environment-contract):
the environment variables, PHP version and extensions, writable directories
and cron.
