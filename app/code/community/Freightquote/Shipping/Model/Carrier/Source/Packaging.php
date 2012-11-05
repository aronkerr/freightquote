<?php
/*
  Freightquote.com Shipping Module for Magento Commerce

  Copyright (c) 2010 Freightquote.com
  
  Developed by Dynamo Effects - sales [at] dynamoeffects.com

  Released under the Open Software License (OSL 3.0)
*/

  class Freightquote_Shipping_Model_Carrier_Source_Packaging extends Mage_Eav_Model_Entity_Attribute_Source_Abstract {
    public function toOptionArray() {
      $freightquote = Mage::getSingleton('freightquote/carrier_shipping');
      
      $arr = $freightquote->getCode('packaging');

      return $arr;
    }
    
    public function getAllOptions() {
      return $this->toOptionArray();
    }
  }
?>