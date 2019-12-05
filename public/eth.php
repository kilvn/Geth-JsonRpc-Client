<?php

namespace kilvn\GethJsonRpcPhpClient;

use \Achse\GethJsonRpcPhpClient as Achse;
use function bcadd, bcmul, bcpow, bcmod, bcdiv, bcsub;

class Eth
{
    private $client;

    //是否调试 1是 0否
    public $debug = 0;

    //是否运行  不运行时拒绝一切访问
    public $runing = 1;
    protected $_id = 0;

    //通讯密钥
    const KEYCODE = "oSnei1oqt1bjvee9";

    //以太坊和智能合约开放的接口列表
    private static $method = [
        'eth' => ['eth_getBalance', 'personal_newAccount', 'personal_importRawKey', 'eth_sendTransaction', 'personal_sendTransaction', 'eth_getTransactionReceipt', 'personal_unlockAccount', 'personal_lockAccount', 'eth_getCode', 'eth_gasPrice', 'eth_estimateGas'],
        'token' => ['token_getBalance', 'token_getSyncStatus', 'token_getPeerCount', 'token_getBlockNumber', 'token_estimateGas', 'token_gasPrice', 'token_getTxUseFee', 'token_sendTransaction', 'token_getTransactionReceipt'], /*'token_getNewWaxTransactions',*/
    ];

    private $token_address = '';
    private $agreement = 'eth';
    private $contract_decimals = 18;

    public function __construct($host = 'localhost', $port = 8545)
    {
        define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
        define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
        define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
        define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
        define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);

        if ($this->runing !== 1) {
            self::output(10400, 'Unknown error.');
        }

        $this->args = $_REQUEST;
        if ($this->debug) {
            if (empty($this->args['sign']) || !(self::createSign($this->args) === $this->args['sign'])) {
                self::output(10401, '权限认证失败');
            }
        }

        if (!isset($this->args['agreement'])) $this->args['agreement'] = $this->agreement;

        if (!in_array($this->args['agreement'], ['eth', 'token'])) {
            self::output(10402, '请传入类型');
        }

        if (!isset($this->args['method'])) {
            self::output(10403, '请传入方法名');
        }

        $this->agreement = $this->args['agreement'];

        //检测环境是否支持必备的函数
        $_func = self::check_function(["bcadd", "bcmul", "bcpow", "bcmod", "bcdiv", "bcsub"]);
        if (!empty($_func)) {
            self::output(10501, "服务器不支持 {$_func} 函数");
        }

