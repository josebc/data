<?php

namespace Drupal\data;

use Drupal\data\TableManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactory;


/**
 * Class TableManager.
 *
 * @package Drupal\data
 */
class TableManager implements TableManagerInterface {

  /**
   * The database connection object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection object.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ConfigFactory $config_factory) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function adopt($table_name) {
    if ($this->defined($table_name) || !$this->moduleHandler->moduleExists('schema')) {
      return FALSE;
    }
    $storage = $this->entityTypeManager->getStorage('data_table_config');
    $config = $this->configFactory->get('schema.settings');
    $schema = schema_dbobject()->inspect($config->get('schema_database_connection'), $table_name);
    $fields = [];
    foreach ($schema[$table_name]['fields'] as $key => $value) {
      $fields[] = [
        'name' => $key,
        'label' => $key,
        'type' => $value['type'],
        'size' => $value['size'],
        'length' => isset($value['length']) ? $value['length'] : FALSE,
        'unsigned' => isset($value['unsigned']) ? $value['unsigned'] : FALSE,
        'index' => isset($value['index']) ? $value['index'] : FALSE,
        'primary' => isset($value['primary']) ? $value['primary'] : FALSE,
      ];
    }
    $table = $storage->create([
      'id' => $table_name,
      'title' => data_natural_name($table_name),
      'table_schema' => array_filter($fields),
      'meta' => '',
    ]);
    //TODO: find a better way than enforceIsNew to avoid data entity class attempting creating the db table.
    $table->enforceIsNew(FALSE);
    $table->save();
  }

  /**
   * {@inheritdoc}
   */
  public function defined($table_name) {
    if ($this->entityTypeManager->getStorage('data_table_config')->load($table_name)) {
      return TRUE;
    }
  }

}
