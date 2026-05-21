<?php

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
  add_menu_page(
    'Reservas de Aseo',
    'Reservas de Aseo',
    'manage_options',
    'reservas-aseo',
    'rae_render_admin_reservas',
    'dashicons-calendar-alt',
    26
  );
});

/**
 * Maneja acciones antes de imprimir HTML.
 */
add_action('admin_init', function () {
  if (
    !isset($_GET['page'], $_GET['rae_action'], $_GET['reserva_id']) ||
    $_GET['page'] !== 'reservas-aseo' ||
    !current_user_can('manage_options')
  ) {
    return;
  }

  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $reserva_id = absint(wp_unslash($_GET['reserva_id']));
  $accion = sanitize_text_field(wp_unslash($_GET['rae_action']));
  $nuevo_estado = '';
  $redirect_args = ['page' => 'reservas-aseo'];
  $filtros_redirect = ['persona_id', 'fecha', 'jornada', 'estado'];

  foreach ($filtros_redirect as $filtro) {
    if (isset($_GET[$filtro]) && $_GET[$filtro] !== '') {
      $redirect_args[$filtro] = sanitize_text_field(wp_unslash($_GET[$filtro]));
    }
  }

  if ($accion === 'confirmar') {
    $nuevo_estado = 'confirmada';
  }

  if ($accion === 'cancelar') {
    $nuevo_estado = 'cancelada';
  }

  if ($nuevo_estado) {
    $actualizado = false;
    $reserva = $wpdb->get_row(
      $wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $reserva_id
      )
    );

    if ($reserva && $reserva->estado !== $nuevo_estado) {
      $actualizado = $wpdb->update(
        $table,
        ['estado' => $nuevo_estado],
        ['id' => $reserva_id],
        ['%s'],
        ['%d']
      );
    }

    if (!empty($actualizado) && is_email($reserva->cliente_email)) {
      $persona_nombre = get_the_title($reserva->persona_id);
      $jornada_nombre = rae_nombre_jornada($reserva->jornada);
      $estado_nombre = ucfirst($nuevo_estado);
      $asunto = 'Actualización de tu reserva';
      $mensaje = "Hola {$reserva->cliente_nombre},

Tu reserva ha cambiado de estado.

Detalles:
Persona seleccionada: {$persona_nombre}
Fecha: {$reserva->fecha}
Jornada: {$jornada_nombre}
Nuevo estado: {$estado_nombre}

Gracias.";

      wp_mail($reserva->cliente_email, $asunto, $mensaje);
    }
  }

  wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
  exit;
});

