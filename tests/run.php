<?php

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

$GLOBALS['rae_test_options'] = [];

function add_action() {}
function add_filter() {}
function absint($value) { return abs((int) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function get_current_blog_id() { return 1; }
function get_option($key, $default = false) {
  return array_key_exists($key, $GLOBALS['rae_test_options'])
    ? $GLOBALS['rae_test_options'][$key]
    : $default;
}
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, (array) $args); }
function wp_generate_password() { return 'TESTREFERENCE'; }
function wp_timezone() { return new DateTimeZone('America/Bogota'); }
function wp_json_encode($value) { return json_encode($value); }
function wp_salt() { return 'test-wordpress-auth-salt'; }
function current_time() { return '2026-08-04 12:00:00'; }
function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL) !== false; }
function rae_enviar_email_reserva_estado() { return true; }

class RAE_Test_WPDB {
  public $prefix = 'wp_';
  public $next_get_var = 0;
  public $next_get_row = null;
  public $next_get_results = [];
  public $next_get_results_queue = [];
  public $next_query_result = 1;
  public $next_insert_result = 1;
  public $next_update_result = 1;
  public $last_query = '';
  public $queries = [];
  public $last_update = null;
  public $last_insert = null;

  public function prepare($query, ...$args) {
    $this->last_query = $query;
    return $query;
  }

  public function get_var($query) {
    $this->last_query = $query;
    return $this->next_get_var;
  }

  public function get_row($query) {
    $this->last_query = $query;
    return $this->next_get_row;
  }

  public function get_results($query) {
    $this->last_query = $query;
    if ($this->next_get_results_queue) {
      return array_shift($this->next_get_results_queue);
    }
    return $this->next_get_results;
  }

  public function query($query) {
    $this->last_query = $query;
    $this->queries[] = $query;
    return $this->next_query_result;
  }

  public function update($table, $data, $where, $formats, $where_formats) {
    $this->last_update = compact('table', 'data', 'where', 'formats', 'where_formats');
    return $this->next_update_result;
  }

  public function insert($table, $data, $formats) {
    $this->last_insert = compact('table', 'data', 'formats');
    return $this->next_insert_result;
  }
}

$GLOBALS['wpdb'] = new RAE_Test_WPDB();

require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/holidays.php';
require_once dirname(__DIR__) . '/includes/wompi.php';
require_once dirname(__DIR__) . '/includes/admin-reservas.php';

$tests = 0;
$failures = [];

function rae_test_assert($condition, $message) {
  global $tests, $failures;
  $tests++;

  if (!$condition) {
    $failures[] = $message;
  }
}

function rae_test_wompi_settings($mode) {
  $environment = $mode === 'production' ? 'prod' : 'test';

  return [
    'mode' => $mode,
    'public_key' => 'pub_' . $environment . '_public',
    'private_key' => 'prv_' . $environment . '_private',
    'events_secret' => $environment . '_events_secret',
    'integrity_secret' => $environment . '_integrity_secret',
    'currency' => 'COP',
    'amount_cop' => '',
    'half_day_amount_cop' => '120000',
    'full_day_amount_cop' => '150000',
  ];
}

$sandbox = rae_test_wompi_settings('sandbox');
$production = rae_test_wompi_settings('production');

rae_test_assert(rae_wompi_credentials_match_mode($sandbox), 'Las credenciales Sandbox válidas fueron rechazadas.');
rae_test_assert(rae_wompi_credentials_match_mode($production), 'Las credenciales de Producción válidas fueron rechazadas.');

$mixed = $sandbox;
$mixed['private_key'] = $production['private_key'];
rae_test_assert(!rae_wompi_credentials_match_mode($mixed), 'Se aceptaron credenciales mezcladas entre ambientes.');

$incomplete = $sandbox;
$incomplete['events_secret'] = '';
rae_test_assert(!rae_wompi_credentials_match_mode($incomplete), 'Se aceptó una configuración sin secreto de eventos.');

$GLOBALS['rae_test_options']['rae_wompi_settings'] = $sandbox;
rae_test_assert(rae_wompi_amount_for_jornada('manana') === 120000, 'El precio de la mañana no coincide.');
rae_test_assert(rae_wompi_amount_for_jornada('tarde') === 120000, 'El precio de la tarde no coincide.');
rae_test_assert(rae_wompi_amount_for_jornada('completa') === 150000, 'El precio de jornada completa no coincide.');
rae_test_assert(rae_wompi_amount_for_jornada('invalida') === 0, 'Una jornada inválida recibió precio.');

