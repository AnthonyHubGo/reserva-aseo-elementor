<?php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_rae_guardar_reserva', 'rae_guardar_reserva');
add_action('wp_ajax_nopriv_rae_guardar_reserva', 'rae_guardar_reserva');

function rae_guardar_reserva() {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';

  $nombre = sanitize_text_field(wp_unslash($_POST['nombre'] ?? ''));
  $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
  $persona_id = absint(wp_unslash($_POST['persona_id'] ?? 0));
  $fecha = sanitize_text_field(wp_unslash($_POST['fecha'] ?? ''));
  $jornada = sanitize_text_field(wp_unslash($_POST['jornada'] ?? ''));
  $ciudad = sanitize_text_field(wp_unslash($_POST['ciudad'] ?? ''));
  $barrio = sanitize_text_field(wp_unslash($_POST['barrio'] ?? ''));
  $direccion = sanitize_text_field(wp_unslash($_POST['direccion'] ?? ''));
  $casa = sanitize_text_field(wp_unslash($_POST['casa'] ?? ''));
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];

  if (!$nombre || !$email || !$persona_id || !$fecha || !$jornada || !$ciudad || !$barrio || !$direccion || !$casa) {
    wp_send_json_error('Todos los campos son obligatorios.');
  }

  if (!is_email($email)) {
    wp_send_json_error('El correo electrónico no es válido.');
  }

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    wp_send_json_error('La fecha no es válida.');
  }

  [$year, $month, $day] = array_map('intval', explode('-', $fecha));
  if (!checkdate($month, $day, $year)) {
    wp_send_json_error('La fecha no es válida.');
  }

  if (!in_array($jornada, $jornadas_permitidas, true)) {
    wp_send_json_error('La jornada no es válida.');
  }

  if (function_exists('rae_personal_aseo_disponible') && !rae_personal_aseo_disponible($persona_id, $fecha)) {
    wp_send_json_error('La persona seleccionada no está disponible para reservas.');
  }

  $jornadas_conflicto = $jornada === 'completa'
    ? $jornadas_permitidas
    : [$jornada, 'completa'];

  $placeholders_jornadas = implode(', ', array_fill(0, count($jornadas_conflicto), '%s'));
  $parametros_conflicto = array_merge([$persona_id, $fecha], $jornadas_conflicto);

  $existe = $wpdb->get_var(
    $wpdb->prepare(
      "SELECT COUNT(*) FROM $table WHERE persona_id = %d AND fecha = %s AND jornada IN ($placeholders_jornadas)",
      $parametros_conflicto
    )
  );

  if ($existe > 0) {
    wp_send_json_error('Esta persona ya tiene una reserva que entra en conflicto para esa fecha.');
  }

  $insertado = $wpdb->insert(
    $table,
    [
      'cliente_nombre' => $nombre,
      'cliente_email' => $email,
      'persona_id' => $persona_id,
      'fecha' => $fecha,
      'jornada' => $jornada,
      'cliente_ciudad' => $ciudad,
      'cliente_barrio' => $barrio,
      'cliente_direccion' => $direccion,
      'cliente_casa' => $casa,
      'estado' => 'pendiente',
    ],
    ['%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
  );

  if (!$insertado) {
    wp_send_json_error('No se pudo guardar la reserva.');
  }

  if (function_exists('rae_enviar_email_reserva_creada')) {
    rae_enviar_email_reserva_creada((object) [
      'cliente_nombre' => $nombre,
      'cliente_email' => $email,
      'cliente_ciudad' => $ciudad,
      'cliente_barrio' => $barrio,
      'cliente_direccion' => $direccion,
      'cliente_casa' => $casa,
      'persona_id' => $persona_id,
      'fecha' => $fecha,
      'jornada' => $jornada,
      'estado' => 'pendiente',
    ]);
  }

  wp_send_json_success('Reserva creada correctamente.');
}
