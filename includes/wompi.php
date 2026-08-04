<?php

if (!defined('ABSPATH')) exit;

function rae_wompi_default_settings() {
  return [
    'mode' => 'sandbox',
    'public_key' => '',
    'private_key' => '',
    'events_secret' => '',
    'integrity_secret' => '',
    'currency' => 'COP',
    'amount_cop' => '',
    'half_day_amount_cop' => '',
    'full_day_amount_cop' => '',
    'payment_expiration_minutes' => 20,
  ];
}

function rae_wompi_settings() {
  $settings = wp_parse_args((array) get_option('rae_wompi_settings', []), rae_wompi_default_settings());
  $constant_map = [
    'mode' => 'RAE_WOMPI_MODE',
    'public_key' => 'RAE_WOMPI_PUBLIC_KEY',
    'private_key' => 'RAE_WOMPI_PRIVATE_KEY',
    'events_secret' => 'RAE_WOMPI_EVENTS_SECRET',
    'integrity_secret' => 'RAE_WOMPI_INTEGRITY_SECRET',
    'currency' => 'RAE_WOMPI_CURRENCY',
    'amount_cop' => 'RAE_WOMPI_AMOUNT_COP',
    'half_day_amount_cop' => 'RAE_WOMPI_HALF_DAY_AMOUNT_COP',
    'full_day_amount_cop' => 'RAE_WOMPI_FULL_DAY_AMOUNT_COP',
    'payment_expiration_minutes' => 'RAE_WOMPI_PAYMENT_EXPIRATION_MINUTES',
  ];

  foreach ($constant_map as $key => $constant) {
    if (defined($constant) && constant($constant) !== '') {
      $settings[$key] = (string) constant($constant);
    }
  }

  $settings['mode'] = $settings['mode'] === 'production' ? 'production' : 'sandbox';
  $settings['currency'] = 'COP';

  return $settings;
}

function rae_wompi_api_base_url() {
  $settings = rae_wompi_settings();

  return $settings['mode'] === 'production'
    ? 'https://production.wompi.co/v1'
    : 'https://sandbox.wompi.co/v1';
}

function rae_wompi_sanitize_amount_cop($amount) {
  return preg_replace('/[^0-9]/', '', (string) $amount);
}

function rae_wompi_amount_for_jornada($jornada) {
  $settings = rae_wompi_settings();
  $fallback_amount = rae_wompi_sanitize_amount_cop($settings['amount_cop'] ?? '');
  $half_day_amount = rae_wompi_sanitize_amount_cop($settings['half_day_amount_cop'] ?? '');
  $full_day_amount = rae_wompi_sanitize_amount_cop($settings['full_day_amount_cop'] ?? '');
  $amount = '';

  if (in_array($jornada, ['manana', 'tarde'], true)) {
    $amount = $half_day_amount !== '' ? $half_day_amount : $fallback_amount;
  } elseif ($jornada === 'completa') {
    $amount = $full_day_amount !== '' ? $full_day_amount : $fallback_amount;
  }

  if ($amount === '') {
    return 0;
  }

  return absint($amount);
}

function rae_wompi_amount_in_cents($jornada = '') {
  $amount = rae_wompi_amount_for_jornada($jornada);

  return $amount > 0 ? $amount * 100 : 0;
}

function rae_wompi_payment_expiration_minutes($settings = null) {
  $settings = is_array($settings) ? $settings : rae_wompi_settings();
  $minutes = absint($settings['payment_expiration_minutes'] ?? 20);

  return max(10, min(60, $minutes ?: 20));
}

function rae_wompi_reservation_hold_minutes($settings = null) {
  return rae_wompi_payment_expiration_minutes($settings) + 5;
}

function rae_wompi_checkout_expiration_time($created_at = '', $settings = null) {
  try {
    $created = $created_at !== ''
      ? new DateTimeImmutable($created_at, wp_timezone())
      : new DateTimeImmutable('now', wp_timezone());
  } catch (Exception $exception) {
    $created = new DateTimeImmutable('now', wp_timezone());
  }

  return $created
    ->modify('+' . rae_wompi_payment_expiration_minutes($settings) . ' minutes')
    ->setTimezone(new DateTimeZone('UTC'))
    ->format('Y-m-d\TH:i:s.000\Z');
}

