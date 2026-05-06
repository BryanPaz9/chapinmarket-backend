<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error_log');

$php_errors = [];
set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$php_errors) {
    $php_errors[] = [
        'code' => $errno,
        'message' => $errstr,
        'file' => basename($errfile),
        'line' => $errline,
        'type' => 'PHP Warning'
    ];
    return true;
});

$mode = $_GET['mode'] ?? 'html';
$mode = in_array($mode, ['html', 'json', 'silent', 'debug']) ? $mode : 'html';
$show_debug = ($mode === 'debug');

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $session_started = true;
} catch (Exception $e) {
    $session_started = false;
    $session_error = $e->getMessage();
}

$diagnostic_session_data = $_SESSION;

$diagnostico = [
    'timestamp' => date('Y-m-d H:i:s'),
    'mode' => $mode,
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
    'session' => [],
    'database' => [],
    'filesystem' => [],
    'endpoints' => [],
    'config' => [],
    'environment' => [],
    'recommendations' => [],
    'php_errors_captured' => $php_errors
];

$diagnostico['session'] = [
    'started' => $session_started ?? false,
    'error' => $session_error ?? null,
    'id' => session_id() ?: 'No session ID (cookie no enviada o no aceptada)',
    'name' => session_name(),
    'status' => session_status(),
    'status_text' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVA' : (session_status() === PHP_SESSION_NONE ? 'INACTIVA' : 'DESHABILITADA'),
    'data' => $_SESSION,
    'cookie_params' => session_get_cookie_params(),
    'save_path' => session_save_path() ?: 'No definido (usando predeterminado)',
    'save_path_writable' => is_writable(session_save_path() ?: sys_get_temp_dir()),
];

$diagnostico['session']['cookies'] = $_COOKIE;
$diagnostico['session']['has_session_cookie'] = isset($_COOKIE[session_name()]);
$diagnostico['session']['session_cookie_value'] = $_COOKIE[session_name()] ?? 'No existe';

$diagnostico['session']['headers'] = [
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'None',
    'referer' => $_SERVER['HTTP_REFERER'] ?? 'None',
    'accept' => $_SERVER['HTTP_ACCEPT'] ?? 'None',
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'None',
];

if (isset($_SESSION['usuario_id'])) {
    $diagnostico['session']['logged_in'] = true;
    $diagnostico['session']['user_id'] = $_SESSION['usuario_id'];
    $diagnostico['session']['user_name'] = $_SESSION['usuario_nombre'] ?? 'Unknown';
    $diagnostico['session']['is_admin'] = ($_SESSION['es_admin'] ?? 0) == 1;
} else {
    $diagnostico['session']['logged_in'] = false;
    $diagnostico['session']['error'] = 'No hay usuario autenticado';
    $diagnostico['recommendations'][] = '⚠️ Inicia sesión en el frontend antes de ejecutar este diagnóstico para pruebas de escritura';
}

$required_files = [
    '../config/database.php' => 'Configuración de BD',
    '../core/Response.php' => 'Respuestas API',
    '../core/Router.php' => 'Enrutador',
    '../controllers/AuthController.php' => 'Autenticación',
    '../controllers/PerfilController.php' => 'Perfil de usuario',
    '../controllers/CarritoController.php' => 'Carrito',
    '../models/BaseModel.php' => 'Modelo base',
    '../models/Usuario.php' => 'Modelo Usuario',
    '../models/Direccion.php' => 'Modelo Dirección',
    '../models/Tarjeta.php' => 'Modelo Tarjeta',
    '../routes/api.php' => 'Rutas API',
];

$missing_files = [];
foreach ($required_files as $file => $description) {
    $full_path = __DIR__ . '/' . $file;
    $exists = file_exists($full_path);
    if (!$exists) {
        $missing_files[] = "$file ($description)";
    }
    $diagnostico['filesystem']['files'][$file] = [
        'description' => $description,
        'exists' => $exists,
        'readable' => is_readable($full_path),
        'size' => file_exists($full_path) ? filesize($full_path) : 0,
        'permissions' => file_exists($full_path) ? substr(sprintf('%o', fileperms($full_path)), -4) : 'N/A',
        'last_modified' => file_exists($full_path) ? date('Y-m-d H:i:s', filemtime($full_path)) : 'N/A'
    ];
}

if (!empty($missing_files)) {
    $diagnostico['recommendations'][] = '❌ Archivos faltantes: ' . implode(', ', $missing_files);
}

$uploads_dir = __DIR__ . '/../uploads';
$diagnostico['filesystem']['uploads_dir'] = [
    'exists' => file_exists($uploads_dir),
    'writable' => is_writable($uploads_dir),
    'path' => $uploads_dir
];

