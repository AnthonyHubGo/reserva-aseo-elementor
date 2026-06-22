<?php

if (!defined('ABSPATH')) exit;

add_action('init', function () {

  register_post_type('personal_aseo', [
    'labels' => [
      'name' => 'Personal de Aseo',
      'singular_name' => 'Persona de Aseo',
      'add_new' => 'Agregar persona',
      'add_new_item' => 'Agregar nueva persona',
      'edit_item' => 'Editar persona',
      'new_item' => 'Nueva persona',
      'view_item' => 'Ver persona',
      'search_items' => 'Buscar personal',
      'not_found' => 'No hay personal registrado',
    ],
    'public' => false,
    'show_ui' => true,
    'menu_icon' => 'dashicons-admin-users',
    'supports' => ['title', 'thumbnail'],
    'capability_type' => ['personal_aseo', 'personal_aseos'],
    'map_meta_cap' => true,
    'capabilities' => [
      'edit_post' => 'edit_personal_aseo',
      'read_post' => 'read_personal_aseo',
      'delete_post' => 'delete_personal_aseo',
      'edit_posts' => 'edit_personal_aseos',
      'edit_others_posts' => 'edit_others_personal_aseos',
      'publish_posts' => 'publish_personal_aseos',
      'read_private_posts' => 'read_private_personal_aseos',
      'delete_posts' => 'delete_personal_aseos',
      'delete_others_posts' => 'delete_others_personal_aseos',
      'delete_published_posts' => 'delete_published_personal_aseos',
      'delete_private_posts' => 'delete_private_personal_aseos',
      'edit_published_posts' => 'edit_published_personal_aseos',
      'edit_private_posts' => 'edit_private_personal_aseos',
      'create_posts' => 'edit_personal_aseos',
    ],
  ]);

});

add_action('add_meta_boxes', function () {
  add_meta_box(
    'rae_disponibilidad_personal',
    'Disponibilidad',
    'rae_render_disponibilidad_personal_metabox',
    'personal_aseo',
    'normal',
    'default'
  );
});

add_action('admin_enqueue_scripts', function ($hook) {
  if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
    return;
  }

  $screen = get_current_screen();

  if (!$screen || $screen->post_type !== 'personal_aseo') {
    return;
  }

  wp_enqueue_style(
    'rae-admin-css',
    RAE_URL . 'assets/css/admin.css',
    [],
    '1.0.0'
  );

  wp_enqueue_script(
    'rae-admin-js',
    RAE_URL . 'assets/js/admin.js',
    [],
    '1.0.0',
    true
  );
});

