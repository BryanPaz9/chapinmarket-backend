<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../config/database.php';

class AuthController
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['correo']) || !isset($data['password'])) {
            Response::error("Correo y contraseña son requeridos", 400);
        }

        $correo   = trim($data['correo']);
        $password = $data['password'];

        // CORREGIDO: El bind parameter en la SQL debe coincidir con oci_bind_by_name
        $sql  = "SELECT id, nombre, correo, contrasena, es_admin, direccion, telefono 
                 FROM usuarios 
                 WHERE correo = :p_correo";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_correo", $correo);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error en base de datos: " . $e['message'], 500);
        }

        $usuario = oci_fetch_assoc($stid);

        if (!$usuario) {
            Response::error("Credenciales incorrectas", 401);
        }

        $passwordValida = ($usuario['CONTRASENA'] === $password);
        if (!$passwordValida && function_exists('password_verify')) {
            $passwordValida = password_verify($password, $usuario['CONTRASENA']);
        }
        if (!$passwordValida) {
            Response::error("Credenciales incorrectas", 401);
        }

        // Obtener tarjetas del usuario
        $tarjetas = [];
        $sqlTarjetas = "SELECT id, titular, numero_enmascarado, tipo, vencimiento FROM tarjetas WHERE usuario_id = :p_uid";
        $stidTarjetas = oci_parse($this->conn, $sqlTarjetas);
        oci_bind_by_name($stidTarjetas, ":p_uid", $usuario['ID']);
        if (oci_execute($stidTarjetas)) {
            while ($t = oci_fetch_assoc($stidTarjetas)) {
                $tarjetas[] = [
                    'id'                => (int)$t['ID'],
                    'titular'           => $t['TITULAR'],
                    'numeroEnmascarado' => $t['NUMERO_ENMASCARADO'],
                    'tipo'              => $t['TIPO'] ?? 'Tarjeta',
                    'vencimiento'       => $t['VENCIMIENTO'] ?? ''
                ];
            }
        }

        // Obtener direcciones del usuario
        $direcciones = [];
        $sqlDir = "SELECT id, etiqueta, linea1, linea2, ciudad, departamento, codigo_postal, es_predeterminada 
                   FROM direcciones WHERE usuario_id = :p_uid ORDER BY es_predeterminada DESC, id ASC";
        $stidDir = oci_parse($this->conn, $sqlDir);
        oci_bind_by_name($stidDir, ":p_uid", $usuario['ID']);
        if (oci_execute($stidDir)) {
            while ($d = oci_fetch_assoc($stidDir)) {
                $direcciones[] = [
                    'id'               => (int)$d['ID'],
                    'etiqueta'         => $d['ETIQUETA'] ?? 'Casa',
                    'linea1'           => $d['LINEA1'],
                    'linea2'           => $d['LINEA2'] ?? '',
                    'ciudad'           => $d['CIUDAD'] ?? 'Ciudad de Guatemala',
                    'departamento'     => $d['DEPARTAMENTO'] ?? 'Guatemala',
                    'codigoPostal'     => $d['CODIGO_POSTAL'] ?? '',
                    'esPredeterminada' => (int)($d['ES_PREDETERMINADA'] ?? 0)
                ];
            }
        }

        $_SESSION['usuario_id']     = (int)$usuario['ID'];
        $_SESSION['usuario_nombre'] = $usuario['NOMBRE'];
        $_SESSION['es_admin']       = (int)$usuario['ES_ADMIN'];

        $respuesta = [
            'id'          => (int)$usuario['ID'],
            'nombre'      => $usuario['NOMBRE'],
            'correo'      => $usuario['CORREO'],
            'direccion'   => $usuario['DIRECCION'] ?? '',
            'telefono'    => $usuario['TELEFONO'] ?? '',
            'es_admin'    => (int)$usuario['ES_ADMIN'],
            'tarjetas'    => $tarjetas,
            'direcciones' => $direcciones,
            'pedidos'     => []
        ];
        Response::success($respuesta, "Inicio de sesión exitoso");
    }

    /**
     * POST /auth/registro
     */
    public function registro()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['nombre']) || !isset($data['correo']) || !isset($data['password'])) {
            Response::error("Nombre, correo y contraseña son requeridos", 400);
        }

        $nombre   = trim($data['nombre']);
        $correo   = trim($data['correo']);
        $password = $data['password'];
        $direccion = $data['direccion'] ?? null;

        // Verificar si el correo ya existe
        $checkSql  = "SELECT id FROM usuarios WHERE correo = :p_correo";
        $checkStid = oci_parse($this->conn, $checkSql);
        oci_bind_by_name($checkStid, ":p_correo", $correo);
        oci_execute($checkStid);
        if (oci_fetch_assoc($checkStid)) {
            Response::error("El correo ya está registrado", 409);
        }

        $sql  = "INSERT INTO usuarios (nombre, correo, contrasena, direccion, es_admin)
                 VALUES (:p_nombre, :p_correo, :p_contrasena, :p_direccion, :p_es_admin)";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_nombre",    $nombre);
        oci_bind_by_name($stid, ":p_correo",    $correo);
        oci_bind_by_name($stid, ":p_contrasena", $password);
        oci_bind_by_name($stid, ":p_direccion", $direccion);
        $esAdmin = 0;
        oci_bind_by_name($stid, ":p_es_admin",  $esAdmin);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al crear usuario: " . $e['message'], 500);
        }

        // Obtener el ID generado por el trigger TRG_USUARIOS_ID (seq_usuarios)
        $sqlId  = "SELECT seq_usuario.CURRVAL FROM DUAL";
        $stidId = oci_parse($this->conn, $sqlId);
        oci_execute($stidId);
        $row   = oci_fetch_assoc($stidId);
        $newId = (int)$row['CURRVAL'];

        if (!empty($direccion)) {
            $sqlDir  = "INSERT INTO direcciones (usuario_id, linea1, es_predeterminada)
                        VALUES (:p_uid_dir, :p_linea1_dir, 1)";
            $stidDir = oci_parse($this->conn, $sqlDir);
            oci_bind_by_name($stidDir, ":p_uid_dir",    $newId);
            oci_bind_by_name($stidDir, ":p_linea1_dir", $direccion);
            oci_execute($stidDir);
        }

        $usuario = [
            'id'          => $newId,
            'nombre'      => $nombre,
            'correo'      => $correo,
            'direccion'   => $direccion,
            'telefono'    => '',
            'es_admin'    => 0,
            'tarjetas'    => [],
            'direcciones' => [],
            'pedidos'     => []
        ];

        Response::success($usuario, "Usuario registrado exitosamente", 201);
    }

    public function me()
    {
        // Fallback para desarrollo: permitir ?uid=XX si no hay sesión
        if (!isset($_SESSION['usuario_id'])) {
            if (isset($_GET['uid'])) {
                $uid = (int)$_GET['uid'];
                $_SESSION['usuario_id'] = $uid;
            } else {
                Response::error("No autenticado", 401);
                exit;
            }
        }
        $id  = (int)$_SESSION['usuario_id'];
        $sql = "SELECT id, nombre, correo, telefono, direccion, es_admin 
                FROM usuarios 
                WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_id", $id);
        oci_execute($stid);
        $usuario = oci_fetch_assoc($stid);

        if (!$usuario) {
            session_destroy();
            Response::error("Usuario no encontrado", 401);
            exit;
        }

        $tarjetas = [];
        $sqlTarjetas  = "SELECT id, titular, numero_enmascarado, tipo, vencimiento FROM tarjetas WHERE usuario_id = :p_uid";
        $stidTarjetas = oci_parse($this->conn, $sqlTarjetas);
        oci_bind_by_name($stidTarjetas, ":p_uid", $usuario['ID']);
        if (oci_execute($stidTarjetas)) {
            while ($t = oci_fetch_assoc($stidTarjetas)) {
                $tarjetas[] = [
                    'id'                => (int)$t['ID'],
                    'titular'           => $t['TITULAR'],
                    'numeroEnmascarado' => $t['NUMERO_ENMASCARADO'],
                    'tipo'              => $t['TIPO'] ?? 'Tarjeta',
                    'vencimiento'       => $t['VENCIMIENTO'] ?? ''
                ];
            }
        }

        $direcciones = [];
        $sqlDir  = "SELECT id, etiqueta, linea1, linea2, ciudad, departamento, codigo_postal, es_predeterminada 
                    FROM direcciones WHERE usuario_id = :p_uid ORDER BY es_predeterminada DESC, id ASC";
        $stidDir = oci_parse($this->conn, $sqlDir);
        oci_bind_by_name($stidDir, ":p_uid", $usuario['ID']);
        if (oci_execute($stidDir)) {
            while ($d = oci_fetch_assoc($stidDir)) {
                $direcciones[] = [
                    'id'               => (int)$d['ID'],
                    'etiqueta'         => $d['ETIQUETA'] ?? 'Casa',
                    'linea1'           => $d['LINEA1'],
                    'linea2'           => $d['LINEA2'] ?? '',
                    'ciudad'           => $d['CIUDAD'] ?? 'Ciudad de Guatemala',
                    'departamento'     => $d['DEPARTAMENTO'] ?? 'Guatemala',
                    'codigoPostal'     => $d['CODIGO_POSTAL'] ?? '',
                    'esPredeterminada' => (int)($d['ES_PREDETERMINADA'] ?? 0)
                ];
            }
        }

        $respuesta = [
            'id'          => (int)$usuario['ID'],
            'nombre'      => $usuario['NOMBRE'],
            'correo'      => $usuario['CORREO'],
            'telefono'    => $usuario['TELEFONO'] ?? '',
            'direccion'   => $usuario['DIRECCION'] ?? '',
            'es_admin'    => (int)$usuario['ES_ADMIN'],
            'tarjetas'    => $tarjetas,
            'direcciones' => $direcciones,
            'pedidos'     => []
        ];
        Response::success($respuesta, "Usuario autenticado");
    }

    public function logout()
    {
        session_destroy();
        Response::success(null, "Sesión cerrada correctamente");
    }
}