function rae_wompi_credentials_match_mode($settings = null) {
  $settings = is_array($settings) ? $settings : rae_wompi_settings();
  $mode = ($settings['mode'] ?? '') === 'production' ? 'production' : 'sandbox';
  $prefixes = $mode === 'production'
    ? [
      'public_key' => 'pub_prod_',
      'private_key' => 'prv_prod_',
      'events_secret' => 'prod_events_',
      'integrity_secret' => 'prod_integrity_',
    ]
    : [
      'public_key' => 'pub_test_',
      'private_key' => 'prv_test_',
      'events_secret' => 'test_events_',
      'integrity_secret' => 'test_integrity_',
    ];

  foreach ($prefixes as $key => $prefix) {
    $value = (string) ($settings[$key] ?? '');

    if ($value === '' || strpos($value, $prefix) !== 0) {
      return false;
    }
  }

  return true;
}

function rae_wompi_can_create_checkout($jornada = '') {
  $settings = rae_wompi_settings();

  return rae_wompi_credentials_match_mode($settings)
    && rae_wompi_amount_in_cents($jornada) > 0;
}

function rae_wompi_create_reference($reserva_id) {
  return 'RAE-' . absint($reserva_id) . '-' . strtoupper(wp_generate_password(10, false, false));
}

function rae_wompi_integrity_signature($reference, $amount_in_cents, $currency, $expiration_time = '') {
  $settings = rae_wompi_settings();
  $payload = $reference . $amount_in_cents . $currency;

  if ($expiration_time !== '') {
    $payload .= $expiration_time;
  }

  return hash('sha256', $payload . $settings['integrity_secret']);
}

function rae_wompi_return_url($reference = '') {
  $args = ['rae_wompi_return' => '1'];

  if ($reference !== '') {
    $args['reference'] = rawurlencode($reference);
  }

  return add_query_arg($args, home_url('/'));
}

function rae_wompi_checkout_url($reserva, $reference, $amount_in_cents) {
  $settings = rae_wompi_settings();
  $currency = $settings['currency'];
  $expiration_time = rae_wompi_checkout_expiration_time($reserva->created_at ?? '', $settings);
  $params = [
    'public-key' => $settings['public_key'],
    'currency' => $currency,
    'amount-in-cents' => $amount_in_cents,
    'reference' => $reference,
    'signature:integrity' => rae_wompi_integrity_signature($reference, $amount_in_cents, $currency, $expiration_time),
    'expiration-time' => $expiration_time,
    'redirect-url' => rae_wompi_return_url($reference),
    'customer-data:email' => $reserva->cliente_email ?? '',
    'customer-data:full-name' => $reserva->cliente_nombre ?? '',
    'customer-data:phone-number' => preg_replace('/[^0-9]/', '', (string) ($reserva->cliente_telefono ?? '')),
    'customer-data:phone-number-prefix' => '+57',
  ];

  return add_query_arg(array_filter($params, static function ($value) {
    return $value !== '';
  }), 'https://checkout.wompi.co/p/');
}

function rae_wompi_log($message, $context = []) {
  if (!defined('WP_DEBUG') || !WP_DEBUG) {
    return;
  }

  $safe_context = $context;
  unset($safe_context['private_key'], $safe_context['events_secret'], $safe_context['integrity_secret']);
  error_log('[RAE Wompi] ' . $message . ' ' . wp_json_encode($safe_context));
}

function rae_wompi_record_webhook_log($status, $message, $context = []) {
  $logs = get_option('rae_wompi_webhook_logs', []);

  if (!is_array($logs)) {
    $logs = [];
  }

  $safe_context = [];

  foreach ((array) $context as $key => $value) {
    if (in_array($key, ['private_key', 'events_secret', 'integrity_secret'], true)) {
      continue;
    }

    if (is_scalar($value) || $value === null) {
      $safe_context[$key] = sanitize_text_field((string) $value);
    }
  }

  array_unshift($logs, [
    'created_at' => current_time('mysql'),
    'status' => sanitize_key($status),
    'message' => sanitize_text_field($message),
    'context' => $safe_context,
  ]);

  update_option('rae_wompi_webhook_logs', array_slice($logs, 0, 20), false);
}

