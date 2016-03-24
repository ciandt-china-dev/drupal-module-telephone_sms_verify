<?php

/**
 * Created by PhpStorm.
 * User: Rock
 * Date: 2016/3/24
 * Time: 14:44
 */
namespace Drupal\telephone_sms_verify\Render\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;

class TelephoneSmsVerifyElement extends FormElement {

  /**
   * Returns the element properties for this element.
   *
   * @return array
   *   An array of element properties. See
   *   \Drupal\Core\Render\ElementInfoManagerInterface::getInfo() for
   *   documentation of the standard properties of all elements, and the
   *   return value format.
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#tree' => TRUE,
      '#input' => TRUE,
      '#required' => FALSE,
      '#process' => array(
        array($class, '_telephone_sms_verify_element_expand'),
        array($class, 'form_process_container')
      ),
      '#description' => t('Telephone number'),
      '#default_value' => '',
      '#theme_wrappers' => array('container'),
      '#default_settings' => array(
        'widget' => FALSE,
        'placeholder' => t('Phone Number'),
        'captcha' => TRUE,
        'captcha_type' => 'default',
        'sms_template' => \Drupal::config('telephone_sms_verify.settings')
          ->get('telephone_sms_verify_sms_template'),
        'sms_code_expire' => \Drupal::config('telephone_sms_verify.settings')
          ->get('telephone_sms_verify_sms_code_expire'),
        'sms_code_length' => \Drupal::config('telephone_sms_verify.settings')
          ->get('telephone_sms_verify_sms_code_length'),
        'sms_code_count_down' => \Drupal::config('telephone_sms_verify.settings')
          ->get('telephone_sms_verify_sms_code_count_down'),
        'sms_code_max_request' => \Drupal::config('telephone_sms_verify.settings')
          ->get('telephone_sms_verify_sms_code_max_request'),
        'display_sms_code_verify' => TRUE,
        'require_sms_code_verify_on_change' => TRUE,
      ),
      '#value_callback' => 'elementValueCallback',
      '#element_validate' => array($class, 'validateElement'),
    );
  }

  public static function _telephone_sms_verify_element_expand(&$element, FormStateInterface &$form_state, &$complete_form) {
    if (!isset($element['#settings'])) {
      $element['#settings'] = array();
    }
    $element['#settings'] += $element['#default_settings'];
    $settings = $element['#settings'];

    $settings['display_sms_code_verify'] &= !\Drupal::currentUser()
      ->hasPermission('bypass telephone sms verify');

    // Give user a chance finally alter the sms code verify element behavior
    $context = array(
      'element' => $element,
      'form' => $complete_form,
      'form_state' => $form_state,
    );
    \Drupal::moduleHandler()
      ->alter('sms_code_verify_element_settings', $settings, $context);

    $id_prefix = implode('-', preg_replace('$_$', '-', $element['#array_parents']));

    $element['value'] = array(
      '#type' => \Drupal::moduleHandler()
        ->moduleExists('elements') ? 'telfield' : 'textfield',
      '#title' => $element['#title'],
      '#required' => $element['#required'],
      '#maxlength' => isset($element['#maxlength']) ? $element['#maxlength'] : 11,
      '#description' => $element['#description'],
      '#default_value' => $element['#default_value'],
      '#weight' => 0,
      '#prefix' => '<div id="' . $id_prefix . '-value-wrapper">',
      '#suffix' => '</div>',
      '#placeholder' => $settings['placeholder'],
      '#element_validate' => array(get_called_class(),'validatePhoneValue'),
    );

    if (isset($complete_form['#form_placeholder'])) {
      $element['value']['#form_placeholder'] = $complete_form['#form_placeholder'];
    }

    if ($settings['display_sms_code_verify']) {
      $element['smscode_captcha'] = array(
        '#type' => 'container',
        '#prefix' => '<div id="' . $id_prefix . '-captcha-wrapper">',
        '#suffix' => '</div>',
      );

      $element['smscode'] = array(
        '#type' => 'textfield',
        '#size' => 6,
        '#title' => t('SMS Code'),
        '#weight' => 1,
        '#prefix' => '<div id="' . $id_prefix . '-sms-verification-code-wrapper" class="sms-verification-code">',
        '#element_validate' => array('telephone_sms_verify_smscode_validate'),
        '#required' => $settings['require_sms_code_verify_on_change'],
      );

      $element['send_smscode'] = array(
        '#name' => $id_prefix . '-send-smscode-btn-op',
        '#type' => 'button',
        '#ajax' => array(
          'wrapper' => $id_prefix . '-value-wrapper',
          'callback' => 'telephone_sms_verify_ajax_callback',
        ),
        '#value' => t('Send SMS Code'),
        '#weight' => 2,
        '#prefix' => '<div id="' . $id_prefix . '-send-smscode-btn-wrapper"><div id="' . $id_prefix . '-send-smscode-btn">',
        '#suffix' => '</div><div id="' . $id_prefix . '-send-smscode-count-down"></div></div></div>',
        '#attached' => array(
          'js' => array(
            drupal_get_path('module', 'telephone_sms_verify') . '/telephone_sms_verify.js',
            array(
              'data' => array('smscode_count_down' => $settings['sms_code_count_down']),
              'type' => 'setting'
            ),
          ),
        ),
        '#limit_validation_errors' => array(
          // Validate only the phone number field on AJAX call
          array_merge($element['#array_parents'], array('value')),
        ),
        '#submit' => array(),
      );
    }

    return $element;
  }

  public static function validateElement(&$element, FormStateInterface &$form_state, &$complete_form) {
    $form_state->setValueForElement($element, $element['#value']);
  }

  public static function elementValueCallback($element, $input, FormStateInterface $form_state) {
    if ($element['#settings']['widget']) {
      return $input;
    }
    else {
      return $input['value'];
    }
  }
  
  public static function validatePhoneValue($element, FormStateInterface &$form_state, $form) {
  $path = implode('/', array_slice($element['#array_parents'], 0, -1));
  $parent_element = TelephoneSmsVerifyElement::getElementByArrayPath($form, $path);
  $parent_element_state = TelephoneSmsVerifyElement::getElementByArrayPath($form_state['values'], $path);

  $settings = $parent_element['#settings'];
  $phone_number = TelephoneSmsVerifyElement::formatValue($parent_element_state['value']);
  if ($settings['widget'] && isset($phone_number['value'])) {
    $phone_number = $phone_number['value'];
  }
$phone_number_default = TelephoneSmsVerifyElement::formatValue($parent_element['#default_value']);

$expire = $settings['sms_code_expire'];

//Compute session key
$form_id = $form_state['values']['form_id'];
$session_key = md5($form_id . $phone_number);

$max_request = $settings['sms_code_max_request'];

// Do not send sms verification code on account editing page and the phone number is not changed
if ($settings['display_sms_code_verify'] && $settings['require_sms_code_verify_on_change'] && $phone_number == $phone_number_default) {
  $form_state->setError($element, t('Your phone number has not changed, no SMS verification code is sent.'));
}

if ($settings['display_sms_code_verify'] && isset($_SESSION[$session_key]) && $_SESSION[$session_key]['time'] + 60 * $expire >= time() && $_SESSION[$session_key]['count'] >= $max_request) {
  $minutes_left = ceil((($_SESSION[$session_key]['time'] + 60 * $expire) - time()) / 60);
  $form_state->setError($element, t('You have reached the maximum request limitation, please try again after @expire minutes', array('@expire' => $minutes_left)));
}
}
  public static function getElementByArrayPath($arr, $path) {
    if (!$path) {
      return NULL;
    }

    $segments = is_array($path) ? $path : explode('/', $path);

    $cur =& $arr;

    foreach ($segments as $segment) {
      if (!isset($cur[$segment])) {
        return NULL;
      }

      $cur = $cur[$segment];
    }

    return $cur;
  }
  public static function formatValue($current, $default = '') {
    return isset($current) ? $current : $default;
  }
}