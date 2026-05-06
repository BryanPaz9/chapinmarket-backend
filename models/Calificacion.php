<?php
require_once 'BaseModel.php';

class Calificacion extends BaseModel
{
    public function getInfo($productoId, $usuarioId = null)
    {
        $sqlAvg = "SELECT ROUND(AVG(calificacion), 1) AS promedio, COUNT(*) AS total
                   FROM calificaciones
                   WHERE producto_id = :1";
        $stid = $this->execute($sqlAvg, [":1" => (int)$productoId]);
        $avgRow = oci_fetch_assoc($stid);

        $data = [
            'promedio'       => $avgRow['PROMEDIO'] !== null ? (float)$avgRow['PROMEDIO'] : 0,
            'total'          => (int)($avgRow['TOTAL'] ?? 0),
            'miCalificacion' => null
        ];

        if ($usuarioId) {
            $sqlUser = "SELECT calificacion FROM calificaciones
                        WHERE producto_id = :1 AND usuario_id = :2";
            $stidUser = $this->execute($sqlUser, [
                ":1" => (int)$productoId,
                ":2" => (int)$usuarioId
            ]);
            $userRow = oci_fetch_assoc($stidUser);
            $data['miCalificacion'] = $userRow ? (int)$userRow['CALIFICACION'] : null;
        }

        return $data;
    }

    public function saveOrUpdate($usuarioId, $productoId, $calificacion)
    {
        $usuarioId    = (int)$usuarioId;
        $productoId   = (int)$productoId;
        $calificacion = (int)$calificacion;

        if ($calificacion < 1 || $calificacion > 5) {
            throw new Exception("Calificación fuera de rango (1-5)");
        }

        // 1. Intentar actualizar fila existente
        $sqlUpd = "UPDATE calificaciones
                      SET calificacion = :1,
                          fecha        = SYSDATE
                    WHERE producto_id  = :2
                      AND usuario_id   = :3";
        $stidUpd = oci_parse($this->conn, $sqlUpd);
        if (!$stidUpd) {
            $e = oci_error($this->conn);
            throw new Exception("Error preparando UPDATE de calificación: " . ($e['message'] ?? 'desconocido'));
        }
        oci_bind_by_name($stidUpd, ":1", $calificacion, -1, SQLT_INT);
        oci_bind_by_name($stidUpd, ":2", $productoId,   -1, SQLT_INT);
        oci_bind_by_name($stidUpd, ":3", $usuarioId,    -1, SQLT_INT);

        if (!oci_execute($stidUpd)) {
            $e = oci_error($stidUpd);
            throw new Exception("Error ejecutando UPDATE de calificación: " . ($e['message'] ?? 'desconocido'));
        }

        if (oci_num_rows($stidUpd) === 0) {
            $sqlIns = "INSERT INTO calificaciones (producto_id, usuario_id, calificacion, fecha)
                       VALUES (:1, :2, :3, SYSDATE)";
            $stidIns = oci_parse($this->conn, $sqlIns);
            if (!$stidIns) {
                $e = oci_error($this->conn);
                throw new Exception("Error preparando INSERT de calificación: " . ($e['message'] ?? 'desconocido'));
            }
            oci_bind_by_name($stidIns, ":1", $productoId,   -1, SQLT_INT);
            oci_bind_by_name($stidIns, ":2", $usuarioId,    -1, SQLT_INT);
            oci_bind_by_name($stidIns, ":3", $calificacion, -1, SQLT_INT);

            if (!oci_execute($stidIns)) {
                $e = oci_error($stidIns);
                throw new Exception("Error ejecutando INSERT de calificación: " . ($e['message'] ?? 'desconocido'));
            }
        }

        return true;
    }
}