<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Demo\Adapters\AsaasSimulado;
use Demo\Adapters\DecorsoftSimulado;
use Demo\IntegradorService;

// Runner pequeno, sem dependências. Falhas encerram o processo com código 1.
function igual(mixed $esperado, mixed $recebido): void
{
    if ($esperado !== $recebido) {
        throw new RuntimeException('Esperado: ' . var_export($esperado, true) . '; recebido: ' . var_export($recebido, true));
    }
}

function rejeita(callable $acao, string $trecho, string $tipo = DomainException::class): void
{
    try {
        $acao();
    } catch (Throwable $erro) {
        if (!$erro instanceof $tipo || !str_contains($erro->getMessage(), $trecho)) {
            throw new RuntimeException('Exceção inesperada: ' . $erro->getMessage(), 0, $erro);
        }
        return;
    }
    throw new RuntimeException('A operação deveria ter sido rejeitada.');
}

function servico(string $arquivo): IntegradorService
{
    return new IntegradorService(
        new DecorsoftSimulado(dirname(__DIR__) . '/data/pedidos.json'),
        new AsaasSimulado($arquivo),
    );
}

$testes = [
    'lista e consulta pedidos fictícios' => static function (string $arquivo): void {
        $servico = servico($arquivo);
        igual(4, count($servico->listarPedidos()));
        igual('Cliente Exemplo A', $servico->buscarPedido(1001)['cliente']);
        igual(87550, $servico->buscarPedido(1002)['valorCentavos']);
        igual('', file_get_contents($arquivo));
    },
    'gera link com valor exato em centavos e parcelas padrão' => static function (string $arquivo): void {
        $link = servico($arquivo)->gerarLinkParaPedido(1001);
        igual(249990, $link['valorCentavos']);
        igual(3, $link['parcelas']);
        igual(1001, $link['pedido']);
        igual('ATIVO', $link['status']);
        igual('pagamentos.example.com', parse_url($link['url'], PHP_URL_HOST));
        igual(1, preg_match('/^demo_[a-f0-9]{12}$/D', $link['id']));
    },
    'aceita os limites de parcelamento da demonstração' => static function (string $arquivo): void {
        $servico = servico($arquivo);
        igual(1, $servico->gerarLinkParaPedido(1001, 1)['parcelas']);
        igual(12, $servico->gerarLinkParaPedido(1002, 12)['parcelas']);
    },
    'rejeita pedido inexistente sem gerar links' => static function (string $arquivo): void {
        rejeita(fn () => servico($arquivo)->gerarLinkParaPedido(9999), 'não encontrado');
        igual('', file_get_contents($arquivo));
    },
    'rejeita códigos não positivos' => static function (string $arquivo): void {
        foreach ([0, -1] as $codigo) {
            rejeita(fn () => servico($arquivo)->buscarPedido($codigo), 'positivo');
        }
    },
    'rejeita orçamento e pedido cancelado sem gerar links' => static function (string $arquivo): void {
        foreach ([1003, 1004] as $codigo) {
            rejeita(fn () => servico($arquivo)->gerarLinkParaPedido($codigo), 'aprovados');
        }
        igual('', file_get_contents($arquivo));
    },
    'rejeita parcelamento fora dos limites' => static function (string $arquivo): void {
        foreach ([0, -1, 13] as $parcelas) {
            rejeita(fn () => servico($arquivo)->gerarLinkParaPedido(1001, $parcelas), '1 a 12');
        }
        igual('', file_get_contents($arquivo));
    },
    'rejeita valores zero ou negativos antes de chamar o gateway' => static function (string $arquivo): void {
        foreach ([0, -100] as $valor) {
            $pedidos = new class($valor) implements \Demo\Contracts\Pedidos {
                public function __construct(private int $valor) {}
                public function listar(): array { return []; }
                public function buscar(int $codigo): ?array {
                    return ['codigo' => $codigo, 'cliente' => 'Teste', 'status' => 'APROVADO', 'valorCentavos' => $this->valor];
                }
            };
            $servico = new IntegradorService($pedidos, new AsaasSimulado($arquivo));
            rejeita(fn () => $servico->gerarLinkParaPedido(1001), 'maior que zero');
        }
        igual('', file_get_contents($arquivo));
    },
    'reutiliza link ativo mesmo em outra instância do serviço' => static function (string $arquivo): void {
        $primeiro = servico($arquivo)->gerarLinkParaPedido(1001);
        $segundo = servico($arquivo)->gerarLinkParaPedido(1001);
        igual($primeiro, $segundo);
        igual(1, count(json_decode(file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR)));
    },
    'rejeita novas condições enquanto existe link ativo' => static function (string $arquivo): void {
        $link = servico($arquivo)->gerarLinkParaPedido(1001, 3);
        rejeita(fn () => servico($arquivo)->gerarLinkParaPedido(1001, 6), 'outras condições');
        rejeita(fn () => (new AsaasSimulado($arquivo))->criar(1001, 150000, 3), 'outras condições');
        igual($link, servico($arquivo)->gerarLinkParaPedido(1001, 3));
    },
    'exclui link, permite repetir exclusão e gerar substituto' => static function (string $arquivo): void {
        $servico = servico($arquivo);
        $link = $servico->gerarLinkParaPedido(1001);
        igual(['id' => $link['id'], 'deleted' => true], $servico->excluirLink($link['id']));
        igual(true, servico($arquivo)->excluirLink($link['id'])['deleted']);
        $novo = servico($arquivo)->gerarLinkParaPedido(1001, 6);
        igual(false, $link['id'] === $novo['id']);
        igual(6, $novo['parcelas']);
        $dados = json_decode(file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
        igual('EXCLUIDO', $dados[$link['id']]['status']);
        igual('ATIVO', $dados[$novo['id']]['status']);
    },
    'rejeita ID de link inválido ou inexistente' => static function (string $arquivo): void {
        rejeita(fn () => servico($arquivo)->excluirLink('link-invalido'), 'ID fictício');
        rejeita(fn () => servico($arquivo)->excluirLink('demo_000000000000'), 'não encontrado');
        igual('', file_get_contents($arquivo));
    },
    'não sobrescreve armazenamento com JSON corrompido' => static function (string $arquivo): void {
        file_put_contents($arquivo, '{invalido');
        rejeita(fn () => servico($arquivo)->gerarLinkParaPedido(1001), 'Syntax error', JsonException::class);
        igual('{invalido', file_get_contents($arquivo));
    },
    'valida estrutura e unicidade dos pedidos de entrada' => static function (string $arquivo): void {
        file_put_contents($arquivo, '{}');
        // Um objeto não vazio não pode ser interpretado como lista.
        file_put_contents($arquivo, '{"pedido": 1001}');
        rejeita(fn () => new DecorsoftSimulado($arquivo), 'lista de pedidos', RuntimeException::class);
        $pedido = ['codigo' => 1001, 'cliente' => 'Teste', 'status' => 'APROVADO', 'valorCentavos' => 100];
        file_put_contents($arquivo, json_encode([$pedido, $pedido], JSON_THROW_ON_ERROR));
        rejeita(fn () => new DecorsoftSimulado($arquivo), 'duplicado', RuntimeException::class);
        $pedido['valorCentavos'] = '100';
        file_put_contents($arquivo, json_encode([$pedido], JSON_THROW_ON_ERROR));
        rejeita(fn () => new DecorsoftSimulado($arquivo), 'inválidos', RuntimeException::class);
    },
];

$falhas = 0;
foreach ($testes as $nome => $teste) {
    $arquivo = tempnam(sys_get_temp_dir(), 'integracao-test-');
    if ($arquivo === false) {
        fwrite(STDERR, "Não foi possível criar arquivo temporário.\n");
        exit(1);
    }
    try {
        $teste($arquivo);
        echo "[OK] {$nome}\n";
    } catch (Throwable $erro) {
        $falhas++;
        fwrite(STDERR, "[FALHOU] {$nome}: {$erro->getMessage()}\n");
    } finally {
        unlink($arquivo);
    }
}
echo PHP_EOL . count($testes) . " testes, {$falhas} falhas.\n";
exit($falhas > 0 ? 1 : 0);
