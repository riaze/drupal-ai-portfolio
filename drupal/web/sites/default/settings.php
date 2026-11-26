<?php

/**
 * @file
 * Drupal site settings.
 *
 * This file is committed: it must never contain a secret. Every value that is
 * secret or differs per environment is read from an environment variable, see
 * docs/architecture.md#production-environment-contract for the full list.
 *
 * Load order:
 *   1. Values from environment variables (production, CI).
 *   2. settings.ddev.php when running inside DDEV (generated, not committed).
 *   3. settings.local.php when present (developer overrides, not committed).
 */

declare(strict_types=1);

/**
 * Reads an environment variable, returning NULL when unset or empty.
 */
$env = static function (string $name): ?string {
  $value = getenv($name);
  return ($value === FALSE || $value === '') ? NULL : $value;
};

// ---------------------------------------------------------------------------
// Database.
// ---------------------------------------------------------------------------
$databases = [];
if ($env('DRUPAL_DB_NAME') !== NULL) {
  $databases['default']['default'] = [
    'driver' => $env('DRUPAL_DB_DRIVER') ?? 'mysql',
    'database' => $env('DRUPAL_DB_NAME'),
    'username' => $env('DRUPAL_DB_USER') ?? '',
    'password' => $env('DRUPAL_DB_PASSWORD') ?? '',
    'host' => $env('DRUPAL_DB_HOST') ?? 'localhost',
    'port' => $env('DRUPAL_DB_PORT') ?? '3306',
    'prefix' => '',
    'collation' => 'utf8mb4_general_ci',
  ];
}

// ---------------------------------------------------------------------------
// Core settings.
// ---------------------------------------------------------------------------
$settings['hash_salt'] = $env('DRUPAL_HASH_SALT') ?? '';
$settings['update_free_access'] = FALSE;
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = $env('DRUPAL_PRIVATE_PATH') ?? '../private';
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['migrate_node_migrate_type_classic'] = FALSE;

// Development-only modules are never written to config/sync, so production
// (composer install --no-dev) can import the configuration as is.
$settings['config_exclude_modules'] = ['devel'];

// Comma-separated list of host name regular expressions, for example
// "^example\.com$,^www\.example\.com$".
if ($env('DRUPAL_TRUSTED_HOSTS') !== NULL) {
  $settings['trusted_host_patterns'] = array_map('trim', explode(',', $env('DRUPAL_TRUSTED_HOSTS')));
}

// Behind a reverse proxy or load balancer (comma-separated IP addresses).
if ($env('DRUPAL_REVERSE_PROXY_ADDRESSES') !== NULL) {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = array_map('trim', explode(',', $env('DRUPAL_REVERSE_PROXY_ADDRESSES')));
}

// ---------------------------------------------------------------------------
// Production performance: errors are logged, never displayed.
// ---------------------------------------------------------------------------
$config['system.logging']['error_level'] = 'hide';

// ---------------------------------------------------------------------------
// Email: SMTP through core's Symfony mailer plugin when SMTP_HOST is set.
// Without it, Drupal falls back to PHP mail().
// ---------------------------------------------------------------------------
if ($env('SMTP_HOST') !== NULL) {
  $config['system.mail']['interface']['default'] = 'symfony_mailer';
  $config['system.mail']['mailer_dsn'] = [
    // "smtp" (STARTTLS when the server offers it) or "smtps" (implicit TLS).
    'scheme' => $env('SMTP_SCHEME') ?? 'smtp',
    'host' => $env('SMTP_HOST'),
    'port' => (int) ($env('SMTP_PORT') ?? 587),
    'user' => $env('SMTP_USER'),
    'password' => $env('SMTP_PASSWORD'),
    'options' => [],
  ];
}

// Personal details stay out of Git: the site address, the contact form
// recipient and the canonical base URL come from the environment.
if ($env('DRUPAL_SITE_MAIL') !== NULL) {
  $config['system.site']['mail'] = $env('DRUPAL_SITE_MAIL');
}
if ($env('DRUPAL_CONTACT_RECIPIENT') !== NULL) {
  $config['contact.form.feedback']['recipients'] = [$env('DRUPAL_CONTACT_RECIPIENT')];
}
if ($env('DRUPAL_BASE_URL') !== NULL) {
  $config['simple_sitemap.settings']['base_url'] = rtrim($env('DRUPAL_BASE_URL'), '/');
}

// ---------------------------------------------------------------------------
// Local environments.
// ---------------------------------------------------------------------------
// Automatically generated include for settings managed by ddev.
if (getenv('IS_DDEV_PROJECT') == 'true' && file_exists(__DIR__ . '/settings.ddev.php')) {
  include __DIR__ . '/settings.ddev.php';
}

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
