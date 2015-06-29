<?php
/**
 * @file
 * Token callbacks for the telephone_sms_verify module.
 */

/**
 * Implements hook_token_info_alter()
 */
function telephone_sms_verify_token_info_alter(&$data) {
  $data['tokens']['sms']['verify_code'] = array(
    'name' => t('Verify code'),
    'description' => t('The verify code in SMS'),
  );
}

/**
 * Implements hook_tokens()
 */
function telephone_sms_verify_tokens($type, $tokens, array $data = array(), array $options = array()) {
  $replacements = array();
  if ($type == 'sms' && isset($_SESSION['smscode'])) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'verify_code':
          $replacements[$original] = $_SESSION['smscode'];
          break;
      }
    }
  }
  return $replacements;
}
