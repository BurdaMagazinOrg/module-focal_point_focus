<?php

/**
 * @file
 * Contains \Drupal\focal_point_focus\Plugin\ImageEffect\FocalPointFocusScaleAndCropImageEffect.
 */

namespace Drupal\focal_point_focus\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\crop\CropInterface;
use Drupal\crop\Entity\Crop;
use Drupal\focal_point\Plugin\ImageEffect\FocalPointScaleAndCropImageEffect;

/**
 * Scales and crops image while keeping its focal point close to centered.
 *
 * @ImageEffect(
 *   id = "focal_point_focus_scale_and_crop",
 *   label = @Translation("Focal Point Focus Scale and Crop"),
 *   description = @Translation("Scales and crops like focal point, but crops once before doing so.")
 * )
 */
class FocalPointFocusScaleAndCropImageEffect extends FocalPointScaleAndCropImageEffect {

  /**
   * The current crop object.
   *
   * @var \Drupal\crop\CropInterface
   */
  protected $crop;

  public function applyEffect(ImageInterface $image) {
    $crop_type = $this->focalPointConfig->get('crop_type');

    /** @var CropInterface $crop */
    if ($this->crop = Crop::findCrop($image->getSource(), $crop_type)) {
      // An existing crop has been found; set the size.
      $this->crop->setSize($this->configuration['width'], $this->configuration['height']);
    }
    else {
      // No existing crop could be found; create a new one using the size.
      $this->crop = $this->cropStorage->create([
        'type' => $crop_type,
        'x' => (int) round($image->getWidth() / 2),
        'y' => (int) round($image->getHeight() / 2),
        'width' => $this->configuration['width'],
        'height' => $this->configuration['height'],
      ]);
    }

    // If we have crop coordinates defined:
    if (!$this->crop->focus_crop_coords->isEmpty()) {
      // 1. Try cropping the image
      if (! $this->preFocalPointCrop($image)) {
        return FALSE;
      }
      // 2. Adjust the x,y values of the crop entity to match the cropped image.
      $new_x = $this->crop->x->value - $this->crop->focus_crop_coords[0]->value;
      $new_y = $this->crop->y->value - $this->crop->focus_crop_coords[1]->value;
      $this->crop->set('x', $new_x);
      $this->crop->set('y', $new_y);
    }

    // Run the parent effect with the pre cropped image.
    return parent::applyEffect($image);
  }


  /**
   * {@inheritdoc}
   */
  public function preFocalPointCrop(ImageInterface $image) {
    if (!$this->crop->focus_crop_coords->isEmpty()) {
      $x = $this->crop->focus_crop_coords[0]->value;
      $y = $this->crop->focus_crop_coords[1]->value;
      $w = $this->crop->focus_crop_coords[2]->value - $x;
      $h = $this->crop->focus_crop_coords[3]->value - $y;

      if (!$image->crop($x, $y, $w, $h)) {
        $this->logger->error(
          'Focal point focus scale and crop failed while initially cropping using the %toolkit toolkit on %path (%mimetype, %dimensions)',
          [
            '%toolkit' => $image->getToolkitId(),
            '%path' => $image->getSource(),
            '%mimetype' => $image->getMimeType(),
            '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
          ]
        );
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * This an almost verbatim copy of the parent implementation.
   * Only difference is we are using the crop entity stored in the property
   * crop, which may have been altered in ::applyEffect().
   */
  public function applyCrop(ImageInterface $image, $original_image_size) {
    $anchor = $this->calculateAnchor($image, $this->crop, $original_image_size);
    if (!$image->crop($anchor['x'], $anchor['y'], $this->configuration['width'], $this->configuration['height'])) {
      $this->logger->error(
        'Focal point scale and crop failed while scaling and cropping using the %toolkit toolkit on %path (%mimetype, %dimensions, anchor: %anchor)',
        [
          '%toolkit' => $image->getToolkitId(),
          '%path' => $image->getSource(),
          '%mimetype' => $image->getMimeType(),
          '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
          '%anchor' => $anchor,
        ]
      );
      return FALSE;
    }

    return TRUE;
  }

}
