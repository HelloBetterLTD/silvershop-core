<?php

namespace SilverShop\Admin;

use SilverShop\Model\Variation\AttributeType;
use SilverShop\Page\Product;
use SilverShop\Page\ProductCategory;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;

/**
 * Product Catalog Admin
 **/
class ProductCatalogAdmin extends ModelAdmin
{
    private static $url_segment = 'catalog';

    private static $menu_title = 'Catalog';

    private static $menu_priority = 5;

    private static $menu_icon_class = 'silvershop-icon-catalog';

    private static $managed_models = [
        Product::class,
        ProductCategory::class,
        AttributeType::class,
    ];

    private static $model_importers = [
        Product::class => ProductBulkLoader::class
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        /* @var $grid GridField */
        $grid = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass));
        $grid->getConfig()
            ->removeComponentsByType(GridFieldPrintButton::class)
            ->removeComponentsByType(GridFieldExportButton::class);
        if ($this->modelClass != Product::class) {
            $grid->getConfig()
                ->removeComponentsByType(GridFieldImportButton::class);
        }

        return $form;
    }

}
