<?php
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['message' => 'Use a conexão pelo menu Meta Ads.']);