try {
    require_once __DIR__ . '/../config/database.php';

    $conn_start = microtime(true);
    $conn = Database::connect();
    $conn_time = round((microtime(true) - $conn_start) * 1000, 2);

    $diagnostico['database']['connection'] = [
        'success' => true,
        'time_ms' => $conn_time,
        'message' => 'Conexión establecida correctamente'
    ];

    $stmt = oci_parse($conn, "SELECT USER, SYSDATE FROM DUAL");
    oci_execute($stmt);
    $row = oci_fetch_assoc($stmt);
    $diagnostico['database']['session_info'] = [
        'current_user' => $row['USER'] ?? 'Unknown',
        'current_date' => $row['SYSDATE'] ?? 'Unknown'
    ];

    try {
        $stmt = oci_parse($conn, "SELECT banner FROM v\$version WHERE banner LIKE 'Oracle%'");
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $diagnostico['database']['oracle_version'] = $row['BANNER'] ?? 'No disponible (permisos insuficientes)';
    } catch (Exception $e) {
        $diagnostico['database']['oracle_version'] = 'No se pudo obtener (usuario sin privilegios)';
    }

    $nls_params = ['NLS_DATE_FORMAT', 'NLS_LANGUAGE', 'NLS_TERRITORY', 'NLS_CHARACTERSET'];
    foreach ($nls_params as $param) {
        $stmt = oci_parse($conn, "SELECT VALUE FROM NLS_SESSION_PARAMETERS WHERE PARAMETER = :p");
        oci_bind_by_name($stmt, ":p", $param);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $diagnostico['database']['nls'][$param] = $row['VALUE'] ?? 'Unknown';
    }
} catch (Exception $e) {
    $diagnostico['database']['connection'] = [
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $show_debug ? $e->getTraceAsString() : 'Habilitar modo debug para ver trace'
    ];
    $diagnostico['recommendations'][] = '❌ Verificar credenciales de Oracle en config/database.php';
    $conn = null;
}

