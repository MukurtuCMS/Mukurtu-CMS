<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\mukurtu_core\Traits\EntityReferenceRoleUpdateTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests roles on media Contributor and People, and their update path.
 */
#[Group('mukurtu_media')]
class MediaPersonFieldRoleTest extends MukurtuMediaTestBase {

  use EntityReferenceRoleUpdateTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['flat_taxonomy'];

  /**
   * Creates and saves a term.
   */
  protected function term(string $vid, string $name): Term {
    $term = Term::create(['vid' => $vid, 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Roles persist on media Contributor and People.
   */
  public function testRolesPersistOnMedia(): void {
    Vocabulary::create(['vid' => 'role', 'name' => 'Role'])->save();
    $role = $this->term('role', 'Speaker');
    $contributor = $this->term('contributor', 'Elder Mary');
    $person = $this->term('people', 'Annie James');

    $media = $this->buildMedia('audio', 'Recording');
    $media->set('field_contributor', [['target_id' => $contributor->id(), 'role_target_id' => $role->id()]]);
    $media->set('field_people', [['target_id' => $person->id()]]);
    $media->save();

    $loaded = $this->reloadEntity('media', $media->id());
    $this->assertEquals($role->id(), $loaded->get('field_contributor')->role_target_id);
    $this->assertEquals($person->id(), $loaded->get('field_people')->target_id);
    $this->assertNull($loaded->get('field_people')->role_target_id);
  }

  /**
   * The update converts a site's existing media person fields.
   */
  public function testUpdateConvertsMediaPersonFields(): void {
    $contributor = $this->term('contributor', 'Elder Mary');
    $person = $this->term('people', 'Annie James');
    $media = $this->buildMedia('audio', 'Recording');
    $media->set('field_contributor', [['target_id' => $contributor->id()]]);
    $media->set('field_people', [['target_id' => $person->id()]]);
    $media->save();

    foreach (['field_contributor', 'field_people'] as $field_name) {
      $this->rollBackToEntityReference('media', $field_name);
      $this->assertStoragePending(TRUE, 'media', $field_name);
    }

    $this->runRoleUpdate();

    $role = $this->term('role', 'Speaker');
    $loaded = $this->reloadEntity('media', $media->id());
    foreach (['field_contributor' => $contributor, 'field_people' => $person] as $field_name => $term) {
      $this->assertStoragePending(FALSE, 'media', $field_name);
      $this->assertEquals($term->id(), $loaded->get($field_name)->target_id, $field_name);
      $this->assertNull($loaded->get($field_name)->role_target_id, $field_name);
      $loaded->get($field_name)->role_target_id = $role->id();
    }
    $loaded->save();

    $reloaded = $this->reloadEntity('media', $media->id());
    $this->assertEquals($role->id(), $reloaded->get('field_contributor')->role_target_id);
    $this->assertEquals($role->id(), $reloaded->get('field_people')->role_target_id);
  }

}
