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
        return parent::install() && $this->registerHook('actionProductUpdate');
    }

    /**
     * Uninstall the module.
     *
     * @return bool Whether the uninstallation was successful.
     */
    public function uninstall() {
        return parent::uninstall() && $this->unregisterHook('actionProductUpdate');
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

        $this->isSaved = true;
        $updatedProductId = (int)$params['id_product'];
        $product = new Product($updatedProductId);

        if ($product->getType() != Product::PTYPE_PACK) {
            $packsQuery = new DbQuery();
            $packsQuery->select('p1.id_product_pack, p2.id_product_item, p2.quantity')
                ->from('pack', 'p1')
                ->innerJoin('pack', 'p2', 'p1.id_product_pack = p2.id_product_pack')
                ->where('p1.id_product_item = ' . (int)$updatedProductId);

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
                        $packProduct->save();
                        PrestaShopLogger::addLog('Updated price of pack ID: ' . $packProduct->id . ' to: ' . $totalPrice);
                    }
                }
            }
        } else {
            $packQuery = new DbQuery();
            $packQuery->select('id_product_item, quantity')
                ->from('pack')
                ->where('id_product_pack = ' . (int)$updatedProductId);

            $packResult = Db::getInstance()->executeS($packQuery);

            if ($packResult) {
                $totalPrice = 0;
                foreach ($packResult as $pack) {
                    $productInPack = new Product($pack['id_product_item']);
                    $totalPrice += $productInPack->price * $pack['quantity'];
                }

                if ($totalPrice > 0) {
                    $packProduct = new Product($updatedProductId);
                    $packProduct->price = $totalPrice;
                    $packProduct->save();
                    PrestaShopLogger::addLog('Updated price of pack ID: ' . $packProduct->id . ' to: ' . $totalPrice);
                }
            }
        }
    }
}
