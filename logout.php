<?php
session_start(); // если сессия ещё не запущена
session_regenerate_id(true);
session_destroy();
header('Location: index.php');
exit;