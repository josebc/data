<?php

namespace Drupal\data\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\data\TableManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TableAdoptForm.
 *
 * @package Drupal\data\Form
 */
class TableAdoptForm extends FormBase {

  /**
   * tableManager service.
   *
   * @var \Drupal\data\TableManager
   */
  protected $TableManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new TableAdoptForm.
   *
   * @param \Drupal\data\TableManagerInterface $table_manager
   *   The book manager.
   */
  public function __construct(TableManagerInterface $table_manager, ModuleHandlerInterface $module_handler) {
    $this->tableManager = $table_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('data.table_manager'),
      $container->get('module_handler')
    );
  }

  /**
   *
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'table_adopt_form';
  }

  /**
   *
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->moduleHandler->moduleExists('schema')) {
      $form['schema_not_enabled'] = [
        '#type' => 'markup',
        '#markup' => $this->t('You need to enable the Schema module to adopt tables.')
      ];
      return $form;
    }
    $schema = schema_get_schema(TRUE);
    $info = schema_compare_schemas($schema);
    // We have now subtracted all the tables created via hook_schema.
    $orphaned_tables = $info['extra'];
    if (count($orphaned_tables) < 1) {
      $form['no_orphaned_tables'] = [
        '#type' => 'markup',
        '#markup' => $this->t('There are no orphaned tables in your database.')
      ];
    }
    else {
      $form['orphaned_tables'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Orphaned Tables'),
        '#options' => array_combine($orphaned_tables, $orphaned_tables)
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Adopt')
      ];
    }
    return $form;
  }

  /**
   *
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $tables = array_filter($form_state->getValue('orphaned_tables'));
    foreach ($tables as $table_name) {
      $status = $this->tableManager->adopt($table_name);
      if ($status) {
        drupal_set_message($this->t("Table @table has been adopted.", [
          '@table' => $table_name
        ]));
      }
    }
    $form_state->setRedirect('entity.data_table_config.collection');
  }

}
