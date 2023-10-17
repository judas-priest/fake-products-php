<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(60);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token");

require_once 'config.php';
require_once 'Database.php';



abstract class Api
{
    protected string $apiController = ''; //Имя контроллера
    protected string $method = '';        //GET|POST|PUT|DELETE
    public array $uri = [];               //Форматированные URI
    public array $headers;                //Форматированные заголовки
    protected string $action = '';        //Название метод для выполнения
    public array $errors    = [];         //Массив кодов ошибок и сообщений
    public function __construct()
    {
        //header('Content-Encoding: gzip'); //включение gzip
        header('Content-Type: application/json');
        header_remove('Set-Cookie');
        //Массивы query, body параметров, файлов и заголовков 
        $temp_request_uri = ucwords(trim($_SERVER['REQUEST_URI'], '/'), '/');
        $this->uri = explode('/',  $temp_request_uri);

        $this->headers = array_change_key_case(apache_request_headers());

        //Определение метода запроса
        $this->method = $_SERVER['REQUEST_METHOD'];

        if ($this->method === 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            switch ($_SERVER['HTTP_X_HTTP_METHOD']) {
                case 'DELETE':
                    $this->method = 'DELETE';
                case 'PUT':
                    $this->method = 'PUT';
                default:
                    throw new Exception('Unexpected Header');
            }
        }
    }

    protected function error(string $errorMessage = '', int $errorCode = 500)
    {
        header("HTTP/1.1 $errorCode " . $this->requestStatus($errorCode));
        $message = $errorMessage ? $errorMessage : $this->requestStatus($errorCode);
        $json = json_encode(['error' => $errorCode, 'message' => $message]);

        return ($json); //gzencode
    }
    public function requestStatus(int $code)
    {
        $status = [
            0 => '',
            200 => 'OK',
            204 => 'No Content',
            302 => 'Moved Temporarily',
            400 => 'Bad Request',
            401 => 'Token is invalid or missing',
            403 => 'Access denided',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            412 => 'Idempotency key is duplicate or missing',
            500 => 'Internal Server Error'
        ];
        return ($status[$code]) ? $status[$code] : $status[500];
    }
    protected function getAction()
    {
        switch ($this->method) {
            case 'GET':
                $action = 'Get';
                break;
            case 'POST':
                $action = 'Set';
                break;
            case 'PUT':
                $action = 'Edit';
                break;
            case 'DELETE':
                $action = 'Del';
                break;
            default:
                return $this->error(0, 401);
        }
        if (count($this->uri) === 1) {
            if (preg_replace('~\d~', '', $this->uri[0]) === '') {
                return $action . substr($this->apiController, 0, -1) . 'Action';
            } else return "$action{$this->uri[0]}Action";
        } else if (count($this->uri) > 1) {
            $parts = explode('/', $_GET['r']);
            $controller = $parts[0];
            
            if(count($parts) > 1){
                $parameter = $parts[1];
                $full_name_method = substr($controller, 0, -1);
            }
            else{
                $full_name_method = $controller;
            }
            //var_dump("$action{$full_name_method}Action");exit();

            return "$action{$full_name_method}Action";
        } else return "$action{$this->apiController}Action";
    }
    /*
    * Filtering and sorting GET parameters, POST body and files
    */
    public function param(string $param)
    {
       
    }
    public function run()
    {
        //Сдвиг указателя URI
        array_shift($this->uri);
        define('TIMESTAMP', time());
       
        //Определение действия для обработки
    
            $this->action = $this->getAction();
            //Если метод определен в контроллере
            if (method_exists($this, $this->action)) {
                $response = $this->{$this->action}();
                return $response;
            } else {
                return $this->error(0, 405); //Method Not Allowed
            }
      
    }
}
