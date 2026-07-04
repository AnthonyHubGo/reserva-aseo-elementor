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
      estado VARCHAR(30) DEFAULT 'pendiente',
      payment_status VARCHAR(30) NOT NULL DEFAULT '',
      payment_reference VARCHAR(80) NOT NULL DEFAULT '',
      wompi_transaction_id VARCHAR(100) NOT NULL DEFAULT '',
      payment_gateway VARCHAR(30) NOT NULL DEFAULT '',
      payment_amount_cop BIGINT UNSIGNED NOT NULL DEFAULT 0,
      payment_response LONGTEXT NULL,
      paid_at DATETIME NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY payment_reference (payment_reference),
      KEY wompi_transaction_id (wompi_transaction_id),
      UNIQUE KEY reserva_unica (persona_id, fecha, jornada)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }
}
