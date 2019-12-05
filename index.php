<?php

require_once 'eth.php';
new \GethJsonRpcPhpClient\Eth();

#查询指定帐户余额
//$result = $client->callMethod('eth_getBalance', ['0xf99ce9c17d0b4f5dfcf663b16c95b96fd47fc8ba', 'latest']);
//$result->result = $utils::bigHexToBigDec($result->result);

#创建帐户
//$result = $client->callMethod('personal_newAccount', ['123456']);

#帐户列表
//$result = $client->callMethod('eth_accounts', []);

#发送一笔交易(转帐)
//$value = $utils::toHex(200);
//$result = $client->callMethod('eth_sendTransaction', [{"from": "0x01c7b49da0ce0f4fa0cebb3ce250ad45982e3fdc","to": "0x42e0c64bb629a46386543bc0126b1581c6dde77f","value": $value}]);

#查看交易状态
//$result = $client->callMethod('eth_getTransactionReceipt', ["0x124384debd7bfaf98d19c1d336a255d816b1a1723c5164347d2e13515a66cb26"]);
//$result->result->status = $utils::bigHexToBigDec($result->result->status);

#钱包解锁
//$result = $client->callMethod('personal_unlockAccount', ["0x01c7b49da0ce0f4fa0cebb3ce250ad45982e3fdc", "123456", 30]);

#钱包加锁
//$result = $client->callMethod('personal_lockAccount', ["0x01c7b49da0ce0f4fa0cebb3ce250ad45982e3fdc"]);