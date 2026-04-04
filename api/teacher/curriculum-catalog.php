<?php
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'learning-common.php';

echo json_encode(['courses' => ms_learning_catalog()]);
