<?php

namespace Drupal\liveblog\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\liveblog\NotificationChannel\NotificationChannelManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller for the liveblog_post entity edit forms.
 *
 * @ingroup liveblog_post
 */
class LiveblogPostForm extends ContentEntityForm {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The notification channel manager.
   *
   * @var \Drupal\liveblog\NotificationChannel\NotificationChannelManager
   */
  protected $notificationChannelManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   The current request.
   * @param \Drupal\liveblog\NotificationChannel\NotificationChannelManager $notification_channel_manager
   *   The notification channel service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, RequestStack $request_stack, NotificationChannelManager $notification_channel_manager) {
    parent::__construct($entity_manager);
    $this->request = $request_stack->getCurrentRequest();
    $this->notificationChannelManager = $notification_channel_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('request_stack'),
      $container->get('plugin.manager.liveblog.notification_channel')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Managed File element ajax fails when working with PrivateTempStore in a
    // form. We have to disable form cache.
    // @see https://www.drupal.org/node/2647812#comment-11683961.
    $form_state->disableCache();

    /* @var $entity \Drupal\liveblog\Entity\LiveblogPost */
    $entity = $this->entity;
    if ($node = $this->getCurrentLiveblogNode()) {
      // Pre-populate liveblog reference if we are at the liveblog page.
      $entity->setLiveblog($node);
    }

    $form = parent::buildForm($form, $form_state);

    $html_id = "{$this->getFormId()}-wrapper";
    if ($node) {
      $form['#attached']['library'][] = 'liveblog/form_improvements';

      $form['#prefix'] = "<div id=\"$html_id\">";
      $form['#suffix'] = '</div>';

      // Hide author and liveblog fields, as they are already pre-populated and
      // should not be changed.
      $form['uid']['#access'] = FALSE;
      $form['liveblog']['#access'] = FALSE;

      $form['actions']['submit']['#ajax'] = [
        'wrapper' => $html_id,
        'callback' => array($this, 'ajaxRebuildCallback'),
        'effect' => 'fade',
      ];
    }

    if ($entity->isNew()) {
      $form['actions']['submit']['#value'] = t('Create');
    }
    else {
      $form['actions']['submit']['#value'] = t('Update');
    }

    // Hide status, source, location fields in a collapsible wrapper.
    $form['additional'] = array(
      '#type' => 'details',
      '#title' => t('Additional'),
      '#weight' => 20,
    );
    foreach (['status', 'source', 'location'] as $key) {
      $form['additional'][$key] = $form[$key];
      unset($form[$key]);
    }

    return $form;
  }

  /**
   * Callback for ajax form submission.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The rebuilt form.
   */
  public function ajaxRebuildCallback(array $form, FormStateInterface $form_state) {
    switch ($this->getOperation()) {
      case 'add':
        drupal_set_message(t('Liveblog post was successfully created.'));
        break;
      case 'edit':
        drupal_set_message(t('Liveblog post was successfully updated.'));
        $html_id = "{$this->getFormId()}-wrapper";
        $element = ['#markup' => "<div id=\"$html_id\"></div>"];
        return $element;
        break;
    }
    return $form;
  }

  /**
   * Gets liveblog node from the current request.
   *
   * Gets the liveblog node from the request if we are at the liveblog page.
   *
   * @return \Drupal\node\Entity\Node|null
   *   The liveblog node, null if not found.
   */
  protected function getCurrentLiveblogNode() {
    /* @var \Drupal\node\Entity\Node $node */
    $node = $this->request->attributes->get('node');
    if ($node && $node->getType() == 'liveblog') {
      return $node;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->save();

    // Trigger an notification channel message.
    if ($plugin = $this->notificationChannelManager->createActiveInstance()) {
      $plugin->triggerLiveblogPostEvent($entity, $this->getOperation());
    }

    if (!$this->getCurrentLiveblogNode()) {
      $url = $entity->toUrl();
      // Redirect to the post's full page if we are not at the liveblog page.
      $form_state->setRedirect($url->getRouteName(), $url->getRouteParameters());
    }
    else if ($this->getOperation() == 'add') {
      // Clear form input fields for the add form, as we stay on the same page.
      $this->clearFormInput($form, $form_state);
    }
  }

  /**
   * Clears form input.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function clearFormInput(array $form, FormStateInterface $form_state) {
    // Replace the form entity with an empty instance.
    $this->entity = $this->entityTypeManager->getStorage('liveblog_post')->create([]);
    // Clear user input.
    $input = $form_state->getUserInput();
    // We should not clear the system items from the user input.
    $clean_keys = $form_state->getCleanValueKeys();
    $clean_keys[] = 'ajax_page_state';
    foreach ($input as $key => $item) {
      if (!in_array($key, $clean_keys) && substr($key, 0, 1) !== '_') {
        unset($input[$key]);
      }
    }
    $form_state->setUserInput($input);
    // Rebuild the form state values.
    $form_state->setRebuild();
  }

}
