<?php
namespace Drupal\csom_datalayer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class CSOMDataLayerSettingsForm
 *
 * @package Drupal\csom_datalayer\Form
 */
class CSOMDataLayerSettingsForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'csom_datalayer_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['csom_datalayer.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('csom_datalayer.settings');

        // Build form elements.
        $form['settings'] = [
        '#type' => 'vertical_tabs',
        '#attributes' => ['class' => ['csom-datalayer']],
        '#attached' => [
            'library' => ['csom_datalayer/drupal.settings_form'],
        ],
        ];
  
        // General tab.
        $form['general'] = [
        '#type' => 'details',
        '#title' => $this->t('General'),
        '#group' => 'settings',
        ];

        $form['general']['status_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status Web Site URL'),
        '#description' => $this->t('The Status Web Site URL for this datalayer Program Status data. This is build for a Slate Web Service.'),
        '#default_value' => $config->get('status_url'),
        '#attributes' => ['placeholder' => ['https']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];

        $form['general']['status_user'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status Web Site User'),
        '#description' => $this->t('The Status Web Site user account for this datalayer Program Status data. This is build for a Slate Web Service.'),
        '#default_value' => $config->get('status_user'),
        '#attributes' => ['placeholder' => ['username']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];

        $form['general']['status_pass'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Status Web Site Password'),
        '#description' => $this->t('The Status Web Site password for this datalayer Program Status data. This is build for a Slate Web Service.'),
        '#default_value' => $config->get('status_pass'),
        '#attributes' => ['placeholder' => ['password']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];

        $form['general']['analytics_url'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Analytics Web Site URL'),
        '#description' => $this->t('The Analytics Visitor Data Web Site URL for web site visitors. This is build for a Matomo - Piwik Web Service.'),
        '#default_value' => $config->get('analytics_url'),
        '#attributes' => ['placeholder' => ['https']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];

        $form['general']['analytics_user'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Analytics Web Site User'),
        '#description' => $this->t('The Analytics Visitor Data Web Site user account data. This is build for a Matomo - Piwik Web Service.'),
        '#default_value' => $config->get('analytics_user'),
        '#attributes' => ['placeholder' => ['username']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];


        $form['general']['analytics_pass'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Analytics Web Site Password'),
        '#description' => $this->t('The Analytics Visitor Data Web Site Password data. This is build for a Matomo - Piwik Web Service.'),
        '#default_value' => $config->get('analytics_pass'),
        '#attributes' => ['placeholder' => ['password']],
        '#size' => 255,
        '#maxlength' => 400,
        '#required' => true,
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // Trim the text values.
        $status_url = trim($form_state->getValue('status_url'));
        $form_state->setValue('status_url', $status_url);

        $status_user = trim($form_state->getValue('status_user'));
        $form_state->setValue('status_user', $status_user);

        $status_pass = trim($form_state->getValue('status_pass'));
        $form_state->setValue('status_pass', $status_pass);

        $analytics_url = trim($form_state->getValue('analytics_url'));
        $form_state->setValue('analytics_url', $analytics_url);

        $analytics_user = trim($form_state->getValue('analytics_user'));
        $form_state->setValue('analytics_user', $analytics_user);

        $analytics_pass = trim($form_state->getValue('analytics_pass'));
        $form_state->setValue('analytics_pass', $analytics_pass);


        // figure out validation rules like something below
        // if (!preg_match('/^http{4,}$/', $status_url)) {
        // @todo Is there a more specific regular expression that applies?
        // @todo Is there a way to "test the connection" to determine a valid ID for
        // a container? It may be valid but not the correct one for the website.
        // $form_state->setError($form['general']['status_url'], $this->t('A valid Status URL formatted like http something.'));
        // }


        parent::validateForm($form, $form_state);
    }


    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->config('csom_datalayer.settings')
            ->set('status_url', $form_state->getValue('status_url'))
            ->set('status_user', $form_state->getValue('status_user'))
            ->set('status_pass', $form_state->getValue('status_pass'))
            ->set('analytics_url', $form_state->getValue('analytics_url'))
            ->set('analytics_user', $form_state->getValue('analytics_user'))
            ->set('analytics_pass', $form_state->getValue('analytics_pass'))
            ->save();

        parent::submitForm($form, $form_state);
    }

}
