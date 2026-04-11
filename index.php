<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

require 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? null;

    // REGISTRO
    if ($accion === "registro") {
        $email = $_POST["email"] ?? "";
        $nombre_usuario = $_POST["nombre_usuario"] ?? "";
        $password = $_POST["password"] ?? "";
        $password_confirm = $_POST["password_confirm"] ?? "";

        // Validaciones
        if ($password !== $password_confirm) {
            echo "PASS_NO_COINCIDEN";
            exit;
        }

        if (!validar_contraseña($password)) {
            echo "PASS_INVALIDA";
            exit;
        }

        // Verificar email único
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
        if (!$stmt) {
            error_log("Error prepare email: " . $conn->error);
            echo "ERROR_REGISTRO";
            exit;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo "EMAIL_EXISTE";
            exit;
        }

        // Verificar nombre_usuario único
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE nombre_usuario = ?");
        if (!$stmt) {
            error_log("Error prepare usuario: " . $conn->error);
            echo "ERROR_REGISTRO";
            exit;
        }
        $stmt->bind_param("s", $nombre_usuario);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo "USUARIO_EXISTE";
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (email, nombre_usuario, password_hash) VALUES (?, ?, ?)");
        if (!$stmt) {
            error_log("Error prepare insert: " . $conn->error);
            echo "ERROR_REGISTRO";
            exit;
        }
        $stmt->bind_param("sss", $email, $nombre_usuario, $hash);

        if ($stmt->execute()) {
            echo "REGISTRO_OK";
        } else {
            error_log("Error execute: " . $stmt->error);
            echo "ERROR_REGISTRO";
        }
        exit;
    }

    // LOGIN
    if ($accion === "login") {
        $identidad = $_POST["identidad"] ?? "";
        $password = $_POST["password"] ?? "";

        // Buscar por email O nombre_usuario
        $stmt = $conn->prepare("SELECT id_usuario, nombre_usuario, email, password_hash FROM usuarios WHERE email = ? OR nombre_usuario = ?");
        if (!$stmt) {
            error_log("Error prepare login: " . $conn->error);
            echo "ERROR_LOGIN";
            exit;
        }
        $stmt->bind_param("ss", $identidad, $identidad);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row["password_hash"])) {
                session_regenerate_id(true);
                $_SESSION["usuario_id"] = $row["id_usuario"];
                $_SESSION["nombre_usuario"] = $row["nombre_usuario"];
                $_SESSION["email"] = $row["email"];
                echo "LOGIN_OK";
            } else {
                echo "PASS_INCORRECTA";
            }
        } else {
            echo "NO_EXISTE";
        }
        exit;
    }

    // LOGOUT
    if ($accion === "logout") {
        session_destroy();
        echo "LOGOUT_OK";
        exit;
    }
}