function rae_wompi_event_value($data, $path) {
  $current = $data;

  foreach (explode('.', $path) as $segment) {
    if (is_array($current) && array_key_exists($segment, $current)) {
      $current = $current[$segment];
      continue;
    }

    return '';
  }

  if (is_bool($current)) {
    return $current ? 'true' : 'false';
  }

  return is_scalar($current) ? (string) $current : '';
}

function rae_wompi_validate_event_signature($event, $request) {
  $settings = rae_wompi_settings();

  if ($settings['events_secret'] === '') {
    return false;
  }

  $properties = $event['signature']['properties'] ?? [];
  $checksum = $event['signature']['checksum'] ?? '';
  $timestamp = $event['timestamp'] ?? '';
  $data = $event['data'] ?? [];

  if (!is_array($properties) || $checksum === '' || $timestamp === '' || !is_array($data)) {
    return false;
  }

  $payload = '';

  foreach ($properties as $property) {
    $payload .= rae_wompi_event_value($data, (string) $property);
  }

  $payload .= (string) $timestamp;
  $payload .= $settings['events_secret'];

  $calculated = hash('sha256', $payload);
  $header_checksum = $request instanceof WP_REST_Request ? $request->get_header('x-event-checksum') : '';
  $received = $header_checksum !== '' ? $header_checksum : $checksum;

  return hash_equals(strtolower($received), strtolower($calculated));
}

function rae_wompi_update_reserva_from_transaction($transaction, $raw_event = null) {
  global $wpdb;

  if (!is_array($transaction)) {
    return false;
  }

  $reference = sanitize_text_field($transaction['reference'] ?? '');
  $transaction_id = sanitize_text_field($transaction['id'] ?? '');

  if ($reference === '' || $transaction_id === '') {
    return false;
  }

  $table = $wpdb->prefix . 'reservas_aseo';
  $reserva = null;

  if ($reference !== '') {
    $reserva = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $table WHERE payment_reference = %s", $reference)
    );
  }

  if (!$reserva && $transaction_id !== '') {
    $reserva = $wpdb->get_row(
      $wpdb->prepare("SELECT * FROM $table WHERE wompi_transaction_id = %s", $transaction_id)
    );
  }

  if (!$reserva) {
    rae_wompi_log('Reserva no encontrada para evento', ['reference' => $reference, 'transaction_id' => $transaction_id]);
    return false;
  }

  $stored_reference = (string) ($reserva->payment_reference ?? '');
  $stored_transaction_id = (string) ($reserva->wompi_transaction_id ?? '');

  if ($stored_reference === '' || !hash_equals($stored_reference, $reference)) {
    rae_wompi_log('La referencia de Wompi no coincide con la reserva', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'reserva_id' => absint($reserva->id),
    ]);

    return false;
  }

  if ($stored_transaction_id !== '' && !hash_equals($stored_transaction_id, $transaction_id)) {
    rae_wompi_log('El ID de transacción no coincide con la reserva', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'reserva_id' => absint($reserva->id),
    ]);

    return false;
  }

  $wompi_status = strtoupper(sanitize_text_field($transaction['status'] ?? ''));
  $transaction_currency = strtoupper(sanitize_text_field($transaction['currency'] ?? ''));
  $transaction_amount_in_cents = absint($transaction['amount_in_cents'] ?? 0);
  $expected_amount_in_cents = absint($reserva->payment_amount_cop ?? 0) * 100;
  $estado_reserva = $reserva->estado;
  $payment_status = strtolower($wompi_status);
  $paid_at = $reserva->paid_at ?? null;

  if (!in_array($wompi_status, ['APPROVED', 'DECLINED', 'VOIDED', 'ERROR', 'PENDING', 'PENDING_VOBO'], true)) {
    rae_wompi_log('Estado de transacción Wompi desconocido', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'status' => $wompi_status,
    ]);

    return false;
  }

  if (
    $expected_amount_in_cents <= 0 ||
    $transaction_amount_in_cents !== $expected_amount_in_cents ||
    $transaction_currency !== 'COP'
  ) {
    rae_wompi_log('Monto o moneda de transacción no coincide con reserva', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'expected_amount_in_cents' => $expected_amount_in_cents,
      'transaction_amount_in_cents' => $transaction_amount_in_cents,
      'transaction_currency' => $transaction_currency,
    ]);

    return false;
  }

  if (strtolower((string) ($reserva->payment_status ?? '')) === 'approved' && $wompi_status !== 'APPROVED') {
    rae_wompi_log('Se ignoró una regresión de estado para un pago aprobado', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'incoming_status' => $wompi_status,
    ]);

    return true;
  }

  if ($wompi_status === 'APPROVED') {
    if ($estado_reserva === 'expirada') {
      $estado_reserva = 'pago_revision';
    } elseif ($estado_reserva !== 'cancelada') {
      $estado_reserva = 'confirmada';
    }

    if (empty($paid_at)) {
      $paid_at = current_time('mysql');
    }
  } elseif (in_array($wompi_status, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
    $estado_reserva = 'rechazada';
  } elseif (in_array($wompi_status, ['PENDING', 'PENDING_VOBO'], true)) {
    $estado_reserva = 'pendiente_pago';
  }

  $payment_response = wp_json_encode($raw_event ?: $transaction);
  $updated = $wpdb->update(
    $table,
    [
      'estado' => $estado_reserva,
      'payment_status' => $payment_status,
      'wompi_transaction_id' => $transaction_id,
      'payment_gateway' => 'wompi',
      'payment_response' => $payment_response,
      'paid_at' => $paid_at,
    ],
    ['id' => absint($reserva->id)],
    ['%s', '%s', '%s', '%s', '%s', '%s'],
    ['%d']
  );

  if ($updated !== false && $estado_reserva !== $reserva->estado && is_email($reserva->cliente_email)) {
    $reserva->estado = $estado_reserva;
    $reserva->payment_status = $payment_status;
    $reserva->wompi_transaction_id = $transaction_id;
    $reserva->payment_gateway = 'wompi';
    $reserva->paid_at = $paid_at;
    if ($estado_reserva === 'pago_revision' && function_exists('rae_enviar_email_notificacion_interna')) {
      rae_enviar_email_notificacion_interna($reserva, $estado_reserva, 'Pago recibido después de caducar la reserva');
    } else {
      rae_enviar_email_estado_reserva($reserva, $estado_reserva);
    }
  }

  return $updated !== false;
}

