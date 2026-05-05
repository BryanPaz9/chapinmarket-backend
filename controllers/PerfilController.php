<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Password.php';
require_once __DIR__ . '/../config/database.php';

class PerfilController
{
    private $conn;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            $this->conn = Database::connect();
        } catch (Exception $e) {
            error_log("PerfilController: Error de conexión DB: " . $e->getMessage());
            Response::error("Error de conexión a la base de datos", 500);
        }
    }

    private function getUsuarioId()
    {
        if (!isset($_SESSION['usuario_id'])) {
            // Fallback para desarrollo: permitir ?uid=XX si no hay sesión
            if (isset($_GET['uid'])) {
                $uid = (int)$_GET['uid'];
                $_SESSION['usuario_id'] = $uid;
            } else {
                error_log("PerfilController::getUsuarioId - SESSION no tiene usuario_id. SESSION: " . json_encode($_SESSION));
                Response::error("No autenticado. Por favor inicia sesión nuevamente.", 401);
                exit;
            }
        }
        return (int)$_SESSION['usuario_id'];
    }

    // ========================
    // DATOS DEL PERFIL
    // ========================

    public function obtenerDatos()
    {
        $usuarioId = $this->getUsuarioId();

        // IMPORTANTE: Usar nombres de bind parameters SIN espacios y SIN caracteres problemáticos
        // Usar nombres como :p_id, :p_uid, etc.
        $sql  = "SELECT id, nombre, correo, telefono, direccion, es_admin FROM usuarios WHERE id = :p_id";
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

        $direcciones = $this->obtenerDirecciones($usuarioId);
        $tarjetas    = $this->obtenerTarjetas($usuarioId);
        $pedidos     = $this->obtenerPedidos($usuarioId);

        $respuesta = [
            'id'          => (int)$usuario['ID'],
            'nombre'      => $usuario['NOMBRE'],
            'correo'      => $usuario['CORREO'],
            'telefono'    => $usuario['TELEFONO'] ?? '',
            'direccion'   => $usuario['DIRECCION'] ?? '',
            'es_admin'    => (int)$usuario['ES_ADMIN'],
            'direcciones' => $direcciones,
            'tarjetas'    => $tarjetas,
            'pedidos'     => $pedidos
        ];

        $_SESSION['usuario_nombre'] = $usuario['NOMBRE'];

        Response::success($respuesta, "Datos del perfil obtenidos");
    }

    public function actualizarDatos()
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        $campos = [];
        $params = [":p_id" => $usuarioId];

        if (isset($data['nombre']) && !empty(trim($data['nombre']))) {
            $campos[] = "nombre = :p_nombre";
            $params[":p_nombre"] = trim($data['nombre']);
            $_SESSION['usuario_nombre'] = trim($data['nombre']);
        }
        if (isset($data['telefono'])) {
            $campos[] = "telefono = :p_telefono";
            $params[":p_telefono"] = trim($data['telefono']);
        }
        if (isset($data['direccion'])) {
            $campos[] = "direccion = :p_direccion";
            $params[":p_direccion"] = trim($data['direccion']);
        }

        if (empty($campos)) {
            Response::error("No hay campos para actualizar", 400);
        }

        $sql  = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);

        foreach ($params as $key => $value) {
            oci_bind_by_name($stid, $key, $params[$key]);
        }

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al actualizar: " . $e['message'], 500);
        }

        Response::success(null, "Datos actualizados correctamente");
    }

    public function cambiarPassword()
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['passwordActual']) || !isset($data['passwordNueva'])) {
            Response::error("Contraseña actual y nueva son requeridas", 400);
        }

        if (strlen($data['passwordNueva']) < 6) {
            Response::error("La nueva contraseña debe tener al menos 6 caracteres", 400);
        }

        $sql  = "SELECT contrasena FROM usuarios WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_id", $usuarioId, -1, SQLT_INT);
        oci_execute($stid);
        $usuario = oci_fetch_assoc($stid);

        if (!$usuario) {
            Response::error("Usuario no encontrado", 404);
        }

        if (!Password::verify($data['passwordActual'], $usuario['CONTRASENA'])) {
            Response::error("La contraseña actual es incorrecta", 401);
        }

        $nuevaPassword = Password::hash($data['passwordNueva']);
        $sql  = "UPDATE usuarios SET contrasena = :p_contrasena WHERE id = :p_id";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_contrasena", $nuevaPassword);
        oci_bind_by_name($stid, ":p_id",         $usuarioId, -1, SQLT_INT);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al cambiar contraseña: " . $e['message'], 500);
        }

        Response::success(null, "Contraseña actualizada correctamente");
    }

    // ========================
    // DIRECCIONES
    // ========================

    private function obtenerDirecciones($usuarioId)
    {
        try {
            $sql  = "SELECT id, etiqueta, linea1, linea2, ciudad, departamento, 
                            codigo_postal, es_predeterminada 
                     FROM direcciones 
                     WHERE usuario_id = :p_uid 
                     ORDER BY es_predeterminada DESC, id ASC";
            $stid = oci_parse($this->conn, $sql);
            oci_bind_by_name($stid, ":p_uid", $usuarioId, -1, SQLT_INT);

            if (!oci_execute($stid)) {
                $e = oci_error($stid);
                error_log("Error SQL obtenerDirecciones: " . ($e['message'] ?? ''));
                return [];
            }

            $direcciones = [];
            while ($row = oci_fetch_assoc($stid)) {
                $direcciones[] = [
                    'id'               => (int)$row['ID'],
                    'etiqueta'         => $row['ETIQUETA'] ?? 'Casa',
                    'linea1'           => $row['LINEA1'],
                    'linea2'           => $row['LINEA2'] ?? '',
                    'ciudad'           => $row['CIUDAD'] ?? 'Ciudad de Guatemala',
                    'departamento'     => $row['DEPARTAMENTO'] ?? 'Guatemala',
                    'codigoPostal'     => $row['CODIGO_POSTAL'] ?? '',
                    'esPredeterminada' => (int)($row['ES_PREDETERMINADA'] ?? 0)
                ];
            }
            return $direcciones;
        } catch (Exception $e) {
            error_log("Error al obtener direcciones: " . $e->getMessage());
            return [];
        }
    }

    public function listarDirecciones()
    {
        $usuarioId   = $this->getUsuarioId();
        $direcciones = $this->obtenerDirecciones($usuarioId);
        Response::success($direcciones, "Direcciones obtenidas");
    }

    public function agregarDireccion()
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['linea1']) || empty(trim($data['linea1']))) {
            Response::error("La dirección (línea 1) es requerida", 400);
        }

        try {
            // Si es predeterminada, quitar predeterminada de las demás
            if (!empty($data['esPredeterminada'])) {
                $sqlReset  = "UPDATE direcciones SET es_predeterminada = 0 WHERE usuario_id = :p_uid";
                $stidReset = oci_parse($this->conn, $sqlReset);
                if ($stidReset) {
                    oci_bind_by_name($stidReset, ":p_uid", $usuarioId, -1, SQLT_INT);
                    if (!oci_execute($stidReset)) {
                        $e = oci_error($stidReset);
                        error_log("Error al resetear predeterminada: " . ($e['message'] ?? ''));
                    }
                }
            }

            $etiqueta         = !empty($data['etiqueta'])      ? trim($data['etiqueta'])      : 'Casa';
            $linea1           = trim($data['linea1']);
            $linea2           = (!empty($data['linea2']))       ? trim($data['linea2'])        : '';
            $ciudad           = !empty($data['ciudad'])         ? trim($data['ciudad'])        : 'Ciudad de Guatemala';
            $departamento     = !empty($data['departamento'])   ? trim($data['departamento'])  : 'Guatemala';
            $codigoPostal     = (!empty($data['codigoPostal'])) ? trim($data['codigoPostal'])  : '';
            $esPredeterminada = (!empty($data['esPredeterminada'])) ? 1 : 0;

            $sql = "INSERT INTO direcciones 
                        (usuario_id, etiqueta, linea1, linea2, ciudad, departamento, codigo_postal, es_predeterminada)
                    VALUES 
                        (:p_uid, :p_etiqueta, :p_linea1, :p_linea2, :p_ciudad, :p_departamento, :p_codigoPostal, :p_esPredeterminada)";

            $stid = oci_parse($this->conn, $sql);
            if (!$stid) {
                $e = oci_error($this->conn);
                throw new Exception("Error al preparar SQL dirección: " . ($e['message'] ?? 'desconocido'));
            }

            oci_bind_by_name($stid, ":p_uid",              $usuarioId,       -1, SQLT_INT);
            oci_bind_by_name($stid, ":p_etiqueta",         $etiqueta,        50, SQLT_CHR);
            oci_bind_by_name($stid, ":p_linea1",           $linea1,         300, SQLT_CHR);
            oci_bind_by_name($stid, ":p_linea2",           $linea2,         300, SQLT_CHR);
            oci_bind_by_name($stid, ":p_ciudad",           $ciudad,         100, SQLT_CHR);
            oci_bind_by_name($stid, ":p_departamento",     $departamento,   100, SQLT_CHR);
            oci_bind_by_name($stid, ":p_codigoPostal",     $codigoPostal,    20, SQLT_CHR);
            oci_bind_by_name($stid, ":p_esPredeterminada", $esPredeterminada, -1, SQLT_INT);

            if (!oci_execute($stid)) {
                $e = oci_error($stid);
                throw new Exception("Error al insertar dirección: " . ($e['message'] ?? 'error desconocido'));
            }

            Response::success(null, "Dirección agregada correctamente", 201);
        } catch (Exception $e) {
            error_log("agregarDireccion ERROR: " . $e->getMessage());
            Response::error("No se pudo guardar la dirección: " . $e->getMessage(), 500);
        }
    }

    public function actualizarDireccion($id)
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data) {
            Response::error("Datos inválidos", 400);
        }

        // Verificar que la dirección pertenece al usuario
        $checkSql  = "SELECT id FROM direcciones WHERE id = :p_id AND usuario_id = :p_uid";
        $checkStid = oci_parse($this->conn, $checkSql);
        oci_bind_by_name($checkStid, ":p_id",  $id, -1, SQLT_INT);
        oci_bind_by_name($checkStid, ":p_uid", $usuarioId, -1, SQLT_INT);
        oci_execute($checkStid);
        if (!oci_fetch_assoc($checkStid)) {
            Response::error("Dirección no encontrada", 404);
        }

        if (!empty($data['esPredeterminada'])) {
            $sqlReset  = "UPDATE direcciones SET es_predeterminada = 0 WHERE usuario_id = :p_uid";
            $stidReset = oci_parse($this->conn, $sqlReset);
            oci_bind_by_name($stidReset, ":p_uid", $usuarioId, -1, SQLT_INT);
            oci_execute($stidReset);
        }

        // Construir actualización dinámica – solo los campos recibidos
        $campos = [];
        $bindParams = [':p_id' => $id, ':p_uid' => $usuarioId];

        if (isset($data['etiqueta'])) {
            $etiqueta = trim($data['etiqueta']) ?: 'Casa';
            $campos[] = "etiqueta = :p_etiqueta";
            $bindParams[':p_etiqueta'] = $etiqueta;
        }
        if (isset($data['linea1'])) {
            $linea1 = trim($data['linea1']);
            if ($linea1 === '') {
                Response::error("La línea 1 de la dirección no puede estar vacía", 400);
            }
            $campos[] = "linea1 = :p_linea1";
            $bindParams[':p_linea1'] = $linea1;
        }
        if (isset($data['linea2'])) {
            $linea2 = trim($data['linea2']);
            $campos[] = "linea2 = :p_linea2";
            $bindParams[':p_linea2'] = $linea2;
        }
        if (isset($data['ciudad'])) {
            $ciudad = trim($data['ciudad']) ?: 'Ciudad de Guatemala';
            $campos[] = "ciudad = :p_ciudad";
            $bindParams[':p_ciudad'] = $ciudad;
        }
        if (isset($data['departamento'])) {
            $departamento = trim($data['departamento']) ?: 'Guatemala';
            $campos[] = "departamento = :p_departamento";
            $bindParams[':p_departamento'] = $departamento;
        }
        if (isset($data['codigoPostal'])) {
            $codigoPostal = trim($data['codigoPostal']);
            $campos[] = "codigo_postal = :p_codigoPostal";
            $bindParams[':p_codigoPostal'] = $codigoPostal;
        }
        if (isset($data['esPredeterminada'])) {
            $esPredeterminada = !empty($data['esPredeterminada']) ? 1 : 0;
            $campos[] = "es_predeterminada = :p_esPredeterminada";
            $bindParams[':p_esPredeterminada'] = $esPredeterminada;

            if ($esPredeterminada) {
                // Quitar predeterminada actual (si se pide)
                $sqlReset = "UPDATE direcciones SET es_predeterminada = 0 WHERE usuario_id = :p_uid_reset";
                $stidReset = oci_parse($this->conn, $sqlReset);
                oci_bind_by_name($stidReset, ":p_uid_reset", $usuarioId, -1, SQLT_INT);
                @oci_execute($stidReset);
            }
        }

        if (empty($campos)) {
            Response::error("No hay campos para actualizar", 400);
        }

        $sql = "UPDATE direcciones SET " . implode(', ', $campos) . " WHERE id = :p_id AND usuario_id = :p_uid";
        $stid = oci_parse($this->conn, $sql);
        foreach ($bindParams as $key => $value) {
            oci_bind_by_name($stid, $key, $bindParams[$key]);
        }

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al actualizar dirección: " . $e['message'], 500);
        }
        Response::success(null, "Dirección actualizada correctamente");
    }

    public function eliminarDireccion($id)
    {
        $usuarioId = $this->getUsuarioId();

        $checkSql  = "SELECT id FROM direcciones WHERE id = :p_id AND usuario_id = :p_uid";
        $checkStid = oci_parse($this->conn, $checkSql);
        oci_bind_by_name($checkStid, ":p_id",  $id, -1, SQLT_INT);
        oci_bind_by_name($checkStid, ":p_uid", $usuarioId, -1, SQLT_INT);
        oci_execute($checkStid);
        if (!oci_fetch_assoc($checkStid)) {
            Response::error("Dirección no encontrada", 404);
        }

        $sql  = "DELETE FROM direcciones WHERE id = :p_id AND usuario_id = :p_uid";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_id",  $id,        -1, SQLT_INT);
        oci_bind_by_name($stid, ":p_uid", $usuarioId, -1, SQLT_INT);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            Response::error("Error al eliminar dirección: " . $e['message'], 500);
        }

        Response::success(null, "Dirección eliminada correctamente");
    }

    // ========================
    // TARJETAS
    // ========================

    private function obtenerTarjetas($usuarioId)
    {
        try {
            $sql  = "SELECT id, titular, numero_enmascarado, tipo, vencimiento 
                     FROM tarjetas WHERE usuario_id = :p_uid ORDER BY id DESC";
            $stid = oci_parse($this->conn, $sql);
            oci_bind_by_name($stid, ":p_uid", $usuarioId, -1, SQLT_INT);

            if (!oci_execute($stid)) {
                $e = oci_error($stid);
                error_log("Error SQL obtenerTarjetas: " . ($e['message'] ?? ''));
                return [];
            }

            $tarjetas = [];
            while ($row = oci_fetch_assoc($stid)) {
                $tarjetas[] = [
                    'id'                => (int)$row['ID'],
                    'titular'           => $row['TITULAR'],
                    'numeroEnmascarado' => $row['NUMERO_ENMASCARADO'],
                    'tipo'              => $row['TIPO'] ?? 'Tarjeta',
                    'vencimiento'       => $row['VENCIMIENTO'] ?? ''
                ];
            }
            return $tarjetas;
        } catch (Exception $e) {
            error_log("Error al obtener tarjetas: " . $e->getMessage());
            return [];
        }
    }

    public function listarTarjetas()
    {
        $usuarioId = $this->getUsuarioId();
        $tarjetas  = $this->obtenerTarjetas($usuarioId);
        Response::success($tarjetas, "Tarjetas obtenidas");
    }

    public function agregarTarjeta()
    {
        $usuarioId = $this->getUsuarioId();
        $data = json_decode(file_get_contents("php://input"), true);

        if (!$data || !isset($data['numeroEnmascarado']) || !isset($data['titular'])) {
            Response::error("Datos de tarjeta inválidos. Se requiere titular y número", 400);
        }

        // Limpiar y enmascarar el número — solo quedarse con dígitos
        $numeroLimpio = preg_replace('/\D/', '', $data['numeroEnmascarado']);
        if (strlen($numeroLimpio) < 4) {
            Response::error("El número de tarjeta debe tener al menos 4 dígitos", 400);
        }

        $ultimos4          = substr($numeroLimpio, -4);
        $numeroEnmascarado = '****' . $ultimos4;
        $titular           = trim($data['titular']);
        $tipo              = !empty($data['tipo'])        ? trim($data['tipo'])        : 'Tarjeta';
        $vencimiento       = !empty($data['vencimiento']) ? trim($data['vencimiento']) : '';

        $sql  = "INSERT INTO tarjetas (usuario_id, titular, numero_enmascarado, tipo, vencimiento)
                 VALUES (:p_uid, :p_titular, :p_numero, :p_tipo, :p_vencimiento)";
        $stid = oci_parse($this->conn, $sql);

        if (!$stid) {
            $e = oci_error($this->conn);
            error_log("Error al parsear SQL tarjeta: " . ($e['message'] ?? 'desconocido'));
            Response::error("Error interno al preparar la consulta de tarjeta", 500);
        }

        oci_bind_by_name($stid, ":p_uid",         $usuarioId,        -1, SQLT_INT);
        oci_bind_by_name($stid, ":p_titular",     $titular,         100, SQLT_CHR);
        oci_bind_by_name($stid, ":p_numero",      $numeroEnmascarado, 20, SQLT_CHR);
        oci_bind_by_name($stid, ":p_tipo",        $tipo,             50, SQLT_CHR);
        oci_bind_by_name($stid, ":p_vencimiento", $vencimiento,      10, SQLT_CHR);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            error_log("Error al insertar tarjeta: " . ($e['message'] ?? 'desconocido'));
            Response::error("No se pudo guardar la tarjeta: " . ($e['message'] ?? 'error desconocido'), 500);
        }

        Response::success(null, "Tarjeta guardada correctamente", 201);
    }

    public function eliminarTarjeta($id)
    {
        $usuarioId = $this->getUsuarioId();

        // Verificar propiedad
        $checkSql  = "SELECT id FROM tarjetas WHERE id = :p_id AND usuario_id = :p_uid";
        $checkStid = oci_parse($this->conn, $checkSql);
        oci_bind_by_name($checkStid, ":p_id",  $id, -1, SQLT_INT);
        oci_bind_by_name($checkStid, ":p_uid", $usuarioId, -1, SQLT_INT);
        oci_execute($checkStid);
        if (!oci_fetch_assoc($checkStid)) {
            Response::error("Tarjeta no encontrada", 404);
        }

        // Verificar si hay transacciones asociadas
        $checkTransSql = "SELECT COUNT(*) AS cnt FROM transacciones WHERE tarjeta_id = :p_tid";
        $checkTransStid = oci_parse($this->conn, $checkTransSql);
        oci_bind_by_name($checkTransStid, ":p_tid", $id, -1, SQLT_INT);
        oci_execute($checkTransStid);
        $row = oci_fetch_assoc($checkTransStid);
        if ($row && $row['CNT'] > 0) {
            Response::error("No se puede eliminar la tarjeta porque tiene transacciones asociadas. Considera desvincularla en lugar de borrarla.", 409);
        }

        $sql  = "DELETE FROM tarjetas WHERE id = :p_id AND usuario_id = :p_uid";
        $stid = oci_parse($this->conn, $sql);
        oci_bind_by_name($stid, ":p_id",  $id,        -1, SQLT_INT);
        oci_bind_by_name($stid, ":p_uid", $usuarioId, -1, SQLT_INT);

        if (!oci_execute($stid)) {
            $e = oci_error($stid);
            // Si aun así falla por integridad, devolver 409
            if (isset($e['code']) && $e['code'] == 2292) {
                Response::error("No se puede eliminar la tarjeta porque tiene transacciones asociadas.", 409);
            }
            Response::error("Error al eliminar tarjeta: " . ($e['message'] ?? 'error'), 500);
        }

        Response::success(null, "Tarjeta eliminada correctamente");
    }

    // ========================
    // PEDIDOS
    // ========================

    private function obtenerPedidos($usuarioId)
    {
        try {
            $sql  = "SELECT p.id, p.fecha, p.total, p.estado, p.direccion_envio,
                            (SELECT COUNT(*) FROM pedido_items pi WHERE pi.pedido_id = p.id) AS cantidad_items
                     FROM pedidos p 
                     WHERE p.usuario_id = :p_uid 
                     ORDER BY p.fecha DESC";
            $stid = oci_parse($this->conn, $sql);
            oci_bind_by_name($stid, ":p_uid", $usuarioId, -1, SQLT_INT);

            if (!oci_execute($stid)) {
                $e = oci_error($stid);
                error_log("Error SQL obtenerPedidos: " . ($e['message'] ?? ''));
                return [];
            }

            $pedidos = [];
            while ($row = oci_fetch_assoc($stid)) {
                $pedidos[] = [
                    'id'             => (int)$row['ID'],
                    'fecha'          => $row['FECHA'],
                    'total'          => (float)$row['TOTAL'],
                    'estado'         => $row['ESTADO'],
                    'direccionEnvio' => $row['DIRECCION_ENVIO'] ?? '',
                    'cantidadItems'  => (int)$row['CANTIDAD_ITEMS']
                ];
            }
            return $pedidos;
        } catch (Exception $e) {
            error_log("Error al obtener pedidos: " . $e->getMessage());
            return [];
        }
    }
}