if ($conn) {
    $tables_info = [];
    $expected_tables = [
        'USUARIOS' => 'Usuarios del sistema',
        'DIRECCIONES' => 'Direcciones de usuarios',
        'TARJETAS' => 'Tarjetas guardadas',
        'CARRITOS' => 'Carritos de compra',
        'CARRITO_ITEMS' => 'Items del carrito',
        'PRODUCTOS' => 'Catálogo de productos',
        'CATEGORIAS' => 'Categorías de productos',
        'TEMPORADAS' => 'Temporadas/promociones',
        'PEDIDOS' => 'Pedidos realizados',
        'PEDIDO_ITEMS' => 'Items de pedidos'
    ];

    foreach ($expected_tables as $table => $description) {
        $stmt = oci_parse($conn, "SELECT COUNT(*) as cnt FROM user_tables WHERE table_name = :t");
        oci_bind_by_name($stmt, ":t", $table);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);

        $exists = ($row['CNT'] ?? 0) > 0;

        if ($exists) {
            $stmtCount = oci_parse($conn, "SELECT COUNT(*) as total FROM \"$table\"");
            oci_execute($stmtCount);
            $countRow = oci_fetch_assoc($stmtCount);

            $stmtCols = oci_parse($conn, "SELECT column_name, data_type, data_length, nullable FROM user_tab_columns WHERE table_name = :t ORDER BY column_id");
            oci_bind_by_name($stmtCols, ":t", $table);
            oci_execute($stmtCols);
            $columns = [];
            while ($col = oci_fetch_assoc($stmtCols)) {
                $columns[] = [
                    'name' => $col['COLUMN_NAME'],
                    'type' => $col['DATA_TYPE'],
                    'length' => $col['DATA_LENGTH'],
                    'nullable' => $col['NULLABLE']
                ];
            }

            $tables_info[$table] = [
                'description' => $description,
                'exists' => true,
                'row_count' => (int)($countRow['TOTAL'] ?? 0),
                'columns' => $columns,
                'column_count' => count($columns)
            ];
        } else {
            $tables_info[$table] = [
                'description' => $description,
                'exists' => false,
                'error' => 'Tabla no encontrada'
            ];
            $diagnostico['recommendations'][] = "❌ Crear tabla $table en el esquema Oracle";
        }
    }
    $diagnostico['database']['tables'] = $tables_info;

    $sequences = [];
    $expected_sequences = ['SEQ_USUARIO', 'SEQ_DIRECCION', 'SEQ_TARJETA', 'SEQ_CARRITO', 'SEQ_PRODUCTO', 'SEQ_CATEGORIA', 'SEQ_TEMPORADA'];

    foreach ($expected_sequences as $seq) {
        $stmt = oci_parse($conn, "SELECT sequence_name, last_number FROM user_sequences WHERE sequence_name = :s");
        oci_bind_by_name($stmt, ":s", $seq);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $sequences[$seq] = $row ? ['exists' => true, 'last_number' => $row['LAST_NUMBER']] : ['exists' => false];
        if (!$row) {
            $diagnostico['recommendations'][] = "⚠️ Secuencia $seq no existe - puede afectar auto-incremento de IDs";
        }
    }
    $diagnostico['database']['sequences'] = $sequences;

    $triggers = [];
    $expected_triggers = ['TRG_USUARIOS_ID', 'TRG_DIRECCION_ID', 'TRG_TARJETA_ID'];

    foreach ($expected_triggers as $trigger) {
        $stmt = oci_parse($conn, "SELECT trigger_name, table_name, status FROM user_triggers WHERE trigger_name = :t");
        oci_bind_by_name($stmt, ":t", $trigger);
        oci_execute($stmt);
        $row = oci_fetch_assoc($stmt);
        $triggers[$trigger] = $row ? ['exists' => true, 'table' => $row['TABLE_NAME'], 'status' => $row['STATUS']] : ['exists' => false];
        if (!$row) {
            $diagnostico['recommendations'][] = "⚠️ Trigger $trigger no existe - necesario para auto-incremento de IDs";
        } elseif ($row['STATUS'] !== 'ENABLED') {
            $diagnostico['recommendations'][] = "⚠️ Trigger $trigger está {$row['STATUS']} - debe estar ENABLED";
        }
    }
    $diagnostico['database']['triggers'] = $triggers;

    if ($diagnostico['session']['logged_in']) {
        $usuarioId = $_SESSION['usuario_id'];

        $stmt = oci_parse($conn, "SELECT id, nombre FROM usuarios WHERE id = :p_id");
        oci_bind_by_name($stmt, ":p_id", $usuarioId);
        oci_execute($stmt);
        $usuario = oci_fetch_assoc($stmt);

        if ($usuario) {
            $testLinea1 = "TEST_DIAG_" . date('Ymd_His');
            $testEtiqueta = "Diagnóstico";

            $sqlInsert = "INSERT INTO direcciones (usuario_id, etiqueta, linea1) VALUES (:p_uid, :p_etiqueta, :p_linea1)";
            $stmtInsert = oci_parse($conn, $sqlInsert);
            oci_bind_by_name($stmtInsert, ":p_uid", $usuarioId);
            oci_bind_by_name($stmtInsert, ":p_etiqueta", $testEtiqueta);
            oci_bind_by_name($stmtInsert, ":p_linea1", $testLinea1);

            $insertSuccess = @oci_execute($stmtInsert);

            if ($insertSuccess) {
                $diagnostico['database']['test_insert_direccion'] = [
                    'success' => true,
                    'message' => 'INSERT exitoso en direcciones',
                    'test_data' => ['linea1' => $testLinea1, 'etiqueta' => $testEtiqueta]
                ];

                // Limpiar
                $sqlDelete = "DELETE FROM direcciones WHERE linea1 = :p_linea1 AND usuario_id = :p_uid";
                $stmtDelete = oci_parse($conn, $sqlDelete);
                oci_bind_by_name($stmtDelete, ":p_linea1", $testLinea1);
                oci_bind_by_name($stmtDelete, ":p_uid", $usuarioId);
                oci_execute($stmtDelete);
            } else {
                $e = oci_error($stmtInsert);
                $diagnostico['database']['test_insert_direccion'] = [
                    'success' => false,
                    'error' => $e['message'] ?? 'Unknown error',
                    'code' => $e['code'] ?? 'N/A',
                    'sql' => $sqlInsert
                ];
                $diagnostico['recommendations'][] = '❌ Revisar la estructura de la tabla DIRECCIONES - ' . ($e['message'] ?? 'Error desconocido');
            }
        } else {
            $diagnostico['database']['test_insert_direccion'] = [
                'success' => false,
                'error' => "Usuario ID $usuarioId no existe en la tabla USUARIOS"
            ];
            $diagnostico['recommendations'][] = "❌ El usuario logueado (ID: $usuarioId) no existe en la base de datos";
        }
    }

    if ($diagnostico['session']['logged_in']) {
        $usuarioId = $_SESSION['usuario_id'];

        $testTitular = "TEST_DIAG_" . date('Ymd');
        $testNumero = "****" . rand(1000, 9999);

        $sqlInsert = "INSERT INTO tarjetas (usuario_id, titular, numero_enmascarado, tipo) VALUES (:p_uid, :p_titular, :p_numero, :p_tipo)";
        $stmtInsert = oci_parse($conn, $sqlInsert);
        oci_bind_by_name($stmtInsert, ":p_uid", $usuarioId);
        oci_bind_by_name($stmtInsert, ":p_titular", $testTitular);
        oci_bind_by_name($stmtInsert, ":p_numero", $testNumero);
        $tipo = "Tarjeta";
        oci_bind_by_name($stmtInsert, ":p_tipo", $tipo);

        $insertSuccess = @oci_execute($stmtInsert);

        if ($insertSuccess) {
            $diagnostico['database']['test_insert_tarjeta'] = [
                'success' => true,
                'message' => 'INSERT exitoso en tarjetas',
                'test_data' => ['titular' => $testTitular, 'numero' => $testNumero]
            ];

            $sqlDelete = "DELETE FROM tarjetas WHERE titular = :p_titular AND usuario_id = :p_uid";
            $stmtDelete = oci_parse($conn, $sqlDelete);
            oci_bind_by_name($stmtDelete, ":p_titular", $testTitular);
            oci_bind_by_name($stmtDelete, ":p_uid", $usuarioId);
            oci_execute($stmtDelete);
        } else {
            $e = oci_error($stmtInsert);
            $diagnostico['database']['test_insert_tarjeta'] = [
                'success' => false,
                'error' => $e['message'] ?? 'Unknown error',
                'code' => $e['code'] ?? 'N/A',
                'sql' => $sqlInsert
            ];
            $diagnostico['recommendations'][] = '❌ Revisar la estructura de la tabla TARJETAS - ' . ($e['message'] ?? 'Error desconocido');
        }
    }
}

