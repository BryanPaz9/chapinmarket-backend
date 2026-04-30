<?php
require_once 'BaseModel.php';

class Tarjeta extends BaseModel {

    public function getAll() {
        $sql = "SELECT * FROM tarjetas";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getAllByUsuario($usuarioId) {
        $sql = "SELECT id, usuario_id, titular, numero_enmascarado, tipo, vencimiento
                FROM tarjetas
                WHERE usuario_id = :usuario_id
                ORDER BY id DESC";
        $stid = $this->execute($sql, [":usuario_id" => $usuarioId]);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id) {
        $sql = "SELECT * FROM tarjetas WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function getByIdForUsuario($id, $usuarioId) {
        $sql = "SELECT id, usuario_id, titular, numero_enmascarado, tipo, vencimiento
                FROM tarjetas
                WHERE id = :id AND usuario_id = :usuario_id";
        $stid = $this->execute($sql, [
            ":id" => $id,
            ":usuario_id" => $usuarioId
        ]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function create($data, $usuarioId = null) {
        $sql = "INSERT INTO tarjetas (usuario_id, titular, numero_enmascarado, tipo, vencimiento)
                VALUES (:usuario_id, :titular, :numero_enmascarado, :tipo, :vencimiento)";

        $this->execute($sql, [
            ":usuario_id" => $usuarioId ?? $data['usuario_id'],
            ":titular" => $data['titular'],
            ":numero_enmascarado" => $data['numero_enmascarado'],
            ":tipo" => $data['tipo'],
            ":vencimiento" => $data['vencimiento']
        ]);

        return true;
    }

    public function update($id, $data) {
        $sql = "UPDATE tarjetas
                SET titular = :titular,
                    numero_enmascarado = :numero_enmascarado,
                    tipo = :tipo,
                    vencimiento = :vencimiento
                WHERE id = :id";

        $this->execute($sql, [
            ":titular" => $data['titular'],
            ":numero_enmascarado" => $data['numero_enmascarado'],
            ":tipo" => $data['tipo'],
            ":vencimiento" => $data['vencimiento'],
            ":id" => $id
        ]);

        return true;
    }

    public function updateForUsuario($id, $usuarioId, $data) {
        $sql = "UPDATE tarjetas
                SET titular = :titular,
                    numero_enmascarado = :numero_enmascarado,
                    tipo = :tipo,
                    vencimiento = :vencimiento
                WHERE id = :id AND usuario_id = :usuario_id";

        $this->execute($sql, [
            ":titular" => $data['titular'],
            ":numero_enmascarado" => $data['numero_enmascarado'],
            ":tipo" => $data['tipo'],
            ":vencimiento" => $data['vencimiento'],
            ":id" => $id,
            ":usuario_id" => $usuarioId
        ]);

        return true;
    }

    public function delete($id) {
        $sql = "DELETE FROM tarjetas WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }

    public function deleteForUsuario($id, $usuarioId) {
        $sql = "DELETE FROM tarjetas WHERE id = :id AND usuario_id = :usuario_id";
        $stid = $this->execute($sql, [
            ":id" => $id,
            ":usuario_id" => $usuarioId
        ]);

        return oci_num_rows($stid) > 0;
    }
}
