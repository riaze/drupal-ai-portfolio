<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Plugin\Filter;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders Markdown (CommonMark with GitHub tables, autolinks, strikethrough).
 *
 * Raw HTML in the source is escaped and unsafe links (javascript:, data:...)
 * are dropped, so the output is safe without a further HTML filter. Level 1
 * headings are demoted to level 2: the page title is the only <h1>.
 */
#[Filter(
  id: 'portfolio_markdown',
  title: new TranslatableMarkup('Markdown (CommonMark, raw HTML escaped)'),
  type: FilterInterface::TYPE_MARKUP_LANGUAGE,
  weight: -20,
)]
final class MarkdownFilter extends FilterBase {

  /**
   * The converter, built on first use.
   */
  private ?MarkdownConverter $converter = NULL;

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    return new FilterProcessResult($this->converter()->convert((string) $text)->getContent());
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE): string {
    return (string) $this->t('Write in <a href=":url">Markdown</a>. Start sections with <code>##</code>. HTML tags are shown as text.', [
      ':url' => 'https://commonmark.org/help/',
    ]);
  }

  /**
   * Builds the CommonMark converter with safe settings.
   */
  private function converter(): MarkdownConverter {
    if ($this->converter === NULL) {
      $environment = new Environment([
        'html_input' => 'escape',
        'allow_unsafe_links' => FALSE,
        'max_nesting_level' => 20,
      ]);
      $environment->addExtension(new CommonMarkCoreExtension());
      $environment->addExtension(new GithubFlavoredMarkdownExtension());
      $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event): void {
        foreach ($event->getDocument()->iterator() as $node) {
          if ($node instanceof Heading && $node->getLevel() === 1) {
            $node->setLevel(2);
          }
        }
      });
      $this->converter = new MarkdownConverter($environment);
    }
    return $this->converter;
  }

}
