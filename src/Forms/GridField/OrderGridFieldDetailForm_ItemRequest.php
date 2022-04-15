<?php

namespace SilverShop\Forms\GridField;

use SilverShop\Cart\ShoppingCart;
use SilverShop\Checkout\OrderProcessor;
use SilverShop\Forms\SplitOrderItemQuantityField;
use SilverShop\Model\Order;
use SilverShop\Model\OrderItem;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

class OrderGridFieldDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{

    private static $url_handlers = [
        'split-order' => 'splitOrder'
    ];

    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm',
        'SplitOrderForm',
        'printorder',
        'splitOrder'
    ];

    /**
     * Add print button to order detail form
     */
    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $printlink = $this->Link('printorder') . '?print=1';
        $printwindowjs = <<<JS
            window.open('$printlink', 'print_order', 'toolbar=0,scrollbars=1,location=1,statusbar=0,menubar=0,resizable=1,width=800,height=600,left = 50,top = 50');return false;
JS;
        $actions = $form->Actions();
        $actions->push(
            LiteralField::create(
                'PrintOrder',
                "<button class=\"no-ajax grid-print-button btn action btn-primary font-icon-print\" onclick=\"javascript:$printwindowjs\">"
                . _t('SilverShop\Model\Order.Print', 'Print') . '</button>'
            )
        );

        /* @var $composite CompositeField  */
        $composite = $actions->fieldByName('MajorActions');
        if (!in_array($this->record->Status, ['AdminCancelled', 'MemberCancelled']) && !$this->record->SplitParentID) {
            $composite->push(FormAction::create('doEditOrder', 'Edit Order')
                ->setUseButtonTag(true)->addExtraClass('btn-outline-primary'));
            $composite->push(LiteralField::create(
                'split-order',
                '<a href="'. $this->Link('split-order') . '" class="btn btn-outline-primary">Split Order</a>'
            ));
        }
        return $form;
    }

    /**
     * Render order for printing
     */
    public function printorder()
    {
        Requirements::clear();
        //include print javascript, if print argument is provided
        if (isset($_REQUEST['print']) && $_REQUEST['print']) {
            Requirements::customScript('if(document.location.href.indexOf(\'print=1\') > 0) {window.print();}');
        }
        $title = _t('SilverShop\Model\Order.Invoice', 'Invoice');
        if ($id = $this->popupController->getRequest()->param('ID')) {
            $title .= " #$id";
        }

        return $this->record->customise(
            [
                'SiteConfig' => SiteConfig::current_site_config(),
                'Title'      => $title,
            ]
        )->renderWith('SilverShop\Admin\OrderAdmin_Printable');
    }

    public function doEditOrder($data, $form)
    {
        $cart = ShoppingCart::singleton();
        $cart->clear(true);
        $cart->loadOrderToEdit($this->record);
        $controller = $this->getToplevelController();
        return $controller->redirect(Director::baseURL());
    }

    public function splitOrder($request)
    {
        $controller = $this->getToplevelController();
        $return = $this->customise([
            'Backlink' => $controller->hasMethod('Backlink') ? $controller->Backlink() : $controller->Link(),
            'ItemEditForm' => $this->SplitOrderForm(),
        ])->renderWith($this->getTemplates());

        if ($request->isAjax()) {
            return $return;
        } else {
            return $controller->customise([
                'Content' => $return,
            ]);
        }
    }

    public function SplitOrderForm()
    {
        $fields = FieldList::create();
        $tabSet = TabSet::create('Root');
        $tabSet->push(Tab::create('Main'));
        $fields->push($tabSet);
        $counter = 0;
        $order = $this->record;
        $fields->addFieldToTab(
            'Root.Main',
            LiteralField::create(
                'OrderItems_Start',
                $order->renderWith('SilverShop\Admin\SplitItemsGridStart')
            )
        );
        foreach ($this->record->Items() as $item) {
            $fields->addFieldToTab(
                'Root.Main',
                SplitOrderItemQuantityField::create('OrderItems__' . $item->ID, 'Quantity')
                    ->setOrderItem($item)
                    ->setValue($item->Quantity)
                    ->setShowTableHeading($counter === 0)
                    ->setDescription('Items purchased ' . $item->Quantity)
            );
            $counter += 1;
        }

        $fields->addFieldToTab(
            'Root.Main',
            LiteralField::create(
                'OrderItems_Start',
                $order->renderWith('SilverShop\Admin\SplitItemsGridEnd')
            )
        );

        $actions = FieldList::create();
        $majorActions = CompositeField::create()->setName('MajorActions');
        $majorActions->setFieldHolderTemplate(get_class($majorActions) . '_holder_buttongroup');
        $actions->push($majorActions);
        $majorActions->push(
            FormAction::create('doSplit', 'Split Order')
                ->setUseButtonTag(true)
                ->addExtraClass('btn-outline-primary')
        );
        $majorActions->push(LiteralField::create(
            'goBack',
            sprintf('<a href="%s" class="btn btn-outline-danger">Back</a>', $this->Link('edit'))
        ));

        $form = Form::create($this, 'SplitOrderForm', $fields, $actions);

        $toplevelController = $this->getToplevelController();
        if ($toplevelController && $toplevelController instanceof LeftAndMain) {
            $form->setTemplate([
                'type' => 'Includes',
                'SilverStripe\\Admin\\LeftAndMain_EditForm',
            ]);
            $form->addExtraClass('cms-content cms-edit-form center fill-height flexbox-area-grow');
            $form->setAttribute('data-pjax-fragment', 'CurrentForm Content');
            if ($form->Fields()->hasTabSet()) {
                $form->Fields()->findOrMakeTab('Root')->setTemplate('SilverStripe\\Forms\\CMSTabSet');
                $form->addExtraClass('cms-tabset');
            }
            $form->Backlink = $this->getBackLink();
        }
        return $form;
    }


    public function doSplit($data, Form $form)
    {
        /* @var $order Order */
        $order = $this->record;
        $orderItemsA = [];
        $orderItemsB = [];
        $shoppingCart = ShoppingCart::singleton();

        foreach ($order->Items() as $orderItem) {
            $quantity = $orderItem->Quantity;
            $quantityA = $data['OrderItems__' . $orderItem->ID];
            $quantityB = 0;
            if ($quantityA > $quantity) {
                $quantityA = $quantity;
            }
            if ($quantityA < $quantity) {
                $quantityB = $quantity - $quantityA;
            }

            $orderItemsA[$orderItem->ID] = $quantityA;
            if ($quantityB) {
                $orderItemsB[$orderItem->ID] = $quantityB;
            }
        }

        $splits = [$orderItemsA];
        if (count($orderItemsB)) {
            $splits[] = $orderItemsB;
        }

        $orderReferences = [];
        foreach ($splits as $split) {
            $splitOrder = $shoppingCart->createSplitOrder($order);
            foreach ($order->Items() as $orderItem) { // add order items
                if (!empty($split[$orderItem->ID])) {
                    $newItem = $orderItem->duplicate(false);
                    $newItem->Quantity = $split[$orderItem->ID];
                    $newItem->OrderID = $splitOrder->ID;
                    $newItem->write();
                }
            }
            $splitOrder->calculate();
            $processor = OrderProcessor::create($splitOrder);
            $processor->placeOrder();

            $splitOrder->Status = $order->Status;
            $splitOrder->write();

            $orderReferences[] = '<a href="' . $this->OrderLink($splitOrder, 'edit') . '">"'
                . htmlspecialchars($splitOrder->Reference, ENT_QUOTES)
                . '"</a>';
        }
        $order->Status = 'AdminCancelled';
        $order->write();

        $message = sprintf('Order split to %s', implode(', ', $orderReferences));
        $editForm = $this->ItemEditForm();
        $editForm->sessionMessage($message, 'good', ValidationResult::CAST_HTML);
        return $this->redirectAfterSave(false);
    }

    public function OrderLink($order, $action = 'edit')
    {
        return Controller::join_links(
            $this->gridField->Link('item'),
            $order->ID,
            $action
        );
    }
}
