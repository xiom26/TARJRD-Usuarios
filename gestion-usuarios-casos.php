<?php
/**
 * Plugin Name: Gestión de Usuarios
 * Description: Shortcode [gestion_usuarios_casos] para crear usuarios (WP + tabla interna) desde el front. Funciona con login propio (sin sesión WP).
 * Version: 1.3.0
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
            wp_register_style('guc-css', false, [], '1.3.0');
            wp_enqueue_style('guc-css');

            $css = <<<CSS
            .guc-app{font-family:"Inter", "Roboto", Arial, sans-serif;color:#3f2d20;background:#f8f4ee;padding:24px;border-radius:22px;box-shadow:0 18px 40px rgba(63,45,32,0.08);border:1px solid #efe4d8}
            .guc-app *{box-sizing:border-box}
            .guc-nav{display:flex;gap:8px;margin-bottom:24px;background:#f1e6d8;padding:8px;border-radius:999px;justify-content:flex-start;align-items:center}
            .guc-nav__item{border:0;background:transparent;color:#866c4f;font-weight:600;padding:10px 20px;border-radius:999px;cursor:default;opacity:.7;transition:all .2s ease}
            .guc-nav__item--active{background:#fff;border:1px solid #e7d5bf;box-shadow:0 6px 12px rgba(63,45,32,0.12);color:#3f2d20;opacity:1}
            .guc-section{margin-bottom:28px}
            .guc-section__header{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:16px}
            .guc-section__title{margin:0;font-weight:800;font-size:22px;color:#3f2d20}
            .guc-section__subtitle{margin:4px 0 0;color:#90765a;font-size:14px;font-weight:500}
            .guc-pill{border:0;border-radius:999px;padding:10px 20px;cursor:pointer;background:linear-gradient(90deg,#8c7457,#bca07b);color:#fff;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 12px 20px rgba(140,116,87,0.2)}
            .guc-pill:disabled{opacity:.6;cursor:not-allowed}
            .guc-card{background:#fff;border-radius:18px;border:1px solid #f0e6db;box-shadow:0 12px 30px rgba(63,45,32,0.08);padding:0}
            .guc-table{width:100%;border-collapse:separate;border-spacing:0;margin:0}
            .guc-table thead{background:linear-gradient(180deg,#f6ede2,#ede0d1)}
            .guc-table th{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#81684b;text-align:left;padding:14px 22px;border-bottom:1px solid #e8dccc}
            .guc-table tbody tr{transition:background .2s ease}
            .guc-table tbody tr:nth-child(even){background:#fcf8f3}
            .guc-table td{padding:18px 22px;color:#3f2d20;border-bottom:1px solid #f2e9de;font-size:14px}
            .guc-table tbody tr:last-child td{border-bottom:none}
            .guc-badge{display:inline-flex;align-items:center;gap:6px;background:#2e7d32;color:#fff;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600}
            .guc-actions{display:flex;gap:10px}
            .guc-icon{border:0;border-radius:12px;padding:10px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 10px 20px rgba(0,0,0,0.15)}
            .guc-icon[data-act="view"]{background:#7d7d7d}
            .guc-icon[data-act="edit"]{background:#f29f05}
            .guc-icon[data-act="delete"]{background:#d64545}
            .guc-empty{padding:40px;text-align:center;color:#a08667;font-weight:500}
            #guc-mask,#guc-edit-mask{position:fixed;inset:0;background:rgba(32,24,18,0.55);display:none;align-items:center;justify-content:center;z-index:9999}
            .guc-modal{width:min(620px,92vw);background:#fff;border-radius:24px;box-shadow:0 30px 80px rgba(32,24,18,0.35);overflow:hidden;border:1px solid #efdfcc}
            .guc-modal__header{display:flex;justify-content:space-between;align-items:center;padding:22px 28px;background:linear-gradient(135deg,#4c3b31,#2f231c);color:#fff}
            .guc-modal__title{font-size:20px;font-weight:800;margin:0}
            .guc-close{background:transparent;border:0;color:#fff;font-size:22px;cursor:pointer}
            .guc-modal__body{padding:26px 28px;background:#faf6f1}
            .guc-field{margin-bottom:18px}
            .guc-label{display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:#4b3526;text-transform:uppercase;letter-spacing:.05em}
            .guc-input{width:100%;padding:14px 16px;border-radius:14px;border:1px solid #e3d3c0;background:#fff4e8;color:#3f2d20;font-size:15px;transition:box-shadow .2s ease,border .2s ease}
            .guc-input:focus{outline:none;border-color:#c89f75;box-shadow:0 0 0 4px rgba(200,159,117,0.25)}
            .guc-input[disabled]{background:#f1ebe3;color:#9f8a71}
            .guc-helper{font-size:12px;color:#a17f56;margin-top:6px}
            .guc-modal__footer{display:flex;gap:12px;justify-content:flex-end;padding:20px 28px;background:#f4ebdf;border-top:1px solid #e8d9c7}
            .guc-btn-outline{background:#fff;border:1px solid #c8a47b;color:#8a6d4c}
            .guc-badge span{display:inline-block;font-weight:700}
            .guc-pill-secondary{background:#fff;border:1px solid #ceb090;color:#8d7457;box-shadow:none}
            @media (max-width:768px){
                .guc-section__header{flex-direction:column;align-items:flex-start}
                .guc-actions{flex-wrap:wrap}
            }
            CSS;

            wp_add_inline_style('guc-css', $css);

            wp_enqueue_script('guc-js', plugin_dir_url(__FILE__) . 'js.js', [], '1.3.0', true);
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
        <div class="guc-app">
            <nav class="guc-nav" aria-label="Secciones del panel">
                <button type="button" class="guc-nav__item">Resumen</button>
                <button type="button" class="guc-nav__item guc-nav__item--active">Casos</button>
                <button type="button" class="guc-nav__item">Usuarios</button>
            </nav>

            <section class="guc-section" aria-labelledby="guc-casos-title">
                <div class="guc-section__header">
                    <div>
                        <h2 class="guc-section__title" id="guc-casos-title">Gestión de Casos</h2>
                        <p class="guc-section__subtitle">Visualiza el estado de tus expedientes y registra acciones.</p>
                    </div>
                    <button type="button" class="guc-pill guc-pill-secondary">
                        <span>＋</span> Nuevo caso
                    </button>
                </div>
                <div class="guc-card">
                    <div class="guc-empty">No hay casos registrados en este módulo.</div>
                </div>
            </section>

            <section class="guc-section" aria-labelledby="guc-users-title">
                <div class="guc-section__header">
                    <div>
                        <h2 class="guc-section__title" id="guc-users-title">Gestión de Usuarios</h2>
                        <p class="guc-section__subtitle">Crea, edita y elimina credenciales para tu equipo de trabajo.</p>
                    </div>
                    <button class="guc-pill" id="guc-open-modal" type="button">
                        <span>＋</span> Crear usuario
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
                                <th>Fecha creación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="guc-tbody"></tbody>
                    </table>
                    <div class="guc-empty" id="guc-empty" style="display:none">No hay usuarios registrados.</div>
                </div>
            </section>
        </div>

        <!-- Modal: Crear -->
        <div id="guc-mask" aria-hidden="true">
            <div class="guc-modal" role="dialog" aria-modal="true" aria-labelledby="guc-modal-title">
                <div class="guc-modal__header">
                    <h3 class="guc-modal__title" id="guc-modal-title">Crear nuevo usuario</h3>
                    <button class="guc-close" id="guc-close" type="button" aria-label="Cerrar">×</button>
                </div>
                <div class="guc-modal__body">
                    <div class="guc-field">
                        <label class="guc-label" for="guc-expediente">Nro Expediente</label>
                        <input type="text" class="guc-input" id="guc-expediente" placeholder="Ej.: TAR-2033-GL">
                        <div class="guc-helper">Se guardará exactamente como lo ingreses.</div>
                    </div>
                </div>
                <div class="guc-modal__footer">
                    <button class="guc-pill guc-pill-secondary" id="guc-cancel" type="button">Cerrar</button>
                    <button class="guc-pill" id="guc-create" type="button">Crear</button>
                </div>
            </div>
        </div>

        <!-- Modal: Editar -->
        <div id="guc-edit-mask" aria-hidden="true">
            <div class="guc-modal" role="dialog" aria-modal="true" aria-labelledby="guc-edit-title">
                <div class="guc-modal__header">
                    <h3 class="guc-modal__title" id="guc-edit-title">Editar usuario</h3>
                    <button class="guc-close" id="guc-edit-close" type="button" aria-label="Cerrar">×</button>
                </div>
                <div class="guc-modal__body">
                    <div class="guc-field">
                        <label class="guc-label" for="guc-edit-username">Usuario</label>
                        <input type="text" class="guc-input" id="guc-edit-username" disabled>
                    </div>
                    <div class="guc-field">
                        <label class="guc-label" for="guc-edit-password">Contraseña</label>
                        <input type="text" class="guc-input" id="guc-edit-password" disabled>
                    </div>
                    <div class="guc-field">
                        <label class="guc-label" for="guc-edit-entity">Entidad</label>
                        <input type="text" class="guc-input" id="guc-edit-entity" placeholder="Ej.: Policía / TAR">
                    </div>
                    <div class="guc-field">
                        <label class="guc-label" for="guc-edit-expediente">Expediente</label>
                        <input type="text" class="guc-input" id="guc-edit-expediente" placeholder="Ej.: TAR-2033-GL">
                    </div>
                </div>
                <div class="guc-modal__footer">
                    <button class="guc-pill guc-pill-secondary" id="guc-edit-cancel" type="button">Cerrar</button>
                    <button class="guc-pill" id="guc-edit-save" type="button">Guardar</button>
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
