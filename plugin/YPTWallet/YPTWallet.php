<?php

global $global;
require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';
require_once $global['systemRootPath'] . 'plugin/Plugin.abstract.php';
require_once $global['systemRootPath'] . 'plugin/YPTWallet/Objects/Wallet.php';
require_once $global['systemRootPath'] . 'plugin/YPTWallet/Objects/Wallet_log.php';

class YPTWallet extends PluginAbstract {

    public function getDescription() {
        return "Wallet for YouPHPTube";
    }

    public function getName() {
        return "YPTWallet";
    }

    public function getUUID() {
        return "2faf2eeb-88ac-48e1-a098-37e76ae3e9f3";
    }

    public function getEmptyDataObject() {
        $obj = new stdClass();
        $obj->decimalPrecision = 2;
        $obj->wallet_button_title = "My Wallet";
        $obj->add_funds_text = "<h1>Adding money instantly from credit/debit card</h1>Add funds on your Account Balance, to support our videos";
        $obj->add_funds_success_success = "<h1>Thank you,<br> Your funds has been added<h1>";
        $obj->add_funds_success_cancel = "<h1>Ops,<br> You have cancel it<h1>";
        $obj->add_funds_success_fail = "<h1>Sorry,<br> Your funds request has been fail<h1>";
        $obj->currency = "USD";
        $obj->currency_symbol = "$";
        $obj->addFundsOptions = "[5,10,20,50]";
        $obj->showWalletOnlyToAdmin = false;
        
        $plugins = self::getAvailablePlugins();
        foreach ($plugins as $value) {
            $eval = "\$obj->enablePlugin_{$value} = false;";
            eval($eval);
            $dataObj = self::getPluginDataObject($value);
            $obj = (object) array_merge((array) $obj, (array) $dataObj);
        }
        
        return $obj;
    }

    public function getTags() {
        return array('free', 'monetization');
    }

    public function getBalance($users_id) {
        $wallet = $this->getWallet($users_id);
        return $wallet->getBalance();
    }
    
    public function getBalanceText($users_id) {
        $balance = $this->getBalanceFormated($users_id);
        return self::formatCurrency($balance);
    }
    
    public function getBalanceFormated($users_id) {
        $balance = $this->getBalance($users_id);
        $obj = $this->getDataObject();
        return number_format($balance, $obj->decimalPrecision);
    }
    
    static function formatCurrency($value){
        $value = floatval($value);
        $obj = YouPHPTubePlugin::getObjectData('YPTWallet');
        return "{$obj->currency_symbol} ".number_format($value, $obj->decimalPrecision)." {$obj->currency}";
    }

    public function getWallet($users_id) {
        $wallet = new Wallet(0);
        $wallet->setUsers_id($users_id);
        return $wallet;
    }

    public function getHistory($user_id) {
        $wallet = $this->getWallet($user_id);
        $log = new WalletLog(0);
        $rows = $log->getAllFromWallet($wallet->getId());
        return $rows;
    }

    public function addBalance($users_id, $value, $description="", $json_data="{}") {
        $wallet = $this->getWallet($users_id);
        $balance = $wallet->getBalance();
        $balance+=$value;
        $wallet->setBalance($balance);
        $wallet_id = $wallet->save();     
        
        WalletLog::addLog($wallet_id, $value, $description, $json_data);
    }

    public function saveBalance($users_id, $value) {
        if(!User::isAdmin()){
            return false;
        }
        $wallet = $this->getWallet($users_id);
        $balance = $wallet->getBalance();
        $wallet->setBalance($value);
        $wallet_id = $wallet->save();     
        $description = "Admin set your balance, from {$balance} to {$value}";
        WalletLog::addLog($wallet_id, $value, $description);
    }

    public function getHTMLMenuRight() {
        global $global;
        if (!User::isLogged()) {
            return "";
        }
        $obj = $this->getDataObject();
        if($obj->showWalletOnlyToAdmin && !User::isAdmin()){
            return "";
        }
        include $global['systemRootPath'] . 'plugin/YPTWallet/view/menuRight.php';
    }

    static function getAvailablePayments() {
        global $global;
        $dir = self::getPluginDir();
        $plugins = self::getEnabledPlugins();
        foreach ($plugins as $value) {
            $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
            $file = $subdir . "{$value}.php";
            if (is_dir($subdir) && file_exists($file)) {
                require_once $file;
                $eval = "\$obj = new {$value}();\$obj->getAprovalButton();";
                eval($eval);
            }
        }
    }
    
    static function getAvailablePlugins() {
        $dir = self::getPluginDir();
        $dirs = scandir($dir);
        $plugins = array();
        foreach ($dirs as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                $subdir = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR;
                $file = $subdir . "{$value}.php";
                if (is_dir($subdir) && file_exists($file)) {
                    $plugins[] = $value;
                }
            }
        }
        return $plugins;
    }
    
    static function getEnabledPlugins() {
        global $global;
        $plugins = self::getAvailablePlugins();
        $wallet = new YPTWallet();
        $obj = $wallet->getDataObject();
        foreach ($plugins as $key => $value) {
            $eval = "\$val = \$obj->enablePlugin_{$value};";
            eval($eval);
            if(empty($val)){
                unset($plugins[$key]);
            }
        }
        return $plugins;
    }
    
    static function getPluginDataObject($pluginName){
        $dir = self::getPluginDir();
        $file = $dir . "/{$pluginName}/{$pluginName}.php";
        if (file_exists($file)) {
            require_once $file;
            $eval = "\$obj = new {$pluginName}();";
            eval($eval);
            return $obj->getEmptyDataObject();
        }
        return array();
    }
    
    static function getPluginDir(){
        global $global;
        $dir = $global['systemRootPath'] . "plugin/YPTWallet/plugins";
        return $dir;
    }

}
