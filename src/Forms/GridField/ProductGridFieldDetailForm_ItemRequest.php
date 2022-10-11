<?php


namespace SilverShop\Forms\GridField;


use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class ProductGridFieldDetailForm_ItemRequest extends VersionedGridFieldItemRequest
{
    private static $allowed_actions = [
        'ItemEditForm'
    ];

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $form->removeExtraClass('cms-previewable');
        $form->Fields()->removeByName('SilverStripeNavigator');
        return $form;
    }
}
