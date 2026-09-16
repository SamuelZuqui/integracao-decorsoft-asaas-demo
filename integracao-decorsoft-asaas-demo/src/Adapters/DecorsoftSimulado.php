<?php

declare(strict_types=1);

namespace Demo\Adapters;

use Demo\Contracts\Pedidos;
use RuntimeException;

final class DecorsoftSimulado implements Pedidos
{
    private array $pedidos;

    public function __construct(string $arquivo)
    {
        $conteudo = file_get_contents($arquivo);
        if ($conteudo === false) {
            throw new RuntimeException('Não foi possível ler os pedidos fictícios.');
        }
        $pedidos = json_decode($conteudo, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($pedidos) || !array_is_list($pedidos)) {
            throw new RuntimeException('A lista de pedidos fictícios é inválida.');
        }
        $codigos = [];
        foreach ($pedidos as $pedido) {
            if (!is_array($pedido)
                || !is_int($pedido['codigo'] ?? null)
                || $pedido['codigo'] < 1
                || !is_string($pedido['cliente'] ?? null)
                || !in_array($pedido['status'] ?? null, ['APROVADO', 'ORCAMENTO', 'CANCELADO'], true)
                || !is_int($pedido['valorCentavos'] ?? null)
                || isset($codigos[$pedido['codigo']])) {
                throw new RuntimeException('Um pedido fictício possui campos inválidos ou código duplicado.');
            }
            $codigos[$pedido['codigo']] = true;
        }
        $this->pedidos = $pedidos;
    }

    public function listar(): array
    {
        return $this->pedidos;
    }

    public function buscar(int $codigo): ?array
    {
        foreach ($this->pedidos as $pedido) {
            if ($pedido['codigo'] === $codigo) {
                return $pedido;
            }
        }
        return null;
    }
}
