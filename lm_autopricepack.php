<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Lm_Autopricepack extends Module {

	const LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE = 'LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE';
    const LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE = 'LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE';
    const LM_AP_AUTOPRICEPACK_SECURE_KEY = 'LM_AP_AUTOPRICEPACK_SECURE_KEY';

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
        return parent::install() && $this->registerHook('actionProductUpdate')
		&& Configuration::updateValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE, 1)
		&& Configuration::updateValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE, 1);
    }

    /**
     * Uninstall the module.
     *
     * @return bool Whether the uninstallation was successful.
     */
    public function uninstall() {
        return parent::uninstall() && $this->unregisterHook('actionProductUpdate')
		&& Configuration::deleteByName(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE)
		&& Configuration::deleteByName(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE)
		&& Configuration::deleteByName(self::LM_AP_AUTOPRICEPACK_SECURE_KEY);
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
		
		$updateOnChildUpdate = Configuration::get(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE);
		$updateOnPackUpdate = Configuration::get(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE);

		if(!$updateOnChildUpdate && !$updateOnPackUpdate){
			PrestaShopLogger::addLog('LM Auto Calculate Product Pack Price Deactivated');
			$this->isSaved = true;
			return;
		}

        $this->isSaved = true;
        $updatedProductId = (int)$params['id_product'];
        $product = new Product($updatedProductId);

        if ($product->getType() != Product::PTYPE_PACK && $updateOnChildUpdate) {
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
        } elseif($product->getType() === Product::PTYPE_PACK && $updateOnPackUpdate) {
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

	/**
	 * Retrieves the content for the module.
	 *
	 * This function checks if the form is submitted and updates the module settings accordingly.
	 * It retrieves the values of the `LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE` and `LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE`
	 * configuration values from the form and updates them using the `Configuration::updateValue()` method.
	 *
	 * After updating the settings, it appends a confirmation message to the output and returns the output
	 * along with the rendered form.
	 *
	 * @return string The content for the module.
	 */
	public function getContent() {
		$output = '';

		if(Tools::isSubmit('submit' . $this->name)) {
            $updateOnChildUpdate = Tools::getValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE);
            $updateOnPackUpdate = Tools::getValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE);

            Configuration::updateValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE, $updateOnChildUpdate);
            Configuration::updateValue(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE, $updateOnPackUpdate);

            $output .= $this->displayConfirmation($this->trans('Settings updated', [], 'Modules.Lm_Autopricepack.Admin'));
        }

        return $output . $this->renderForm();
	}

	/**
	 * Renders the form for module configuration.
	 *
	 * This function generates the form for module configuration and returns the rendered form.
	 * It retrieves the secure key from the configuration and generates a new one if it doesn't exist.
	 * It then generates the URL for the cron job and sets the form fields.
	 * The form includes switches for auto calculating pack price and updating pack price when pack is updated.
	 * It also includes a link to the cron job URL.
	 * The function uses the HelperForm class to generate the form.
	 *
	 * @return string The rendered form.
	 */
	protected function renderForm() {
        $secureKey = Configuration::get(self::LM_AP_AUTOPRICEPACK_SECURE_KEY);
        if (!$secureKey) {
            $secureKey = Tools::passwdGen(16);
            Configuration::updateValue(self::LM_AP_AUTOPRICEPACK_SECURE_KEY, $secureKey);
        }

        $cronUrl = $this->context->link->getModuleLink(
            $this->name, 
            'cron', 
            ['secure_key' => $secureKey], 
            true
        );

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Lm_Autopricepack.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Update pack price when child product is updated', [], 'Modules.Lm_Autopricepack.Admin'),
                        'desc' => $this->trans('If this option is enabled, the module will auto calculate pack price any of its child products are updated.', [], 'Modules.Lm_Autopricepack.Admin'),
                        'name' => self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Update pack price when pack is updated', [], 'Modules.Lm_Autopricepack.Admin'),
                        'desc' => $this->trans('If this option is enabled, the module will auto calculate pack price when pack itself is updated.', [], 'Modules.Lm_Autopricepack.Admin'),
                        'name' => self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'html',
                        'label' => $this->trans('Update Pack Price with CRON Job', [], 'Modules.Lm_Autopricepack.Admin'),
                        'name' => 'cron_job_link',
                        'html_content' => '<div class="alert alert-info alert-link-icon"><a href="' . $cronUrl . '" target="_blank">' . $cronUrl . '</a></div>',
                        'hint' => $this->trans('The cron job URL will be displayed in a new tab.', [], 'Modules.Lm_Autopricepack.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

	protected function getConfigFormValues() {
        return [
            self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE => Configuration::get(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_CHILD_UPDATE, 1),
            self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE => Configuration::get(self::LM_AP_AUTOPRICEPACK_UPDATE_ON_PACK_UPDATE, 1),
        ];
    }
}