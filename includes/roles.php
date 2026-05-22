<?php

if (!defined('ABSPATH')) exit;

function rae_reservas_capabilities() {
  return [
    'rae_view_reservas',
    'rae_manage_reservas',
  ];
}

function rae_personal_aseo_capabilities() {
  return [
    'edit_personal_aseo',
    'read_personal_aseo',
    'delete_personal_aseo',
    'edit_personal_aseos',
    'edit_others_personal_aseos',
    'publish_personal_aseos',
    'read_private_personal_aseos',
    'delete_personal_aseos',
    'delete_others_personal_aseos',
    'delete_published_personal_aseos',
    'delete_private_personal_aseos',
    'edit_published_personal_aseos',
    'edit_private_personal_aseos',
  ];
}

function rae_plugin_capabilities() {
  return array_merge(
    rae_reservas_capabilities(),
    rae_personal_aseo_capabilities()
  );
}

function rae_coordinador_capabilities_no_permitidas() {
  return [
    'activate_plugins',
    'create_users',
    'delete_plugins',
    'delete_users',
    'edit_dashboard',
    'edit_files',
    'edit_plugins',
    'edit_theme_options',
    'edit_themes',
    'edit_users',
    'export',
    'import',
    'install_plugins',
    'install_themes',
    'list_users',
    'manage_categories',
    'manage_options',
    'promote_users',
    'remove_users',
    'switch_themes',
    'update_core',
    'update_plugins',
    'update_themes',
  ];
}

function rae_agregar_capabilities_a_rol($role_name, $capabilities, $extra_capabilities = []) {
  $role = get_role($role_name);

  if (!$role) {
    return;
  }

  foreach (array_merge($capabilities, $extra_capabilities) as $capability) {
    $role->add_cap($capability);
  }
}

function rae_remover_capabilities_de_rol($role_name, $capabilities) {
  $role = get_role($role_name);

  if (!$role) {
    return;
  }

  foreach ($capabilities as $capability) {
    $role->remove_cap($capability);
  }
}

function rae_registrar_roles_y_capabilities() {
  $coordinador_capabilities = [
    'read' => true,
    'upload_files' => true,
  ];

  foreach (rae_plugin_capabilities() as $capability) {
    $coordinador_capabilities[$capability] = true;
  }

  if (!get_role('rae_coordinador_reservas')) {
    add_role(
      'rae_coordinador_reservas',
      'Coordinador de Reservas',
      $coordinador_capabilities
    );
  } else {
    rae_agregar_capabilities_a_rol(
      'rae_coordinador_reservas',
      rae_plugin_capabilities(),
      ['read', 'upload_files']
    );
  }

  rae_remover_capabilities_de_rol(
    'rae_coordinador_reservas',
    rae_coordinador_capabilities_no_permitidas()
  );

  rae_agregar_capabilities_a_rol('administrator', rae_plugin_capabilities());
}

function rae_usuario_es_coordinador_reservas($user = null) {
  $user = $user instanceof WP_User ? $user : wp_get_current_user();

  return $user && in_array('rae_coordinador_reservas', (array) $user->roles, true);
}

function rae_admin_url_reservas() {
  return admin_url('admin.php?page=reservas-aseo');
}

add_filter('login_redirect', function ($redirect_to, $requested_redirect_to, $user) {
  if ($user instanceof WP_User && rae_usuario_es_coordinador_reservas($user)) {
    return rae_admin_url_reservas();
  }

  return $redirect_to;
}, 10, 3);

add_action('admin_init', function () {
  if (!rae_usuario_es_coordinador_reservas()) {
    return;
  }

  global $pagenow;

  $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
  $paginas_bloqueadas = [
    'users.php',
    'user-new.php',
    'plugins.php',
    'plugin-install.php',
    'plugin-editor.php',
    'options-general.php',
    'options-writing.php',
    'options-reading.php',
    'options-discussion.php',
    'options-media.php',
    'options-permalink.php',
    'options-privacy.php',
    'themes.php',
    'theme-install.php',
    'theme-editor.php',
  ];

  if ($page === 'rae-reservas') {
    wp_safe_redirect(rae_admin_url_reservas());
    exit;
  }

  if ($pagenow === 'index.php' || in_array($pagenow, $paginas_bloqueadas, true) || $page === 'reservas-aseo-configuracion') {
    wp_safe_redirect(rae_admin_url_reservas());
    exit;
  }
});

add_action('admin_menu', function () {
  if (!rae_usuario_es_coordinador_reservas()) {
    return;
  }

  remove_menu_page('index.php');
  remove_menu_page('upload.php');
  remove_menu_page('users.php');
  remove_menu_page('plugins.php');
  remove_menu_page('options-general.php');
  remove_menu_page('tools.php');
  remove_menu_page('themes.php');
}, 999);
