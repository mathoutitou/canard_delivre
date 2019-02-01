<?php

namespace Drupal\custom_media\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\WidgetBase;
use Drupal\media\MediaInterface;

/**
 * Abstract class for create media entity.
 *
 * @package Drupal\custom_media\Plugin\EntityBrowser\Widget
 */
abstract class AbstractMediaAdd extends WidgetBase {

  /**
   * The media type source to create.
   *
   * @var string
   */
  const SOURCE = '';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'media_type' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $aditional_widget_parameters);

    $form_display = entity_get_form_display('media', $this->configuration['media_type'], 'default');
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => $this->configuration['media_type'],
    ]);
    $form_display->buildForm($media, $form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $form_display = entity_get_form_display('media', $this->configuration['media_type'], 'default');
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => $this->configuration['media_type'],
    ]);
    $form_display->extractFormValues($media, $form, $form_state);
    return [$media];
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $entities = $this->prepareEntities($form, $form_state);
      array_walk(
        $entities,
        function (MediaInterface $media) {
          $media->save();
        }
      );

      $this->selectEntities($entities, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $media_type_options = [];
    $media_types = $this
      ->entityTypeManager
      ->getStorage('media_type')
      ->loadByProperties(['source' => static::SOURCE]);

    foreach ($media_types as $media_type) {
      $media_type_options[$media_type->id()] = $media_type->label();
    }

    if (empty($media_type_options)) {
      $url = Url::fromRoute('entity.media_type.add_form')->toString();
      $form['media_type'] = [
        '#markup' => $this->t('custom.media.entity_browser.widget.error.no_media_type(@source,!link)', [
          '@source' => static::SOURCE,
          '!link' => $url,
        ]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('custom.media.entity_browser.widget.media_type.label'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $media_type_options,
      ];
    }

    return $form;
  }

}
