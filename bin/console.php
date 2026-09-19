<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Demo\Adapters\AsaasSimulado;
use Demo\Adapters\DecorsoftSimulado;
use Demo\IntegradorService;

function inteiroPositivo(string $valor): int
{
    $numero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($numero === false) {
        throw new DomainException('Informe um número inteiro positivo.');
    }
    return $numero;
}

function exibir(array $dados): void
{
    echo json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
}

$temporario = null;
try {
    $comando = $argv[1] ?? 'ajuda';
    $quantidade = count($argv) - 2;
    $limites = ['listar' => [0, 0], 'ver' => [1, 1], 'gerar' => [1, 2], 'excluir' => [1, 1], 'demo' => [0, 0]];

    if (in_array($comando, ['ajuda', '--help', '-h'], true)) {
        echo <<<'HELP'
Integração Decorsoft → Asaas | DEMONSTRAÇÃO LOCAL

Nenhuma API é acessada. Todos os pedidos e links são fictícios.

Uso:
  php bin/console.php demo                  Executa um ciclo completo e isolado
  php bin/console.php listar                Lista os pedidos fictícios
  php bin/console.php ver 1001              Exibe um pedido
  php bin/console.php gerar 1001 [parcelas]  Gera um link fictício (padrão: 3)
  php bin/console.php excluir demo_<id>     Exclui um link gerado localmente

Valores monetários são exibidos em centavos: 249990 = R$ 2.499,90.
Os links exibidos são ilustrativos e não permitem pagamento.
HELP;
        echo PHP_EOL;
        exit(0);
    }
    if (!isset($limites[$comando])) {
        throw new DomainException('Comando desconhecido. Use: php bin/console.php ajuda');
    }
    [$minimo, $maximo] = $limites[$comando];
    if ($quantidade < $minimo || $quantidade > $maximo) {
        throw new DomainException('Quantidade de argumentos inválida. Use: php bin/console.php ajuda');
    }

    $arquivo = dirname(__DIR__) . '/var/links.json';
    if ($comando === 'demo') {
        $temporario = tempnam(sys_get_temp_dir(), 'erp-demo-');
        if ($temporario === false) {
            throw new RuntimeException('Não foi possível iniciar a demonstração temporária.');
        }
        $arquivo = $temporario;
    }
    $servico = new IntegradorService(
        new DecorsoftSimulado(dirname(__DIR__) . '/data/pedidos.json'),
        new AsaasSimulado($arquivo),
    );
    echo "[DEMO LOCAL] Dados fictícios. Nenhuma cobrança real.\n";
    switch ($comando) {
        case 'listar':
            exibir($servico->listarPedidos());
            break;
        case 'ver':
            exibir($servico->buscarPedido(inteiroPositivo($argv[2])));
            break;
        case 'gerar':
            exibir($servico->gerarLinkParaPedido(inteiroPositivo($argv[2]), inteiroPositivo($argv[3] ?? '3')));
            break;
        case 'excluir':
            exibir($servico->excluirLink($argv[2]));
            break;
        case 'demo':
            echo "\n1. Consultando pedido aprovado\n";
            exibir($servico->buscarPedido(1001));
            echo "\n2. Gerando link fictício\n";
            $link = $servico->gerarLinkParaPedido(1001);
            exibir($link);
            echo "\n3. Repetindo a solicitação sem duplicar o link\n";
            $repetido = $servico->gerarLinkParaPedido(1001);
            exibir(['mesmoLink' => $link['id'] === $repetido['id']]);
            echo "\n4. Excluindo o link fictício\n";
            exibir($servico->excluirLink($link['id']));
            echo "\nDemonstração concluída.\n";
            break;
    }
} catch (Throwable $erro) {
    fwrite(STDERR, '[ERRO] ' . $erro->getMessage() . PHP_EOL);
    $falhou = true;
} finally {
    if (is_string($temporario) && is_file($temporario)) {
        unlink($temporario);
    }
}
exit(isset($falhou) ? 1 : 0);
