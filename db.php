<?php
$pdo = new PDO("mysql:host=localhost;dbname=pr_tareas2025_bd;charset=utf8", "root", "root");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);