<?php

// PLEASE SEE: http://silex.sensiolabs.org/doc/usage.html

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../models/Member.php';
require_once __DIR__.'/../models/Vendor.php';

use Symfony\Component\HttpFoundation\Response;

$app = new Silex\Application();

$app['debug'] = true;

// REGISTER SERVICES

/**
 * Create one instance of our http client, use it
 * across entire application
 */
$app['httpClient'] = $app->share(function () {
    return new \GuzzleHttp\Client();
});

/**
 * Instantiates a new member from the given ID, this
 * fetches it from the Member's team so we have access to
 * payment details
 * @param $email String email belonging to member
 * @return Member object with various fields
 */
$memberProvider = function ($email) use ($app) {
    return new Member($email, $app['httpClient']);
};

$vendorProvider = function ($id) use ($app) {
    return new Vendor($id, $app['httpClient']);
};

/*
$app['user.persister'] = $app->share(
    $app->protect(function($email) use ($app) {
    return new Member($email, $app['user.persist_path']);
}));
*/

// GET ORDER HISTORY
$app->get('api/member/{member}/orders', function (Member $member) use ($app) {
    return $app->json($member);
    //return $app->json($member->getOrderHistory());
})
->convert('member', $memberProvider); // construct member info

// GET TRANSACTION HISTORY
$app->get('api/vendor/{vendor}/transactions', function (Vendor $vendor) use ($app) {
    return $app->json($vendor->getTransactionHistory());
})
->convert('vendor', $vendorProvider);



// ERROR RESPONSE
$app->error(function (\Exception $e, $code) use ($app) {
    // Keep error messages if in debug mode
    if ($app['debug']) {
        return;
    }

    // Logic to handle errors
    switch ($code) {
        case 404:
            $message = 'The requested page could not be found. If you believe this is an error, please email the Orders
            and Transactions team: cse210-orders-transactions@googlegroups.com';
            break;
        default:
            $message = 'We are sorry, but something went terribly wrong. Please email the Orders & Transactions
            team: cse210-orders-transactions@googlegroups.com';
    }

    return new Response($message);
});

$app->run();