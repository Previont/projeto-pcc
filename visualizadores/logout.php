<?php

session_start();
/*
 Propósito: encerrar a sessão atual e redirecionar para a página inicial.
 Funcionalidade: limpa dados de sessão e destrói a sessão.
 Relacionados: `visualizadores/login.php`, `controladores/processar_login.php`.
 Entradas: nenhuma.
 Saídas: redirecionamento para `paginainicial.php`.
 Exemplos: clicar em "Sair" no menu do usuário.
 Boas práticas: sempre chamar `session_destroy` e invalidar dados sensíveis.
 Armadilhas: manter dados no navegador — limpe cookies se necessário (fora deste escopo).
*/


$_SESSION = array();


session_destroy();


header("Location: paginainicial.php");
exit;
?>
