<?php
require_once __DIR__ . '/../config/database.php';

class BaseModel {
    protected $conn;

    public function __construct() {
        $this->conn = Database::connect();
    }

protected function execute($sql, $params = []) {
    $stid = oci_parse($this->conn, $sql);

    foreach ($params as $key => $value) {
        oci_bind_by_name($stid, $key, $params[$key]);
    }

    $result = @oci_execute($stid);

    if (!$result) {
        $e = oci_error($stid);

        Response::error(
            "Error en base de datos",
            500,
            $e['message']
        );
    }

    return $stid;
}
}