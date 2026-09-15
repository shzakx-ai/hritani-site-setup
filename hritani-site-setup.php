<?php
/**
 * Plugin Name: HRITANI Site Setup
 * Description: يُثبّت موقع HRITANI GROUP كاملاً بنقرة واحدة — ينشئ الصفحات، القائمة، الإعدادات، ويعيّن الثيم تلقائياً.
 * Version:     1.0.0
 * Author:      Hermes (HRITANI GROUP)
 * Text Domain: hritani-setup
 */

if (!defined('ABSPATH')) exit;

/**
 * 1) الإعدادات الأساسية
 */
function hritani_setup_site_options() {
    update_option('blogname', 'عالم البخار — مجموعة حريتاني');
    update_option('blogdescription', 'تجهيزات متكاملة لخطوط البخار والمعامل الصناعية');
    update_option('timezone_string', 'Asia/Damascus');
    update_option('start_of_week', 6); // السبت
    update_option('template', 'hritani');
    update_option('stylesheet', 'hritani');
    update_option('hritani_installed_via', 'plugin-activation');
    update_option('hritani_install_time', current_time('mysql'));
}

/**
 * 2) إنشاء الصفحات
 */
function hritani_create_pages() {
    $pages = array(
        array('الرئيسية',     '', 'front-page', null),
        array('منتجاتنا',     'تجهيزات متكاملة لخطوط البخار.', 'products', 'منتجاتنا'),
        array('من نحن',       'مجموعة حريتاني — خبرة في التجهيزات الصناعية.', 'about-us', 'من نحن'),
        array('تواصل معنا',   'سوريا — حلب، السليمانية، شارع الحاج حبيب.', 'contact', 'تواصل معنا'),
    );

    foreach ($pages as $p) {
        $existing = get_page_by_path($p[2]);
        if ($existing) continue;
        $pid = wp_insert_post(array(
            'post_title'    => $p[0],
            'post_content'  => $p[1],
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => $p[2],
        ));
        if (!is_wp_error($pid)) {
            update_post_meta($pid, '_wp_page_template', 'default');
        }
    }

    // الصفحة الرئيسية = الواجهة
    $front = get_page_by_path('front-page');
    if ($front) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $front->ID);
    }
}

/**
 * إيجاد صفحة بالعنوان باستخدام WP_Query (بديل حديث عن get_page_by_title)
 */
function hritani_get_page_by_title($title) {
    $q = new WP_Query(array(
        'post_type'      => 'page',
        'title'          => $title,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ));
    return $q->have_posts() ? $q->posts[0] : null;
}

/**
 * 3) القائمة الرئيسية
 */
function hritani_setup_menu() {
    $menu_name = 'القائمة الرئيسية';
    $menu = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu ? $menu->term_id : wp_create_nav_menu($menu_name);

    $items = array('الرئيسية', 'منتجاتنا', 'من نحن', 'تواصل معنا');
    foreach ($items as $item) {
        $page = hritani_get_page_by_title($item); // WP_Query بديل غير مُهجّر
        if (!$page) continue;
        $exists = false;
        $menu_items = wp_get_nav_menu_items($menu_id);
        if ($menu_items) {
            foreach ($menu_items as $mi) {
                if ($mi->object_id == $page->ID) { $exists = true; break; }
            }
        }
        if (!$exists) {
            wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title'     => $item,
                'menu-item-object'    => 'page',
                'menu-item-object-id' => $page->ID,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            ));
        }
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) $locations = array();
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

/**
 * 4) تثبيت الثيم إذا لم يكن موجوداً (من ZIP مضمّن في البلوجن)
 */
function hritani_install_theme() {
    if (!function_exists('WP_Filesystem')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('wp_install_theme')) {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
    }
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $zip = plugin_dir_path(__FILE__) . 'themes/hritani.zip';
    if (!file_exists($zip)) {
        return new WP_Error('no_zip', 'لا يوجد hritani.zip داخل البلوجن');
    }

    // إذا كان الثيم موجوداً مسبقاً — فعّله فقط
    $installed_before = wp_get_themes();
    if (isset($installed_before['hritani'])) {
        switch_theme('hritani');
        return true;  // موجود — لا حاجة لتثبيت
    }

    $upgrader = new Theme_Upgrader(new WP_Upgrader_Skin());
    $result = $upgrader->install($zip);
    if (is_wp_error($result)) return $result;

    // تفعيل الثيم
    $installed = wp_get_themes();
    if (isset($installed['hritani'])) {
        switch_theme('hritani');
    }

    // تعيين الصفحة الرئيسية كواجهة
    $front = get_page_by_path('front-page');
    if ($front) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $front->ID);
    }

    return true;
}

/**
 * نقطة الدخول — تفعيل البلوجن
 */
register_activation_hook(__FILE__, function () {
    hritani_setup_site_options();
    $theme_install = hritani_install_theme();  // يثبّت الثيم أولاً
    hritani_create_pages();
    hritani_setup_menu();
    set_transient('hritani_setup_result', $theme_install === true ? 'ok' : (is_wp_error($theme_install) ? $theme_install->get_error_message() : '?'), 60);
});

add_action('admin_notices', function () {
    $r = get_transient('hritani_setup_result');
    if ($r) {
        delete_transient('hritani_setup_result');
        $ok = ($r === 'ok');
        echo '<div class="notice ' . ($ok ? 'notice-success' : 'notice-error') . '"><p>' .
             ($ok ? '✅ موقع HRITANI GROUP جاهز! الصفحات + القائمة + الثيم + الإعدادات كلها أُنشئت.' : '⚠️ ' . esc_html($r)) .
             '</p></div>';
    }
});