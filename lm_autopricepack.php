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

    // Log the product update action
    PrestaShopLogger::addLog('Product update hook triggered for product ID: ' . $updatedProductId);

    // Retrieve packs and their items in a single query
    $query = "SELECT p1.id_product_pack, p2.id_product_item 
              FROM " . _DB_PREFIX_ . "pack p1
              LEFT JOIN " . _DB_PREFIX_ . "pack p2 ON p1.id_product_pack = p2.id_product_pack
              WHERE p1.id_product_item = " . $updatedProductId;

    $result = Db::getInstance()->executeS($query);

    if ($result) {
        // $packProducts = [];
        $packId = $result[0]['id_product_pack'];

        PrestaShopLogger::addLog('Products in pack' . print_r($result, true));

        // Logic to update the pack price
        $totalPrice = 0;
        foreach ($result as $product) {
            $product = new Product($product['id_product_item']);

            $priceQuery = "SELECT price FROM " . _DB_PREFIX_ . "product WHERE id_product = " . $product->id;
            $priceResult = Db::getInstance()->getValue($priceQuery);

            $totalPrice += (float)$priceResult;
        }

        if($totalPrice > 0) {
          $product = new Product($packId);
          $product->price = $totalPrice;
          PrestaShopLogger::addLog('Updated price of pack ID: ' . $packId . ' to: ' . $totalPrice);
          $this->isSaved = true;
          
          $product->save();
      }
    } else {
        $this->isSaved = true;
        PrestaShopLogger::addLog('No packs found for product ID: ' . $updatedProductId);
    }
  }
}
