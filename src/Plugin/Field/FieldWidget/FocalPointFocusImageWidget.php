<?php

/**
 * @file
 * Contains \Drupal\focal_point_focus\Plugin\Field\FieldWidget\FocalPointFocusImageWidget.
 */

namespace Drupal\focal_point_focus\Plugin\Field\FieldWidget;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\crop\Entity\Crop;
use Drupal\focal_point\Plugin\Field\FieldWidget\FocalPointImageWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;

/**
 * Extension of focal_point's FocalPointImageWidget adding a crop area.
 *
 * @see focal_point_focus_field_widget_form_alter
 */
class FocalPointFocusImageWidget extends FocalPointImageWidget {

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $item = $element['#value'];
    // prepend the value of $element_selector of focal_point with "crop-" to
    // be able to find our field with js
    $element_selector = 'crop-focal-point-' . implode('-', $element['#parents']);

    $element['crop_rect'] = [
      '#type' => 'textfield',
      '#title' => t('Crop rectangle'),
      '#element_validate' => [[__CLASS__, 'validateCropRectFormat']],
      '#description' => t('Coordinates of the crop rectangle "x1,y1,x2,y2".'),
      '#default_value' => $item['crop_rect'],
      '#attributes' => array(
        'class' => array('focal-point-focus-crop', $element_selector),
      ),
      '#attached' => [
        'library' => ['focal_point_focus/widget'],
        'drupalSettings' => [
          'focal_point_focus' => [
            'width' => $element['width']['#value'],
            'height' => $element['height']['#value'],
          ]
        ],
      ],
    ];
    $element['#element_validate'][] = [__CLASS__, 'validateValues'];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function value($element, $input = FALSE, FormStateInterface $form_state) {
    $return = parent::value($element, $input, $form_state);

    // When an element is loaded, crop_rect needs to be set. During a form
    // submission the value will already be there.
    if (isset($return['target_id']) && !isset($return['crop_rect'])) {
      /** @var \Drupal\file\FileInterface $file */
      $file = \Drupal::service('entity_type.manager')
        ->getStorage('file')
        ->load($return['target_id']);
      $crop_type = \Drupal::config('focal_point.settings')->get('crop_type');
      $crop = Crop::findCrop($file->getFileUri(), $crop_type);
      if ($crop) {
        $crop_rect = array_map(function ($item) { return $item['value']; }, $crop->focus_crop_coords->getValue());
        $return['crop_rect'] = join(',', $crop_rect);
      }
    }
    return $return;
  }

  /**
   * Validation callback enforcing the input formally.
   */
  public static function validateCropRectFormat($element, FormStateInterface $form_state) {
    $crop_rect = $element['#value'];
    if (empty($crop_rect)) {
      return;
    }

    if (!preg_match('#^\d+,\d+,\d+,\d+$#', $crop_rect)) {
      $form_state->setError($element, new TranslatableMarkup('%title needs to be either empty or four non-negative integers separated by comma.', array('%title' => $element['#title'])));
    }

    $crop_coords = explode(',', $crop_rect);
    if (
      $crop_coords[0] >= $crop_coords[2] || // Left is greater than right
      $crop_coords[1] >= $crop_coords[3] // Upper is greater than lower
    ) {
      $form_state->setError($element, new TranslatableMarkup('The first two value need to describe the upper left corner of the crop rectangle.'));
    }
  }

  /**
   * Validation callback checking for value sanity such as the focal point being
   * inside the crop rectangle and the crop rectangle being inside the image
   * boundaries.
   */
  public static function validateValues($element, FormStateInterface $form_state) {
    if (empty($element['#value']['crop_rect'])) {
      return;
    }

    $crop_rect = explode(',', $element['#value']['crop_rect']);
    if (
      $crop_rect[0] > $element['#value']['width'] || // Left is greater than image width
      $crop_rect[1] > $element['#value']['height'] || // Upper is greater than image height
      $crop_rect[2] > $element['#value']['width'] || // Right is greater than image width
      $crop_rect[3] > $element['#value']['height'] // Lower is greater than image height
    ) {
      $form_state->setError($element, new TranslatableMarkup('The crop rectangle needs to be within the image boundaries.'));
    }

    $focal_point = explode(',', $element['#value']['focal_point']);

    $real_focal_point = [
      $focal_point[0] * $element['#value']['width'] / 100,
      $focal_point[1] * $element['#value']['height'] / 100,
    ];

    if (
      $crop_rect[0] > $real_focal_point[0] || // Focal point is smaller then left
      $crop_rect[1] > $real_focal_point[1] || // Focal point is smaller then upper
      $crop_rect[2] < $real_focal_point[0] || // Focal point is greater then right
      $crop_rect[3] < $real_focal_point[1] // Focal point is greater then lower
    ) {
      $form_state->setError($element, new TranslatableMarkup('The focal point needs to be inside the crop rectancle!'));
    }
  }
}
