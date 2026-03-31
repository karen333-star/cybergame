<?php
session_start();
$conn = new mysqli("localhost", "root", "", "proyecto");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"];
    $correo = $_POST["correo"];
    $password = $_POST["password"];

    // REGISTRO
    if ($accion === "registro") {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO usuarios (correo, password) VALUES (?,?)");
        $stmt->bind_param("ss", $correo, $hash);

        if ($stmt->execute()) {
            echo "REGISTRO_OK";
        } else {
            echo "ERROR_REGISTRO";
        }
        exit;
    }

    // LOGIN
    if ($accion === "login") {

        $stmt = $conn->prepare("SELECT password FROM usuarios WHERE correo=?");
        $stmt->bind_param("s", $correo);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row["password"])) {
                $_SESSION["usuario"] = $correo;
                echo "LOGIN_OK";
            } else {
                echo "PASS_INCORRECTA";
            }
        } else {
            echo "NO_EXISTE";
        }
        exit;
    }
}