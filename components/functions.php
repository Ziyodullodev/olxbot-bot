<?php

function setUserConfig($file_path, $key = '', $value = '')
{
    $file = $file_path;
    if (file_exists($file)) {
        $user_data = file_get_contents($file);
        $user_data = json_decode($user_data, TRUE);
    } else {
        $user_data = [];
    }
    $user_data[$key] = $value;
    write_file($file, json_encode($user_data));

    return TRUE;
}

function getUserConfig($file_path, $key = '')
{
    $file = $file_path;
    if (file_exists($file)) {
        $user_data = file_get_contents($file);
        $user_data = json_decode($user_data, TRUE);
    } else {
        $user_data = [];
    }

    if (array_key_exists($key, $user_data)) {
        return $user_data[$key];
    }

    return FALSE;
}

function write_file($path, $data, $mode = 'wb')
{
    if (!$fp = @fopen($path, $mode)) return FALSE;

    flock($fp, LOCK_EX);

    for ($result = $written = 0, $length = strlen($data); $written < $length; $written += $result) {
        if (($result = fwrite($fp, substr($data, $written))) === FALSE) break;
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return is_int($result);
}


function send_product_photos($db, $product_id, $lang = "uz")
{
    $stmt = $db->db->prepare("SELECT image_url as image FROM product_image WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    $products = $db->db->query("SELECT 
               pr.title, pr.description, 
               users.name, users.chat_id, users.phone_number,
               cat.title_uz as category_title
               FROM products as pr
               LEFT JOIN users ON users.id = pr.user_id
               LEFT JOIN categories as cat ON cat.id = pr.category_id
               WHERE pr.id = {$product_id}")->fetch(PDO::FETCH_ASSOC);
    // // Initialize an array to store media items
    $mediaItems = [];

    // Fetch all images and create media items
    $i = 0;
    $text = "🛍 <b>" . $products['title'] . "</b>\n\n<i>" . $products['description'] . "</i>\n\n" . $db->get_text("product_phone_text", $lang)
        . "<b>" . $products['phone_number'] . "</b>\n\n" . $db->get_text("product_name_text", $lang)
        . "<b>" . $products['name'] . "</b>\n\n" . $db->get_text("product_category_text", $lang)
        . "<b>" . $products['category_title'] . "</b>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Check if the 'image' column is not null
        if (!is_null($row['image'])) {
            // Create a media item for each image
            if ($i == 0) {
                $mediaItems[] = [
                    'type' => 'photo',
                    // Specify the media type as photo
                    'media' => $row['image'],
                    // URL or file ID of the image
                    'caption' => $text,
                    'parse_mode' => 'HTML'
                ];
                $i++;
            } else {
                $mediaItems[] = [
                    'type' => 'photo',
                    // Specify the media type as photo
                    'media' => $row['image'],
                    // URL or file ID of the image
                ];
            }
        }
    }

    return [$mediaItems, $text];
}

function get_product_image($product_id, $page = 0)
{
    global $tg, $db;

    $image = $db->get_product_image($product_id, $page);
    if (isset($image[0]['image_url'])){
        $tg->set_inlineKeyboard([
            [
                ['text' => "✏️", 'callback_data' => 'edit-' . $image[0]['id']],
                ['text' => "❌", 'callback_data' => 'delete-' . $image[0]['id']]
            ],
            [
                ['text' => "<<" . $image[1], 'callback_data' => 'old-' . $image[1]],
                ['text' => ">>" . $image[1], 'callback_data' => 'next-' . $image[1]]
            ]
        ]);
        return $image[0]['image_url'];
    }
    return false;
}


function clear_text_to_characters($text)
{
    $text = str_replace(['<', '>', '(', ')', '`', '*', '_', '[', ']', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!', '\'', '"', ':', ';', ','], '', $text);
    return $text;
}
