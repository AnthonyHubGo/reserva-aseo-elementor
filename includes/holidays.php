<?php

if (!defined('ABSPATH')) exit;

function rae_formatear_fecha(DateTimeInterface $fecha) {
  return $fecha->format('Y-m-d');
}

function rae_fecha_pascua_colombia($year) {
  $timezone = wp_timezone();
  $a = $year % 19;
  $b = intdiv($year, 100);
  $c = $year % 100;
  $d = intdiv($b, 4);
  $e = $b % 4;
  $f = intdiv($b + 8, 25);
  $g = intdiv($b - $f + 1, 3);
  $h = (19 * $a + $b - $d - $g + 15) % 30;
  $i = intdiv($c, 4);
  $k = $c % 4;
  $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
  $m = intdiv($a + 11 * $h + 22 * $l, 451);
  $month = intdiv($h + $l - 7 * $m + 114, 31);
  $day = (($h + $l - 7 * $m + 114) % 31) + 1;

  return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $timezone);
}

function rae_sumar_dias_fecha(DateTimeInterface $fecha, $dias) {
  return DateTimeImmutable::createFromInterface($fecha)->modify(($dias >= 0 ? '+' : '') . (int) $dias . ' days');
}

function rae_mover_al_siguiente_lunes(DateTimeInterface $fecha) {
  $fecha = DateTimeImmutable::createFromInterface($fecha);
  $dia_semana = (int) $fecha->format('N');

  if ($dia_semana !== 1) {
    $fecha = $fecha->modify('+' . (8 - $dia_semana) . ' days');
  }

  return $fecha;
}

function rae_festivos_colombia($year) {
  $year = absint($year);

  if (!$year) {
    return [];
  }

  $timezone = wp_timezone();
  $pascua = rae_fecha_pascua_colombia($year);
  $festivos_fijos = [
    'Año Nuevo' => new DateTimeImmutable("$year-01-01", $timezone),
    'Día del Trabajo' => new DateTimeImmutable("$year-05-01", $timezone),
    'Independencia de Colombia' => new DateTimeImmutable("$year-07-20", $timezone),
    'Batalla de Boyacá' => new DateTimeImmutable("$year-08-07", $timezone),
    'Inmaculada Concepción' => new DateTimeImmutable("$year-12-08", $timezone),
    'Navidad' => new DateTimeImmutable("$year-12-25", $timezone),
    'Jueves Santo' => rae_sumar_dias_fecha($pascua, -3),
    'Viernes Santo' => rae_sumar_dias_fecha($pascua, -2),
  ];
  $festivos_trasladables = [
    'Reyes Magos' => new DateTimeImmutable("$year-01-06", $timezone),
    'San José' => new DateTimeImmutable("$year-03-19", $timezone),
    'San Pedro y San Pablo' => new DateTimeImmutable("$year-06-29", $timezone),
    'Asunción de la Virgen' => new DateTimeImmutable("$year-08-15", $timezone),
    'Día de la Raza' => new DateTimeImmutable("$year-10-12", $timezone),
    'Todos los Santos' => new DateTimeImmutable("$year-11-01", $timezone),
    'Independencia de Cartagena' => new DateTimeImmutable("$year-11-11", $timezone),
    'Ascensión del Señor' => rae_sumar_dias_fecha($pascua, 39),
    'Corpus Christi' => rae_sumar_dias_fecha($pascua, 60),
    'Sagrado Corazón' => rae_sumar_dias_fecha($pascua, 68),
  ];
  $festivos = [];

  foreach ($festivos_fijos as $nombre => $fecha) {
    $festivos[rae_formatear_fecha($fecha)] = $nombre;
  }

  foreach ($festivos_trasladables as $nombre => $fecha) {
    $festivos[rae_formatear_fecha(rae_mover_al_siguiente_lunes($fecha))] = $nombre;
  }

  ksort($festivos);

  return $festivos;
}

function rae_es_festivo_colombia($fecha) {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $fecha)) {
    return false;
  }

  $year = (int) substr($fecha, 0, 4);
  $festivos = rae_festivos_colombia($year);

  return isset($festivos[$fecha]);
}

function rae_festivos_colombia_rango($year_inicio, $year_fin) {
  $festivos = [];

  for ($year = absint($year_inicio); $year <= absint($year_fin); $year++) {
    $festivos += rae_festivos_colombia($year);
  }

  ksort($festivos);

  return $festivos;
}
