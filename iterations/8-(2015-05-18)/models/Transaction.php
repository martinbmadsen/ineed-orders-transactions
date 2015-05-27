<?php

require_once __DIR__.'/TransactionState.php';
require_once __DIR__.'/OrderTransactionMediator.php';
require_once __DIR__.'/Item.php';
require_once __DIR__.'/Order.php';

class Transaction {
    /** @var \GuzzleHttp\Client $httpClient */
    private $httpClient;
    /** @var bool $created */
    private $created = False;
    /** @var bool $transactionFromDeal */
    private $transactionFromDeal = False;
    /** @var string $id */
    private $id = "Not set yet, call createTransaction()";
    /** @var int $transactionState  */
    public $transactionState;
    /** @var Order $order */
    private $order;
    /** @var Deal $deal */
    private $deal = null;
    /** @var Vendor $vendor */
    private $vendor = null;
    /** @var Item $item  */
    private $item = null;
    /** @var int $quantity */
    private $quantity;
    /** @var double $unitPrice */
    private $unitPrice;
    /** @var OrderTransactionMediator $mediator */
    private $mediator;

    function __construct() {
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this,$f='__construct'.$i)) {
            call_user_func_array(array($this,$f),$a);
        }
    }

    /**
     * Creating a transaction from an item purchase
     * @param Order $order
     * @param Item $item
     * @param int $quantity
     * @param Vendor $vendor
     * @param \GuzzleHttp\Client $httpClient
     */
    function __construct5(Order $order, Item $item, $quantity, Vendor $vendor, \GuzzleHttp\Client $httpClient) {
        $this->order = $order;
        $this->item = $item;
        $this->vendor = $vendor;
        $this->httpClient = $httpClient;
        $this->unitPrice = $item->price;
        $this->mediator = $this->order->mediator;
        $this->mediator->registerTransaction($this);
        $this->setQuantity($quantity);
    }

    /**
     * Creating a transaction from a deal
     * @param Order $order
     * @param Deal $deal
     * @param $quantity
     * @param \GuzzleHttp\Client $httpClient
     */
    function __construct4(Order $order, Deal $deal, $quantity, \GuzzleHttp\Client $httpClient) {
        $this->httpClient = $httpClient;
        $this->order = $order;
        $this->deal = $deal;
        $this->vendor = $deal->getVendor();
        $this->unitPrice = $deal->getPrice();
        $this->mediator = $this->order->mediator;
        $this->mediator->registerTransaction($this);
        $this->setQuantity($quantity);
        $this->transactionFromDeal = True;
    }
    
    public function getUnitPrice() {
        return $this->unitPrice;
    }
    
    public function getQuantity() {
        return $this->quantity;
    }
    
    public function getTransactionState() {
        return $this->transactionState;
    }
    
    // Begin a Mediator interaction
    public function setQuantity($toQuantity) {
        $this->quantity = $toQuantity;
        $this->mediator->updateTotal();
    }
    
    public function removeTransaction() {
        $this->mediator->unregisterTransaction($this);
        $this->mediator->updateTotal();
    }

    /**
     * Instantiate this instance of the transaction in the db
     */
    public function createinDB() {
        if($this->created) // Don't create a new transaction if this instance already has been created
            return;

        $res = $this->httpClient->post('https://ineed-db.mybluemix.net/api/transactions', [ 'json' => [
            'orderId' => $this->order->id,
            'itemId' => $this->item ? $this->item->id : NULL,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'vendorId' => $this->vendor->getID(),
            'dealId' => $this->deal ? $this->deal->getID() : NULL,
            'dealDiscount' => $this->deal ? $this->deal->getDiscount() : NULL
        ]]);
        $transactionJson = $res->json();
        $this->id = $transactionJson['_id'];
        TransactionState::setState($this, TransactionState::$orderPlaced);
        $this->created = True;
    }

    /**
     * Updates the state of this transaction
     * @param $toState int Use the static {TransactionState} class for the specific classifications of states
     */
    public function updateTransactionState($toState) {
        $this->transactionState = $toState;
        $this->httpClient->put("https://ineed-db.mybluemix.net/api/transactions/{$this->id}/transaction_state", [
            'json' => [
                "currentState" => $toState
        ]]);
    }

    /**
     * Gets the member who initiated this Transaction
     * @return Member who initiated transaction
     */
    public function getMember() {
        return $this->order->member;
    }

    /**
     * Formats this instance to a key-value pair containing only
     * the most important fields. Used when serializing this object
     * as JSON.
     * @return array of key-value pairs, useful for the json_encode() function
     */
    public  function toJsonObject() {
        return array(
            'transactionId'    => $this->id,
            'isDeal'           => $this->transactionFromDeal,
            'transactionState' => TransactionState::toString($this->transactionState),
            'orderId'          => $this->order->id,
            'itemId'           => $this->item ? $this->item->id : null,
            'quantity'         => $this->quantity,
            'unitPrice'        => $this->unitPrice, // TODO: Mediator caluclateTotal() instead?
            'vendorId'         => $this->vendor->getID(),
            'dealId'           => $this->deal ? $this->deal->getID() : null,
            'dealDiscount'     => $this->deal ? $this->deal->getDiscount() : null,
        );
    }

    public static function getTransactionFromId($id, \GuzzleHttp\Client $httpClient)
    {
        $res = $httpClient->get("https://ineed-db.mybluemix.net/api/transactions/{$id}");
        $transactionJson = $res->json();

        $order = Order::getOrderFromId($transactionJson['orderId'], $httpClient);
        if(is_null($order)) // If no order exists for this transaction
            return null;

        // Items, quantity, price, are not part of transactions any longer

        $vendor = new Vendor($transactionJson['vendorId'], $httpClient);
        if(is_null($vendor)) // If vendor cannot be found for this trans
            return null;

        $item = new Item($transactionJson['itemId'], $httpClient);
        $quantity = $transactionJson['quantity'];
        if (array_key_exists('dealId', $transactionJson)) {
            // This transaction is the result of a deal
            $deal = new Deal($transactionJson['dealId'], $httpClient);

            $trans = new Transaction($order, $deal, $quantity, $httpClient);
            $trans->id = $transactionJson['_id'];
            $trans->transactionFromDeal = True;
            $trans->created = True;
            $trans->transactionState = TransactionState::getTransStateForTrans($trans);
            return $trans;
        }
        else {
            // This transaction is the result of an item purchase
            $trans = new Transaction($order, $item, $quantity, $vendor, $httpClient);
            $trans->id = $transactionJson['_id'];
            $trans->created = True;
            return $trans;
        }
    }

    /**
     * Gets the ID of this transaction instance.
     * @return string the hexadecimal ID. Or "Not set yet, call createTransaction()" if this instance has not been
     * committed to the DB.
     */
    public function getID() {
        return $this->id;
    }

    /**
     * @return boolean
     */
    public function isTransactionFromDeal() {
        return $this->transactionFromDeal;
    }
}