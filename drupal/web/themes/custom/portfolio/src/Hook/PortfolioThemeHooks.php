<?php

declare(strict_types=1);

namespace Drupal\portfolio\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\portfolio_content\NodeDescriber;

/**
 * Hook implementations for the Portfolio theme.
 */
final class PortfolioThemeHooks {

  use StringTranslationTrait;

  /**
   * Fields rendered with a theme-specific template, by field name.
   */
  private const FIELD_TEMPLATES = [
    'field_problem' => 'field__portfolio_section',
    'field_solution' => 'field__portfolio_section',
    'field_results' => 'field__portfolio_section',
    'field_stack' => 'field__portfolio_chips',
    'field_tags' => 'field__portfolio_chips',
    'field_cta' => 'field__portfolio_inline',
    'field_repo_link' => 'field__portfolio_inline',
    'field_demo_link' => 'field__portfolio_inline',
    'field_published_date' => 'field__portfolio_inline',
  ];

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly NodeDescriber $describer,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for html.html.twig.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if (!empty($variables['is_front'])) {
      $variables['attributes']['class'][] = 'path-frontpage';
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for page.html.twig.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    $config = $this->configFactory->get('system.site');
    $variables['site_name'] = $config->get('name');
    $variables['copyright_year'] = date('Y');
    $variables['#cache']['tags'] = Cache::mergeTags($variables['#cache']['tags'] ?? [], $config->getCacheTags());
    $variables['#cache']['contexts'] = Cache::mergeContexts($variables['#cache']['contexts'] ?? [], ['languages:language_interface']);
  }

  /**
   * Implements hook_preprocess_HOOK() for links--language-block.html.twig.
   *
   * Each language is named in its own language ("Français", "English") and
   * carries lang/hreflang attributes so screen readers pronounce it correctly.
   */
  #[Hook('preprocess_links__language_block')]
  public function preprocessLanguageLinks(array &$variables): void {
    $names = LanguageManager::getStandardLanguageList();
    foreach ($variables['links'] as $langcode => &$link) {
      if (!isset($link['link']) || !is_string($langcode)) {
        continue;
      }
      $link['link']['#title'] = $names[$langcode][1] ?? $langcode;
      $link['link']['#options']['attributes']['lang'] = $langcode;
      $link['link']['#options']['attributes']['hreflang'] = $langcode;
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter() for field templates.
   */
  #[Hook('theme_suggestions_field_alter')]
  public function fieldSuggestions(array &$suggestions, array $variables): void {
    $name = $variables['element']['#field_name'] ?? '';
    if (isset(self::FIELD_TEMPLATES[$name])) {
      $suggestions[] = self::FIELD_TEMPLATES[$name];
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for field templates.
   *
   * Link fields render as buttons; repository and demo links get explicit
   * labels instead of a bare URL.
   */
  #[Hook('preprocess_field')]
  public function preprocessField(array &$variables): void {
    $buttons = [
      'field_cta' => ['button', NULL],
      'field_demo_link' => ['button', $this->t('See the demo')],
      'field_repo_link' => ['button button--secondary', $this->t('View the code')],
    ];
    $name = $variables['field_name'] ?? '';
    if (!isset($buttons[$name])) {
      return;
    }
    [$class, $title] = $buttons[$name];
    foreach ($variables['items'] as &$item) {
      $item['content']['#options']['attributes']['class'][] = $class;
      if ($title !== NULL) {
        $item['content']['#title'] = $title;
      }
    }
  }

  /**
   * Implements hook_preprocess_HOOK() for node templates.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $variables['view_mode'] !== 'teaser') {
      return;
    }
    $variables['description'] = $this->describer->describe($node, 220);
    $variables['heading_level'] = $variables['elements']['#portfolio_heading_level'] ?? 2;
  }

  /**
   * Implements hook_preprocess_HOOK() for views-view-unformatted.html.twig.
   *
   * Cards are <h2> on listing pages and <h3> inside front page sections, whose
   * block title is the <h2>. The level is part of the render cache keys.
   */
  #[Hook('preprocess_views_view_unformatted')]
  public function preprocessViewsUnformatted(array &$variables): void {
    $level = $variables['view']->display_handler->getPluginId() === 'block' ? 3 : 2;
    foreach ($variables['rows'] as &$row) {
      if (isset($row['content']['#node'])) {
        $row['content']['#portfolio_heading_level'] = $level;
        $row['content']['#cache']['keys'][] = 'h' . $level;
      }
    }
  }

}
