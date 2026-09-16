<?php

declare(strict_types=1);

namespace Demo;

use Demo\Contracts\LinksPagamento;
use Demo\Contracts\Pedidos;
use DomainException;

final class IntegradorService
{
    public function __construct(
        private readonly Pedidos $pedidos,
        private readonly LinksPagamento $links,
    ) {
    }

    public function listarPedidos(): array
    {
        return $this->pedidos->listar();
    }

    public function buscarPedido(int $codigo): array
    {
        if ($codigo < 1) {
            throw new DomainException('Informe um código de pedido positivo.');
        }
        return $this->pedidos->buscar($codigo)
            ?? throw new DomainException("Pedido fictício #{$codigo} não encontrado.");
    }

    public function gerarLinkParaPedido(int $codigo, int $parcelas = 3): array
    {
        if ($parcelas < 1 || $parcelas > 12) {
            throw new DomainException('Nesta demonstração, informe de 1 a 12 parcelas.');
        }
        $pedido = $this->buscarPedido($codigo);
        if ($pedido['status'] !== 'APROVADO') {
            throw new DomainException('Somente pedidos aprovados podem gerar links de pagamento.');
        }
        if ($pedido['valorCentavos'] <= 0) {
            throw new DomainException('O valor do pedido precisa ser maior que zero.');
        }
        return $this->links->criar($codigo, $pedido['valorCentavos'], $parcelas);
    }

    public function excluirLink(string $id): array
    {
        if (preg_match('/^demo_[a-f0-9]{12}$/D', $id) !== 1) {
            throw new DomainException('Informe um ID fictício no formato demo_ seguido de 12 caracteres hexadecimais.');
        }
        return $this->links->excluir($id);
    }
}
