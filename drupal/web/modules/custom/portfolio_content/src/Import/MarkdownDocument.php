<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Import;

use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

/**
 * One Markdown file of content/: YAML front matter, body and sections.
 *
 * Sections let one file fill several long-text fields:
 * @code
 * <!-- field: problem -->
 * Markdown for the "problem" field…
 * @endcode
 * Other HTML comments are guidance for writers and are removed.
 */
final class MarkdownDocument {

  /**
   * Marks a placeholder that must be replaced before publication.
   */
  public const PLACEHOLDER_PATTERN = '/\[\[[^\]\n]+\]\]/u';

  /**
   * @param string $path
   *   Path relative to the content directory, for messages.
   * @param string $langcode
   *   Language of the file (its top-level directory).
   * @param array<string, mixed> $frontMatter
   *   The parsed YAML front matter.
   * @param string $body
   *   Markdown before the first section marker.
   * @param array<string, string> $sections
   *   Markdown of each section, keyed by name.
   * @param string $hash
   *   Hash of the raw file contents.
   */
  public function __construct(
    public readonly string $path,
    public readonly string $langcode,
    public readonly array $frontMatter,
    public readonly string $body,
    public readonly array $sections,
    public readonly string $hash,
  ) {}

  /**
   * Parses Markdown source.
   *
   * @throws \InvalidArgumentException
   *   When the front matter is missing or is not a YAML mapping.
   */
  public static function parse(string $source, string $path, string $langcode): self {
    try {
      $result = (new FrontMatterParser(new SymfonyYamlFrontMatterParser()))->parse($source);
    }
    catch (InvalidFrontMatterException $e) {
      throw new \InvalidArgumentException(sprintf('%s: invalid front matter: %s', $path, $e->getMessage()), 0, $e);
    }
    $front_matter = $result->getFrontMatter();
    if (!is_array($front_matter) || $front_matter === [] || array_is_list($front_matter)) {
      throw new \InvalidArgumentException(sprintf('%s: the file must start with a YAML front matter block (--- ... ---).', $path));
    }
    $parts = preg_split('/^<!--\s*field:\s*([a-z_]+)\s*-->[ \t]*$/m', $result->getContent(), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [''];
    $body = self::clean((string) array_shift($parts));
    $sections = [];
    foreach (array_chunk($parts, 2) as $chunk) {
      $sections[(string) $chunk[0]] = self::clean((string) ($chunk[1] ?? ''));
    }
    /** @var array<string, mixed> $front_matter */
    return new self($path, $langcode, $front_matter, $body, $sections, hash('sha256', $source));
  }

  /**
   * Returns a front matter value.
   */
  public function get(string $key, mixed $default = NULL): mixed {
    $value = $this->frontMatter[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
  }

  /**
   * Returns a front matter value as a trimmed string ('' when absent).
   */
  public function string(string $key): string {
    $value = $this->get($key, '');
    return is_scalar($value) ? trim((string) $value) : '';
  }

  /**
   * Returns a front matter value as a list of non-empty strings.
   *
   * @return list<string>
   *   The values.
   */
  public function list(string $key): array {
    $value = $this->get($key, []);
    $values = is_array($value) ? $value : explode(',', (string) (is_scalar($value) ? $value : ''));
    return array_values(array_filter(array_map(static fn(mixed $v): string => is_scalar($v) ? trim((string) $v) : '', $values), static fn(string $v): bool => $v !== ''));
  }

  /**
   * Returns a section's Markdown ('' when absent).
   */
  public function section(string $name): string {
    return $this->sections[$name] ?? '';
  }

  /**
   * Whether the file still contains [[placeholders]].
   */
  public function hasPlaceholders(): bool {
    $text = implode("\n", [
      json_encode($this->frontMatter, JSON_UNESCAPED_UNICODE) ?: '',
      $this->body,
      implode("\n", $this->sections),
    ]);
    return (bool) preg_match(self::PLACEHOLDER_PATTERN, $text);
  }

  /**
   * Removes HTML comments (writer guidance) and surrounding whitespace.
   */
  private static function clean(string $markdown): string {
    return trim((string) preg_replace('/<!--.*?-->/s', '', $markdown));
  }

}
