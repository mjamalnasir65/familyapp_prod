<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=familyapp;charset=utf8mb4','root','1111',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    $row = $pdo->query('SELECT 1 as ok')->fetch();
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true,'db'=>$row['ok']??0]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
