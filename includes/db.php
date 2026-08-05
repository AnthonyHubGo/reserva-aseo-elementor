<?php

if (!defined('ABSPATH')) exit;

class RAE_DB {

  public static function create_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'reservas_aseo';
    $attempts_table = $wpdb->prefix . 'reservas_aseo_pagos';
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
      payment_retry_until DATETIME NULL,
      paid_at DATETIME NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY payment_reference (payment_reference),
      KEY wompi_transaction_id (wompi_transaction_id),
      KEY reserva_disponibilidad (persona_id, fecha, jornada, estado)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $attempts_sql = "CREATE TABLE $attempts_table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      reserva_id BIGINT UNSIGNED NOT NULL,
      payment_reference VARCHAR(80) NOT NULL,
      transaction_id VARCHAR(100) NOT NULL,
      status VARCHAR(30) NOT NULL DEFAULT '',
      payment_method VARCHAR(50) NOT NULL DEFAULT '',
      amount_in_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
      currency VARCHAR(10) NOT NULL DEFAULT '',
      raw_response LONGTEXT NULL,
      received_at DATETIME NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY transaction_id (transaction_id),
      KEY reserva_id (reserva_id),
      KEY payment_reference (payment_reference)
    ) $charset;";

    dbDelta($attempts_sql);

    // Versiones anteriores impedían conservar reservas canceladas o rechazadas
    // y volver a utilizar la misma jornada. La exclusión concurrente se maneja
    // ahora con un bloqueo por persona y fecha al crear la reserva.
    $legacy_unique_index = $wpdb->get_var(
      "SHOW INDEX FROM $table WHERE Key_name = 'reserva_unica'"
    );

    if ($legacy_unique_index !== null) {
      $wpdb->query("ALTER TABLE $table DROP INDEX reserva_unica");
    }
  }
}

function rae_reserva_lock_name($persona_id, $fecha) {
  return sprintf(
    'rae_%d_%d_%s',
    get_current_blog_id(),
    absint($persona_id),
    substr(hash('sha256', (string) $fecha), 0, 20)
  );
}

function rae_adquirir_bloqueo_reserva($persona_id, $fecha, $timeout = 5) {
  global $wpdb;

  $lock_name = rae_reserva_lock_name($persona_id, $fecha);
  $acquired = $wpdb->get_var(
    $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, absint($timeout))
  );

  return (string) $acquired === '1';
}

function rae_liberar_bloqueo_reserva($persona_id, $fecha) {
  global $wpdb;

  $wpdb->get_var(
    $wpdb->prepare('SELECT RELEASE_LOCK(%s)', rae_reserva_lock_name($persona_id, $fecha))
  );
}

function rae_reserva_tiene_conflicto($persona_id, $fecha, $jornada) {
  global $wpdb;

  if (function_exists('rae_wompi_expire_pending_reservations')) {
    rae_wompi_expire_pending_reservations();
  }

  $table = $wpdb->prefix . 'reservas_aseo';
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];

  if (!in_array($jornada, $jornadas_permitidas, true)) {
    return true;
  }

  $jornadas_conflicto = $jornada === 'completa'
    ? $jornadas_permitidas
    : [$jornada, 'completa'];
  $estados_bloqueantes = ['pendiente', 'pendiente_pago', 'pagado', 'confirmada', 'pago_revision'];
  $placeholders_jornadas = implode(', ', array_fill(0, count($jornadas_conflicto), '%s'));
  $placeholders_estados = implode(', ', array_fill(0, count($estados_bloqueantes), '%s'));
  $params = array_merge([$persona_id, $fecha], $jornadas_conflicto, $estados_bloqueantes);
  $count = $wpdb->get_var(
    $wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE persona_id = %d AND fecha = %s AND jornada IN ($placeholders_jornadas) AND estado IN ($placeholders_estados)",
      $params
    )
  );

  return absint($count) > 0;
}
