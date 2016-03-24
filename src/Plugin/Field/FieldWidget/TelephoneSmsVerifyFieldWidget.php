<?php

/**
 * @file
 * Contains \Drupal\telephone_sms_verify\Plugin\Field\FieldWidget\TelephoneSmsVerifyFieldWidget.
 */

namespace Drupal\telephone_sms_verify\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'telephone_sms_verify_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "telephone_sms_verify_field_widget",
 *   label = @Translation("Telephone number with SMS verification"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class TelephoneSmsVerifyFieldWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'widget' => TRUE,
      'placeholder' => t('Phone Number'),
      'captcha' => TRUE,
      'captcha_type' => 'default',
      'sms_template' => t('Your SMS verification code is [phone_sms_verify:verify_code]. This code will be expired in [phone_sms_verify:expire_time] minutes.'),
      'sms_code_expire' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_expire'),
      'sms_code_length' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_length'),
      'sms_code_count_down' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_count_down'),
      'sms_code_max_request' => \Drupal::config('telephone_sms_verify.settings')->get('telephone_sms_verify_sms_code_max_request'),
      'display_sms_code_verify' => TRUE,
      'require_sms_code_verify_on_change' => TRUE,
      'sms_code_verify_not_displayed_forms' => '',
      'sms_code_verify_required_on_phone_changes_forms' => "user_profile_form",
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    $element['placeholder'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Placeholder'),
      '#access' => \Drupal::moduleHandler()->moduleExists('elements') || \Drupal::moduleHandler()->moduleExists('placeholder'),
      '#description' => $this->t('The placeholder is a short hint (a word or short phrase) intended to aid the user with data entry. A hint could be a sample value or a brief description of the expected format.'),
      '#default_value' => $this->getSetting('placeholder'),
    );

    $element['sms_template'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('SMS Template'),
      '#description' => $this->t('The SMS template to send to the user.'),
      '#default_value' => $this->getSetting('sms_template'),
    );

/*
    $element['sms_template']['#description'] .= '<br />' . $this->t('This field supports tokens.')
    $element['sms_template_tokens'] = array(
     '#theme' => 'token_tree',
     '#dialog' => TRUE,
     '#token_types' => array('phone_sms_verify'),
    );
*/

    $element['sms_code_length'] = array(
      '#type' => 'number',
      '#size' => 6,
      '#title' => $this->t('SMS Code Length'),
      '#maxlength' => 6,
      '#description' => $this->t('The length of the SMS verification code'),
      '#default_value' => $this->getSetting('sms_code_length'),
      // '#element_validate' => array('element_validate_integer_positive'),
    );

    $element['sms_code_expire'] = array(
      '#type' => 'number',
      '#size' => 6,
      '#title' => $this->t('SMS Code Expire'),
      '#maxlength' => 6,
      '#description' => $this->t('The SMS verification time expiration time (in minutes).'),
      '#default_value' => $this->getSetting('sms_code_expire'),
      // '#element_validate' => array('element_validate_integer_positive'),
    );

    $element['sms_code_count_down'] = array(
      '#type' => 'number',
      '#size' => 6,
      '#title' => $this->t('SMS Code Count Down'),
      '#maxlength' => 6,
      '#description' => $this->t('The count down time (in seconds) until a new sms code can be sent.'),
      '#default_value' => $this->getSetting('sms_code_count_down'),
      // '#element_validate' => array('element_validate_integer_positive'),
    );

    $element['sms_code_max_request'] = array(
      '#type' => 'number',
      '#size' => 6,
      '#title' => $this->t('SMS Code Max Request'),
      '#maxlength' => 6,
      '#description' => $this->t('The maximum times that the SMS code can be requested in the expiration time.'),
      '#default_value' => $this->getSetting('sms_code_max_request'),
      // '#element_validate' => array('element_validate_integer_positive'),
    );

    $element['sms_code_verify_not_displayed_forms'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('SMS Code Verify Not Required Forms'),
      '#description' => $this->t('Form IDs that do not require SMS code verification. Fill with a drupal form id in each line.'),
      '#default_value' => $this->getSetting('sms_code_verify_not_displayed_forms'),
    );

    $element['sms_code_verify_required_on_phone_changes_forms'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('SMS Code Verify Required Only if Phone Changes Forms'),
      '#description' => $this->t('Form IDs that only requires SMS code verification if the phone number has changed. Fill with a drupal form id in each line.'),
      '#default_value' => $this->getSetting('sms_code_verify_required_on_phone_changes_forms'),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summaryItems = [
      'placeholder',
      'sms_template',
//      'sms_template_tokens',
      'sms_code_length',
      'sms_code_expire',
      'sms_code_count_down',
      'sms_code_max_request',
      'sms_code_verify_not_displayed_forms',
      'sms_code_verify_required_on_phone_changes_forms',
    ];

    $str2words = function($str){
      $words = str_replace('_', ' ', $str);
      $words[0] =chr(ord($words[0]) - 32);
      return $words;
    };

    foreach ($summaryItems as $item) {
      if (!empty($val = $this->getSetting($item))) {
        $summary[] = $this->t( $str2words($item).': @val', ['@val'=> $val] );
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $form_id = $form_state->getBuildInfo()['form_id'];

    $settings = $this->settings;

    // Do not show sms code verify element on field settings page
    if (empty($element['#entity'])) {
      $settings['display_sms_code_verify'] = FALSE;
    }

    // Allow to hide sms code verify element in specific forms
    if (in_array($form_id, _telephone_sms_verify_explode_multi_lines($settings['sms_code_verify_not_displayed_forms']))) {
      $settings['display_sms_code_verify'] = FALSE;
    }

    // Allow to mark sms code verify not required on specific forms
    if (in_array($form_id, _telephone_sms_verify_explode_multi_lines($settings['sms_code_verify_required_on_phone_changes_forms']))) {
      $settings['require_sms_code_verify_on_change'] = FALSE;
    }

    $element += array(
      '#type' => 'telephone_with_sms_verify',
      '#settings' => $settings,
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : '',
    );

    return $element;
  }

}
