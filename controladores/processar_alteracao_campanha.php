<?php
session_start();
require_once __DIR__ . '/../modelos/configuraçõesdeconexão.php';

// Validação de segurança
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../visualizadores/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;
}

$id_usuario = $_SESSION['id_usuario'];
$acao = $_POST['acao'] ?? '';

try {
    if ($acao === 'editar') {
        // Coleta e validação dos dados
        $id_campanha = filter_input(INPUT_POST, 'id_campanha', FILTER_VALIDATE_INT);
        $titulo = trim(filter_input(INPUT_POST, 'titulo', FILTER_SANITIZE_STRING));
        $descricao = trim(filter_input(INPUT_POST, 'descricao', FILTER_SANITIZE_STRING));
        $meta = filter_input(INPUT_POST, 'meta', FILTER_VALIDATE_FLOAT);

        if (!$id_campanha || empty($titulo) || empty($descricao) || !$meta || $meta <= 0) {
            $_SESSION['erro_campanha'] = "Todos os campos são obrigatórios e a meta deve ser um número positivo.";
            header("Location: ../visualizadores/editar-campanha.php?id=" . $id_campanha);
            exit;
        }

        // Verifica se a campanha pertence ao usuário
        $verifica = $pdo->prepare("SELECT id FROM campanhas WHERE id = ? AND id_usuario = ?");
        $verifica->execute([$id_campanha, $id_usuario]);
        
        if (!$verifica->fetch()) {
            $_SESSION['erro_campanha'] = "Campanha não encontrada ou você não tem permissão para editá-la.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Atualiza a campanha
        $sql = "UPDATE campanhas SET titulo = :titulo, descricao = :descricao, meta_arrecadacao = :meta WHERE id = :id AND id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':meta' => $meta,
            ':id' => $id_campanha,
            ':id_usuario' => $id_usuario
        ]);

        $_SESSION['sucesso_campanha'] = "Campanha atualizada com sucesso!";
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;

    } elseif ($acao === 'excluir') {
        // Exclusão de campanha
        $id_campanha = filter_input(INPUT_POST, 'id_campanha', FILTER_VALIDATE_INT);
        
        if (!$id_campanha) {
            $_SESSION['erro_campanha'] = "ID da campanha inválido.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Verifica se a campanha pertence ao usuário
        $verifica = $pdo->prepare("SELECT id FROM campanhas WHERE id = ? AND id_usuario = ?");
        $verifica->execute([$id_campanha, $id_usuario]);
        
        if (!$verifica->fetch()) {
            $_SESSION['erro_campanha'] = "Campanha não encontrada ou você não tem permissão para excluí-la.";
            header("Location: ../visualizadores/minhas-campanhas.php");
            exit;
        }

        // Exclui a campanha
        $sql = "DELETE FROM campanhas WHERE id = :id AND id_usuario = :id_usuario";
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':id' => $id_campanha,
            ':id_usuario' => $id_usuario
        ]);

        $_SESSION['sucesso_campanha'] = "Campanha excluída com sucesso!";
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;

    } else {
        header("Location: ../visualizadores/minhas-campanhas.php");
        exit;
    }

} catch (PDOException $e) {
    $_SESSION['erro_campanha'] = "Erro ao processar solicitação. Tente novamente.";
    header("Location: ../visualizadores/minhas-campanhas.php");
    exit;
}
?>