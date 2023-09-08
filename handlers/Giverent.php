<?php


class Giverent
{

    private $db;
    private $tg;
    private $chat_id;

    public $text;

    function __construct($db, $tg, $chat_id)
    {
        $this->db = $db;
        $this->tg = $tg;
        $this->chat_id = $chat_id;
    }


    function show_category($start = 0, $first = false, $id = false, $back = 'back')
    {

        if ($first) {
            $this->category_keyboard($start, $id, $back);
            $this->tg->send_message("Kategoriyalardan birini tanlang:");
            // $this->db->update_user(['step' => "show_category", 'category_id' => null]);
        } else {
            $this->category_keyboard($start, $id, $back);
            $this->tg->edit_message($this->text);
            // $this->db->update_user(['step' => "show_category"]);
        }
    }

    private function category_keyboard($start = 0, $id, $back)
    {
        $limit = 4;
        $this->text = "test";
        $lang = "uz";
        if (!$id) {
            $categorys = $this->db->db->query("SELECT * FROM categories WHERE category_id IS NULL and active = 1 LIMIT {$limit} OFFSET {$start}")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $categorys = $this->db->db->query("SELECT * FROM categories WHERE category_id = {$id} and active = 1 LIMIT {$limit} OFFSET {$start}")->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!$categorys) {
            $user = $this->db->user;
            if ($user['command'] == "sale") {
                $this->text = "Maxsulot nomini yuboring:";
                $this->db->update_user(['step' => "add_product", 'back' => json_encode(['menu', 0]), 'page' => 0]);
                $this->tg->set_inlineKeyboard([
                    [['text' => "🔙 Orqaga", 'callback_data' => $back]]
                ]);
                return;
            }
            $categorys = $this->db->db->query("SELECT products.title as title_uz,products.title as title_ru, products.id FROM products WHERE category_id = {$id} and active = 1 LIMIT {$limit} OFFSET 0")->fetchAll(PDO::FETCH_ASSOC);
            //  and active = 1
            if ($categorys) {
                $stmt = $this->db->db->query("SELECT COUNT(*) as count FROM products WHERE category_id = {$id} and active = 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $this->text = "Mahsulotlardan birini tanlang:";
                $this->db->update_user(['step' => "show_product", 'page' => 0]);
                $back = "backk";
            } else {
                $this->tg->send_answer("Bu kategoriyada mahsulotlar yo'q");
                exit();
            }
        } else {
            if ($id == null) {
                $stmt = $this->db->db->query("SELECT COUNT(*) as count FROM categories WHERE category_id IS NULL and active = 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            } else {
                $stmt = $this->db->db->query("SELECT COUNT(*) as count FROM categories WHERE category_id = {$id} and active = 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            }
            $this->text = "Kategoriyalardan birini tanlang:";
        }
        $i = 0;
        foreach ($categorys as $key_text) {
            $category_name = $key_text['title_' . $lang];
            $category_id = $key_text['id'];
            $keys[] = [['text' => $category_name, 'callback_data' => $category_id]];
            $i++;
        }


        $this->db->update_user(['total_count' => $result]);
        $pagination_count = ceil($result / $limit);
        $i += $start;
        $n = ceil($i / $limit);
        if ($pagination_count > 1) {
            $pagination = [
                ['text' => "⬅️", 'callback_data' => 'prev'],
                ['text' => $n . " / " . $pagination_count, 'callback_data' => 'pagination_category'],
                ['text' => "➡️", 'callback_data' => 'next']
            ];
            $keys[] = $pagination;
        }
        $keys[] = [['text' => "🔙 Orqaga", 'callback_data' => $back]];

        $this->tg->set_inlineKeyboard($keys);
    }

    function show_product($id = NULL, $start = 0, $action = 'show', $back = "back")
    {
        $sending_media = $this->product_keyboard($start, $id, $action, $back);
        if ($back == "backk") {
            $this->tg->edit_message($this->text);
        } else {
            if ($sending_media) {
                $this->tg->edit_message($this->text);
            } else {
                $this->tg->delete_message();
                $this->tg->send_message("Boshqa mahsulotlarni ko'rish uchun 🔙 Orqaga tugmasini bosing");
            }
        }
    }


    private function product_keyboard($start = 0, $id = null, $action, $back)
    {
        $limit = 4;
        $lang = "uz";
        if ($action == "product") {
            // Retrieve all images for the product
            $stmt = $this->db->db->prepare("SELECT image_url as image FROM product_image WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $products = $this->db->db->query("SELECT 
               pr.title, pr.description, 
               users.name, users.chat_id, users.phone_number,
               cat.title_uz as category_title
               FROM products as pr
               LEFT JOIN users ON users.id = pr.user_id
               LEFT JOIN categories as cat ON cat.id = pr.category_id
               WHERE pr.id = {$id}")->fetch(PDO::FETCH_ASSOC);
            // Initialize an array to store media items
            $mediaItems = [];

            // Fetch all images and create media items
            $i = 0;
            $this->text = "🛍 <b>" . $products['title'] . "</b>\n\n<i>" . $products['description'] . "</i>\n\n" . "📞 Telefon raqam: <b>" . $this->db->user['phone_number'] . "</b>\n\n" . "👤 Sotuvchi: <b>" . $products['name'] . "</b>\n\n" . "📦 Kategoriya: <b>" . $products['category_title'] . "</b>";
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
                            'caption' => $this->text,
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

            $key = [
                [['text' => "🔙 Orqaga", 'callback_data' => $back]]
            ];
            $this->tg->set_inlineKeyboard($key);
            // Check if there are any media items to send
            if (!empty($mediaItems)) {
                //      // Send the media group
                $this->tg->send_media_group($mediaItems);
                return false;
            }
            return true;
        }
        $products = $this->db->db->query("SELECT * FROM products WHERE category_id = {$id} and active = 1 LIMIT {$limit} OFFSET {$start}")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->db->db->query("SELECT COUNT(*) as count FROM products WHERE category_id = {$id} and active = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $this->text = "Mahsulotlardan birini tanlang:";
        $i = 0;
        foreach ($products as $key_text) {
            $product_name = $key_text['title'];
            $product_id = $key_text['id'];
            $keys[] = [['text' => $product_name, 'callback_data' => $product_id]];
            $i++;
        }

        $this->db->update_user(['total_count' => $result]);
        $pagination_count = ceil($result / $limit);
        $i += $start;
        $n = ceil($i / $limit);
        if ($pagination_count > 1) {
            $pagination = [
                ['text' => "⬅️", 'callback_data' => 'prev'],
                ['text' => $n . " / " . $pagination_count, 'callback_data' => 'pagination_product'],
                ['text' => "➡️", 'callback_data' => 'next']
            ];
            $keys[] = $pagination;
        }
        $keys[] = [['text' => "🔙 Orqaga", 'callback_data' => $back]];

        $this->tg->set_inlineKeyboard($keys);
        return true;
    }
}
