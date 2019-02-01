<?php

namespace Drupal\custom_default_content;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\default_content\Event\DefaultContentEvents;
use Drupal\default_content\Event\ImportEvent;
use Drupal\default_content\Importer as OriginalImporter;
use Drupal\user\EntityOwnerInterface;

/**
 * Decorate Importer class.
 */
class Importer extends OriginalImporter {

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public function importContent($module) {
    $created = [];
    $folder = drupal_get_path('module', $module) . '/content';

    if (file_exists($folder)) {
      $root_user = $this->entityTypeManager->getStorage('user')->load(1);
      $this->accountSwitcher->switchTo($root_user);
      $file_map = [];

      $entity_types = $this->entityTypeManager->getDefinitions();

      // Import field collection items at the end.
      if (isset($entity_types['field_collection_item'])) {
        $entity_type = $entity_types['field_collection_item'];
        unset($entity_types['field_collection_item']);
        $entity_types['field_collection_item'] = $entity_type;
      }

      foreach ($entity_types as $entity_type_id => $entity_type) {
        $reflection = new \ReflectionClass($entity_type->getClass());
        // We are only interested in importing content entities.
        if ($reflection->implementsInterface(ConfigEntityInterface::class)) {
          continue;
        }
        if (!file_exists($folder . '/' . $entity_type_id)) {
          continue;
        }
        $files = $this->scanner->scan($folder . '/' . $entity_type_id);
        // Default content uses drupal.org as domain.
        // @todo Make this use a uri like default-content:.
        $this->linkManager->setLinkDomain($this->linkDomain);
        // Parse all of the files and sort them in order of dependency.
        foreach ($files as $file) {
          $contents = $this->parseFile($file);
          // Decode the file contents.
          $decoded = $this->serializer->decode($contents, 'hal_json');
          // Get the link to this entity.
          $item_uuid = $decoded['uuid'][0]['value'];

          // Throw an exception when this UUID already exists.
          if (isset($file_map[$item_uuid])) {
            // Reset link domain.
            $this->linkManager->setLinkDomain(FALSE);
            throw new \Exception(sprintf('Default content with uuid "%s" exists twice: "%s" "%s"', $item_uuid, $file_map[$item_uuid]->uri, $file->uri));
          }

          // Store the entity type with the file.
          $file->entity_type_id = $entity_type_id;
          // Store the file in the file map.
          $file_map[$item_uuid] = $file;
          // Create a vertex for the graph.
          $vertex = $this->getVertex($item_uuid);
          $this->graph[$vertex->id]['edges'] = [];
          if (empty($decoded['_embedded'])) {
            // No dependencies to resolve.
            continue;
          }
          // Here we need to resolve our dependencies:
          foreach ($decoded['_embedded'] as $embedded) {
            foreach ($embedded as $item) {
              $uuid = $item['uuid'][0]['value'];
              $edge = $this->getVertex($uuid);
              $this->graph[$vertex->id]['edges'][$edge->id] = TRUE;
            }
          }
        }
      }

      // @todo what if no dependencies?
      $sorted = $this->sortTree($this->graph);
      foreach ($sorted as $link => $details) {
        if (!empty($file_map[$link])) {
          $file = $file_map[$link];
          $entity_type_id = $file->entity_type_id;
          $class = $this->entityTypeManager->getDefinition($entity_type_id)->getClass();
          $contents = $this->parseFile($file);
          $entity = $this->serializer->deserialize($contents, $class, 'hal_json', ['request_method' => 'POST']);
          $entity->enforceIsNew(TRUE);
          // Ensure that the entity is not owned by the anonymous user.
          if ($entity instanceof EntityOwnerInterface && empty($entity->getOwnerId())) {
            $entity->setOwner($root_user);
          }

          $this->entitySave($entity);
          $created[$entity->uuid()] = $entity;
        }
      }
      $this->eventDispatcher->dispatch(DefaultContentEvents::IMPORT, new ImportEvent($created, $module));
      $this->accountSwitcher->switchBack();
    }
    // Reset the tree.
    $this->resetTree();
    // Reset link domain.
    $this->linkManager->setLinkDomain(FALSE);
    return $created;
  }

