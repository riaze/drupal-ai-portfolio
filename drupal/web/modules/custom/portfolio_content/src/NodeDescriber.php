<?php

declare(strict_types=1);

namespace Drupal\portfolio_content;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Render\RendererInterface;
use Drupal\node\NodeInterface;

/**
 * Builds the plain-text description of a node.
 *
 * Used for meta and Open Graph descriptions and for teaser cards, so every
 * content type has a description without extra editorial work: the summary
 * when it is filled in, otherwise the beginning of the body as plain text.
 */
final class NodeDescriber {

  /**
   * Maximum length recommended for a meta description.
   */
  public const MAX_LENGTH = 160;

  public function __construct(
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Returns the plain-text description of a node translation.
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
