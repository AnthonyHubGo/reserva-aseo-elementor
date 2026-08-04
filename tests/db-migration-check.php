<?php

if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "Este script solo puede ejecutarse desde CLI.\n");
  exit(1);
}

$config_path = $argv[1] ?? '';

if ($config_path === '' || !is_readable($config_path)) {
  fwrite(STDERR, "Uso: php tests/db-migration-check.php <ruta-wp-config.php> [puerto]\n");
  exit(1);
}

$config = file_get_contents($config_path);

function rae_migration_config_value($config, $name) {
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

$host_value = rae_migration_config_value($config, 'DB_HOST');
$host_parts = explode(':', $host_value, 2);
$host = $host_parts[0];
$port = isset($argv[2])
  ? (int) $argv[2]
  : (isset($host_parts[1]) ? (int) $host_parts[1] : 3306);
$database = rae_migration_config_value($config, 'DB_NAME');
$username = rae_migration_config_value($config, 'DB_USER');
$password = rae_migration_config_value($config, 'DB_PASSWORD');
$table = $prefix_match[1] . 'reservas_aseo';
$first = new mysqli($host, $username, $password, $database, $port);
$second = new mysqli($host, $username, $password, $database, $port);

if ($first->connect_errno || $second->connect_errno) {
  fwrite(STDERR, "No se pudo conectar a la base local.\n");
  exit(1);
}

$first->set_charset('utf8mb4');
$second->set_charset('utf8mb4');
$first->begin_transaction();
$lock_name = 'rae_migration_check_' . bin2hex(random_bytes(8));
$lock_held = false;

try {
  $insert = $first->prepare(
    "INSERT INTO `$table` (cliente_nombre, cliente_email, persona_id, fecha, jornada, estado) VALUES (?, ?, ?, ?, ?, ?)"
  );
  $name = 'Prueba de migración';
  $email = 'rae-migration-check@example.invalid';
  $person_id = 999999999;
  $date = '2099-12-30';
  $shift = 'manana';

  foreach (['cancelada', 'rechazada'] as $status) {
    $insert->bind_param('ssisss', $name, $email, $person_id, $date, $shift, $status);

    if (!$insert->execute()) {
      throw new RuntimeException('El índice de la tabla todavía impide reutilizar una jornada liberada.');
    }
  }

  $count = $first->query(
    "SELECT COUNT(*) AS total FROM `$table` WHERE cliente_email = 'rae-migration-check@example.invalid'"
  )->fetch_assoc();

  if ((int) $count['total'] !== 2) {
    throw new RuntimeException('No se conservaron las dos reservas históricas de prueba.');
  }

  $escaped_lock = $first->real_escape_string($lock_name);
  $acquired_first = (int) $first->query("SELECT GET_LOCK('$escaped_lock', 0) AS acquired")->fetch_assoc()['acquired'];
  $lock_held = $acquired_first === 1;
  $escaped_lock_second = $second->real_escape_string($lock_name);
  $acquired_second = (int) $second->query("SELECT GET_LOCK('$escaped_lock_second', 0) AS acquired")->fetch_assoc()['acquired'];

  if (!$lock_held || $acquired_second !== 0) {
    throw new RuntimeException('El bloqueo contra reservas simultáneas no funciona como se esperaba.');
  }

  echo json_encode([
    'historical_rows_with_same_slot' => 2,
    'concurrent_lock_blocks_second_connection' => true,
    'test_rows_persisted' => false,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
} finally {
  $first->rollback();

  if ($lock_held) {
    $escaped_lock = $first->real_escape_string($lock_name);
    $first->query("SELECT RELEASE_LOCK('$escaped_lock')");
  }

  $first->close();
  $second->close();
}
