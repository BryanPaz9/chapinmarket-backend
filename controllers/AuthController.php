<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Password.php';
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

        if (!Password::verify($password, $usuario['CONTRASENA'])) {
            Response::error("Credenciales incorrectas", 401);
        }

        if (Password::needsRehash($usuario['CONTRASENA'])) {
            $this->actualizarContrasena((int)$usuario['ID'], $password);
        }
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

        if (strlen($password) < 6) {
            Response::error("La contraseÃ±a debe tener al menos 6 caracteres", 400);
        }

        $passwordHash = Password::hash($password);

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
        oci_bind_by_name($stid, ":p_contrasena", $passwordHash);
        oci_bind_by_name($stid, ":p_direccion", $direccion);
        $esAdmin = 0;
        oci_bind_by_name($stid, ":p_es_admin",  $esAdmin);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al crear usuario: " . $e['message'], 500);
        }

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

    public function cambiarPassword()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['passwordActual']) || !isset($data['passwordNueva'])) {
            Response::error("ContraseÃ±a actual y nueva son requeridas", 400);
        }

        if (strlen($data['passwordNueva']) < 6) {
            Response::error("La nueva contraseÃ±a debe tener al menos 6 caracteres", 400);
        }

        $usuarioId = $_SESSION['usuario_id'] ?? $data['usuarioId'] ?? $data['usuario_id'] ?? null;
        if (!$usuarioId) {
            Response::error("Usuario no autenticado o usuarioId no enviado", 401);
        }

        $sql  = "SELECT id, contrasena FROM usuarios WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_id", $usuarioId, -1, SQLT_INT);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error en base de datos: " . $e['message'], 500);
        }

        $usuario = oci_fetch_assoc($stid);
        if (!$usuario) {
            Response::error("Usuario no encontrado", 404);
        }

        if (!Password::verify($data['passwordActual'], $usuario['CONTRASENA'])) {
            Response::error("La contraseÃ±a actual es incorrecta", 401);
        }

        $this->actualizarContrasena((int)$usuario['ID'], $data['passwordNueva']);

        Response::success(null, "ContraseÃ±a actualizada correctamente");
    }

    public function me()
    {
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
    private function actualizarContrasena($usuarioId, $password)
    {
        $passwordHash = Password::hash($password);
        $sql  = "UPDATE usuarios SET contrasena = :p_contrasena WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_contrasena", $passwordHash);
        oci_bind_by_name($stid, ":p_id", $usuarioId, -1, SQLT_INT);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al actualizar contraseÃ±a: " . $e['message'], 500);
        }
    }
}
