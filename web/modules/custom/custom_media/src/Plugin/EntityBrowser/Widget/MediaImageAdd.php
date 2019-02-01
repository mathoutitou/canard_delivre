<?php

namespace Drupal\custom_media\Plugin\EntityBrowser\Widget;

/**
 * Create media entity images.
 *
 * @EntityBrowserWidget(
 *   id = "custom_media_image_add",
 *   label = @Translation("custom.media.entity_browser.image.widget.label"),
 *   description = @Translation("custom.media.entity_browser.image.widget.description"),
 *   auto_select = FALSE
 * )
 */
class MediaImageAdd extends AbstractMediaAdd {

  /**
   * {@inheritdoc}
   */
  const SOURCE = 'image';

}
