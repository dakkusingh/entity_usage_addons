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
      $itemCount = count($ids);

      if ($itemCount > $maxExpanded) {
        return $this->linked_usage($itemCount, $entity);
      }
      else {
        return $this->detailed_usage($ids, $sourceType);
      }
    }
  }

  /**
   * @param $ids
   * @param $sourceType
   * @param string $themeType
   *
   * @return array
   */
  protected function detailed_usage($ids, $sourceType, $themeType = 'table') {
    $rows = [];
    $header = [];
    $typeStorage = \Drupal::service('entity_type.manager')->getStorage($sourceType);

    foreach ($ids as $sourceId => $records) {
      $sourceEntity = $typeStorage->load($sourceId);
      $showFields = $this->getSetting('show_fields');
      $row = [];

      if (isset($showFields['entity'])) {
        $link = $sourceEntity->toLink();
        $row[] = $link;
        
        if (!key_exists('entity', $header)) {
          $header['entity'] = $this->t('Entity');
        }
      }

      if (isset($showFields['status'])) {
        if (isset($sourceEntity->status)) {
          $published = !empty($sourceEntity->status->value) ? $this->t('Published') : $this->t('Unpublished');
        }
        else {
          $published = '';
        }

        $row[] = $published;

        if (!key_exists('status', $header)) {
          $header['status'] = $this->t('Status');
        }
      }

      if (isset($showFields['status'])) {
        $row[] = $sourceEntity->getEntityTypeId();

        if (!key_exists('type', $header)) {
          $header['type'] = $this->t('Type');
        }
      }

      $rows[] = $row;
    }

    $build = [
      '#theme' => 'table',
      '#rows' => $rows,
    ];

    if ($this->getSetting('show_header')) {
      $build['#header'] = $header;
    }

    return $build;
  }

  protected function linked_usage($itemCount, $entity) {
    $route = "entity.{$entity->getEntityTypeId()}.entity_usage";
    $url = Url::fromRoute($route, [$entity->getEntityTypeId() => $entity->id()]);
    $link = Link::fromTextAndUrl($itemCount, $url);
    return $link->toString();
  }
}