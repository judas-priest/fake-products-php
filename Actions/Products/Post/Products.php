<?php
class SetProducts
{
    public function Action($props)
    {
        echo exec('whoami');
    $database = new SQLite3('../../products2.sqlite');
    $database->exec("INSERT INTO products (user, phone_number, email, price, date, payment_method, name) VALUES ('Петров',  '+79222222222', 'petrov@email.ru', 968, 1561550400, 0, 'Сковорода')");
    }
}
