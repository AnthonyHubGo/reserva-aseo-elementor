<?php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_rae_guardar_reserva', 'rae_guardar_reserva');
add_action('wp_ajax_nopriv_rae_guardar_reserva', 'rae_guardar_reserva');

function rae_guardar_reserva() {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';

  $nombre = sanitize_text_field($_POST['nombre'] ?? '');
  $email = sanitize_email($_POST['email'] ?? '');
  $persona_id = intval($_POST['persona_id'] ?? 0);
  $fecha = sanitize_text_field($_POST['fecha'] ?? '');
  $jornada = sanitize_text_field($_POST['jornada'] ?? '');

  if (!$nombre || !$email || !$persona_id || !$fecha || !$jornada) {
    wp_send_json_error('Todos los campos son obligatorios.');
  }

  $existe = $wpdb->get_var(
    $wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE persona_id = %d AND fecha = %s AND jornada = %s",
      $persona_id,
      $fecha,
      $jornada
    )
  );

  if ($existe > 0) {
    wp_send_json_error('Esta persona ya está reservada para esa fecha y jornada.');
  }

  $insertado = $wpdb->insert($table, [
    'cliente_nombre' => $nombre,
    'cliente_email' => $email,
    'persona_id' => $persona_id,
    'fecha' => $fecha,
    'jornada' => $jornada,
    'estado' => 'pendiente',
  ]);

  if (!$insertado) {
    wp_send_json_error('No se pudo guardar la reserva.');
  }

  wp_send_json_success('Reserva creada correctamente.');
}