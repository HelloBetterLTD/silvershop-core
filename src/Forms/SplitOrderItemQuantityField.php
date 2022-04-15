<?php

namespace SilverShop\Forms;

use SilverShop\Model\OrderItem;
use SilverStripe\Forms\NumericField;

class SplitOrderItemQuantityField extends NumericField
{

    private $orderItem;

    private $showTableHeading = true;

    protected $inputType = 'number';

    public function setOrderItem(OrderItem $orderItem)
    {
        $this->orderItem = $orderItem;
        return $this;
    }

    public function getOrderItem()
    {
        return $this->orderItem;
    }

    public function setShowTableHeading($showTableHeading)
    {
        $this->showTableHeading = $showTableHeading;
        return $this;
    }

    public function getShowTableHeading()
    {
        return $this->showTableHeading;
    }

    public function validate($validator)
    {
        $ret = parent::validate($validator);
        if ($this->orderItem->Quantity < $this->value) {
            $ret = false;
            $validator->validationError(
                $this->name,
                _t(
                    'SilverStripe\\Forms\\NumericField.VALIDATION',
                    "'{value}' is greater than the quantity of the urder",
                    ['value' => $this->value]
                )
            );
        }
        return $ret;
    }
}