function rae_wompi_expiration_cutoff($now = '', $settings = null) {
  try {
    $current = $now !== ''
      ? new DateTimeImmutable($now, wp_timezone())
      : new DateTimeImmutable('now', wp_timezone());
  } catch (Exception $exception) {
    $current = new DateTimeImmutable('now', wp_timezone());
  }

  return $current
    ->modify('-' . rae_wompi_reservation_hold_minutes($settings) . ' minutes')
    ->format('Y-m-d H:i:s');
}

function rae_wompi_expire_pending_reservations($now = '') {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $cutoff = rae_wompi_expiration_cutoff($now);
  $reservations = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT * FROM $table WHERE estado = %s AND payment_gateway = %s AND payment_status IN (%s, %s, %s) AND created_at <= %s ORDER BY id ASC LIMIT 50",
      'pendiente_pago',
      'wompi',
      '',
      'pending',
      'pending_vobo',
      $cutoff
    )
  );
  $expired = 0;

  foreach ($reservations as $reservation) {
    $updated = $wpdb->query(
      $wpdb->prepare(
        "UPDATE $table SET estado = %s, payment_status = %s WHERE id = %d AND estado = %s AND payment_status IN (%s, %s, %s)",
        'expirada',
        'expired',
        absint($reservation->id),
        'pendiente_pago',
        '',
        'pending',
        'pending_vobo'
      )
    );

    if ($updated) {
      $expired++;
      $reservation->estado = 'expirada';
      $reservation->payment_status = 'expired';

      if (is_email($reservation->cliente_email ?? '')) {
        rae_enviar_email_estado_reserva($reservation, 'expirada');
      }
    }
  }

  return $expired;
}

add_filter('cron_schedules', function ($schedules) {
  $schedules['rae_every_five_minutes'] = [
    'interval' => 5 * MINUTE_IN_SECONDS,
    'display' => 'Cada 5 minutos (Reservas de Aseo)',
  ];

  return $schedules;
});

