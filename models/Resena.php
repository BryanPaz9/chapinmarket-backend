<?php
require_once 'BaseModel.php';

class Resena extends BaseModel
{
    public function getByProducto($productoId)
    {
        $sql = "SELECT r.id, r.producto_id, r.usuario_id, r.comentario,
                       TO_CHAR(r.fecha, 'YYYY-MM-DD HH24:MI:SS') AS fecha,
                       u.nombre AS usuario_nombre
                FROM resenas r
                JOIN usuarios u ON u.id = r.usuario_id
                WHERE r.producto_id = :1
                ORDER BY r.fecha DESC";
        $stid = $this->execute($sql, [":1" => (int)$productoId]);

        $resenas = [];
        while ($row = oci_fetch_assoc($stid)) {
            $resenas[] = [
                'id'            => (int)$row['ID'],
                'productoId'    => (int)$row['PRODUCTO_ID'],
                'usuarioId'     => (int)$row['USUARIO_ID'],
                'comentario'    => $row['COMENTARIO'],
                'fecha'         => $row['FECHA'],
                'usuarioNombre' => $row['USUARIO_NOMBRE']
            ];
        }
        return $resenas;
    }

    public function create($usuarioId, $productoId, $comentario)
    {
        $usuarioId  = (int)$usuarioId;
        $productoId = (int)$productoId;
        $comentario = trim($comentario);

        if (empty($comentario)) {
            throw new Exception("El comentario no puede estar vacío");
        }
        if (strlen($comentario) > 500) {
            throw new Exception("El comentario no puede superar 500 caracteres");
        }

        $sqlIns = "INSERT INTO resenas (producto_id, usuario_id, comentario, fecha)
                   VALUES (:1, :2, :3, SYSDATE)";
        $stidIns = oci_parse($this->conn, $sqlIns);
        if (!$stidIns) {
            $e = oci_error($this->conn);
            throw new Exception("Error preparando INSERT de reseña: " . ($e['message'] ?? 'desconocido'));
        }

        oci_bind_by_name($stidIns, ":1", $productoId, -1, SQLT_INT);
        oci_bind_by_name($stidIns, ":2", $usuarioId,  -1, SQLT_INT);
        oci_bind_by_name($stidIns, ":3", $comentario, 500, SQLT_CHR);

        if (!oci_execute($stidIns)) {
            $e = oci_error($stidIns);
            throw new Exception("Error al crear reseña: " . ($e['message'] ?? 'desconocido'));
        }

        $newId = 0;
        try {
            $sqlCurr  = "SELECT SEQ_RESENA.CURRVAL AS nuevo_id FROM DUAL";
            $stidCurr = oci_parse($this->conn, $sqlCurr);
            if ($stidCurr && oci_execute($stidCurr)) {
                $row   = oci_fetch_assoc($stidCurr);
                $newId = isset($row['NUEVO_ID']) ? (int)$row['NUEVO_ID'] : 0;
            }
        } catch (Exception $ignored) {
            $sqlFb  = "SELECT id FROM resenas
                        WHERE usuario_id  = :1
                          AND producto_id = :2
                        ORDER BY fecha DESC
                        FETCH FIRST 1 ROWS ONLY";
            $stidFb = oci_parse($this->conn, $sqlFb);
            if ($stidFb) {
                oci_bind_by_name($stidFb, ":1", $usuarioId,  -1, SQLT_INT);
                oci_bind_by_name($stidFb, ":2", $productoId, -1, SQLT_INT);
                if (oci_execute($stidFb)) {
                    $rowFb = oci_fetch_assoc($stidFb);
                    $newId = isset($rowFb['ID']) ? (int)$rowFb['ID'] : 0;
                }
            }
        }

        return $newId;
    }
}