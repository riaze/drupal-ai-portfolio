# ADR 0002: Drupal and Python service split

- Status: Accepted
- Date: 2026-11-26

## Context

Later phases add AI features: suggestions for editors (summaries, alt text,
tags), a retrieval-augmented assistant over published content, and an MCP
server exposing that content to AI clients. They need LLM provider calls,
embeddings, a vector index and long-running jobs. The public site must stay
fast, cacheable and safe, and no AI library is installed in phase 1.

## Decision

Split responsibilities:

- **Drupal (`drupal/`)** owns content, translations, the editorial workflow,
  presentation and access control. It exposes published content read-only
  through JSON:API.
- **A Python service (`ai-service/`)** owns everything AI: provider calls and
  credentials, embeddings and the vector index, the RAG assistant and the MCP
  server.

The seams exist from phase 1:

- `ai_bridge` module: `AiBridgeInterface` with a null implementation, to be
  replaced by an HTTP client of the service.
- `api_reader` role: read-only access to published content for the indexer
  and the MCP server.
- `field_ai_review_status` on articles (none, suggested, approved, rejected):
  suggestions are stored for human review and published only through the
  editorial workflow.
- The Key module is installed so provider or service credentials can come
  from environment variables when needed.

## Consequences

- The Python AI ecosystem is available without adding its dependencies,
  latency or failure modes to page rendering; visitors never wait on an LLM.
- Provider credentials and AI costs are isolated in one service that can be
  deployed, scaled, rate-limited or switched off independently.
- Two deployables and a service-to-service contract to maintain:
  authentication of `api_reader`, a versioned HTTP API for `ai_bridge`, and
  JSON:API resources to restrict before phase 3 (see `docs/architecture.md`).
- Drupal remains the single source of truth: the service never writes
  published content directly.

## Alternatives

- **Drupal AI modules inside Drupal** (for example the `ai` module and its
  providers): quick to start and integrated in the admin UI, but puts provider
  calls, credentials and AI dependencies in the CMS, and RAG/MCP would still
  need external components. Can be revisited for editor-facing features if it
  proves simpler than the bridge.
- **Headless front end with AI in a JavaScript backend:** more moving parts
  for a content site that renders well server side.
