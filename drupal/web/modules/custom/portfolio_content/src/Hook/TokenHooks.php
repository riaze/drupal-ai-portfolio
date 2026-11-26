<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Hook;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Provides [node:portfolio_description] for meta and Open Graph descriptions.
 *
 * Every content type gets a description without extra editorial work: the
 * summary when it is filled in, otherwise the beginning of the body as plain
 * text. Articles keep an empty summary until one is written or approved.
 */
final class TokenHooks {

  use StringTranslationTrait;

  /**
   * Maximum length recommended for a meta description.
   */
  public const MAX_LENGTH = 160;

  public function __construct(
    private readonly RendererInterface $renderer,
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
            'description' => $this->t('The summary when filled in, otherwise the beginning of the body as plain text (at most @max characters).', ['@max' => self::MAX_LENGTH]),
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
    return [$tokens['portfolio_description'] => $this->describe($node)];
  }

  /**
   * Returns the plain-text description of a node.
   */
  public function describe(NodeInterface $node, int $max_length = self::MAX_LENGTH): string {
    $text = '';
    if ($node->hasField('field_summary') && !$node->get('field_summary')->isEmpty()) {
      $text = (string) $node->get('field_summary')->value;
    }
    elseif ($node->hasField('body') && !$node->get('body')->isEmpty()) {
      $item = $node->get('body')->first();
      $build = [
        '#type' => 'processed_text',
        '#text' => (string) $item?->get('value')->getValue(),
        '#format' => (string) $item?->get('format')->getValue(),
        '#langcode' => $node->language()->getId(),
      ];
      $text = (string) $this->renderer->renderInIsolation($build);
    }
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    return Unicode::truncate($text, $max_length, TRUE, TRUE);
  }

}
