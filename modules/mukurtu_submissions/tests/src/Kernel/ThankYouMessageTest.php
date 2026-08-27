<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Controller\ThankYouController;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;

/**
 * Tests that the thank-you page renders a configured custom message when
 * one is set, falls back to the generic message otherwise, and carries the
 * settings entity's cache tags so edits to it invalidate the cached page.
 *
 * @group mukurtu_submissions
 */
class ThankYouMessageTest extends MukurtuSubmissionsKernelTestBase {

  protected function viewThankYouPage(): array {
    $controller = ThankYouController::create($this->container);
    return $controller->view('node', static::TEST_BUNDLE);
  }

  public function testFallbackMessageWithNoSettingsEntity(): void {
    $build = $this->viewThankYouPage();

    $this->assertArrayHasKey('#markup', $build);
    $this->assertStringContainsString('Thank you for your submission.', (string) $build['#markup']);
    $this->assertArrayNotHasKey('tags', $build['#cache'] ?? []);
  }

  public function testFallbackMessageWhenThankYouTextEmpty(): void {
    SubmissionSettings::create([
      'id' => 'submission_test_content',
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ])->save();

    $build = $this->viewThankYouPage();

    $this->assertArrayHasKey('#markup', $build);
    $this->assertStringContainsString('Thank you for your submission.', (string) $build['#markup']);
  }

  public function testCustomThankYouTextOverridesDefault(): void {
    $settings = SubmissionSettings::create([
      'id' => 'submission_test_content',
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'thank_you_text' => [
        'value' => '<p>Custom thanks!</p>',
        'format' => 'plain_text',
      ],
    ]);
    $settings->save();

    $build = $this->viewThankYouPage();

    $this->assertEquals('processed_text', $build['#type']);
    $this->assertEquals('<p>Custom thanks!</p>', $build['#text']);
    $this->assertEquals('plain_text', $build['#format']);
    $this->assertContains($settings->getCacheTags()[0], $build['#cache']['tags']);
  }

}
