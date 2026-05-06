<?php
require_once 'BaseModel.php';

class Direccion extends BaseModel
{

    public function getByUsuario($usuarioId)
    {
        $sql = "SELECT * FROM direcciones WHERE usuario_id = :usuario_id ORDER BY es_predeterminada DESC, id ASC";
        $stid = $this->execute($sql, [":usuario_id" => $usuarioId]);

        $data = [];
        while ($row = oci_fetch_assoc($stid)) {
            $data[] = [
                'id' => (int)$row['ID'],
                'usuarioId' => (int)$row['USUARIO_ID'],
                'etiqueta' => $row['ETIQUETA'],
                'linea1' => $row['LINEA1'],
                'linea2' => $row['LINEA2'] ?? '',
                'ciudad' => $row['CIUDAD'] ?? 'Ciudad de Guatemala',
                'departamento' => $row['DEPARTAMENTO'] ?? 'Guatemala',
                'codigoPostal' => $row['CODIGO_POSTAL'] ?? '',
                'esPredeterminada' => (int)($row['ES_PREDETERMINADA'] ?? 0)
            ];
        }
        return $data;
    }

    public function getById($id)
    {
        $sql = "SELECT * FROM direcciones WHERE id = :id";
        $stid = $this->execute($sql, [":id" => $id]);
        $result = oci_fetch_assoc($stid);
        return $result ?: null;
    }

    public function create($data)
    {
        if (!empty($data['esPredeterminada'])) {
            $sql = "UPDATE direcciones SET es_predeterminada = 0 WHERE usuario_id = :usuario_id";
            $this->execute($sql, [":usuario_id" => $data['usuarioId']]);
        }

        $sql = "INSERT INTO direcciones (usuario_id, etiqueta, linea1, linea2, ciudad, departamento, codigo_postal, es_predeterminada)
                VALUES (:usuario_id, :etiqueta, :linea1, :linea2, :ciudad, :departamento, :codigo_postal, :es_predeterminada)";

        $this->execute($sql, [
            ":usuario_id" => $data['usuarioId'],
            ":etiqueta" => $data['etiqueta'] ?? 'Casa',
            ":linea1" => $data['linea1'],
            ":linea2" => $data['linea2'] ?? null,
            ":ciudad" => $data['ciudad'] ?? 'Ciudad de Guatemala',
            ":departamento" => $data['departamento'] ?? 'Guatemala',
            ":codigo_postal" => $data['codigoPostal'] ?? null,
            ":es_predeterminada" => $data['esPredeterminada'] ?? 0
        ]);

        return true;
    }

    public function update($id, $data)
    {
        if (!empty($data['esPredeterminada'])) {
            $dir = $this->getById($id);
            if ($dir) {
                $sql = "UPDATE direcciones SET es_predeterminada = 0 WHERE usuario_id = :usuario_id";
                $this->execute($sql, [":usuario_id" => $dir['USUARIO_ID']]);
            }
        }

        $sql = "UPDATE direcciones SET 
                etiqueta = :etiqueta,
                linea1 = :linea1,
                linea2 = :linea2,
                ciudad = :ciudad,
                departamento = :departamento,
                codigo_postal = :codigo_postal,
                es_predeterminada = :es_predeterminada
                WHERE id = :id";

        $this->execute($sql, [
            ":etiqueta" => $data['etiqueta'],
            ":linea1" => $data['linea1'],
            ":linea2" => $data['linea2'] ?? null,
            ":ciudad" => $data['ciudad'] ?? 'Ciudad de Guatemala',
            ":departamento" => $data['departamento'] ?? 'Guatemala',
            ":codigo_postal" => $data['codigoPostal'] ?? null,
            ":es_predeterminada" => $data['esPredeterminada'] ?? 0,
            ":id" => $id
        ]);

        return true;
    }

    public function delete($id)
    {
        $sql = "DELETE FROM direcciones WHERE id = :id";
        $this->execute($sql, [":id" => $id]);
        return true;
    }
}
