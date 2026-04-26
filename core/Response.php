<?php

class Response {

    public static function success($data = null, $message = "OK", $status = 200, $meta = null) {
        http_response_code($status);

        echo json_encode([
            "success" => true,
            "message" => $message,
            "data" => $data,
            "errors" => null,
            "meta" => $meta
        ],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function error($message = "Error", $status = 500, $errors = null) {
        http_response_code($status);

        echo json_encode([
            "success" => false,
            "message" => $message,
            "data" => null,
            "errors" => $errors,
            "meta" => null
        ],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }
}