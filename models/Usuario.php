<?php
require_once 'BaseModel.php';

class Usuario extends BaseModel {

    public function getAll() {
        $sql = "SELECT * FROM usuarios";
        $stid = $this->execute($sql);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getById($id) {
        $sql = "SELECT * FROM usuarios WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null; // 👈 aquí
    }



    public function getByCorreo($correo) {
        $sql = "SELECT * FROM usuarios WHERE correo = :correo";
        $stid = $this->execute($sql, [":correo" => $correo]);

        $result = oci_fetch_assoc($stid);

        return $result ?: null;
    }

    public function create($data) {
        $sql = "INSERT INTO usuarios (nombre, correo, contrasena, direccion, es_admin)
                VALUES (:nombre, :correo, :contrasena, :direccion, :es_admin)";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":correo" => $data['correo'],
            ":contrasena" => password_hash($data['contrasena'], PASSWORD_DEFAULT),
            ":direccion" => $data['direccion'] ?? null,
            ":es_admin" => $data['es_admin'] ?? 0
        ]);

        return true;
    }

    public function validarCredenciales($correo, $contrasena) {
        $usuario = $this->getByCorreo($correo);

        if (!$usuario || !isset($usuario['CONTRASENA'])) {
            return null;
        }

        $contrasenaGuardada = $usuario['CONTRASENA'];
        $infoHash = password_get_info($contrasenaGuardada);
        $esValida = password_verify($contrasena, $contrasenaGuardada)
            || ($infoHash['algo'] === 0 && hash_equals($contrasenaGuardada, $contrasena));

        if (!$esValida) {
            return null;
        }

        unset($usuario['CONTRASENA']);

        return $usuario;
    }

    public function update($id, $data) {
        $sql = "UPDATE usuarios
                SET nombre = :nombre,
                    correo = :correo,
                    direccion = :direccion
                WHERE id = :id";

        $this->execute($sql, [
            ":nombre" => $data['nombre'],
            ":correo" => $data['correo'],
            ":direccion" => $data['direccion'],
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id) {
        $sql = "DELETE FROM usuarios WHERE id = :id";
        $this->execute($sql, [":id" => $id]);

        return true;
    }
}
