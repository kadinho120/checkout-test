<?php
/**
 * BACEN EMV QRCPS (BR Code) Pix Payload Generator
 * Gera o código Pix Copia e Cola conforme especificação do Banco Central do Brasil.
 *
 * @param string $pixKey Chave Pix (CPF, CNPJ, Email, Telefone ou Chave Aleatória)
 * @param string $merchantName Nome do Titular / Beneficiário (max 25 chars)
 * @param string $merchantCity Cidade do Titular (max 15 chars)
 * @param float $amount Valor da cobrança em reais (ex: 47.90)
 * @param string $txid Identificador da transação (max 25 chars alfanumérico)
 * @return string Código Pix Copia e Cola completo com CRC16
 */
function generatePixBrcode($pixKey, $merchantName = 'RECEBEDOR', $merchantCity = 'SAO PAULO', $amount = 0.0, $txid = '***')
{
    // 1. Helper para sanitizar strings ASCII sem acentos
    $sanitizeAscii = function ($str, $maxLen) {
        $accentMap = [
            'á'=>'a', 'à'=>'a', 'ã'=>'a', 'â'=>'a', 'ä'=>'a',
            'Á'=>'A', 'À'=>'A', 'Ã'=>'A', 'Â'=>'A', 'Ä'=>'A',
            'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
            'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E',
            'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
            'Í'=>'I', 'Ì'=>'I', 'Î'=>'I', 'Ï'=>'I',
            'ó'=>'o', 'ò'=>'o', 'õ'=>'o', 'ô'=>'o', 'ö'=>'o',
            'Ó'=>'O', 'Ò'=>'O', 'Õ'=>'O', 'Ô'=>'O', 'Ö'=>'O',
            'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
            'Ú'=>'U', 'Ù'=>'U', 'Û'=>'U', 'Ü'=>'U',
            'ç'=>'c', 'Ç'=>'C',
            'ñ'=>'n', 'Ñ'=>'N'
        ];
        $clean = strtr($str, $accentMap);
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $clean);
        return strtoupper(substr(trim($clean), 0, $maxLen));
    };

    // 2. Helper para cálculo do CRC16-CCITT (Polinômio 0x1021, Init 0xFFFF)
    $calculateCrc16 = function ($payload) {
        $polynomial = 0x1021;
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    };

    // 3. Helper para empacotar campo TLV (Tag, Length, Value)
    $emvField = function ($id, $value) {
        $len = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $len . $value;
    };

    // 00: Payload Format Indicator (Fixo: 01)
    $payload = $emvField('00', '01');

    // 26: Merchant Account Information - Pix
    $gui = $emvField('00', 'BR.GOV.BCB.PIX');
    $key = $emvField('01', trim($pixKey));
    $merchantAccount = $gui . $key;
    $payload .= $emvField('26', $merchantAccount);

    // 52: Merchant Category Code (0000 = Padrão)
    $payload .= $emvField('52', '0000');

    // 53: Transaction Currency (986 = Real Brasileiro BRL)
    $payload .= $emvField('53', '986');

    // 54: Transaction Amount (Opcional se > 0)
    if ($amount > 0) {
        $amountFormatted = number_format((float)$amount, 2, '.', '');
        $payload .= $emvField('54', $amountFormatted);
    }

    // 58: Country Code (BR)
    $payload .= $emvField('58', 'BR');

    // 59: Merchant Name (Nome do Titular, max 25 chars)
    $cleanName = $sanitizeAscii($merchantName ?: 'RECEBEDOR', 25);
    $payload .= $emvField('59', $cleanName ?: 'RECEBEDOR');

    // 60: Merchant City (Cidade do Titular, max 15 chars)
    $cleanCity = $sanitizeAscii($merchantCity ?: 'SAO PAULO', 15);
    $payload .= $emvField('60', $cleanCity ?: 'SAO PAULO');

    // 62: Additional Data Field (TxID, max 25 chars)
    $cleanTxid = preg_replace('/[^A-Za-z0-9]/', '', (string)$txid);
    if (empty($cleanTxid)) {
        $cleanTxid = '***';
    } else {
        $cleanTxid = substr($cleanTxid, 0, 25);
    }
    $referenceLabel = $emvField('05', $cleanTxid);
    $payload .= $emvField('62', $referenceLabel);

    // 63: CRC16 (Tag 63, Length 04)
    $payloadToCrc = $payload . '6304';
    $crc = $calculateCrc16($payloadToCrc);

    return $payloadToCrc . $crc;
}
