<?php

class Database {

    private static $conn = null;

    public static function connect() {

        if (self::$conn === null) {

            $username = 'chapinmarket';
            $password = 'Chapin2026';
            $connectionString = '//localhost/XEPDB1';

            self::$conn = oci_connect(
                $username,
                $password,
                $connectionString,
                'AL32UTF8'
            );

            if (!self::$conn) {
                $e = oci_error();

                // Manejo consistente de errores
                self::handleError($e);
            }
        }

        return self::$conn;
    }

    private static function handleError($error) {
        http_response_code(500);

        echo json_encode([
            "success" => false,
            "error" => "Error de conexión a la base de datos",
            "details" => $error['message']
        ]);

        exit;
    }

    public static function close() {
        if (self::$conn !== null) {
            oci_close(self::$conn);
            self::$conn = null;
        }
    }
}