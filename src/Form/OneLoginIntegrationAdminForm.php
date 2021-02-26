<?php

namespace Drupal\latvia_auth\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class OneLoginSAMLAdminForm.
 *
 * @package Drupal\latvia_auth\Form
 */
class OneLoginIntegrationAdminForm extends ConfigFormBase {

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'latvia_auth_admin_form';
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['latvia_auth.settings'];
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('latvia_auth.settings');

    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic settings'),
      '#collapsible' => FALSE,
    ];
    $form['basic']['activate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate authentication via latvija.lv'),
      '#default_value' => $config->get('activate'),
      '#description' => $this->t('Checking this box before configuring the module could lock you out of Drupal.'),
    ];

    $form['basic']['cert'] = [
      '#type' => 'textarea',
      '#title' => $this->t('X.509 Certificate'),
      '#default_value' => $config->get('cert'),
      '#description' => '',
      '#required' => FALSE,
    ];
    $form['basic']['privatekey'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Private Key'),
      '#default_value' => $config->get('privatekey'),
      '#description' => '',
      '#required' => FALSE,
    ];

    $form['authentication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Drupal authentication'),
      '#collapsible' => FALSE,
    ];
    $form['authentication']['disable_default_login'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable authentication with local Drupal accounts'),
      '#default_value' => $config->get('disable_default_login'),
      '#description' => $this->t('Check this box if you want to disable log in with local Drupal accounts (without using simpleSAMLphp).'),
    ];
    $form['authentication']['disable_set_drupal_pwd'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable users to set Drupal passwords'),
      '#default_value' => $config->get('disable_set_drupal_pwd'),
      '#description' => $this->t('Check this box if you want to disable passwords set for local Drupal accounts.'),
    ];

    $form['actions']['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * Submits the form.
   *
   * @param array $form
   *   The form itself.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('latvia_auth.settings');

    $config->set('activate', $form_state->getValue('activate'));
    $config->set('disable_default_login', $form_state->getValue('disable_default_login'));
    $config->set('disable_set_drupal_pwd', $form_state->getValue('disable_set_drupal_pwd'));
    $config->set('cert', $form_state->getValue('cert'));
    $config->set('privatekey', $form_state->getValue('privatekey'));
    $config->set('config_array', $form_state->getValue('config_array'));
    $config->save(TRUE);

    parent::submitForm($form, $form_state);
  }

}
