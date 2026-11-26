<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_content\Unit;

use Drupal\portfolio_content\Import\ContentImporter;
use Drupal\portfolio_content\Import\MarkdownDocument;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the publication rules and identifiers of the content importer.
 */
#[CoversClass(ContentImporter::class)]
#[Group('portfolio')]
final class ContentImporterValidationTest extends UnitTestCase {

  /**
   * UUIDs are stable, distinct and valid version 5 UUIDs.
   */
  public function testUuid(): void {
    $uuid = ContentImporter::uuid('node:about');
    $this->assertSame($uuid, ContentImporter::uuid('node:about'));
    $this->assertNotSame($uuid, ContentImporter::uuid('node:home'));
    $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid);
  }

  /**
   * Drafts may contain placeholders; published content may not.
   */
  public function testPlaceholdersBlockPublication(): void {
    $draft = $this->document("id: legal\ntype: page\ntitle: Legal\nstatus: draft", 'Publisher: [[Name]].');
    $published = $this->document("id: legal\ntype: page\ntitle: Legal\nstatus: published", 'Publisher: [[Name]].');
    $this->assertSame([], ContentImporter::validate($draft, 'page', sys_get_temp_dir(), []));
    $errors = ContentImporter::validate($published, 'page', sys_get_temp_dir(), []);
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('placeholders', $errors[0]);
  }

  /**
   * Type-specific rules.
   */
  public function testTypeRules(): void {
    $case_study = $this->document("id: c\ntype: case_study\ntitle: C\nstatus: published\nsummary: S", '');
    $this->assertCount(3, ContentImporter::validate($case_study, 'case_study', sys_get_temp_dir(), []), 'Published case study needs its three sections.');

    $image_without_alt = $this->document("id: c\ntype: article\ntitle: A\nimage: images/missing.jpg", 'Text');
    $this->assertCount(2, ContentImporter::validate($image_without_alt, 'article', sys_get_temp_dir(), []), 'Missing file and missing alt text.');

    $faq = $this->document("id: f\ntype: faq\ntitle: Q?\ncategory: pricing", 'A.');
    $this->assertSame([], ContentImporter::validate($faq, 'faq', sys_get_temp_dir(), ['pricing' => TRUE]));
    $this->assertCount(1, ContentImporter::validate($faq, 'faq', sys_get_temp_dir(), []));

    $review_page = $this->document("id: p\ntype: page\ntitle: P\nstatus: review", '');
    $this->assertCount(1, ContentImporter::validate($review_page, 'page', sys_get_temp_dir(), []), 'Only moderated types have a review state.');
  }

  /**
   * Builds a document from front matter lines and a body.
   */
  private function document(string $front_matter, string $body): MarkdownDocument {
    return MarkdownDocument::parse("---\n$front_matter\n---\n$body", 'test.md', 'fr');
  }

}
