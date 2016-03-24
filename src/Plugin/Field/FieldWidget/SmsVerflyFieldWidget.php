<?php

/**
 * @file
 * Contains \Drupal\sms_verfly\Plugin\Field\FieldWidget\SmsVerflyFieldWidget.
 */

namespace Drupal\sms_verfly\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'sms_verfly_field_widget' widget.
 *
 * @FieldWidget(
 *   id = "sms_verfly_field_widget",
 *   label = @Translation("Sms verfly field widget"),
 *   field_types = {
 *     "telephone"
 *   }
 * )
 */
class SmsVerflyFieldWidget extends WidgetBase {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'placeholder' => 'Phone Number',
      'sms_template' => '',
      'sms_code_length' => 6,
      'sms_code_expire' => 30,
      'sms_code_count_down' => 30,
      'sms_code_max_request' => 3,
      'sms_code_verify_not_displayed_forms' => '',
      'sms_code_verify_required_on_phone_changes_forms' => '',
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
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => $this->t('The placeholder is a short hint (a word or short phrase) intended to aid the user with data entry. A hint could be a sample value or a brief description of the expected format.'),
    );

    $element['sms_template'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('SMS Template'),
      '#description' => $this->t('The SMS template to send to the user.') . '<br />' . $this->t('This field supports tokens.'),
      '#default_value' => $this->getSetting('sms_template'),
    );

    /*$element['sms_template_tokens'] = array(
      '#theme' => 'token_tree',
      '#dialog' => TRUE,
      '#token_types' => array('phone_sms_verify'),
    );*/

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
    $element = [];
    $item = $items[$delta];

    $element['value'] = $element + array(
      '#type' => 'textfield',
      '#title' => $this->t($item->getFieldDefinition()->getLabel()),
      '#default_value' => isset($item->value) ? $item->value : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
    );

    $element['smscode'] = [
      '#type' => 'textfield',
      '#size' => 10,
      '#title' => t('SMS Code'),
      // '#prefix' => '<div id="' . $id_prefix . '-sms-verification-code-wrapper" class="sms-verification-code">',
      // '#element_validate' => array('telephone_sms_verify_smscode_validate'),
      // '#required' => $settings['require_sms_code_verify_on_change'],
    ];

    $element['send_smscode'] = [
      '#type' => 'button',
      '#value' => t('Send SMS Code'),
    ];




    return $element;
  }

}
