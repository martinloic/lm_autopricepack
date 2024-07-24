<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Lm_Autopricepack extends Module {

  protected $isSaved = false;

  public function __construct() {
      $this->name = 'lm_autopricepack';
      $this->tab = 'advertising_marketing';
      $this->version = '1.0.0';
      $this->author = 'Loïc MARTIN';
      $this->need_instance = 0;
      $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
      $this->bootstrap = true;
      parent::__construct();
      $this->displayName = $this->l('LM Auto Calculate Product Pack Price');
      $this->description = $this->l('Automatically calculates and updates pack price when prices of its items are changed');
  }

  /**
   * Install the module.
   *
   * @return bool Whether the installation was successful.
   */
  public function install() {
      // Register the hooks.
      if (!parent::install() ||
          !$this->registerHook('actionProductUpdate') // Register the actionProductUpdate hook.
      ) {
          return false;
      }
      return true;
  }

  /**
   * Uninstall the module.
   *
   * @return bool Whether the uninstallation was successful.
   */
  public function uninstall() {
      // Unregister the hooks.
      if (!parent::uninstall() ||
          !$this->unregisterHook('actionProductUpdate') // Unregister the actionProductUpdate hook.
      ) {
          return false;
      }
      return true;
  }

  public function hookActionProductUpdate($params) {
    if ($this->isSaved)
      return null;

    $updatedProductId = (int)$params['id_product'];

    $getProductInPack = "SELECT id_product_pack FROM " . _DB_PREFIX_ . "pack WHERE id_product_item = " . $updatedProductId;
    $resultProductInPack =  Db::getInstance()->executeS($getProductInPack);

    if($resultProductInPack) {
      // PrestaShopLogger::addLog('Product update hook triggered for product ID: ' . print_r($resultProductInPack,true));

      foreach($resultProductInPack as $productPack) {
        $getPackQuery = "SELECT id_product_item, quantity FROM " . _DB_PREFIX_ . "pack WHERE id_product_pack = " . $productPack['id_product_pack'];
        $resultPackQuery =  Db::getInstance()->executeS($getPackQuery);

        $totalPrice = 0;
        foreach($resultPackQuery as $product) {
          $productInPack = new Product($product['id_product_item']);
          $totalPrice += $productInPack->price * $product['quantity'];
        }
        // PrestaShopLogger::addLog('Product pack ID : '.$productPack['id_product_pack'] .' price of the pack : ' . $totalPrice);
        if($totalPrice > 0) {
          $product = new Product($productPack['id_product_pack']);
          $product->price = $totalPrice;
          PrestaShopLogger::addLog('Updated price of pack ID: ' . $product->id . ' to: ' . $totalPrice);
          $product->save();
        }
      }

      $this->isSaved = true;
    } else {
      PrestaShopLogger::addLog('The product ID : ' . $updatedProductId . ' is not in a pack');
      $this->isSaved = true;
    }
  }
}
