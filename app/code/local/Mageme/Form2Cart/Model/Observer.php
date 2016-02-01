<?php

class Mageme_Form2Cart_Model_Observer
{
    public function saveCart(Varien_Event_Observer $observer)
    {
        /** @var VladimirPopov_WebForms_Model_Results $result */
        $result = $observer->getResult();

        /** @var VladimirPopov_WebForms_Model_Webforms $webform */
        $form = $result->getWebform();

        $postData = $result->getData('field');

        // detect that the form should operate with cart
        if (strstr($form->getCode(), "f2c")) {

            /*
             * products array to be added to the cart
             */
            $products = array();

            /*
             * field code naming conventions
             * f2c-product-id-<product id>
             * f2c-product-<product id>-option-<option sku>
             * f2c-product-<product id>-quantity
             */

            // collect product ids
            foreach ($form->getFieldsToFieldsets(true) as $fieldset) {
                /** @var VladimirPopov_WebForms_Model_Fields $field */
                foreach ($fieldset["fields"] as $field) {
                    if (strstr($field->getCode(), "f2c-product-id")) {
                        $product_id = trim(str_replace("f2c-product-id-", "", $field->getCode()));
                        if ($product_id && !in_array($product_id, $products)) $products[$product_id] = array("qty" => 1, "product" => $product_id);
                    }
                }
            }

            // collect custom options and quantities
            foreach ($form->getFieldsToFieldsets(true) as $fieldset) {
                /** @var VladimirPopov_WebForms_Model_Fields $field */
                foreach ($products as $product_id => $data) {
                    $product = Mage::getModel('catalog/product')->load($product_id);
                    foreach ($fieldset["fields"] as $field) {
                        // collect product custom options
                        if (count($product->getOptions()) > 0) {
                            if (strstr($field->getCode(), "f2c-product-{$product_id}-option-")) {
                                $option_sku = trim(str_replace("f2c-product-{$product_id}-option-", "", $field->getCode()));
                                $option_value = $postData[$field->getId()];
                                foreach ($product->getOptions() as $opt) {
                                    // find match with option sku
                                    if ($opt->getSku() == $option_sku) {

                                        if (in_array($field->getType(), array('file', 'image'))) {
                                            $fullpath = $result->getFileFullPath($field->getId(), $postData[$field->getId()]);
                                            $quote_path = DS. 'media' .DS. $result->getRelativePath($field->getId(), $postData[$field->getId()]);
                                            $imgSize = getimagesize($fullpath);
                                            $products[$product_id]["options"][$opt->getId()] = array(
                                                "type" => $_FILES["file_".$field->getId()]["type"],
                                                "size" => $_FILES["file_".$field->getId()]["size"],
                                                "width" => $imgSize[0],
                                                "height" => $imgSize[1],
                                                "quote_path" => $quote_path,
                                                "order_path" => $quote_path,
                                                "fullpath" => $fullpath,
                                                "title" => $postData[$field->getId()],
                                                'secret_key' => substr(md5(file_get_contents($fullpath)), 0, 20)
                                            );
                                        } else {
                                            $products[$product_id]["options"][$opt->getId()] = $option_value;
                                        }
                                    }
                                }
                            }
                        }
                        // collect quantity
                        if (strstr($field->getCode(), "f2c-product-{$product_id}-quantity")) {

                            $quantity = intval($postData[$field->getId()]);

                            if ($quantity > 0) $products[$product_id]["qty"] = $quantity;
                        }
                    }
                }
            }
            if (count($products)) {
                $cart = $this->emptyCart();
                foreach ($products as $product_id => $data) {
                    $product = Mage::getModel('catalog/product')->load($product_id);
                    $cart->addProduct($product, $data);
                }
                $cart->save();
                Mage::getSingleton('checkout/session')->setCartWasUpdated(true);

            }
        }
    }

    public function emptyCart()
    {
        //Get cart
        $cart = Mage::helper('checkout/cart')->getCart();
        $cart->init();

        //Get all items from cart
        $items = $cart->getItems();

        //Loop through all of cart items
        foreach ($items as $item) {
            $itemId = $item->getItemId();
            //Remove items, one by one
            $cart->removeItem($itemId);
        }

        return $cart;

    }
}