<?php

if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
  exit(1);
}

$config_path = $argv[1] ?? '';

if ($config_path === '' || !is_readable($config_path)) {
  fwrite(STDERR, "Uso: php tests/db-snapshot.php <ruta-wp-config.php>\n");
  exit(1);
}

$config = file_get_contents($config_path);

function rae_snapshot_config_value($config, $name) {
  $pattern = '/define\(\s*[\'\"]' . preg_quote($name, '/') . '[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/';

  if (!preg_match($pattern, $config, $matches)) {
    throw new RuntimeException("No se encontró $name en wp-config.php.");
  }

  return $matches[1];
}

if (!preg_match('/\$table_prefix\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $config, $prefix_match)) {
  fwrite(STDERR, "No se encontró table_prefix en wp-config.php.\n");
  exit(1);
}

$host_value = rae_snapshot_config_value($config, 'DB_HOST');
$host_parts = explode(':', $host_value, 2);
$host = $host_parts[0];
$port = isset($argv[2])
  ? (int) $argv[2]
  : (isset($host_parts[1]) ? (int) $host_parts[1] : 3306);
$database = rae_snapshot_config_value($config, 'DB_NAME');
$username = rae_snapshot_config_value($config, 'DB_USER');
$password = rae_snapshot_config_value($config, 'DB_PASSWORD');
$prefix = $prefix_match[1];
$table = $prefix . 'reservas_aseo';
$attempts_table = $prefix . 'reservas_aseo_pagos';
$options_table = $prefix . 'options';
$connection = new mysqli($host, $username, $password, $database, $port);

if ($connection->connect_errno) {
  fwrite(STDERR, "No se pudo conectar a la base local.\n");
  exit(1);
}

$connection->set_charset('utf8mb4');
$count_result = $connection->query("SELECT COUNT(*) AS total FROM `$table`");
$count = $count_result ? (int) $count_result->fetch_assoc()['total'] : 0;
$attempts_count_result = $connection->query("SELECT COUNT(*) AS total FROM `$attempts_table`");
$attempts_count = $attempts_count_result ? (int) $attempts_count_result->fetch_assoc()['total'] : null;
$retry_column_result = $connection->query("SHOW COLUMNS FROM `$table` LIKE 'payment_retry_until'");
$has_retry_column = $retry_column_result && $retry_column_result->num_rows === 1;
$version_result = $connection->query(
  "SELECT option_value FROM `$options_table` WHERE option_name = 'rae_db_version' LIMIT 1"
);
$version_row = $version_result ? $version_result->fetch_assoc() : null;
$index_result = $connection->query("SHOW INDEX FROM `$table`");
$indexes = [];

while ($index_result && ($index = $index_result->fetch_assoc())) {
  $indexes[] = [
    'name' => $index['Key_name'],
    'non_unique' => (int) $index['Non_unique'],
    'column' => $index['Column_name'],
    'sequence' => (int) $index['Seq_in_index'],
  ];
}

$latest_result = $connection->query(
  "SELECT id, persona_id, fecha, jornada, estado, payment_status, payment_gateway, payment_amount_cop, payment_reference, wompi_transaction_id, payment_retry_until, created_at FROM `$table` ORDER BY id DESC LIMIT 1"
);
$latest = $latest_result ? $latest_result->fetch_assoc() : null;
$recent_result = $connection->query(
  "SELECT id, persona_id, fecha, jornada, estado, payment_status, payment_gateway, payment_amount_cop, payment_reference, wompi_transaction_id, payment_retry_until, created_at FROM `$table` ORDER BY id DESC LIMIT 5"
);
$recent = [];

while ($recent_result && ($reservation = $recent_result->fetch_assoc())) {
  $recent[] = $reservation;
}

echo json_encode([
  'db_version' => $version_row['option_value'] ?? '',
  'reservations' => $count,
  'payment_attempts' => $attempts_count,
  'has_payment_retry_until' => $has_retry_column,
  'latest_reservation' => $latest,
  'recent_reservations' => $recent,
  'indexes' => $indexes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$connection->close();
