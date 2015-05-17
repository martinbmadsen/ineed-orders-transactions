<?php

require_once __DIR__.'/Deal.php';
require_once __DIR__.'/Item.php';

class Vendor {
    private $httpClient;
    public $id, $address, $description, $email, $name, $phoneNumber, $state, $type, $items = array(), $deals = array();

    public function __construct($id, \GuzzleHttp\Client $httpClient) {
        $this->httpClient = $httpClient;

        $res = $this->httpClient->get("https://ineed-db.mybluemix.net/api/vendors/{$id}");
        $vendorJson = $res->json();

        $this->id = $vendorJson['_id'];
        $this->address = $vendorJson['address'];
        $this->description = $vendorJson['description'];
        $this->email = $vendorJson['email'];
        $this->name = $vendorJson['name'];
        $this->phoneNumber = $vendorJson['phoneNumber'];
        $this->state = $vendorJson['state'];
        $this->type = $vendorJson['type'];

        //echo json_encode($res['vendors'], JSON_PRETTY_PRINT);
    }

    public function getTransactionHistory() {
        $res = $this->httpClient->get("https://ineed-db.mybluemix.net/api/transactions?vendorId={$this->id}");

        return $res->json();
    }

    public function updateItems() {
        $items = array();
        $res = $this->httpClient->get("http://ineedvendors.mybluemix.net/api/vendor/catalog/{$this->id}");

        foreach ($res->json()['products'] as $itemJson)
            array_push($items, new Item($itemJson['id'], $this, $this->httpClient));
        $this->items = $items;
    }

    public function updateDeals() {
        $deals = array();
        $res = $this->httpClient->get("http://ineed-dealqq.mybluemix.net/findDeal?vendorId={$this->id}");

        foreach($res->json() as $dealJson)
            array_push($deals,
                new Deal($dealJson['_id'], $this, $dealJson['dealName'], $dealJson['discount'], $dealJson['expireDate'],
                    $dealJson['itemSell'], $dealJson['price'], $dealJson['redeemCount'], $dealJson['sendCount'],
                    $dealJson['type'], $this->httpClient
                ));

        $this->deals = $deals;
    }

    /**
     * Static method to get an array of all vendors
     * @param \GuzzleHttp\Client $httpClient To be able to make requests
     * @return array of {Vendor} types, all encapsulated
     */
    public static function getAllVendors(\GuzzleHttp\Client $httpClient) {
        $vendorsJson = $httpClient->get('http://ineedvendors.mybluemix.net/api/vendors')->json()['vendors'];

        $vendors = array();
        foreach($vendorsJson as $vendorJson) {
            array_push($vendors, new Vendor($vendorJson['_id'], $httpClient));
        }
        return $vendors;
    }
}