if ($diagnostico['session']['logged_in']) {

    if ($conn) {
        $usuarioId = $_SESSION['usuario_id'];
        $stmt = oci_parse($conn, "SELECT id, nombre, correo, telefono, direccion, es_admin FROM usuarios WHERE id = :p_id");
        oci_bind_by_name($stmt, ":p_id", $usuarioId);
        oci_execute($stmt);
        $usuarioData = oci_fetch_assoc($stmt);

        if ($usuarioData) {
            $stmtDir = oci_parse($conn, "SELECT COUNT(*) as cnt FROM direcciones WHERE usuario_id = :p_uid");
            oci_bind_by_name($stmtDir, ":p_uid", $usuarioId);
            oci_execute($stmtDir);
            $dirCount = oci_fetch_assoc($stmtDir);

            $stmtTar = oci_parse($conn, "SELECT COUNT(*) as cnt FROM tarjetas WHERE usuario_id = :p_uid");
            oci_bind_by_name($stmtTar, ":p_uid", $usuarioId);
            oci_execute($stmtTar);
            $tarCount = oci_fetch_assoc($stmtTar);

            $diagnostico['controllers']['perfil'] = [
                'success' => true,
                'response_summary' => [
                    'user_name' => $usuarioData['NOMBRE'],
                    'user_id' => $usuarioData['ID'],
                    'direcciones_count' => (int)($dirCount['CNT'] ?? 0),
                    'tarjetas_count' => (int)($tarCount['CNT'] ?? 0)
                ]
            ];
        } else {
            $diagnostico['controllers']['perfil'] = [
                'success' => false,
                'error' => 'Usuario no encontrado en BD'
            ];
        }
    } else {
        $diagnostico['controllers']['perfil'] = [
            'success' => false,
            'error' => 'No hay conexión a BD'
        ];
    }
}

$diagnostico['config']['php'] = [
    'display_errors' => ini_get('display_errors'),
    'display_startup_errors' => ini_get('display_startup_errors'),
    'error_reporting' => ini_get('error_reporting'),
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
    'error_log_writable' => is_writable(dirname(ini_get('error_log') ?: 'C:/xampp/php/logs')),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'post_max_size' => ini_get('post_max_size'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'date_timezone' => ini_get('date.timezone') ?: 'No definido',
];

$diagnostico['config']['extensions'] = get_loaded_extensions();
$diagnostico['config']['oci8_loaded'] = extension_loaded('oci8');
$diagnostico['config']['pdo_oci_loaded'] = extension_loaded('pdo_oci');

if (!$diagnostico['config']['oci8_loaded']) {
    $diagnostico['recommendations'][] = '❌ La extensión OCI8 de PHP no está cargada. Es necesaria para conectar con Oracle.';
}

$diagnostico['environment'] = [
    'os' => PHP_OS,
    'os_family' => PHP_OS_FAMILY,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'http_host' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'https' => isset($_SERVER['HTTPS']) ? 'On' : 'Off',
];

$logPath = ini_get('error_log');
if ($logPath && file_exists($logPath)) {
    $logContent = file($logPath);
    $logLines = count($logContent);
    $lastErrors = array_slice($logContent, -50);

    $chapin_errors = array_filter($lastErrors, function ($line) {
        return stripos($line, 'chapinmarket') !== false || stripos($line, 'ORA-') !== false;
    });

    $diagnostico['logs']['php_errors'] = [
        'path' => $logPath,
        'size_bytes' => filesize($logPath),
        'total_lines' => $logLines,
        'last_50_lines' => $lastErrors,
        'chapin_related_errors' => array_values($chapin_errors),
        'has_recent_errors' => !empty(preg_grep('/error/i', $lastErrors))
    ];
} else {
    $diagnostico['logs']['php_errors'] = [
        'path' => $logPath ?: 'No definido',
        'accessible' => false,
        'message' => 'No se pudo acceder al log de errores de PHP'
    ];
}

$apacheLogPath = 'C:/xampp/apache/logs/error.log';
if (file_exists($apacheLogPath)) {
    $apacheLogContent = file($apacheLogPath);
    $lastApacheErrors = array_slice($apacheLogContent, -30);

    $chapin_apache_errors = array_filter($lastApacheErrors, function ($line) {
        return stripos($line, 'chapinmarket') !== false || stripos($line, 'PHP') !== false;
    });

    $diagnostico['logs']['apache_errors'] = [
        'path' => $apacheLogPath,
        'size_bytes' => filesize($apacheLogPath),
        'last_30_lines' => $lastApacheErrors,
        'chapin_related_errors' => array_values($chapin_apache_errors)
    ];
} else {
    $diagnostico['logs']['apache_errors'] = [
        'path' => $apacheLogPath,
        'accessible' => false,
        'message' => 'No se pudo acceder al log de Apache'
    ];
}

$endpoints_to_test = [
    '/categorias' => 'GET',
    '/productos' => 'GET',
    '/auth/me' => 'GET',
];

$diagnostico['endpoint_tests'] = [];
foreach ($endpoints_to_test as $endpoint => $method) {
    $url = 'http://localhost/chapinmarket-backend/public' . $endpoint;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookies.txt');

    $start = microtime(true);
    $response = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000, 2);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $diagnostico['endpoint_tests'][$endpoint] = [
        'method' => $method,
        'url' => $url,
        'http_code' => $http_code,
        'time_ms' => $time,
        'success' => $http_code >= 200 && $http_code < 400,
        'error' => $error ?: null
    ];
}

