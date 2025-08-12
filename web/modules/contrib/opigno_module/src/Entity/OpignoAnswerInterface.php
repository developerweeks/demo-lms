<?php

namespace Drupal\opigno_module\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\group\Entity\GroupInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Answer entities.
 *
 * @ingroup opigno_module
 */
interface OpignoAnswerInterface extends EntityChangedInterface, EntityOwnerInterface, RevisionableInterface {

  /**
   * Gets the Answer type.
   *
   * @return string
   *   The Answer type.
   */
  public function getType();

  /**
   * Gets the Answer creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Answer.
   */
  public function getCreatedTime();

  /**
   * Sets the Answer creation timestamp.
   *
   * @param int $timestamp
   *   The Answer creation timestamp.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the score the user earned for the answer.
   *
   * @return int
   *   The answer score.
   */
  public function getScore(): int;

  /**
   * Sets the answer score.
   *
   * @param string|int $value
   *   The answer score to be set.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setScore(string|int $value): OpignoAnswerInterface;

  /**
   * Gets the related User module status entity.
   *
   * @return \Drupal\opigno_module\Entity\UserModuleStatusInterface|null
   *   The related user module status entity.
   */
  public function getUserModuleStatus(): ?UserModuleStatusInterface;

  /**
   * Gets the related activity entity.
   *
   * @return \Drupal\opigno_module\Entity\OpignoActivityInterface|null
   *   The related activity entity.
   */
  public function getActivity(): ?OpignoActivityInterface;

  /**
   * Gets the related Opigno module entity.
   *
   * @return \Drupal\opigno_module\Entity\OpignoModuleInterface|null
   *   The related Opigno module entity.
   */
  public function getModule(): ?OpignoModuleInterface;

  /**
   * Gets the related learning path entity.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The related learning path entity.
   */
  public function getLearningPath(): ?GroupInterface;

  /**
   * Sets the skills mode value.
   *
   * @param int|null $value
   *   The skills mode value to be set.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setSkillId(?int $value): OpignoAnswerInterface;

  /**
   * Gets the skills mode.
   *
   * @return int
   *   The skills mode.
   */
  public function getSkillId(): int;

  /**
   * Sets the skills level value.
   *
   * @param int|null $value
   *   The skills level value to be set.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setSkillLevel(?int $value): OpignoAnswerInterface;

  /**
   * Gets the evaluation status value.
   *
   * @return bool
   *   The evaluation status value.
   */
  public function isEvaluated(): bool;

  /**
   * Sets the evaluation status value.
   *
   * @param bool $value
   *   The evaluation status to be set.
   *
   * @return \Drupal\opigno_module\Entity\OpignoAnswerInterface
   *   The called Answer entity.
   */
  public function setEvaluated(bool $value): OpignoAnswerInterface;

}
