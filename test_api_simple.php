<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['keyword'] = 'Cảng';
$_GET['diem_dau'] = '';
require 'api/search_diem.php';
