<?php
/**
 * Função para gerar um número de telefone aleatório (formato 55 + DDD + 9 + 8 dígitos)
 */
function generate_random_phone()
{
    // Lista de DDDs comuns para evitar bloqueios por DDD inválido
    $ddds = [11, 21, 31, 41, 51, 61, 71, 81, 91, 19, 13, 12];
    $ddd = $ddds[array_rand($ddds)];

    // Gera 8 dígitos aleatórios
    $number = mt_rand(10000000, 99999999);

    return "55{$ddd}9{$number}";
}
