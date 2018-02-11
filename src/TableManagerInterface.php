<?php

namespace Drupal\data;

/**
 * Class TableManagerInterface.
 *
 * @package Drupal\data
 */
interface TableManagerInterface {

  /**
   * @param string $table_name
   *   Table to adopt.
   *
   * @return bool
   *   Result of save operation.
   */
  public function adopt($table_name);

  /**
   * Determine whether a table is defined.
   *
   * @param string $table_name
   *   Table to check.
   * @return
   *   TRUE if the table is defined, FALSE otherwise.
   *   Note: If a table is defined it does not mean that it actually exists in the
   *   database.
   */
  public function defined($table_name);

}