$cors_test_url = 'http://localhost/chapinmarket-backend/public/categorias';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $cors_test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Origin: http://localhost']);
$response = curl_exec($ch);
$headers = substr($response, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
curl_close($ch);

$diagnostico['cors'] = [
    'access_control_allow_origin' => preg_match('/Access-Control-Allow-Origin: (.*)/i', $headers, $matches) ? $matches[1] : 'No presente',
    'access_control_allow_credentials' => preg_match('/Access-Control-Allow-Credentials: (.*)/i', $headers, $matches) ? $matches[1] : 'No presente',
    'access_control_allow_methods' => preg_match('/Access-Control-Allow-Methods: (.*)/i', $headers, $matches) ? $matches[1] : 'No presente',
];

if (stripos($headers, 'Access-Control-Allow-Origin: http://localhost') === false) {
    $diagnostico['recommendations'][] = '⚠️ CORS no configurado correctamente - verificar headers en index.php';
}

$diagnostico['summary'] = [
    'total_checks' => 0,
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'critical_issues' => 0
];

if (isset($diagnostico['database']['connection']['success'])) {
    $diagnostico['summary']['total_checks']++;
    $diagnostico['database']['connection']['success'] ? $diagnostico['summary']['passed']++ : $diagnostico['summary']['failed']++;
}

foreach ($diagnostico['database']['tables'] ?? [] as $info) {
    $diagnostico['summary']['total_checks']++;
    $info['exists'] ? $diagnostico['summary']['passed']++ : $diagnostico['summary']['failed']++;
}

$diagnostico['summary']['total_checks']++;
$diagnostico['session']['logged_in'] ? $diagnostico['summary']['passed']++ : $diagnostico['summary']['failed']++;

if (isset($diagnostico['config']['oci8_loaded'])) {
    $diagnostico['summary']['total_checks']++;
    $diagnostico['config']['oci8_loaded'] ? $diagnostico['summary']['passed']++ : $diagnostico['summary']['failed']++;
}

$diagnostico['summary']['warnings'] = count($diagnostico['recommendations']);
$diagnostico['summary']['critical_issues'] = count(array_filter($diagnostico['recommendations'], function ($r) {
    return strpos($r, '❌') !== false;
}));

$diagnostico['php_errors_captured'] = $php_errors;

restore_error_handler();
if ($mode === 'json' && !$diagnostico['session']['logged_in']) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($diagnostico, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($mode === 'silent') {
    $success = $diagnostico['session']['logged_in'] &&
        isset($diagnostico['database']['connection']['success']) &&
        $diagnostico['database']['connection']['success'];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'recommendations' => count($diagnostico['recommendations'])]);
    exit;
}

// Si hay sesión activa, forzar modo HTML
if ($diagnostico['session']['logged_in']) {
    $mode = 'html';
}

// ==================== SALIDA HTML MEJORADA ====================
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Diagnóstico Avanzado - ChapínMarket v3.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f1f5f9;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .badge-pass {
            background: #22c55e;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-fail {
            background: #ef4444;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-warning {
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-info {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }

        pre {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 12px;
            overflow-x: auto;
            font-size: 12px;
        }

        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #22c55e;
            transition: width 0.3s;
        }

        h2 {
            color: #0f172a;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-header {
            cursor: pointer;
            user-select: none;
        }

        .section-content {
            display: none;
            margin-top: 16px;
        }

        .section-content.open {
            display: block;
        }

        .metric-card {
            transition: transform 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
        }

        .status-icon {
            font-size: 2rem;
        }
    </style>
</head>

