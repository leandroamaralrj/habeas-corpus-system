<?php

$templatesDir = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
if (!is_dir($templatesDir)) {
    mkdir($templatesDir, 0777, true);
}

if (!isset($_FILES['template']) || $_FILES['template']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php');
    exit;
}

$file = $_FILES['template'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if ($ext !== 'docx') {
    header('Location: index.php');
    exit;
}

$nomeBase = pathinfo($file['name'], PATHINFO_FILENAME);
$nomeSanitizado = preg_replace('/[^A-Za-z0-9_\-]/', '_', $nomeBase);
if ($nomeSanitizado === '') {
    $nomeSanitizado = 'modelo';
}

$destino = $templatesDir . DIRECTORY_SEPARATOR . $nomeSanitizado . '.docx';
$i = 1;
while (file_exists($destino)) {
    $destino = $templatesDir . DIRECTORY_SEPARATOR . $nomeSanitizado . '_' . $i . '.docx';
    $i++;
}

if (!move_uploaded_file($file['tmp_name'], $destino)) {
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;


