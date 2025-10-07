<?php
/**
 * Plugin Name: Gestión de Usuarios
 * Description: Shortcode [gestion_usuarios_casos] para crear usuarios (WP + tabla interna) desde el front. Funciona con login propio (sin sesión WP).
 * Version: 1.2.0
 * Author: Tu Equipo
 */

if (!defined('ABSPATH')) exit;

class GUC_Plugin {

    /** Ajusta si usas otro rol o dominio ficticio para el correo */
    const DB_VERSION       = '1.0.0';
    const TABLE            = 'guc_users';
    const GUC_DEFAULT_ROLE = 'customer';       // cambia a 'cliente' si tu rol personalizado se llama así (slug)
    const GUC_EMAIL_DOMAIN = 'tarjrd.local';   // dominio ficticio para generar emails únicos

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);

        add_shortcode('gestion_usuarios_casos', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts',        [$this, 'enqueue_assets']);

        // AJAX: logged-in y no-logged (nopriv) para funcionar con logins de membresía
        add_action('wp_ajax_guc_create',        [$this, 'ajax_create']);
        add_action('wp_ajax_guc_list',          [$this, 'ajax_list']);
        add_action('wp_ajax_guc_delete',        [$this, 'ajax_delete']);
        add_action('wp_ajax_guc_update',        [$this, 'ajax_update']);

        add_action('wp_ajax_nopriv_guc_create', [$this, 'ajax_create']);
        add_action('wp_ajax_nopriv_guc_list',   [$this, 'ajax_list']);
        add_action('wp_ajax_nopriv_guc_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_nopriv_guc_update', [$this, 'ajax_update']);

        // evita caché en /panel-administrador (recomendado)
        add_action('wp',                         [$this, 'nocache_panel_page']);
    }

    /** Crear tabla interna al activar */
    public function activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(16) NOT NULL,
            password_plain VARCHAR(64) NOT NULL,
            entity VARCHAR(191) NULL,
            expediente VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY username (username)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        add_option('guc_db_version', self::DB_VERSION);
    }

    /** Evitar cache en /panel-administrador */
    public function nocache_panel_page() {
        if (function_exists('is_page') && is_page('panel-administrador')) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
            nocache_headers();
            if (!headers_sent()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
        }
    }

    /** Cargar CSS y JS sólo donde se usa el shortcode */
    public function enqueue_assets() {
        if (!is_singular()) return;
        global $post;
        if (!$post) return;
        if (has_shortcode($post->post_content, 'gestion_usuarios_casos')) {
            wp_register_style('guc-css', false);
            wp_enqueue_style('guc-css');
            $css = "
            .guc-wrap{font-family: Inter, Roboto, Arial, sans-serif; color:#2b2b2b}
            .guc-card{background:#fff;border-radius:14px;box-shadow:0 6px 20px rgba(0,0,0,.06);padding:18px;border:1px solid #ece7df}
            .guc-title{font-weight:800;font-size:22px;margin:0 0 14px}
            .guc-btn{border:0;border-radius:10px;padding:10px 14px;cursor:pointer;font-weight:600}
            .guc-btn:disabled{opacity:.6;cursor:not-allowed}
            .guc-btn-primary{background:#bfa27b;color:#fff}
            .guc-btn-outline{background:#fff;border:2px solid #bfa27b;color:#5a4b35}
            .guc-table{width:100%;border-collapse:separate;border-spacing:0 10px}
            .guc-table thead th{font-size:13px;text-transform:uppercase;color:#6e6b66;text-align:left;border-bottom:1px solid #eee;padding:8px 12px}
            .guc-table tbody tr{background:#fff;border:1px solid #eee;border-radius:12px}
            .guc-table tbody td{padding:12px}
            .guc-badge-green{background:#2e7d32;color:#fff;padding:6px 10px;border-radius:999px;font-size:12px}
            .guc-actions .guc-icon{border:0;background:#f5f2ec;padding:8px;border-radius:10px;margin-right:6px;cursor:pointer}
            .guc-actions .guc-view{background:#9e9e9e;color:#fff}
            .guc-actions .guc-edit{background:#ffa000;color:#fff}
            .guc-actions .guc-del{background:#e53935;color:#fff}
            .guc-modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:9999}
            .guc-modal{width:min(680px,92vw);background:#fff;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.2);overflow:hidden}
            .guc-modal-header{display:flex;justify-content:space-between;align-items:center;padding:18px 20px;border-bottom:1px solid #eee;background:#4b3a31;color:#fff}
            .guc-modal-title{font-size:20px;font-weight:800}
            .guc-close{background:transparent;border:0;font-size:20px;cursor:pointer;color:#fff}
            .guc-modal-body{padding:20px}
            .guc-field{margin-bottom:14px}
            .guc-label{display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#4b3a31}
            .guc-input{width:100%;padding:12px 12px;border-radius:10px;border:1px solid #e6e0d6;background:#fbfaf8}
            .guc-input[disabled]{background:#f1f0ee;color:#8c8c8c}
            .guc-modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:16px 20px;border-top:1px solid #eee}
            .guc-helper{font-size:12px;color:#9c8b74;margin-top:-6px;margin-bottom:10px}
            ";
            wp_add_inline_style('guc-css', $css);

            wp_enqueue_script('guc-js', plugin_dir_url(__FILE__) . 'js.js', [], '1.2.0', true);
            wp_localize_script('guc-js', 'GUC', [
                'ajax'   => admin_url('admin-ajax.php'),
                'nonce'  => wp_create_nonce('guc_nonce'),
                'capErr' => __('No autorizado', 'guc'),
            ]);
        }
    }

    /** Shortcode (sin exigir sesión WP: tu login ya protege la página) */
    public function shortcode($atts) {
        $this->maybe_create_table();

        ob_start(); ?>
        <div class="guc-wrap">
            <div class="guc-card" style="margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
                <h3 class="guc-title" style="margin:0">Gestión de Usuarios</h3>
                <button class="guc-btn guc-btn-primary" id="guc-open-modal">
                    <span style="margin-right:6px">👤</span> Crear usuario
                </button>
            </div>

            <div class="guc-card">
                <table class="guc-table" id="guc-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Contraseña</th>
                            <th>Entidad</th>
                            <th>Expediente</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="guc-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- Modal: Crear -->
        <div class="guc-modal-mask" id="guc-mask" aria-hidden="true">
            <div class="guc-modal" role="dialog" aria-modal="true" aria-labelledby="guc-modal-title">
                <div class="guc-modal-header">
                    <div class="guc-modal-title" id="guc-modal-title">Crear nuevo usuario</div>
                    <button class="guc-close" id="guc-close">×</button>
                </div>
                <div class="guc-modal-body">
                    <div class="guc-field">
                        <label class="guc-label">Nro Expediente</label>
                        <input type="text" class="guc-input" id="guc-expediente" placeholder="Ej.: JRD-2025">
                        <div class="guc-helper">Se guardará exactamente como lo ingreses.</div>
                    </div>
                </div>
                <div class="guc-modal-footer">
                    <button class="guc-btn guc-btn-outline" id="guc-cancel">Cerrar</button>
                    <button class="guc-btn guc-btn-primary" id="guc-create">Crear</button>
                </div>
            </div>
        </div>

        <!-- Modal: Editar -->
        <div class="guc-modal-mask" id="guc-edit-mask" aria-hidden="true">
            <div class="guc-modal" role="dialog" aria-modal="true" aria-labelledby="guc-edit-title">
                <div class="guc-modal-header">
                    <div class="guc-modal-title" id="guc-edit-title">Editar usuario</div>
                    <button class="guc-close" id="guc-edit-close">×</button>
                </div>
                <div class="guc-modal-body">
                    <div class="guc-field">
                        <label class="guc-label">Usuario</label>
                        <input type="text" class="guc-input" id="guc-edit-username" disabled>
                    </div>
                    <div class="guc-field">
                        <label class="guc-label">Contraseña</label>
                        <input type="text" class="guc-input" id="guc-edit-password" disabled>
                    </div>
                    <div class="guc-field">
                        <label class="guc-label">Entidad</label>
                        <input type="text" class="guc-input" id="guc-edit-entity" placeholder="Ej.: AA / BB / ...">
                    </div>
                    <div class="guc-field">
                        <label class="guc-label">Expediente</label>
                        <input type="text" class="guc-input" id="guc-edit-expediente" placeholder="Ej.: JRD-2025">
                    </div>
                </div>
                <div class="guc-modal-footer">
                    <button class="guc-btn guc-btn-outline" id="guc-edit-cancel">Cerrar</button>
                    <button class="guc-btn guc-btn-primary" id="guc-edit-save">Guardar</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** ---------- UTILIDADES ---------- */
    private function maybe_create_table(){
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        if($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table){
            $this->activate();
        }
    }

    /** Seguridad mínima: validamos solo el nonce (CSRF). Tu página ya está restringida por tu sistema. */
    private function ensure_nonce_only() {
        check_ajax_referer('guc_nonce', 'nonce');
    }

    private function random_password($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $pass = '';
        for ($i=0;$i<$length;$i++) $pass .= $chars[random_int(0, strlen($chars)-1)];
        return $pass;
    }

    /** Generar username AAA-000 único también en wp_users */
    private function generate_unique_username_wp(){
        global $wpdb;
        $table_guc = $wpdb->prefix . self::TABLE;

        do {
            $letters = '';
            for ($i=0;$i<3;$i++) $letters .= chr(rand(65,90)); // A-Z
            $digits = str_pad((string)rand(0,999), 3, '0', STR_PAD_LEFT);
            $u = $letters . '-' . $digits;

            $exists_guc = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_guc WHERE username=%s", $u));
            $exists_wp  = username_exists($u);
        } while ($exists_guc > 0 || $exists_wp);

        return $u;
    }

    /** Crear usuario real en WordPress (wp_users) sin necesidad de estar logueado */
    private function create_wp_user($username, $password, $display = ''){
        // correo obligatorio y único
        $email = $username . '@' . self::GUC_EMAIL_DOMAIN;
        $n = 0;
        while (email_exists($email)) {
            $n++;
            $email = $username . '+' . $n . '@' . self::GUC_EMAIL_DOMAIN;
        }

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_pass'    => $password,
            'user_email'   => $email,
            'display_name' => $display ?: $username,
            'role'         => self::GUC_DEFAULT_ROLE, // customer / cliente
        ]);

        if (is_wp_error($user_id)) return $user_id;
        return $user_id;
    }

    /** ---------- AJAX ---------- */
    public function ajax_create() {
        $this->ensure_nonce_only();

        $exp = isset($_POST['expediente']) ? sanitize_text_field($_POST['expediente']) : '';
        if (empty($exp)) wp_send_json_error(['msg'=>'Expediente es requerido'], 422);

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        // 1) generar credenciales únicas (válidas en wp_users)
        $username = $this->generate_unique_username_wp();
        $password = $this->random_password(8);
        $now = current_time('mysql');

        // 2) crear usuario REAL en WP
        $wp_result = $this->create_wp_user($username, $password, $username);
        if (is_wp_error($wp_result)) {
            wp_send_json_error(['msg' => 'No se pudo crear el usuario WP: ' . $wp_result->get_error_message()], 500);
        }

        // 3) guardar además en la tabla del plugin (tu panel)
        $wpdb->insert($table, [
            'username'       => $username,
            'password_plain' => $password,
            'entity'         => '',
            'expediente'     => $exp,
            'created_at'     => $now
        ], ['%s','%s','%s','%s','%s']);

        if (!$wpdb->insert_id) {
            // rollback del usuario WP si algo falló aquí
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user($wp_result);
            wp_send_json_error(['msg'=>'Error al crear registro interno'], 500);
        }

        wp_send_json_success([
            'row' => [
                'id'         => $wpdb->insert_id,
                'username'   => $username,
                'password'   => $password,
                'entity'     => '',
                'expediente' => $exp,
                'created_at' => mysql2date('d/m/Y', $now),
            ]
        ]);
    }

    public function ajax_list() {
        $this->ensure_nonce_only();
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $rows = $wpdb->get_results("SELECT id, username, password_plain, entity, expediente, created_at FROM $table ORDER BY id DESC", ARRAY_A);
        $data = array_map(function($r){
            return [
                'id'         => (int)$r['id'],
                'username'   => $r['username'],
                'password'   => $r['password_plain'],
                'entity'     => $r['entity'],
                'expediente' => $r['expediente'],
                'created_at' => mysql2date('d/m/Y', $r['created_at']),
            ];
        }, $rows);
        wp_send_json_success(['rows' => $data]);
    }

    public function ajax_delete() {
    $this->ensure_nonce_only();
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    if (!$id) wp_send_json_error(['msg'=>'ID inválido'], 422);

    global $wpdb;
    $table = $wpdb->prefix . self::TABLE;

    // 1) obtener el username desde tu tabla interna
    $row = $wpdb->get_row($wpdb->prepare("SELECT username FROM $table WHERE id=%d", $id));
    if (!$row) wp_send_json_error(['msg'=>'Usuario no encontrado'], 404);

    // 2) si existe en wp_users, eliminarlo con la API de WP
    $wp_user = get_user_by('login', $row->username);
    if ($wp_user) {
        // cargar helpers de usuario si hiciera falta
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        // elimina el usuario de wp_users + metas/roles
        wp_delete_user((int)$wp_user->ID);
    }

    // 3) eliminar el espejo en tu tabla interna
    $ok = $wpdb->delete($table, ['id'=>$id], ['%d']);
    if (!$ok) wp_send_json_error(['msg'=>'No se pudo eliminar'], 500);

    wp_send_json_success(['id'=>$id]);
    }

    /** Nuevo: actualizar entidad y expediente */
    public function ajax_update() {
        $this->ensure_nonce_only();

        $id         = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $entity     = isset($_POST['entity']) ? sanitize_text_field($_POST['entity']) : '';
        $expediente = isset($_POST['expediente']) ? sanitize_text_field($_POST['expediente']) : '';

        if (!$id) wp_send_json_error(['msg'=>'ID inválido'], 422);

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $ok = $wpdb->update($table, [
            'entity'     => $entity,
            'expediente' => $expediente
        ], ['id' => $id], ['%s','%s'], ['%d']);

        if ($ok === false) wp_send_json_error(['msg'=>'No se pudo actualizar'], 500);

        wp_send_json_success([
            'id'         => $id,
            'entity'     => $entity,
            'expediente' => $expediente
        ]);
    }
}
new GUC_Plugin();
