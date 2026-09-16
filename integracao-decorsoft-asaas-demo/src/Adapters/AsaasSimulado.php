<?php

declare(strict_types=1);

namespace Demo\Adapters;

use Demo\Contracts\LinksPagamento;
use DomainException;
use RuntimeException;

final class AsaasSimulado implements LinksPagamento
{
    public function __construct(private readonly string $arquivo)
    {
    }

    public function criar(int $pedido, int $valorCentavos, int $parcelas): array
    {
        return $this->alterar(static function (array &$links) use ($pedido, $valorCentavos, $parcelas): array {
            // Evita links ativos duplicados na demonstração. Não representa garantia da API real.
            foreach ($links as $link) {
                if ($link['pedido'] === $pedido && $link['status'] === 'ATIVO') {
                    if ($link['valorCentavos'] !== $valorCentavos || $link['parcelas'] !== $parcelas) {
                        throw new DomainException('Já existe um link ativo com outras condições. Exclua-o antes de gerar outro.');
                    }
                    return $link;
                }
            }
            $id = 'demo_' . bin2hex(random_bytes(6));
            $link = [
                'id' => $id,
                'url' => 'https://pagamentos.example.com/' . $id,
                'pedido' => $pedido,
                'valorCentavos' => $valorCentavos,
                'parcelas' => $parcelas,
                'status' => 'ATIVO',
            ];
            $links[$id] = $link;
            return $link;
        });
    }

    public function excluir(string $id): array
    {
        return $this->alterar(static function (array &$links) use ($id): array {
            if (!isset($links[$id])) {
                throw new DomainException('Link fictício não encontrado.');
            }
            $links[$id]['status'] = 'EXCLUIDO';
            return ['id' => $id, 'deleted' => true];
        });
    }

    private function alterar(callable $operacao): array
    {
        $handle = fopen($this->arquivo, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível abrir o armazenamento local.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Não foi possível bloquear o armazenamento local.');
            }
            $conteudo = stream_get_contents($handle);
            if ($conteudo === false) {
                throw new RuntimeException('Não foi possível ler o armazenamento local.');
            }
            $links = $conteudo === '' ? [] : json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($links)) {
                throw new RuntimeException('O armazenamento local é inválido.');
            }
            $resultado = $operacao($links);
            $json = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
                throw new RuntimeException('Não foi possível salvar o armazenamento local.');
            }
            return $resultado;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
