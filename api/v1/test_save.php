<?php
include_once '../connection.php';
$database = new Database();
$db = $database->getConnection();

$data = [
    'id' => 1,
    'name' => 'Teste',
    'slug' => 'teste-' . time(),
    'price' => 10.00,
    'description' => 'Teste',
    'active' => 1,
    'theme' => 'dark',
    'checkout_style' => 'minimalist'
];

$data = (object) $data;

try {
    $db->beginTransaction();
    $stmt = $db->prepare("UPDATE products SET 
        name = :name, 
        slug = :slug, 
        description = :description, 
        price = :price, 
        image_url = :image_url, 
        active = :active, 
        theme = :theme, 
        request_email = :request_email, 
        request_phone = :request_phone, 
        request_name = :request_name, 
        track_initiate_checkout = :track_initiate_checkout, 
        track_add_payment_info = :track_add_payment_info, 
        evolution_instance = :evolution_instance, 
        evolution_token = :evolution_token, 
        evolution_url = :evolution_url, 
        deliverable_type = :deliverable_type, 
        deliverable_text = :deliverable_text, 
        deliverable_file = :deliverable_file, 
        deliverable_email_subject = :deliverable_email_subject, 
        deliverable_email_body = :deliverable_email_body, 
        fake_notifications = :fake_notifications, 
        notification_text = :notification_text, 
        top_bar_enabled = :top_bar_enabled, 
        top_bar_text = :top_bar_text, 
        top_bar_bg_color = :top_bar_bg_color, 
        top_bar_text_color = :top_bar_text_color, 
        downsell_enabled = :downsell_enabled, 
        downsell_discount_type = :downsell_discount_type, 
        downsell_discount_amount = :downsell_discount_amount,
        checkout_style = :checkout_style
        WHERE id = :id");

    $stmt->execute([
        ':name' => $data->name,
        ':slug' => $data->slug,
        ':description' => $data->description ?? '',
        ':price' => $data->price,
        ':image_url' => $data->image_url ?? '',
        ':active' => $data->active ?? 1,
        ':theme' => $data->theme ?? 'dark',
        ':request_email' => isset($data->request_email) ? ($data->request_email ? 1 : 0) : 1,
        ':request_phone' => isset($data->request_phone) ? ($data->request_phone ? 1 : 0) : 1,
        ':request_name' => isset($data->request_name) ? ($data->request_name ? 1 : 0) : 1,
        ':track_initiate_checkout' => isset($data->track_initiate_checkout) ? ($data->track_initiate_checkout ? 1 : 0) : 1,
        ':track_add_payment_info' => isset($data->track_add_payment_info) ? ($data->track_add_payment_info ? 1 : 0) : 1,
        ':evolution_instance' => $data->evolution_instance ?? '',
        ':evolution_token' => $data->evolution_token ?? '',
        ':evolution_url' => $data->evolution_url ?? '',
        ':deliverable_type' => $data->deliverable_type ?? 'text',
        ':deliverable_text' => $data->deliverable_text ?? '',
        ':deliverable_file' => $data->deliverable_file ?? '',
        ':deliverable_email_subject' => $data->deliverable_email_subject ?? '',
        ':deliverable_email_body' => $data->deliverable_email_body ?? '',
        ':fake_notifications' => isset($data->fake_notifications) ? ($data->fake_notifications ? 1 : 0) : 0,
        ':notification_text' => $data->notification_text ?? '',
        ':top_bar_enabled' => isset($data->top_bar_enabled) ? ($data->top_bar_enabled ? 1 : 0) : 0,
        ':top_bar_text' => $data->top_bar_text ?? '',
        ':top_bar_bg_color' => $data->top_bar_bg_color ?? '#000000',
        ':top_bar_text_color' => $data->top_bar_text_color ?? '#ffffff',
        ':downsell_enabled' => isset($data->downsell_enabled) ? ($data->downsell_enabled ? 1 : 0) : 0,
        ':downsell_discount_type' => $data->downsell_discount_type ?? 'fixed',
        ':downsell_discount_amount' => $data->downsell_discount_amount ?? 0,
        ':checkout_style' => $data->checkout_style ?? 'default',
        ':id' => $data->id
    ]);
    $db->commit();
    echo "SUCCESS";
} catch (Exception $e) {
    $db->rollBack();
    echo "ERROR: " . $e->getMessage();
}
