(function($, Drupal) {
'use strict';

  // Drupal behavior.
  Drupal.behaviors.focal_point_focus = {
    attach: function(context, settings) {
      $(".focal-point-focus-crop", context).once('focal-point-hide-crop-field').each(function() {
        if (!$(this).hasClass('error')) {
          $(this).closest('.form-item').hide();
        }
      });

      $(".focal-point-indicator", context).once('focal-point-focus-crop').each(function() {

        var indicator = $(this);
        var img = indicator.siblings('img');
        var field = $("." + "crop-" + indicator.attr('data-selector'));

        var fpf = new Drupal.FocalPointFocus(img, field, settings.focal_point_focus, indicator);

        // Set the position of the indicator on image load and any time the
        // field value changes. We use a bit of hackery to make certain that the
        // image is loaded before moving the crosshair. See http://goo.gl/B02vFO
        // The setTimeout was added to ensure the focal point is set properly on
        // modal windows. See http://goo.gl/s73ge.
        setTimeout(function() {
          img.one('load', function(){
            fpf.setup();
          }).each(function() {
            if (this.complete) $(this).load();
          });
        }, 0);

      });
    }
  };


  Drupal.FocalPointFocus = function(img, field, settings, indicator) {
    var self = this;

    this.img = img;
    this.field = field;
    this.settings = settings;
    this.indicator = indicator;
  }

  Drupal.FocalPointFocus.prototype.setup = function() {
    var fpf = this;
    this.img.wrap('<div class="focal-point-focal-outer-rect"></div>');
    this.outerRect = this.img.parent()
      .width(this.img.width())
      .height(this.img.height());
    this.cropRect = $('<div class="crop-rect"></div>').appendTo(this.outerRect);
    var settings = this.settings;

    // Allow users to double-click the indicator to reveal the focal point form
    // element.
    this.indicator.on('dblclick', function() {
      fpf.field.closest('.form-item').toggle();
    });

    this.outerRect.drag('start', function(ev, dd) {
      self = $(this);
      var relX = ev.pageX - self.offset().left;
      var relY = ev.pageY - self.offset().top;
      var fullX = relX / self.width() * settings.width;
      var fullY = relY / self.height() * settings.height;
      fpf.outerRect.data('start', [fullX, fullY]);
    });

    this.outerRect.drag(function(ev, dd) {
      var x1, y1, x2, y2;
      [x1, y1] = fpf.outerRect.data('start');
      var fullDeltaX = dd.deltaX / self.width() * settings.width;
      var fullDeltaY = dd.deltaY / self.height() * settings.height;

      // Handle negative delta values (dragging left and/or up)
      if (fullDeltaX > 0) {
        x2 = x1 + fullDeltaX;
      }
      else {
        x2 = x1;
        x1 = x2 + fullDeltaX;
      }
      if (fullDeltaY > 0) {
        y2 = y1 + fullDeltaY;
      }
      else {
        y2 = y1;
        y1 = y2 + fullDeltaY;
      }

      // Handle dragging out of image boundaries
      if (x1 < 0) { x1 = 0; }
      if (y1 < 0) { y1 = 0; }
      if (x2 > settings.width) { x2 = settings.width; }
      if (y2 > settings.height) { y2 = settings.height; }

      fpf.setCoords(x1, y1, x2, y2);
    });

  }
  Drupal.FocalPointFocus.prototype.drawRect = function() {
    var coords = this.field.val().split(',');
    var x1, y1, x2, y2;

    if (4 == coords.length) {
      x1 = coords[0]/this.settings.width * this.outerRect.width();
      y1 = coords[1]/this.settings.height * this.outerRect.height();
      x2 = coords[2]/this.settings.width * this.outerRect.width();
      y2 = coords[3]/this.settings.height * this.outerRect.height();
    }
    else {
      x1 = 0;
      y1 = 0;
      x2 = this.outerRect.width();
      y2 = this.outerRect.height();
    }

    this.cropRect.css({
      left: x1,
      top: y1,
      width: x2 - x1,
      height: y2 - y1
    });
  }

  Drupal.FocalPointFocus.prototype.setCoords = function(x1,y1,x2,y2) {
    this.field.val([x1, y1, x2, y2].map(Math.round).join(','));
    this.drawRect();
  }
})(jQuery, Drupal);
