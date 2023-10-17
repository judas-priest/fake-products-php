<?php
class ProductsModel extends Database
{
    public function getProducts()
    {
        return $this->select("SELECT * FROM products;");
    }
}