function rae_render_disponibilidad_personal_metabox($post) {
  $estado = get_post_meta($post->ID, '_rae_disponibilidad_estado', true);
  $motivo = get_post_meta($post->ID, '_rae_disponibilidad_motivo', true);
  $fechas_no_disponibles = get_post_meta($post->ID, '_rae_fechas_no_disponibles', true);
  $motivos_por_fecha = get_post_meta($post->ID, '_rae_motivos_no_disponibilidad_fechas', true);
  $motivos_no_disponibilidad = rae_motivos_no_disponibilidad();
  $ocupaciones = rae_obtener_ocupaciones_personal($post->ID);

  if (!$estado) {
    $estado = 'no_disponible';
  }

  if (!$motivo || !array_key_exists($motivo, $motivos_no_disponibilidad)) {
    $motivo = 'vacaciones';
  }

  if (!is_array($fechas_no_disponibles)) {
    $fechas_no_disponibles = [];
  }

  if (!is_array($motivos_por_fecha)) {
    $motivos_por_fecha = [];
  }

  wp_nonce_field('rae_guardar_disponibilidad_personal', 'rae_disponibilidad_personal_nonce');
  ?>

  <p>
    <label for="rae_disponibilidad_estado"><strong>Estado de disponibilidad</strong></label>
  </p>

  <select id="rae_disponibilidad_estado" name="rae_disponibilidad_estado" style="width: 100%;">
    <option value="disponible" <?php selected($estado, 'disponible'); ?>>Disponible</option>
    <option value="no_disponible" <?php selected($estado, 'no_disponible'); ?>>No disponible</option>
  </select>

  <p>
    <label for="rae_disponibilidad_motivo"><strong>Motivo de no disponibilidad</strong></label>
  </p>

  <div class="rae-admin-reason-options" id="rae_disponibilidad_motivo">
    <?php foreach ($motivos_no_disponibilidad as $valor => $etiqueta): ?>
      <label>
        <input
          type="radio"
          name="rae_disponibilidad_motivo"
          value="<?php echo esc_attr($valor); ?>"
          <?php checked($motivo, $valor); ?>
        >
        <span><?php echo esc_html($etiqueta); ?></span>
      </label>
    <?php endforeach; ?>
  </div>

  <p>
    <label for="rae_fechas_no_disponibles"><strong>Fechas no disponibles</strong></label>
  </p>

  <div class="rae-admin-date-picker">
    <label>
      <span>Desde</span>
      <input type="date" id="rae_fecha_no_disponible_inicio">
    </label>

    <label>
      <span>Hasta</span>
      <input type="date" id="rae_fecha_no_disponible_fin">
    </label>

    <button type="button" class="button" id="rae_agregar_fecha_no_disponible">Agregar intervalo</button>
  </div>

  <div class="rae-admin-calendar">
    <div class="rae-admin-calendar-toolbar">
      <button type="button" class="button" id="rae_calendario_mes_anterior">Anterior</button>
      <strong id="rae_calendario_mes_actual"></strong>
      <button type="button" class="button" id="rae_calendario_mes_siguiente">Siguiente</button>

      <label>
        <span>No disponibilidad</span>
        <select id="rae_calendario_filtro_motivo">
          <?php foreach ($motivos_no_disponibilidad as $valor => $etiqueta): ?>
            <option value="<?php echo esc_attr($valor); ?>"><?php echo esc_html($etiqueta); ?></option>
          <?php endforeach; ?>
          <option value="todos" selected>Todos los motivos</option>
        </select>
      </label>
    </div>

    <div id="rae_calendario_disponibilidad" class="rae-admin-calendar-grid"></div>
  </div>

  <input
    type="hidden"
    id="rae_fechas_no_disponibles"
    name="rae_fechas_no_disponibles"
    value="<?php echo esc_attr(implode("\n", $fechas_no_disponibles)); ?>"
  >

  <input
    type="hidden"
    id="rae_motivos_no_disponibilidad_fechas"
    name="rae_motivos_no_disponibilidad_fechas"
    value="<?php echo esc_attr(wp_json_encode($motivos_por_fecha)); ?>"
  >

  <input
    type="hidden"
    id="rae_ocupaciones_personal"
    value="<?php echo esc_attr(wp_json_encode($ocupaciones)); ?>"
  >

  <p class="description">Agrega un intervalo o haz clic en un día del calendario para cambiar su disponibilidad. Para conservar los cambios, haz clic en Actualizar.</p>

  <?php
}

add_action('save_post_personal_aseo', function ($post_id) {
  if (
    !isset($_POST['rae_disponibilidad_personal_nonce']) ||
    !wp_verify_nonce(
      sanitize_text_field(wp_unslash($_POST['rae_disponibilidad_personal_nonce'])),
      'rae_guardar_disponibilidad_personal'
    )
  ) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $estados_permitidos = ['disponible', 'no_disponible'];
  $estado = isset($_POST['rae_disponibilidad_estado'])
    ? sanitize_text_field(wp_unslash($_POST['rae_disponibilidad_estado']))
    : 'disponible';

  if (!in_array($estado, $estados_permitidos, true)) {
    $estado = 'disponible';
  }

  $motivo = isset($_POST['rae_disponibilidad_motivo'])
    ? sanitize_key(wp_unslash($_POST['rae_disponibilidad_motivo']))
    : 'vacaciones';

  if (!array_key_exists($motivo, rae_motivos_no_disponibilidad())) {
    $motivo = 'vacaciones';
  }

  $fechas_no_disponibles = isset($_POST['rae_fechas_no_disponibles'])
    ? rae_sanitizar_fechas_no_disponibles(wp_unslash($_POST['rae_fechas_no_disponibles']))
    : [];
  $motivos_por_fecha = isset($_POST['rae_motivos_no_disponibilidad_fechas'])
    ? rae_sanitizar_motivos_no_disponibilidad_fechas(wp_unslash($_POST['rae_motivos_no_disponibilidad_fechas']), $fechas_no_disponibles, $motivo)
    : [];

  update_post_meta($post_id, '_rae_disponibilidad_estado', $estado);

  if ($estado === 'no_disponible') {
    update_post_meta($post_id, '_rae_disponibilidad_motivo', $motivo);
  } else {
    delete_post_meta($post_id, '_rae_disponibilidad_motivo');
  }

  if (!empty($fechas_no_disponibles)) {
    update_post_meta($post_id, '_rae_fechas_no_disponibles', $fechas_no_disponibles);
    update_post_meta($post_id, '_rae_motivos_no_disponibilidad_fechas', $motivos_por_fecha);
  } else {
    delete_post_meta($post_id, '_rae_fechas_no_disponibles');
    delete_post_meta($post_id, '_rae_motivos_no_disponibilidad_fechas');
  }
});

