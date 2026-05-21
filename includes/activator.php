<?php

if (!defined('ABSPATH')) exit;

class RAE_Activator {

  public static function activate() {
    RAE_DB::create_table();
    update_option('rae_db_version', RAE_DB_VERSION);
  }
}