function rae_wompi_schedule_expiration() {
  if (!wp_next_scheduled('rae_wompi_expire_pending_reservations')) {
    wp_schedule_event(time() + MINUTE_IN_SECONDS, 'rae_every_five_minutes', 'rae_wompi_expire_pending_reservations');
  }
}

add_action('rae_wompi_expire_pending_reservations', 'rae_wompi_expire_pending_reservations');

add_action('rest_api_init', function () {
  register_rest_route('sat-reservas/v1', '/wompi-webhook', [
    'methods' => WP_REST_Server::CREATABLE,
    'callback' => 'rae_wompi_webhook',
    'permission_callback' => '__return_true',
  ]);
});

function rae_wompi_webhook(WP_REST_Request $request) {
  $event = $request->get_json_params();
  $event_name = is_array($event) ? sanitize_text_field($event['event'] ?? '') : '';

  rae_wompi_record_webhook_log('received', 'Webhook recibido', [
    'event' => $event_name,
    'ip' => $request->get_header('x-forwarded-for') ?: ($_SERVER['REMOTE_ADDR'] ?? ''),
  ]);

  if (!is_array($event) || $event_name !== 'transaction.updated') {
    rae_wompi_record_webhook_log('ignored', 'Evento ignorado', [
      'event' => $event_name,
    ]);

    return new WP_REST_Response(['received' => true], 200);
  }

  if (!rae_wompi_validate_event_signature($event, $request)) {
    rae_wompi_log('Firma de webhook inválida');
    rae_wompi_record_webhook_log('invalid_signature', 'Firma de webhook inválida', [
      'event' => $event_name,
    ]);

    return new WP_Error('rae_wompi_invalid_signature', 'Firma inválida.', ['status' => 401]);
  }

  $transaction = $event['data']['transaction'] ?? [];
  $reference = is_array($transaction) ? sanitize_text_field($transaction['reference'] ?? '') : '';
  $transaction_id = is_array($transaction) ? sanitize_text_field($transaction['id'] ?? '') : '';
  $transaction_status = is_array($transaction) ? sanitize_text_field($transaction['status'] ?? '') : '';

  if (!rae_wompi_update_reserva_from_transaction($transaction, $event)) {
    rae_wompi_record_webhook_log('not_updated', 'Webhook válido, pero no actualizó reserva', [
      'reference' => $reference,
      'transaction_id' => $transaction_id,
      'transaction_status' => $transaction_status,
    ]);

    return new WP_REST_Response(['received' => true, 'updated' => false], 200);
  }

  rae_wompi_record_webhook_log('updated', 'Reserva actualizada por webhook', [
    'reference' => $reference,
    'transaction_id' => $transaction_id,
    'transaction_status' => $transaction_status,
  ]);

  return new WP_REST_Response(['received' => true, 'updated' => true], 200);
}

function rae_wompi_fetch_transaction($transaction_id) {
  $settings = rae_wompi_settings();

  if ($transaction_id === '' || $settings['private_key'] === '') {
    return null;
  }

  $response = wp_remote_get(
    rae_wompi_api_base_url() . '/transactions/' . rawurlencode($transaction_id),
    [
      'headers' => [
        'Authorization' => 'Bearer ' . $settings['private_key'],
      ],
      'timeout' => 12,
    ]
  );

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
    rae_wompi_log('No se pudo consultar transacción de retorno', ['transaction_id' => $transaction_id]);
    return null;
  }

  $body = json_decode(wp_remote_retrieve_body($response), true);

  return is_array($body) ? ($body['data'] ?? null) : null;
}

function rae_wompi_normalize_transaction_response($body, $reference = '') {
  if (!is_array($body)) {
    return null;
  }

  $data = $body['data'] ?? null;

  if (is_array($data) && isset($data['id'])) {
    return $data;
  }

  if (is_array($data)) {
    foreach ($data as $transaction) {
      if (!is_array($transaction)) {
        continue;
      }

      if ($reference === '' || (($transaction['reference'] ?? '') === $reference)) {
        return $transaction;
      }
    }
  }

  return null;
}

