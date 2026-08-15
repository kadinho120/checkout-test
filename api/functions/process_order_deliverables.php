<?php
/**
 * Process Deliverables for a given list of products
 * 
 * @param array $productsList List of products (must contain 'sku')
 * @param array $customerData Must contain ['phone', 'name', 'email']
 * @param PDO $db Database connection
 * @param array $types Types to send: ['wpp', 'email', 'sms'] (default: all)
 */
function processOrderDeliverables($productsList, $customerData, $db, $types = ['wpp', 'email', 'sms'])
{
    $results = [];
    $sentCount = 0;

    // Extract phone safely
    $customerPhone = $customerData['phone'] ?? '';

    foreach ($productsList as $item) {
        $sku = $item['sku'] ?? '';
        $deliverableConfig = null;

        if (empty($sku))
            continue;

        // Determine if Bump or Main Product
        if (strpos($sku, 'BUMP-') === 0) {
            // It is a bump
            $bumpId = (int) str_replace('BUMP-', '', $sku);

            // Re-fetch with join to get parent credentials
            $stmt = $db->prepare("
                SELECT ob.deliverable_type, ob.deliverable_text, ob.deliverable_file,
                       ob.deliverable_email_subject, ob.deliverable_email_body,
                       ob.twilio_content_sid, ob.twilio_content_variables, ob.twilio_message, ob.twilio_media_url,
                       ob.sms_message,
                       p.evolution_instance, p.evolution_token, p.evolution_url,
                       p.twilio_account_sid, p.twilio_auth_token, p.twilio_from,
                       p.sms_token,
                       p.name as product_name
                FROM order_bumps ob
                JOIN products p ON ob.product_id = p.id
                WHERE ob.id = ?
            ");
            $stmt->execute([$bumpId]);
            $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Main Product (lookup by slug)
            $stmt = $db->prepare("SELECT evolution_instance, evolution_token, evolution_url, deliverable_type, deliverable_text, deliverable_file, deliverable_email_subject, deliverable_email_body, twilio_account_sid, twilio_auth_token, twilio_from, twilio_content_sid, twilio_content_variables, twilio_message, twilio_media_url, sms_token, sms_message, name as product_name FROM products WHERE slug = ?");
            $stmt->execute([$sku]);
            $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 1. WhatsApp Sending (Evolution API)
        if (in_array('wpp', $types)) {
            if ($deliverableConfig && !empty($deliverableConfig['evolution_url']) && !empty($deliverableConfig['evolution_instance'])) {

                // Apply Shortcodes
                $finalMessage = replaceShortcodes($deliverableConfig['deliverable_text'], $customerData, '');

                $res = sendEvolutionMessage(
                    $deliverableConfig['evolution_instance'],
                    $deliverableConfig['evolution_token'],
                    $deliverableConfig['evolution_url'],
                    $customerPhone,
                    $deliverableConfig['deliverable_type'],
                    $finalMessage,
                    $deliverableConfig['deliverable_file']
                );
                $results[] = ['sku' => $sku, 'wpp_status' => $res['success'] ? 'sent' : 'failed'];
                if ($res['success'])
                    $sentCount++;
            } else {
                $results[] = ['sku' => $sku, 'wpp_status' => 'skipped_no_config'];
            }

            // 1.1 WhatsApp Sending (Twilio API)
            require_once __DIR__ . '/send_twilio_message.php';

            $twSid = !empty($deliverableConfig['twilio_account_sid']) ? $deliverableConfig['twilio_account_sid'] : (getenv('TWILIO_ACCOUNT_SID') ?: ($_ENV['TWILIO_ACCOUNT_SID'] ?? ''));
            $twToken = !empty($deliverableConfig['twilio_auth_token']) ? $deliverableConfig['twilio_auth_token'] : (getenv('TWILIO_AUTH_TOKEN') ?: ($_ENV['TWILIO_AUTH_TOKEN'] ?? ''));
            $twFrom = !empty($deliverableConfig['twilio_from']) ? $deliverableConfig['twilio_from'] : (getenv('TWILIO_FROM') ?: (getenv('TWILIO_WHATSAPP_FROM') ?: ($_ENV['TWILIO_FROM'] ?? '')));
            $twContentSid = $deliverableConfig['twilio_content_sid'] ?? '';
            $twContentVars = $deliverableConfig['twilio_content_variables'] ?? '';
            $twMessage = $deliverableConfig['twilio_message'] ?? '';
            $twMediaUrl = $deliverableConfig['twilio_media_url'] ?? '';

            if (!empty($twSid) && !empty($twToken) && !empty($twFrom) && (!empty($twContentSid) || !empty($twMessage))) {
                $finalTwMessage = !empty($twMessage) ? replaceShortcodes($twMessage, $customerData, '') : '';
                $finalTwVars = $twContentVars;
                if (!empty($finalTwVars) && is_string($finalTwVars)) {
                    $finalTwVars = replaceShortcodes($finalTwVars, $customerData, '');
                    $decodedVars = json_decode($finalTwVars, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $finalTwVars = $decodedVars;
                    }
                }

                $resTwilio = sendTwilioMessage(
                    $twSid,
                    $twToken,
                    $twFrom,
                    $customerPhone,
                    $twContentSid,
                    $finalTwVars,
                    $finalTwMessage,
                    $twMediaUrl
                );

                $results[] = ['sku' => $sku, 'twilio_status' => $resTwilio['success'] ? 'sent' : 'failed'];
                if ($resTwilio['success']) {
                    $sentCount++;
                }
            }
        }

        // 2. SMS Sending (DisparoPro API)
        if (in_array('sms', $types)) {
            require_once __DIR__ . '/send_sms_disparopro.php';

            $smsApiKey = !empty($deliverableConfig['sms_token']) ? $deliverableConfig['sms_token'] : (getenv('DISPAROPRO_API_KEY') ?: ($_ENV['DISPAROPRO_API_KEY'] ?? ''));
            $smsMsg = $deliverableConfig['sms_message'] ?? '';

            if (!empty($smsApiKey) && !empty($smsMsg) && !empty($customerPhone)) {
                // Ensure product name is present for shortcode
                if (empty($customerData['product_name']) && !empty($deliverableConfig['product_name'])) {
                    $customerData['product_name'] = $deliverableConfig['product_name'];
                }

                $finalSmsMsg = replaceShortcodes($smsMsg, $customerData, '');
                $resSms = sendSmsDisparoPro($smsApiKey, $customerPhone, $finalSmsMsg, $sku);

                $statusKey = 'sms_status';
                $found = false;
                foreach ($results as &$r) {
                    if ($r['sku'] === $sku) {
                        $r[$statusKey] = $resSms['success'] ? 'sent' : 'failed';
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $results[] = ['sku' => $sku, $statusKey => $resSms['success'] ? 'sent' : 'failed'];
                }
                if ($resSms['success']) {
                    $sentCount++;
                }
            }
        }

        // 3. Email Sending
        if (in_array('email', $types)) {
            if ($deliverableConfig && !empty($deliverableConfig['deliverable_email_subject']) && !empty($deliverableConfig['deliverable_email_body'])) {
                // Ensure product name is present for shortcode
                if (empty($customerData['product_name']) && !empty($deliverableConfig['product_name'])) {
                    $customerData['product_name'] = $deliverableConfig['product_name'];
                }

                $emailBody = replaceShortcodes($deliverableConfig['deliverable_email_body'], $customerData, '');
                $emailSubject = replaceShortcodes($deliverableConfig['deliverable_email_subject'], $customerData, '');

                $resEmail = sendOrderEmail($customerData['email'], $emailSubject, $emailBody);

                // Track result
                $statusKey = 'email_status';
                // Find existing item in results if WPP or SMS ran, or create new
                $found = false;
                foreach ($results as &$r) {
                    if ($r['sku'] === $sku) {
                        $r[$statusKey] = $resEmail['success'] ? 'sent' : 'failed';
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $results[] = ['sku' => $sku, $statusKey => $resEmail['success'] ? 'sent' : 'failed'];
                }
                if ($resEmail['success']) {
                    $sentCount++;
                }
            }
        }
    }

    return ['sent' => $sentCount, 'details' => $results];
}
