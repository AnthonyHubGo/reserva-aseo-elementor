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

  if (!$estado) {
    $estado = 'disponible';
  }

  if (!is_array($fechas_no_disponibles)) {
    $fechas_no_disponibles = [];
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

  <textarea
    id="rae_disponibilidad_motivo"
    name="rae_disponibilidad_motivo"
    rows="4"
    style="width: 100%;"
  ><?php echo esc_textarea($motivo); ?></textarea>

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
        <span>Disponibilidad</span>
        <select id="rae_calendario_filtro_disponibilidad">
          <option value="disponible">Disponible</option>
          <option value="no_disponible" selected>No disponible</option>
        </select>
      </label>
    </div>

    <div id="rae_calendario_disponibilidad" class="rae-admin-calendar-grid"></div>
  </div>

  <textarea
    hidden
    id="rae_fechas_no_disponibles"
    name="rae_fechas_no_disponibles"
  ><?php echo esc_textarea(implode("\n", $fechas_no_disponibles)); ?></textarea>

  <p class="description">Agrega un intervalo o haz clic en un día del calendario para cambiar su disponibilidad.</p>

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
    ? sanitize_textarea_field(wp_unslash($_POST['rae_disponibilidad_motivo']))
    : '';
  $fechas_no_disponibles = isset($_POST['rae_fechas_no_disponibles'])
    ? rae_sanitizar_fechas_no_disponibles(wp_unslash($_POST['rae_fechas_no_disponibles']))
    : [];

  update_post_meta($post_id, '_rae_disponibilidad_estado', $estado);

  if ($estado === 'no_disponible') {
    update_post_meta($post_id, '_rae_disponibilidad_motivo', $motivo);
  } else {
    delete_post_meta($post_id, '_rae_disponibilidad_motivo');
  }

  if (!empty($fechas_no_disponibles)) {
    update_post_meta($post_id, '_rae_fechas_no_disponibles', $fechas_no_disponibles);
  } else {
    delete_post_meta($post_id, '_rae_fechas_no_disponibles');
  }
});

function rae_personal_aseo_disponible($post_id, $fecha = '') {
  $estado = get_post_meta($post_id, '_rae_disponibilidad_estado', true);

  if ($estado && $estado !== 'disponible') {
    return false;
  }

  if ($fecha) {
    $fechas_no_disponibles = get_post_meta($post_id, '_rae_fechas_no_disponibles', true);

    if (is_array($fechas_no_disponibles) && in_array($fecha, $fechas_no_disponibles, true)) {
      return false;
    }
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
