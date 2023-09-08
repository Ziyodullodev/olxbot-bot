<?php

class Localbase
{
    public $db;
    public $user_id;
    public $chat_id;
    public $user;
    private $db_lang = 'mysql';
    private $db_host = 'localhost';
    private $dbname;
    private $db_username;
    private $db_password;

    function __construct($chat_id, $db_host, $dbname , $db_username, $db_password)
    {
        $this->db_host = $db_host;
        $this->dbname = $dbname;
        $this->db_username = $db_username;
        $this->db_password = $db_password;
        $this->chat_id = $chat_id;
        $this->connect();
        $this->get_user();
    }

    function connect()
    {
        try {
            // $dbPath = 'db/db.sqlite3';
            $pdo = new PDO($this->db_lang . ":host=" . $this->db_host . ";dbname=" . $this->dbname . ";charset=utf8mb4", $this->db_username, $this->db_password);
        } catch (PDOException $e) {
            echo "Error connecting to database: " . $e->getMessage();
            die();
        }
        // echo "successfully connected";
        $this->db = $pdo;
    }

    function get_user()
    {
        $stmt = $this->db->query("SELECT * FROM users WHERE chat_id = '{$this->chat_id}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->user = $data;
    }

    public function create_user($name, $step, $chatId, $lang = 'uz')
    {
        // Prepare the SQL statement
        $sql = "INSERT INTO `users` (`id`, `chat_id`, `name`, `phone_number`, `step`, `lang`, `create_at`) 
        VALUES (NULL, :chatId, :name, NULL, :step, :lang, CURRENT_TIMESTAMP);";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':step', $step);
        $stmt->bindParam(':lang', $lang);
        $stmt->bindParam(':chatId', $chatId);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    function update_user($data, $user_id = false)
    {
        $placeholders = [];
        foreach ($data as $key => $value) {
            // Escape and sanitize the value
            $escapedValue = $this->db->quote($value);
            $placeholders[] = "$key = $escapedValue";
        }

        $updateFields = implode(', ', $placeholders);

        $sql = "UPDATE users SET $updateFields WHERE chat_id = :chatId";

        $stmt = $this->db->prepare($sql);
        if ($user_id) {
            $stmt->bindValue(':chatId', $user_id);
        } else {
            $stmt->bindValue(':chatId', $this->chat_id);
        }

        return $stmt->execute();
    }

    public function deleteuser()
    {
        $this->db->query("DELETE FROM users WHERE chat_id = '{$this->chat_id}'");
        return "okey";
    }

    public function update_user_location($data)
    {
        $placeholders = [];
        foreach ($data as $key => $value) {
            // Escape and sanitize the value
            $escapedValue = $this->db->quote($value);
            $placeholders[] = "$key = $escapedValue";
        }

        $updateFields = implode(', ', $placeholders);

        $sql = "UPDATE location SET $updateFields WHERE user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $this->user['id']);

        return $stmt->execute();
    }

    public function create_user_location($user_id = null)
    {
        $sql = "INSERT INTO `location` (`id`, `user_id`, `longitude`, `latitude`, `city_id`, `region_id`, `create_at`)
            VALUES (NULL, :user_id, NULL, NULL, NULL, NULL, CURRENT_TIMESTAMP);";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $user_id ?? $this->user['id']);

        return $stmt->execute();
    }

    public function get_user_location($user_id = null)
    {
        $stmt = $this->db->query("SELECT `location`.* , `citys`.`name` as city_name, `regions`.`name` as region_name FROM `location`
          INNER JOIN `citys` ON `location`.`city_id` = `citys`.`id`
          INNER JOIN `regions` ON `location`.`region_id` = `regions`.`id`
          WHERE `location`.`user_id` = '{$user_id}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function get_citys()
    {
        $stmt = $this->db->query("SELECT * FROM `citys`");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    public function get_region($city_id)
    {
        $stmt = $this->db->query("SELECT * FROM `regions` WHERE `city_id` = '{$city_id}'");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    public function get_menu($lang)
    {
        $stmt = $this->db->query("SELECT name_{$lang} as name FROM `menu` WHERE `type` = 'menu'");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $data;
    }

    public function get_command_by_menu_name($menu_name, $lang)
    {
        $stmt = $this->db->query("SELECT command FROM `menu` WHERE `name_{$lang}` = '{$menu_name}' and `type` != 'text'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function create_product($category_id = 1, $title = "Демисезонная куртка Perfecto")
    {
        $user_id = $this->user['id'];
        $description = "none";
        $phone_number = "none";
        $sql = "INSERT INTO `products` (`id`, `title`, `description`, `user_id`, `phone_number`, `category_id`, `create_at`) 
          VALUES (NULL, :title, :description, :user_id, :phone_number, :category_id, CURRENT_TIMESTAMP);";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':phone_number', $phone_number);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    public function update_product($data, $id)
    {
        $placeholders = [];
        foreach ($data as $key => $value) {
            // Escape and sanitize the value
            $escapedValue = $this->db->quote($value);
            $placeholders[] = "$key = $escapedValue";
        }

        $updateFields = implode(', ', $placeholders);

        $sql = "UPDATE products SET $updateFields WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);

        return $stmt->execute();
    }

    public function delete_product($product_id)
    {
        $this->db->query("DELETE FROM `products` WHERE `id` = '{$product_id}'");
    }

    // create img for product fucntion
    public function create_img($product_id, $img_url, $local_url = null)
    {
        $sql = "INSERT INTO `product_image` (`id`, `product_id`, `image_url`, `local_url`, `create_at`) 
          VALUES (NULL, :product_id, :img_url, :local_url, CURRENT_TIMESTAMP);";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':img_url', $img_url);
        $stmt->bindParam(':local_url', $local_url);
        $stmt->execute();
        return $this->db->lastInsertId();
    }

    public function get_count_img($product_id)
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM `product_image` WHERE `product_id` = '{$product_id}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        return $data;
    }

    public function delete_product_and_img($product_id)
    {
        $this->db->query("DELETE FROM `product_image` WHERE `product_id` = '{$product_id}'");
        $this->db->query("DELETE FROM `products` WHERE `id` = '{$product_id}'");
        return "okey";
    }

    public function get_product($product_id)
    {
        $stmt = $this->db->query("SELECT * FROM `products` WHERE `id` = '{$product_id}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data;
    }

    public function get_product_user_chat_id($product_id)
    {
        $stmt = $this->db->query("SELECT `chat_id` FROM `users` WHERE `id` = (SELECT `user_id` FROM `products` WHERE `id` = '{$product_id}')");
        $data = $stmt->fetch(PDO::FETCH_ASSOC)['chat_id'];
        return $data;
    }

    public function get_user_chat_id($user_id)
    {
        $stmt = $this->db->query("SELECT `chat_id` FROM `users` WHERE `id` = '{$user_id}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC)['chat_id'];
        return $data;
    }

    public function get_text($command, $lang)
    {
        $stmt = $this->db->query("SELECT name_{$lang} as name FROM `menu` WHERE `command` = '{$command}'");
        $data = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
        return $data;
    }
}


// $db = new Localbase("848796050");
// var_dump($db->user);
// var_dump($db->get_product(6));

