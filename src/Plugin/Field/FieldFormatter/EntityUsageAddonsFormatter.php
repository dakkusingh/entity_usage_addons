<?php

namespace Drupal\entity_usage_addons\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldFormatter\BaseFieldFileFormatterBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Formatter that shows the file size in a human readable way.
 *
 * @FieldFormatter(
 *   id = "entity_usage_addons_formatter",
 *   label = @Translation("Entity Usage"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class EntityUsageAddonsFormatter extends BaseFieldFileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $settings = parent::defaultSettings();

    // Default expanded references.
    $settings['max_expanded'] = 3;

    // Default fields to show.
    $settings['show_fields'] = "entity";

    // Show header.
    $settings['show_header'] = FALSE;

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $form['max_expanded'] = [
      '#title' => $this->t('Max number of references to expand'),
      '#description' => $this->t('Max number of references to expand.'),
      '#type' => 'select',
      // TODO: Expose these options in a YAML conf.
      '#options' => [
        0 => 0,
        1 => 1,
        3 => 3,
        5 => 5,
        10 => 10,
        20 => 20,
        50 => 50,
        100 => 100,
      ],
      '#default_value' => $this->getSetting('max_expanded'),
    ];

    $form['show_header'] = [
      '#title' => $this->t('Show Header'),
      '#description' => $this->t('Show Header?.'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_header'),
    ];

    $form['show_fields'] = [
      '#title' => $this->t('Show Fields'),
      '#description' => $this->t('Select the fields to display.'),
      '#type' => 'checkboxes',
      '#options' => [
        'entity' => $this->t('Entity'),
        'status' => $this->t('Status'),
        'type' => $this->t('Type'),
      ],
      '#default_value' => $this->getSetting('show_fields'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function viewValue(FieldItemInterface $item) {
    $entityType = $item->getEntity()->getEntityType()->id();

    $entity = \Drupal::service('entity_type.manager')
      ->getStorage($entityType)
      ->load($item->value);

    $all_usages = \Drupal::service('entity_usage.usage')->listUsage($entity);

    // If there is no usage, breakout.
    if (empty($all_usages)) {
      return;
    }

    foreach ($all_usages as $sourceType => $ids) {
      $maxExpanded = $this->getSetting('max_expanded');

      // Count all usages to determine what type of display to show.
      $itemCount = count($ids);

      if ($itemCount > $maxExpanded) {
        return $this->linkedUsage($itemCount, $entity);
      }
      else {
        return $this->detailedUsage($ids, $sourceType);
      }
    }
  }

  /**
   * Generate Detailed usage.
   *
   * @param array $ids
   *   Ids.
   * @param string $sourceType
   *   Source Type.
   * @param string $themeType
   *   Theme Type.
   *
   * @return array
   *   Return the themed array.
   */
  protected function detailedUsage(array $ids, $sourceType, $themeType = 'table') {
    $rows = [];
    $header = [];

    // Get entity type manager storage.
    $typeStorage = \Drupal::service('entity_type.manager')->getStorage($sourceType);

    // Loop over every usage entry for this entity.
    foreach ($ids as $sourceId => $records) {
      $sourceEntity = $typeStorage->load($sourceId);
      $showFields = array_filter($this->getSetting('show_fields'));

      $row = [];

      // Show Entity field.
      if (in_array('entity', $showFields)) {
        $link = $sourceEntity->toLink();
        $row[] = $link;

        if (!array_key_exists('entity', $header)) {
          $header['entity'] = $this->t('Entity');
        }
      }

      // Show Status field.
      if (in_array('status', $showFields)) {
        if (isset($sourceEntity->status)) {
          $published = !empty($sourceEntity->status->value) ? $this->t('Published') : $this->t('Unpublished');
        }
        else {
          $published = '';
        }

        $row[] = $published;

        if (!array_key_exists('status', $header)) {
          $header['status'] = $this->t('Status');
        }
      }

      // Show Type field.
      if (in_array('type', $showFields)) {
        $row[] = $sourceEntity->getEntityTypeId();

        if (!array_key_exists('type', $header)) {
          $header['type'] = $this->t('Type');
        }
      }

      $rows[] = $row;
    }

    // Render Table.
    $build = [
      // TODO Add logic to get list.
      '#theme' => 'table',
      '#rows' => $rows,
    ];

    // Add header if required.
    if ($this->getSetting('show_header')) {
      $build['#header'] = $header;
    }

    return $build;
  }

  /**
   * Linked Usage.
   *
   * @param int $itemCount
   *   Item Count.
   * @param object $entity
   *   Source Entity.
   *
   * @return \Drupal\Core\GeneratedLink
   *   Link.
   */
  protected function linkedUsage($itemCount, $entity) {
    $route = "entity.{$entity->getEntityTypeId()}.entity_usage";
    $url = Url::fromRoute($route, [$entity->getEntityTypeId() => $entity->id()]);
    $link = Link::fromTextAndUrl($itemCount, $url);

    return $link->toString();
  }

}
