/**
 * @file
 * Defines the behavior of the Telephone SMS Verify Count Down.
 */

(function ($) {
  function smsCountDown(seconds, element, callback) {
    element.text(seconds + 's');

    if (seconds > 0) {
      seconds -= 1;
      setTimeout(function () {
        smsCountDown(seconds, element, callback);
      }, 1000);
    } else {
      callback();
    }
  }

  $.fn.DrupalTelephoneSMSVerifyCountDown = function(btn, count_down) {
    var $smsCodeBtn = $(btn),
      $countDownBtn = $(count_down);

    $smsCodeBtn.hide();
    $countDownBtn.show();

    smsCountDown(Drupal.settings.smscode_count_down, $countDownBtn, function() {
      $smsCodeBtn.show();
      $countDownBtn.hide();
    });

  };
  
})(jQuery);
