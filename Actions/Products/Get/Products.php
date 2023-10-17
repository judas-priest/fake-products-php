<?php
class GetProducts
{
    public function Action($props)
    {
        require_once(PATH . '/Models/ProductsModel.php');

        $model = new ProductsModel();
        $products = $model->getProducts();
        return $products;
    }
}
