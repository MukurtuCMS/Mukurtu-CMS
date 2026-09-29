<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * Tests taxonomy field behaviour on Mukurtu media entities.
 *
 * Covers: auto-create terms (media_tags, contributor, people), multi-value
 * term assignment, term reuse by ID, and protocol access on media entities.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_media')]
class MukurtuMediaTaxonomyTest extends MukurtuMediaTestBase {

  /**
   * Test that saving an audio entity with an unsaved media_tag term auto-creates it.
   *
   * field_media_tags has auto_create=TRUE, so new term entities attached to the
   * field should be persisted when the media entity is saved.
   */
  public function testMediaTagAutoCreate(): void {
    $media = $this->buildMedia('audio', 'Tag Auto-create Test');

    $newTerm = Term::create(['name' => 'Oral Recording', 'vid' => 'media_tag']);
    $media->set('field_media_tags', [['entity' => $newTerm]]);
    $media->save();

    $loaded = Media::load($media->id());
    $tags = $loaded->get('field_media_tags')->referencedEntities();

    $this->assertCount(1, $tags);
    $this->assertEquals('Oral Recording', $tags[0]->getName());
    $this->assertNotNull($tags[0]->id(), 'The media tag term should have been auto-created on save.');
  }

  /**
   * Test that multiple media tags are saved and returned in order.
   */
  public function testMultipleMediaTags(): void {
    $media = $this->buildMedia('audio', 'Multi-tag Test');

    $media->set('field_media_tags', [
      ['entity' => Term::create(['name' => 'Music', 'vid' => 'media_tag'])],
      ['entity' => Term::create(['name' => 'Dance', 'vid' => 'media_tag'])],
      ['entity' => Term::create(['name' => 'Ceremony', 'vid' => 'media_tag'])],
    ]);
    $media->save();

    $loaded = Media::load($media->id());
    $tags = $loaded->get('field_media_tags')->referencedEntities();

    $this->assertCount(3, $tags);
    $this->assertEquals('Music', $tags[0]->getName());
    $this->assertEquals('Dance', $tags[1]->getName());
    $this->assertEquals('Ceremony', $tags[2]->getName());
  }

  /**
   * Test that an existing media_tag term is reused rather than duplicated when
   * a second media entity references the same term by ID.
   */
  public function testExistingMediaTagReuse(): void {
    // Save first entity creating the 'Traditional Song' tag.
    $media1 = $this->buildMedia('audio', 'First Audio');
    $media1->set('field_media_tags', [
      ['entity' => Term::create(['name' => 'Traditional Song', 'vid' => 'media_tag'])],
    ]);
    $media1->save();
    $media1Tags = Media::load($media1->id())->get('field_media_tags')->referencedEntities();
    $this->assertNotEmpty($media1Tags, 'The tag term should have been created and referenced.');
    $term = $media1Tags[0];
    $originalId = $term->id();

    // Save second entity referencing the same term by ID.
    $media2 = $this->buildMedia('document', 'Second Document');
    $media2->set('field_media_tags', [['target_id' => $originalId]]);
    $media2->save();

    $loaded2 = Media::load($media2->id());
    $tags2 = $loaded2->get('field_media_tags')->referencedEntities();

    $this->assertCount(1, $tags2);
    $this->assertEquals($originalId, $tags2[0]->id(), 'The existing tag term should be reused, not duplicated.');
  }

  /**
   * Test that multiple contributor terms can be set on audio (auto_create=TRUE, cardinality=-1).
   */
  public function testMultipleContributors(): void {
    $media = $this->buildMedia('audio', 'Contributors Test');

    $media->set('field_contributor', [
      ['entity' => Term::create(['name' => 'Elder Mary', 'vid' => 'contributor'])],
      ['entity' => Term::create(['name' => 'Elder John', 'vid' => 'contributor'])],
    ]);
    $media->save();

    $loaded = Media::load($media->id());
    $contributors = $loaded->get('field_contributor')->referencedEntities();

    $this->assertCount(2, $contributors);
    $this->assertEquals('Elder Mary', $contributors[0]->getName());
    $this->assertEquals('Elder John', $contributors[1]->getName());
  }

  /**
   * Test that people terms can be added to audio (auto_create=TRUE).
   */
  public function testPeopleTagOnAudio(): void {
    $media = $this->buildMedia('audio', 'People Tag Test');

    $media->set('field_people', [
      ['entity' => Term::create(['name' => 'Annie James', 'vid' => 'people'])],
    ]);
    $media->save();

    $loaded = Media::load($media->id());
    $people = $loaded->get('field_people')->referencedEntities();

    $this->assertCount(1, $people);
    $this->assertEquals('Annie James', $people[0]->getName());
    $this->assertNotNull($people[0]->id(), 'The people term should be auto-created on save.');
  }

  /**
   * Test that people terms can be added to document (auto_create=TRUE).
   */
  public function testPeopleTagOnDocument(): void {
    $media = $this->buildMedia('document', 'Document People Test');

    $media->set('field_people', [
      ['entity' => Term::create(['name' => 'Sam Clearwater', 'vid' => 'people'])],
    ]);
    $media->save();

    $loaded = Media::load($media->id());
    $people = $loaded->get('field_people')->referencedEntities();

    $this->assertCount(1, $people);
    $this->assertEquals('Sam Clearwater', $people[0]->getName());
  }

  /**
   * Test that a media entity under a strict protocol is invisible to a user
   * with no protocol membership, and visible to a member.
   *
   * This smoke test confirms CulturalProtocolControlledInterface works
   * end-to-end on the actual Mukurtu media bundle classes.
   */
  public function testProtocolAccessSmoke(): void {
    $strictProtocol = Protocol::create([
      'name' => 'Strict Media Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strictProtocol->save();
    $strictProtocol->addMember($this->currentUser, ['protocol_steward']);

    $media = $this->buildMedia('audio', 'Restricted Audio');
    $media->setSharingSetting('any');
    $media->setProtocols([$strictProtocol]);
    $media->save();

    // A user with no protocol membership cannot view.
    $outsider = User::create(['name' => $this->randomString()]);
    $outsider->save();
    $this->assertFalse(
      $media->access('view', $outsider),
      'Non-member should not view strict-protocol media.'
    );

    // A protocol steward member can view.
    $this->assertTrue(
      $media->access('view', $this->currentUser),
      'Protocol steward should view strict-protocol media.'
    );
  }

  /**
   * Test that the identifier field persists on all three bundle types.
   */
  public function testIdentifierFieldPersistence(): void {
    foreach (['audio', 'image', 'document'] as $bundle) {
      $media = $this->buildMedia($bundle, "Identifier Test: $bundle");
      $media->set('field_identifier', 'ACC-2024-' . strtoupper($bundle));
      $media->save();

      $loaded = Media::load($media->id());
      $this->assertEquals(
        'ACC-2024-' . strtoupper($bundle),
        $loaded->get('field_identifier')->value,
        "field_identifier should persist on the $bundle bundle."
      );
    }
  }

}
