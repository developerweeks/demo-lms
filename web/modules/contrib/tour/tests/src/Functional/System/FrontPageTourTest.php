<?php

namespace Drupal\Tests\tour\Functional\System;

use Drupal\Tests\tour\Functional\TourTestBase;

/**
 * Tests tour functionality when using front page rout.
 *
 * @group tour
 */
class FrontPageTourTest extends TourTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'node', 'tour_test'];

  /**
   * {@inheritdoc}
   */
  protected array $permissions = ['administer blocks', 'access tour'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
    $id = $this->drupalCreateNode(['promote' => 1])->id();

    // Configure 'node' as front page.
    $this->config('system.site')->set('page.front', '/node/' . $id)->save();
  }

  /**
   * Test tips appear for frontpage.
   */
  public function testFrontPageTour(): void {
    $this->drupalGet('<front>');
    $this->assertTourTips();
  }

}
