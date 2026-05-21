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