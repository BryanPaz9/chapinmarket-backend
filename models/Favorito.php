<?php
require_once 'BaseModel.php';

class Favorito extends BaseModel
{
    /**
     * Obtiene los IDs de productos favoritos del usuario.
     * @param int $usuarioId
     * @return int[]
     */
    public function getByUsuario($usuarioId)
    {
        $sql = "SELECT producto_id FROM favoritos WHERE usuario_id = :p_uid ORDER BY fecha DESC";
        $stid = $this->execute($sql, [":p_uid" => (int)$usuarioId]);

        $favoritos = [];
        while ($row = oci_fetch_assoc($stid)) {
            $favoritos[] = (int)$row['PRODUCTO_ID'];
        }
        return $favoritos;
    }

    public function add($usuarioId, $productoId)
    {
        // Verificar si ya existe
        $sqlCheck = "SELECT COUNT(*) AS cnt FROM favoritos WHERE usuario_id = :p_uid AND producto_id = :p_pid";
        $stidCheck = $this->execute($sqlCheck, [
            ":p_uid" => (int)$usuarioId,
            ":p_pid" => (int)$productoId
        ]);
        $row = oci_fetch_assoc($stidCheck);
        if ($row['CNT'] > 0) {
            return true;
        }

        $sql = "INSERT INTO favoritos (usuario_id, producto_id) VALUES (:p_uid, :p_pid)";
        $this->execute($sql, [
            ":p_uid" => (int)$usuarioId,
            ":p_pid" => (int)$productoId
        ]);
        return true;
    }

    public function remove($usuarioId, $productoId)
    {
        $sql = "DELETE FROM favoritos WHERE usuario_id = :p_uid AND producto_id = :p_pid";
        $this->execute($sql, [
            ":p_uid" => (int)$usuarioId,
            ":p_pid" => (int)$productoId
        ]);
        return true;
    }
}