<?php

namespace Drupal\data\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\data\TableManagerInterface;
use Drupal\views\ViewsData;
use Drupal\views\ViewsDataHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TableRelationForm.
 *
 * @package Drupal\data\Form
 */
class TableRelationForm extends FormBase {

  /**
   * TableManager service.
   *
   * @var \Drupal\data\TableManager
   */
  protected $tableManager;

  /**
   * The views data.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * The views data helper.
   *
   * @var \Drupal\views\ViewsDataHelper
   */
  protected $viewsDataHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new TableRelationForm.
   *
   * @param \Drupal\data\TableManagerInterface $table_manager
   *   The table manager.
   * @param \Drupal\views\ViewsData $views_data
   *   The views data manager.
   * @param \Drupal\views\ViewsDataHelper $views_data_helper
   *   The views data helper.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The views data helper.
   *
   */
  public function __construct(TableManagerInterface $table_manager, ViewsData $views_data, ViewsDataHelper $views_data_helper, EntityTypeManagerInterface $entity_type_manager) {
    $this->tableManager = $table_manager;
    $this->viewsData = $views_data;
    $this->viewsDataHelper = $views_data_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data.table_manager'),
      $container->get('views.views_data'),
      $container->get('views.views_data_helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'data_table_relation';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $views_tables = $this->getViewsTables();
    sort($views_tables);
    $table_name = $request->get('table_name');
    $field_name = $request->get('field_name');
    $table = $this->entityTypeManager->getStorage('data_table_config')->load($table_name);
    $field_relation = !empty($table->meta['relation'][$field_name]) ? $table->meta['relation'][$field_name] : [];

    $table_default = NULL;
    if ($form_state->getValue('base_table')) {
      $table_default = $form_state->getValue('base_table');
    }
    elseif (!empty($field_relation['base_table'])) {
      $table_default = $field_relation['base_table'];
    }
    $form['base_table'] = [
      '#type' => 'select',
      '#title' => $this->t('Base table'),
      '#options' => array_combine($views_tables, $views_tables),
      '#default_value' => $table_default,
      '#empty_label' => $this->t('Select'),
      '#empty_value' => '',
      '#ajax' => [
        'callback' => '::getViewsTableFields',
        'wrapper' => 'data-relation-field-wrapper',
      ],
    ];
    $form['base_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Base field'),
      '#options' => !empty($table_default) ? $this->getViewsTableFieldsOptions($table_default) : [],
      '#default_value' => isset($field_relation['base_field']) ? $field_relation['base_field'] : NULL,
      '#empty_label' => $this->t('Select'),
      '#empty_value' => '',
      '#prefix' => '<div id="data-relation-field-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#button_type' => 'danger',
      '#submit' => ['::deleteRelation'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $table_name = $request->get('table_name');
    $field_name = $request->get('field_name');
    $table = $this->entityTypeManager->getStorage('data_table_config')->load($table_name);

    $table->meta['relation'][$field_name] = [
      'base_table' => $form_state->getValue('base_table'),
      'base_field' => $form_state->getValue('base_field'),
    ];
    if ($table->save()) {
      drupal_flush_all_caches();
      $form_state->setRedirect('entity.data_table_config.edit_form', ['data_table_config' => $table_name]);
    }
  }

  /**
   * Form submission handler for delete relation.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function deleteRelation(array &$form, FormStateInterface $form_state) {
    $request = \Drupal::request();
    $table_name = $request->get('table_name');
    $field_name = $request->get('field_name');
    $table = $this->entityTypeManager->getStorage('data_table_config')->load($table_name);
    unset($table->meta['relation'][$field_name]);
    if ($table->save()) {
      drupal_flush_all_caches();
      $form_state->setRedirect('entity.data_table_config.edit_form', ['data_table_config' => $table_name]);
    }
  }

  /**
   * Helper function to get view tables.
   *
   * @return array
   */
  protected function getViewsTables() {
    return array_keys($this->viewsData->getAll());
  }

  /**
   * Helper function to get views field for a table.
   *
   * @param string $table_name
   *
   * @return array
   */
  protected function getViewsTableFieldsOptions($table_name) {
    $options = [];
    foreach ($this->viewsDataHelper->fetchFields($table_name, 'field') as $key => $field) {
      $field_name = end((explode('.', $key)));
      $options[$field_name] = $field_name;
    }
    return $options;
  }

  /**
   * Callback for ajax base field.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function getViewsTableFields(array &$form, FormStateInterface $form_state) {
    return $form['base_field'];
  }

}