$expected_signature = hash('sha256', 'RAE-1-TEST12000000COP' . $sandbox['integrity_secret']);
rae_test_assert(
  hash_equals($expected_signature, rae_wompi_integrity_signature('RAE-1-TEST', 12000000, 'COP')),
  'La firma de integridad no coincide con el formato de Wompi.'
);

$expiration_time = '2026-08-04T18:20:00.000Z';
$expected_expiring_signature = hash(
  'sha256',
  'RAE-1-TEST12000000COP' . $expiration_time . $sandbox['integrity_secret']
);
rae_test_assert(
  hash_equals(
    $expected_expiring_signature,
    rae_wompi_integrity_signature('RAE-1-TEST', 12000000, 'COP', $expiration_time)
  ),
  'La firma no incluyó la expiración del checkout en el orden exigido por Wompi.'
);
rae_test_assert(
  rae_wompi_checkout_expiration_time('2026-08-04 13:00:00', $sandbox) === $expiration_time,
  'La expiración del checkout no se convirtió correctamente a UTC.'
);
rae_test_assert(
  rae_wompi_expiration_cutoff('2026-08-04 14:00:00', $sandbox) === '2026-08-04 13:35:00',
  'El horario no conserva los cinco minutos de margen después de expirar el checkout.'
);

$resume_reservation = (object) [
  'id' => 31,
  'cliente_email' => 'cliente@example.com',
  'payment_reference' => 'RAE-31-TEST',
  'created_at' => '2026-08-04 13:00:00',
];
$resume_expires = (new DateTimeImmutable($expiration_time))->getTimestamp();
$resume_token = rae_wompi_resume_payment_signature($resume_reservation, $resume_expires);
rae_test_assert(
  rae_wompi_resume_payment_signature_is_valid($resume_reservation, $resume_expires, $resume_token),
  'Un enlace auténtico de recuperación fue rechazado.'
);
rae_test_assert(
  rae_wompi_resume_payment_link_is_valid($resume_reservation, $resume_expires, $resume_token, $resume_expires - 1),
  'Un enlace de recuperación vigente fue rechazado.'
);
rae_test_assert(
  !rae_wompi_resume_payment_link_is_valid($resume_reservation, $resume_expires, $resume_token, $resume_expires + 1),
  'Un enlace de recuperación vencido fue aceptado.'
);
rae_test_assert(
  !rae_wompi_resume_payment_signature_is_valid($resume_reservation, $resume_expires, str_repeat('0', 64)),
  'Un token de recuperación alterado fue aceptado.'
);
rae_test_assert(
  !rae_wompi_resume_payment_signature_is_valid($resume_reservation, $resume_expires + 60, $resume_token),
  'Un enlace pudo ampliar su vencimiento original.'
);
rae_test_assert(
  rae_wompi_checkout_expiration_time_from_timestamp($resume_expires) === $expiration_time,
  'No se conservó la expiración original al reconstruir el checkout.'
);
$changed_expiration_settings = $sandbox;
$changed_expiration_settings['payment_expiration_minutes'] = 10;
$GLOBALS['rae_test_options']['rae_wompi_settings'] = $changed_expiration_settings;
rae_test_assert(
  rae_wompi_resume_payment_link_is_valid($resume_reservation, $resume_expires, $resume_token, $resume_expires - 1),
  'Cambiar la configuración invalidó una reserva creada previamente.'
);
$GLOBALS['rae_test_options']['rae_wompi_settings'] = $sandbox;

$lock_a = rae_reserva_lock_name(10, '2026-08-20');
$lock_b = rae_reserva_lock_name(10, '2026-08-21');
rae_test_assert($lock_a !== $lock_b, 'Dos fechas generaron el mismo nombre de bloqueo.');
rae_test_assert(strlen($lock_a) <= 64, 'El nombre del bloqueo excede el límite de MySQL.');

$GLOBALS['wpdb']->next_get_var = 1;
rae_test_assert(rae_reserva_tiene_conflicto(10, '2026-08-20', 'manana'), 'No se detectó una reserva conflictiva.');
$GLOBALS['wpdb']->next_get_var = 0;
rae_test_assert(!rae_reserva_tiene_conflicto(10, '2026-08-20', 'tarde'), 'Se reportó un conflicto inexistente.');
rae_test_assert(rae_reserva_tiene_conflicto(10, '2026-08-20', 'invalida'), 'Una jornada inválida no se bloqueó.');

