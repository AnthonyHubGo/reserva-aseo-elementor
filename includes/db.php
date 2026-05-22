<?php

if (!defined('ABSPATH')) exit;

class RAE_DB {

  public static function create_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'reservas_aseo';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      cliente_nombre VARCHAR(100) NOT NULL,
      cliente_email VARCHAR(100) NOT NULL,
      cliente_telefono VARCHAR(30) NOT NULL DEFAULT '',
      persona_id BIGINT UNSIGNED NOT NULL,
      fecha DATE NOT NULL,
      jornada VARCHAR(20) NOT NULL,
      cliente_ciudad VARCHAR(100) NOT NULL DEFAULT '',
      cliente_barrio VARCHAR(100) NOT NULL DEFAULT '',
      cliente_direccion VARCHAR(255) NOT NULL DEFAULT '',
      cliente_casa VARCHAR(100) NOT NULL DEFAULT '',
      estado VARCHAR(20) DEFAULT 'pendiente',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY reserva_unica (persona_id, fecha, jornada)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }
}
