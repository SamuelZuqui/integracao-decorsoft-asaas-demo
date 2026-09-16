<?php

declare(strict_types=1);

namespace Demo\Contracts;

interface Pedidos
{
    /** @return list<array{codigo: int, cliente: string, status: string, valorCentavos: int}> */
    public function listar(): array;

    /** @return array{codigo: int, cliente: string, status: string, valorCentavos: int}|null */
    public function buscar(int $codigo): ?array;
}
