<?php

declare(strict_types=1);

namespace Drupal\portfolio_content\Import;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\node\NodeInterface;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Imports the Markdown files of content/ into Drupal, idempotently.
 *
 * - content/<langcode>/**\/*.md: one file per node translation. Files of the
 *   same "id" in fr/ and en/ are translations of one node. Files whose name
 *   starts with "_" and README.md are ignored.
 * - content/<langcode>/taxonomy/faq_category.yml: FAQ categories (key: label).
 * - content/images/: images referenced from front matter.
 *
 * Every entity gets a UUID derived from its id, so the same files always map
 * to the same entities, on any environment. A hash of each translation is
 * kept: unchanged files are skipped, so edits made in Drupal survive until
 * the file itself changes (or --force is used).
 *
 * Nothing that still contains a [[placeholder]] can be published.
 */
final class ContentImporter {

  /**
   * Content types that can be imported.
   */
  public const TYPES = ['page', 'service', 'case_study', 'article', 'faq'];

  /**
   * Content types under the editorial workflow.
   */
  public const MODERATED = ['article', 'case_study'];

  /**
   * Namespace of the name-based (version 5) UUIDs of imported entities.
   */
  private const UUID_NAMESPACE = '33c4da78-0ebb-42c4-99b8-1d40b7ef8dc0';

  /**
   * Key-value collection holding the hash of each imported translation.
   */
  private const STATE_COLLECTION = 'portfolio_content.import';

