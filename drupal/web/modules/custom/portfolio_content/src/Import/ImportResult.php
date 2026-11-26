<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Import;

/**
 * Outcome of importing one item (a node translation, a term, a file).
 */
final class ImportResult {

  public const CREATED = 'created';
  public const UPDATED = 'updated';
  public const UNCHANGED = 'unchanged';
  public const ERROR = 'error';

  public function __construct(
    public readonly string $item,
    public readonly string $langcode,
    public readonly string $action,
    public readonly string $message = '',
  ) {}

}
