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

    $form['show_fields'] = [
      '#title' => $this->t('Show Fields'),
      '#description' => $this->t('Select the fields to display.'),
      '#type' => 'checkboxes',
      '#options' => [
        'entity' => $this->t('Entity'),
        'type' => $this->t('Type'),
        'field_name' => $this->t('Field name'),
        'status' => $this->t('Status'),
        'used_in' => $this->t('Used in'),
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
   * {@inheritdoc}
   */
//  public function viewElements(FieldItemListInterface $items, $langcode) {
//    $elements = [];
//
//    foreach ($items as $delta => $item) {
//      $elements[$delta] = ['#markup' => $item->value];
//      //ksm($item);
//    }
//
//    return $elements;
//  }

  /**
   * @param $ids
   * @param $sourceType
   * @param string $themeType
   *
   * @return array
   */
  protected function detailed_usage($ids, $sourceType, $themeType = 'table') {
    $rows = [];

    $typeStorage = \Drupal::service('entity_type.manager')->getStorage($sourceType);

    foreach ($ids as $sourceId => $records) {
      $sourceEntity = $typeStorage->load($sourceId);
      $link = $sourceEntity->toLink();

      if (isset($sourceEntity->status)) {
        $published = !empty($sourceEntity->status->value) ? $this->t('Published') : $this->t('Unpublished');
      }
      else {
        $published = '';
      }

      $rows[] = [
        $link,
        //$sourceType->getLabel(),
        //$languages[$default_langcode]->getName(),
        //$field_label,
        $published,
        //$used_in_text,
      ];
    }

    $header = [
      $this->t('Entity'),
      //$this->t('Type'),
      //$this->t('Field name'),
      $this->t('Status'),
      //$this->t('Used in'),
    ];

    $build = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

  protected function linked_usage($itemCount, $entity) {
    $route = "entity.{$entity->getEntityTypeId()}.entity_usage";
    $url = Url::fromRoute($route, [$entity->getEntityTypeId() => $entity->id()]);
    $link = Link::fromTextAndUrl($itemCount, $url);
    return $link->toString();
  }
}