  /**
   * The results of the current run.
   *
   * @var list<\Drupal\portfolio_content\Import\ImportResult>
   */
  private array $results = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly AccountSwitcherInterface $accountSwitcher,
    private readonly TimeInterface $time,
    #[Autowire('@?simple_sitemap.generator')]
    private readonly ?Generator $sitemapGenerator = NULL,
  ) {}

  /**
   * Returns the deterministic UUID of an imported entity.
   *
   * @param string $name
   *   For example "node:about" or "taxonomy_term:faq_category:general".
   */
  public static function uuid(string $name): string {
    $hash = sha1((string) hex2bin(str_replace('-', '', self::UUID_NAMESPACE)) . $name);
    return sprintf(
      '%s-%s-%04x-%04x-%s',
      substr($hash, 0, 8),
      substr($hash, 8, 4),
      (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
      (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
      substr($hash, 20, 12),
    );
  }

  /**
   * Imports a content directory.
   *
   * @param string $directory
   *   The content directory (contains fr/, en/ and images/).
   * @param bool $force
   *   Re-apply files even when unchanged since the last import.
   * @param bool $dry_run
   *   Validate and report without saving anything.
   *
   * @return list<\Drupal\portfolio_content\Import\ImportResult>
   *   One result per item.
   */
  public function import(string $directory, bool $force = FALSE, bool $dry_run = FALSE): array {
    $this->results = [];
    $root = realpath($directory);
    if ($root === FALSE || !is_dir($root)) {
      throw new \InvalidArgumentException(sprintf('Content directory not found: %s', $directory));
    }
    // Revisions are attributed to the site owner (user 1).
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1, 'roles' => ['authenticated', 'administrator']]));
    try {
      $langcodes = $this->langcodes();
      $categories = $this->importFaqCategories($root, $langcodes, $dry_run);
      foreach ($this->collect($root, $langcodes) as $id => $documents) {
        $this->importNode($root, $id, $documents, $categories, $force, $dry_run);
      }
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
    return $this->results;
  }

  /**
   * Returns the site languages, default language first.
   *
   * @return list<string>
   *   Language codes.
   */
  private function langcodes(): array {
    $default = $this->languageManager->getDefaultLanguage()->getId();
    $others = array_diff(array_keys($this->languageManager->getLanguages()), [$default]);
    return array_values(array_merge([$default], $others));
  }

  /**
   * Imports FAQ categories from <langcode>/taxonomy/faq_category.yml.
   *
   * @param string $root
   *   The content directory.
   * @param list<string> $langcodes
   *   Site languages, default first.
   * @param bool $dry_run
   *   Whether to skip saving.
   *
   * @return array<string, true>
   *   Known category keys.
   */
  private function importFaqCategories(string $root, array $langcodes, bool $dry_run): array {
    $labels = [];
    foreach ($langcodes as $langcode) {
      $file = "$root/$langcode/taxonomy/faq_category.yml";
      if (!is_file($file)) {
        continue;
      }
      $data = Yaml::parseFile($file);
      foreach (is_array($data) ? $data : [] as $key => $label) {
        if (is_string($key) && is_scalar($label) && trim((string) $label) !== '') {
          $labels[$key][$langcode] = trim((string) $label);
        }
      }
    }
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $default = $langcodes[0];
    $weight = 0;
    foreach ($labels as $key => $translations) {
      $item = "faq_category:$key";
      $source_langcode = isset($translations[$default]) ? $default : (string) array_key_first($translations);
      if ($dry_run) {
        continue;
      }
      $uuid = self::uuid("taxonomy_term:faq_category:$key");
      $terms = $storage->loadByProperties(['uuid' => $uuid]);
      $term = reset($terms);
      $action = $term ? ImportResult::UNCHANGED : ImportResult::CREATED;
      if (!$term instanceof TermInterface) {
        $term = $storage->create([
          'vid' => 'faq_category',
          'uuid' => $uuid,
          'langcode' => $source_langcode,
          'name' => $translations[$source_langcode],
        ]);
        assert($term instanceof TermInterface);
      }
      foreach ($translations as $langcode => $label) {
        $translation = $term->hasTranslation($langcode) ? $term->getTranslation($langcode) : $term->addTranslation($langcode);
        if ($translation->getName() !== $label || $translation->getWeight() !== $weight) {
          $translation->setName($label)->setWeight($weight);
          $action = $action === ImportResult::CREATED ? $action : ImportResult::UPDATED;
        }
      }
      if ($action !== ImportResult::UNCHANGED) {
        $term->save();
      }
      $this->results[] = new ImportResult($item, implode(',', array_keys($translations)), $action);
      $weight++;
    }
    return array_fill_keys(array_keys($labels), TRUE);
  }

  /**
   * Finds and parses the Markdown files, grouped by id and language.
   *
   * @param string $root
   *   The content directory.
   * @param list<string> $langcodes
   *   Site languages, default first.
   *
   * @return array<string, array<string, \Drupal\portfolio_content\Import\MarkdownDocument>>
   *   Documents keyed by id, then language code (default language first).
   */
  private function collect(string $root, array $langcodes): array {
    $documents = [];
    foreach ($langcodes as $langcode) {
      if (!is_dir("$root/$langcode")) {
        continue;
      }
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/$langcode", \FilesystemIterator::SKIP_DOTS));
      $paths = [];
      foreach ($files as $file) {
        assert($file instanceof \SplFileInfo);
        if ($file->getExtension() === 'md' && !str_starts_with($file->getFilename(), '_') && $file->getFilename() !== 'README.md') {
          $paths[] = $file->getPathname();
        }
      }
      sort($paths);
      foreach ($paths as $path) {
        $relative = substr($path, strlen($root) + 1);
        try {
          $document = MarkdownDocument::parse((string) file_get_contents($path), $relative, $langcode);
        }
        catch (\InvalidArgumentException $e) {
          $this->results[] = new ImportResult($relative, $langcode, ImportResult::ERROR, $e->getMessage());
          continue;
        }
        $id = $document->string('id');
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $id)) {
          $this->results[] = new ImportResult($relative, $langcode, ImportResult::ERROR, 'Missing or invalid "id" (lowercase letters, digits and dashes).');
          continue;
        }
        if (isset($documents[$id][$langcode])) {
          $this->results[] = new ImportResult($relative, $langcode, ImportResult::ERROR, sprintf('Duplicate id "%s", already used by %s.', $id, $documents[$id][$langcode]->path));
          continue;
        }
        $documents[$id][$langcode] = $document;
      }
    }
    return $documents;
  }

  /**
   * Validates a document against its content type.
   *
   * @param \Drupal\portfolio_content\Import\MarkdownDocument $document
   *   The document.
   * @param string $type
   *   The content type of the node.
   * @param string $root
   *   The content directory.
   * @param array<string, true> $categories
   *   Known FAQ category keys.
   *
   * @return list<string>
   *   Error messages; empty when valid.
   */
  public static function validate(MarkdownDocument $document, string $type, string $root, array $categories): array {
    $errors = [];
    $status = $document->string('status') ?: 'draft';
    $published = $status === 'published';
    $statuses = in_array($type, self::MODERATED, TRUE) ? ['draft', 'review', 'published'] : ['draft', 'published'];
    if (!in_array($type, self::TYPES, TRUE)) {
      return [sprintf('Unknown type "%s" (expected one of: %s).', $type, implode(', ', self::TYPES))];
    }
    if ($document->string('id') === 'home' && $type !== 'page') {
      $errors[] = 'The "home" content (front page) must be of type "page".';
    }
    if ($document->string('type') !== $type) {
      $errors[] = sprintf('Type "%s" differs from the other language ("%s").', $document->string('type'), $type);
    }
    if ($document->string('title') === '') {
      $errors[] = 'Missing "title".';
    }
    if (!in_array($status, $statuses, TRUE)) {
      $errors[] = sprintf('Invalid status "%s" (expected one of: %s).', $status, implode(', ', $statuses));
    }
    $path = $document->string('path');
    if ($path !== '' && !preg_match('~^/[^\s?#]*$~', $path)) {
      $errors[] = sprintf('Invalid path "%s": it must start with "/" and contain no spaces.', $path);
    }
    if (!in_array($document->string('menu'), ['', 'main', 'footer'], TRUE)) {
      $errors[] = 'Invalid "menu" (expected main or footer).';
    }
    if ($published && $document->hasPlaceholders()) {
      $errors[] = 'Still contains [[placeholders]]: replace them before setting "status: published".';
    }
    $image = $document->string('image');
    if ($image !== '') {
      if (!is_file("$root/$image") || !preg_match('/\.(png|jpe?g|webp)$/i', $image) || str_contains($image, '..')) {
        $errors[] = sprintf('Image "%s" not found under content/ (png, jpg or webp).', $image);
      }
      if ($document->string('image_alt') === '') {
        $errors[] = 'An image needs "image_alt" (alternative text).';
      }
    }
    switch ($type) {
      case 'service':
        if ($published && $document->string('summary') === '') {
          $errors[] = 'A published service needs a "summary".';
        }
        if (!is_int($document->get('order', 0))) {
          $errors[] = '"order" must be an integer.';
        }
        if (($document->string('cta_url') === '') !== ($document->string('cta_label') === '')) {
          $errors[] = '"cta_url" and "cta_label" go together.';
        }
        break;

      case 'case_study':
        if ($published) {
          foreach (['problem', 'solution', 'results'] as $section) {
            if ($document->section($section) === '') {
              $errors[] = sprintf('A published case study needs a "<!-- field: %s -->" section.', $section);
            }
          }
          if ($document->string('summary') === '') {
            $errors[] = 'A published case study needs a "summary".';
          }
        }
        foreach (['repo_url', 'demo_url'] as $key) {
          if ($document->string($key) !== '' && !preg_match('#^https://#', $document->string($key))) {
            $errors[] = sprintf('"%s" must be an https:// URL.', $key);
          }
        }
        break;

      case 'article':
        if ($published && $document->body === '') {
          $errors[] = 'A published article needs a body.';
        }
        if ($document->get('published_date') !== NULL && self::date($document->get('published_date')) === NULL) {
          $errors[] = '"published_date" must be a date (YYYY-MM-DD).';
        }
        break;

      case 'faq':
        if ($published && $document->body === '') {
          $errors[] = 'A published FAQ entry needs an answer (the body).';
        }
        if ($document->string('category') !== '' && !isset($categories[$document->string('category')])) {
          $errors[] = sprintf('Unknown category "%s": add it to taxonomy/faq_category.yml.', $document->string('category'));
        }
        if ($published && $document->string('category') === '') {
          $errors[] = 'A published FAQ entry needs a "category".';
        }
        break;
    }
    return $errors;
  }

  /**
   * Imports all translations of one node.
   *
   * @param string $root
   *   The content directory.
   * @param string $id
   *   The content id.
   * @param array<string, \Drupal\portfolio_content\Import\MarkdownDocument> $documents
   *   Documents keyed by language code, default language first.
   * @param array<string, true> $categories
   *   Known FAQ category keys.
   * @param bool $force
   *   Re-apply unchanged files.
   * @param bool $dry_run
   *   Validate only.
   */
  private function importNode(string $root, string $id, array $documents, array $categories, bool $force, bool $dry_run): void {
    $source = reset($documents);
    $type = $source->string('type');
    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->loadByProperties(['uuid' => self::uuid("node:$id")]);
    $node = reset($nodes) ?: NULL;
    assert($node === NULL || $node instanceof NodeInterface);
    if ($node !== NULL && $node->bundle() !== $type) {
      $this->results[] = new ImportResult($id, $source->langcode, ImportResult::ERROR, sprintf('Already imported as "%s"; the type cannot change.', $node->bundle()));
      return;
    }
    $state = $this->state();

    foreach ($documents as $langcode => $document) {
      $errors = self::validate($document, $type, $root, $categories);
      if ($errors !== []) {
        $this->results[] = new ImportResult($document->path, $langcode, ImportResult::ERROR, implode(' ', $errors));
        if ($node === NULL) {
          // Translations cannot exist without the source.
          return;
        }
        continue;
      }
      $hash = hash('sha256', $document->hash . $this->imageHash($root, $source));
      $exists = $node !== NULL && $node->hasTranslation($langcode);
      if ($exists && !$force && $state->get("$id:$langcode") === $hash) {
        $this->results[] = new ImportResult($document->path, $langcode, ImportResult::UNCHANGED);
        continue;
      }
      if ($dry_run) {
        $this->results[] = new ImportResult($document->path, $langcode, $exists ? ImportResult::UPDATED : ImportResult::CREATED, 'dry run');
        continue;
      }
      if ($node === NULL) {
        $node = $storage->create([
          'type' => $type,
          'uuid' => self::uuid("node:$id"),
          'langcode' => $langcode,
          'uid' => 1,
        ]);
        assert($node instanceof NodeInterface);
        $translation = $node;
        // Suggestions start empty; the importer never touches them later.
        if ($node->hasField('field_ai_review_status')) {
          $node->set('field_ai_review_status', 'none');
        }
      }
      else {
        $translation = $node->hasTranslation($langcode) ? $node->getTranslation($langcode) : $node->addTranslation($langcode, ['uid' => 1]);
      }
      $is_source = $langcode === $source->langcode;
      $this->applyFields($translation, $document, $id, $type, $root, $is_source);
      $translation->setNewRevision(TRUE);
      $translation->setRevisionLogMessage(sprintf('Imported from content/%s', $document->path));
      $translation->setRevisionUserId(1);
      $translation->setRevisionCreationTime($this->time->getRequestTime());
      $translation->save();
      $state->set("$id:$langcode", $hash);
      $this->results[] = new ImportResult($document->path, $langcode, $exists ? ImportResult::UPDATED : ImportResult::CREATED);
    }

    if ($node !== NULL && !$dry_run && $node->id() !== NULL) {
      $this->syncMenuLink($node, $id, $documents);
      if ($id === 'home' && $this->sitemapGenerator !== NULL) {
        // The front page is listed as "/", not under its own path.
        $this->sitemapGenerator->entityManager()->setEntityInstanceSettings('node', (string) $node->id(), ['index' => FALSE]);
      }
    }
  }

  /**
   * Sets the fields of a node translation from a document.
   */
  private function applyFields(NodeInterface $node, MarkdownDocument $document, string $id, string $type, string $root, bool $is_source): void {
    $status = $document->string('status') ?: 'draft';
    $node->setTitle($document->string('title'));
    if (in_array($type, self::MODERATED, TRUE)) {
      $node->set('moderation_state', $status);
    }
    elseif ($status === 'published') {
      $node->setPublished();
    }
    else {
      $node->setUnpublished();
    }

    // The front page always lives at /home (see system.site:page.front).
    $path = $id === 'home' ? '/home' : $document->string('path');
    $node->set('path', $path !== '' ? ['alias' => $path, 'pathauto' => 0] : ['pathauto' => 1]);

    $markdown = static fn(string $text): array => $text === '' ? [] : ['value' => $text, 'format' => 'markdown'];
    if ($node->hasField('body')) {
      $node->set('body', $markdown($document->body));
    }
    if ($node->hasField('field_summary') && ($type !== 'article' || $document->string('summary') !== '')) {
      // Article summaries stay empty unless written: they are left for review.
      $node->set('field_summary', $document->string('summary'));
    }

    switch ($type) {
      case 'service':
        $node->set('field_weight', (int) $document->get('order', 0));
        $cta_url = $document->string('cta_url');
        $node->set('field_cta', $cta_url === '' ? [] : [
          'uri' => self::linkUri($cta_url),
          'title' => $document->string('cta_label'),
        ]);
        break;

      case 'case_study':
        foreach (['problem', 'solution', 'results'] as $section) {
          $node->set("field_$section", $markdown($document->section($section)));
        }
        if ($is_source) {
          $node->set('field_stack', $this->terms('stack', $document->list('stack'), 'zxx'));
          $node->set('field_repo_link', $document->string('repo_url') === '' ? [] : ['uri' => $document->string('repo_url')]);
          $node->set('field_demo_link', $document->string('demo_url') === '' ? [] : ['uri' => $document->string('demo_url')]);
        }
        $this->setImage($node, $document, $root, $is_source);
        break;

      case 'article':
        $node->set('field_tags', $this->terms('tags', $document->list('tags'), $document->langcode));
        if ($is_source) {
          $date = self::date($document->get('published_date')) ?? date('Y-m-d', $this->time->getRequestTime());
          $node->set('field_published_date', $date);
        }
        $this->setImage($node, $document, $root, $is_source);
        break;

      case 'faq':
        if ($is_source && $document->string('category') !== '') {
          $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['uuid' => self::uuid('taxonomy_term:faq_category:' . $document->string('category'))]);
          $node->set('field_faq_category', $terms ? [reset($terms)] : []);
        }
        break;
    }
  }

  /**
   * Sets the image (shared file, per-language alternative text).
   */
  private function setImage(NodeInterface $node, MarkdownDocument $document, string $root, bool $is_source): void {
    $image = $document->string('image');
    if ($image === '') {
      if ($is_source) {
        $node->set('field_image', []);
      }
      return;
    }
    $file = $is_source ? $this->file($root, $image) : $node->get('field_image')->entity;
    if ($file instanceof FileInterface) {
      $node->set('field_image', ['target_id' => $file->id(), 'alt' => $document->string('image_alt')]);
    }
  }

  /**
   * Returns the managed file for an image of content/, copying it if needed.
   */
  private function file(string $root, string $relative): FileInterface {
    $storage = $this->entityTypeManager->getStorage('file');
    $uuid = self::uuid("file:$relative");
    $files = $storage->loadByProperties(['uuid' => $uuid]);
    $file = reset($files) ?: NULL;
    $hash = (string) sha1_file("$root/$relative");
    $state = $this->state();
    if ($file instanceof FileInterface && $state->get("file:$relative") === $hash) {
      return $file;
    }
    $directory = 'public://content-images';
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $destination = $this->fileSystem->copy("$root/$relative", $directory . '/' . basename($relative), FileExists::Replace);
    if (!$file instanceof FileInterface) {
      $file = $storage->create(['uuid' => $uuid, 'uri' => $destination, 'uid' => 1]);
      assert($file instanceof FileInterface);
    }
    $file->setFileUri($destination);
    $file->setFilename(basename($destination));
    $file->setPermanent();
    $file->save();
    // Regenerate image style derivatives of a replaced file.
    foreach ($this->entityTypeManager->getStorage('image_style')->loadMultiple() as $style) {
      if ($style instanceof ImageStyleInterface) {
        $style->flush($destination);
      }
    }
    $state->set("file:$relative", $hash);
    $this->results[] = new ImportResult($relative, '', ImportResult::UPDATED, 'image copied');
    return $file;
  }

  /**
   * Returns terms of a vocabulary by name, creating the missing ones.
   *
   * @param string $vid
   *   The vocabulary.
   * @param list<string> $names
   *   Term names.
   * @param string $langcode
   *   Language of the terms ("zxx" for language-neutral vocabularies).
   *
   * @return list<\Drupal\taxonomy\TermInterface>
   *   The terms.
   */
  private function terms(string $vid, array $names, string $langcode): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = [];
    foreach (array_unique($names) as $name) {
      $existing = $storage->loadByProperties(['vid' => $vid, 'name' => $name, 'langcode' => $langcode]);
      $term = reset($existing) ?: $storage->create(['vid' => $vid, 'name' => $name, 'langcode' => $langcode]);
      assert($term instanceof TermInterface);
      if ($term->isNew()) {
        $term->save();
      }
      $terms[] = $term;
    }
    return $terms;
  }

  /**
   * Creates, updates or removes the menu link of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $id
   *   The content id.
   * @param array<string, \Drupal\portfolio_content\Import\MarkdownDocument> $documents
   *   Documents keyed by language code, source first.
   */
  private function syncMenuLink(NodeInterface $node, string $id, array $documents): void {
    $source = reset($documents);
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $links = $storage->loadByProperties(['uuid' => self::uuid("menu_link_content:$id")]);
    $link = reset($links) ?: NULL;
    $menu = $source->string('menu');
    if ($menu === '') {
      $link?->delete();
      return;
    }
    if (!$link instanceof ContentEntityInterface) {
      $link = $storage->create([
        'uuid' => self::uuid("menu_link_content:$id"),
        'langcode' => $source->langcode,
        'bundle' => 'menu_link_content',
      ]);
      assert($link instanceof ContentEntityInterface);
    }
    $link->set('menu_name', $menu);
    $link->set('link', ['uri' => 'entity:node/' . $node->id()]);
    $link->set('weight', (int) $source->get('menu_weight', 0));
    $link->set('expanded', FALSE);
    foreach ($documents as $langcode => $document) {
      if (!$node->hasTranslation($langcode)) {
        continue;
      }
      $translation = $link->hasTranslation($langcode) ? $link->getTranslation($langcode) : $link->addTranslation($langcode);
      $translation->set('title', $document->string('menu_title') ?: $document->string('title'));
    }
    $link->save();
  }

  /**
   * Returns the hash of the image referenced by a document ('' if none).
   */
  private function imageHash(string $root, MarkdownDocument $document): string {
    $image = $document->string('image');
    return ($image !== '' && is_file("$root/$image")) ? (string) sha1_file("$root/$image") : '';
  }

  /**
   * Converts a front matter link to a link field URI.
   */
  private static function linkUri(string $url): string {
    return str_starts_with($url, '/') ? 'internal:' . $url : $url;
  }

  /**
   * Normalizes a front matter date (YAML may parse it as a timestamp).
   */
  private static function date(mixed $value): ?string {
    if (is_int($value)) {
      return gmdate('Y-m-d', $value);
    }
    if ($value instanceof \DateTimeInterface) {
      return $value->format('Y-m-d');
    }
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
      return $value;
    }
    return NULL;
  }

  /**
   * Returns the key-value store of import hashes.
   */
  private function state(): KeyValueStoreInterface {
    return $this->keyValueFactory->get(self::STATE_COLLECTION);
  }

}
