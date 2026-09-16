<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

/**
 * Tests LocalContextsSupportedProjectManager: site and group project CRUD.
 *
 * Covers: addSiteProject, isSiteSupportedProject, addGroupProject,
 * isGroupSupportedProject, getSiteSupportedProjects, getGroupSupportedProjects,
 * removeSiteProject, removeGroupProject, removeProject (force), and
 * idempotent add behavior.
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_local_contexts')]
class LocalContextsSupportedProjectTest extends LocalContextsTestBase {

  /**
   * Test that a project can be added as a site project and detected.
   */
  public function testAddAndDetectSiteProject(): void {
    $projectId = 'test-project-site-1';
    $this->insertProjectRecord($projectId, 'Site Project One');

    $this->assertFalse($this->manager->isSiteSupportedProject($projectId));

    $this->manager->addSiteProject($projectId);

    $this->assertTrue($this->manager->isSiteSupportedProject($projectId));
  }

  /**
   * Test that addSiteProject is idempotent: calling it twice does not
   * insert a duplicate row (which would cause a primary key violation).
   */
  public function testAddSiteProjectIdempotent(): void {
    $projectId = 'test-project-site-idempotent';
    $this->insertProjectRecord($projectId, 'Idempotent Site Project');

    $this->manager->addSiteProject($projectId);
    // Should not throw a database exception.
    $this->manager->addSiteProject($projectId);

    $this->assertTrue($this->manager->isSiteSupportedProject($projectId));
  }

  /**
   * Test that isSiteSupportedProject returns false for an unknown project.
   */
  public function testIsSiteSupportedProjectFalseForUnknown(): void {
    $this->assertFalse($this->manager->isSiteSupportedProject('nonexistent-project-id'));
  }

  /**
   * Test that a project can be added as a group project and detected.
   */
  public function testAddAndDetectGroupProject(): void {
    $projectId = 'test-project-group-1';
    $this->insertProjectRecord($projectId, 'Community Project One');

    $this->assertFalse($this->manager->isGroupSupportedProject($this->community, $projectId));

    $this->manager->addGroupProject($this->community, $projectId);

    $this->assertTrue($this->manager->isGroupSupportedProject($this->community, $projectId));
  }

  /**
   * Test that addGroupProject is idempotent.
   */
  public function testAddGroupProjectIdempotent(): void {
    $projectId = 'test-project-group-idempotent';
    $this->insertProjectRecord($projectId, 'Idempotent Group Project');

    $this->manager->addGroupProject($this->community, $projectId);
    // Should not throw.
    $this->manager->addGroupProject($this->community, $projectId);

    $this->assertTrue($this->manager->isGroupSupportedProject($this->community, $projectId));
  }

  /**
   * Test that a site project is NOT detected as a group project for a given
   * group (scopes are independent).
   */
  public function testSiteProjectNotDetectedAsGroupProject(): void {
    $projectId = 'test-project-scope-isolation';
    $this->insertProjectRecord($projectId, 'Scope Isolation Project');

    $this->manager->addSiteProject($projectId);

    $this->assertFalse(
      $this->manager->isGroupSupportedProject($this->community, $projectId),
      'A site-scoped project must not be detected as a group project.'
    );
  }

  /**
   * Test that getSiteSupportedProjects returns site projects after a join.
   */
  public function testGetSiteSupportedProjects(): void {
    $projectId = 'test-get-site-projects';
    $this->insertProjectRecord($projectId, 'Get Site Project');

    $this->manager->addSiteProject($projectId);

    $projects = $this->manager->getSiteSupportedProjects();
    $this->assertArrayHasKey($projectId, $projects);
    $this->assertEquals('Get Site Project', $projects[$projectId]['title']);
  }

  /**
   * Test that getSiteSupportedProjects does NOT return group projects.
   */
  public function testGetSiteSupportedProjectsExcludesGroupProjects(): void {
    $siteId = 'site-only-project';
    $groupId = 'group-only-project';
    $this->insertProjectRecord($siteId, 'Site Only');
    $this->insertProjectRecord($groupId, 'Group Only');

    $this->manager->addSiteProject($siteId);
    $this->manager->addGroupProject($this->community, $groupId);

    $projects = $this->manager->getSiteSupportedProjects();
    $this->assertArrayHasKey($siteId, $projects);
    $this->assertArrayNotHasKey($groupId, $projects);
  }

  /**
   * Test that getGroupSupportedProjects returns only the group's projects.
   */
  public function testGetGroupSupportedProjects(): void {
    $projectId = 'test-get-group-projects';
    $this->insertProjectRecord($projectId, 'Group Project Fetch');

    $this->manager->addGroupProject($this->community, $projectId);

    $projects = $this->manager->getGroupSupportedProjects($this->community);
    $this->assertArrayHasKey($projectId, $projects);
    $this->assertEquals('Group Project Fetch', $projects[$projectId]['title']);
  }

  /**
   * Test that getGroupSupportedProjects for a different group returns empty.
   */
  public function testGetGroupSupportedProjectsIsolatedByGroup(): void {
    $projectId = 'test-group-isolation';
    $this->insertProjectRecord($projectId, 'Group Isolated Project');

    $this->manager->addGroupProject($this->community, $projectId);

    // The protocol group has no projects.
    $protocolProjects = $this->manager->getGroupSupportedProjects($this->protocol);
    $this->assertArrayNotHasKey($projectId, $protocolProjects);
  }

  /**
   * Test removeSiteProject removes the project from the site scope and
   * completely deletes the project record (no other references).
   */
  public function testRemoveSiteProjectDeletesWhenUnused(): void {
    $projectId = 'test-remove-site-project';
    $this->insertProjectRecord($projectId, 'Removable Site Project');

    $this->manager->addSiteProject($projectId);
    $this->assertTrue($this->manager->isSiteSupportedProject($projectId));

    $this->manager->removeSiteProject($projectId);

    $this->assertFalse($this->manager->isSiteSupportedProject($projectId));

    // The project record itself should also be gone (no other references).
    $projects = $this->manager->getSiteSupportedProjects();
    $this->assertArrayNotHasKey($projectId, $projects);
  }

  /**
   * Test that removeSiteProject does NOT delete the project record when
   * the project is still referenced as a group project.
   */
  public function testRemoveSiteProjectPreservesWhenGroupStillReferences(): void {
    $projectId = 'test-shared-project';
    $this->insertProjectRecord($projectId, 'Shared Project');

    $this->manager->addSiteProject($projectId);
    $this->manager->addGroupProject($this->community, $projectId);

    // Removing from site scope should not delete the project row because
    // the community group still references it.
    $this->manager->removeSiteProject($projectId);

    $this->assertFalse($this->manager->isSiteSupportedProject($projectId));
    $this->assertTrue($this->manager->isGroupSupportedProject($this->community, $projectId));
  }

  /**
   * Test removeGroupProject removes the group scope entry and deletes the
   * project record when there are no remaining references.
   */
  public function testRemoveGroupProjectDeletesWhenUnused(): void {
    $projectId = 'test-remove-group-project';
    $this->insertProjectRecord($projectId, 'Removable Group Project');

    $this->manager->addGroupProject($this->community, $projectId);
    $this->assertTrue($this->manager->isGroupSupportedProject($this->community, $projectId));

    $this->manager->removeGroupProject($this->community, $projectId);

    $this->assertFalse($this->manager->isGroupSupportedProject($this->community, $projectId));

    $projects = $this->manager->getGroupSupportedProjects($this->community);
    $this->assertArrayNotHasKey($projectId, $projects);
  }

  /**
   * Test that removeProject with force_delete=TRUE removes regardless of
   * remaining references.
   */
  public function testForceRemoveProjectIgnoresReferences(): void {
    $projectId = 'test-force-remove-project';
    $this->insertProjectRecord($projectId, 'Force Removable Project');

    $this->manager->addSiteProject($projectId);
    $this->manager->addGroupProject($this->community, $projectId);

    $this->manager->removeProject($projectId, TRUE);

    $this->assertFalse($this->manager->isSiteSupportedProject($projectId));
    $this->assertFalse($this->manager->isGroupSupportedProject($this->community, $projectId));
  }

  /**
   * Test that removeProject without force_delete is a no-op when the project
   * is still in use.
   */
  public function testRemoveProjectWithoutForceIsNoOpWhenInUse(): void {
    $projectId = 'test-in-use-project';
    $this->insertProjectRecord($projectId, 'In-Use Project');

    $this->manager->addSiteProject($projectId);

    // Without force, the project should NOT be deleted because it's still
    // referenced in supported_projects.
    $this->manager->removeProject($projectId, FALSE);

    $this->assertTrue($this->manager->isSiteSupportedProject($projectId));
  }

  /**
   * Test getSiteSupportedProjects with exclude_legacy=TRUE excludes legacy IDs.
   */
  public function testGetSiteSupportedProjectsExcludeLegacy(): void {
    $legacyId = 'default_tk';
    $this->insertProjectRecord($legacyId, 'Legacy TK Project');
    $this->manager->addSiteProject($legacyId);

    $regularId = 'regular-project';
    $this->insertProjectRecord($regularId, 'Regular Project');
    $this->manager->addSiteProject($regularId);

    $allProjects = $this->manager->getSiteSupportedProjects(FALSE);
    $this->assertArrayHasKey($legacyId, $allProjects);

    $filteredProjects = $this->manager->getSiteSupportedProjects(TRUE);
    $this->assertArrayNotHasKey($legacyId, $filteredProjects);
    $this->assertArrayHasKey($regularId, $filteredProjects);
  }

}
