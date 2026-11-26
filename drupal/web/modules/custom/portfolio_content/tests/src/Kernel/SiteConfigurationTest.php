<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_content\Kernel;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Checks the content types and roles exported to config/sync.
 *
 * The configuration is created from the sync directory with the entity API,
 * so schema, dependencies and permissions are validated as on a real import.
 */
#[Group('portfolio')]
#[RunTestsInSeparateProcesses]
final class SiteConfigurationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'datetime',
    'options',
    'taxonomy',
    'path',
    'path_alias',
    'menu_ui',
    'menu_link_content',
    'language',
    'content_translation',
    'workflows',
    'content_moderation',
    'contact',
    'toolbar',
    'portfolio_content',
  ];

  /**
   * Content types and the fields each one must have.
   */
  private const CONTENT_MODEL = [
    'page' => ['body'],
    'service' => ['field_summary', 'body', 'field_weight', 'field_cta'],
    'case_study' => [
      'field_summary',
      'field_problem',
      'field_solution',
      'field_results',
      'field_stack',
      'field_repo_link',
      'field_demo_link',
      'field_image',
    ],
    'article' => [
      'body',
      'field_summary',
      'field_tags',
      'field_image',
      'field_published_date',
      'field_ai_review_status',
    ],
    'faq' => ['body', 'field_faq_category'],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('path_alias');
    $this->installConfig(['system', 'field', 'filter', 'node', 'user', 'language']);

    // Order matters: each group depends on the previous ones.
    foreach ([
      'language.entity.',
      'filter.format.markdown',
      'taxonomy.vocabulary.',
      'node.type.',
      'field.storage.node.',
      'field.field.node.',
      'core.base_field_override.node.',
      'language.content_settings.node.',
      'workflows.workflow.',
      'user.role.',
    ] as $prefix) {
      $this->importFromSync($prefix);
    }
  }

  /**
   * Tests the content types, their fields and translation settings.
   */
  public function testContentModel(): void {
    $this->assertEqualsCanonicalizing(array_keys(self::CONTENT_MODEL), array_keys(NodeType::loadMultiple()));

    foreach (self::CONTENT_MODEL as $bundle => $fields) {
      foreach ($fields as $field) {
        $this->assertInstanceOf(FieldConfig::class, FieldConfig::loadByName('node', $bundle, $field), "$bundle has $field");
      }
      $settings = ContentLanguageSettings::loadByEntityTypeBundle('node', $bundle);
      $this->assertTrue($settings->getThirdPartySetting('content_translation', 'enabled'), "$bundle is translatable");
      $this->assertSame('site_default', $settings->getDefaultLangcode());
    }
    $this->assertNotNull(ConfigurableLanguage::load('fr'));
    $this->assertNotNull(ConfigurableLanguage::load('en'));

    // Images require alternative text, translated per language.
    foreach (['case_study', 'article'] as $bundle) {
      $image = FieldConfig::loadByName('node', $bundle, 'field_image');
      $this->assertInstanceOf(FieldConfig::class, $image);
      $this->assertTrue($image->getSetting('alt_field_required'), "$bundle image requires alt text");
      $this->assertSame(['alt' => 'alt', 'title' => 'title'], $image->getThirdPartySetting('content_translation', 'translation_sync'));
    }

    // Article summary is empty by default; AI review status starts at none.
    $summary = FieldConfig::loadByName('node', 'article', 'field_summary');
    $this->assertInstanceOf(FieldConfig::class, $summary);
    $this->assertFalse($summary->isRequired());
    $this->assertSame([], $summary->getDefaultValueLiteral());
    $status = FieldStorageConfig::loadByName('node', 'field_ai_review_status');
    $this->assertSame(['none', 'suggested', 'approved', 'rejected'], array_keys($status?->getSetting('allowed_values') ?? []));
    $this->assertSame([['value' => 'none']], FieldConfig::loadByName('node', 'article', 'field_ai_review_status')?->getDefaultValueLiteral());

    // Bodies use the Markdown format only.
    $this->assertSame(['markdown'], FieldConfig::loadByName('node', 'page', 'body')?->getSetting('allowed_formats'));
  }

  /**
   * Tests the editorial workflow.
   */
  public function testEditorialWorkflow(): void {
    $workflow = Workflow::load('editorial');
    $this->assertNotNull($workflow);
    $plugin = $workflow->getTypePlugin();
    $this->assertEqualsCanonicalizing(['draft', 'review', 'published'], array_keys($plugin->getStates()));
    $this->assertEqualsCanonicalizing(['article', 'case_study'], $plugin->getConfiguration()['entity_types']['node']);
  }

  /**
   * Tests the roles and their minimal permissions.
   */
  public function testRoles(): void {
    $roles = Role::loadMultiple();
    foreach (['editor', 'reviewer', 'api_reader'] as $id) {
      $this->assertArrayHasKey($id, $roles);
    }
    $this->assertArrayNotHasKey('content_editor', $roles);

    // The future MCP server reads published content and nothing else.
    $this->assertSame(['access content'], $roles['api_reader']->getPermissions());

    // Editors write and submit; only reviewers publish.
    $this->assertTrue($roles['editor']->hasPermission('use editorial transition submit_for_review'));
    $this->assertFalse($roles['editor']->hasPermission('use editorial transition publish'));
    $this->assertTrue($roles['reviewer']->hasPermission('use editorial transition publish'));
    $this->assertFalse($roles['reviewer']->hasPermission('create article content'));

    // No role other than administrator bypasses access or administers.
    foreach ($roles as $id => $role) {
      if ($role->isAdmin()) {
        continue;
      }
      foreach ($role->getPermissions() as $permission) {
        $this->assertDoesNotMatchRegularExpression('/^(administer|bypass) /', $permission, "$id: $permission");
      }
    }
  }

  /**
   * Creates the configuration whose name starts with a prefix from sync.
   */
  private function importFromSync(string $prefix): void {
    $sync = new FileStorage($this->root . '/../config/sync');
    $config_manager = $this->container->get('config.manager');
    $entity_type_manager = $this->container->get('entity_type.manager');
    $names = $sync->listAll($prefix);
    $this->assertNotEmpty($names, "config/sync contains $prefix*");
    foreach ($names as $name) {
      $data = $sync->read($name);
      $this->assertIsArray($data);
      unset($data['_core']);
      $entity_type_id = $config_manager->getEntityTypeIdByName($name);
      if ($entity_type_id === NULL) {
        $this->config($name)->setData($data)->save();
        continue;
      }
      $storage = $entity_type_manager->getStorage($entity_type_id);
      assert($storage instanceof ConfigEntityStorageInterface);
      $existing = $storage->load($data[$storage->getEntityType()->getKey('id')] ?? '');
      // As on a configuration import: stored format, then entity hooks.
      if ($existing instanceof ConfigEntityInterface) {
        $entity = $storage->updateFromStorageRecord($existing, $data);
      }
      else {
        $entity = $storage->createFromStorageRecord($data);
        $entity->enforceIsNew();
      }
      $entity->save();
    }
  }

}
