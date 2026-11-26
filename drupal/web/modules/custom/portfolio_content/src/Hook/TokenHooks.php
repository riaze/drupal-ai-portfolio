<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\portfolio_content\NodeDescriber;

/**
 * Provides [node:portfolio_description] for meta and Open Graph descriptions.
 *
 * @see \Drupal\portfolio_content\NodeDescriber
 */
final class TokenHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly NodeDescriber $describer,
  ) {}

  /**
   * Implements hook_token_info().
   *
   * @return array<string, array<string, mixed>>
   *   The token definitions.
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    return [
      'tokens' => [
        'node' => [
          'portfolio_description' => [
            'name' => $this->t('Description'),
            'description' => $this->t('The summary when filled in, otherwise the beginning of the body as plain text (at most @max characters).', ['@max' => NodeDescriber::MAX_LENGTH]),
          ],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens().
   *
   * @param string $type
   *   The token type.
   * @param array<string, string> $tokens
   *   The tokens to replace, keyed by name.
   * @param array<string, mixed> $data
   *   The token data.
   * @param array<string, mixed> $options
   *   The replacement options.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Cacheability metadata of the replacements.
   *
   * @return array<string, string>
   *   The replacements, keyed by original token.
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    if ($type !== 'node' || !isset($tokens['portfolio_description']) || !($data['node'] ?? NULL) instanceof NodeInterface) {
      return [];
    }
    $node = $data['node'];
    $langcode = $options['langcode'] ?? NULL;
    if (is_string($langcode) && $node->hasTranslation($langcode)) {
      $node = $node->getTranslation($langcode);
    }
    $bubbleable_metadata->addCacheableDependency($node);
    return [$tokens['portfolio_description'] => $this->describer->describe($node)];
  }

}
