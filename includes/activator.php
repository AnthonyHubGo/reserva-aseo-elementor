<?php

if (!defined('ABSPATH')) exit;

class RAE_Activator {

  public static function activate() {
    RAE_DB::create_table();
  }
}