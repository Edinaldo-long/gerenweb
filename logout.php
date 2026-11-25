<?php
session_start();
session_unset();
session_destroy();

// Redireciona para o index.html (que agora é a sua tela de login)
header('Location: index.html');
exit;