        try {
            //实例化 以太坊
            if ($this->agreement == 'eth') {
                if (!in_array($this->args['method'], self::$method['eth'])) {
                    self::output(10404, 'Eth method not undefined.');
                }

                require_once dirname(__DIR__) . '/vendor/autoload.php';

                $httpClient = new Achse\JsonRpc\GuzzleClient(new Achse\JsonRpc\GuzzleClientFactory(), $host, $port);
                $this->client = new Achse\JsonRpc\Client($httpClient);
            }

            //实例化 智能合约-代币
            if ($this->agreement == 'contract') {
                if (!in_array($this->args['method'], self::$method['contract'])) {
                    self::output(10404, 'Eth token method not undefined.');
                }

                require_once dirname(__DIR__) . '/contract/Wax.php';

                $decimals_file = realpath(dirname(__DIR__)) . '/contract/decimals.json';
                $decimals = @file_get_contents($decimals_file);
                $decimals = @json_decode($decimals, true);

                if (!self::isValidAddress($this->args['token_address'])) {
                    self::output(10405, '请传入正确的合约地址');
                }

                $this->token_address = $this->args['token_address'];

                //存取代币的位数
                if (empty($decimals[$this->token_address])) {
                    $result = self::_curl("http://api.ethplorer.io/getTokenInfo/{$this->token_address}?apiKey=freekey", [], false, 'GET');
                    $result = @json_decode($result, true);

                    if (isset($result['error'])) {
                        self::output($result['error']['code'], "error", $result['error']['message']);
                    }

                    if (isset($result['decimals']) && $result['decimals'] > 0) {
                        $this->contract_decimals = $result['decimals'];

                        //保存
                        $decimals[$this->token_address] = $result['decimals'];
                        @file_put_contents($decimals_file, json_encode($decimals));
                    }
                }

                $this->client = new \Wax($host, $port, true, $this->token_address);
            }

            $this->args['id'] = ++$this->_id;

            $method = $this->args['method'];
            $this->$method();
        } catch (\Exception $e) {
            self::output(10500, $e->getMessage());
        }
    }

    public function __call($name, $arguments)
    {
        self::output(10500, '不存在的方法名.');
    }

    /**
     * 检测是否支持函数
     * @param string $function_name
     * @return string
     */
    private function check_function($function_name = "")
    {
        $str = "";
        if (is_string($function_name)) {
            if (!empty($function_name) && !function_exists($function_name)) {
                $str = $function_name;
            }
        } elseif (is_array($function_name)) {
            foreach ($function_name as $value) {
                if (!function_exists($value)) {
                    $str .= $value . ',';
                }
            }
            if (strlen($str)) $str = rtrim($str, ',');
        }

        return $str;
    }

    /**
     * 查询代币余额
     * @throws \Exception
     */
    public function token_getBalance()
    {
        self::method_filter();

        if (!self::isValidAddress($this->args['address'])) self::output(10010, "钱包地址格式不正确");

        self::logs($this->args, __METHOD__);

        $result = $this->client->getWaxBalance($this->args['address']);

        self::logs($result, __METHOD__);

        if (intval($result) > 0) {
            $result = (float)$result / self::get_token_wei();
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币同步状态
     * @throws \Exception
     */
    public function token_getSyncStatus()
    {
        self::method_filter();

        $result = $this->client->getSyncStatus();

        self::logs($result, __METHOD__);

        $result = $result ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : false;

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币节点数量
     * @throws \Exception
     */
    public function token_getPeerCount()
    {
        self::method_filter();

        $result = $this->client->getPeerCount();

        self::logs($result, __METHOD__);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币区块高度
     * @throws \Exception
     */
    public function token_getBlockNumber()
    {
        self::method_filter();

        $result = $this->client->getBlockNumber();

        self::logs($result, __METHOD__);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币转账预估Gas
     * @throws \Exception
     */
    public function token_estimateGas()
    {
        self::method_filter();

        if (!self::isValidAddress($this->args['from'])) self::output(10010, "from钱包地址格式不正确");
        if (!self::isValidAddress($this->args['to'])) self::output(10011, "to钱包地址格式不正确");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;

        $this->args['value'] = $this->args['value'] * self::get_token_wei();

        self::logs($this->args, __METHOD__);

        $result = $this->client->getEstimateGas($this->args['from'], $this->args['to'], $this->args['value']);

        self::logs($result, __METHOD__);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        $data = [
            'number' => 0,
            'hex' => '0x0',
        ];

        if (!isset($result['code']) && !empty($result) && is_string($result)) {
            $number = self::HexToDec($result);

            $data['number'] = (int)$number + 292;
            $data['hex'] = self::toHex($data['number']);
        }

        self::output(10000, "success", $data);
    }

    /**
     * 查询代币转账预估Gas price
     * @throws \Exception
     */
    public function token_gasPrice()
    {
        self::method_filter();

        $result = $this->client->getGasPrice();

        self::logs($result, __METHOD__);

        if (!isset($result['code']) && is_string($result)) {
            $result = [
                'hex' => $result,
                'number' => self::HexToDec($result)
            ];
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币转账预估手续费
     */
    public function token_getTxUseFee()
    {
        self::method_filter();

        if (!self::isValidAddress($this->args['from'])) self::output(10010, "from钱包地址格式不正确");
        if (!self::isValidAddress($this->args['to'])) self::output(10011, "to钱包地址格式不正确");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;

        $this->args['value'] = $this->args['value'] * self::get_token_wei();

        self::logs($this->args, __METHOD__);

        $result = self::getTransactionFee($this->args['from'], $this->args['to'], $this->args['value']);

        self::logs($result, __METHOD__);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 获取代币预估交易手续费 [内部复用]
     * @param $from
     * @param $to
     * @param $value
     * @return array|string
     */
    private function getTransactionFee($from, $to, $value)
    {
        ob_clean();
        try {
            $gas = $this->client->getEstimateGas($from, $to, $value);
            $gasPrice = $this->client->getGasPrice();

            $num = [
                'gas' => intval(self::HexToDec($gas)),
                'gasPrice' => intval(self::HexToDec($gasPrice)),
            ];

            $num['gas'] += 292;

            $result = [
                'gas' => [
                    'hex' => self::toHex($num['gas']),
                    'number' => $num['gas']
                ],
                'gasPrice' => [
                    'hex' => $gasPrice,
                    'number' => $num['gasPrice']
                ],
                'fee' => self::wei_to_eth($num['gas'] * $num['gasPrice'])
            ];

            return $result;
        } catch (\Exception $e) {
            return "Error:" . $e->getMessage() . " Code:" . $e->getCode();
        }
    }

    /**
     * 代币转账
     * @throws \Exception
     */
    public function token_sendTransaction()
    {
        self::method_filter();

        if (!self::isValidAddress($this->args['from'])) self::output(10010, "from钱包地址格式不正确");
        if (!self::isValidAddress($this->args['to'])) self::output(10011, "to钱包地址格式不正确");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;
        if (empty($this->args['passphrase'])) self::output(10013, "请传入密码");
        $this->args['parameter'] = $this->args['parameter'] ?? 30;

        $this->args['value'] = $this->args['value'] * self::get_token_wei();

        if (!empty($this->args['gas'])) $args['gas'] = self::toHex(intval($this->args['gas']));
        if (!empty($this->args['gasPrice'])) $args['gasPrice'] = self::toHex(intval($this->args['gasPrice']));
        if (!empty($this->args['nonce'])) $args['nonce'] = self::toHex($this->args['nonce']);

        self::logs($this->args, __METHOD__);

        $unlockAccount = $this->client->unlockAccount($this->args['from'], $this->args['passphrase'], $this->args['parameter']);
        if (!$unlockAccount) {
            self::output(10014, "钱包解锁失败");
        }

        $result = $this->client->sendWax($this->args['from'], $this->args['to'], $this->args['value'], $this->args);

        self::logs($result, __METHOD__);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        self::output(10000, "success", ['result' => $result]);
    }

    /**
     * 查看代币交易状态
     * @throws \Exception
     */
    public function token_getTransactionReceipt()
    {
        self::method_filter();

        if (empty($this->args['transaction_address'])) self::output(10010, "请传入交易ID");

        self::logs($this->args, __METHOD__);

        $result = $this->client->getTransactionReceipt($this->args['transaction_address']);

        if (isset($result['code'])) {
            self::output($result['code'], "error", $result['message']);
        }

        if (isset($result['status'])) {
            $result['status'] = self::HexToDec($result['status']);
        }

        self::logs($result, __METHOD__);

        self::output(10000, "success", $result);
    }

    /**
     * 查询代币事物
     * @throws \Exception
     */
    public function token_getNewWaxTransactions()
    {
        self::method_filter();

        $result = [];
        $filter_id = $this->client->createNewPendingTransactionFilter();
        while (true) {
            $result = $this->client->getNewWaxTransactions($filter_id);
            if (!empty($result)) {
                self::logs($result, __METHOD__);
                continue;
            }

            sleep(1);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询指定帐户余额
     */
    public function eth_getBalance()
    {
        self::method_filter();

        $this->args['parameter'] = $this->args['parameter'] ? $this->args['parameter'] : 'latest';
        if (!self::isValidAddress($this->args['address'])) self::output(10010, "钱包地址格式不正确");

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('eth_getBalance', [$this->args['address'], $this->args['parameter']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        if (!isset($result['error']['code']) && isset($result->result)) {
            $result->result = [
                'hex' => $result->result,
                'number' => self::wei_to_eth(self::HexToDec($result->result))
            ];
        }

        self::output(10000, "success", $result);
    }

    /**
     * 创建帐户
     */
    public function personal_newAccount()
    {
        self::method_filter('post');

        if (empty($this->args['passphrase'])) self::output(10010, "请传入钱包密码");

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('personal_newAccount', [$this->args['passphrase']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 钱包解锁
     */
    public function personal_unlockAccount()
    {
        self::method_filter('post');

        if (empty($this->args['address'])) self::output(10010, "请传入钱包地址");
        if (empty($this->args['passphrase'])) self::output(10011, "请传入钱包密码");
        $this->args['parameter'] = $this->args['parameter'] ?? 30;

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('personal_unlockAccount', [$this->args['address'], $this->args['passphrase'], $this->args['parameter']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 通过EC私钥导入钱包
     */
    public function personal_importRawKey()
    {
        self::method_filter('post');

        if (empty($this->args['privatekey'])) self::output(10010, "请传入EC私钥");
        if (empty($this->args['passphrase'])) self::output(10010, "请传入钱包密码");

        if (substr($this->args['privatekey'], 0, 2) == '0x') {
            $this->args['privatekey'] = substr($this->args['privatekey'], 2, strlen($this->args['privatekey']));
        }

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('personal_importRawKey', [$this->args['privatekey'], $this->args['passphrase']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 钱包加锁
     */
    public function personal_lockAccount()
    {
        self::method_filter('post');

        if (empty($this->args['address'])) self::output(10010, "请传入钱包地址");

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('personal_lockAccount', [$this->args['address']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查询预计需要的Gas
     */
    public function eth_estimateGas()
    {
        self::method_filter('post');

        if (empty($this->args['from'])) self::output(10010, "请传入发送地址");
        if (empty($this->args['to'])) self::output(10011, "请传入接收地址");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;

        $this->args['value'] = self::toHex(intval(self::eth_to_wei($this->args['value'])));

        $send = [
            "from" => $this->args['from'],
            "to" => $this->args['to'],
            "value" => $this->args['value']
        ];

        if (!empty($this->args['gasPrice'])) $send['gasPrice'] = self::toHex(intval($this->args['gasPrice']));

        self::logs($send, __METHOD__);

        $result = $this->client->callMethod('eth_estimateGas', [$send]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        if (isset($result->result)) {
            $result->result = [
                'hex' => $result->result,
                'number' => self::HexToDec($result->result)
            ];
        }

        self::output(10000, "success", $result);
    }

    /**
     * 发送一笔交易(转帐) personal_sendTransaction
     */
    public function personal_sendTransaction()
    {
        self::method_filter('post');

        if (empty($this->args['from'])) self::output(10010, "请传入发送地址");
        if (empty($this->args['to'])) self::output(10011, "请传入接收地址");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;
        if (empty($this->args['passphrase'])) self::output(10013, "请传入密码");

        $this->args['value'] = self::toHex(intval(self::eth_to_wei($this->args['value'])));

        $send = [
            "from" => $this->args['from'],
            "to" => $this->args['to'],
            "value" => $this->args['value']
        ];

        if (!empty($this->args['gas'])) $send['gas'] = self::toHex(intval($this->args['gas']));
        if (!empty($this->args['gasPrice'])) $send['gasPrice'] = self::toHex(intval($this->args['gasPrice']));
        if (!empty($this->args['data'])) $send['nonce'] = self::toHex($this->args['data']);
        if (!empty($this->args['nonce'])) $send['nonce'] = self::toHex($this->args['nonce']);

        self::logs($send, __METHOD__);

        $result = $this->client->callMethod('personal_sendTransaction', [$send, $this->args['passphrase']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 发送一笔交易(转帐) eth_sendTransaction
     */
    public function eth_sendTransaction()
    {
        self::method_filter('post');

        if (empty($this->args['from'])) self::output(10010, "请传入发送地址");
        if (empty($this->args['to'])) self::output(10011, "请传入接收地址");
        if (!(floatval($this->args['value']) > 0)) $this->args['value'] = 0;

        $this->args['value'] = self::toHex(intval(self::eth_to_wei($this->args['value'])));

        $send = [
            "from" => $this->args['from'],
            "to" => $this->args['to'],
            "value" => $this->args['value']
        ];

        if (!empty($this->args['gas'])) $send['gas'] = self::toHex(intval($this->args['gas']));
        if (!empty($this->args['gasPrice'])) $send['gasPrice'] = self::toHex(intval($this->args['gasPrice']));
        if (!empty($this->args['data'])) $send['nonce'] = self::toHex($this->args['data']);
        if (!empty($this->args['nonce'])) $send['nonce'] = self::toHex($this->args['nonce']);

        self::logs($send, __METHOD__);

        $result = $this->client->callMethod('eth_sendTransaction', [$send]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 查看交易状态
     */
    public function eth_getTransactionReceipt()
    {
        self::method_filter();

        if (empty($this->args['transaction_address'])) self::output(10010, "请传入交易ID");

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('eth_getTransactionReceipt', [$this->args['transaction_address']]);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        if (isset($result->result->status)) {
            $result->result->status = self::HexToDec($result->result->status);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 返回wei中每个气体的当前价格。
     */
    public function eth_gasPrice()
    {
        self::method_filter();

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('eth_gasPrice', []);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        if (isset($result->result)) {
            $result->result = [
                'hex' => $result->result,
                'number' => self::HexToDec($result->result)
            ];
        }

        self::output(10000, "success", $result);
    }

    /**
     * 返回给定地址的代码。
     */
    public function eth_getCode()
    {
        self::method_filter();

        if (empty($this->args['address'])) self::output(10010, "请传入钱包地址");

        self::logs($this->args, __METHOD__);

        $result = $this->client->callMethod('eth_getCode', [$this->args['address'], '0x2']);

        self::logs($result, __METHOD__);

        if (isset($result->error)) {
            self::output($result->error->code, "error", $result->error->message);
        }

        self::output(10000, "success", $result);
    }

    /**
     * 请求来源判断
     * @param $method
     */
    protected function method_filter($method = 'post')
    {
        //GET（SELECT）：从服务器取出资源（一项或多项）。
        //POST（CREATE）：在服务器新建一个资源。
        //PUT（UPDATE）：在服务器更新资源（客户端提供改变后的完整资源）。
        //PATCH（UPDATE）：在服务器更新资源（客户端提供改变的属性）。
        //DELETE（DELETE）：从服务器删除资源。
        if (strtolower($method) !== strtolower(REQUEST_METHOD)) {
            $this->error_method();
        }
    }

    /**
     * 空方法
     */
    public function error_method()
    {
        self::output(10400, '错误的请求方式');
    }

    /**
     * 获取请求方式
     * @return string
     */
    public static function get_method()
    {
        return strtolower(REQUEST_METHOD);
    }

    /**
     * 输出json格式消息
     * @param int $code
     * @param string $message
     * @param array $result
     */
    public function output($code = 0, $message = "", $result = [])
    {
        $data = ['code' => $code, 'message' => $message, 'result' => $result];
        if ($this->debug === 1) {
            self::debug($data);
        } else {
            self::ajaxReturn($data);
        }
    }

    /**
     * 生成签名
     * @param $args //要发送的参数
     * @param $key //keycode
     * @return string
     */
    private function createSign($args, $key = '')
    {
        $signPars = ""; //初始化
        ksort($args); //键名升序排序
        $key = empty($key) ? self::KEYCODE : $key;

        foreach ($args as $k => $v) {
            if (!isset($v) || strtolower($k) == "sign") {
                continue;
            }
            $signPars .= $k . "=" . $v . "&";
        }

        $signPars .= "key={$key}";

        $sign = md5($signPars); //md5加密
        $sign = strtoupper($sign); //转为大写
        return $sign; //最终的签名
    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @param int $json_option 传递给json_encode的option参数
     * @return void
     */
    public static function ajaxReturn($data, $type = 'JSON', $json_option = 0)
    {
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data, $json_option));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $handler = 'jsonp';
                exit($handler . '(' . json_encode($data, $json_option) . ');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
            default     :
                // 用于扩展其他返回格式数据
                self::ajaxReturn($data);
        }
    }

    /**
     * 调试参数中的变量并中断程序的执行，参数可以为任意多个,类型任意，如果参数中含有'debug'参数，刚显示所有的调用过程。
     *
     * <code>
     * debug($var1,$obj1,$array1[,]................);
     * debug($var1,'debug');
     * </code>
     */
    public static function debug()
    {
        $args = func_get_args();
        header('Content-type: text/html; charset=utf-8');
        echo "\n<pre>---------------------------------debug 调试信息.---------------------------------\n";
        foreach ($args as $value) {
            if (is_null($value)) {
                echo '[is_null]';
            } elseif (is_bool($value) || empty ($value)) {
                var_dump($value);
            } else {
                print_r($value);
            }
            echo "\n";
        }
        $trace = debug_backtrace();
        $next = array_merge(
            array(
                'line' => '??',
                'file' => '[internal]',
                'class' => null,
                'function' => '[main]'
            ), $trace[0]
        );

        $dir = realpath(__DIR__);
        if (strpos($next['file'], $dir) === 0) {
            $next['file'] = str_replace($dir, "", $next['file']);
        }

        echo "\n---------------------------------debug 调试结束.---------------------------------\n\n文件位置:";
        echo $next['file'] . "\t第" . $next['line'] . "行.</pre>\n";
        if (in_array('debug', $args)) {
            echo "\n<pre>";
            print_r($trace);
        }
        //运行时间
        exit;
    }

    /**
     * @see http://stackoverflow.com/questions/1273484/large-hex-values-with-php-hexdec
     *
     * @param string $hex
     * @return string
     */
    public static function HexToDec(string $hex): string
    {
        $dec = '0';
        $len = \strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(\strval(hexdec($hex[$i - 1])), bcpow('16', \strval($len - $i))));
        }

        return $dec;
    }

    public static function toHex($input)
    {
        if (is_numeric($input)) {
            $hexStr = self::largeDecHex($input);
        } elseif (is_string($input)) {
            $hexStr = self::strToHex($input);
        } else {
            throw new \InvalidArgumentException($input . ' is not a string or number.');
        }
        return '0x' . $hexStr;
    }

    private static function largeDecHex($dec)
    {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last) . $hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);
        return $hex;
    }

    private static function strToHex($string)
    {
        $hex = unpack('H*', $string);
        return array_shift($hex);
    }

    /**
     * 日志记录
     * @param $msg
     * @param string $method
     */
    private static function logs($result, $method = "")
    {
        if (is_object($result)) {
            $_error = [];
            if (isset($result->error)) {
                $_error = [
                    'message' => $result->error->message,
                    'code' => $result->error->code,
                ];
            }

            $result = [
                'id' => $result->id,
                'jsonrpc' => $result->jsonrpc,
            ];

            if (isset($result->result)) {
                $result['result'] = $result->result;
            }

            if (count($_error)) {
                $result['error'] = $_error;
            }
        }

        $path = realpath(dirname(__DIR__)) . "/run.log";
        $_method = !empty($method) ? "[方法名:{$method}]\r\n" : "\r\n";
        $result = is_array($result) ? json_encode($result) : $result;
        $content = date("Y-m-d H:i:s", time()) . " {$_method}" . $result . "\r\n\n";
        @file_put_contents($path, $content, FILE_APPEND);
    }

    /**
     * 以太坊钱包地址是否合法
     * Returns true if provided string is a valid ethereum address.
     *
     * @param string $address Address to check
     * @return bool
     */
    protected function isValidAddress($address)
    {
        return (is_string($address)) ? preg_match("/^0x[0-9a-fA-F]{40}$/", $address) : false;
    }

    /**
     * 以太坊交易hash是否合法
     * Returns true if provided string is a valid ethereum tx hash.
     *
     * @param string $hash Hash to check
     * @return bool
     */
    protected function isValidTransactionHash($hash)
    {
        return (is_string($hash)) ? preg_match("/^0x[0-9a-fA-F]{64}$/", $hash) : false;
    }

    private static function eth_to_wei($int)
    {
        return (float)$int * 1000000000000000000;
    }

    private static function wei_to_eth($int)
    {
        return (float)$int / 1000000000000000000;
    }

    private function get_token_wei()
    {
        $num = 10000000000;

        if ($this->contract_decimals < 18) {
            $num = $num / pow(10, (18 - $this->contract_decimals));
        }

        return $num;
    }

    /**
     * 网络请求
     * @param $url
     * @param null $data
     * @param bool $json
     * @param string $method
     * @param int $timeout
     * @param array $header
     * @return mixed
     */
    protected function _curl($url, $data = null, $json = false, $method = 'POST', $timeout = 30, $header = [])
    {
        $ssl = substr(trim($url), 0, 8) == "https://" ? true : false;
        $ch = curl_init();
        $fields = $data;
        $headers = [];
        if ($json && is_array($data)) {
            $fields = json_encode($data);
            $headers = [
                "Content-Type: application/json",
                'Content-Length: ' . strlen($fields),
            ];
        }

        if (!empty($header)) {
            $headers = array_merge($header, $headers);
        }

        $opt = [
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($ssl) {
            $opt[CURLOPT_SSL_VERIFYHOST] = 1;
            $opt[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}