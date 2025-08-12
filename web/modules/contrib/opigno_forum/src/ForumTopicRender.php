<?php

namespace Drupal\opigno_forum;

/**
 * Forum topic render helper class.
 */
class ForumTopicRender {

  /**
   * Prepares variables for opigno_forum_last_topics_block template.
   *
   * @param array $variables
   *   Main data array.
   */
  public function preprocessForumLastTopicsBlock(array &$variables): void {
    foreach ($variables["elements"]["topics"] as $index => $topic) {
      $variables["elements"]["topics"][$index] = [
        '#theme' => 'opigno_forum_last_topics_item',
        '#topic' => $topic,
      ];
    }
  }

  /**
   * Prepares variables for opigno_forum_last_topics_item template.
   *
   * @param array $variables
   *   Main render array.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function preprocessForumLastTopicsItem(array &$variables): void {
    /** @var \Drupal\node\Entity\Node $topic */
    $topic = &$variables["topic"];
    $variables['name'] = $topic->label();
    $variables['link'] = $topic->toUrl()->toString();
    $variables['new_posts'] = $this->countRecentComments($topic->id());
  }

  /**
   * Counting comments created less than a week ago.
   *
   * @param int $nid
   *   Id of a topic.
   *
   * @return int
   *   Counted comments.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function countRecentComments(int $nid): int {
    $one_week_ago = time() - 604800;
    return \Drupal::entityTypeManager()->getStorage('comment')
      ->getQuery()
      ->accessCheck()
      ->condition('entity_id', $nid)
      ->condition('entity_type', 'node')
      ->condition('created', $one_week_ago, '>=')
      ->count()
      ->execute();
  }

}