function rae_personal_aseo_disponible($post_id, $fecha = '') {
  $estado = get_post_meta($post_id, '_rae_disponibilidad_estado', true);
  $fechas_no_disponibles = get_post_meta($post_id, '_rae_fechas_no_disponibles', true);

  if (!is_array($fechas_no_disponibles)) {
    $fechas_no_disponibles = [];
  }

  if ($estado === 'no_disponible' && empty($fechas_no_disponibles)) {
    return false;
  }

  if ($fecha && in_array($fecha, $fechas_no_disponibles, true)) {
    return false;
  }

  return true;
}

function rae_sanitizar_fechas_no_disponibles($fechas) {
  $lineas = preg_split('/\r\n|\r|\n/', sanitize_textarea_field($fechas));
  $fechas_validas = [];

  foreach ($lineas as $fecha) {
    $fecha = trim($fecha);

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
      continue;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $fecha));

    if (checkdate($month, $day, $year)) {
      $fechas_validas[] = $fecha;
    }
  }

  return array_values(array_unique($fechas_validas));
}

function rae_motivos_no_disponibilidad() {
  return [
    'vacaciones' => 'Vacaciones',
    'incapacidad' => 'Incapacidad',
    'licencia_maternidad' => 'Licencia de Maternidad',
  ];
}

function rae_sanitizar_motivos_no_disponibilidad_fechas($motivos_json, $fechas_validas, $motivo_default) {
  $motivos = json_decode(sanitize_textarea_field($motivos_json), true);
  $motivos_validos = rae_motivos_no_disponibilidad();
  $motivos_por_fecha = [];

  if (!array_key_exists($motivo_default, $motivos_validos)) {
    $motivo_default = 'vacaciones';
  }

  if (!is_array($motivos)) {
    $motivos = [];
  }

  foreach ($fechas_validas as $fecha) {
    $motivo = isset($motivos[$fecha]) ? sanitize_key($motivos[$fecha]) : $motivo_default;

    if (!array_key_exists($motivo, $motivos_validos)) {
      $motivo = $motivo_default;
    }

    $motivos_por_fecha[$fecha] = $motivo;
  }

  return $motivos_por_fecha;
}

function rae_obtener_ocupaciones_personal($post_id) {
  global $wpdb;

  $table = $wpdb->prefix . 'reservas_aseo';
  $estados_bloqueantes = ['pendiente_pago', 'pagado', 'confirmada'];
  $placeholders_estados = implode(', ', array_fill(0, count($estados_bloqueantes), '%s'));
  $reservas = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT fecha, jornada FROM $table WHERE persona_id = %d AND estado IN ($placeholders_estados)",
      array_merge([$post_id], $estados_bloqueantes)
    )
  );
  $ocupaciones = [];

  foreach ($reservas as $reserva) {
    if (!isset($ocupaciones[$reserva->fecha])) {
      $ocupaciones[$reserva->fecha] = [];
    }

    $ocupaciones[$reserva->fecha][] = $reserva->jornada;
  }

  return array_map('array_values', $ocupaciones);
}
