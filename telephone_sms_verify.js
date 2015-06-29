/**
 * @file
 * Defines the behavior of the Telephone SMS Verify Count Down.
 */

(function ($) {
  function sms_count_down(seconds, element, callback) {
    element.text(seconds + 's');

    if (seconds > 0) {
      seconds -= 1;
      setTimeout(function () {
        sms_count_down(seconds, element, callback);
      }, 1000);
    } else {
      callback();
    }
  }

  $.fn.DrupalTelephoneSMSVerifyCountDwon = function() {
    var send_smscode_btn = $('#send-smscode-btn');
    var send_smscode_count_down = $('#send-smscode-count-down');
    send_smscode_btn.hide();
    send_smscode_count_down.show();
    sms_count_down(30, send_smscode_count_down, function() {
      send_smscode_btn.show();
      send_smscode_count_down.hide();
    });
  };
})(jQuery);
