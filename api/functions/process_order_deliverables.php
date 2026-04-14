<?php
/**
 * Process Deliverables for a given list of products
 * 
 * @param array $productsList List of products (must contain 'sku')
 * @param array $customerData Must contain ['phone', 'name', 'email']
 * @param PDO $db Database connection
 * @param array $types Types to send: ['wpp', 'email'] (default: both)
 */
function processOrderDeliverables($productsList, $customerData, $db, $types = ['wpp', 'email'])
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
                       p.evolution_instance, p.evolution_token, p.evolution_url, p.name as product_name
                FROM order_bumps ob
                JOIN products p ON ob.product_id = p.id
                WHERE ob.id = ?
            ");
            $stmt->execute([$bumpId]);
            $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Main Product (lookup by slug)
            $stmt = $db->prepare("SELECT evolution_instance, evolution_token, evolution_url, deliverable_type, deliverable_text, deliverable_file, deliverable_email_subject, deliverable_email_body, name as product_name FROM products WHERE slug = ?");
            $stmt->execute([$sku]);
            $deliverableConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // 1. WhatsApp Sending
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
        }

        // 2. Email Sending
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
                // Find existing item in results if WPP ran, or create new
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
            }
        }
    }

    return ['sent' => $sentCount, 'details' => $results];
}
