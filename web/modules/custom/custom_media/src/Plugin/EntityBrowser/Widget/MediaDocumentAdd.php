<?php

namespace Drupal\custom_media\Plugin\EntityBrowser\Widget;

/**
 * Create media entity documents.
 *
 * @EntityBrowserWidget(
 *   id = "custom_media_document_add",
 *   label = @Translation("electron.media.entity_browser.document.widget.label"),
 *   description = @Translation("electron.media.entity_browser.document.widget.description"),
 *   auto_select = FALSE
 * )
 */
class MediaDocumentAdd extends AbstractMediaAdd {

  /**
   * {@inheritdoc}
   */
  const SOURCE = 'file';

}
