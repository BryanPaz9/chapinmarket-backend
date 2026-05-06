<?php
require_once __DIR__ . '/../config/database.php';

class BaseModel
{
    protected $conn;

    public function __construct()
    {
        $this->conn = Database::connect();
    }

    /**
     * @throws Exception
     */
    protected function execute($sql, $params = [])
    {
        $stid = oci_parse($this->conn, $sql);
        if (!$stid) {
            $e = oci_error($this->conn);
            throw new Exception("Error al preparar la consulta: " . ($e['message'] ?? 'Error desconocido'));
        }

        $bindings = [];

        foreach ($params as $placeholder => &$value) {
            oci_bind_by_name($stid, $placeholder, $value);
            $bindings[] = &$value;
        }
        unset($value);

        $result = oci_execute($stid);
        if (!$result) {
            $e = oci_error($stid);
            $errorMsg = "Error SQL: " . ($e['message'] ?? 'Error desconocido') . " | Código: " . ($e['code'] ?? 'N/A');
            error_log($errorMsg . " | SQL: $sql");
            throw new Exception($errorMsg);
        }

        return $stid;
    }
}