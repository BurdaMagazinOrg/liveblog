<?php

namespace Drupal\liveblog_pusher\Plugin\LiveblogNotificationChannel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\liveblog\Entity\LiveblogPost;
use Drupal\liveblog\NotificationChannel\NotificationChannelPluginBase;
use Drupal\liveblog_pusher\PusherLoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Pusher.com notification channel.
 *
 * @LiveblogNotificationChannel(
 *   id = "liveblog_pusher",
 *   label = @Translation("Pusher.com"),
 *   description = @Translation("Pusher.com notification channel."),
 * )
 */
class PusherNotificationChannel extends NotificationChannelPluginBase {


  /**
   * The entity type manager.
   *
   * @var \Drupal\liveblog_pusher\PusherLoggerInterface
   */
  protected $logger;

  /**
   * The pusher client.
   *
   * @var \Pusher
   */
  protected $client;


  /**
   * Constructs an EntityForm object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\liveblog_pusher\PusherLoggerInterface $logger
   *   The logger.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, PusherLoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $entity_type_manager);
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('liveblog_pusher.notification_channel.log')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['app_id'] = [
      '#type' => 'textfield',
      '#title' => t('App ID'),
      '#required' => TRUE,
      '#default_value' => !empty($this->configuration['app_id']) ? $this->configuration['app_id'] : '',
      '#description' => t('Please enter your Pusher App ID.'),
    ];
    $form['key'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#required' => TRUE,
      '#default_value' => !empty($this->configuration['key']) ? $this->configuration['key']: '',
      '#description' => t('Please enter your Pusher key.'),
    ];
    $form['secret'] = [
      '#type' => 'textfield',
      '#title' => t('Secret'),
      '#required' => TRUE,
      '#default_value' => !empty($this->configuration['secret']) ? $this->configuration['secret'] : '',
      '#description' => t('Please enter your Pusher secret.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    // Check the required dependency on the Pusher library.
    if (!class_exists('\Pusher')) {
      $form_state->setErrorByName('plugin', t('The "\Pusher" class was not found. Please make sure you have included the <a href="https://github.com/pusher/pusher-http-php">Pusher PHP Library</a>.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @return \Pusher
   *   The notification channel client.
   */
  public function getClient() {
    if (!$this->client) {
      $options = array(
        'encrypted' => true
      );
      $this->client = new \Pusher(
        $this->getConfigurationValue('key'),
        $this->getConfigurationValue('secret'),
        $this->getConfigurationValue('app_id'),
        $options
      );
      $this->client->set_logger($this->logger);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  function triggerLiveblogPostEvent(LiveblogPost $liveblogPost, $event) {
    $client = $this->getClient();

    $channel = "liveblog-{$liveblogPost->id()}";

    $rendered_entity = $this->entityTypeManager->getViewBuilder('liveblog_post')->view($liveblogPost);
    $output = render($rendered_entity);
    $data['rendered_entity'] = $output;

    // Trigger an event by providing event name and payload.
    $response = $client->trigger($channel, $event, $data);

    if (!$response) {
      // Log response if there is an error.
      $this->logger->saveLog('error');
    }
  }

}