rae_test_assert(
  !rae_reserva_puede_confirmarse_manualmente((object) [
    'estado' => 'cancelada',
    'payment_gateway' => 'wompi',
    'payment_status' => 'approved',
  ]),
  'Una reserva Wompi cancelada se pudo confirmar manualmente.'
);
rae_test_assert(
  !rae_reserva_puede_confirmarse_manualmente((object) [
    'estado' => 'pendiente_pago',
    'payment_gateway' => 'wompi',
    'payment_status' => 'pending',
  ]),
  'Una reserva Wompi pendiente se pudo confirmar manualmente.'
);
rae_test_assert(
  rae_reserva_puede_confirmarse_manualmente((object) [
    'estado' => 'pendiente',
    'payment_gateway' => '',
  ]),
  'Una reserva heredada pendiente no se pudo confirmar manualmente.'
);

rae_test_assert(rae_es_festivo_colombia('2026-01-01'), 'No se detectó Año Nuevo de 2026.');
rae_test_assert(rae_es_festivo_colombia('2026-01-12'), 'No se trasladó Reyes Magos al lunes en 2026.');
rae_test_assert(rae_es_festivo_colombia('2026-04-03'), 'No se detectó Viernes Santo de 2026.');
rae_test_assert(!rae_es_festivo_colombia('2026-04-04'), 'Se marcó como festivo un día ordinario.');

$base_reservation = (object) [
  'id' => 31,
  'cliente_email' => 'cliente@example.com',
  'estado' => 'pendiente_pago',
  'payment_status' => 'pending',
  'payment_reference' => 'RAE-31-TEST',
  'wompi_transaction_id' => '',
  'payment_amount_cop' => 120000,
  'payment_retry_until' => null,
  'paid_at' => null,
];
$approved_transaction = [
  'id' => '12124635-1783357852-92980',
  'reference' => 'RAE-31-TEST',
  'status' => 'APPROVED',
  'currency' => 'COP',
  'amount_in_cents' => 12000000,
];

$GLOBALS['wpdb']->next_get_row = clone $base_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  rae_wompi_update_reserva_from_transaction($approved_transaction),
  'Una transacción aprobada y válida no actualizó la reserva.'
);
rae_test_assert(
  ($GLOBALS['wpdb']->last_update['data']['estado'] ?? '') === 'confirmada',
  'El pago aprobado no confirmó la reserva.'
);

$expired_reservation = clone $base_reservation;
$expired_reservation->estado = 'expirada';
$expired_reservation->payment_status = 'expired';
$GLOBALS['wpdb']->next_get_row = $expired_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  rae_wompi_update_reserva_from_transaction($approved_transaction),
  'Un pago aprobado fuera de tiempo no se registró para revisión.'
);
rae_test_assert(
  ($GLOBALS['wpdb']->last_update['data']['estado'] ?? '') === 'pago_revision',
  'Un pago recibido después de expirar confirmó la reserva automáticamente.'
);

$wrong_currency = $approved_transaction;
$wrong_currency['currency'] = 'USD';
$GLOBALS['wpdb']->next_get_row = clone $base_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  !rae_wompi_update_reserva_from_transaction($wrong_currency),
  'Se aceptó una transacción en moneda diferente de COP.'
);
rae_test_assert($GLOBALS['wpdb']->last_update === null, 'Una moneda inválida modificó la reserva.');

$wrong_amount = $approved_transaction;
$wrong_amount['amount_in_cents'] = 100;
$GLOBALS['wpdb']->next_get_row = clone $base_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  !rae_wompi_update_reserva_from_transaction($wrong_amount),
  'Se aceptó una transacción con monto incorrecto.'
);

$wrong_reference = $approved_transaction;
$wrong_reference['reference'] = 'RAE-OTRA';
$GLOBALS['wpdb']->next_get_row = clone $base_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  !rae_wompi_update_reserva_from_transaction($wrong_reference),
  'Se aceptó una transacción con referencia diferente.'
);

