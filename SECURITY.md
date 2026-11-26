# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Report it privately through the contact form of the production site, with
"Security" as the subject, or by a private GitHub Security Advisory on this
repository if you have access. Include the affected URL or file, the steps to
reproduce and the impact you expect.

You will get an acknowledgement within 5 working days. A fix, or a mitigation
plan, follows within 30 days for confirmed issues.

## Supported versions

Only the `main` branch, which is what runs in production, receives fixes.

## How this project handles security

- **Secrets never live in Git.** Database credentials, the hash salt, SMTP
  credentials and future API keys come from environment variables
  (see `drupal/web/sites/default/settings.php`). Future AI provider keys are
  stored with the Key module using its environment-variable provider.
- **Secret scanning** runs on every commit (gitleaks pre-commit hook) and on
  every pull request and push to `main` (CI job `secret-scan`, full history).
- **Dependencies** are updated by Dependabot (Composer, GitHub Actions, pip).
  `composer audit` runs in CI, and Drupal security advisories
  (<https://www.drupal.org/security>) are applied within 48 hours for
  "critical" and "highly critical" ratings.
- **Least privilege:** the `editor`, `reviewer` and `api_reader` roles have
  only the permissions they need (see `docs/architecture.md`). JSON:API is
  read-only.
- **No third-party trackers:** no analytics, no external fonts, no CDN scripts
  (see ADR 0003).
