<?php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_rae_guardar_reserva', 'rae_guardar_reserva');
add_action('wp_ajax_nopriv_rae_guardar_reserva', 'rae_guardar_reserva');

function rae_guardar_reserva() {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';

  if (
    !isset($_POST['nonce']) ||
    !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'rae_guardar_reserva')
  ) {
    wp_send_json_error('No se pudo validar la solicitud.');
  }

  $nombre = sanitize_text_field(wp_unslash($_POST['nombre'] ?? ''));
  $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
  $telefono = sanitize_text_field(wp_unslash($_POST['telefono'] ?? ''));
  $persona_id = absint(wp_unslash($_POST['persona_id'] ?? 0));
  $fecha = sanitize_text_field(wp_unslash($_POST['fecha'] ?? ''));
  $jornada = sanitize_text_field(wp_unslash($_POST['jornada'] ?? ''));
  $ciudad = sanitize_text_field(wp_unslash($_POST['ciudad'] ?? ''));
  $barrio = sanitize_text_field(wp_unslash($_POST['barrio'] ?? ''));
  $direccion = sanitize_text_field(wp_unslash($_POST['direccion'] ?? ''));
  $casa = sanitize_text_field(wp_unslash($_POST['casa'] ?? ''));
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];

  if (!$nombre || !$email || !$telefono || !$persona_id || !$fecha || !$jornada || !$ciudad || !$barrio || !$direccion || !$casa) {
    wp_send_json_error('Todos los campos son obligatorios.');
  }

  if (mb_strlen($direccion) < 4 || mb_strlen($casa) < 4) {
    wp_send_json_error('La dirección y la casa/apartamento deben tener al menos 4 caracteres.');
  }

  if (!is_email($email)) {
    wp_send_json_error('El correo electrónico no es válido.');
  }

  if (!preg_match('/^[0-9+\-\s()]{7,30}$/', $telefono)) {
    wp_send_json_error('El número de teléfono no es válido.');
  }

  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    wp_send_json_error('La fecha no es válida.');
  }

  [$year, $month, $day] = array_map('intval', explode('-', $fecha));
  if (!checkdate($month, $day, $year)) {
    wp_send_json_error('La fecha no es válida.');
  }

  $hoy = wp_date('Y-m-d');

  if ($fecha < $hoy) {
    wp_send_json_error('No puedes reservar una fecha anterior al día actual.');
  }

  if (function_exists('rae_es_festivo_colombia') && rae_es_festivo_colombia($fecha)) {
    wp_send_json_error('No puedes reservar en días festivos de Colombia.');
  }

  if (!in_array($jornada, $jornadas_permitidas, true)) {
    wp_send_json_error('La jornada no es válida.');
  }

  $persona = get_post($persona_id);

  if (!$persona || $persona->post_type !== 'personal_aseo' || $persona->post_status !== 'publish') {
    wp_send_json_error('La persona seleccionada no es válida.');
  }

  if (function_exists('rae_personal_aseo_disponible') && !rae_personal_aseo_disponible($persona_id, $fecha)) {
    wp_send_json_error('La persona seleccionada no está disponible para reservas.');
  }

  if (!function_exists('rae_wompi_can_create_checkout') || !rae_wompi_can_create_checkout($jornada)) {
    wp_send_json_error('La pasarela de pagos no está configurada correctamente.');
  }

  $amount_cop = function_exists('rae_wompi_amount_for_jornada') ? rae_wompi_amount_for_jornada($jornada) : 0;
  $created_at = current_time('mysql');

  if ($amount_cop <= 0) {
    wp_send_json_error('No hay un valor configurado para la jornada seleccionada.');
  }

  if (!rae_adquirir_bloqueo_reserva($persona_id, $fecha)) {
    wp_send_json_error('Hay otra reserva procesándose para esa fecha. Intenta nuevamente en unos segundos.');
  }

  if (rae_reserva_tiene_conflicto($persona_id, $fecha, $jornada)) {
    rae_liberar_bloqueo_reserva($persona_id, $fecha);
    wp_send_json_error('Esta persona ya tiene una reserva que entra en conflicto para esa fecha.');
  }

  $insertado = $wpdb->insert(
    $table,
    [
      'cliente_nombre' => $nombre,
      'cliente_email' => $email,
      'cliente_telefono' => $telefono,
      'persona_id' => $persona_id,
      'fecha' => $fecha,
      'jornada' => $jornada,
      'cliente_ciudad' => $ciudad,
      'cliente_barrio' => $barrio,
      'cliente_direccion' => $direccion,
      'cliente_casa' => $casa,
      'estado' => 'pendiente_pago',
      'payment_status' => 'pending',
      'payment_gateway' => 'wompi',
      'payment_amount_cop' => $amount_cop,
      'created_at' => $created_at,
    ],
    ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
  );

  rae_liberar_bloqueo_reserva($persona_id, $fecha);

  if (!$insertado) {
    wp_send_json_error('No se pudo guardar la reserva.');
  }

  $reserva_id = absint($wpdb->insert_id);
  $reference = rae_wompi_create_reference($reserva_id);
  $amount_in_cents = $amount_cop * 100;
  $reserva = (object) [
    'id' => $reserva_id,
    'cliente_nombre' => $nombre,
    'cliente_email' => $email,
    'cliente_telefono' => $telefono,
    'cliente_ciudad' => $ciudad,
    'cliente_barrio' => $barrio,
    'cliente_direccion' => $direccion,
    'cliente_casa' => $casa,
    'persona_id' => $persona_id,
    'fecha' => $fecha,
    'jornada' => $jornada,
    'estado' => 'pendiente_pago',
    'payment_reference' => $reference,
    'payment_amount_cop' => $amount_cop,
    'created_at' => $created_at,
  ];

  $payment_updated = $wpdb->update(
    $table,
    [
      'payment_reference' => $reference,
    ],
    ['id' => $reserva_id],
    ['%s'],
    ['%d']
  );

  if ($payment_updated === false) {
    $wpdb->delete($table, ['id' => $reserva_id], ['%d']);
    wp_send_json_error('La reserva fue creada, pero no se pudo preparar el pago.');
  }

  if (function_exists('rae_enviar_email_reserva_creada')) {
    rae_enviar_email_reserva_creada($reserva);
  }

  wp_send_json_success([
    'message' => 'Reserva creada correctamente. Serás redirigido a Wompi para completar el pago.',
    'payment_url' => rae_wompi_checkout_url($reserva, $reference, $amount_in_cents),
    'reference' => $reference,
  ]);
}