$approved_reservation = clone $base_reservation;
$approved_reservation->estado = 'confirmada';
$approved_reservation->payment_status = 'approved';
$approved_reservation->wompi_transaction_id = $approved_transaction['id'];
$declined_after_approval = $approved_transaction;
$declined_after_approval['status'] = 'DECLINED';
$GLOBALS['wpdb']->next_get_row = $approved_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  rae_wompi_update_reserva_from_transaction($declined_after_approval),
  'No se procesó de forma idempotente un evento tardío.'
);
rae_test_assert($GLOBALS['wpdb']->last_update === null, 'Un evento tardío degradó un pago aprobado.');

$declined_transaction = $approved_transaction;
$declined_transaction['id'] = 'declined-attempt-1';
$declined_transaction['status'] = 'DECLINED';
$GLOBALS['wpdb']->next_get_var = 0;
$GLOBALS['wpdb']->next_get_row = clone $base_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  rae_wompi_update_reserva_from_transaction($declined_transaction),
  'El primer intento rechazado no abrió la ventana de reintento.'
);
rae_test_assert(
  ($GLOBALS['wpdb']->last_update['data']['estado'] ?? '') === 'pendiente_pago'
    && ($GLOBALS['wpdb']->last_update['data']['payment_status'] ?? '') === 'retry_available'
    && !empty($GLOBALS['wpdb']->last_update['data']['payment_retry_until']),
  'Un intento rechazado liberó la reserva antes de terminar la ventana de reintento.'
);
rae_test_assert(
  ($GLOBALS['wpdb']->last_update['where']['payment_status'] ?? null) === 'pending',
  'Un evento rechazado no quedó protegido frente a una aprobación concurrente.'
);

$retry_reservation = clone $base_reservation;
$retry_reservation->payment_status = 'retry_available';
$retry_reservation->wompi_transaction_id = $declined_transaction['id'];
$retry_reservation->payment_retry_until = '2026-08-04 12:03:00';
$approved_retry = $approved_transaction;
$approved_retry['id'] = 'approved-attempt-2';
$GLOBALS['wpdb']->next_get_row = $retry_reservation;
$GLOBALS['wpdb']->last_update = null;
rae_test_assert(
  rae_wompi_update_reserva_from_transaction($approved_retry),
  'El segundo intento aprobado con otro ID de transacción fue rechazado.'
);
rae_test_assert(
  ($GLOBALS['wpdb']->last_update['data']['estado'] ?? '') === 'confirmada'
    && ($GLOBALS['wpdb']->last_update['data']['payment_status'] ?? '') === 'approved'
    && ($GLOBALS['wpdb']->last_update['data']['wompi_transaction_id'] ?? '') === 'approved-attempt-2'
    && array_key_exists('payment_retry_until', $GLOBALS['wpdb']->last_update['data'])
    && $GLOBALS['wpdb']->last_update['data']['payment_retry_until'] === null,
  'El segundo intento aprobado no confirmó correctamente la misma referencia.'
);
rae_test_assert(
  !array_key_exists('payment_status', $GLOBALS['wpdb']->last_update['where']),
  'La aprobación quedó condicionada a un estado anterior y podría perderse por concurrencia.'
);

$normalized_retry = rae_wompi_normalize_transaction_response([
  'data' => [$declined_transaction, $approved_retry],
], 'RAE-31-TEST');
rae_test_assert(
  ($normalized_retry['id'] ?? '') === 'approved-attempt-2',
  'La consulta por referencia prefirió un intento rechazado sobre el aprobado.'
);

$due_retry = clone $retry_reservation;
$due_retry->payment_retry_until = '2026-08-04 12:03:00';
$GLOBALS['wpdb']->next_get_results_queue = [[$due_retry], []];
$GLOBALS['wpdb']->queries = [];
rae_test_assert(
  rae_wompi_expire_pending_reservations('2026-08-04 12:04:00') === 1,
  'La reserva no se liberó al finalizar la ventana de reintento.'
);
rae_test_assert(
  count(array_filter($GLOBALS['wpdb']->queries, function ($query) {
    return strpos($query, 'payment_retry_until = NULL') !== false;
  })) === 1,
  'La liberación del reintento no fue una transición atómica.'
);

if ($failures) {
  foreach ($failures as $failure) {
    fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
  }

  fwrite(STDERR, sprintf('%d de %d pruebas fallaron.%s', count($failures), $tests, PHP_EOL));
  exit(1);
}

echo sprintf('OK: %d pruebas superadas.%s', $tests, PHP_EOL);
