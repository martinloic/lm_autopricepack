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
      if (!parent::install() || !$this->registerHook('actionProductUpdate')) {
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
      if (!parent::uninstall() || !$this->unregisterHook('actionProductUpdate')) {
          return false;
      }
      return true;
  }

  /**
   * Hook called when a product is updated.
   *
   * @param array $params
   * @return void
   */
  public function hookActionProductUpdate($params) {
    if ($this->isSaved) {
        return;
    }

    $updatedProductId = (int)$params['id_product'];
    
    // Combine queries to reduce database calls
    $packsQuery = "SELECT p1.id_product_pack, p2.id_product_item, p2.quantity 
                   FROM " . _DB_PREFIX_ . "pack p1 
                   JOIN " . _DB_PREFIX_ . "pack p2 ON p1.id_product_pack = p2.id_product_pack 
                   WHERE p1.id_product_item = " . $updatedProductId;

    $packsResult = Db::getInstance()->executeS($packsQuery);

    if ($packsResult) {
        $packs = [];
        foreach ($packsResult as $row) {
            $packs[$row['id_product_pack']][] = $row;
        }

        foreach ($packs as $packId => $items) {
            $totalPrice = 0;
            foreach ($items as $item) {
                $productInPack = new Product($item['id_product_item']);
                $totalPrice += $productInPack->price * $item['quantity'];
            }

            if ($totalPrice > 0) {
                $packProduct = new Product($packId);
                $packProduct->price = $totalPrice;
                PrestaShopLogger::addLog('Updated price of pack ID: ' . $packProduct->id . ' to: ' . $totalPrice);
                $packProduct->save();
            }
        }
    } else {
        PrestaShopLogger::addLog('The product ID : ' . $updatedProductId . ' is not in a pack');
    }

    $this->isSaved = true;
  }
}
