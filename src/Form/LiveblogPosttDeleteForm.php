<?php

namespace Drupal\liveblog\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting a liveblog_post entity.
 *
 * @ingroup liveblog_post
 */
class LiveblogPostDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete entity %title?', array('%title' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the liveblog_post list.
   */
  public function getCancelUrl() {
    return new Url('entity.liveblog_post.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();

    $this->logger('liveblog_post')->notice('@type: deleted %title.',
      array(
        '@type' => $this->entity->bundle(),
        '%title' => $this->entity->label(),
      ));
    $form_state->setRedirect('entity.liveblog_post.collection');
  }

}
