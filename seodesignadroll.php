<?php
if (!defined('_PS_VERSION_'))
	exit;


class kjellbergadroll extends Module
{
  public function __construct()
  {
    $this->name = 'kjellbergadroll';
    $this->tab = 'kjellberg_adroll';
    $this->version = '1.0';
    $this->author = 'Rasms Kjellberg';
    $this->need_instance = 0;
 
    parent::__construct();
 
    $this->displayName = $this->l('Kjellberg AdRoll');
    $this->description = $this->l('Lägger till AdRoll E-handelsspårning vid orderConfirmation.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
 
    // if (!Configuration::get('MYMODULE_NAME'))      
    //   $this->warning = $this->l('No name provided');
  }

  function install()
  {
    if (!parent::install() ||
        !$this->registerHook('header') ||
        !$this->registerHook('orderConfirmation'))
      return false;
    return true;
  }
  
  function uninstall()
  {
    if (!parent::uninstall())
      return false;
    return true;
  }

  function hookOrderConfirmation($params)
  {
    // Setting parameters
    $parameters = Configuration::getMultiple(array('PS_LANG_DEFAULT'));
    
    $order = $params['objOrder'];
    if (Validate::isLoadedObject($order))
    {
      $deliveryAddress = new Address(intval($order->id_address_delivery));

      $conversion_rate = 1;
      if ($order->id_currency != Configuration::get('PS_CURRENCY_DEFAULT'))
      {
        $currency = new Currency(intval($order->id_currency));
        $conversion_rate = floatval($currency->conversion_rate);
      }

      // Order general information
      $trans = array(
        'id' => intval($order->id),       // order ID - required
            'store' => htmlentities(Configuration::get('PS_SHOP_NAME')), // affiliation or store name
            'total' => Tools::ps_round(floatval($order->total_paid) / floatval($conversion_rate), 2),   // total - required
            'tax' => '0', // tax
            'shipping' => Tools::ps_round(floatval($order->total_shipping) / floatval($conversion_rate), 2),  // shipping
            'city' => addslashes($deliveryAddress->city),   // city
            'state' => '',        // state or province
            'country' => addslashes($deliveryAddress->country) // country
            );

      // Product information
      $products = $order->getProducts();
      foreach ($products AS $product)
      {
        $category = Db::getInstance()->getRow('
                SELECT name FROM `'._DB_PREFIX_.'category_lang` , '._DB_PREFIX_.'product 
                WHERE `id_product` = '.intval($product['product_id']).' AND `id_category_default` = `id_category` 
                AND `id_lang` = '.intval($parameters['PS_LANG_DEFAULT']));
        
        $items[] = array(
          'OrderId' => intval($order->id),                // order ID - required
                'SKU' => addslashes($product['product_id']),    // SKU/code - required
                'Product' => addslashes($product['product_name']),    // product name
                'Category' => addslashes($category['name']),      // category or variation
                'Price' => Tools::ps_round(floatval($product['product_price_wt']) / floatval($conversion_rate), 2), // unit price - required
                'Quantity' => addslashes(intval($product['product_quantity']))  //quantity - required
                );
      }
      $ganalytics_id = 'tjtest';

      $this->context->smarty->assign('items', $items);
      $this->context->smarty->assign('userid', $this->context->customer->id);
      $this->context->smarty->assign('trans', $trans);
      $this->context->smarty->assign('ganalytics_id', $ganalytics_id);
      $this->context->smarty->assign('isOrder', true);

      
      return $this->display(__FILE__, 'adrollCode.tpl');
    }
  }


}