<?php

namespace Ariel\CreateMassOrder\Controller\Adminhtml\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order;

class MassInvoice extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction {

    /**
     * @var InvoiceSender
     */
    protected $_invoiceSender;

    /**
     * @var object
     */
    protected $collectionFactory;

    public function __construct(
        Context $context, 
        Filter $filter, 
        CollectionFactory $collectionFactory,
        InvoiceSender $invoiceSender    
    ) {
        parent::__construct($context, $filter); 
        $this->collectionFactory = $collectionFactory;
        $this->_invoiceSender = $invoiceSender;    
    }

    protected function massAction(AbstractCollection $collection) {

        $countInvoice = 0;

        foreach ($collection->getItems() as $order) {

            // Check if order can be invoiced
            if (!$order->canInvoice()) {
                $this->messageManager->addError(__('Cannot create invoice for order %1', $order->getIncrementId()));
                continue;
            }else{
                 $this->messageManager->addSuccess(__('Created invoice for order %1', $order->getIncrementId()));
            }

            try {

                $invoice = $order->prepareInvoice();
                $invoice->getOrder()->setIsInProcess(true);

                // Offline only
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);

                // Save & set paid
                $invoice->register()->pay();
                $invoice->save();

                // Uncomment if you also want to send an e-mail
                //$this->_invoiceSender->send($invoice);

                // Change order status
                $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
                $order->save();

                $countInvoice++;


            } catch (\Exception $e) {
                $this->messageManager->addError(__('Error creating invoice for order %1', $order->getIncrementId()));
            }
        }    

        if ($countInvoice) {
           $orderId = $this->getRequest()->getParam('order_id');
$new =  $this->_objectManager->create('Magento\Sales\Model\Order')->load($orderId);
           
            $this->messageManager->addSuccess(__('Total created invoice for %1 order(s)', $countInvoice));

          
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->getComponentRefererUrl());
        return $resultRedirect;
    }
}