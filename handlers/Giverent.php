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
                $this->db->update_user(['step' => "add_product", 'back' => json_encode(['menu', $id]), 'page' => 0]);
                $this->tg->delete_message()
                    ->set_replyKeyboard([["🔙 Orqaga"]]);
                $this->tg->send_message($this->text);
                exit();
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
            $key = [
                [['text' => "🔙 Orqaga", 'callback_data' => $back]]
            ];

            $this->tg->set_inlineKeyboard($key);
            $product_photos = send_product_photos($this->db, $id);
            if (!empty($product_photos[0])) {
                //      // Send the media group
                $this->tg->send_media_group($product_photos[0]);
                return false;
            } else {
                $this->text = $product_photos[1];
            }

            // Check if there are any media items to send

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

    function search_product($text = "", $start = 0, $action = 'show', $back = "back")
    {
        $sending_media = $this->search_product_keyboard($start, $text, $action, $back);
        if ($back == "backk") {
            $this->tg->edit_message($this->text);
        } else {
            if ($sending_media) {
                $this->tg->send_message($this->text);
                $this->db->update_user(['step' => "search_product", 'page' => 0, 'back' => json_encode(['menu', $text])]);
            } else {
                $this->tg->delete_message();
                $this->tg->send_message("Boshqa mahsulotlarni ko'rish uchun 🔙 Orqaga tugmasini bosing");
            }
        }
    }


    private function search_product_keyboard($start = 0, $text, $action, $back)
    {
        $limit = 4;
        $lang = "uz";
        if ($action == "product") {
            // Retrieve all images for the product
            $key = [
                [['text' => "🔙 Orqaga", 'callback_data' => $back]]
            ];

            $this->tg->set_inlineKeyboard($key);
            $product_photos = send_product_photos($this->db, $text);
            if (!empty($product_photos[0])) {
                //      // Send the media group
                $this->tg->send_media_group($product_photos[0]);
                return false;
            } else {
                $this->text = $product_photos[1];
            }

            // Check if there are any media items to send

            return true;
        }
        // Define the SQL query with placeholders
        $sql = "SELECT * FROM products WHERE `title` LIKE :search_text AND active = 1 LIMIT :limit OFFSET :start";

        // Prepare the SQL statement
        $stmt = $this->db->db->prepare($sql);

        // Bind the parameters
        $stmt->bindParam(':search_text', "%$text%", PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':start', $start, PDO::PARAM_INT);

        // Execute the statement
        $stmt->execute();

        // Fetch the results
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if there are no results
        if (count($products) === 0) {
            $this->text = "Bunday mahsulot topilmadi";
            return true;
        }
        $sql = "SELECT COUNT(*) as count FROM products WHERE `title` LIKE :search_text AND active = 1";

        // Prepare the SQL statement
        $stmt = $this->db->db->prepare($sql);
        
        // Bind the search_text parameter
        $stmt->bindParam(':search_text', "%$text%", PDO::PARAM_STR);
        
        // Execute the statement
        $stmt->execute();
        
        // Fetch the result
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