<body class="p-6">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="card">
            <div class="flex justify-between items-start flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-chapinAzul">🔧 ChapínMarket - Diagnóstico Ultra Premium</h1>
                    <p class="text-slate-500 mt-1"><?php echo $diagnostico['timestamp']; ?></p>
                    <p class="text-xs text-slate-400 mt-1">PHP <?php echo phpversion(); ?> | <?php echo $diagnostico['environment']['os']; ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="?mode=json" target="_blank" class="bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-lg text-sm">📄 Ver JSON</a>
                    <a href="?mode=debug" class="bg-purple-100 hover:bg-purple-200 px-4 py-2 rounded-lg text-sm">🐛 Modo Debug</a>
                    <button onclick="window.location.reload()" class="bg-chapinAzul text-white px-4 py-2 rounded-lg text-sm ml-2">🔄 Actualizar</button>
                </div>
            </div>

            <!-- Resumen rápido -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-6">
                <div class="bg-green-50 rounded-xl p-4 text-center metric-card">
                    <div class="text-2xl font-bold text-green-600"><?php echo $diagnostico['summary']['passed']; ?></div>
                    <div class="text-xs text-green-600">Pruebas exitosas</div>
                </div>
                <div class="bg-red-50 rounded-xl p-4 text-center metric-card">
                    <div class="text-2xl font-bold text-red-600"><?php echo $diagnostico['summary']['failed']; ?></div>
                    <div class="text-xs text-red-600">Pruebas fallidas</div>
                </div>
                <div class="bg-orange-50 rounded-xl p-4 text-center metric-card">
                    <div class="text-2xl font-bold text-orange-600"><?php echo $diagnostico['summary']['warnings']; ?></div>
                    <div class="text-xs text-orange-600">Recomendaciones</div>
                </div>
                <div class="bg-red-100 rounded-xl p-4 text-center metric-card">
                    <div class="text-2xl font-bold text-red-700"><?php echo $diagnostico['summary']['critical_issues']; ?></div>
                    <div class="text-xs text-red-700">Problemas críticos</div>
                </div>
                <div class="bg-blue-50 rounded-xl p-4 text-center metric-card">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $diagnostico['summary']['total_checks']; ?></div>
                    <div class="text-xs text-blue-600">Total verificaciones</div>
                </div>
            </div>

            <!-- Barra de progreso -->
            <div class="mt-4">
                <div class="progress-bar">
                    <?php
                    $percent = $diagnostico['summary']['total_checks'] > 0
                        ? ($diagnostico['summary']['passed'] / $diagnostico['summary']['total_checks']) * 100
                        : 0;
                    ?>
                    <div class="progress-fill" style="width: <?php echo $percent; ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-slate-500 mt-1">
                    <span>Estado general del sistema</span>
                    <span><?php echo round($percent); ?>%</span>
                </div>
            </div>
        </div>

        <!-- Recomendaciones -->
        <?php if (!empty($diagnostico['recommendations'])): ?>
            <div class="card bg-orange-50 border-l-4 border-orange-500">
                <h2>💡 Recomendaciones (<?php echo count($diagnostico['recommendations']); ?>)</h2>
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($diagnostico['recommendations'] as $rec): ?>
                        <li class="text-sm <?php echo strpos($rec, '❌') !== false ? 'text-red-700 font-semibold' : (strpos($rec, '⚠️') !== false ? 'text-orange-700' : 'text-orange-800'); ?>">
                            <?php echo htmlspecialchars($rec); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Errores PHP capturados -->
        <?php if (!empty($php_errors)): ?>
            <div class="card bg-red-50 border-l-4 border-red-500">
                <h2>⚠️ Errores PHP Detectados</h2>
                <div class="space-y-2">
                    <?php foreach ($php_errors as $err): ?>
                        <div class="text-sm text-red-700 font-mono">
                            [<?php echo $err['type']; ?>] <?php echo htmlspecialchars($err['message']); ?>
                            en <?php echo $err['file']; ?>:<?php echo $err['line']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Sesión -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('session-content')">
                <h2>
                    <span class="status-icon"><?php echo $diagnostico['session']['logged_in'] ? '✅' : '❌'; ?></span>
                    Estado de la Sesión
                    <span class="text-slate-400 text-sm ml-2">▼</span>
                </h2>
            </div>
            <div id="session-content" class="section-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div><span class="font-semibold">Session ID:</span> <?php echo htmlspecialchars($diagnostico['session']['id']); ?></div>
                    <div><span class="font-semibold">Session Name:</span> <?php echo htmlspecialchars($diagnostico['session']['name']); ?></div>
                    <div><span class="font-semibold">Estado:</span> <?php echo $diagnostico['session']['status_text']; ?></div>
                    <div><span class="font-semibold">Usuario logueado:</span> <?php echo $diagnostico['session']['logged_in'] ? 'Sí' : 'No'; ?></div>
                    <?php if ($diagnostico['session']['logged_in']): ?>
                        <div><span class="font-semibold">Usuario ID:</span> <?php echo $diagnostico['session']['user_id']; ?></div>
                        <div><span class="font-semibold">Nombre:</span> <?php echo htmlspecialchars($diagnostico['session']['user_name']); ?></div>
                        <div><span class="font-semibold">Es Admin:</span> <?php echo $diagnostico['session']['is_admin'] ? 'Sí' : 'No'; ?></div>
                    <?php endif; ?>
                    <div><span class="font-semibold">Cookie de sesión presente:</span> <?php echo $diagnostico['session']['has_session_cookie'] ? '✅ Sí' : '❌ No'; ?></div>
                </div>
                <pre><?php echo htmlspecialchars(print_r($diagnostico['session']['data'], true)); ?></pre>
            </div>
        </div>

        <!-- Base de Datos -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('db-content')">
                <h2>
                    <span class="status-icon"><?php echo isset($diagnostico['database']['connection']['success']) && $diagnostico['database']['connection']['success'] ? '✅' : '❌'; ?></span>
                    Base de Datos Oracle
                    <span class="text-slate-400 text-sm ml-2">▼</span>
                </h2>
            </div>
            <div id="db-content" class="section-content">
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                    <div><span class="font-semibold">Conexión:</span> <?php echo $diagnostico['database']['connection']['time_ms'] ?? 'N/A'; ?> ms</div>
                    <div><span class="font-semibold">Usuario BD:</span> <?php echo htmlspecialchars($diagnostico['database']['session_info']['current_user'] ?? 'N/A'); ?></div>
                    <div><span class="font-semibold">Fecha BD:</span> <?php echo htmlspecialchars($diagnostico['database']['session_info']['current_date'] ?? 'N/A'); ?></div>
                    <div><span class="font-semibold">Oracle Version:</span> <?php echo htmlspecialchars(substr($diagnostico['database']['oracle_version'] ?? 'N/A', 0, 50)); ?></div>
                </div>

                <h3 class="font-semibold mb-2 mt-4">📊 Tablas</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-2 text-left">Tabla</th>
                                <th class="p-2 text-left">Descripción</th>
                                <th class="p-2 text-left">Estado</th>
                                <th class="p-2 text-left">Registros</th>
                                <th class="p-2 text-left">Columnas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostico['database']['tables'] ?? [] as $table => $info): ?>
                                <tr class="border-b">
                                    <td class="p-2 font-mono"><?php echo htmlspecialchars($table); ?></td>
                                    <td class="p-2 text-xs text-slate-500"><?php echo htmlspecialchars($info['description'] ?? ''); ?></td>
                                    <td class="p-2"><?php echo $info['exists'] ? '<span class="badge-pass">OK</span>' : '<span class="badge-fail">FALTA</span>'; ?></td>
                                    <td class="p-2"><?php echo $info['row_count'] ?? 'N/A'; ?></td>
                                    <td class="p-2"><?php echo $info['column_count'] ?? 'N/A'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="font-semibold mb-2 mt-4">🔗 Triggers</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-2 text-left">Trigger</th>
                                <th class="p-2 text-left">Tabla</th>
                                <th class="p-2 text-left">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostico['database']['triggers'] ?? [] as $trigger => $info): ?>
                                <tr class="border-b">
                                    <td class="p-2 font-mono"><?php echo htmlspecialchars($trigger); ?></td>
                                    <td class="p-2"><?php echo $info['table'] ?? 'N/A'; ?></td>
                                    <td class="p-2"><?php echo $info['exists'] ? ($info['status'] === 'ENABLED' ? '<span class="badge-pass">ENABLED</span>' : '<span class="badge-warning">' . $info['status'] . '</span>') : '<span class="badge-fail">NO EXISTE</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (isset($diagnostico['database']['test_insert_direccion'])): ?>
                    <h3 class="font-semibold mb-2 mt-4">🧪 Prueba de INSERT - Direcciones</h3>
                    <div class="p-3 rounded-lg <?php echo $diagnostico['database']['test_insert_direccion']['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $diagnostico['database']['test_insert_direccion']['success'] ? '✅ INSERT exitoso' : '❌ ' . htmlspecialchars($diagnostico['database']['test_insert_direccion']['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($diagnostico['database']['test_insert_tarjeta'])): ?>
                    <h3 class="font-semibold mb-2 mt-4">🧪 Prueba de INSERT - Tarjetas</h3>
                    <div class="p-3 rounded-lg <?php echo $diagnostico['database']['test_insert_tarjeta']['success'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo $diagnostico['database']['test_insert_tarjeta']['success'] ? '✅ INSERT exitoso' : '❌ ' . htmlspecialchars($diagnostico['database']['test_insert_tarjeta']['error']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Controladores -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('controllers-content')">
                <h2><span>🎮</span> Controladores API <span class="text-slate-400 text-sm ml-2">▼</span></h2>
            </div>
            <div id="controllers-content" class="section-content">
                <div class="space-y-4">
                    <?php foreach ($diagnostico['controllers'] ?? [] as $name => $info): ?>
                        <div class="border rounded-lg p-3">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold"><?php echo ucfirst($name); ?>Controller</span>
                                <?php if ($info['success']): ?>
                                    <span class="badge-pass">Funciona</span>
                                <?php else: ?>
                                    <span class="badge-fail">Error</span>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($info['response_summary'])): ?>
                                <div class="text-xs text-slate-500 mt-1">
                                    Usuario: <?php echo $info['response_summary']['user_name']; ?> |
                                    Direcciones: <?php echo $info['response_summary']['direcciones_count']; ?> |
                                    Tarjetas: <?php echo $info['response_summary']['tarjetas_count']; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($info['error'])): ?>
                                <p class="text-red-600 text-sm mt-2"><?php echo htmlspecialchars($info['error']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Endpoints -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('endpoints-content')">
                <h2><span>🌐</span> Pruebas de Endpoints <span class="text-slate-400 text-sm ml-2">▼</span></h2>
            </div>
            <div id="endpoints-content" class="section-content">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-2 text-left">Endpoint</th>
                                <th class="p-2 text-left">Método</th>
                                <th class="p-2 text-left">Status</th>
                                <th class="p-2 text-left">Tiempo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostico['endpoint_tests'] ?? [] as $endpoint => $test): ?>
                                <tr class="border-b">
                                    <td class="p-2 font-mono"><?php echo htmlspecialchars($endpoint); ?></td>
                                    <td class="p-2"><?php echo $test['method']; ?></td>
                                    <td class="p-2"><?php echo $test['success'] ? '<span class="badge-pass">' . $test['http_code'] . '</span>' : '<span class="badge-fail">' . $test['http_code'] . '</span>'; ?></td>
                                    <td class="p-2"><?php echo $test['time_ms']; ?> ms</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Configuración -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('config-content')">
                <h2><span>⚙️</span> Configuración PHP <span class="text-slate-400 text-sm ml-2">▼</span></h2>
            </div>
            <div id="config-content" class="section-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <?php foreach ($diagnostico['config']['php'] as $key => $value): ?>
                        <div><span class="font-semibold"><?php echo $key; ?>:</span> <?php echo htmlspecialchars((string)$value); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                    <p><span class="font-semibold">OCI8 cargada:</span> <?php echo $diagnostico['config']['oci8_loaded'] ? '✅ Sí' : '❌ No'; ?></p>
                    <p><span class="font-semibold">PDO_OCI cargado:</span> <?php echo $diagnostico['config']['pdo_oci_loaded'] ? '✅ Sí' : '❌ No'; ?></p>
                    <p><span class="font-semibold">Extensiones cargadas:</span> <?php echo count($diagnostico['config']['extensions']); ?> extensiones</p>
                </div>
            </div>
        </div>

        <!-- Logs -->
        <div class="card">
            <div class="section-header" onclick="toggleSection('logs-content')">
                <h2><span>📋</span> Logs de Errores <span class="text-slate-400 text-sm ml-2">▼</span></h2>
            </div>
            <div id="logs-content" class="section-content">
                <h3 class="font-semibold mb-2">PHP Error Log</h3>
                <pre><?php
                        if (isset($diagnostico['logs']['php_errors']['chapin_related_errors']) && !empty($diagnostico['logs']['php_errors']['chapin_related_errors'])) {
                            echo htmlspecialchars(implode('', $diagnostico['logs']['php_errors']['chapin_related_errors']));
                        } elseif (isset($diagnostico['logs']['php_errors']['last_50_lines'])) {
                            echo htmlspecialchars(implode('', array_slice($diagnostico['logs']['php_errors']['last_50_lines'], -20)));
                        } else {
                            echo 'No se encontraron errores de PHP relacionados con ChapínMarket o no se pudo leer el log';
                        }
                        ?></pre>

                <h3 class="font-semibold mt-4 mb-2">Apache Error Log</h3>
                <pre><?php
                        if (isset($diagnostico['logs']['apache_errors']['chapin_related_errors']) && !empty($diagnostico['logs']['apache_errors']['chapin_related_errors'])) {
                            echo htmlspecialchars(implode('', $diagnostico['logs']['apache_errors']['chapin_related_errors']));
                        } elseif (isset($diagnostico['logs']['apache_errors']['last_30_lines'])) {
                            echo htmlspecialchars(implode('', array_slice($diagnostico['logs']['apache_errors']['last_30_lines'], -15)));
                        } else {
                            echo 'No se encontraron errores de Apache relacionados con ChapínMarket';
                        }
                        ?></pre>
            </div>
        </div>
    </div>

    <script>
        function toggleSection(id) {
            const element = document.getElementById(id);
            element.classList.toggle('open');
        }

        // Abrir secciones con errores por defecto
        <?php if ($diagnostico['summary']['failed'] > 0 || $diagnostico['summary']['critical_issues'] > 0): ?>
            document.querySelectorAll('.section-content').forEach(el => el.classList.add('open'));
        <?php else: ?>
            document.getElementById('db-content').classList.add('open');
            document.getElementById('session-content').classList.add('open');
        <?php endif; ?>
    </script>
</body>

</html>