<?php

declare(strict_types=1);

namespace Drupal\ai_bridge;

/**
 * Placeholder implementation: no AI service is connected yet.
 */
final class NullAiBridge implements AiBridgeInterface {

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    return FALSE;
  }

}
