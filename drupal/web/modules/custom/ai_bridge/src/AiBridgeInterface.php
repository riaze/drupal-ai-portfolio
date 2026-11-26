<?php

declare(strict_types=1);

namespace Drupal\ai_bridge;

/**
 * Entry point for AI features, implemented by the Python service later.
 *
 * Contract planned for phase 2 (see ai-service/README.md):
 * - Drupal never calls an LLM provider directly; the implementation talks to
 *   ai-service over HTTP, which holds provider credentials (Key module).
 * - Suggestions are stored for human review (field_ai_review_status:
 *   none -> suggested -> approved | rejected) and never published
 *   automatically.
 */
interface AiBridgeInterface {

  /**
   * Whether the AI service is configured and can be used.
   */
  public function isAvailable(): bool;

}
