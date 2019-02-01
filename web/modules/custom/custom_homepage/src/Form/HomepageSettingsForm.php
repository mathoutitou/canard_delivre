<?php

namespace Drupal\custom_homepage\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class HomepageSettingsForm.
 *
 * @package Drupal\custom_homepage\Form
 */
class HomepageSettingsForm extends ConfigFormBase {

  const CACHE_TAG = 'custom_homepage:settings';

  /**
   * The logger service.
   *
   * @var LoggerChannelFactory
   */
  protected $loggerService;

  /**
   * The entity type manager.
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   *
   * @param EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param LoggerChannelFactory $loggerService
   *   Logger service.
   */
  public function __construct(EntityTypeManager $entityTypeManager, LoggerChannelFactory $loggerService) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerService = $loggerService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Load the services required to construct this class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_homepage_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'custom_homepage.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config($this->getEditableConfigNames()[0]);
    $sliderItems = $settings->get('slider');
    $numberSliderItems = $form_state->get('number_slider_items');

    if (empty($numberSliderItems)) {
      $numberSliderItems = $sliderItems ? count($sliderItems) : 1;
      $form_state->set('number_slider_items', $numberSliderItems);
    }

    $form['slider'] = [
      '#type' => 'container',
      '#title' => $this->t('custom.homepage.form.settings.slider.title'),
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'slider-fieldset-wrapper'
      ],
    ];

    $form['slider']['items'] = [
      '#type' => 'container',
      '#title' => $this->t('custom.homepage.form.settings.slider.title'),
      '#cardinality_multiple' => TRUE,
      '#theme' => 'field_multiple_value_form',
      '#field_name' => 'slider',
      '#field_parents' => ['slider'],
    ];

    for ($i = 0; $i < $numberSliderItems; $i++) {
      $sliderItem = !$form_state->isRebuilding() && $sliderItems ? array_shift($sliderItems) : [];

      $item = [
        '#type' => 'container'
      ];

      $item['target_id'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('custom.homepage.form.settings.slider.item.target_id.title'),
        '#target_type' => 'node',
        '#selection_settings' => [
          'target_bundles' => ['article'],
        ],
        '#default_value' => isset($sliderItem['target_id']) ? $this->entityTypeManager->getStorage('node')->load($sliderItem['target_id']) : NULL,
        '#required' => TRUE,
      ];

      $item['_weight'] = [
        '#type' => 'weight',
        '#default_value' => $i,
      ];

      if ($numberSliderItems > 1) {
        $item['actions']['remove_' . $i] = [
          '#type' => 'submit',
          '#value' => $this->t('custom.homepage.form.settings.slider.item.remove_button.value'),
          '#submit' => ['::removeSliderItem'],
          '#name' => 'remove_slider_item_' . $i,
          '#ajax' => [
            'callback' => '::sliderCallback',
            'wrapper' => 'slider-fieldset-wrapper',
          ],
          '#limit_validation_errors' => array(),
        ];
      }

      $form['slider']['items'][$i] = $item;
    }

    $form['slider']['add'] = [
      '#type' => 'submit',
      '#value' => t('custom.homepage.form.settings.slider.add_button.value'),
      '#submit' => ['::addSliderItem'],
      '#name' => 'add_slider_item',
      '#ajax' => [
        'callback' => '::sliderCallback',
        'wrapper' => 'slider-fieldset-wrapper',
      ],
      '#limit_validation_errors' => array(array('slider')),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $parsedSliderItems = [];
    $sliderItems = $form_state->getValue(['slider', 'items']);

    foreach ($sliderItems as $item) {
      $parsedSliderItems[] = [
        'target_id' => $item['target_id'],
        'weight' => $item['_weight'],
      ];
    }

    // Sort tags by weight before delete "weight" key.
    uasort($parsedSliderItems, array('Drupal\Component\Utility\SortArray', 'sortByWeightElement'));
    foreach ($parsedSliderItems as &$item) {
      unset($item['weight']);
    }

    $this->config($this->getEditableConfigNames()[0])
      ->set('slider', array_values($parsedSliderItems))
      ->save();

    // Log settings update to keep date and author.
    $this->loggerService->get('custom_homepage')->info($this->t('custom.homepage.log.settings.form_submitted'));

    // Invalid cache tag on submit.
    Cache::invalidateTags([self::CACHE_TAG]);

    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback to add a new element to slider list.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public function addSliderItem(array &$form, FormStateInterface $form_state) {
    $count = count($form_state->getValue(['slider', 'items']));

    $form_state->set('number_slider_items', ++$count);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback to remove an element from slider list.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public function removeSliderItem(array &$form, FormStateInterface $form_state) {
    $numTags = $form_state->get('number_slider_items');
    if ($numTags > 1) {
      $form_state->set('number_slider_items', --$numTags);

      $delta = $form_state->getTriggeringElement()['#parents'][2];

      // Remove in values.
      $sliderItems = $form_state->getValue(['slider', 'items']);
      unset($sliderItems[$delta]);
      $form_state->setValue(['slider', 'items'], $sliderItems);

      // Remove in user inputs.
      $user_input = $form_state->getUserInput();
      unset($user_input['slider']['items'][$delta]);
      $user_input['slider']['items'] = array_values($user_input['slider']['items']);
      $form_state->setUserInput($user_input);
    }
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for add and remove slider items.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The changed elements.
   *
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  public function sliderCallback(array &$form, FormStateInterface $form_state) {
    return $form['slider'];
  }

}
