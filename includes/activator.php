<?php

if (!defined('ABSPATH')) exit;

class RAE_Activator {

  public static function activate() {
    RAE_DB::create_table();

    if (function_exists('rae_registrar_roles_y_capabilities')) {
      rae_registrar_roles_y_capabilities();
    }

    if (function_exists('rae_wompi_schedule_expiration')) {
      rae_wompi_schedule_expiration();
    }

    update_option('rae_db_version', RAE_DB_VERSION);
  }
}
