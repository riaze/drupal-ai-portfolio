# ai-service (placeholder)

This directory will hold the Python service that provides the AI features in
later phases. **Nothing is installed or running yet**, on purpose: the Drupal
foundation ships first, and the AI pieces plug into the seams prepared for
them.

## Planned responsibilities

| Phase | Capability | Talks to Drupal through |
| --- | --- | --- |
| 2 | LLM suggestions (article summaries, alt text, tags) written back as *suggestions* that a human approves | Drupal `ai_bridge` module service, field `field_ai_review_status` |
| 3 | RAG assistant over published content (FR and EN) | JSON:API, read-only, `api_reader` role |
| 4 | MCP server exposing published content to AI clients | JSON:API, read-only, `api_reader` role |

Why a separate Python service rather than Drupal AI modules: see
[ADR 0002](../docs/adr/0002-drupal-and-python-service-split.md).

## Contract with Drupal (to be finalised in phase 2)

- Drupal never calls an LLM provider directly. The `ai_bridge` module calls
  this service over HTTP; the service holds provider credentials.
- The service reads content only through JSON:API, authenticated as a user
  with the `api_reader` role (published content only, no write access).
- Suggestions are never published automatically: they land in a field whose
  review status moves `none → suggested → approved | rejected`, and the
  editorial workflow (Draft → Review → Published) still applies.

## Files

- `requirements.txt`: intentionally empty for now. It exists so Dependabot's
  `pip` ecosystem is wired from day one.
