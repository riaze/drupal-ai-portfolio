# ADR 0001: Drupal core over a distribution

- Status: Accepted
- Date: 2026-11-26

## Context

The site is the portfolio of a Drupal developer: a small, bilingual,
content-focused site that must stay fast, secure and cheap to maintain, and
whose codebase is itself a showcase. Starting points considered:

- **Drupal CMS**, the official distribution built on recipes. It preinstalls
  a broad set of contributed modules and opinionated configuration (admin
  experience, SEO, forms, privacy tooling, optional AI features).
- **Another distribution or starter kit** from an agency.
- **Drupal core** (`drupal/recommended-project`) with the Standard profile and
  only the modules the site needs.

## Decision

Use Drupal core 11 with the Standard profile, add a short, explicit list of
contributed modules through Composer (Drush, admin_toolbar, key, honeypot,
token, pathauto, metatag, simple_sitemap; devel for development), and keep
all configuration in `drupal/config/sync`.

## Consequences

- Every module, permission and configuration object is deliberate and
  reviewable in Git; the attack surface and the update workload stay small.
- Upgrades follow core's release cycle (11.5 LTS, then Drupal 12) without
  waiting for a distribution to catch up.
- Features a distribution would bring for free (a richer admin experience,
  cookie consent tooling, AI tooling) must be added on purpose. For this site
  that is the point: AI features are built as an integration (ADR 0002) and no
  cookie consent is needed (ADR 0003).
- Drupal 11.4's Standard profile no longer creates Page and Article types
  (they moved to recipes); all five content types are defined in this
  project's configuration.

## Alternatives

- **Drupal CMS:** the fastest start, but many of its modules and recipes
  would be unused or removed, and its configuration would be harder to keep
  minimal and fully owned.
- **Agency starter kit:** adds a third-party layer with its own upgrade
  schedule and conventions, for no clear gain on a site of this size.
