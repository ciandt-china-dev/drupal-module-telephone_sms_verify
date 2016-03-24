<?php

namespace Drupal\telephone_sms_verify\Render\Element;

use  \Drupal\Core\Ajax\RemoveCommand;
use \Drupal\Core\Ajax\ReplaceCommand;
use \Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form element for an HTML 'telephone_with_sms_verify' input element.
 *
 * Specify either #default_value or #value but not both.
 *
 * Properties:
 * - #default_value: The initial value of the form element. JavaScript may
 *   alter the value prior to submission.
 * - #value: The value of the form element. The Form API ensures that this
 *   value remains unchanged by the browser.
 *
 * Usage example:
 * @code
 * $form['entity_id'] = array('#type' => 'telephone_with_sms_verify', '#value' => $entity_id);
 * @endcode
 *
 *
 * @FormElement("telephone_with_sms_verify")
 */
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
      '#value_callback' => array($class, 'elementValueCallback'),
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
      '#element_validate' => array(get_called_class(), 'validatePhoneValue'),
    );

    if (isset($complete_form['#form_placeholder'])) {
      $element['value']['#form_placeholder'] = $complete_form['#form_placeholder'];
    }

    if ($settings['display_sms_code_verify']) {
      $element['smscode'] = array(
        '#type' => 'textfield',
        '#size' => 6,
        '#title' => t('SMS Code'),
        '#weight' => 1,
        '#prefix' => '<div id="' . $id_prefix . '-sms-verification-code-wrapper" class="sms-verification-code">',
        '#element_validate' => array(
          get_called_class(),
          'validateSmsVerifyCode'
        ),
        '#required' => $settings['require_sms_code_verify_on_change'],
      );

      $element['send_smscode'] = array(
        '#name' => $id_prefix . '-send-smscode-btn-op',
        '#type' => 'button',
        '#ajax' => array(
          'wrapper' => $id_prefix . '-value-wrapper',
          'callback' => 'Drupal\telephone_sms_verify\Render\Element\TelephoneSmsVerifyElement::ajaxCallback',
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

  public static function ajaxCallback($form, FormStateInterface $form_state) {
    if (preg_match('/captcha-verify-op$/', $form_state['triggering_element']['#name'])) {
      $path = implode('/', array_slice($form_state['triggering_element']['#array_parents'], 0, -3));
    }
    else {
      $path = implode('/', array_slice($form_state['triggering_element']['#array_parents'], 0, -1));
    }
    $parent_element = TelephoneSmsVerifyElement::getElementByArrayPath($form, $path);

    $settings = $parent_element['#settings'];
    $phone_number = TelephoneSmsVerifyElement::formatValue($parent_element['#value']);
    if ($settings['widget']) {
      $phone_number = $phone_number['value'];
    }

    $commands = array();

// if there are errors on phone number element
    if (empty($phone_number) || $form_state->getError($parent_element['value'])) {
      // Clear all messages errors
      $commands[] = (new RemoveCommand('#messages'))->render();
      // Show error messages before the AJAX wrapper element
      // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
//
//
// @see https://www.drupal.org/node/2195739
// $commands[] = ajax_command_before(NULL, theme('status_messages'));

      // Update the AJAX wrapper element
      $replaceCommand = new ReplaceCommand(NULL, \Drupal::service("renderer")
        ->render($parent_element['value']));
      $commands[] = $replaceCommand->render();
      return array('#type' => 'ajax', '#commands' => $commands);
    }

    $id_prefix = implode('-', preg_replace('$_$', '-', $parent_element['#array_parents']));

//Compute session key
    $form_id = $form_state['values']['form_id'];
    $session_key = md5($form_id . $phone_number);

    $expire = $settings['sms_code_expire'];
    $length = $settings['sms_code_length'];
    $max_request = $settings['sms_code_max_request'];

// Generate SMS verification code if not set or has expired
    if (!isset($_SESSION[$session_key]) || $_SESSION[$session_key]['time'] + 60 * $expire < time()) {
      $_SESSION[$session_key]['code'] = sprintf('%0' . $length . 'd', rand(0, (int) str_repeat('9', $length)));
      $_SESSION[$session_key]['time'] = time();
      $_SESSION[$session_key]['count'] = 0;
    }
    elseif ($_SESSION[$session_key]['count'] < $max_request) {
      $_SESSION[$session_key]['count'] += 1;
    }

// Get SMS verification template from field widget settings
    $template = $settings['sms_template'];

// Set default value is no template is configured
    if (empty($template)) {
      $template = t(DEFAULT_VERIFICATION_MESSAGE_TEMPLATE);
    }

    $template = \Drupal::token()->replace($template, array(
      'phone_sms_verify' => array(
        'verify_code' => $_SESSION[$session_key]['code'],
        'expire_time' => $expire
      )
    ));

// Send SMS verification code using SMS Framework
    sms_send($phone_number, $template);

// Clear all messages errors
    $removeCommand = new RemoveCommand('#messages');
    $commands[] = $removeCommand->render();
// Update the AJAX wrapper element
    $replaceCommand = new ReplaceCommand(NULL, \Drupal::service("renderer")
      ->render($parent_element['value']));
    $commands[] = $replaceCommand->render();
// Trigger javascript messages send countdown
    $invokeCommand = new InvokeCommand(NULL, 'DrupalTelephoneSMSVerifyCountDown', array(
      '#' . $id_prefix . '-send-smscode-btn',
      '#' . $id_prefix . '-send-smscode-count-down'
    ));
    $commands[] = $invokeCommand->render();

    return array('#type' => 'ajax', '#commands' => $commands);
  }

  public static function validateSmsVerifyCode(&$element, FormStateInterface &$form_state, &$form) {
    $path = implode('/', array_slice($element['#array_parents'], 0, -1));

    $parent_element = TelephoneSmsVerifyElement::getElementByArrayPath($form, $path);
    $parent_element_state = TelephoneSmsVerifyElement::getElementByArrayPath($form_state['values'], $path);

    $settings = $parent_element['#settings'];
    $phone_number = TelephoneSmsVerifyElement::formatValue($parent_element_state['value']);
    if ($settings['widget'] && isset($phone_number['value'])) {
      $phone_number = $phone_number['value'];
    }
    $phone_number_default = isset($parent_element['value']['#default_value']) ? $parent_element['value']['#default_value'] : '';

    // Only validates this field if the telephone number is newly added or changed
    if ($phone_number != $phone_number_default) {
      if (empty($element['#value'])) {
        $form_state->setError($element, t('This field is required.'));
      }

      //Compute session key
      $form_id = $form_state['values']['form_id'];
      $session_key = md5($form_id . $phone_number);

      $expire = $settings['sms_code_expire'];

      if (!isset($_SESSION[$session_key]) || $element['#value'] != $_SESSION[$session_key]['code']) {
        $form_state->setError($element, t('Your SMS verification code is not right'));
      }
      elseif ($_SESSION[$session_key]['time'] + 60 * $expire < time()) {
        $form_state->setError($element, t('This SMS code has expired.'));
      }
    }
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