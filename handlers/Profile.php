<?php


class Profile
{
    public $db;
    public $tg;
    public $chat_id;

    function __construct($db, $tg, $chat_id)
    {
        $this->db = $db;
        $this->tg = $tg;
        $this->chat_id = $chat_id;
    }


    function choice_city($name, $action = false, $edit = false)
    {
        $i = 0;
        $c = 2;
        $step = "start";
        $citys = $this->db->get_citys();
        foreach ($citys as $key => $value) {
            $keys[floor($i / $c)][$i % $c] = ['text' => $value['name'], 'callback_data' => $value['id']];
            $i++;
        }
        $this->tg->set_inlineKeyboard($keys);

        if (!$action) {
            
            $this->db->update_user(['step' => $step]);
            $text = $this->db->get_text("choice_city", $this->db->user['lang']) . $this->tg->get_webhookUpdates()['message']['message_id'];
            if ($edit) {
                $this->tg->edit_message($text, $this->tg->get_webhookUpdates()['message']['message_id']+2);
            } else {
                $this->tg->send_message($text);
            }
        } else {
            $user_id = $this->db->create_user($name, $step, $this->chat_id);
            $this->db->create_user_location($user_id);
            $text = $this->db->get_text("start_text", 'uz');
            $text = str_replace("{name}", $name, $text);
            $this->tg->send_message($text);
            exit();
        }
    }

    function choice_region($city_id)
    {
        $i = 0;
        $c = 2;
        $regions = $this->db->get_region($city_id);
        foreach ($regions as $key => $value) {
            $keys[floor($i / $c)][$i % $c] = ['text' => $value['name'], 'callback_data' => $value['id']];
            $i++;
        }
        $this->tg
            ->set_inlineKeyboard($keys)
            ->edit_message($this->db->get_text("choice_region_text", $this->db->user['lang']));

        $this->db->update_user_location(['city_id' => $city_id]);
        $this->db->update_user(['step' => "choice_region"]);
    }

    function choice_region_redirect_menu($region_id)
    {
        $menus = $this->db->get_menu($this->db->user['lang']);
        $menu = [];
        foreach ($menus as $key => $value) {
            $menu[] = [$value['name']];
        }
        $this->tg->delete_message()
            ->set_replyKeyboard($menu)
            ->send_message($this->db->get_text("menu_text", $this->db->user['lang']));
        $this->db->update_user(['step' => "menu"]);
        $this->db->update_user_location(['region_id' => $region_id]);
    }


    public function show_profile()
    {
        $user = $this->db->user;
        $keyboard = $this->db->db->query("SELECT * FROM `menu` WHERE `type` = 'profile_menu' ORDER BY `location` ASC")->fetchAll(PDO::FETCH_ASSOC);
        $keys = [];
        foreach ($keyboard as $key => $value) {
            $keys[] = [$value['name_'. $user['lang']]];
        }
        $location = $this->db->get_user_location($user['id']);
        $phone_number = $user['phone_number'] ?? "Telefon raqam kiritilmagan";
        $this->tg->set_replyKeyboard($keys)
        ->send_message("Sizning profiliz:\n\nIsm: {$user['name']}\nViloyat: {$location['city_name']}\nTuman: {$location['region_name']}\nTelefon raqam: {$phone_number}");
    }

    public function change_lang($lang)
    {
        $this->db->update_user(['lang' => $lang, 'step' => "menu"]);
    }

    public function lang_keyboard()
    {
        $this->db->update_user(['step' => "lang"]);
        $this->tg->set_replyKeyboard([['🇺🇿 O\'zbekcha'], ['🇷🇺 Русский']])
            ->send_message("Tilni tanlang\nВыберите язык");
    }

    public function my_products($text = "Sizning mahsulotlaringiz")
    {
        $products = $this->db->db->query("SELECT pr.* FROM `products` as pr 
        LEFT JOIN users as us ON pr.user_id = us.id
        WHERE us.id = '{$this->db->user['id']}' and pr.active = 1")->fetchAll(PDO::FETCH_ASSOC);
        $i = 0;
        $c = 2;
        foreach ($products as $key => $value) {
            $keys[floor($i / $c)][$i % $c] = ['text' => $value['title'], 'callback_data' => $value['id']];
            $i++;
        }
        $keys[floor($i / $c)][$i % $c] = ['text' => $this->db->get_text("back_button", $this->db->user['lang']), 'callback_data' => 'back'];
        
        $this->tg->set_inlineKeyboard($keys)
            ->send_message($text);
        
        $this->db->update_user(['step' => 'my_products']);
    }

}
