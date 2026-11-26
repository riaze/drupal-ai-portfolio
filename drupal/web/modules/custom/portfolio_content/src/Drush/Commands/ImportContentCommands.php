<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Drush\Commands;

use Drupal\portfolio_content\Import\ContentImporter;
use Drupal\portfolio_content\Import\ImportResult;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush command importing the Markdown files of content/.
 */
final class ImportContentCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly ContentImporter $importer,
  ) {
    parent::__construct();
  }

  /**
   * Imports content/fr and content/en (idempotent).
   *
   * @param array{source: ?string, force: bool, dry-run: bool} $options
   *   The command options.
   */
  #[CLI\Command(name: 'portfolio:import-content', aliases: ['pic'])]
  #[CLI\Option(name: 'source', description: 'Content directory; when omitted, the monorepo content/ directory (../content from the Composer root).')]
  #[CLI\Option(name: 'force', description: 'Re-apply every file, even when unchanged since the last import.')]
  #[CLI\Option(name: 'dry-run', description: 'Validate and report without saving anything.')]
  #[CLI\Usage(name: 'drush portfolio:import-content', description: 'Import new and changed files.')]
  #[CLI\Usage(name: 'drush portfolio:import-content --dry-run', description: 'Check the files without importing them.')]
  public function import(array $options = ['source' => NULL, 'force' => FALSE, 'dry-run' => FALSE]): int {
    $source = $options['source'] ?: dirname(DRUPAL_ROOT) . '/../content';
    try {
      $results = $this->importer->import($source, (bool) $options['force'], (bool) $options['dry-run']);
    }
    catch (\InvalidArgumentException $e) {
      $this->logger()?->error($e->getMessage());
      return self::EXIT_FAILURE;
    }

    $counts = array_fill_keys([
      ImportResult::CREATED,
      ImportResult::UPDATED,
      ImportResult::UNCHANGED,
      ImportResult::ERROR,
    ], 0);
    $rows = [];
    foreach ($results as $result) {
      $counts[$result->action]++;
      $rows[] = [$result->item, $result->langcode, $result->action, $result->message];
    }
    $this->io()->table(['Item', 'Lang', 'Result', 'Message'], $rows);
    $summary = vsprintf('%d created, %d updated, %d unchanged, %d errors.', array_values($counts));
    if ($counts[ImportResult::ERROR] > 0) {
      $this->logger()?->error($summary);
      return self::EXIT_FAILURE;
    }
    $this->logger()?->success($summary);
    return self::EXIT_SUCCESS;
  }

}
