<?php

class Products extends Api
{
    protected string $apiController = __CLASS__;
    /* 
     * Авторизация
     * POST
     * http://localhost/Products
     */
    protected function GetProductsAction()
    {
        include_once(PATH . '/Actions/Products/Get/Products.php');
        $action = new GetProducts();
        return $action->Action($this);
    }
    protected function SetProductsAction()
    {
        include_once(PATH . '/Actions/Products/Post/Products.php');
        $action = new SetProducts();
        return $action->Action($this);
    }
    protected function GetProductAction()
    {
        include_once(PATH . '/Actions/Products/Get/Product.php');
        $action = new GetProduct();
        return $action->Action($this);
    }
}