function rae_render_admin_reservas() {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $jornadas_permitidas = ['manana', 'tarde', 'completa'];
  $estados_permitidos = ['pendiente', 'confirmada', 'cancelada'];

  $persona_id = isset($_GET['persona_id']) ? absint(wp_unslash($_GET['persona_id'])) : 0;
  $fecha = isset($_GET['fecha']) ? sanitize_text_field(wp_unslash($_GET['fecha'])) : '';
  $jornada = isset($_GET['jornada']) ? sanitize_text_field(wp_unslash($_GET['jornada'])) : '';
  $estado = isset($_GET['estado']) ? sanitize_text_field(wp_unslash($_GET['estado'])) : '';

  if ($fecha && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !rae_fecha_valida($fecha))) {
    $fecha = '';
  }

  if ($jornada && !in_array($jornada, $jornadas_permitidas, true)) {
    $jornada = '';
  }

  if ($estado && !in_array($estado, $estados_permitidos, true)) {
    $estado = '';
  }

  $filtros_actuales = [];

  if ($persona_id) {
    $filtros_actuales['persona_id'] = $persona_id;
  }

  if ($fecha) {
    $filtros_actuales['fecha'] = $fecha;
  }

  if ($jornada) {
    $filtros_actuales['jornada'] = $jornada;
  }

  if ($estado) {
    $filtros_actuales['estado'] = $estado;
  }

  $where = "WHERE 1=1";
  $params = [];

  if ($persona_id) {
    $where .= " AND persona_id = %d";
    $params[] = $persona_id;
  }

  if ($fecha) {
    $where .= " AND fecha = %s";
    $params[] = $fecha;
  }

  if ($jornada) {
    $where .= " AND jornada = %s";
    $params[] = $jornada;
  }

  if ($estado) {
    $where .= " AND estado = %s";
    $params[] = $estado;
  }

  $sql = "SELECT * FROM $table $where ORDER BY fecha DESC, created_at DESC";

  if (!empty($params)) {
    $sql = call_user_func_array([$wpdb, 'prepare'], array_merge([$sql], $params));
  }

  $reservas = $wpdb->get_results($sql);

  $personal = get_posts([
    'post_type' => 'personal_aseo',
    'numberposts' => -1,
    'post_status' => 'publish',
  ]);
  ?>

  <div class="wrap">
    <h1>Reservas de Aseo</h1>

    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 20px 0;">
      <input type="hidden" name="page" value="reservas-aseo">

      <select name="persona_id">
        <option value="">Todas las personas</option>
        <?php foreach ($personal as $persona): ?>
          <option value="<?php echo esc_attr($persona->ID); ?>" <?php selected($persona_id, $persona->ID); ?>>
            <?php echo esc_html($persona->post_title); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="fecha" value="<?php echo esc_attr($fecha); ?>">

      <select name="jornada">
        <option value="">Todas las jornadas</option>
        <option value="manana" <?php selected($jornada, 'manana'); ?>>Mañana</option>
        <option value="tarde" <?php selected($jornada, 'tarde'); ?>>Tarde</option>
        <option value="completa" <?php selected($jornada, 'completa'); ?>>Día completa</option>
      </select>

      <select name="estado">
        <option value="">Todos los estados</option>
        <option value="pendiente" <?php selected($estado, 'pendiente'); ?>>Pendiente</option>
        <option value="confirmada" <?php selected($estado, 'confirmada'); ?>>Confirmada</option>
        <option value="cancelada" <?php selected($estado, 'cancelada'); ?>>Cancelada</option>
      </select>

      <button class="button button-primary">Filtrar</button>

      <a href="<?php echo esc_url(admin_url('admin.php?page=reservas-aseo')); ?>" class="button">
        Limpiar
      </a>
    </form>

    <table class="widefat striped">
      <thead>
        <tr>
          <th>Cliente</th>
          <th>Email</th>
          <th>Persona</th>
          <th>Fecha</th>
          <th>Jornada</th>
          <th>Estado</th>
          <th>Creada</th>
          <th>Acciones</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($reservas)): ?>
          <tr>
            <td colspan="8">No hay reservas con estos filtros.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($reservas as $reserva): ?>
            <tr>
              <td><?php echo esc_html($reserva->cliente_nombre); ?></td>
              <td><?php echo esc_html($reserva->cliente_email); ?></td>
              <td><?php echo esc_html(get_the_title($reserva->persona_id)); ?></td>
              <td><?php echo esc_html($reserva->fecha); ?></td>
              <td><?php echo esc_html(rae_nombre_jornada($reserva->jornada)); ?></td>
              <td><?php echo esc_html(ucfirst($reserva->estado)); ?></td>
              <td><?php echo esc_html($reserva->created_at); ?></td>
              <td>
                <?php if ($reserva->estado !== 'confirmada'): ?>
                  <a
                    class="button button-primary"
                    href="<?php echo esc_url(add_query_arg(array_merge($filtros_actuales, [
                      'page' => 'reservas-aseo',
                      'rae_action' => 'confirmar',
                      'reserva_id' => $reserva->id,
                    ]), admin_url('admin.php'))); ?>"
                  >
                    Confirmar
                  </a>
                <?php endif; ?>

                <?php if ($reserva->estado !== 'cancelada'): ?>
                  <a
                    class="button"
                    href="<?php echo esc_url(add_query_arg(array_merge($filtros_actuales, [
                      'page' => 'reservas-aseo',
                      'rae_action' => 'cancelar',
                      'reserva_id' => $reserva->id,
                    ]), admin_url('admin.php'))); ?>"
                  >
                    Cancelar
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
}

function rae_nombre_jornada($jornada) {
  $jornadas = [
    'manana' => 'Mañana',
    'tarde' => 'Tarde',
    'completa' => 'Día completa',
  ];

  return $jornadas[$jornada] ?? $jornada;
}

function rae_fecha_valida($fecha) {
  [$year, $month, $day] = array_map('intval', explode('-', $fecha));

  return checkdate($month, $day, $year);
}
