<?php
// Utilidades gerais
// Objetivo: funções auxiliares de URL, redirecionamento e verificação de conexão.
// Exemplo: redirect('/login.php', 'erro', 'Preencha os campos') envia mensagem e redireciona.

function urlAbsoluta(string $path): string {
    // Normaliza path relativo
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Remove barras duplicadas
    $path = '/' . ltrim($path, '/');
    return $scheme . '://' . $host . $path;
}

if (!function_exists('redirect')) {
    function redirect($url, $sessionOrStatus = null, $message = null) {
        if (is_string($sessionOrStatus) && $message !== null) {
            $_SESSION[$sessionOrStatus] = $message;
            $statusCode = 303;
        } else {
            $statusCode = is_int($sessionOrStatus) ? $sessionOrStatus : 303;
        }
        header('Location: ' . $url, true, $statusCode);
        exit;
    }
}

if (!function_exists('assertPdo')) {
    function assertPdo($pdo) {
        if (!($pdo instanceof PDO)) {
            throw new RuntimeException('Conexão PDO indisponível');
        }
    }
}

function encerrarSessaoAtual(string $motivo = ''): void {
    try {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    } catch (Throwable $e) {
        error_log('Falha ao encerrar sessão: ' . $e->getMessage());
    }
    if ($motivo !== '') {
        $_SESSION['erro'] = $motivo;
    }
}

function exigirUsuarioAtivo(PDO $pdo): void {
    if (!isset($_SESSION['id_usuario'])) { return; }
    try {
        $stmt = $pdo->prepare('SELECT ativo FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['id_usuario']]);
        $ativo = $stmt->fetchColumn();
        if ($ativo === null) {
            encerrarSessaoAtual('Sessão inválida. Faça login novamente.');
            redirect('../visualizadores/login.php');
        }
        if ((int)$ativo === 0) {
            encerrarSessaoAtual('Usuário desativado. Entre em contato com o administrador.');
            redirect('../visualizadores/login.php');
        }
    } catch (Throwable $e) {
        error_log('Erro ao verificar status do usuário: ' . $e->getMessage());
    }
}

function __runAuthTests(PDO $pdo): array {
    $resultados = [];
    $uid = null;
    try {
        $nome = 'usuario_teste_' . substr(md5(uniqid('', true)), 0, 6);
        $email = $nome . '@exemplo.com';
        $senha = 'Senha@123';
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO usuarios (nome_usuario, email, senha, tipo_usuario, ativo) VALUES (?, ?, ?, ?, 1)')->execute([$nome, $email, $hash, 'usuario']);
        $uid = (int)$pdo->lastInsertId();
        $resultados[] = ['nome' => 'criar usuário ativo', 'ok' => $uid > 0];
        $stmt = $pdo->prepare('SELECT id, senha, ativo FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        $resultados[] = ['nome' => 'login permitido quando ativo', 'ok' => ($u && (int)$u['ativo'] === 1 && password_verify($senha, $u['senha']))];
        $pdo->prepare('UPDATE usuarios SET ativo = 0 WHERE id = ?')->execute([$uid]);
        $stmt = $pdo->prepare('SELECT ativo FROM usuarios WHERE id = ?');
        $stmt->execute([$uid]);
        $ativo = (int)$stmt->fetchColumn();
        $resultados[] = ['nome' => 'status desativado aplicado', 'ok' => $ativo === 0];
        $_SESSION['id_usuario'] = $uid;
        exigirUsuarioAtivo($pdo);
        $resultados[] = ['nome' => 'middleware encerra sessão desativada', 'ok' => !isset($_SESSION['id_usuario'])];
    } catch (Throwable $e) {
        $resultados[] = ['nome' => 'erro testes', 'ok' => false, 'erro' => $e->getMessage()];
    } finally {
        if ($uid) { $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$uid]); }
    }
    return $resultados;
}

?>
<?php
/**
 * Lista recursivamente arquivos e diretórios a partir de um caminho raiz.
 * Retorna um iterador preguiçoso para alto desempenho em árvores grandes.
 */
function iterarEstrutura(string $raiz, array $opcoes = []): Generator {
    $seguirLinks = isset($opcoes['seguir_links']) ? (bool)$opcoes['seguir_links'] : false;
    $usarRelativo = isset($opcoes['relativo']) ? (bool)$opcoes['relativo'] : true;
    $base = rtrim($raiz, "\\/");
    if ($base === '' || !is_dir($base)) {
        throw new InvalidArgumentException('Caminho inválido');
    }
    $flags = FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::KEY_AS_PATHNAME;
    if ($seguirLinks) { $flags |= FilesystemIterator::FOLLOW_SYMLINKS; }
    try {
        $dir = new RecursiveDirectoryIterator($base, $flags);
    } catch (UnexpectedValueException $e) {
        throw new RuntimeException('Permissão insuficiente ou diretório inacessível');
    }
    $it = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $path => $info) {
        $tipo = $info->isDir() ? 'dir' : 'file';
        $tamanho = null;
        if ($tipo === 'file') {
            try { $tamanho = $info->getSize(); } catch (RuntimeException $e) { $tamanho = null; }
        }
        $p = $usarRelativo ? substr($path, strlen($base) + 1) : $path;
        yield ['path' => $p, 'name' => $info->getFilename(), 'type' => $tipo, 'size' => $tamanho];
    }
}

/**
 * Coleta toda a estrutura em um array.
 */
function listarEstrutura(string $raiz, array $opcoes = []): array {
    $resultado = [];
    foreach (iterarEstrutura($raiz, $opcoes) as $item) { $resultado[] = $item; }
    return $resultado;
}

/**
 * Execução segura que captura erros de caminho, permissões e limites.
 */
function tentarListarEstrutura(string $raiz, array $opcoes = []): array {
    try {
        return ['ok' => true, 'itens' => listarEstrutura($raiz, $opcoes), 'erro' => null];
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'itens' => [], 'erro' => 'caminho inválido'];
    } catch (RuntimeException $e) {
        return ['ok' => false, 'itens' => [], 'erro' => 'permissão insuficiente'];
    } catch (Throwable $e) {
        return ['ok' => false, 'itens' => [], 'erro' => 'falha de sistema de arquivos'];
    }
}
?>
