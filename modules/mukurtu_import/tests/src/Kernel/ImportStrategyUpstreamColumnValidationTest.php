<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests file-aware validation of identifier/label/media-source columns.
 *
 * ExecuteImportForm::detectUpstreamDependencies() correlates a downstream
 * migration's entity reference field to an upstream migration's real source
 * IDs, using whichever of the upstream config's identifier, label, or media
 * source column is configured. Before validating those columns against the
 * upstream file's actual headers, a stale/reused template could hand a
 * column name absent from that file down as a `lookup_source_ids` value,
 * which aborts the whole migration at Row construction (see
 * ImportBatchFinishedSuccessGatingTest::testEndToEndMigrationFailureYieldsUnsuccessfulResult
 * for what that failure looks like). getIdentifierColumn(), getLabelSourceColumn(),
 * and getMediaSourceColumn() now accept the file being imported and return
 * NULL instead of a column that isn't actually one of its headers, so
 * detectUpstreamDependencies() can fall through to record numbers instead.
 */
class ImportStrategyUpstreamColumnValidationTest extends MukurtuImportTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media', 'image'];

  /**
   * getIdentifierColumn() only validates against a file's headers when one
   * is passed; the no-argument call site (e.g. getUnmatchedIdentifierColumns())
   * is unaffected.
   */
  public function testGetIdentifierColumnValidatesAgainstFileHeaders() {
    $file = $this->createCsvFile([
      ['title', 'Real Column'],
      ['Some Title', 'value'],
    ]);

    $config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config->setTargetEntityTypeId('node');
    $config->setTargetBundle('protocol_aware_content');
    $config->setConfig('identifier_column', 'Missing Column');

    $this->assertEquals('Missing Column', $config->getIdentifierColumn());
    $this->assertNull($config->getIdentifierColumn($file));

    $config->setConfig('identifier_column', 'Real Column');
    $this->assertEquals('Real Column', $config->getIdentifierColumn($file));
  }

  /**
   * getLabelSourceColumn() drops a mapped label column that isn't a header
   * in the given file.
   */
  public function testGetLabelSourceColumnValidatesAgainstFileHeaders() {
    $file = $this->createCsvFile([
      ['title'],
      ['Some Title'],
    ]);

    $config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config->setTargetEntityTypeId('node');
    $config->setTargetBundle('protocol_aware_content');
    $config->setMapping([
      ['target' => 'title', 'source' => 'Old Title Column'],
    ]);

    $this->assertEquals('Old Title Column', $config->getLabelSourceColumn());
    $this->assertNull($config->getLabelSourceColumn($file));

    $config->setMapping([
      ['target' => 'title', 'source' => 'title'],
    ]);
    $this->assertEquals('title', $config->getLabelSourceColumn($file));
  }

  /**
   * getMediaSourceColumn() drops a mapped media source column that isn't a
   * header in the given file.
   */
  public function testGetMediaSourceColumnValidatesAgainstFileHeaders() {
    $media_type = $this->createMediaType('image');
    $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type)->getName();

    $file = $this->createCsvFile([
      ['name', 'Image'],
      ['Some Media', 'photo.jpg'],
    ]);

    $config = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $config->setTargetEntityTypeId('media');
    $config->setTargetBundle($media_type->id());
    $config->setMapping([
      ['target' => 'name', 'source' => 'name'],
      ['target' => "$source_field/target_id", 'source' => 'Old Image Column'],
    ]);

    $this->assertEquals('Old Image Column', $config->getMediaSourceColumn());
    $this->assertNull($config->getMediaSourceColumn($file));

    $config->setMapping([
      ['target' => 'name', 'source' => 'name'],
      ['target' => "$source_field/target_id", 'source' => 'Image'],
    ]);
    $this->assertEquals('Image', $config->getMediaSourceColumn($file));
  }

}
