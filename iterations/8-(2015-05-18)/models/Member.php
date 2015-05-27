<?php

class Member {
    private $httpClient;
    public $email, $cardNumber, $cardCVC2, $cardExpiration;

    public function __construct($email, \GuzzleHttp\Client $httpClient)  {
        $this->httpClient = $httpClient;
        // TODO: exception handling if member not found, etc (property of $res)
        $res = $httpClient->get("http://ineed-members.mybluemix.net/api/profile?email={$email}");
        $member = $res->json();

        $this->email = $member['email'];

        // TODO: If this information is null, throw error
        $this->cardNumber = $member['creditCard']['cardNumber'];
        $this->cardCVC2 = $member['creditCard']['cardCVC2'];
        $this->cardExpiration = $member['creditCard']['cardExpiration'];

        /*
        if (null === $id) {
            throw new NotFoundHttpException(sprintf('User %d does not exist', $id));
        }
        */
    }

    public function getOrderHistory() {
        return Order::getOrdersgetoneForMember($this, $this->httpClient);
    }
}