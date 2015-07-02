<?php
/**
 * @file
 * Token callbacks for the telephone_sms_verify module.
 */

/**
 * Implements hook_token_info().
 */
function telephone_sms_verify_token_info() {
  $info['types']['phone_sms_verify'] = array(
    'name' => t('Telephone SMS Verification'),
    'needs-data' => 'phone_sms_verify',
    'description' => t('Tokens provided by Telephone SMS Verification'),
  );

  $info['tokens']['phone_sms_verify']['verify_code'] = array(
    'name' => t('Verify Code'),
    'description' => t('The verify code of the phone number'),
  );

  $info['tokens']['phone_sms_verify']['expire_time'] = array(
    'name' => t('Expire Time'),
    'description' => t('The expire time (in seconds) of verify code'),
  );

  return $info;
}

/**
 * Implements hook_tokens().
 */
function telephone_sms_verify_tokens($type, $tokens, array $data = array(), array $options = array()) {
  $replacements = array();
  if ($type == 'phone_sms_verify') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'verify_code':
          $replacements[$original] = $data['phone_sms_verify']['verify_code'];
          break;
        case 'expire_time':
          $replacements[$original] = $data['phone_sms_verify']['expire_time'];
          break;
      }
    }
  }
  return $replacements;
}
