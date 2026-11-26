<?php

declare(strict_types=1);

namespace Drupal\Tests\portfolio_content\Unit;

use Drupal\portfolio_content\Import\MarkdownDocument;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests parsing of the Markdown files of content/.
 */
#[CoversClass(MarkdownDocument::class)]
#[Group('portfolio')]
final class MarkdownDocumentTest extends UnitTestCase {

  /**
   * Front matter, body, sections and guidance comments.
   */
  public function testParse(): void {
    $source = <<<'MD'
      ---
      id: case-one
      type: case_study
      title: "Migration : Drupal 7 → 11"
      stack: [Drupal, PHP]
      ---
      <!-- Guidance for the writer, removed. -->
      Intro.

      <!-- field: problem -->
      The **problem**.

      <!-- field: results -->
      - Faster
      MD;
    $document = MarkdownDocument::parse($source, 'fr/case.md', 'fr');

    $this->assertSame('case-one', $document->string('id'));
    $this->assertSame('Migration : Drupal 7 → 11', $document->string('title'));
    $this->assertSame(['Drupal', 'PHP'], $document->list('stack'));
    $this->assertSame('Intro.', $document->body);
    $this->assertSame('The **problem**.', $document->section('problem'));
    $this->assertSame('- Faster', $document->section('results'));
    $this->assertSame('', $document->section('solution'));
    $this->assertFalse($document->hasPlaceholders());
    $this->assertSame(hash('sha256', $source), $document->hash);
  }

  /**
   * Placeholders are detected in the front matter and in the text.
   */
  public function testPlaceholders(): void {
    $in_front_matter = MarkdownDocument::parse("---\nid: a\ntitle: \"[[Your name]]\"\n---\nText", 'a.md', 'fr');
    $in_body = MarkdownDocument::parse("---\nid: a\ntitle: Real\n---\nBy [[Prénom Nom]].", 'a.md', 'fr');
    $this->assertTrue($in_front_matter->hasPlaceholders());
    $this->assertTrue($in_body->hasPlaceholders());
  }

  /**
   * A file without front matter is rejected.
   */
  public function testMissingFrontMatter(): void {
    $this->expectException(\InvalidArgumentException::class);
    MarkdownDocument::parse('# Just Markdown', 'fr/x.md', 'fr');
  }

}
