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


    function choice_city($name, $action = false)
    {
        $i = 0;
        $c = 2;
        $step = "start";
        $citys = $this->db->get_citys();
        foreach ($citys as $key => $value) {
            $keys[floor($i / $c)][$i % $c] = ['text' => $value['name'], 'callback_data' => $value['id']];
            $i++;
        }
        $this->tg->set_inlineKeyboard($keys)
            ->send_message("Assalomu alaykum, $name\ntuda suda karochi\n\nQaysi viloyatdansiz?");

        if (!$action) {
            $this->db->update_user(['step' => $step]);
        } else {
            $user_id = $this->db->create_user($name, $step, $this->chat_id);
            $this->db->create_user_location($user_id);
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
            ->edit_message("Qaysi tuman yoki shahardansiz?");

        $this->db->update_user_location(['city_id' => $city_id]);
        $this->db->update_user(['step' => "choice_region"]);
    }

    function choice_region_redirect_menu($region_id)
    {
        $menus = $this->db->get_menu();
        $menu = [];
        foreach ($menus as $key => $value) {
            $menu[] = [$value['name']];
        }
        $this->tg->delete_message()
            ->set_replyKeyboard($menu)
            ->send_message("Kerakli bolimni tanlang:");
        $this->db->update_user(['step' => "menu"]);
        $this->db->update_user_location(['region_id' => $region_id]);
    }

    public function show_profile()
    {
        $user = $this->db->user;

        $location = $this->db->get_user_location($user['id']);
        $phone_number = $user['phone_number'] ?? "Telefon raqam kiritilmagan";
        $this->tg->send_message("Sizning profiliz:\n\nIsm: {$user['name']}\nViloyat: {$location['city_name']}\nTuman: {$location['region_name']}\nTelefon raqam: {$phone_number}");
    }

    public function change_lang($lang)
    {
        $this->db->update_user(['lang' => $lang, 'step' => "menu"]);
    }

    public function lang_keyboard()
    {
        $this->db->update_user(['step' => "lang"]);
        $this->tg->set_replyKeyboard([['🇺🇿 O\'zbekcha', '🇷🇺 Русский']])
            ->send_message("Tilni tanlang\nВыберите язык");
    }


}
