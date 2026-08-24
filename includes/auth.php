<?php
session_start();

if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = [
        'nome' => 'Ana Clara',
        'email' => 'ana.clara@email.com',
        'tipo' => 'Estudante'
    ];
}
?>
alterar 