<?php
/*
  Freightquote.com Shipping Module for Magento Commerce

  Copyright (c) 2010 Freightquote.com
  
  Developed by Dynamo Effects - sales [at] dynamoeffects.com

  Released under the Open Software License (OSL 3.0)
*/

  class Freightquote_Shipping_Model_Carrier_Shipping 
    extends Mage_Shipping_Model_Carrier_Abstract {
    
    protected $_code = 'freightquote';
    protected $_request = null;
    protected $_response = null;
    protected $_result = null;
    protected $_gatewayUrl = 'https://b2b.Freightquote.com/WebService/QuoteService.asmx';
    
    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
      if (!$this->getConfigData('active')) {
        Mage::log('The ' . $this->_code . ' shipping method is not active.');
        
        return false;
      }
      
      $this->_request = $this->setRequest($request);
      
      $this->_getQuotes();
      
      //$this->_updateFreeMethodQuote($request);

      return $this->_result;
    }
    
    public function setRequest(Mage_Shipping_Model_Rate_Request $request) {
      $r = new Varien_Object();
      
      if ($request->getLimitMethod()) {
        $r->setService($request->getLimitMethod());
      }
      
      $storeId = Mage::app()->getStore()->getId();
      $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();

      $store = new Mage_Adminhtml_Model_System_Store();
      $storeName = strtolower($store->getStoreName($storeId));

      unset($store, $storeId, $websiteId);
   
      $r->setOrigName($storeName);
      
      $r->setUsername($this->getConfigData('username'));
      $r->setPassword($this->getConfigData('password'));

      if ($request->getFreightquotePackaging()) {
        $packaging = $request->getFreightquotePackaging();
      }
      
      //$r->setPackaging($packaging);
      $r->setServiceType($this->getConfigData('service_type'));
      
      $r->setOrigLocationType($this->getConfigData('origin_location_type'));
      $r->setOrigLiftgate(($this->getConfigData('origin_liftgate') == 1 ? 'true' : 'false'));
      
      $r->setDestLocationType($this->getConfigData('destination_location_type'));
      $r->setDestLiftgate(($this->getConfigData('destination_liftgate') == 1 ? 'true' : 'false'));

      $r->setOrigName(Mage::getStoreConfig('design/head/default_title'));
      if ($request->getOrigPostcode()) {
        $r->setOrigPostcode($request->getOrigPostcode());
      } else {
        $r->setOrigPostcode(Mage::getStoreConfig('shipping/origin/postcode', $this->getStore()));
      }
    
      if ($request->getOrigCountry()) {
        $origCountry = $request->getOrigCountry();
      } else {
        $origCountry = Mage::getStoreConfig('shipping/origin/country_id', $this->getStore());
      }
      $r->setOrigCountry(Mage::getModel('directory/country')->load($origCountry)->getIso2Code());
      
      $r->setOrigDock(($this->getConfigData('origin_dock') ? 'true' : 'false'));
      $r->setOrigConstruction(($this->getConfigData('origin_construction') ? 'true' : 'false'));
      $r->setOrigResidence(($this->getConfigData('origin_residence') ? 'true' : 'false'));


      if ($request->getDestCountryId()) {
        $destCountry = $request->getDestCountryId();
      } else {
        $destCountry = self::USA_COUNTRY_ID;
      }
      
      $r->setDestCountry($destCountry);

      if ($request->getDestPostcode()) {
        if ($destCountry == 'US') {
          $destPostcode = preg_replace('/[^0-9]/', '', $request->getDestPostcode());
          $destPostcode = substr($destPostcode, 0, 5);
        } elseif ($destCountry == 'CA') {
          $destPostcode = preg_replace('/[^0-9A-Z]/', '', strtoupper($request->getDestPostcode()));
          $destPostcode = substr($destPostcode, 0, 6);
        }
        $r->setDestPostcode($destPostcode);
      } else {
        Mage::log('Freightquote.com Shipping Module missing destination postcode.');
        return false;
      }
      
      $r->setDestResidence(($this->getConfigData('destination_residence') == 1 ? 'true' : 'false'));
      $r->setDestConstruction(($this->getConfigData('destination_construction') == 1 ? 'true' : 'false'));
      $r->setDestDock(($this->getConfigData('destination_dock') == 1 ? 'true' : 'false'));

      $weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
      $r->setWeight($weight);
      
      if ($request->getFreeMethodWeight()!= $request->getPackageWeight()) {
        $r->setFreeMethodWeight($request->getFreeMethodWeight());
      }

      $r->setValue($request->getPackageValue());
      $r->setValueWithDiscount($request->getPackageValueWithDiscount());
      
      return $r;
    }
    
    protected function _getQuotes() {
      $r =& $this->_request;
      
      $customerName = trim(Mage::getSingleton('customer/session')->getCustomer()->getName());
      
      /* Set up initial XML structure */
      $requestXml = array(
        'GetRatingEngineQuote' => array(
          'request' => array(
            'CustomerId' => (int)$r->getUserId(),
            'QuoteType' => 'B2B',
            'ServiceType' => $r->getServiceType(),
            'QuoteShipment' => array(
              'ShipmentLabel' => $this->getConfigData('shipment_label'),
              'IsBlind' => ($this->getConfigData('blind_ship') ? 'true' : 'false'),
              'ShipmentLocations' => array(
                'Location' => array(
                  array(
                    'LocationName' => $r->getOrigName(),
                    'LocationType' => $r->getOrigLocationType(),
                    'HasLoadingDock' => $r->getOrigDock(),
                    'RequiresLiftgate' => $r->getOrigLiftgate(),
                    'IsConstructionSite' => $r->getOrigConstruction(),
                    'IsResidential' => $r->getOrigResidence(),
                    'LocationAddress' => array(
                      'PostalCode' => $r->getOrigPostcode(),
                      'CountryCode' => $r->getOrigCountry()
                    )
                  ),
                  array(
                    'LocationName' => ($customerName == '' ? 'Guest' : $customerName),
                    'LocationType' => $r->getDestLocationType(),
                    'HasLoadingDock' => $r->getDestDock(),
                    'RequiresLiftgate' => $r->getDestLiftgate(),
                    'IsConstructionSite' => $r->getDestConstruction(),
                    'IsResidential' => $r->getDestResidence(),
                    'LocationAddress' => array(
                      'PostalCode' => $r->getDestPostcode(),
                      'CountryCode' => $r->getDestCountry()
                    )
                  )
                )
              ),
              'ShipmentProducts' => array(
                'Product' => array()
              )
            )
          ),
          'user' => array(
            'Name' => $r->getUsername(),
            'Password' => $r->getPassword()
          )
        )
      );

      /* Process cart items */ 
      $cartItems = Mage::getModel('checkout/session')->getQuote()->getAllItems();
      $shipmentProducts = array();
      $counter = 1;
      $excluded = 0;
      
      foreach ($cartItems as $item) {
        $product = $item->getProduct();
        
        if ($product->getData('freightquote_enable') == 1) {
          $shipmentProducts[] = array(
            'Class' => $product->getData('freightquote_class'),
            'ProductDescription' => $item->getName(),
            'Weight' => ceil($item->getQty() * (int)$item->getWeight()),
            'Length' => ceil($product->getData('freightquote_length')),
            'Width' => ceil($product->getData('freightquote_width')),
            'Height' => ceil($product->getData('freightquote_height')),
            'PackageType' => ($product->getData('freightquote_packaging') ? $product->getData('freightquote_packaging') : 'Boxes'),
            'DeclaredValue' => round($item->getPrice()),
            'CommodityType' => ($product->getData('freightquote_commodity') ? $product->getData('freightquote_commodity') : 'GeneralMerchandise'),
            'ContentType' => ($product->getData('freightquote_content') ? $product->getData('freightquote_content') : 'NewCommercialGoods'),
            'IsHazardousMaterial' => ($product->getData('freightquote_hzmt') == 1 ? 'true' : 'false'),
            'NMFC' => $product->getData('freightquote_nmfc'),
            'PieceCount' => $item->getQty(),
            'ItemNumber' => $counter
          );
          $counter++;
        } else {
          $excluded++;
        }
      }
      
      $totalProducts = count($shipmentProducts);
      
      /* Don't continue if there are no valid products */
      if ($totalProducts < 1) {
        return false;
      }
      
      /* Maximum 6 products allowed per query, so repeat the query multiple times if necessary */
      $responses = array();
      
      /* Only 6 items allowed per query */
      for ($x = 0; $x < $totalProducts; $x+=6) {
        $productRequest = array();
        
        for ($n = 1; $n <= 6; $n++) {
          $ret = ($n + $x) - 1;
          if (isset($shipmentProducts[$ret])) {
            $productRequest[] = $shipmentProducts[$ret];
          }
        }
        
        $requestXml['GetRatingEngineQuote']['request']['QuoteShipment']['ShipmentProducts']['Product'] = $productRequest;
        
        $response = $this->_executeRequest($requestXml);
        
        if (!$response) {
          Mage::log('Freightquote.com: Invalid response from Freightquote.com');
          return false;
        }

        if (isset($response['GetRatingEngineQuoteResponse'])) {
          $responses[] = $response['GetRatingEngineQuoteResponse'][0]['GetRatingEngineQuoteResult'][0];
        }
      }

      $totalShippingPrice = array(
        'rate' => 0, 
        'shipment_id' => ''
      );
      
      $errors = array();

      foreach ($responses as $quote) {
        if (@is_array($quote['QuoteCarrierOptions'])) {
          if ($totalShippingPrice['shipment_id'] != '') $totalShippingPrice['shipment_id'] .= ' & ';
          $totalShippingPrice['shipment_id'] .= $quote['QuoteId'];
          
          $totalShippingPrice['rate'] += preg_replace('/[^0-9\.]/', '', $quote['QuoteCarrierOptions'][0]['CarrierOption'][0]['QuoteAmount']);
        } elseif (@count($quote['ValidationErrors']) > 0) {
          foreach ($quote['ValidationErrors'][0]['B2BError'] as $errorMsg) {
            $errors[] = $errorMsg['ErrorMessage'];
          }
        }
      }

	  /* If there are any validation errors, don't display this shipping option */
      if (count($errors) > 0) {
        Mage::log('Freightquote.com: Validation errors. No rate was returned.');
        return false;
      }

      
      /* If the shipping price is 0 and no errors were returned, don't display this shipping option */
      if ($totalShippingPrice['rate'] <= 0 && count($errors) < 1) {
        Mage::log('Freightquote.com: Total shipping price returned was 0');
        return false;
      }
      
      //Add price modifier
      if ($this->getConfigData('rate_modifier') > 0) {
        $totalShippingPrice['rate'] = $totalShippingPrice['rate'] * $this->getConfigData('rate_modifier');
      }
      
      //Add handling charges
      if ($this->getConfigData('handling_fee') > 0) {
        if ($this->getConfigData('handling_action') == 'O') {
          $totalShippingPrice['rate'] += $this->getConfigData('handling_fee');
        } elseif ($this->getConfigData('handling_action') == 'P') {
          $totalShippingPrice['rate'] += $this->getConfigData('handling_fee') * $totalProducts;
        }
      }
      
      $this->_result = Mage::getModel('shipping/rate_result');
      
      $method = Mage::getModel('shipping/rate_result_method');
      $method->setCarrier($this->_code);
      $method->setCarrierTitle($this->getConfigData('title'));
      $method->setMethod('default');
      $method->setMethodTitle('Quote #: ' . $totalShippingPrice['shipment_id']);
      $method->setPrice($totalShippingPrice['rate']);
      
      $this->_result->append($method);
      
      return true;
    }
    
    protected function _executeRequest($arr) {
      //Make sure cURL exists
      if (!function_exists('curl_init')) {
        Mage::log('Freightquote.com: cURL not found on the server.');
        return false;
      }
      
      //XML template file used for request
      $xml = $this->_arrayToXml($arr);
      
      //Initialize curl
      $ch = curl_init();
      
      $headers = array(
        'Content-Type: text/xml; charset=utf-8',
        'Content-Length: ' . strlen($xml),
        'SOAPAction: "http://tempuri.org/GetRatingEngineQuote"'
      );

      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_HEADER, 0); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_TIMEOUT, 180);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_URL, $this->_gatewayUrl);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $xml); 
      
      $this->_response = curl_exec($ch);

      if (curl_errno($ch) == 0) {
        curl_close($ch);
        
        //Simple check to make sure that this is a valid XML response
        if (strpos(strtolower($this->_response), 'soap:envelope') === false) {
          Mage::log('Freightquote.com: Invalid response from server.');
          return false;
        }

        if ($this->_response) {
          //Convert the XML into an easy-to-use associative array
          $this->_response = $this->_parseXml($this->_response);       
        }
		
        return $this->_response;
      } else {
        //Collect the error returned
        $curlErrors = curl_error($ch) . ' (Error No. ' . curl_errno($ch) . ')';

        curl_close($ch);
        
        Mage::log('Freightquote.com: ' . $curlErrors);
        return false;
      }
    }
    
    protected function _getPickupDate() {
      $nextDate = date("U")+86400; 

      $workDay = date("w", $nextDate);
      
      if ($workDay > 0 && $workDay < 6) {
        while ($this->_isHoliday($nextDate)) {
          $nextDate += 86400;
          $workDay = date("w", $nextDate);
        }
        return date(DATE_ATOM, $nextDate);
      } else {
        while ($workDay < 1 || $workDay > 5) {
          $nextDate += 86400;
          $workDay = date("w", $nextDate);
          if ($workDay > 0 && $workDay < 6) {
            while ($this->_isHoliday($nextDate)) {
              $nextDate += 86400;
              $workDay = date("w", $nextDate);
            }
            return date(DATE_ATOM, $nextDate);
          }
        }
      }
    }
    
    protected function _isHoliday($date) {
      $fed_holidays = array(
        "2010-01-01", "2010-01-18", "2010-02-15", "2010-05-31",
        "2010-07-05", "2010-09-06", "2010-10-11", "2010-11-11",
        "2010-11-25", "2010-12-24",

        "2010-12-31", "2011-01-17", "2011-02-21", "2011-05-30",
        "2011-07-04", "2011-09-05", "2011-10-10", "2011-11-11",
        "2011-11-24", "2011-12-26",

        "2012-01-02", "2012-01-16", "2012-02-20", "2012-05-28",
        "2012-07-04", "2012-09-03", "2012-10-08", "2012-11-12",
        "2012-11-22", "2012-12-25"
      );
      
      if (in_array(date("Y-m-d", $date), $fed_holidays)) {
        return true;
      }
      
      return false;
    }
    
    protected function _arrayToXml($array, $wrapper = true) {
      $xml = '';
      
      if ($wrapper) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
                 '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n" .
                 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' . "\n" .
               '<soap:Body>' . "\n";
      }
      
      $first_key = true;
      
      foreach ($array as $key => $value) {
        $position = 0;
        
        if (is_array($value)) {
          $is_value_assoc = $this->_isAssoc($value);
          $xml .= "<$key" . ($first_key && $wrapper ? ' xmlns="http://tempuri.org/"' : '') . ">\n";
          $first_key = false;
          
          foreach ($value as $key2 => $value2) {
            if (is_array($value2)) {
              if ($is_value_assoc) {
                $xml .= "<$key2>\n" . $this->_arrayToXml($value2, false) . "</$key2>\n";
              } elseif (is_array($value2)) {
                $xml .= $this->_arrayToXml($value2, false);
                $position++;
                
                if ($position < count($value) && count($value) > 1) $xml .= "</$key>\n<$key>\n";
              }
            } else {
              $xml .= "<$key2>" . $this->_xmlSafe($value2) . "</$key2>\n";
            }
          }
          $xml .= "</$key>\n";
        } else {
        
          $xml .= "<$key>" . $this->_xmlSafe($value) . "</$key>\n";
        }
      }
      
      if ($wrapper) {
        $xml .= '</soap:Body>' . "\n" .
              '</soap:Envelope>';
      }
      
      return $xml;
    }
    
    protected function _isAssoc($array) {
      return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }

    protected function _parseXml($text) {
      $reg_exp = '/<(\w+)[^>]*>(.*?)<\/\\1>/s';
      preg_match_all($reg_exp, $text, $match);
      foreach ($match[1] as $key=>$val) {
        if ( preg_match($reg_exp, $match[2][$key]) ) {
            $array[$val][] = $this->_parseXml($match[2][$key]);
        } else {
            $array[$val] = $match[2][$key];
        }
      }
      return $array;
    }
    
    protected function _xmlSafe($str) {
      //The 5 evil characters in XML
      $str = str_replace('<', '&lt;', $str);
      $str = str_replace('>', '&gt;', $str);
      $str = str_replace('&', '&amp;', $str);
      $str = str_replace("'", '&apos;', $str);
      $str = str_replace('"', '&quot;', $str);

      return $str;
    }
    
    public function getCode($type, $code = '') {
      $codes = array(
        'classes' => array(
          array('value' => '50', 'label' => '50'),
          array('value' => '55', 'label' => '55'),
          array('value' => '60', 'label' => '60'),
          array('value' => '65', 'label' => '65'),
          array('value' => '70', 'label' => '70'),
          array('value' => '77.5', 'label' => '77.5'),
          array('value' => '85', 'label' => '85'),
          array('value' => '92.5', 'label' => '92.5'),
          array('value' => '100', 'label' => '100'),
          array('value' => '110', 'label' => '110'),
          array('value' => '125', 'label' => '125'),
          array('value' => '150', 'label' => '150'),
          array('value' => '175', 'label' => '175'),
          array('value' => '200', 'label' => '200'),
          array('value' => '250', 'label' => '250'),
          array('value' => '300', 'label' => '300'),
          array('value' => '400', 'label' => '400'),
          array('value' => '500', 'label' => '500')
        ),
        
        'packaging' => array(
          array('value' => 'Bags', 'label' => 'Bags'),
          array('value' => 'Bales', 'label' => 'Bales'),
          array('value' => 'Boxes', 'label' => 'Boxes'),
          array('value' => 'Bundles', 'label' => 'Bundles'),
          array('value' => 'Carpets', 'label' => 'Carpets'),
          array('value' => 'Coils', 'label' => 'Coils'),
          array('value' => 'Crates', 'label' => 'Crates'),
          array('value' => 'Cylinders', 'label' => 'Cylinders'),
          array('value' => 'Drums', 'label' => 'Drums'),
          array('value' => 'Pails', 'label' => 'Pails'),
          array('value' => 'Reels', 'label' => 'Reels'),
          array('value' => 'Rolls', 'label' => 'Rolls'),
          array('value' => 'TubesPipes', 'label' => 'Tubes/Pipes'),
          array('value' => 'Motorcycle', 'label' => 'Motorcycle'),
          array('value' => 'ATV', 'label' => 'ATV'),
          array('value' => 'Pallets_48x40', 'label' => 'Pallets 48x40'),
          array('value' => 'Pallets_other', 'label' => 'Pallets Other'),
          array('value' => 'Pallets_120x120', 'label' => 'Pallets 120x120'),
          array('value' => 'Pallets_120x100', 'label' => 'Pallets 120x100'),
          array('value' => 'Pallets_120x80', 'label' => 'Pallets 120x80'),
          array('value' => 'Pallets_europe', 'label' => 'Pallets Europe'),
          array('value' => 'Pallets_48x48', 'label' => 'Pallets 48x48'),
          array('value' => 'Pallets_60x48', 'label' => 'Pallets 60x48')
        ),
        
        'commodities' => array(
          array('value' => 'GeneralMerchandise', 'label' => 'General Merchandise'),
          array('value' => 'Machinery', 'label' => 'Machinery'),
          array('value' => 'HouseholdGoods', 'label' => 'Household Goods'),
          array('value' => 'FragileGoods', 'label' => 'Fragile Goods'),
          array('value' => 'ComputerHardware', 'label' => 'Computer Hardware'),
          array('value' => 'BottledProducts', 'label' => 'Bottled Products'),
          array('value' => 'BottleBeverages', 'label' => 'Bottle Beverages'),
          array('value' => 'NonPerishableFood', 'label' => 'Non Perishable Food'),
          array('value' => 'SteelSheet', 'label' => 'Steel Sheet'),
          array('value' => 'BrandedGoods', 'label' => 'Branded Goods'),
          array('value' => 'PrecisionInstruments', 'label' => 'Precision Instruments'),
          array('value' => 'ChemicalsHazardous', 'label' => 'Chemicals Hazardous'),
          array('value' => 'FineArt', 'label' => 'Fine Art'),
          array('value' => 'Automobiles', 'label' => 'Automobiles'),
          array('value' => 'CellPhones', 'label' => 'Cell Phones'),
          array('value' => 'NewMachinery', 'label' => 'New Machinery'),
          array('value' => 'UsedMachinery', 'label' => 'Used Machinery'),
          array('value' => 'HotTubs', 'label' => 'Hot Tubs')
        ),
        
        'contents' => array(
          array('value' => 'NewCommercialGoods', 'label' => 'New Commercial Goods'),
          array('value' => 'UsedCommercialGoods', 'label' => 'Used Commercial Goods'),
          array('value' => 'HouseholdGoods', 'label' => 'Household Goods'),
          array('value' => 'FragileGoods', 'label' => 'Fragile Goods'),
          array('value' => 'Automobile', 'label' => 'Automobile'),
          array('value' => 'Motorcycle', 'label' => 'Motorcycle'),
          array('value' => 'AutoOrMotorcycle', 'label' => 'Auto or Motorcycle')
        ),
        
        'location_type' => array(
          array('value' => 'Origin', 'label' => 'Origin'),
          array('value' => 'Destination', 'label' => 'Destination'),
          array('value' => 'StopoffPickupDelivery', 'label' => 'Stopoff Pickup Delivery'),
          array('value' => 'StopoffDelivery', 'label' => 'Stopoff Delivery'),
          array('value' => 'StopoffPickup', 'label' => 'Stopoff Pickup'),
        ),
        
        'service_type' => array(
          array('value' => 'LTL', 'label' => 'LTL'),
          array('value' => 'Truckload', 'label' => 'Truckload')
          //array('value' => 'Europe', 'label' => 'Europe'),
          //array('value' => 'Groupage', 'label' => 'Groupage'),
          //array('value' => 'Haulage', 'label' => 'Haulage')
        )
      );
      
      if (!isset($codes[$type])) {
        return false;
      } elseif ('' === $code) {
        return $codes[$type];
      }

      if (!isset($codes[$type][$code])) {
        return false;
      } else {
        return $codes[$type][$code];
      }
    }
  }
?>