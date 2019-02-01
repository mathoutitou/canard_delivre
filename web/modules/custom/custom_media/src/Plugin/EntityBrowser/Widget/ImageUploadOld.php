<?php

namespace Drupal\custom_media\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload as FileUpload;

/**
 * Uses upload to create media entity images.
 *
 * @EntityBrowserWidget(
 *   id = "media_entity_image_upload",
 *   label = @Translation("Upload images (no longer supported !)"),
 *   description = @Translation("This entity browser widget is no longer supported."),
 *   auto_select = FALSE
 * )
 */
class ImageUploadOld extends FileUpload {

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {
    return ['#markup' => $this->t('This entity browser widget is no longer supported.')];
  }

}
