<?php

class Database
{
    protected $db;
    public function __construct()
    {
        try {
            $this->db = new SQLite3('products2.sqlite');
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
    public function select(string $query = '', ?array $params = [])
    {
        try {
            // print_r($query);
            // exit();
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute();
            $data= [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                array_push($data, $row);
            }
            
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
         $this->db->close();
        return false;
    }

    public function update(string $query = '', ?array $params = [])
    {
    }
    public function insert(string $query = '', ?array $params = [])
    {


if (!$result) {
  die('Ошибка выполнения операции INSERT');
}

    }


}
