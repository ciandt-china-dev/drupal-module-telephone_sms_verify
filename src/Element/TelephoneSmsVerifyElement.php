<?php

/**
 * @file
 * Contains \Drupal\telephone_sms_verify\Element\TelephoneSmsVerifyElement.
 */

namespace Drupal\telephone_sms_verify\Element;

use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;

use Drupal\Core\Render\Element;
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
   * Implements hook_element_info().
   */
  function telephone_sms_verify_element_info() {
    $type['telephone_with_sms_verify'] = array(
      '#tree' => TRUE,
      '#input' => TRUE,
      '#required' => FALSE,
      '#process' => array('_telephone_sms_verify_element_expand', 'form_process_container'),
      '#description' => t('Telephone number'),
      '#default_value' => '',
      '#theme_wrappers' => array('container'),
      '#default_settings' => array(
        'widget' => FALSE,
        'placeholder' => t('Phone Number'),
        'captcha' => TRUE,
        'captcha_type' => 'default',
        'sms_template' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_template'),
        'sms_code_expire' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_expire'),
        'sms_code_length' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_length'),
        'sms_code_count_down' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_count_down'),
        'sms_code_max_request' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_max_request'),
        'display_sms_code_verify' => TRUE,
        'require_sms_code_verify_on_change' => TRUE,
      ),
      '#value_callback' => '_telephone_sms_verify_element_value',
      '#element_validate' => array('telephone_sms_verify_validate'),
    );
    return $type;
  }

  function _telephone_sms_verify_element_expand($element, &$form_state, $complete_form) {
    if (!isset($element['#settings'])) {
      $element['#settings'] = array();
    }
    $element['#settings'] += $element['#default_settings'];
    $settings = $element['#settings'];

    $settings['display_sms_code_verify'] &= !\Drupal::currentUser()->hasPermission('bypass telephone sms verify');

    // Give user a chance finally alter the sms code verify element behavior
    $context = array(
      'element' => $element,
      'form' => $complete_form,
      'form_state' => $form_state,
    );
    \Drupal::moduleHandler()->alter('sms_code_verify_element_settings', $settings, $context);

    $id_prefix = implode('-', preg_replace('$_$', '-', $element['#array_parents']));

    $element['value'] = array(
      '#type' => \Drupal::moduleHandler()->moduleExists('elements') ? 'telfield' : 'textfield',
      '#title' => $element['#title'],
      '#required' => $element['#required'],
      '#maxlength' => isset($element['#maxlength']) ? $element['#maxlength'] : 11,
      '#description' => $element['#description'],
      '#default_value' => $element['#default_value'],
      '#weight' => 0,
      '#prefix' => '<div id="' . $id_prefix . '-value-wrapper">',
      '#suffix' => '</div>',
      '#placeholder' => $settings['placeholder'],
      '#element_validate' => array('telephone_sms_verify_value_validate'),
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
      if ($settings['captcha'] && \Drupal::moduleHandler()->moduleExists('captcha') && $_GET['q'] == 'system/ajax' &&
        (
          // We need captcha to be presented in the ajax form rebuild stage, but not when the captcha verify button is clicked, because next step is to submit the entire form, and we do not want to validate the capture element in this step.
          $form_state['rebuild'] == TRUE && $form_state['triggering_element']['#name'] != $id_prefix . '-captcha-verify-op' ||
          // We need captcha to be presented in the normal form build stage so that the captcha can be validated only when not in the form submit process
          $form_state['rebuild'] == FALSE && (!isset($form_state['triggering_element']) || $form_state['triggering_element']['#name'] != 'op')
        )
      ) {
        $element['smscode_captcha']['#attached'] = array(
          'js' => array(
            drupal_get_path('module', 'telephone_sms_verify') . '/telephone_sms_verify.js',
          ),
          'css' => array(
            drupal_get_path('module', 'telephone_sms_verify') . '/telephone_sms_verify.css',
          ),
        );
        $element['smscode_captcha']['overlay'] = array(
          '#type' => 'container',
          '#attributes' => array(
            'class' => array(
              'smscode-captcha-overlay',
              $id_prefix . '-smscode-captcha-overlay',
            ),
          ),
        );

        $element['smscode_captcha']['container'] = array(
          '#type' => 'container',
          '#attributes' => array(
            'class' => array(
              'smscode-captcha-container',
              $id_prefix . '-smscode-captcha-container',
            ),
          ),
        );

        $element['smscode_captcha']['container']['close'] = array(
          '#markup' => '<a class="boxclose" data-id="' . $id_prefix . '"></a>',
        );

        $element['smscode_captcha']['container']['captcha'] = array(
          '#type' => 'captcha',
          '#captcha_type' => $settings['captcha_type'],
        );

        if (\Drupal::moduleHandler()->moduleExists('image_captcha_refresh')) {
          $element['smscode_captcha']['container']['captcha']['#after_build'][] = '_telephone_sms_verify_image_captcha_refresh_after_build_process';
          $element['smscode_captcha']['container']['captcha']['#attached']['js'][] = drupal_get_path('module', 'image_captcha_refresh') . '/image_captcha_refresh.js';
        }

        $element['smscode_captcha']['container']['verify'] = array(
          '#name' => $id_prefix . '-captcha-verify-op',
          '#type' => 'button',
          '#ajax' => array(
            'wrapper' => $id_prefix . '-value-wrapper',
            'callback' => 'telephone_sms_verify_ajax_callback',
          ),
          '#value' => t('Verify'),
          '#limit_validation_errors' => array(
            array('captcha_response'),
            array_merge($element['#array_parents'], array('value')),
            array_merge($element['#array_parents'], array(
              'smscode_captcha',
              'container',
              'captcha'
            )),
          ),
        );
      }

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

  function _telephone_sms_verify_element_value($element, $input, $form_state) {
    if ($element['#settings']['widget']) {
      return $input;
    }
    else {
      return $input['value'];
    }
  }

  function telephone_sms_verify_ajax_callback($form, $form_state) {
    if (preg_match('/captcha-verify-op$/', $form_state['triggering_element']['#name'])) {
      $path = implode('/', array_slice($form_state['triggering_element']['#array_parents'], 0, -3));
    }
    else {
      $path = implode('/', array_slice($form_state['triggering_element']['#array_parents'], 0, -1));
    }
    $parent_element = _telephone_sms_verify_get_element_by_array_path($form, $path);

    $settings = $parent_element['#settings'];
    $phone_number = _telephone_sms_verify_format_default_value($parent_element['#value']);
    if ($settings['widget']) {
      $phone_number = $phone_number['value'];
    }

    $commands = array();

    // if there are errors on phone number element
    if (empty($phone_number) || form_get_error($parent_element['value'])) {
      // Clear all messages errors
      $removeCommand = new RemoveCommand('#messages');
      $commands[] = $removeCommand->render();

      // Show error messages before the AJAX wrapper element
      // @FIXME
      // theme() has been renamed to _theme() and should NEVER be called directly.
      // Calling _theme() directly can alter the expected output and potentially
      // introduce security issues (see https://www.drupal.org/node/2195739). You
      // should use renderable arrays instead.
      //
      //
      // @see https://www.drupal.org/node/2195739
      $beforeCommand = new BeforeCommand(NULL, \Drupal::service("renderer")->render(array('#type' => 'status_messages')));
      $commands[] = $beforeCommand->render();

      // Update the AJAX wrapper element
      $replaceCommand = new ReplaceCommand(NULL, \Drupal::service("renderer")->render($parent_element['value']));
      $commands[] = $replaceCommand->render();

      return array('#type' => 'ajax', '#commands' => $commands);
    }

    $id_prefix = implode('-', preg_replace('$_$', '-', $parent_element['#array_parents']));

    if ($settings['captcha'] && \Drupal::moduleHandler()->moduleExists('captcha')) {
      if ($form_state['triggering_element']['#name'] == $id_prefix . '-send-smscode-btn-op' || isset($parent_element['smscode_captcha']['container']) && form_get_error($parent_element['smscode_captcha']['container']['captcha']['captcha_widgets']['captcha_response'])) {
        // Clear all messages errors
        $removeCommand = new RemoveCommand('#messages');
        $commands[] = $removeCommand->render();

        $replaceCommand = new ReplaceCommand(NULL, \Drupal::service("renderer")->render($parent_element['value']));
        $commands[] = $replaceCommand->render();

        $replaceCommand = new ReplaceCommand('#' . $id_prefix . '-captcha-wrapper', \Drupal::service("renderer")->render($parent_element['smscode_captcha']));
        $commands[] = $replaceCommand->render();

        // Show error messages before the AJAX wrapper element
        // @FIXME
        // theme() has been renamed to _theme() and should NEVER be called directly.
        // Calling _theme() directly can alter the expected output and potentially
        // introduce security issues (see https://www.drupal.org/node/2195739). You
        // should use renderable arrays instead.
        //
        //
        // @see https://www.drupal.org/node/2195739
        $afterCommand = new AfterCommand('.' . $id_prefix . '-smscode-captcha-container .form-item-captcha-response', \Drupal::service("renderer")->render(array('#type' => 'status_messages')));
        $commands[] = $afterCommand->render();

        return array('#type' => 'ajax', '#commands' => $commands);
      }
      else {
        $replaceCommand = new ReplaceCommand('#' . $id_prefix . '-captcha-wrapper', '<div id="' . $id_prefix . '-captcha-wrapper"></div>');
        $commands[] = $replaceCommand->render();
      }
    }

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
    $replaceCommand = new ReplaceCommand(NULL, \Drupal::service("renderer")->render($parent_element['value']));
    $commands[] = $replaceCommand->render();

    // Trigger javascript messages send countdown
    $invokeCommand = new InvokeCommand(NULL, 'DrupalTelephoneSMSVerifyCountDown', array(
      '#' . $id_prefix . '-send-smscode-btn',
      '#' . $id_prefix . '-send-smscode-count-down'
    ));
    $commands[] = $invokeCommand->render();

    return array('#type' => 'ajax', '#commands' => $commands);
  }

  function telephone_sms_verify_validate($element, &$form_state, $form) {
    $form_state->setValueForElement($element, $element['#value']);
  }

  function telephone_sms_verify_value_validate($element, &$form_state, $form) {
    $path = implode('/', array_slice($element['#array_parents'], 0, -1));
    $parent_element = _telephone_sms_verify_get_element_by_array_path($form, $path);
    $parent_element_state = _telephone_sms_verify_get_element_by_array_path($form_state['values'], $path);

    $settings = $parent_element['#settings'];
    $phone_number = _telephone_sms_verify_format_default_value($parent_element_state['value']);
    if ($settings['widget'] && isset($phone_number['value'])) {
      $phone_number = $phone_number['value'];
    }
    $phone_number_default = _telephone_sms_verify_format_default_value($parent_element['#default_value']);

    $expire = $settings['sms_code_expire'];

    //Compute session key
    $form_id = $form_state['values']['form_id'];
    $session_key = md5($form_id . $phone_number);

    $max_request = $settings['sms_code_max_request'];

    // Do not send sms verification code on account editing page and the phone number is not changed
    if ($settings['display_sms_code_verify'] && $settings['require_sms_code_verify_on_change'] && $phone_number == $phone_number_default) {
      form_error($element, t('Your phone number has not changed, no SMS verification code is sent.'));
    }

    if ($settings['display_sms_code_verify'] && isset($_SESSION[$session_key]) && $_SESSION[$session_key]['time'] + 60 * $expire >= time() && $_SESSION[$session_key]['count'] >= $max_request) {
      $minutes_left = ceil((($_SESSION[$session_key]['time'] + 60 * $expire) - time()) / 60);
      form_error($element, t('You have reached the maximum request limitation, please try again after @expire minutes', array('@expire' => $minutes_left)));
    }
  }

  function telephone_sms_verify_smscode_validate($element, &$form_state, $form) {
    $path = implode('/', array_slice($element['#array_parents'], 0, -1));

    $parent_element = _telephone_sms_verify_get_element_by_array_path($form, $path);
    $parent_element_state = _telephone_sms_verify_get_element_by_array_path($form_state['values'], $path);

    $settings = $parent_element['#settings'];
    $phone_number = _telephone_sms_verify_format_default_value($parent_element_state['value']);
    if ($settings['widget'] && isset($phone_number['value'])) {
      $phone_number = $phone_number['value'];
    }
    $phone_number_default = isset($parent_element['value']['#default_value']) ? $parent_element['value']['#default_value'] : '';

    // Only validates this field if the telephone number is newly added or changed
    if ($phone_number != $phone_number_default) {
      if (empty($element['#value'])) {
        form_error($element, t('This field is required.'));
      }

      //Compute session key
      $form_id = $form_state['values']['form_id'];
      $session_key = md5($form_id . $phone_number);

      $expire = $settings['sms_code_expire'];

      if (!isset($_SESSION[$session_key]) || $element['#value'] != $_SESSION[$session_key]['code']) {
        form_error($element, t('Your SMS verification code is not right'));
      }
      elseif ($_SESSION[$session_key]['time'] + 60 * $expire < time()) {
        form_error($element, t('This SMS code has expired.'));
      }
    }
  }

  function _telephone_sms_verify_format_default_value($current, $default = '') {
    return isset($current) ? $current : $default;
  }

  function _telephone_sms_verify_get_element_by_array_path($arr, $path) {
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
}