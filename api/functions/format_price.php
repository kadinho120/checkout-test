<?php
/**
 * Formata o preço para exibição. 
 * Se o valor não tiver centavos (ex: 97.00), exibe sem casas decimais.
 * Se tiver centavos (ex: 97.50), exibe com duas casas decimais.
 * 
 * @param float $price O preço a ser formatado.
 * @return string O preço formatado no padrão brasileiro (R$ X ou R$ X,XX).
 */
function format_price($price) {
    if ($price == (int)$price) {
        return number_format($price, 0, ',', '.');
    }
    return number_format($price, 2, ',', '.');
}