  /**
   * Imports user from a given module.
   *
   * @param string $module
   *   The module to create the user from.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of created entities keyed by their UUIDs.
   */
  public function importUser($module) {
    $created = [];
    $entity_type_id = 'user';
    $folder = drupal_get_path('module', $module) . '/content/' . $entity_type_id;

    if (!file_exists($folder)) {
      return;
    }

    $file_map = [];
    $files = $this->scanner->scan($folder);
    $this->linkManager->setLinkDomain($this->linkDomain);
    // Parse all of the files and sort them in order of dependency.
    foreach ($files as $file) {
      $contents = $this->parseFile($file);
      // Decode the file contents.
      $decoded = $this->serializer->decode($contents, 'hal_json');
      // Get the link to this entity.
      $item_uuid = $decoded['uuid'][0]['value'];

      // Throw an exception when this UUID already exists.
      if (isset($file_map[$item_uuid])) {
        // Reset link domain.
        $this->linkManager->setLinkDomain(FALSE);
        throw new \Exception(sprintf('Default content with uuid "%s" exists twice: "%s" "%s"', $item_uuid, $file_map[$item_uuid]->uri, $file->uri));
      }

      // Store the entity type with the file.
      $file->entity_type_id = $entity_type_id;
      // Store the file in the file map.
      $file_map[$item_uuid] = $file;
      // Create a vertex for the graph.
      $vertex = $this->getVertex($item_uuid);
      $this->graph[$vertex->id]['edges'] = [];
      if (empty($decoded['_embedded'])) {
        // No dependencies to resolve.
        continue;
      }
      // Here we need to resolve our dependencies:
      foreach ($decoded['_embedded'] as $embedded) {
        foreach ($embedded as $item) {
          $uuid = $item['uuid'][0]['value'];
          $edge = $this->getVertex($uuid);
          $this->graph[$vertex->id]['edges'][$edge->id] = TRUE;
        }
      }
    }

    $sorted = $this->sortTree($this->graph);
    foreach ($sorted as $link => $details) {
      if (!empty($file_map[$link])) {
        $file = $file_map[$link];
        $entity_type_id = $file->entity_type_id;
        $class = $this->entityTypeManager->getDefinition($entity_type_id)->getClass();
        $contents = $this->parseFile($file);

        $entity = $this->serializer->deserialize($contents, $class, 'hal_json', ['request_method' => 'POST']);

        $already_exists = user_load_by_mail($entity->getEmail());
        if (!$already_exists) {
          // Enforce creation. Don't keep uid and uuid.
          $entity->uid = NULL;
          $entity->uuid = \Drupal::service('uuid')->generate();
          $entity->enforceIsNew(TRUE);

          $entity->save();
          $created[$entity->uuid()] = $entity;
        }
      }
    }
    $this->eventDispatcher->dispatch(DefaultContentEvents::IMPORT, new ImportEvent($created, $module));

    // Reset the tree.
    $this->resetTree();
    // Reset link domain.
    $this->linkManager->setLinkDomain(FALSE);
    return $created;
  }

  /**
   * Save entity with specific rule for "Field collection item" entities.
   *
   * @param EntityInterface $entity
   *   The entity to save.
   */
  private function entitySave(EntityInterface $entity) {
    // Specific rules for "field_collection_item" entities.
    if ($entity->getEntityTypeId() === 'field_collection_item') {
      // Check if host is defined.
      $hostEntity = $entity->getHost(TRUE);
      if (!($hostEntity instanceof EntityInterface)) {
        // Try to verify if host was imported.
        $hostEntities = \Drupal::entityTypeManager()->getStorage($entity->host_type->value)->loadByProperties([
          $entity->bundle() => $entity->id(),
        ]);
        $hostEntity = reset($hostEntities);

        if ($hostEntity instanceof EntityInterface) {
          // Define host but not create link.
          $entity->setHostEntity($hostEntity, FALSE);
        }
        else {
          // Skip host saving.
          $entity->save(TRUE);
          return;
        }
      }
    }
    elseif ($entity->getEntityTypeId() === 'node') {
      /*
       * Node entity has 2 moderation states values :
       * - the first state is 'draft' defined by ContentModeration::getInitialState(),
       * - the second state is 'published' defined by JSON file.
       *
       * If the node has multiple moderation states, we keep the last one.
       *
       * Bug : https://www.drupal.org/project/default_content/issues/2952963
       */
      $node_moderation_states = $entity->get('moderation_state');
      if (count($node_moderation_states) > 1) {
        $value = array_pop($node_moderation_states->getValue())['value'];
        $entity->set('moderation_state', $value);
      }
    }

    $entity->save();
  }

}
