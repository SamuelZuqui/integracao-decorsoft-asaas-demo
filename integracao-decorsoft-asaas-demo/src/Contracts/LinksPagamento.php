<?php

declare(strict_types=1);

namespace Demo\Contracts;

interface LinksPagamento
{
    /** @return array{id: string, url: string, pedido: int, valorCentavos: int, parcelas: int, status: string} */
    public function criar(int $pedido, int $valorCentavos, int $parcelas): array;

    /** @return array{id: string, deleted: bool} */
    public function excluir(string $id): array;
}