function rae_wompi_fetch_transaction_by_reference($reference) {
  $settings = rae_wompi_settings();
  $reference = sanitize_text_field($reference);

  if ($reference === '' || $settings['private_key'] === '') {
    return null;
  }

  $response = wp_remote_get(
    add_query_arg('reference', rawurlencode($reference), rae_wompi_api_base_url() . '/transactions'),
    [
      'headers' => [
        'Authorization' => 'Bearer ' . $settings['private_key'],
      ],
      'timeout' => 12,
    ]
  );

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
    rae_wompi_log('No se pudo consultar transacción por referencia', [
      'reference' => $reference,
      'status' => is_wp_error($response) ? $response->get_error_code() : wp_remote_retrieve_response_code($response),
    ]);
    return null;
  }

  $body = json_decode(wp_remote_retrieve_body($response), true);

  return rae_wompi_normalize_transaction_response($body, $reference);
}

function rae_wompi_verify_reserva_payment($reserva) {
  if (!$reserva) {
    return false;
  }

  $transaction = null;

  if (!empty($reserva->wompi_transaction_id)) {
    $transaction = rae_wompi_fetch_transaction($reserva->wompi_transaction_id);
  }

  if (!$transaction && !empty($reserva->payment_reference)) {
    $transaction = rae_wompi_fetch_transaction_by_reference($reserva->payment_reference);
  }

  if (!is_array($transaction)) {
    return false;
  }

  return rae_wompi_update_reserva_from_transaction($transaction, [
    'source' => 'manual_or_return_verification',
    'transaction' => $transaction,
  ]);
}

add_action('template_redirect', function () {
  $is_return = isset($_GET['rae_wompi_return']) ? sanitize_text_field(wp_unslash($_GET['rae_wompi_return'])) : '';

  if ($is_return !== '1') {
    return;
  }

  $transaction_id = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : '';
  $reference = isset($_GET['reference']) ? sanitize_text_field(wp_unslash($_GET['reference'])) : '';
  $transaction = rae_wompi_fetch_transaction($transaction_id);

  if (!$transaction && $reference !== '') {
    $transaction = rae_wompi_fetch_transaction_by_reference($reference);
  }

  if (is_array($transaction)) {
    rae_wompi_update_reserva_from_transaction($transaction, [
      'source' => 'return_verification',
      'transaction' => $transaction,
    ]);
  }

  $status = strtoupper((string) ($transaction['status'] ?? ''));
  $title = 'Pago recibido para verificación';
  $message = 'Recibimos tu regreso desde Wompi. La reserva se confirmará cuando Wompi notifique el resultado final mediante webhook.';

  if ($status === 'APPROVED') {
    $title = 'Pago aprobado';
    $message = 'Wompi reporta el pago como aprobado. Estamos esperando la confirmación segura del webhook para actualizar tu reserva.';
  } elseif (in_array($status, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
    $title = 'Pago no aprobado';
    $message = 'Wompi reporta que el pago no fue aprobado. Puedes intentar de nuevo o comunicarte con nuestro equipo.';
  } elseif ($status === 'PENDING') {
    $title = 'Pago pendiente';
    $message = 'Tu pago sigue pendiente en Wompi. Te notificaremos cuando el estado final sea confirmado.';
  }

  status_header(200);
  nocache_headers();
  ?>
  <!doctype html>
  <html <?php language_attributes(); ?>>
    <head>
      <meta charset="<?php bloginfo('charset'); ?>">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?php echo esc_html($title); ?></title>
      <?php wp_head(); ?>
    </head>
    <body <?php body_class('rae-wompi-return'); ?>>
      <main style="max-width: 720px; margin: 64px auto; padding: 24px; font-family: Arial, sans-serif;">
        <h1><?php echo esc_html($title); ?></h1>
        <p><?php echo esc_html($message); ?></p>
        <?php if ($transaction_id !== ''): ?>
          <p><strong>ID de transacción:</strong> <?php echo esc_html($transaction_id); ?></p>
        <?php endif; ?>
        <p><a href="<?php echo esc_url(home_url('/')); ?>">Volver al inicio</a></p>
      </main>
      <?php wp_footer(); ?>
    </body>
  </html>
  <?php
  exit;
});

